<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function password_change_response(bool $success, string $title, string $message, int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode([
        'status' => $success ? 'success' : 'error',
        'title' => $title,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    password_change_response(false, 'Invalid Request', 'This endpoint accepts POST requests only.', 405);
}

require_role('student');

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody ?: '', true);

if (!is_array($data)) {
    password_change_response(false, 'Invalid Request', 'Invalid password request payload.', 400);
}

if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    password_change_response(false, 'Security Check Failed', 'Your session security token is invalid. Refresh the page and try again.', 419);
}

$currentPassword = (string) ($data['current_password'] ?? '');
$newPassword = (string) ($data['new_password'] ?? '');
$confirmPassword = (string) ($data['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    password_change_response(false, 'Validation Error', 'All password fields are required.', 422);
}

if (strlen($currentPassword) > 255 || strlen($newPassword) > 72 || strlen($confirmPassword) > 72) {
    password_change_response(false, 'Invalid Password', 'The supplied password is too long.', 422);
}

if (strlen($newPassword) < 8) {
    password_change_response(false, 'Weak Password', 'New password must contain at least 8 characters.', 422);
}

if (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
    password_change_response(false, 'Weak Password', 'New password must contain at least one letter and one number.', 422);
}

if ($newPassword !== $confirmPassword) {
    password_change_response(false, 'Password Mismatch', 'New password and confirmation do not match.', 422);
}

if ($currentPassword === $newPassword) {
    password_change_response(false, 'Same Password', 'New password must be different from the current password.', 422);
}

$studentId = current_user_id();

try {
    $statement = $conn->prepare(
        "SELECT password
         FROM students
         WHERE id = ? AND status = 'Active'
         LIMIT 1
         FOR UPDATE"
    );

    $conn->beginTransaction();
    $statement->execute([$studentId]);
    $student = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $conn->rollBack();
        password_change_response(false, 'Student Not Found', 'Unable to find your active student account.', 404);
    }

    $storedPassword = (string) ($student['password'] ?? '');
    $currentPasswordValid = password_verify($currentPassword, $storedPassword);
    $legacyPasswordMatch = false;

    if (!$currentPasswordValid && $storedPassword !== '') {
        $legacyPasswordMatch = hash_equals($storedPassword, $currentPassword);
    }

    if (!$currentPasswordValid && !$legacyPasswordMatch) {
        $conn->rollBack();
        password_change_response(false, 'Incorrect Password', 'Current password is incorrect.', 422);
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    if ($hashedPassword === false) {
        throw new RuntimeException('Password hashing failed.');
    }

    $update = $conn->prepare(
        "UPDATE students
         SET password = ?
         WHERE id = ? AND status = 'Active'"
    );
    $update->execute([$hashedPassword, $studentId]);

    if ($update->rowCount() < 1) {
        throw new RuntimeException('Password update was not persisted.');
    }

    $conn->commit();

    password_change_response(true, 'Password Updated', 'Your password has been changed successfully.');
} catch (Throwable $exception) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Student password update failed: ' . $exception->getMessage());
    password_change_response(false, 'Update Failed', 'Unable to update your password right now.', 500);
}
