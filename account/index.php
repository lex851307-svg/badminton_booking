<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$user_stmt = $conn->prepare(
    'SELECT full_name, email, phone, role, created_at
     FROM users
     WHERE user_id = ? AND status = \'active\'
     LIMIT 1'
);
$user_stmt->execute([current_user_id()]);
$user = $user_stmt->fetch();

if (!$user) {
    unset($_SESSION['user_id'], $_SESSION['full_name'], $_SESSION['email'], $_SESSION['role']);
    set_flash('error', 'Tài khoản không còn hoạt động.');
    redirect('auth/login.php');
}

$stats_stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total_bookings,
        SUM(booking_status = 'confirmed') AS confirmed_bookings,
        COALESCE(SUM(CASE WHEN booking_status = 'confirmed' THEN total_price ELSE 0 END), 0) AS total_value
     FROM bookings
     WHERE user_id = ?"
);
$stats_stmt->execute([current_user_id()]);
$stats = $stats_stmt->fetch();

$recent_stmt = $conn->prepare(
    "SELECT b.booking_id, b.booking_code, b.booking_date, b.booking_status,
            c.court_name, ts.start_time, ts.end_time
     FROM bookings b
     JOIN courts c ON c.court_id = b.court_id
     JOIN time_slots ts ON ts.slot_id = b.slot_id
     WHERE b.user_id = ?
     ORDER BY b.created_at DESC
     LIMIT 5"
);
$recent_stmt->execute([current_user_id()]);
$recent_bookings = $recent_stmt->fetchAll();

$status_labels = [
    'pending' => 'Chờ thanh toán',
    'confirmed' => 'Đã xác nhận',
    'cancelled' => 'Đã hủy',
    'completed' => 'Hoàn thành',
];

$page_title = 'Tài khoản';
$extra_css = ['account.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="account-section">
    <div class="container account-container">
        <div class="profile-card">
            <div class="profile-avatar"><?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></div>
            <div class="profile-main">
                <span class="profile-role"><?= $user['role'] === 'admin' ? 'Quản trị viên' : 'Thành viên' ?></span>
                <h1><?= e($user['full_name']) ?></h1>
                <p><?= e($user['email']) ?></p>
                <p><?= e($user['phone'] ?: 'Chưa cập nhật số điện thoại') ?></p>
            </div>
            <a class="button button-primary" href="<?= e(url('account/edit.php')) ?>">Chỉnh sửa tài khoản</a>
        </div>

        <div class="account-stats">
            <article><small>Tổng lịch đặt</small><strong><?= (int) $stats['total_bookings'] ?></strong></article>
            <article><small>Đã xác nhận</small><strong><?= (int) $stats['confirmed_bookings'] ?></strong></article>
            <article><small>Tổng giá trị</small><strong><?= number_format((float) $stats['total_value'], 0, ',', '.') ?>đ</strong></article>
            <article><small>Ngày tham gia</small><strong><?= e(date('d/m/Y', strtotime($user['created_at']))) ?></strong></article>
        </div>

        <section class="account-panel">
            <div class="account-panel-heading">
                <div><h2>Lịch đặt gần đây</h2><p>Năm lịch đặt mới nhất của bạn.</p></div>
                <a href="<?= e(url('booking/history.php')) ?>">Xem tất cả →</a>
            </div>

            <?php if (!$recent_bookings): ?>
                <div class="account-empty">Bạn chưa có lịch đặt sân.</div>
            <?php else: ?>
                <div class="recent-booking-list">
                    <?php foreach ($recent_bookings as $booking): ?>
                        <article>
                            <div><small><?= e($booking['booking_code']) ?></small><h3><?= e($booking['court_name']) ?></h3></div>
                            <div><strong><?= e(date('d/m/Y', strtotime($booking['booking_date']))) ?></strong><small><?= e(substr($booking['start_time'], 0, 5)) ?>–<?= e(substr($booking['end_time'], 0, 5)) ?></small></div>
                            <span class="account-status status-<?= e($booking['booking_status']) ?>"><?= e($status_labels[$booking['booking_status']]) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
