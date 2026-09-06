<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect('');
}

$error = '';
$email = '';

if (is_post_request()) {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Vui lòng nhập email và mật khẩu hợp lệ.';
    } else {
        $stmt = $conn->prepare(
            'SELECT user_id, full_name, email, password, role, status
             FROM users
             WHERE email = ?
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Email hoặc mật khẩu không chính xác.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Tài khoản này đã bị khóa.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            set_flash('success', 'Đăng nhập thành công.');
            redirect($user['role'] === 'admin' ? 'admin/' : '');
        }
    }
}

$page_title = 'Đăng nhập';
$extra_css = ['auth.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card">
        <div class="auth-heading">
            <span class="auth-icon">🏸</span>
            <h1>Chào mừng trở lại</h1>
            <p>Đăng nhập để tiếp tục đặt sân.</p>
        </div>

        <?php if ($error): ?>
            <div class="form-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_input() ?>

            <div class="form-group auth-field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
            </div>

            <div class="form-group auth-field">
                <label for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <a class="forgot-link" href="<?= e(url('auth/forgot_password.php')) ?>">Quên mật khẩu?</a>
            </div>

            <button class="button button-primary auth-submit" type="submit">Đăng nhập</button>
        </form>

        <p class="auth-switch">Chưa có tài khoản? <a href="<?= e(url('auth/register.php')) ?>">Đăng ký ngay</a></p>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
