<?php

const COURT_IMAGE_MAX_SIZE = 5 * 1024 * 1024;

function store_court_image(array $file): array
{
    $upload_error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($upload_error === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }

    if ($upload_error !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'Không thể tải ảnh lên. Vui lòng thử lại.'];
    }

    $temporary_path = $file['tmp_name'] ?? '';
    $file_size = (int) ($file['size'] ?? 0);

    if ($temporary_path === '' || !is_uploaded_file($temporary_path)) {
        return ['path' => null, 'error' => 'Tệp tải lên không hợp lệ.'];
    }

    if ($file_size < 1 || $file_size > COURT_IMAGE_MAX_SIZE) {
        return ['path' => null, 'error' => 'Ảnh phải có dung lượng không quá 5 MB.'];
    }

    $image_info = @getimagesize($temporary_path);
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime_type = is_array($image_info) ? ($image_info['mime'] ?? '') : '';

    if (!isset($allowed_types[$mime_type])) {
        return ['path' => null, 'error' => 'Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP.'];
    }

    $width = (int) ($image_info[0] ?? 0);
    $height = (int) ($image_info[1] ?? 0);

    if ($width < 300 || $height < 200 || $width > 6000 || $height > 6000) {
        return ['path' => null, 'error' => 'Kích thước ảnh phải từ 300×200 đến 6000×6000 pixel.'];
    }

    $image_directory = dirname(__DIR__) . '/assets/images';

    if (!is_dir($image_directory) && !mkdir($image_directory, 0755, true)) {
        return ['path' => null, 'error' => 'Không thể tạo thư mục lưu ảnh.'];
    }

    $file_name = 'court-' . bin2hex(random_bytes(12)) . '.' . $allowed_types[$mime_type];
    $destination = $image_directory . '/' . $file_name;

    if (!move_uploaded_file($temporary_path, $destination)) {
        return ['path' => null, 'error' => 'Không thể lưu ảnh vào hệ thống.'];
    }

    return ['path' => 'assets/images/' . $file_name, 'error' => null];
}

function remove_stored_court_image(?string $path): void
{
    $normalized_path = ltrim(str_replace('\\', '/', trim((string) $path)), '/');

    if (!preg_match('#^assets/images/court-[a-f0-9]{24}\.(jpg|png|webp)$#', $normalized_path)) {
        return;
    }

    $file_path = dirname(__DIR__) . '/' . $normalized_path;

    if (is_file($file_path)) {
        unlink($file_path);
    }
}
