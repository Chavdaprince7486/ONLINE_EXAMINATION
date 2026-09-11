<?php

declare(strict_types=1);

require_once '../config/session.php';

$error =
    $_SESSION['forgot_error']
    ?? '';

$success =
    $_SESSION['forgot_success']
    ?? '';

$oldEmail =
    $_SESSION['forgot_old_email']
    ?? '';

unset(
    $_SESSION['forgot_error'],
    $_SESSION['forgot_success'],
    $_SESSION['forgot_old_email']
);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Forgot Password | ExamSphere
</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="../assets/css/login.css"
>

<style>

.reset-message {
    padding:12px 15px;
    margin-bottom:18px;
    border-radius:12px;
    font-size:14px;
}

.reset-error {
    background:#fff0f0;
    color:#a33;
}

.reset-success {
    background:#eefaf1;
    color:#28743d;
}

</style>

</head>

<body>

<div class="login-wrapper">

    <div class="login-card">

        <div class="logo-area">

            <img
                src="../assets/images/exam_logo.png"
                alt="ExamSphere"
            >

            <h2>
                ExamSphere
            </h2>

            <p>
                Password Recovery
            </p>

        </div>


        <div class="login-title">

            <h1>
                Forgot Password
            </h1>

            <p>
                Enter your registered student email address.
            </p>

        </div>


        <?php if ($error !== ''): ?>

            <div class="reset-message reset-error">

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <?php if ($success !== ''): ?>

            <div class="reset-message reset-success">

                <?= htmlspecialchars(
                    $success,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <form
            action="forgot_password_process.php"
            method="POST"
            autocomplete="off"
        >

            <?= csrf_field() ?>


            <div class="form-group">

                <label
                    for="email"
                >
                    Email Address
                </label>

                <div class="input-box">

                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars(
                            $oldEmail,
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>"
                        required
                        maxlength="150"
                        autocomplete="email"
                        placeholder="Enter your registered email"
                    >

                </div>

            </div>


            <button
                class="login-btn"
                type="submit"
            >
                Send OTP
            </button>

        </form>


        <br>


        <a href="login.php">
            ← Back to Login
        </a>

    </div>

</div>

</body>

</html>