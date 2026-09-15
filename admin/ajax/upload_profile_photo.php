<?php
declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/auth.php';

require_login('admin');

header('Content-Type: application/json; charset=utf-8');

try {
    $adminId = current_user_id();

    $token = $_POST['csrf_token'] ?? null;
    if (function_exists('verify_csrf_token') && !verify_csrf_token(is_string($token) ? $token : null)) {
        throw new RuntimeException('Security verification failed. Refresh the page and try again.');
    }

    /* Ensure persistent storage exists in the original admins table. */
    $columnCheck = $conn->query("SHOW COLUMNS FROM admins LIKE 'profile_photo'");
    if (!$columnCheck->fetch()) {
        $conn->exec("ALTER TABLE admins ADD COLUMN profile_photo VARCHAR(255) NULL DEFAULT NULL AFTER password");
    }

    $file = $_FILES['profile_photo'] ?? null;
    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please select a profile photo.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Invalid uploaded file.');
    if ($size <= 0 || $size > 2 * 1024 * 1024) throw new RuntimeException('Maximum image size is 2 MB.');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($map[$mime])) throw new RuntimeException('Only JPG, PNG and WebP images are allowed.');

    $uploadDir = dirname(__DIR__, 2) . '/uploads/admins';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create upload directory.');
    }

    $oldStmt = $conn->prepare('SELECT profile_photo FROM admins WHERE id = ? LIMIT 1');
    $oldStmt->execute([$adminId]);
    $oldPhoto = trim((string)($oldStmt->fetchColumn() ?: ''));

    $fileName = 'admin_' . $adminId . '_' . bin2hex(random_bytes(8)) . '.' . $map[$mime];
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($tmp, $destination)) throw new RuntimeException('Unable to save the uploaded photo.');

    $stmt = $conn->prepare('UPDATE admins SET profile_photo = ? WHERE id = ?');
    $stmt->execute([$fileName, $adminId]);
    if ($stmt->rowCount() < 1) {
        @unlink($destination);
        throw new RuntimeException('Admin profile could not be updated.');
    }

    $_SESSION['admin_profile_photo'] = $fileName;

    if ($oldPhoto !== '' && basename($oldPhoto) !== $fileName) {
        $oldPath = $uploadDir . DIRECTORY_SEPARATOR . basename($oldPhoto);
        if (is_file($oldPath)) @unlink($oldPath);
    }

    $photoUrl = rtrim(BASE_URL, '/') . '/uploads/admins/' . rawurlencode($fileName) . '?v=' . (string)@filemtime($destination);

    echo json_encode([
        'status' => 'success',
        'message' => 'Profile photo updated successfully.',
        'photo_url' => $photoUrl
    ], JSON_UNESCAPED_SLASHES);

} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $exception->getMessage()
    ]);
}
