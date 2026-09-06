<?php

require_once __DIR__ . '/../config/app.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

function project_image_url(?string $path): ?string
{
    $normalized_path = ltrim(str_replace('\\', '/', trim((string) $path)), '/');

    if ($normalized_path === '' || str_contains($normalized_path, '..')) {
        return null;
    }

    $file_path = dirname(__DIR__)
        . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $normalized_path);

    return is_file($file_path) ? url($normalized_path) : null;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function is_post_request(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_valid_date(string $date): bool
{
    $date_object = DateTime::createFromFormat('Y-m-d', $date);

    return $date_object !== false && $date_object->format('Y-m-d') === $date;
}
