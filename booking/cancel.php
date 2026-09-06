<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/booking_groups.php';

require_login();

if (!is_post_request()) {
    http_response_code(405);
    exit('Phương thức không được hỗ trợ.');
}

verify_csrf();
$booking_id = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);

if (!$booking_id) {
    set_flash('error', 'Mã đặt sân không hợp lệ.');
    redirect('booking/history.php');
}

$bookings = find_booking_group($conn, $booking_id, current_user_id());

if (!$bookings || !booking_group_all_status($bookings, 'pending')) {
    set_flash('error', 'Nhóm đặt sân này không thể hủy.');
    redirect('booking/history.php');
}

$booking_ids = booking_group_ids($bookings);
$placeholders = implode(',', array_fill(0, count($booking_ids), '?'));

try {
    $conn->beginTransaction();

    $update_stmt = $conn->prepare(
        "UPDATE bookings
         SET booking_status = 'cancelled'
         WHERE booking_id IN ($placeholders)
           AND user_id = ?
           AND booking_status = 'pending'
           AND booking_date >= CURRENT_DATE"
    );
    $update_stmt->execute(array_merge($booking_ids, [current_user_id()]));

    if ($update_stmt->rowCount() !== count($booking_ids)) {
        throw new RuntimeException('Not all bookings could be cancelled.');
    }

    $payment_stmt = $conn->prepare(
        "UPDATE payments
         SET payment_status = 'failed'
         WHERE booking_id IN ($placeholders) AND payment_status = 'pending'"
    );
    $payment_stmt->execute($booking_ids);

    $conn->commit();
    set_flash('success', 'Đã hủy ' . count($booking_ids) . ' khung giờ trong nhóm.');
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Cancel booking group failed: ' . $e->getMessage());
    set_flash('error', 'Không thể hủy nhóm đặt sân. Vui lòng thử lại.');
}

redirect('booking/history.php');
