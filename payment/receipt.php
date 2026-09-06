<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/booking_groups.php';

require_login();

$booking_id = filter_var($_GET['booking_id'] ?? null, FILTER_VALIDATE_INT);

if (!$booking_id) {
    redirect('booking/history.php');
}

$bookings = find_booking_group(
    $conn,
    $booking_id,
    is_admin() ? null : current_user_id()
);

if (!$bookings || empty($bookings[0]['payment_id'])) {
    set_flash('error', 'Không tìm thấy thông tin thanh toán.');
    redirect('booking/history.php');
}

$first_booking = $bookings[0];
$total_price = booking_group_total($bookings);
$method_labels = [
    'cash' => 'Thanh toán tại sân',
    'bank_transfer' => 'Chuyển khoản ngân hàng',
    'qr_code' => 'Quét mã QR',
];

$all_paid = true;
foreach ($bookings as $booking) {
    if ($booking['payment_status'] !== 'paid') {
        $all_paid = false;
        break;
    }
}

$page_title = $all_paid ? 'Hóa đơn thanh toán' : 'Phiếu đặt sân';
$extra_css = ['payment.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="receipt-section">
    <div class="container receipt-wrapper">
        <article class="receipt-card">
            <header class="receipt-header">
                <div><span class="receipt-logo">🏸</span><h1><?= $all_paid ? 'Hóa đơn thanh toán' : 'Phiếu đặt sân' ?></h1><p>Badminton Booking</p></div>
                <span class="receipt-status <?= $all_paid ? 'paid' : 'pending' ?>"><?= $all_paid ? 'Đã thanh toán' : 'Thanh toán tại sân' ?></span>
            </header>

            <div class="receipt-meta">
                <div><small>Mã nhóm</small><strong><?= e($first_booking['booking_code']) ?></strong></div>
                <div><small>Ngày tạo</small><strong><?= e(date('d/m/Y H:i', strtotime($first_booking['created_at']))) ?></strong></div>
            </div>

            <div class="receipt-section-block">
                <h2>Khách hàng</h2>
                <p><?= e($first_booking['full_name']) ?></p>
                <p><?= e($first_booking['email']) ?></p>
                <?php if ($first_booking['phone']): ?><p><?= e($first_booking['phone']) ?></p><?php endif; ?>
            </div>

            <div class="receipt-section-block">
                <h2>Chi tiết đặt sân</h2>
                <dl class="receipt-details">
                    <div><dt>Sân</dt><dd><?= e($first_booking['court_name']) ?></dd></div>
                    <div><dt>Khu vực</dt><dd><?= e($first_booking['location']) ?></dd></div>
                    <div><dt>Ngày chơi</dt><dd><?= e(date('d/m/Y', strtotime($first_booking['booking_date']))) ?></dd></div>
                    <div><dt>Phương thức</dt><dd><?= e($method_labels[$first_booking['payment_method']] ?? $first_booking['payment_method']) ?></dd></div>
                    <?php if ($first_booking['transaction_code']): ?><div><dt>Mã giao dịch</dt><dd><?= e($first_booking['transaction_code']) ?></dd></div><?php endif; ?>
                </dl>
            </div>

            <div class="receipt-slot-table">
                <div class="receipt-slot-head"><span>Khung giờ</span><span>Thành tiền</span></div>
                <?php foreach ($bookings as $booking): ?>
                    <div><span><?= e(substr($booking['start_time'], 0, 5)) ?>–<?= e(substr($booking['end_time'], 0, 5)) ?></span><strong><?= number_format((float) $booking['total_price'], 0, ',', '.') ?>đ</strong></div>
                <?php endforeach; ?>
            </div>

            <div class="receipt-total"><span>Tổng cộng</span><strong><?= number_format($total_price, 0, ',', '.') ?>đ</strong></div>
            <footer class="receipt-footer">
                <a class="button button-light" href="<?= e(url('booking/detail.php?booking_id=' . $booking_id)) ?>">Chi tiết đặt sân</a>
                <button class="button button-primary print-button" type="button">In hóa đơn</button>
            </footer>
        </article>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
