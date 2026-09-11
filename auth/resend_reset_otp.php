<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/mail.php';


function resend_otp_error(
    string $message
): never {

    $_SESSION['reset_otp_error'] =
        $message;

    header(
        'Location: verify_reset_otp.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| REQUEST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {

    header(
        'Location: forgot_password.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    !verify_csrf_token(
        $_POST['csrf_token'] ?? null
    )
) {

    resend_otp_error(
        'Your session expired. Please try again.'
    );
}


/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| ONE-MINUTE COOLDOWN
|--------------------------------------------------------------------------
*/

$lastSentAt =
    (int) (
        $data['otp_sent_at']
        ?? 0
    );


if (
    $lastSentAt > 0
    &&
    (
        time()
        -
        $lastSentAt
    ) < 60
) {

    $remaining =
        60
        -
        (
            time()
            -
            $lastSentAt
        );

    resend_otp_error(
        "Please wait {$remaining} seconds before requesting another OTP."
    );
}


/*
|--------------------------------------------------------------------------
| NEW OTP
|--------------------------------------------------------------------------
*/

$otp =
    (string) random_int(
        100000,
        999999
    );


$otpHash =
    password_hash(
        $otp,
        PASSWORD_DEFAULT
    );


if (
    $otpHash === false
) {

    resend_otp_error(
        'Unable to generate a new OTP.'
    );
}


/*
|--------------------------------------------------------------------------
| SEND MAIL
|--------------------------------------------------------------------------
*/

try {

    $mail =
        getMailer();

    $mail->addAddress(
        (string) $data['email'],
        (string) $data['name']
    );

    $mail->Subject =
        'ExamSphere New Password Reset OTP';


    $safeName =
        htmlspecialchars(
            (string) $data['name'],
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );


    $safeOtp =
        htmlspecialchars(
            $otp,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );


    $mail->Body = '

<div style="
    font-family:Arial,Helvetica,sans-serif;
    background:#f7f4ef;
    padding:40px 20px;
">

    <div style="
        max-width:600px;
        margin:0 auto;
        background:#ffffff;
        border-radius:20px;
        overflow:hidden;
    ">

        <div style="
            background:#5D4037;
            color:#ffffff;
            padding:28px;
            text-align:center;
        ">

            <h1 style="
                margin:0;
            ">
                ExamSphere
            </h1>

            <p>
                New Password Reset OTP
            </p>

        </div>


        <div style="
            padding:35px;
        ">

            <p>
                Hello <strong>'
                . $safeName .
                '</strong>,
            </p>


            <p>
                Your new password reset OTP is:
            </p>


            <div style="
                margin:25px 0;
                padding:25px;
                background:#f5f5dc;
                border-radius:15px;
                text-align:center;
            ">

                <div style="
                    font-size:38px;
                    font-weight:bold;
                    letter-spacing:9px;
                    color:#5D4037;
                ">
                    '
                    . $safeOtp .
                    '
                </div>

            </div>


            <p>
                This OTP is valid for
                <strong>5 minutes</strong>.
            </p>


            <p>
                Do not share this OTP with anyone.
            </p>

        </div>


        <div style="
            background:#fafafa;
            padding:20px;
            text-align:center;
            color:#888;
            font-size:12px;
        ">
            © ExamSphere. All rights reserved.
        </div>

    </div>

</div>
';


    $mail->AltBody =
        "Hello {$data['name']},\n\n"
        . "Your new ExamSphere password reset OTP is: "
        . $otp
        . "\n\n"
        . "This OTP is valid for 5 minutes.";


    $mail->send();


    /*
    |--------------------------------------------------------------------------
    | UPDATE SESSION
    |--------------------------------------------------------------------------
    */

    $_SESSION['forgot_password']['otp'] =
        $otpHash;

    $_SESSION['forgot_password']['expiry'] =
        time() + 300;

    $_SESSION['forgot_password']['otp_attempts'] =
        0;

    $_SESSION['forgot_password']['otp_sent_at'] =
        time();

    $_SESSION['forgot_password']['verified'] =
        false;


    unset(
        $_SESSION['password_reset_verified']
    );


    $_SESSION['reset_otp_success'] =
        'A new OTP has been sent to your email address.';


    header(
        'Location: verify_reset_otp.php'
    );

    exit;


} catch (Throwable $exception) {

    error_log(
        'ExamSphere resend reset OTP failed: '
        . $exception->getMessage()
    );

    resend_otp_error(
        'Unable to send a new OTP right now. Please try again.'
    );
}