<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';

require_admin();

$allowed_statuses = ['active', 'maintenance', 'inactive'];
$status_labels = [
    'active' => 'Đang hoạt động',
    'maintenance' => 'Đang bảo trì',
    'inactive' => 'Ngừng hoạt động',
];

$errors = [];
$editing_court = null;
$form_data = [
    'court_id' => '',
    'court_name' => '',
    'location' => '',
    'description' => '',
    'price_per_hour' => '',
    'image_path' => null,
    'status' => 'active',
];

$existing_image_path = null;
$stored_image_path = null;

if (is_post_request()) {
    verify_csrf();

    $form_data = [
        'court_id' => trim($_POST['court_id'] ?? ''),
        'court_name' => trim($_POST['court_name'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'price_per_hour' => trim($_POST['price_per_hour'] ?? ''),
        'image_path' => null,
        'status' => $_POST['status'] ?? 'active',
    ];

    $court_id = $form_data['court_id'] !== ''
        ? filter_var($form_data['court_id'], FILTER_VALIDATE_INT)
        : null;

    if ($form_data['court_id'] !== '' && !$court_id) {
        $errors['court_id'] = 'Mã sân không hợp lệ.';
    }

    if (mb_strlen($form_data['court_name']) < 2 || mb_strlen($form_data['court_name']) > 100) {
        $errors['court_name'] = 'Tên sân phải có từ 2 đến 100 ký tự.';
    }

    if (mb_strlen($form_data['location']) < 2 || mb_strlen($form_data['location']) > 150) {
        $errors['location'] = 'Khu vực phải có từ 2 đến 150 ký tự.';
    }

    if ($form_data['description'] !== '' && mb_strlen($form_data['description']) > 1000) {
        $errors['description'] = 'Mô tả không được vượt quá 1000 ký tự.';
    }

    if (
        !is_numeric($form_data['price_per_hour'])
        || (float) $form_data['price_per_hour'] <= 0
        || (float) $form_data['price_per_hour'] > 10000000
    ) {
        $errors['price_per_hour'] = 'Giá sân phải từ 1 đến 10.000.000 VNĐ.';
    }

    if (!in_array($form_data['status'], $allowed_statuses, true)) {
        $errors['status'] = 'Trạng thái sân không hợp lệ.';
    }

    if (!$errors && $court_id) {
        $exists_stmt = $conn->prepare('SELECT image_path FROM courts WHERE court_id = ? LIMIT 1');
        $exists_stmt->execute([$court_id]);
        $existing_court = $exists_stmt->fetch();

        if (!$existing_court) {
            $errors['court_id'] = 'Không tìm thấy sân cần cập nhật.';
        } else {
            $existing_image_path = $existing_court['image_path'];
            $form_data['image_path'] = $existing_image_path;
        }
    }

    if (!$errors && $court_id && $form_data['status'] !== 'active') {
        $booking_stmt = $conn->prepare(
            "SELECT COUNT(*)
             FROM bookings
             WHERE court_id = ?
               AND booking_date >= CURRENT_DATE
               AND booking_status IN ('pending', 'confirmed')"
        );
        $booking_stmt->execute([$court_id]);

        if ((int) $booking_stmt->fetchColumn() > 0) {
            $errors['status'] = 'Sân còn lịch đặt chưa hoàn tất. Hãy xử lý các lịch đặt trước khi đóng sân.';
        }
    }

    if (!$errors) {
        $upload_result = store_court_image($_FILES['court_image'] ?? []);

        if ($upload_result['error'] !== null) {
            $errors['court_image'] = $upload_result['error'];
        } else {
            $stored_image_path = $upload_result['path'];
        }
    }

    if (!$errors) {
        $image_path = $stored_image_path ?? $existing_image_path;

        try {
            if ($court_id) {
                $stmt = $conn->prepare(
                    "UPDATE courts
                     SET court_name = ?, location = ?, description = ?,
                         price_per_hour = ?, image_path = ?, status = ?
                     WHERE court_id = ?"
                );
                $stmt->execute([
                    $form_data['court_name'],
                    $form_data['location'],
                    $form_data['description'] !== '' ? $form_data['description'] : null,
                    $form_data['price_per_hour'],
                    $image_path,
                    $form_data['status'],
                    $court_id,
                ]);
                set_flash('success', 'Đã cập nhật thông tin sân.');
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO courts (
                        court_name, location, description, price_per_hour, image_path, status
                     ) VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $form_data['court_name'],
                    $form_data['location'],
                    $form_data['description'] !== '' ? $form_data['description'] : null,
                    $form_data['price_per_hour'],
                    $image_path,
                    $form_data['status'],
                ]);
                set_flash('success', 'Đã thêm sân mới.');
            }
        } catch (Throwable $exception) {
            remove_stored_court_image($stored_image_path);
            throw $exception;
        }

        redirect('admin/courts.php');
    }
}

