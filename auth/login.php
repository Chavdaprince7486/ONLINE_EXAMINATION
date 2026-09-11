<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SESSION + AUTHENTICATION BOOTSTRAP
|--------------------------------------------------------------------------
|
| session.php defines csrf_token()/csrf_field()/verify_csrf_token().
| Load it explicitly here so the login page does not depend on another
| include chain for CSRF helpers.
|
|--------------------------------------------------------------------------
*/
require_once '../config/session.php';
require_once '../config/auth.php';

$existingUserId =
    current_user_id();

$existingRole =
    current_user_role();

if (
    $existingUserId > 0 &&
    $existingRole !== ''
) {
    header(
        'Location: ' .
        dashboard_url_for_role(
            $existingRole
        )
    );

    exit;
}

clear_invalid_auth_session();

$success =
    $_SESSION['success_message']
    ?? '';

$error =
    $_SESSION['error']
    ?? '';

unset(
    $_SESSION['success_message'],
    $_SESSION['error']
);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ExamSphere</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body class="auth-page">
<main class="auth-shell">
    <aside class="auth-showcase">
        <a class="auth-brand" href="../index.php">
            <img src="../assets/images/exam_logo.png" alt="ExamSphere logo">
            <span><b>Exam<span>Sphere</span></b><small>SMART ASSESSMENT, SEAMLESS LEARNING, REAL RESULTS.</small></span>
        </a>
        <div class="showcase-copy">
            <p class="eyebrow">WELCOME BACK</p>
            <h1>Login to <span>continue.</span></h1>
            <p>Access your practice exams, track your performance, and achieve your goals with ExamSphere.</p>
        </div>
        <div class="benefit-list">
            <div><i class="fa-solid fa-file-pen"></i><span><b>Practice &amp; Live Exams</b><small>Unlimited practice and premium live exams.</small></span></div>
            <div><i class="fa-solid fa-chart-column"></i><span><b>Performance Analytics</b><small>Clear insights to improve each attempt.</small></span></div>
            <div><i class="fa-solid fa-trophy"></i><span><b>Leaderboard</b><small>Track your progress and climb the ranks.</small></span></div>
            <div><i class="fa-solid fa-shield-halved"></i><span><b>Secure &amp; Reliable</b><small>Your account is protected at every step.</small></span></div>
        </div>
        <a class="back-home" href="../index.php"><i class="fa-solid fa-arrow-left"></i> Back to home</a>
    </aside>

    <section class="auth-main">
        <div class="auth-card">
            <div class="auth-card-logo"><img src="../assets/images/exam_logo.png" alt="ExamSphere"></div>
            <header class="auth-heading">
                <h2>Login to <span>ExamSphere</span></h2>
                <p>Enter your credentials to access your account.</p>
            </header>

            <?php if ($success): ?><div class="auth-alert success"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <?php if ($error): ?><div class="auth-alert error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

            <form
                id="loginForm"
                action="login_process.php"
                method="POST"
                autocomplete="on"
            >

    
                <?= csrf_field() ?>
                <input type="hidden" name="role" id="role" value="student">
                <div class="field-label">Login as</div>
                <div class="role-selector" role="group" aria-label="Choose account role">
                    <button type="button" class="role-btn active" data-role="student"><i class="fa-solid fa-user-graduate"></i> Student</button>
                    <button type="button" class="role-btn" data-role="teacher"><i class="fa-solid fa-chalkboard-user"></i> Teacher</button>
                    <button type="button" class="role-btn" data-role="admin"><i class="fa-solid fa-user-shield"></i> Admin</button>
                </div>

                <div class="form-group">
                    <label for="email">Email address</label>
                    <div class="input-box"><i class="fa-regular fa-envelope"></i><input id="email" type="email" name="email" autocomplete="email" placeholder="Enter your email address" required></div>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-box"><i class="fa-solid fa-lock"></i><input id="password" type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required><button id="togglePassword" type="button" aria-label="Show or hide password"><i class="fa-regular fa-eye"></i></button></div>
                </div>
                <div class="option-area">
                    <label><input type="checkbox" name="remember"> <span>Remember me</span></label>
                    <a href="forgot_password.php">Forgot password?</a>
                </div>
                <button class="login-btn" type="submit"><i class="fa-solid fa-lock"></i> Login</button>
            </form>

            <div class="divider"><span>OR</span></div>
            <a href="register.php" class="register-btn"><i class="fa-solid fa-user-plus"></i> New student? Register here</a>
            <p class="security-box"><i class="fa-solid fa-shield-halved"></i> Your data is safe and secure with us.</p>
        </div>
    </section>
</main>
<script src="../assets/js/login.js"></script>
</body>
</html>
