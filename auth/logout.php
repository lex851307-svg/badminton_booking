<?php

require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_post_request()) {
    http_response_code(405);
    exit('Phương thức không được hỗ trợ.');
}

verify_csrf();

unset(
    $_SESSION['user_id'],
    $_SESSION['full_name'],
    $_SESSION['email'],
    $_SESSION['role'],
    $_SESSION['csrf_token']
);

session_regenerate_id(true);
set_flash('success', 'Bạn đã đăng xuất.');
redirect('');
