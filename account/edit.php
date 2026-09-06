<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$user_stmt = $conn->prepare(
    'SELECT full_name, email, phone, password FROM users WHERE user_id = ? LIMIT 1'
);
$user_stmt->execute([current_user_id()]);
$user = $user_stmt->fetch();

if (!$user) {
    unset($_SESSION['user_id'], $_SESSION['full_name'], $_SESSION['email'], $_SESSION['role']);
    set_flash('error', 'Tài khoản không còn hoạt động.');
    redirect('auth/login.php');
}

$profile_errors = [];
$password_errors = [];
$active_form = $_POST['action'] ?? 'profile';

if (is_post_request()) {
    verify_csrf();

    if ($active_form === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (mb_strlen($full_name) < 2 || mb_strlen($full_name) > 100) {
            $profile_errors['full_name'] = 'Họ và tên phải có từ 2 đến 100 ký tự.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profile_errors['email'] = 'Địa chỉ email không hợp lệ.';
        }

        if ($phone !== '' && !preg_match('/^[0-9+ .-]{8,20}$/', $phone)) {
            $profile_errors['phone'] = 'Số điện thoại không hợp lệ.';
        }

        if (!$profile_errors) {
            $email_stmt = $conn->prepare(
                'SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1'
            );
            $email_stmt->execute([$email, current_user_id()]);

            if ($email_stmt->fetch()) {
                $profile_errors['email'] = 'Email này đã được sử dụng.';
            } else {
                $update_stmt = $conn->prepare(
                    'UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?'
                );
                $update_stmt->execute([
                    $full_name,
                    $email,
                    $phone !== '' ? $phone : null,
                    current_user_id(),
                ]);

                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                set_flash('success', 'Đã cập nhật thông tin tài khoản.');
                redirect('account/');
            }
        }

        $user['full_name'] = $full_name;
        $user['email'] = $email;
        $user['phone'] = $phone;
    } elseif ($active_form === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $password_confirmation = $_POST['password_confirmation'] ?? '';

        if (!password_verify($current_password, $user['password'])) {
            $password_errors['current_password'] = 'Mật khẩu hiện tại không chính xác.';
        }

        if (strlen($new_password) < 8) {
            $password_errors['new_password'] = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
        }

        if ($new_password !== $password_confirmation) {
            $password_errors['password_confirmation'] = 'Mật khẩu xác nhận không khớp.';
        }

        if (!$password_errors) {
            $update_stmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
            $update_stmt->execute([
                password_hash($new_password, PASSWORD_DEFAULT),
                current_user_id(),
            ]);

            session_regenerate_id(true);
            set_flash('success', 'Đã thay đổi mật khẩu.');
            redirect('account/');
        }
    } else {
        http_response_code(400);
        exit('Yêu cầu không hợp lệ.');
    }
}

$page_title = 'Chỉnh sửa tài khoản';
$extra_css = ['account.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="account-section">
    <div class="container edit-account-container">
        <a class="account-back" href="<?= e(url('account/')) ?>">← Quay lại tài khoản</a>
        <div class="edit-heading"><h1>Chỉnh sửa tài khoản</h1><p>Cập nhật thông tin cá nhân hoặc thay đổi mật khẩu.</p></div>

        <div class="edit-account-grid">
            <section class="edit-card">
                <h2>Thông tin cá nhân</h2>
                <form method="post" novalidate>
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group account-field">
                        <label for="full_name">Họ và tên</label>
                        <input id="full_name" name="full_name" type="text" maxlength="100" value="<?= e($user['full_name']) ?>" required>
                        <?php if (isset($profile_errors['full_name'])): ?><small class="field-error"><?= e($profile_errors['full_name']) ?></small><?php endif; ?>
                    </div>
                    <div class="form-group account-field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" maxlength="150" value="<?= e($user['email']) ?>" required>
                        <?php if (isset($profile_errors['email'])): ?><small class="field-error"><?= e($profile_errors['email']) ?></small><?php endif; ?>
                    </div>
                    <div class="form-group account-field">
                        <label for="phone">Số điện thoại</label>
                        <input id="phone" name="phone" type="tel" maxlength="20" value="<?= e($user['phone']) ?>">
                        <?php if (isset($profile_errors['phone'])): ?><small class="field-error"><?= e($profile_errors['phone']) ?></small><?php endif; ?>
                    </div>
                    <button class="button button-primary" type="submit">Lưu thông tin</button>
                </form>
            </section>

            <section class="edit-card">
                <h2>Thay đổi mật khẩu</h2>
                <form method="post" novalidate>
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group account-field">
                        <label for="current_password">Mật khẩu hiện tại</label>
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                        <?php if (isset($password_errors['current_password'])): ?><small class="field-error"><?= e($password_errors['current_password']) ?></small><?php endif; ?>
                    </div>
                    <div class="form-group account-field">
                        <label for="new_password">Mật khẩu mới</label>
                        <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
                        <?php if (isset($password_errors['new_password'])): ?><small class="field-error"><?= e($password_errors['new_password']) ?></small><?php endif; ?>
                    </div>
                    <div class="form-group account-field">
                        <label for="password_confirmation">Xác nhận mật khẩu mới</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
                        <?php if (isset($password_errors['password_confirmation'])): ?><small class="field-error"><?= e($password_errors['password_confirmation']) ?></small><?php endif; ?>
                    </div>
                    <button class="button button-primary" type="submit">Đổi mật khẩu</button>
                </form>
            </section>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
