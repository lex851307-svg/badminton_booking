<?php

require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$page_title = $page_title ?? APP_NAME;
$active_page = $active_page ?? '';
$extra_css = $extra_css ?? [];
$flash = get_flash();
$main_css_path = dirname(__DIR__) . '/assets/css/main.css';
$main_css_version = is_file($main_css_path) ? (string) filemtime($main_css_path) : '1';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/css/main.css?v=' . $main_css_version)) ?>">
    <?php foreach ($extra_css as $css_file): ?>
        <?php
        $extra_css_path = dirname(__DIR__) . '/assets/css/' . $css_file;
        $extra_css_version = is_file($extra_css_path) ? (string) filemtime($extra_css_path) : '1';
        ?>
        <link rel="stylesheet" href="<?= e(url('assets/css/' . $css_file . '?v=' . $extra_css_version)) ?>">
    <?php endforeach; ?>
</head>
<body>
    <header class="site-header">
        <div class="container nav-wrapper">
            <a class="brand" href="<?= e(url()) ?>">
                <span class="brand-icon">🏸</span>
                <span>Badminton Booking</span>
            </a>

            <button class="menu-button" type="button" aria-label="Mở menu" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="main-nav" aria-label="Điều hướng chính">
                <a class="<?= $active_page === 'home' ? 'active' : '' ?>" href="<?= e(url()) ?>">
                    Trang chủ
                </a>
                <a class="<?= $active_page === 'courts' ? 'active' : '' ?>" href="<?= e(url('courts/')) ?>">
                    Tìm sân
                </a>

                <?php if (is_logged_in()): ?>
                    <a class="<?= $active_page === 'history' ? 'active' : '' ?>" href="<?= e(url('booking/history.php')) ?>">
                        Lịch sử đặt sân
                    </a>

                    <?php if (is_admin()): ?>
                        <a class="<?= $active_page === 'admin' ? 'active' : '' ?>" href="<?= e(url('admin/')) ?>">Quản lý</a>
                    <?php endif; ?>

                    <a href="<?= e(url('account/')) ?>"><?= e($_SESSION['full_name'] ?? 'Tài khoản') ?></a>

                    <form class="logout-form" action="<?= e(url('auth/logout.php')) ?>" method="post">
                        <?= csrf_input() ?>
                        <button type="submit">Đăng xuất</button>
                    </form>
                <?php else: ?>
                    <a href="<?= e(url('auth/login.php')) ?>">Đăng nhập</a>
                    <a class="nav-register" href="<?= e(url('auth/register.php')) ?>">Đăng ký</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="container flash flash-<?= e($flash['type']) ?>" role="alert">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <main>
