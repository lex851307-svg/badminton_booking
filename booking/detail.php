<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
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

if (!$bookings) {
    http_response_code(404);
    exit('Không tìm thấy lịch đặt sân.');
}

$first_booking = $bookings[0];
$total_price = booking_group_total($bookings);
$is_pending = booking_group_all_status($bookings, 'pending');
$is_cancelled = booking_group_all_status($bookings, 'cancelled');
$has_payment = !empty($first_booking['payment_id']);

$status_labels = [
    'pending' => 'Chờ thanh toán',
    'confirmed' => 'Đã xác nhận',
    'cancelled' => 'Đã hủy',
    'completed' => 'Hoàn thành',
];
$group_status = $first_booking['booking_status'];

$page_title = 'Chi tiết đặt sân';
$active_page = 'history';
$extra_css = ['booking.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="booking-detail-section">
    <div class="container booking-detail-container">
        <a class="back-link" href="<?= e(url('booking/history.php')) ?>">← Quay lại lịch sử</a>

        <div class="detail-title-row">
            <div>
                <span class="eyebrow eyebrow-dark">Mã <?= e($first_booking['booking_code']) ?></span>
                <h1>Chi tiết đặt sân</h1>
                <p>Nhóm này có <?= count($bookings) ?> khung giờ.</p>
            </div>
            <span class="booking-status status-<?= e($group_status) ?>"><?= e($status_labels[$group_status] ?? $group_status) ?></span>
        </div>

        <div class="booking-detail-grid">
            <section class="detail-panel">
                <h2>Thông tin sân</h2>
                <dl class="detail-list">
                    <div><dt>Sân</dt><dd><?= e($first_booking['court_name']) ?></dd></div>
                    <div><dt>Khu vực</dt><dd><?= e($first_booking['location']) ?></dd></div>
                    <div><dt>Ngày chơi</dt><dd><?= e(date('d/m/Y', strtotime($first_booking['booking_date']))) ?></dd></div>
                    <div><dt>Người đặt</dt><dd><?= e($first_booking['full_name']) ?></dd></div>
                </dl>

                <h2 class="slot-heading">Khung giờ đã chọn</h2>
                <div class="group-slot-list">
                    <?php foreach ($bookings as $booking): ?>
                        <div>
                            <span><?= e(substr($booking['start_time'], 0, 5)) ?>–<?= e(substr($booking['end_time'], 0, 5)) ?></span>
                            <strong><?= number_format((float) $booking['total_price'], 0, ',', '.') ?>đ</strong>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="detail-total"><span>Tổng cộng</span><strong><?= number_format($total_price, 0, ',', '.') ?>đ</strong></div>
            </section>

            <aside class="detail-action-panel">
                <h2>Thao tác</h2>

                <?php if ($is_pending): ?>
                    <a class="button button-primary" href="<?= e(url('payment/checkout.php?booking_id=' . $booking_id)) ?>">Thanh toán nhóm</a>
                    <form class="confirm-form" action="<?= e(url('booking/cancel.php')) ?>" method="post" data-confirm="Bạn chắc chắn muốn hủy toàn bộ nhóm đặt sân này?">
                        <?= csrf_input() ?>
                        <input type="hidden" name="booking_id" value="<?= (int) $booking_id ?>">
                        <button class="button danger-button" type="submit">Hủy toàn bộ nhóm</button>
                    </form>
                <?php elseif ($has_payment): ?>
                    <a class="button button-primary" href="<?= e(url('payment/receipt.php?booking_id=' . $booking_id)) ?>">Xem hóa đơn</a>
                <?php elseif ($is_cancelled): ?>
                    <p class="action-note">Nhóm đặt sân này đã được hủy.</p>
                <?php endif; ?>

                <a class="button secondary-button" href="<?= e(url('courts/detail.php?id=' . $first_booking['court_id'])) ?>">Đặt thêm sân</a>
            </aside>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
