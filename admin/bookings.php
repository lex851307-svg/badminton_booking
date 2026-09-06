<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$allowed_statuses = ['pending', 'confirmed', 'cancelled', 'completed'];
$status_transitions = [
    'pending' => ['pending', 'confirmed', 'cancelled'],
    'confirmed' => ['confirmed', 'completed', 'cancelled'],
    'cancelled' => ['cancelled'],
    'completed' => ['completed'],
];

if (is_post_request()) {
    verify_csrf();

    $booking_id = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
    $new_status = $_POST['booking_status'] ?? '';

    if (!$booking_id || !in_array($new_status, $allowed_statuses, true)) {
        set_flash('error', 'Thông tin cập nhật không hợp lệ.');
        redirect('admin/bookings.php');
    }

    try {
        $conn->beginTransaction();

        $booking_stmt = $conn->prepare(
            'SELECT booking_status FROM bookings WHERE booking_id = ? FOR UPDATE'
        );
        $booking_stmt->execute([$booking_id]);
        $current_booking = $booking_stmt->fetch();

        if (!$current_booking) {
            throw new RuntimeException('Booking not found.');
        }

        $current_status = $current_booking['booking_status'];

        if (!in_array($new_status, $status_transitions[$current_status], true)) {
            throw new RuntimeException('Invalid booking status transition.');
        }

        $update_stmt = $conn->prepare(
            'UPDATE bookings SET booking_status = ? WHERE booking_id = ?'
        );
        $update_stmt->execute([$new_status, $booking_id]);

        if ($new_status === 'cancelled') {
            $refund_stmt = $conn->prepare(
                "UPDATE payments
                 SET payment_status = CASE
                    WHEN payment_status = 'paid' THEN 'refunded'
                    ELSE 'failed'
                 END
                 WHERE booking_id = ? AND payment_status IN ('paid', 'pending')"
            );
            $refund_stmt->execute([$booking_id]);
        }

        $conn->commit();
        set_flash('success', 'Đã cập nhật trạng thái đặt sân.');
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        error_log('Admin booking update failed: ' . $e->getMessage());
        set_flash('error', 'Không thể cập nhật trạng thái. Vui lòng thử lại.');
    }

    $return_query = http_build_query([
        'status' => $_POST['return_status'] ?? '',
        'date' => $_POST['return_date'] ?? '',
    ]);
    redirect('admin/bookings.php?' . $return_query);
}

$selected_status = $_GET['status'] ?? '';
$selected_date = $_GET['date'] ?? '';

if (!in_array($selected_status, $allowed_statuses, true)) {
    $selected_status = '';
}

if ($selected_date !== '' && (!is_string($selected_date) || !is_valid_date($selected_date))) {
    $selected_date = '';
}

$sql = "SELECT
            b.booking_id,
            b.booking_code,
            b.booking_date,
            b.booking_status,
            b.total_price,
            u.full_name,
            u.email,
            c.court_name,
            ts.start_time,
            ts.end_time,
            p.payment_status
        FROM bookings b
        JOIN users u ON u.user_id = b.user_id
        JOIN courts c ON c.court_id = b.court_id
        JOIN time_slots ts ON ts.slot_id = b.slot_id
        LEFT JOIN payments p ON p.booking_id = b.booking_id
        WHERE 1 = 1";

$params = [];

if ($selected_status !== '') {
    $sql .= ' AND b.booking_status = ?';
    $params[] = $selected_status;
}

if ($selected_date !== '') {
    $sql .= ' AND b.booking_date = ?';
    $params[] = $selected_date;
}

$sql .= ' ORDER BY b.booking_date DESC, ts.start_time DESC';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$status_labels = [
    'pending' => 'Chờ thanh toán',
    'confirmed' => 'Đã xác nhận',
    'cancelled' => 'Đã hủy',
    'completed' => 'Hoàn thành',
];

$page_title = 'Quản lý đặt sân';
$active_page = 'admin';
$extra_css = ['admin.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin-section">
    <div class="container">
        <div class="admin-heading">
            <div>
                <span class="eyebrow eyebrow-dark">Quản trị hệ thống</span>
                <h1>Quản lý đặt sân</h1>
                <p>Kiểm tra và cập nhật trạng thái của các lịch đặt sân.</p>
            </div>
        </div>

        <nav class="admin-tabs" aria-label="Menu quản trị">
            <a href="<?= e(url('admin/')) ?>">Tổng quan</a>
            <a class="active" href="<?= e(url('admin/bookings.php')) ?>">Đặt sân</a>
            <a href="<?= e(url('admin/courts.php')) ?>">Sân cầu lông</a>
        </nav>

        <form class="admin-filter" method="get">
            <div class="form-group">
                <label for="status">Trạng thái</label>
                <select id="status" name="status">
                    <option value="">Tất cả trạng thái</option>
                    <?php foreach ($status_labels as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $selected_status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="date">Ngày đặt</label>
                <input id="date" name="date" type="date" value="<?= e($selected_date) ?>">
            </div>
            <button class="button button-primary" type="submit">Lọc dữ liệu</button>
            <a class="button reset-button" href="<?= e(url('admin/bookings.php')) ?>">Đặt lại</a>
        </form>

        <section class="admin-panel">
            <div class="panel-heading">
                <div><h2>Danh sách đặt sân</h2><p>Tìm thấy <?= count($bookings) ?> lịch đặt sân.</p></div>
            </div>

            <?php if (!$bookings): ?>
                <div class="admin-empty">Không tìm thấy lịch đặt sân phù hợp.</div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="admin-table booking-admin-table">
                        <thead>
                            <tr>
                                <th>Thông tin</th>
                                <th>Khách hàng</th>
                                <th>Ngày và giờ</th>
                                <th>Thanh toán</th>
                                <th>Cập nhật trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($booking['court_name']) ?></strong><br>
                                        <small class="code-text"><?= e($booking['booking_code']) ?></small>
                                    </td>
                                    <td><?= e($booking['full_name']) ?><br><small><?= e($booking['email']) ?></small></td>
                                    <td><?= e(date('d/m/Y', strtotime($booking['booking_date']))) ?><br><small><?= e(substr($booking['start_time'], 0, 5)) ?>–<?= e(substr($booking['end_time'], 0, 5)) ?></small></td>
                                    <td>
                                        <strong><?= number_format((float) $booking['total_price'], 0, ',', '.') ?>đ</strong><br>
                                        <small><?= e($booking['payment_status'] ?? 'Chưa thanh toán') ?></small>
                                    </td>
                                    <td>
                                        <form class="status-form" method="post">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="booking_id" value="<?= (int) $booking['booking_id'] ?>">
                                            <input type="hidden" name="return_status" value="<?= e($selected_status) ?>">
                                            <input type="hidden" name="return_date" value="<?= e($selected_date) ?>">
                                            <select name="booking_status" aria-label="Trạng thái đặt sân">
                                                <?php foreach ($status_transitions[$booking['booking_status']] as $value): ?>
                                                    <?php $label = $status_labels[$value]; ?>
                                                    <option value="<?= e($value) ?>" <?= $booking['booking_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (count($status_transitions[$booking['booking_status']]) > 1): ?>
                                                <button type="submit">Lưu</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
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
