<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Trang chủ';
$active_page = 'home';

$court_stmt = $conn->query(
    "SELECT court_id, court_name, location, description, price_per_hour, image_path
     FROM courts
     WHERE status = 'active'
     ORDER BY court_id
     LIMIT 3"
);
$featured_courts = $court_stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container hero-content">
        <span class="eyebrow">Sân tốt • Giá rõ ràng • Đặt nhanh</span>
        <h1>Đặt sân cầu lông<br>đơn giản hơn mỗi ngày</h1>
        <p>Chọn sân, kiểm tra giờ trống và hoàn tất đặt sân chỉ trong vài bước.</p>

        <div class="hero-actions">
            <a class="button button-primary" href="<?= e(url('courts/')) ?>">Tìm sân ngay</a>
            <a class="button button-light" href="#how-it-works">Cách đặt sân</a>
        </div>
    </div>
</section>

<section class="search-panel-section">
    <div class="container">
        <form class="search-panel" action="<?= e(url('courts/')) ?>" method="get">
            <div class="search-heading">
                <span>🔎</span>
                <div>
                    <h2>Tìm sân phù hợp</h2>
                    <p>Chọn ngày và khu vực bạn muốn chơi</p>
                </div>
            </div>

            <div class="form-group">
                <label for="date">Ngày đặt</label>
                <input id="date" name="date" type="date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label for="location">Khu vực</label>
                <select id="location" name="location">
                    <option value="">Tất cả khu vực</option>
                    <option value="Khu A">Khu A</option>
                    <option value="Khu B">Khu B</option>
                </select>
            </div>

            <button class="button button-primary search-submit" type="submit">Tìm kiếm</button>
        </form>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <div>
                <span class="eyebrow eyebrow-dark">Sân nổi bật</span>
                <h2>Chọn sân phù hợp với bạn</h2>
            </div>
            <a class="text-link" href="<?= e(url('courts/')) ?>">Xem tất cả →</a>
        </div>

        <div class="court-grid">
            <?php foreach ($featured_courts as $index => $court): ?>
                <?php $image_url = project_image_url($court['image_path'] ?? null); ?>
                <article class="court-card">
                    <div class="court-image <?= $image_url === null ? 'court-image-' . (($index % 3) + 1) : '' ?>">
                        <?php if ($image_url !== null): ?>
                            <img src="<?= e($image_url) ?>" alt="<?= e($court['court_name']) ?>" loading="lazy">
                        <?php else: ?>
                            <span>🏸</span>
                        <?php endif; ?>
                    </div>
                    <div class="court-body">
                        <div class="court-title-row">
                            <h3><?= e($court['court_name']) ?></h3>
                            <span class="status-badge">Đang mở</span>
                        </div>
                        <p class="court-location">📍 <?= e($court['location']) ?></p>
                        <p><?= e($court['description']) ?></p>
                        <div class="court-footer">
                            <strong><?= number_format((float) $court['price_per_hour'], 0, ',', '.') ?>đ <small>/ giờ</small></strong>
                            <a href="<?= e(url('courts/detail.php?id=' . $court['court_id'])) ?>">Chi tiết</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section steps-section" id="how-it-works">
    <div class="container">
        <div class="section-heading centered">
            <div>
                <span class="eyebrow eyebrow-dark">Hướng dẫn</span>
                <h2>Đặt sân trong 3 bước</h2>
            </div>
        </div>

        <div class="steps-grid">
            <article class="step-card">
                <span class="step-number">01</span>
                <h3>Chọn sân</h3>
                <p>Xem thông tin, vị trí và mức giá của từng sân.</p>
            </article>
            <article class="step-card">
                <span class="step-number">02</span>
                <h3>Chọn thời gian</h3>
                <p>Kiểm tra ngày và khung giờ còn trống phù hợp với bạn.</p>
            </article>
            <article class="step-card">
                <span class="step-number">03</span>
                <h3>Xác nhận đặt sân</h3>
                <p>Kiểm tra thông tin và lựa chọn phương thức thanh toán.</p>
            </article>
        </div>
    </div>
</section>

<section class="cta-section">
    <div class="container cta-content">
        <div>
            <h2>Sẵn sàng ra sân?</h2>
            <p>Tìm khung giờ phù hợp và đặt sân ngay hôm nay.</p>
        </div>
        <a class="button button-light" href="<?= e(url('courts/')) ?>">Đặt sân ngay</a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
