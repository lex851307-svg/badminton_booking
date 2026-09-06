<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$court_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$selected_date = $_GET['date'] ?? date('Y-m-d');

if (!$court_id) {
    http_response_code(404);
    exit('Không tìm thấy sân.');
}

if (!is_string($selected_date) || !is_valid_date($selected_date) || $selected_date < date('Y-m-d')) {
    $selected_date = date('Y-m-d');
}

$court_stmt = $conn->prepare(
    "SELECT court_id, court_name, location, description, price_per_hour, image_path, status
     FROM courts
     WHERE court_id = ? AND status != 'inactive'
     LIMIT 1"
);
$court_stmt->execute([$court_id]);
$court = $court_stmt->fetch();

if (!$court) {
    http_response_code(404);
    exit('Không tìm thấy sân.');
}

$image_url = project_image_url($court['image_path'] ?? null);

$slot_stmt = $conn->prepare(
    "SELECT
        ts.slot_id,
        ts.start_time,
        ts.end_time,
        CASE WHEN b.booking_id IS NULL THEN 1 ELSE 0 END AS is_available
     FROM time_slots ts
     LEFT JOIN bookings b
        ON b.slot_id = ts.slot_id
        AND b.court_id = ?
        AND b.booking_date = ?
        AND b.booking_status IN ('pending', 'confirmed')
     ORDER BY ts.start_time"
);
$slot_stmt->execute([$court_id, $selected_date]);
$time_slots = $slot_stmt->fetchAll();

$page_title = $court['court_name'];
$active_page = 'courts';
$extra_css = ['courts.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="court-detail-section">
    <div class="container">
        <a class="back-link" href="<?= e(url('courts/?date=' . $selected_date)) ?>">← Quay lại danh sách sân</a>

        <div class="court-detail-grid">
            <div class="detail-cover <?= $image_url === null ? 'court-cover-' . ((($court['court_id'] - 1) % 3) + 1) : '' ?>">
                <?php if ($image_url !== null): ?>
                    <img src="<?= e($image_url) ?>" alt="<?= e($court['court_name']) ?>">
                <?php else: ?>
                    <span>🏸</span>
                <?php endif; ?>
            </div>

            <div class="detail-info">
                <span class="availability <?= $court['status'] === 'maintenance' ? 'full' : '' ?>">
                    <?= $court['status'] === 'maintenance' ? 'Đang bảo trì' : 'Đang hoạt động' ?>
                </span>
                <h1><?= e($court['court_name']) ?></h1>
                <p class="court-location">📍 <?= e($court['location']) ?></p>
                <p><?= e($court['description']) ?></p>
                <strong class="detail-price"><?= number_format((float) $court['price_per_hour'], 0, ',', '.') ?>đ <small>/ giờ</small></strong>
            </div>
        </div>

        <div class="schedule-card">
            <div class="schedule-heading">
                <div>
                    <h2>Chọn khung giờ</h2>
                    <p>Màu xanh là khung giờ còn có thể đặt.</p>
                </div>

                <form class="date-filter-form" method="get">
                    <input type="hidden" name="id" value="<?= (int) $court['court_id'] ?>">
                    <label for="date">Ngày đặt</label>
                    <input id="date" name="date" type="date" min="<?= date('Y-m-d') ?>" value="<?= e($selected_date) ?>">
                </form>
            </div>

            <?php if ($court['status'] === 'maintenance'): ?>
                <div class="maintenance-notice">Sân đang bảo trì và chưa thể nhận đặt sân.</div>
            <?php else: ?>
                <?php if (is_logged_in()): ?>
                    <form class="multi-slot-form" action="<?= e(url('booking/create.php')) ?>" method="get">
                        <input type="hidden" name="court_id" value="<?= (int) $court['court_id'] ?>">
                        <input type="hidden" name="date" value="<?= e($selected_date) ?>">
                        <div class="slot-grid">
                <?php else: ?>
                    <div class="slot-grid">
                <?php endif; ?>

                    <?php foreach ($time_slots as $slot): ?>
                        <?php
                        $has_ended = $selected_date === date('Y-m-d')
                            && $slot['end_time'] <= date('H:i:s');
                        ?>
                        <?php if ((int) $slot['is_available'] === 1 && !$has_ended): ?>
                            <?php if (is_logged_in()): ?>
                                <label class="time-slot available selectable">
                                    <input type="checkbox" name="slot_ids[]" value="<?= (int) $slot['slot_id'] ?>">
                                    <strong><?= e(substr($slot['start_time'], 0, 5)) ?></strong>
                                    <span><?= e(substr($slot['end_time'], 0, 5)) ?></span>
                                </label>
                            <?php else: ?>
                                <div class="time-slot available">
                                    <strong><?= e(substr($slot['start_time'], 0, 5)) ?></strong>
                                    <span><?= e(substr($slot['end_time'], 0, 5)) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="time-slot booked">
                                <strong><?= e(substr($slot['start_time'], 0, 5)) ?></strong>
                                <span><?= $has_ended ? 'Đã qua' : 'Đã đặt' ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                        </div>

                <?php if (is_logged_in()): ?>
                        <div class="slot-selection-footer">
                            <span>Đã chọn <strong class="selected-slot-count">0</strong> khung giờ</span>
                            <button class="button button-primary multi-slot-submit" type="submit" disabled>Tiếp tục đặt sân</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="slot-login-notice">
                        <span>Đăng nhập để chọn một hoặc nhiều khung giờ.</span>
                        <a class="button button-primary" href="<?= e(url('auth/login.php')) ?>">Đăng nhập</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
