<?php
declare(strict_types=1);
require_once '../config/session.php';
require_once '../config/config.php';

function reset_back(string $message): never {
    $_SESSION['forgot_error'] = $message;
    header('Location: reset_password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

$fp = $_SESSION['forgot_password'] ?? null;
if (!is_array($fp) || empty($fp['verified']) || empty($fp['id']) || empty($fp['table'])) {
    header('Location: forgot_password.php');
    exit;
}

$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');

if (strlen($password) < 8) {
    reset_back('Password must be at least 8 characters.');
}
if ($password !== $confirm) {
    reset_back('New password and confirm password do not match.');
}

$allowedTables = ['students','teachers','admins'];
$table = (string)$fp['table'];
if (!in_array($table, $allowedTables, true)) {
    header('Location: forgot_password.php');
    exit;
}

try {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) {
        reset_back('Unable to secure the new password.');
    }

    $stmt = $conn->prepare("UPDATE {$table} SET password = ? WHERE id = ? LIMIT 1");
    $stmt->execute([$hash, (int)$fp['id']]);

    if ($stmt->rowCount() < 1) {
        reset_back('Password could not be updated. Please try again.');
    }

    unset($_SESSION['forgot_password']);
    $_SESSION['success_message'] = 'Password reset successfully. Please login with your new password.';
    header('Location: login.php');
    exit;
} catch (Throwable $e) {
    error_log('ExamSphere password reset error: '.$e->getMessage());
    reset_back('Unable to reset your password right now. Please try again.');
}
