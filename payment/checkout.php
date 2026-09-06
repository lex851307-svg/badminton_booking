<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/booking_groups.php';

require_login();

$request_data = is_post_request() ? $_POST : $_GET;
$booking_id = filter_var($request_data['booking_id'] ?? null, FILTER_VALIDATE_INT);

if (!$booking_id) {
    set_flash('error', 'Mã đặt sân không hợp lệ.');
    redirect('booking/history.php');
}

$bookings = find_booking_group($conn, $booking_id, current_user_id());

if (!$bookings || booking_group_has_status($bookings, 'cancelled')) {
    set_flash('error', 'Không tìm thấy nhóm đặt sân cần thanh toán.');
    redirect('booking/history.php');
}

$all_paid = true;
foreach ($bookings as $booking) {
    if ($booking['payment_status'] !== 'paid') {
        $all_paid = false;
        break;
    }
}

if ($all_paid) {
    redirect('payment/receipt.php?booking_id=' . $booking_id);
}

$first_booking = $bookings[0];
$total_price = booking_group_total($bookings);
$allowed_methods = ['cash', 'bank_transfer', 'qr_code'];
$error = '';

if (is_post_request()) {
    verify_csrf();
    $payment_method = $_POST['payment_method'] ?? '';

    if (!in_array($payment_method, $allowed_methods, true)) {
        $error = 'Vui lòng chọn phương thức thanh toán.';
    } elseif (!booking_group_all_status($bookings, 'pending')) {
        $error = 'Nhóm đặt sân không còn ở trạng thái chờ thanh toán.';
    } else {
        $booking_ids = booking_group_ids($bookings);
        $placeholders = implode(',', array_fill(0, count($booking_ids), '?'));

        try {
            $conn->beginTransaction();

            $lock_stmt = $conn->prepare(
                "SELECT booking_id, total_price, booking_status
                 FROM bookings
                 WHERE booking_id IN ($placeholders) AND user_id = ?
                 FOR UPDATE"
            );
            $lock_stmt->execute(array_merge($booking_ids, [current_user_id()]));
            $locked_bookings = $lock_stmt->fetchAll();

            if (count($locked_bookings) !== count($booking_ids)) {
                throw new RuntimeException('Booking group changed.');
            }

            foreach ($locked_bookings as $locked_booking) {
                if ($locked_booking['booking_status'] !== 'pending') {
                    throw new RuntimeException('Booking group is no longer pending.');
                }
            }

            $is_paid = $payment_method !== 'cash';
            $payment_status = $is_paid ? 'paid' : 'pending';
            $group_transaction = $is_paid
                ? 'PAY' . date('ymdHis') . random_int(100, 999)
                : null;
            $paid_at = $is_paid ? date('Y-m-d H:i:s') : null;
            $payment_stmt = $conn->prepare(
                "INSERT INTO payments (
                    booking_id, payment_method, amount, payment_status,
                    transaction_code, paid_at
                 ) VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    payment_method = VALUES(payment_method),
                    amount = VALUES(amount),
                    payment_status = VALUES(payment_status),
                    transaction_code = VALUES(transaction_code),
                    paid_at = VALUES(paid_at)"
            );

            foreach ($locked_bookings as $locked_booking) {
                $payment_stmt->execute([
                    $locked_booking['booking_id'],
                    $payment_method,
                    $locked_booking['total_price'],
                    $payment_status,
                    $group_transaction,
                    $paid_at,
                ]);
            }

            $update_stmt = $conn->prepare(
                "UPDATE bookings
                 SET booking_status = 'confirmed'
                 WHERE booking_id IN ($placeholders)"
            );
            $update_stmt->execute($booking_ids);

            $conn->commit();
            set_flash(
                'success',
                $is_paid ? 'Thanh toán nhóm thành công.' : 'Đã xác nhận thanh toán tại sân.'
            );
            redirect('payment/receipt.php?booking_id=' . $booking_id);
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Group checkout failed: ' . $e->getMessage());
            $error = 'Không thể xử lý thanh toán. Vui lòng thử lại.';
        }
    }
}

$page_title = 'Thanh toán';
$extra_css = ['payment.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="payment-section">
    <div class="container payment-layout">
        <div class="payment-card">
            <span class="eyebrow eyebrow-dark">Thanh toán an toàn</span>
            <h1>Chọn phương thức thanh toán</h1>
            <p>Thanh toán một lần cho <?= count($bookings) ?> khung giờ trong nhóm.</p>

            <?php if ($error): ?><div class="form-error" role="alert"><?= e($error) ?></div><?php endif; ?>

            <form method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="booking_id" value="<?= (int) $booking_id ?>">
                <div class="payment-methods">
                    <label class="payment-method"><input type="radio" name="payment_method" value="qr_code" required><span class="method-icon">▦</span><span><strong>Quét mã QR</strong><small>Xác nhận thanh toán ngay</small></span></label>
                    <label class="payment-method"><input type="radio" name="payment_method" value="bank_transfer"><span class="method-icon">🏦</span><span><strong>Chuyển khoản ngân hàng</strong><small>Mô phỏng chuyển khoản thành công</small></span></label>
                    <label class="payment-method"><input type="radio" name="payment_method" value="cash"><span class="method-icon">💵</span><span><strong>Thanh toán tại sân</strong><small>Thanh toán khi đến nhận sân</small></span></label>
                </div>
                <button class="button button-primary payment-submit" type="submit">Xác nhận</button>
            </form>
        </div>

        <aside class="order-card">
            <h2>Thông tin nhóm đặt sân</h2>
            <dl>
                <div><dt>Sân</dt><dd><?= e($first_booking['court_name']) ?></dd></div>
                <div><dt>Khu vực</dt><dd><?= e($first_booking['location']) ?></dd></div>
                <div><dt>Ngày</dt><dd><?= e(date('d/m/Y', strtotime($first_booking['booking_date']))) ?></dd></div>
                <div><dt>Số khung giờ</dt><dd><?= count($bookings) ?></dd></div>
            </dl>
            <div class="order-slot-list">
                <?php foreach ($bookings as $booking): ?>
                    <span><?= e(substr($booking['start_time'], 0, 5)) ?>–<?= e(substr($booking['end_time'], 0, 5)) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="order-total"><span>Tổng tiền</span><strong><?= number_format($total_price, 0, ',', '.') ?>đ</strong></div>
        </aside>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
