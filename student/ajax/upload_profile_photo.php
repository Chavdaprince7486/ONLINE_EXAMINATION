<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function profile_photo_response(bool $success, string $title, string $message, array $data = [], int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode(
        array_merge([
            'status' => $success ? 'success' : 'error',
            'title' => $title,
            'message' => $message,
        ], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    profile_photo_response(false, 'Invalid Request', 'This endpoint accepts POST requests only.', [], 405);
}

require_role('student');

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    profile_photo_response(false, 'Security Check Failed', 'Your session security token is invalid. Refresh the page and try again.', [], 419);
}

if (!isset($_FILES['profile_photo']) || !is_array($_FILES['profile_photo'])) {
    profile_photo_response(false, 'No File', 'Please select a profile image.', [], 422);
}

$file = $_FILES['profile_photo'];

if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    profile_photo_response(false, 'Upload Failed', 'The selected image could not be uploaded.', [], 422);
}

if (!isset($file['tmp_name'], $file['size'], $file['name']) || !is_uploaded_file((string) $file['tmp_name'])) {
    profile_photo_response(false, 'Invalid Upload', 'The uploaded file is not valid.', [], 422);
}

$maxSize = 2 * 1024 * 1024;
if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxSize) {
    profile_photo_response(false, 'Invalid File Size', 'Profile photo must be larger than 0 bytes and no more than 2 MB.', [], 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = (string) $finfo->file((string) $file['tmp_name']);

$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];

if (!isset($allowedMimeTypes[$mimeType])) {
    profile_photo_response(false, 'Invalid Image', 'Only JPG, JPEG and PNG images are allowed.', [], 422);
}

$imageInfo = @getimagesize((string) $file['tmp_name']);
if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
    profile_photo_response(false, 'Invalid Image', 'The selected file is not a valid image.', [], 422);
}

$width = (int) $imageInfo[0];
$height = (int) $imageInfo[1];

if ($width < 80 || $height < 80 || $width > 5000 || $height > 5000) {
    profile_photo_response(false, 'Invalid Dimensions', 'Profile photo dimensions must be between 80×80 and 5000×5000 pixels.', [], 422);
}

$extension = $allowedMimeTypes[$mimeType];
$studentId = current_user_id();
$uploadDir = dirname(__DIR__, 2) . '/uploads/students/';

if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    profile_photo_response(false, 'Storage Error', 'Profile image storage is not available.', [], 500);
}

if (!is_writable($uploadDir)) {
    profile_photo_response(false, 'Storage Error', 'Profile image storage is not writable.', [], 500);
}

try {
    $studentStatement = $conn->prepare(
        "SELECT profile_photo
         FROM students
         WHERE id = ? AND status = 'Active'
         LIMIT 1
         FOR UPDATE"
    );

    $conn->beginTransaction();
    $studentStatement->execute([$studentId]);
    $student = $studentStatement->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $conn->rollBack();
        profile_photo_response(false, 'Student Not Found', 'Unable to find your active student account.', [], 404);
    }

    $newFileName = 'student_' . $studentId . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = $uploadDir . $newFileName;

    if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
        $conn->rollBack();
        profile_photo_response(false, 'Upload Failed', 'Unable to save the profile image.', [], 500);
    }

    $update = $conn->prepare(
        "UPDATE students
         SET profile_photo = ?
         WHERE id = ? AND status = 'Active'"
    );
    $update->execute([$newFileName, $studentId]);

    if ($update->rowCount() < 1) {
        if (is_file($destination)) {
            unlink($destination);
        }
        $conn->rollBack();
        profile_photo_response(false, 'Update Failed', 'Unable to update the profile image record.', [], 500);
    }

    $conn->commit();

    $oldPhoto = basename((string) ($student['profile_photo'] ?? ''));
    if ($oldPhoto !== '' && $oldPhoto !== $newFileName) {
        $oldPath = $uploadDir . $oldPhoto;
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    profile_photo_response(true, 'Profile Photo Updated', 'Your profile photo has been updated successfully.', [
        'photo' => '../uploads/students/' . rawurlencode($newFileName),
    ]);
} catch (Throwable $exception) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Student profile photo upload failed: ' . $exception->getMessage());
    profile_photo_response(false, 'Upload Failed', 'Unable to update your profile photo right now.', [], 500);
}
