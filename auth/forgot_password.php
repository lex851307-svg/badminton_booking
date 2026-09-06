<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect('');
}

$error = '';
$submitted = false;
$demo_reset_link = '';
$email = '';

if (is_post_request()) {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Vui lòng nhập một địa chỉ email hợp lệ.';
    } else {
        $stmt = $conn->prepare(
            "SELECT user_id FROM users
             WHERE email = ? AND status = 'active'
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expires_at = date('Y-m-d H:i:s', time() + 30 * 60);

            $conn->beginTransaction();
            try {
                $delete_stmt = $conn->prepare(
                    'DELETE FROM password_reset_tokens WHERE user_id = ?'
                );
                $delete_stmt->execute([$user['user_id']]);

                $insert_stmt = $conn->prepare(
                    'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                     VALUES (?, ?, ?)'
                );
                $insert_stmt->execute([$user['user_id'], $token_hash, $expires_at]);
                $conn->commit();

                $demo_reset_link = url('auth/reset_password.php?token=' . urlencode($token));
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log('Create reset token failed: ' . $e->getMessage());
                $error = 'Không thể tạo liên kết đặt lại mật khẩu. Vui lòng thử lại.';
            }
        }

        if (!$error) {
            $submitted = true;
        }
    }
}

$page_title = 'Quên mật khẩu';
$extra_css = ['auth.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card">
        <div class="auth-heading">
            <span class="auth-icon">🔑</span>
            <h1>Quên mật khẩu</h1>
            <p>Nhập email đã đăng ký để tạo liên kết đặt lại mật khẩu.</p>
        </div>

        <?php if ($error): ?><div class="form-error" role="alert"><?= e($error) ?></div><?php endif; ?>

        <?php if ($submitted): ?>
            <div class="reset-message">
                <strong>Yêu cầu đã được tiếp nhận.</strong>
                <p>Nếu email tồn tại, hệ thống sẽ gửi hướng dẫn đặt lại mật khẩu.</p>
            </div>

            <?php if ($demo_reset_link): ?>
                <div class="demo-link">
                    <small>Liên kết minh họa vì đồ án chưa cấu hình gửi email:</small>
                    <a href="<?= e($demo_reset_link) ?>">Đặt lại mật khẩu</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <form method="post" novalidate>
                <?= csrf_input() ?>
                <div class="form-group auth-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
                </div>
                <button class="button button-primary auth-submit" type="submit">Tạo liên kết</button>
            </form>
        <?php endif; ?>

        <p class="auth-switch"><a href="<?= e(url('auth/login.php')) ?>">← Quay lại đăng nhập</a></p>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
