<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$stmt = $conn->prepare(
    "SELECT
        b.booking_id,
        b.group_id,
        b.booking_code,
        b.booking_date,
        b.total_price,
        b.booking_status,
        b.created_at,
        c.court_name,
        c.location,
        ts.start_time,
        ts.end_time,
        p.payment_id,
        p.payment_status
     FROM bookings b
     JOIN courts c ON c.court_id = b.court_id
     JOIN time_slots ts ON ts.slot_id = b.slot_id
     LEFT JOIN payments p ON p.booking_id = b.booking_id
     WHERE b.user_id = ?
     ORDER BY b.booking_date DESC, ts.start_time"
);
$stmt->execute([current_user_id()]);
$booking_rows = $stmt->fetchAll();
$booking_groups = [];

foreach ($booking_rows as $booking) {
    $group_key = (int) ($booking['group_id'] ?: $booking['booking_id']);

    if (!isset($booking_groups[$group_key])) {
        $booking_groups[$group_key] = [
            'booking_id' => $group_key,
            'booking_code' => $booking['booking_code'],
            'booking_date' => $booking['booking_date'],
            'booking_status' => $booking['booking_status'],
            'court_name' => $booking['court_name'],
            'location' => $booking['location'],
            'total_price' => 0.0,
            'times' => [],
            'count' => 0,
            'has_payment' => false,
        ];
    }

    $booking_groups[$group_key]['total_price'] += (float) $booking['total_price'];
    $booking_groups[$group_key]['times'][] = substr($booking['start_time'], 0, 5)
        . '–' . substr($booking['end_time'], 0, 5);
    $booking_groups[$group_key]['count']++;
    $booking_groups[$group_key]['has_payment'] = $booking_groups[$group_key]['has_payment']
        || !empty($booking['payment_id']);
}

$status_labels = [
    'pending' => 'Chờ thanh toán',
    'confirmed' => 'Đã xác nhận',
    'cancelled' => 'Đã hủy',
    'completed' => 'Hoàn thành',
];

$page_title = 'Lịch sử đặt sân';
$active_page = 'history';
$extra_css = ['booking.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="history-section">
    <div class="container">
        <div class="history-heading">
            <div><span class="eyebrow eyebrow-dark">Tài khoản của bạn</span><h1>Lịch sử đặt sân</h1><p>Mỗi thẻ đại diện cho một lần đặt, có thể gồm nhiều khung giờ.</p></div>
            <a class="button button-primary" href="<?= e(url('courts/')) ?>">Đặt sân mới</a>
        </div>

        <?php if (!$booking_groups): ?>
            <div class="empty-state"><span>📅</span><h2>Bạn chưa có lịch đặt sân</h2><p>Hãy chọn sân và khung giờ phù hợp để bắt đầu.</p><a class="button button-primary" href="<?= e(url('courts/')) ?>">Tìm sân</a></div>
        <?php else: ?>
            <div class="history-list">
                <?php foreach ($booking_groups as $booking): ?>
                    <article class="history-card">
                        <div class="history-main">
                            <div class="history-code"><small>Mã đặt sân</small><strong><?= e($booking['booking_code']) ?></strong></div>
                            <div><h2><?= e($booking['court_name']) ?></h2><p>📍 <?= e($booking['location']) ?> • <?= (int) $booking['count'] ?> khung giờ</p></div>
                            <div class="history-time"><strong><?= e(date('d/m/Y', strtotime($booking['booking_date']))) ?></strong><span><?= e(implode(', ', $booking['times'])) ?></span></div>
                            <span class="booking-status status-<?= e($booking['booking_status']) ?>"><?= e($status_labels[$booking['booking_status']] ?? $booking['booking_status']) ?></span>
                        </div>

                        <div class="history-actions">
                            <strong><?= number_format($booking['total_price'], 0, ',', '.') ?>đ</strong>
                            <div>
                                <a class="small-button receipt-button" href="<?= e(url('booking/detail.php?booking_id=' . $booking['booking_id'])) ?>">Chi tiết</a>

                                <?php if ($booking['has_payment']): ?>
                                    <a class="small-button receipt-button" href="<?= e(url('payment/receipt.php?booking_id=' . $booking['booking_id'])) ?>">Xem phiếu</a>
                                <?php endif; ?>

                                <?php if ($booking['booking_status'] === 'pending'): ?>
                                    <a class="small-button pay-button" href="<?= e(url('payment/checkout.php?booking_id=' . $booking['booking_id'])) ?>">Thanh toán</a>
                                    <form class="confirm-form" action="<?= e(url('booking/cancel.php')) ?>" method="post" data-confirm="Bạn chắc chắn muốn hủy toàn bộ nhóm đặt sân này?">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="booking_id" value="<?= (int) $booking['booking_id'] ?>">
                                        <button class="small-button cancel-button" type="submit">Hủy lịch</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
