<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_location = trim($_GET['location'] ?? '');

if (!is_string($selected_date) || !is_valid_date($selected_date) || $selected_date < date('Y-m-d')) {
    $selected_date = date('Y-m-d');
}

if (!is_string($selected_location)) {
    $selected_location = '';
}

$location_stmt = $conn->query(
    "SELECT DISTINCT location FROM courts WHERE status = 'active' ORDER BY location"
);
$locations = $location_stmt->fetchAll(PDO::FETCH_COLUMN);

$sql = "SELECT
            c.court_id,
            c.court_name,
            c.location,
            c.description,
            c.price_per_hour,
            c.image_path,
            COUNT(ts.slot_id) AS total_slots,
            COUNT(b.booking_id) AS booked_slots
        FROM courts c
        CROSS JOIN time_slots ts
        LEFT JOIN bookings b
            ON b.court_id = c.court_id
            AND b.slot_id = ts.slot_id
            AND b.booking_date = ?
            AND b.booking_status IN ('pending', 'confirmed')
        WHERE c.status = 'active'";

$params = [$selected_date];

if ($selected_location !== '') {
    $sql .= ' AND c.location = ?';
    $params[] = $selected_location;
}

$sql .= ' GROUP BY c.court_id ORDER BY c.court_name';

$court_stmt = $conn->prepare($sql);
$court_stmt->execute($params);
$courts = $court_stmt->fetchAll();

$page_title = 'Danh sách sân';
$active_page = 'courts';
$extra_css = ['courts.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-banner">
    <div class="container">
        <span class="eyebrow">Đặt sân trực tuyến</span>
        <h1>Danh sách sân cầu lông</h1>
        <p>Kiểm tra thông tin và số khung giờ còn trống trước khi đặt sân.</p>
    </div>
</section>

<section class="court-list-section">
    <div class="container">
        <form class="court-filter" method="get">
            <div class="form-group">
                <label for="date">Ngày đặt</label>
                <input id="date" name="date" type="date" min="<?= date('Y-m-d') ?>" value="<?= e($selected_date) ?>" required>
            </div>

            <div class="form-group">
                <label for="location">Khu vực</label>
                <select id="location" name="location">
                    <option value="">Tất cả khu vực</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= e($location) ?>" <?= $selected_location === $location ? 'selected' : '' ?>>
                            <?= e($location) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="button button-primary" type="submit">Tìm kiếm</button>
        </form>

        <div class="result-heading">
            <div>
                <h2>Sân đang hoạt động</h2>
                <p>Ngày <?= e(date('d/m/Y', strtotime($selected_date))) ?></p>
            </div>
            <strong><?= count($courts) ?> sân</strong>
        </div>

        <?php if (!$courts): ?>
            <div class="empty-state">
                <span>🔎</span>
                <h2>Không tìm thấy sân</h2>
                <p>Hãy thử chọn khu vực hoặc ngày khác.</p>
            </div>
        <?php else: ?>
            <div class="court-list-grid">
                <?php foreach ($courts as $court): ?>
                    <?php
                    $available_slots = (int) $court['total_slots'] - (int) $court['booked_slots'];
                    $image_url = project_image_url($court['image_path'] ?? null);
                    ?>
                    <article class="list-court-card">
                        <div class="court-cover <?= $image_url === null ? 'court-cover-' . ((($court['court_id'] - 1) % 3) + 1) : '' ?>">
                            <?php if ($image_url !== null): ?>
                                <img src="<?= e($image_url) ?>" alt="<?= e($court['court_name']) ?>" loading="lazy">
                            <?php else: ?>
                                <span>🏸</span>
                            <?php endif; ?>
                        </div>

                        <div class="list-court-body">
                            <div class="court-name-row">
                                <div>
                                    <h2><?= e($court['court_name']) ?></h2>
                                    <p class="court-location">📍 <?= e($court['location']) ?></p>
                                </div>
                                <span class="availability <?= $available_slots === 0 ? 'full' : '' ?>">
                                    <?= $available_slots ?> giờ trống
                                </span>
                            </div>

                            <p class="court-description"><?= e($court['description']) ?></p>

                            <div class="list-court-footer">
                                <strong><?= number_format((float) $court['price_per_hour'], 0, ',', '.') ?>đ <small>/ giờ</small></strong>
                                <a class="button button-primary" href="<?= e(url('courts/detail.php?id=' . $court['court_id'] . '&date=' . $selected_date)) ?>">
                                    Xem khung giờ
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
