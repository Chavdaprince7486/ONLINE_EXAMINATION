<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {
    http_response_code(404);
    exit;
}

$studentId = (int) $_SESSION['user_id'];

try {
    $stmt = $conn->prepare(
        'SELECT profile_photo FROM students WHERE id = ? AND status = \'Active\' LIMIT 1'
    );
    $stmt->execute([$studentId]);
    $photoName = trim((string)($stmt->fetchColumn() ?? ''));

    if ($photoName === '') {
        http_response_code(404);
        exit;
    }

    $safeName = basename($photoName);
    if ($safeName === '' || $safeName === '.' || $safeName === '..') {
        http_response_code(404);
        exit;
    }

    $file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'students' . DIRECTORY_SEPARATOR . $safeName;

    if (!is_file($file) || !is_readable($file)) {
        http_response_code(404);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        http_response_code(404);
        exit;
    }

    $size = filesize($file);
    $modified = filemtime($file) ?: time();

    if ($size !== false) {
        header('Content-Length: ' . (string)$size);
    }
    header('Content-Type: ' . $mime);
    header('ETag: "student-photo-' . sha1($studentId . '|' . $safeName . '|' . $modified) . '"');

    readfile($file);
} catch (Throwable $exception) {
    error_log('Student profile photo serving failed: ' . $exception->getMessage());
    http_response_code(404);
}
