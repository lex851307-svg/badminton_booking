<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect('');
}

$token = is_post_request() ? ($_POST['token'] ?? '') : ($_GET['token'] ?? '');
$token = is_string($token) ? trim($token) : '';
$token_hash = strlen($token) === 64 ? hash('sha256', $token) : '';
$error = '';
$token_record = null;

if ($token_hash !== '') {
    $token_stmt = $conn->prepare(
        "SELECT pr.reset_id, pr.user_id, u.email
         FROM password_reset_tokens pr
         JOIN users u ON u.user_id = pr.user_id
         WHERE pr.token_hash = ?
           AND pr.used_at IS NULL
           AND pr.expires_at > NOW()
           AND u.status = 'active'
         LIMIT 1"
    );
    $token_stmt->execute([$token_hash]);
    $token_record = $token_stmt->fetch();
}

if (!$token_record) {
    $error = 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.';
}

if (is_post_request() && $token_record) {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $password_confirmation = $_POST['password_confirmation'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    } elseif ($password !== $password_confirmation) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } else {
        try {
            $conn->beginTransaction();

            $lock_stmt = $conn->prepare(
                "SELECT reset_id, user_id
                 FROM password_reset_tokens
                 WHERE reset_id = ? AND used_at IS NULL AND expires_at > NOW()
                 FOR UPDATE"
            );
            $lock_stmt->execute([$token_record['reset_id']]);
            $locked_token = $lock_stmt->fetch();

            if (!$locked_token) {
                throw new RuntimeException('Reset token is no longer valid.');
            }

            $password_stmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
            $password_stmt->execute([
                password_hash($password, PASSWORD_DEFAULT),
                $locked_token['user_id'],
            ]);

            $used_stmt = $conn->prepare(
                'UPDATE password_reset_tokens SET used_at = NOW() WHERE reset_id = ?'
            );
            $used_stmt->execute([$locked_token['reset_id']]);

            $conn->commit();
            set_flash('success', 'Đã đặt lại mật khẩu. Bạn có thể đăng nhập bằng mật khẩu mới.');
            redirect('auth/login.php');
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Reset password failed: ' . $e->getMessage());
            $error = 'Không thể đặt lại mật khẩu. Vui lòng tạo liên kết mới.';
        }
    }
}

$page_title = 'Đặt lại mật khẩu';
$extra_css = ['auth.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card">
        <div class="auth-heading">
            <span class="auth-icon">🔒</span>
            <h1>Đặt lại mật khẩu</h1>
            <p>Tạo mật khẩu mới cho tài khoản của bạn.</p>
        </div>

        <?php if ($error): ?><div class="form-error" role="alert"><?= e($error) ?></div><?php endif; ?>

        <?php if ($token_record): ?>
            <form method="post" novalidate>
                <?= csrf_input() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="form-group auth-field">
                    <label for="password">Mật khẩu mới</label>
                    <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required autofocus>
                </div>
                <div class="form-group auth-field">
                    <label for="password_confirmation">Xác nhận mật khẩu mới</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
                </div>
                <button class="button button-primary auth-submit" type="submit">Đặt lại mật khẩu</button>
            </form>
        <?php else: ?>
            <a class="button button-primary auth-submit" href="<?= e(url('auth/forgot_password.php')) ?>">Tạo liên kết mới</a>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
