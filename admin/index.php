<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$stats = [
    'users' => (int) $conn->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
    'courts' => (int) $conn->query("SELECT COUNT(*) FROM courts WHERE status = 'active'")->fetchColumn(),
    'today_bookings' => (int) $conn->query(
        "SELECT COUNT(*) FROM bookings
         WHERE booking_date = CURRENT_DATE
           AND booking_status IN ('pending', 'confirmed')"
    )->fetchColumn(),
    'revenue' => (float) $conn->query(
        "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'paid'"
    )->fetchColumn(),
];

$recent_bookings = $conn->query(
    "SELECT
        b.booking_id,
        b.booking_code,
        b.booking_date,
        b.booking_status,
        b.total_price,
        u.full_name,
        c.court_name,
        ts.start_time,
        ts.end_time
     FROM bookings b
     JOIN users u ON u.user_id = b.user_id
     JOIN courts c ON c.court_id = b.court_id
     JOIN time_slots ts ON ts.slot_id = b.slot_id
     ORDER BY b.created_at DESC
     LIMIT 6"
)->fetchAll();

$status_labels = [
    'pending' => 'Chờ thanh toán',
    'confirmed' => 'Đã xác nhận',
    'cancelled' => 'Đã hủy',
    'completed' => 'Hoàn thành',
];

$page_title = 'Bảng điều khiển';
$active_page = 'admin';
$extra_css = ['admin.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin-section">
    <div class="container">
        <div class="admin-heading">
            <div>
                <span class="eyebrow eyebrow-dark">Quản trị hệ thống</span>
                <h1>Bảng điều khiển</h1>
                <p>Tổng quan tình trạng hoạt động của hệ thống đặt sân.</p>
            </div>
        </div>

        <nav class="admin-tabs" aria-label="Menu quản trị">
            <a class="active" href="<?= e(url('admin/')) ?>">Tổng quan</a>
            <a href="<?= e(url('admin/bookings.php')) ?>">Đặt sân</a>
            <a href="<?= e(url('admin/courts.php')) ?>">Sân cầu lông</a>
        </nav>

        <div class="stats-grid">
            <article class="stat-card">
                <span>👥</span>
                <div><small>Người dùng hoạt động</small><strong><?= $stats['users'] ?></strong></div>
            </article>
            <article class="stat-card">
                <span>🏸</span>
                <div><small>Sân đang hoạt động</small><strong><?= $stats['courts'] ?></strong></div>
            </article>
            <article class="stat-card">
                <span>📅</span>
                <div><small>Lịch đặt hôm nay</small><strong><?= $stats['today_bookings'] ?></strong></div>
            </article>
            <article class="stat-card">
                <span>💳</span>
                <div><small>Doanh thu đã thanh toán</small><strong><?= number_format($stats['revenue'], 0, ',', '.') ?>đ</strong></div>
            </article>
        </div>

        <section class="admin-panel">
            <div class="panel-heading">
                <div>
                    <h2>Đặt sân gần đây</h2>
                    <p>Sáu lịch đặt sân được tạo gần nhất trong hệ thống.</p>
                </div>
                <a href="<?= e(url('admin/bookings.php')) ?>">Xem tất cả →</a>
            </div>

            <?php if (!$recent_bookings): ?>
                <div class="admin-empty">Chưa có dữ liệu đặt sân.</div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Mã đặt sân</th>
                                <th>Khách hàng</th>
                                <th>Sân</th>
                                <th>Ngày và giờ</th>
                                <th>Trạng thái</th>
                                <th>Tổng tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <tr>
                                    <td><strong class="code-text"><?= e($booking['booking_code']) ?></strong></td>
                                    <td><?= e($booking['full_name']) ?></td>
                                    <td><?= e($booking['court_name']) ?></td>
                                    <td>
                                        <?= e(date('d/m/Y', strtotime($booking['booking_date']))) ?><br>
                                        <small><?= e(substr($booking['start_time'], 0, 5)) ?>–<?= e(substr($booking['end_time'], 0, 5)) ?></small>
                                    </td>
                                    <td><span class="admin-status status-<?= e($booking['booking_status']) ?>"><?= e($status_labels[$booking['booking_status']]) ?></span></td>
                                    <td><?= number_format((float) $booking['total_price'], 0, ',', '.') ?>đ</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
