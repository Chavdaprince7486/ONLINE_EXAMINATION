<?php
declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/mail.php';

function fp_back_error(string $message): never {
    $_SESSION['forgot_error'] = $message;
    header('Location: forgot_password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

$role = strtolower(trim((string)($_POST['role'] ?? '')));
$email = strtolower(trim((string)($_POST['email'] ?? '')));

$roleMap = [
    'student' => 'students',
    'teacher' => 'teachers',
    'admin'   => 'admins',
];

if (!isset($roleMap[$role])) {
    fp_back_error('Please select a valid account type.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fp_back_error('Please enter a valid email address.');
}

try {
    $table = $roleMap[$role];
    $stmt = $conn->prepare("SELECT id, full_name, email FROM {$table} WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        fp_back_error('No account was found with this email for the selected account type.');
    }

    $otp = (string)random_int(100000, 999999);

    $_SESSION['forgot_password'] = [
        'id' => (int)$user['id'],
        'name' => (string)$user['full_name'],
        'email' => (string)$user['email'],
        'role' => $role,
        'table' => $table,
        'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
        'expiry' => time() + 300,
        'verified' => false,
    ];

    $mail = getMailer();
    $mail->clearAddresses();
    $mail->isHTML(true);
    $mail->addAddress((string)$user['email'], (string)$user['full_name']);
    $mail->Subject = 'ExamSphere Password Reset OTP';

    $safeName = htmlspecialchars((string)$user['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mail->Body = '
    <div style="font-family:Arial,sans-serif;max-width:620px;margin:0 auto;background:#F5F5DC;padding:28px;color:#3E2723">
      <div style="background:#fff;border:1px solid #E3DCD2;border-radius:20px;padding:30px">
        <div style="text-align:center">
          <div style="font-size:28px;font-weight:900;color:#5D4037">Exam<span style="color:#556B2F">Sphere</span></div>
          <div style="font-size:11px;color:#756B63;letter-spacing:1px;margin-top:4px">SMART ASSESSMENT. SEAMLESS LEARNING. REAL RESULTS.</div>
        </div>
        <h2 style="color:#3E2723;margin-top:28px">Password Reset</h2>
        <p>Hello <strong>'.$safeName.'</strong>,</p>
        <p>Use the following OTP to reset your ExamSphere password:</p>
        <div style="text-align:center;margin:28px 0">
          <span style="display:inline-block;padding:16px 24px;background:#5D4037;color:#fff;border-radius:14px;font-size:30px;letter-spacing:8px;font-weight:900">'.$otp.'</span>
        </div>
        <p style="color:#756B63">This OTP is valid for <strong>5 minutes</strong>.</p>
        <p style="color:#756B63">If you did not request a password reset, you can safely ignore this email.</p>
      </div>
    </div>';

    if (!$mail->send()) {
        unset($_SESSION['forgot_password']);
        fp_back_error('Unable to send OTP. Please check your email settings and try again.');
    }

    $_SESSION['forgot_success'] = 'OTP sent successfully. Please check your email.';
    header('Location: verify_reset_otp.php');
    exit;
} catch (Throwable $e) {
    error_log('ExamSphere forgot password error: '.$e->getMessage());
    fp_back_error('Unable to process your request right now. Please try again.');
}
