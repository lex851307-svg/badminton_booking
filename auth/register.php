<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect('');
}

$errors = [];
$full_name = '';
$email = '';
$phone = '';

if (is_post_request()) {
    verify_csrf();

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirmation = $_POST['password_confirmation'] ?? '';

    if (mb_strlen($full_name) < 2 || mb_strlen($full_name) > 100) {
        $errors['full_name'] = 'Họ và tên phải có từ 2 đến 100 ký tự.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Địa chỉ email không hợp lệ.';
    }

    if ($phone !== '' && !preg_match('/^[0-9+ .-]{8,20}$/', $phone)) {
        $errors['phone'] = 'Số điện thoại không hợp lệ.';
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Mật khẩu phải có ít nhất 8 ký tự.';
    }

    if ($password !== $password_confirmation) {
        $errors['password_confirmation'] = 'Mật khẩu xác nhận không khớp.';
    }

    if (!$errors) {
        $check_stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $check_stmt->execute([$email]);

        if ($check_stmt->fetch()) {
            $errors['email'] = 'Email này đã được sử dụng.';
        } else {
            $insert_stmt = $conn->prepare(
                'INSERT INTO users (full_name, email, phone, password)
                 VALUES (?, ?, ?, ?)'
            );
            $insert_stmt->execute([
                $full_name,
                $email,
                $phone !== '' ? $phone : null,
                password_hash($password, PASSWORD_DEFAULT),
            ]);

            set_flash('success', 'Đăng ký thành công. Bạn có thể đăng nhập ngay.');
            redirect('auth/login.php');
        }
    }
}

$page_title = 'Đăng ký';
$extra_css = ['auth.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card auth-card-wide">
        <div class="auth-heading">
            <span class="auth-icon">🏸</span>
            <h1>Tạo tài khoản</h1>
            <p>Đăng ký để đặt sân và quản lý lịch chơi của bạn.</p>
        </div>

        <form method="post" novalidate>
            <?= csrf_input() ?>

            <div class="form-group auth-field">
                <label for="full_name">Họ và tên</label>
                <input id="full_name" name="full_name" type="text" maxlength="100" value="<?= e($full_name) ?>" autocomplete="name" required>
                <?php if (isset($errors['full_name'])): ?>
                    <small class="field-error"><?= e($errors['full_name']) ?></small>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group auth-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" maxlength="150" value="<?= e($email) ?>" autocomplete="email" required>
                    <?php if (isset($errors['email'])): ?>
                        <small class="field-error"><?= e($errors['email']) ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group auth-field">
                    <label for="phone">Số điện thoại</label>
                    <input id="phone" name="phone" type="tel" maxlength="20" value="<?= e($phone) ?>" autocomplete="tel">
                    <?php if (isset($errors['phone'])): ?>
                        <small class="field-error"><?= e($errors['phone']) ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group auth-field">
                    <label for="password">Mật khẩu</label>
                    <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
                    <?php if (isset($errors['password'])): ?>
                        <small class="field-error"><?= e($errors['password']) ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group auth-field">
                    <label for="password_confirmation">Xác nhận mật khẩu</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
                    <?php if (isset($errors['password_confirmation'])): ?>
                        <small class="field-error"><?= e($errors['password_confirmation']) ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <button class="button button-primary auth-submit" type="submit">Đăng ký</button>
        </form>

        <p class="auth-switch">Đã có tài khoản? <a href="<?= e(url('auth/login.php')) ?>">Đăng nhập</a></p>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
