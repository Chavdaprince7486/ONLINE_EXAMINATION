<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/mail.php';


/*
|--------------------------------------------------------------------------
| REDIRECT WITH ERROR
|--------------------------------------------------------------------------
*/

function forgot_password_error(
    string $message,
    string $email = ''
): never {

    $_SESSION['forgot_error'] =
        $message;

    $_SESSION['forgot_old_email'] =
        $email;

    header(
        'Location: forgot_password.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| GENERIC SUCCESS
|--------------------------------------------------------------------------
*/

function forgot_password_generic_success(
    string $email
): never {

    /*
    |--------------------------------------------------------------------------
    | Never reveal whether the account exists.
    |--------------------------------------------------------------------------
    */

    $_SESSION['forgot_success'] =
        'If an account exists for this email, a password reset OTP has been sent.';

    $_SESSION['forgot_old_email'] =
        $email;

    header(
        'Location: forgot_password.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
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

    forgot_password_error(
        'Your session expired. Please try again.'
    );
}


/*
|--------------------------------------------------------------------------
| EMAIL
|--------------------------------------------------------------------------
*/

$email =
    strtolower(
        trim(
            (string) (
                $_POST['email']
                ?? ''
            )
        )
    );


/*
|--------------------------------------------------------------------------
| REQUIRED EMAIL
|--------------------------------------------------------------------------
*/

if (
    $email === ''
) {

    forgot_password_error(
        'Please enter your email address.'
    );
}


/*
|--------------------------------------------------------------------------
| EMAIL FORMAT
|--------------------------------------------------------------------------
*/

if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    forgot_password_error(
        'Please enter a valid email address.',
        $email
    );
}


/*
|--------------------------------------------------------------------------
| EMAIL LENGTH
|--------------------------------------------------------------------------
*/

if (
    strlen($email) > 150
) {

    forgot_password_error(
        'Please enter a valid email address.',
        $email
    );
}


/*
|--------------------------------------------------------------------------
| REQUEST RATE LIMIT
|--------------------------------------------------------------------------
|
| A reset request can only be started once every 60 seconds from the same
| browser session.
|
| This is not the only rate-limit layer needed for a public production
| deployment, but it prevents accidental / repeated requests immediately.
|
|--------------------------------------------------------------------------
*/

$lastResetRequestAt =
    (int) (
        $_SESSION[
            'forgot_password_request_at'
        ]
        ?? 0
    );


if (
    $lastResetRequestAt > 0
) {

    $elapsed =
        time()
        -
        $lastResetRequestAt;


    if (
        $elapsed < 60
    ) {

        $remaining =
            60 -
            max(
                0,
                $elapsed
            );


        forgot_password_error(
            "Please wait {$remaining} seconds before requesting another password reset OTP.",
            $email
        );
    }
}


/*
|--------------------------------------------------------------------------
| DATABASE LOOKUP
|--------------------------------------------------------------------------
*/

try {

    $stmt =
        $conn->prepare(
            "
            SELECT

                id,
                full_name,
                email,
                status

            FROM students

            WHERE email = ?

            LIMIT 1
            "
        );


    $stmt->execute([
        $email
    ]);


    $student =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | UNKNOWN EMAIL
    |--------------------------------------------------------------------------
    |
    | Preserve anti-enumeration behavior.
    |--------------------------------------------------------------------------
    */

    if (
        !$student
    ) {

        $_SESSION[
            'forgot_password_request_at'
        ] =
            time();


        forgot_password_generic_success(
            $email
        );
    }


    /*
    |--------------------------------------------------------------------------
    | INACTIVE ACCOUNT
    |--------------------------------------------------------------------------
    */

    if (
        ($student['status'] ?? '')
        !==
        'Active'
    ) {

        $_SESSION[
            'forgot_password_request_at'
        ] =
            time();


        forgot_password_generic_success(
            $email
        );
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE OTP
    |--------------------------------------------------------------------------
    */

    $otp =
        (string)
        random_int(
            100000,
            999999
        );


    /*
    |--------------------------------------------------------------------------
    | HASH OTP
    |--------------------------------------------------------------------------
    */

    $otpHash =
        password_hash(
            $otp,
            PASSWORD_DEFAULT
        );


    if (
        $otpHash === false
    ) {

        throw new RuntimeException(
            'Unable to create the password reset verification code.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | OTP TIMES
    |--------------------------------------------------------------------------
    */

    $issuedAt =
        time();


    $expiresAt =
        $issuedAt + 300;


    /*
    |--------------------------------------------------------------------------
    | SAFE EMAIL VALUES
    |--------------------------------------------------------------------------
    */

    $safeName =
        htmlspecialchars(
            (string)
            $student['full_name'],
            ENT_QUOTES |
            ENT_SUBSTITUTE,
            'UTF-8'
        );


    $safeOtp =
        htmlspecialchars(
            $otp,
            ENT_QUOTES |
            ENT_SUBSTITUTE,
            'UTF-8'
        );


    /*
    |--------------------------------------------------------------------------
    | CREATE MAILER
    |--------------------------------------------------------------------------
    */

    $mail =
        getMailer();


    /*
    |--------------------------------------------------------------------------
    | RECIPIENT
    |--------------------------------------------------------------------------
    */

    $mail->addAddress(

        (string)
        $student['email'],

        (string)
        $student['full_name']

    );


    /*
    |--------------------------------------------------------------------------
    | SUBJECT
    |--------------------------------------------------------------------------
    */

    $mail->Subject =
        'ExamSphere Password Reset OTP';


    /*
    |--------------------------------------------------------------------------
    | HTML BODY
    |--------------------------------------------------------------------------
    */

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
        box-shadow:0 15px 45px rgba(0,0,0,.10);
    ">


        <div style="
            background:#5D4037;
            color:#ffffff;
            text-align:center;
            padding:28px;
        ">

            <h1 style="
                margin:0;
                font-size:28px;
            ">
                ExamSphere
            </h1>


            <p style="
                margin:8px 0 0;
                opacity:.9;
            ">
                Password Recovery
            </p>

        </div>


        <div style="
            padding:35px;
        ">


            <p style="
                font-size:16px;
                color:#333333;
            ">

                Hello
                <strong>
                    '
                    .
                    $safeName
                    .
                    '
                </strong>,

            </p>


            <p style="
                color:#555555;
                line-height:1.7;
            ">

                We received a request to reset your
                ExamSphere account password.

            </p>


            <div style="
                background:#f5f5dc;
                border-radius:16px;
                padding:24px;
                text-align:center;
                margin:28px 0;
            ">


                <div style="
                    font-size:13px;
                    color:#777777;
                    margin-bottom:10px;
                ">

                    Password reset OTP

                </div>


                <div style="
                    font-size:38px;
                    font-weight:bold;
                    letter-spacing:9px;
                    color:#5D4037;
                ">

                    '
                    .
                    $safeOtp
                    .
                    '

                </div>


            </div>


            <p style="
                color:#555555;
                line-height:1.7;
                font-size:14px;
            ">

                This OTP is valid for
                <strong>5 minutes</strong>.

            </p>


            <p style="
                color:#777777;
                line-height:1.7;
                font-size:14px;
            ">

                Never share this OTP with anyone.

            </p>


            <p style="
                color:#777777;
                line-height:1.7;
                font-size:14px;
            ">

                If you did not request a password reset,
                you can safely ignore this email.

            </p>


        </div>


        <div style="
            background:#fafafa;
            border-top:1px solid #eeeeee;
            padding:20px;
            text-align:center;
            color:#888888;
            font-size:12px;
        ">

            © ExamSphere. All rights reserved.

        </div>


    </div>

</div>
';


    /*
    |--------------------------------------------------------------------------
    | PLAIN TEXT
    |--------------------------------------------------------------------------
    */

    $mail->AltBody =
        'Hello '
        .
        (string)
        $student['full_name']
        .
        ','
        .
        PHP_EOL
        .
        PHP_EOL
        .
        'Your ExamSphere password reset OTP is: '
        .
        $otp
        .
        PHP_EOL
        .
        PHP_EOL
        .
        'This OTP is valid for 5 minutes.'
        .
        PHP_EOL
        .
        'Do not share this OTP with anyone.'
        .
        PHP_EOL;


    /*
    |--------------------------------------------------------------------------
    | SEND EMAIL BEFORE SESSION COMMIT
    |--------------------------------------------------------------------------
    */

    $mail->send();


    /*
    |--------------------------------------------------------------------------
    | ONLY AFTER SUCCESSFUL EMAIL DELIVERY:
    | CREATE RESET SESSION
    |--------------------------------------------------------------------------
    */

    $_SESSION[
        'forgot_password'
    ] = [

        'id' =>
            (int)
            $student['id'],

        'name' =>
            (string)
            $student['full_name'],

        'email' =>
            (string)
            $student['email'],

        'otp' =>
            $otpHash,

        'expiry' =>
            $expiresAt,

        'otp_attempts' =>
            0,

        'otp_sent_at' =>
            $issuedAt,

        'verified' =>
            false

    ];


    /*
    |--------------------------------------------------------------------------
    | REQUEST COOLDOWN
    |--------------------------------------------------------------------------
    */

    $_SESSION[
        'forgot_password_request_at'
    ] =
        $issuedAt;


    /*
    |--------------------------------------------------------------------------
    | CLEAR ANY OLD RESET AUTHORIZATION
    |--------------------------------------------------------------------------
    */

    unset(
        $_SESSION[
            'password_reset_verified'
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | CLEAR OLD MESSAGES
    |--------------------------------------------------------------------------
    */

    unset(
        $_SESSION[
            'forgot_error'
        ],

        $_SESSION[
            'forgot_success'
        ],

        $_SESSION[
            'reset_otp_error'
        ],

        $_SESSION[
            'reset_otp_success'
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    header(
        'Location: verify_reset_otp.php'
    );

    exit;


} catch (
    Throwable $exception
) {

    /*
    |--------------------------------------------------------------------------
    | CLEAN RESET STATE ON FAILURE
    |--------------------------------------------------------------------------
    */

    unset(
        $_SESSION[
            'forgot_password'
        ],

        $_SESSION[
            'password_reset_verified'
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | LOG
    |--------------------------------------------------------------------------
    */

    error_log(
        'ExamSphere forgot password error: '
        .
        $exception->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | USER RESPONSE
    |--------------------------------------------------------------------------
    */

    forgot_password_error(
        'Unable to send the password reset OTP right now. Please try again later.',
        $email
    );
}