if (!is_post_request()) {
    $edit_id = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);

    if ($edit_id) {
        $edit_stmt = $conn->prepare(
            "SELECT court_id, court_name, location, description, price_per_hour, image_path, status
             FROM courts WHERE court_id = ? LIMIT 1"
        );
        $edit_stmt->execute([$edit_id]);
        $editing_court = $edit_stmt->fetch();

        if (!$editing_court) {
            set_flash('error', 'Không tìm thấy sân cần chỉnh sửa.');
            redirect('admin/courts.php');
        }

        $form_data = $editing_court;
    }
}

$courts = $conn->query(
    "SELECT
        c.court_id,
        c.court_name,
        c.location,
        c.description,
        c.price_per_hour,
        c.image_path,
        c.status,
        COUNT(CASE
            WHEN b.booking_date >= CURRENT_DATE
             AND b.booking_status IN ('pending', 'confirmed')
            THEN 1
        END) AS upcoming_bookings
     FROM courts c
     LEFT JOIN bookings b ON b.court_id = c.court_id
     GROUP BY c.court_id
     ORDER BY c.court_id"
)->fetchAll();

$page_title = 'Quản lý sân';
$active_page = 'admin';
$extra_css = ['admin.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin-section">
    <div class="container">
        <div class="admin-heading">
            <div>
                <span class="eyebrow eyebrow-dark">Quản trị hệ thống</span>
                <h1>Quản lý sân cầu lông</h1>
                <p>Thêm sân mới hoặc cập nhật thông tin sân hiện có.</p>
            </div>
        </div>

        <nav class="admin-tabs" aria-label="Menu quản trị">
            <a href="<?= e(url('admin/')) ?>">Tổng quan</a>
            <a href="<?= e(url('admin/bookings.php')) ?>">Đặt sân</a>
            <a class="active" href="<?= e(url('admin/courts.php')) ?>">Sân cầu lông</a>
        </nav>

        <div class="court-admin-layout">
            <section class="court-form-card">
                <h2><?= $form_data['court_id'] ? 'Chỉnh sửa sân' : 'Thêm sân mới' ?></h2>
                <p><?= $form_data['court_id'] ? 'Cập nhật thông tin bên dưới.' : 'Nhập thông tin sân cần thêm.' ?></p>

                <form method="post" enctype="multipart/form-data" novalidate>
                    <?= csrf_input() ?>
                    <input type="hidden" name="court_id" value="<?= e($form_data['court_id']) ?>">
                    <?php if (isset($errors['court_id'])): ?><div class="form-error"><?= e($errors['court_id']) ?></div><?php endif; ?>

                    <div class="form-group admin-field">
                        <label for="court_name">Tên sân</label>
                        <input id="court_name" name="court_name" type="text" maxlength="100" value="<?= e($form_data['court_name']) ?>" required>
                        <?php if (isset($errors['court_name'])): ?><small class="field-error"><?= e($errors['court_name']) ?></small><?php endif; ?>
                    </div>

                    <div class="form-group admin-field">
                        <label for="location">Khu vực</label>
                        <input id="location" name="location" type="text" maxlength="150" value="<?= e($form_data['location']) ?>" required>
                        <?php if (isset($errors['location'])): ?><small class="field-error"><?= e($errors['location']) ?></small><?php endif; ?>
                    </div>

                    <div class="form-group admin-field">
                        <label for="price_per_hour">Giá mỗi giờ (VNĐ)</label>
                        <input id="price_per_hour" name="price_per_hour" type="number" min="1000" step="1000" value="<?= e($form_data['price_per_hour']) ?>" required>
                        <?php if (isset($errors['price_per_hour'])): ?><small class="field-error"><?= e($errors['price_per_hour']) ?></small><?php endif; ?>
                    </div>

                    <div class="form-group admin-field">
                        <label for="status">Trạng thái</label>
                        <select id="status" name="status">
                            <?php foreach ($status_labels as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $form_data['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['status'])): ?><small class="field-error"><?= e($errors['status']) ?></small><?php endif; ?>
                    </div>

                    <div class="form-group admin-field">
                        <label for="court_image">Hình ảnh sân</label>
                        <?php $current_image_url = project_image_url($form_data['image_path'] ?? null); ?>
                        <?php if ($current_image_url !== null): ?>
                            <img class="current-court-image" src="<?= e($current_image_url) ?>" alt="Ảnh hiện tại của <?= e($form_data['court_name']) ?>">
                        <?php endif; ?>
                        <input id="court_image" name="court_image" type="file" accept="image/jpeg,image/png,image/webp">
                        <small class="field-hint">JPG, PNG hoặc WEBP • tối đa 5 MB • không chọn tệp nếu không muốn thay ảnh</small>
                        <?php if (isset($errors['court_image'])): ?><small class="field-error"><?= e($errors['court_image']) ?></small><?php endif; ?>
                    </div>

                    <div class="form-group admin-field">
                        <label for="description">Mô tả</label>
                        <textarea id="description" name="description" rows="5" maxlength="1000"><?= e($form_data['description']) ?></textarea>
                        <?php if (isset($errors['description'])): ?><small class="field-error"><?= e($errors['description']) ?></small><?php endif; ?>
                    </div>

                    <div class="court-form-actions">
                        <button class="button button-primary" type="submit"><?= $form_data['court_id'] ? 'Lưu thay đổi' : 'Thêm sân' ?></button>
                        <?php if ($form_data['court_id']): ?>
                            <a class="button reset-button" href="<?= e(url('admin/courts.php')) ?>">Hủy chỉnh sửa</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="admin-panel court-list-panel">
                <div class="panel-heading">
                    <div><h2>Danh sách sân</h2><p>Hiện có <?= count($courts) ?> sân trong hệ thống.</p></div>
                </div>

                <div class="admin-court-list">
                    <?php foreach ($courts as $court): ?>
                        <?php $court_image_url = project_image_url($court['image_path'] ?? null); ?>
                        <article class="admin-court-item">
                            <div class="admin-court-icon">
                                <?php if ($court_image_url !== null): ?>
                                    <img src="<?= e($court_image_url) ?>" alt="<?= e($court['court_name']) ?>" loading="lazy">
                                <?php else: ?>
                                    <span>🏸</span>
                                <?php endif; ?>
                            </div>
                            <div class="admin-court-info">
                                <div>
                                    <h3><?= e($court['court_name']) ?></h3>
                                    <p><?= e($court['location']) ?> • <?= number_format((float) $court['price_per_hour'], 0, ',', '.') ?>đ/giờ</p>
                                </div>
                                <span class="court-status court-status-<?= e($court['status']) ?>"><?= e($status_labels[$court['status']]) ?></span>
                                <small><?= (int) $court['upcoming_bookings'] ?> lịch sắp tới</small>
                                <a class="edit-court-link" href="<?= e(url('admin/courts.php?edit=' . $court['court_id'])) ?>">Chỉnh sửa</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
