<?php

declare(strict_types=1);

require_once '../config/session.php';


if (
    empty(
        $_SESSION['forgot_password']
    )
) {

    header(
        'Location: forgot_password.php'
    );

    exit;
}


$data =
    $_SESSION['forgot_password'];


$email =
    htmlspecialchars(
        (string) (
            $data['email'] ?? ''
        ),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );


$error =
    $_SESSION['reset_otp_error']
    ?? '';


$success =
    $_SESSION['reset_otp_success']
    ?? '';


unset(
    $_SESSION['reset_otp_error'],
    $_SESSION['reset_otp_success']
);


$remainingSeconds =
    max(
        0,
        (int) (
            $data['expiry']
            ?? 0
        ) - time()
    );


$canResend =
    (
        time()
        -
        (int) (
            $data['otp_sent_at']
            ?? 0
        )
    )
    >= 60;

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
    Verify Password Reset OTP | ExamSphere
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

.otp-message {
    padding:12px 15px;
    margin-bottom:18px;
    border-radius:12px;
    font-size:14px;
}

.otp-error {
    background:#fff0f0;
    color:#a33;
}

.otp-success {
    background:#eefaf1;
    color:#28743d;
}

.otp-input {
    text-align:center;
    letter-spacing:8px;
    font-size:24px;
    font-weight:600;
}

.otp-meta {
    text-align:center;
    margin-top:16px;
    color:#777;
    font-size:14px;
}

.resend-form {
    margin-top:15px;
}

.resend-btn {
    border:0;
    background:none;
    color:#5D4037;
    font-weight:600;
    cursor:pointer;
}

.resend-btn:disabled {
    color:#999;
    cursor:not-allowed;
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
            Password Reset Verification
        </p>

    </div>


    <div class="login-title">

        <h1>
            Verify OTP
        </h1>

        <p>
            OTP has been sent to
            <br>
            <strong>
                <?= $email ?>
            </strong>
        </p>

    </div>


    <?php if ($error !== ''): ?>

        <div class="otp-message otp-error">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <?php if ($success !== ''): ?>

        <div class="otp-message otp-success">

            <?= htmlspecialchars(
                $success,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <form
        action="verify_reset_otp_process.php"
        method="POST"
        autocomplete="off"
    >

        <?= csrf_field() ?>


        <div class="form-group">

            <label
                for="otp"
            >
                Enter 6 Digit OTP
            </label>

            <div class="input-box">

                <input
                    id="otp"
                    class="otp-input"
                    type="text"
                    name="otp"
                    maxlength="6"
                    minlength="6"
                    pattern="[0-9]{6}"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    placeholder="000000"
                    required
                    autofocus
                >

            </div>

        </div>


        <button
            class="login-btn"
            type="submit"
        >
            Verify OTP
        </button>

    </form>


    <div class="otp-meta">

        OTP expires in

        <strong id="otpTimer">
            --:--
        </strong>

    </div>


    <?php if ($canResend): ?>

        <form
            class="resend-form"
            action="resend_reset_otp.php"
            method="POST"
        >

            <?= csrf_field() ?>

            <button
                id="resendButton"
                class="resend-btn"
                type="submit"
            >
                Resend OTP
            </button>

        </form>

    <?php else: ?>

        <div
            class="otp-meta"
            id="resendMessage"
        >
            Resend available in
            <strong id="resendTimer">
                60
            </strong>
            seconds.
        </div>

    <?php endif; ?>


    <div class="auth-switch">

        <a href="forgot_password.php">

            ← Change Email

        </a>

    </div>

</div>

</div>


<script>

let remainingSeconds =
    <?= $remainingSeconds ?>;

const otpTimer =
    document.getElementById(
        "otpTimer"
    );


function updateOtpTimer() {

    if (!otpTimer) {
        return;
    }

    if (
        remainingSeconds <= 0
    ) {

        otpTimer.textContent =
            "Expired";

        return;
    }


    const minutes =
        Math.floor(
            remainingSeconds / 60
        );

    const seconds =
        remainingSeconds % 60;


    otpTimer.textContent =
        minutes
        + ":"
        + String(
            seconds
        ).padStart(
            2,
            "0"
        );


    remainingSeconds--;

    setTimeout(
        updateOtpTimer,
        1000
    );
}


updateOtpTimer();


const otpInput =
    document.getElementById(
        "otp"
    );


if (otpInput) {

    otpInput.addEventListener(
        "input",
        function () {

            this.value =
                this.value.replace(
                    /[^0-9]/g,
                    ''
                );
        }
    );
}


let resendSeconds = 60;

const resendButton =
    document.getElementById(
        "resendButton"
    );

const resendTimer =
    document.getElementById(
        "resendTimer"
    );


if (
    resendButton &&
    resendTimer
) {

    resendButton.disabled =
        true;


    const resendInterval =
        setInterval(
            function () {

                resendSeconds--;


                resendTimer.textContent =
                    resendSeconds;


                if (
                    resendSeconds <= 0
                ) {

                    clearInterval(
                        resendInterval
                    );

                    resendButton.disabled =
                        false;

                    const parent =
                        document.getElementById(
                            "resendMessage"
                        );

                    if (parent) {

                        parent.innerHTML =
                            '<button '
                            + 'type="submit" '
                            + 'class="resend-btn">'
                            + 'Resend OTP'
                            + '</button>';
                    }
                }

            },
            1000
        );
}

</script>

</body>

</html>