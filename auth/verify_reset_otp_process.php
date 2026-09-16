<?php
declare(strict_types=1);
require_once '../config/session.php';
require_once '../config/config.php';

function otp_back(string $message): never {
    $_SESSION['forgot_error'] = $message;
    header('Location: verify_reset_otp.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

$fp = $_SESSION['forgot_password'] ?? null;
$otp = preg_replace('/\D+/', '', (string)($_POST['otp'] ?? ''));

if (!is_array($fp) || empty($fp['otp_hash']) || empty($fp['expiry'])) {
    header('Location: forgot_password.php');
    exit;
}

if ((int)$fp['expiry'] < time()) {
    unset($_SESSION['forgot_password']);
    otp_back('Your OTP has expired. Please request a new OTP.');
}

if (strlen($otp) !== 6 || !password_verify($otp, (string)$fp['otp_hash'])) {
    otp_back('Invalid OTP. Please enter the correct 6-digit OTP.');
}

$_SESSION['forgot_password']['verified'] = true;
unset($_SESSION['forgot_password']['otp_hash'], $_SESSION['forgot_password']['expiry']);

$_SESSION['forgot_success'] = 'OTP verified. Please create your new password.';
header('Location: reset_password.php');
exit;
