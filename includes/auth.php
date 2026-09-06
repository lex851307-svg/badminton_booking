<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/functions.php';

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user_id(): ?int
{
    return is_logged_in() ? (int) $_SESSION['user_id'] : null;
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? 'user') === 'admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Vui lòng đăng nhập để tiếp tục.');
        redirect('auth/login.php');
    }
}

function require_admin(): void
{
    if (!is_admin()) {
        http_response_code(403);
        exit('Bạn không có quyền truy cập trang này.');
    }
}
