<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function profile_update_response(bool $success, string $title, string $message, int $httpCode = 200): never
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
    profile_update_response(false, 'Invalid Request', 'This endpoint accepts POST requests only.', 405);
}

require_role('student');

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    profile_update_response(false, 'Security Check Failed', 'Your session security token is invalid. Refresh the page and try again.', 419);
}

$studentId = current_user_id();
$fullName = trim((string) ($_POST['full_name'] ?? ''));
$mobile = trim((string) ($_POST['mobile'] ?? ''));
$gender = trim((string) ($_POST['gender'] ?? ''));
$dob = trim((string) ($_POST['dob'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$city = trim((string) ($_POST['city'] ?? ''));
$state = trim((string) ($_POST['state'] ?? ''));
$pincode = trim((string) ($_POST['pincode'] ?? ''));

if ($fullName === '' || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
    profile_update_response(false, 'Invalid Name', 'Full name must be between 2 and 100 characters.', 422);
}

if (!preg_match('/^[\p{L}][\p{L} .\-\']{1,99}$/u', $fullName)) {
    profile_update_response(false, 'Invalid Name', 'Please enter a valid full name.', 422);
}

if ($mobile === '' || !preg_match('/^[0-9]{10,15}$/', $mobile)) {
    profile_update_response(false, 'Invalid Mobile', 'Mobile number must contain 10 to 15 digits.', 422);
}

if ($gender !== '' && !in_array($gender, ['Male', 'Female', 'Other'], true)) {
    profile_update_response(false, 'Invalid Gender', 'Please select a valid gender.', 422);
}

if ($dob !== '') {
    $dobDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dob);
    $dateErrors = DateTimeImmutable::getLastErrors();

    if (
        !$dobDate ||
        ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
        $dobDate->format('Y-m-d') !== $dob ||
        $dobDate > new DateTimeImmutable('today')
    ) {
        profile_update_response(false, 'Invalid Date of Birth', 'Date of birth must be a valid date and cannot be in the future.', 422);
    }
}

if (mb_strlen($address) > 2000) {
    profile_update_response(false, 'Address Too Long', 'Address cannot exceed 2000 characters.', 422);
}

if (mb_strlen($city) > 80 || ($city !== '' && !preg_match('/^[\p{L}0-9 .\-\']+$/u', $city))) {
    profile_update_response(false, 'Invalid City', 'Please enter a valid city.', 422);
}

if (mb_strlen($state) > 80 || ($state !== '' && !preg_match('/^[\p{L}0-9 .\-\']+$/u', $state))) {
    profile_update_response(false, 'Invalid State', 'Please enter a valid state.', 422);
}

if ($pincode !== '' && !preg_match('/^[0-9]{4,10}$/', $pincode)) {
    profile_update_response(false, 'Invalid Pincode', 'Pincode must contain 4 to 10 digits.', 422);
}

try {
    $studentStatement = $conn->prepare(
        "SELECT id, mobile
         FROM students
         WHERE id = ? AND status = 'Active'
         LIMIT 1
         FOR UPDATE"
    );
    $conn->beginTransaction();
    $studentStatement->execute([$studentId]);

    if (!$studentStatement->fetch(PDO::FETCH_ASSOC)) {
        $conn->rollBack();
        profile_update_response(false, 'Student Not Found', 'Unable to find your active student account.', 404);
    }

    $mobileCheck = $conn->prepare(
        "SELECT id
         FROM students
         WHERE mobile = ? AND id <> ?
         LIMIT 1"
    );
    $mobileCheck->execute([$mobile, $studentId]);

    if ($mobileCheck->fetchColumn()) {
        $conn->rollBack();
        profile_update_response(false, 'Duplicate Mobile', 'This mobile number is already registered with another student account.', 409);
    }

    $update = $conn->prepare(
        "UPDATE students
         SET full_name = ?,
             mobile = ?,
             gender = ?,
             dob = ?,
             address = ?,
             city = ?,
             state = ?,
             pincode = ?
         WHERE id = ? AND status = 'Active'"
    );

    $update->execute([
        $fullName,
        $mobile,
        $gender !== '' ? $gender : null,
        $dob !== '' ? $dob : null,
        $address !== '' ? $address : null,
        $city !== '' ? $city : null,
        $state !== '' ? $state : null,
        $pincode !== '' ? $pincode : null,
        $studentId,
    ]);

    if ($update->rowCount() < 1) {
        $conn->rollBack();
        profile_update_response(false, 'No Changes', 'No profile changes were detected.', 200);
    }

    $conn->commit();
    $_SESSION['user_name'] = $fullName;

    profile_update_response(true, 'Profile Updated', 'Your profile has been updated successfully.');
} catch (Throwable $exception) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Student profile update failed: ' . $exception->getMessage());
    profile_update_response(false, 'Update Failed', 'Unable to update your profile right now.', 500);
}
