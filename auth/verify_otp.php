<?php

declare(strict_types=1);

require_once '../config/session.php';


/*
|--------------------------------------------------------------------------
| PENDING REGISTRATION
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['pending_registration']
    )
    ||
    !is_array(
        $_SESSION['pending_registration']
    )
) {

    $_SESSION['register_error'] =
        'Your verification session has expired. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
}


$data =
    $_SESSION[
        'pending_registration'
    ];


/*
|--------------------------------------------------------------------------
| REQUIRED SESSION FIELDS
|--------------------------------------------------------------------------
*/

$requiredFields = [

    'email',
    'otp',
    'otp_expires',
    'otp_attempts'

];


foreach (
    $requiredFields
    as $field
) {

    if (
        !array_key_exists(
            $field,
            $data
        )
    ) {

        unset(
            $_SESSION[
                'pending_registration'
            ]
        );

        $_SESSION['register_error'] =
            'Your verification session is incomplete. Please register again.';

        header(
            'Location: register.php'
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| OTP EXPIRY
|--------------------------------------------------------------------------
*/

$otpExpires =
    (int)
    $data[
        'otp_expires'
    ];


$now =
    time();


if (
    $otpExpires <=
    $now
) {

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );

    $_SESSION['register_error'] =
        'Your OTP has expired. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| OTP ATTEMPTS
|--------------------------------------------------------------------------
*/

$otpAttempts =
    max(
        0,
        (int)
        $data[
            'otp_attempts'
        ]
    );


$remainingAttempts =
    max(
        0,
        5 - $otpAttempts
    );


if (
    $remainingAttempts <= 0
) {

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );

    $_SESSION['register_error'] =
        'Too many incorrect OTP attempts. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| EMAIL DISPLAY
|--------------------------------------------------------------------------
*/

$email =
    htmlspecialchars(
        (string)
        $data['email'],
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );


/*
|--------------------------------------------------------------------------
| FLASH MESSAGES
|--------------------------------------------------------------------------
*/

$error =
    (string) (
        $_SESSION[
            'otp_error'
        ]
        ?? ''
    );


$message =
    (string) (
        $_SESSION[
            'otp_message'
        ]
        ?? ''
    );


unset(

    $_SESSION[
        'otp_error'
    ],

    $_SESSION[
        'otp_message'
    ]

);


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

$csrfToken =
    htmlspecialchars(
        csrf_token(),
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );


/*
|--------------------------------------------------------------------------
| TIMER
|--------------------------------------------------------------------------
*/

$remainingSeconds =
    max(
        0,
        $otpExpires -
        $now
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


    <meta
        name="color-scheme"
        content="light"
    >


    <title>
        Verify OTP | ExamSphere
    </title>


    <link
        rel="stylesheet"
        href="../assets/css/login.css"
    >


    <style>

        body {

            min-height: 100vh;

            margin: 0;

            padding: 20px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #f7f4ef;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

        }


        .otp-card {

            width: 100%;

            max-width: 440px;

            padding: 40px;

            box-sizing: border-box;

            background: #ffffff;

            border-radius: 24px;

            box-shadow:
                0 20px 60px
                rgba(
                    0,
                    0,
                    0,
                    0.10
                );

            text-align: center;

        }


        .otp-logo {

            width: 75px;

            height: 75px;

            margin:
                0 auto 20px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #f5f5dc;

            border-radius: 20px;

        }


        .otp-logo img {

            max-width: 55px;

            max-height: 55px;

            object-fit: contain;

        }


        .otp-card h1 {

            margin:
                0 0 10px;

            color: #5D4037;

            font-size: 28px;

        }


        .otp-card p {

            margin:
                0 0 20px;

            color: #666666;

            line-height: 1.6;

        }


        .email-box {

            margin-bottom: 25px;

            padding:
                12px 15px;

            background: #f8f6f2;

            border-radius: 12px;

            color: #5D4037;

            font-weight: 600;

            word-break: break-word;

        }


        .alert {

            margin-bottom: 20px;

            padding:
                13px 15px;

            border-radius: 12px;

            font-size: 14px;

            line-height: 1.5;

        }


        .alert-error {

            background: #fff0f0;

            color: #a33;

        }


        .alert-success {

            background: #eefaf1;

            color: #28743d;

        }


        .attempt-box {

            margin:
                -5px 0 18px;

            color: #777777;

            font-size: 13px;

        }


        .attempt-box strong {

            color: #5D4037;

        }


        .otp-input {

            width: 100%;

            height: 58px;

            padding:
                0 15px;

            box-sizing: border-box;

            border:
                2px solid #ded8d0;

            border-radius: 14px;

            outline: none;

            text-align: center;

            font-size: 28px;

            letter-spacing: 9px;

            color: #333333;

            background: #ffffff;

            transition:
                border-color .2s ease,
                box-shadow .2s ease;

        }


        .otp-input:focus {

            border-color: #8B5A2B;

            box-shadow:
                0 0 0 4px
                rgba(
                    139,
                    90,
                    43,
                    .08
                );

        }


        .otp-input:disabled {

            background: #f4f1ec;

            color: #888888;

            cursor: not-allowed;

        }


        .verify-btn {

            width: 100%;

            min-height: 54px;

            margin-top: 18px;

            border: 0;

            border-radius: 14px;

            background: #5D4037;

            color: #ffffff;

            font-size: 16px;

            font-weight: 600;

            cursor: pointer;

            transition:
                opacity .2s ease,
                transform .2s ease;

        }


        .verify-btn:hover:not(:disabled) {

            opacity: 0.92;

            transform:
                translateY(-1px);

        }


        .verify-btn:disabled {

            opacity: .55;

            cursor: not-allowed;

            transform: none;

        }


        .timer {

            margin-top: 18px;

            color: #777777;

            font-size: 14px;

        }


        .timer strong {

            color: #5D4037;

        }


        .timer.expired {

            color: #a33;

            font-weight: 600;

        }


        .back-link {

            display: inline-block;

            margin-top: 22px;

            color: #5D4037;

            text-decoration: none;

            font-weight: 600;

        }


        .back-link:hover {

            text-decoration: underline;

        }


        .expired-message {

            display: none;

            margin-top: 16px;

            padding:
                12px 14px;

            background: #fff0f0;

            color: #a33;

            border-radius: 12px;

            font-size: 14px;

            line-height: 1.5;

        }


        .expired-message.show {

            display: block;

        }


        @media (
            max-width: 520px
        ) {

            body {

                padding: 14px;

            }


            .otp-card {

                padding: 28px 20px;

                border-radius: 20px;

            }


            .otp-card h1 {

                font-size: 24px;

            }


            .otp-input {

                font-size: 24px;

                letter-spacing: 7px;

            }

        }

    </style>

</head>


<body>


<div class="otp-card">


    <div class="otp-logo">

        <img
            src="../assets/images/exam_logo.png"
            alt="ExamSphere"
        >

    </div>


    <h1>
        Verify Your Email
    </h1>


    <p>
        Enter the 6-digit OTP sent to your email address.
    </p>


    <div class="email-box">

        <?= $email ?>

    </div>


    <?php if (
        $error !== ''
    ): ?>

        <div
            class="alert alert-error"
            role="alert"
        >

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES |
                ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <?php if (
        $message !== ''
    ): ?>

        <div
            class="alert alert-success"
            role="alert"
        >

            <?= htmlspecialchars(
                $message,
                ENT_QUOTES |
                ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <div class="attempt-box">

        Attempts remaining:

        <strong>
            <?= $remainingAttempts ?>
        </strong>

    </div>


    <form
        action="verify_otp_process.php"
        method="POST"
        autocomplete="off"
        id="otpForm"
    >


        <input
            type="hidden"
            name="csrf_token"
            value="<?= $csrfToken ?>"
        >


        <input
            class="otp-input"
            id="otpInput"
            type="text"
            name="otp"
            inputmode="numeric"
            pattern="[0-9]{6}"
            maxlength="6"
            minlength="6"
            autocomplete="one-time-code"
            placeholder="000000"
            aria-label="6-digit verification OTP"
            required
            autofocus
        >


        <button
            class="verify-btn"
            id="verifyButton"
            type="submit"
        >

            Verify Email

        </button>


    </form>


    <div
        class="timer"
        id="otpTimerContainer"
    >

        OTP expires in

        <strong id="otpTimer">
            --:--
        </strong>

    </div>


    <div
        class="expired-message"
        id="expiredMessage"
        role="alert"
    >

        Your OTP has expired.

        Please return to registration and request a
        new verification code.

    </div>


    <a
        class="back-link"
        href="register.php"
    >

        ← Back to Registration

    </a>


</div>


<script>

(function () {

    'use strict';


    let remainingSeconds =
        <?= $remainingSeconds ?>;


    const timerElement =
        document.getElementById(
            'otpTimer'
        );


    const timerContainer =
        document.getElementById(
            'otpTimerContainer'
        );


    const expiredMessage =
        document.getElementById(
            'expiredMessage'
        );


    const otpInput =
        document.getElementById(
            'otpInput'
        );


    const verifyButton =
        document.getElementById(
            'verifyButton'
        );


    const form =
        document.getElementById(
            'otpForm'
        );


    let submitted =
        false;


    /*
    |--------------------------------------------------------------------------
    | FORMAT TIMER
    |--------------------------------------------------------------------------
    */

    function formatTime(
        seconds
    ) {

        const minutes =
            Math.floor(
                seconds / 60
            );


        const remainder =
            seconds % 60;


        return (
            String(minutes)
            +
            ':'
            +
            String(
                remainder
            ).padStart(
                2,
                '0'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EXPIRE UI
    |--------------------------------------------------------------------------
    */

    function expireOtp() {

        remainingSeconds =
            0;


        if (
            timerElement
        ) {

            timerElement.textContent =
                'Expired';

        }


        if (
            timerContainer
        ) {

            timerContainer.classList.add(
                'expired'
            );

        }


        if (
            otpInput
        ) {

            otpInput.disabled =
                true;

        }


        if (
            verifyButton
        ) {

            verifyButton.disabled =
                true;

            verifyButton.textContent =
                'OTP Expired';

        }


        if (
            expiredMessage
        ) {

            expiredMessage.classList.add(
                'show'
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | TIMER
    |--------------------------------------------------------------------------
    */

    function updateTimer() {

        if (
            remainingSeconds <=
            0
        ) {

            expireOtp();

            return;

        }


        if (
            timerElement
        ) {

            timerElement.textContent =
                formatTime(
                    remainingSeconds
                );

        }


        remainingSeconds--;

        window.setTimeout(
            updateTimer,
            1000
        );

    }


    /*
    |--------------------------------------------------------------------------
    | NUMERIC INPUT
    |--------------------------------------------------------------------------
    */

    if (
        otpInput
    ) {

        otpInput.addEventListener(
            'input',
            function () {

                this.value =
                    this.value
                        .replace(
                            /[^0-9]/g,
                            ''
                        )
                        .slice(
                            0,
                            6
                        );


                if (
                    verifyButton
                ) {

                    verifyButton.disabled =
                        this.value.length !== 6
                        ||
                        remainingSeconds <= 0;

                }

            }
        );


        otpInput.addEventListener(
            'paste',
            function () {

                window.setTimeout(
                    function () {

                        otpInput.dispatchEvent(
                            new Event(
                                'input'
                            )
                        );

                    },
                    0
                );

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | FORM SUBMIT
    |--------------------------------------------------------------------------
    */

    if (
        form
    ) {

        form.addEventListener(
            'submit',
            function (
                event
            ) {

                if (
                    submitted
                ) {

                    event.preventDefault();

                    return;

                }


                if (
                    remainingSeconds <=
                    0
                ) {

                    event.preventDefault();

                    expireOtp();

                    return;

                }


                const value =
                    otpInput
                        ? otpInput.value
                        : '';


                if (
                    !/^[0-9]{6}$/
                        .test(
                            value
                        )
                ) {

                    event.preventDefault();

                    if (
                        otpInput
                    ) {

                        otpInput.focus();

                    }

                    return;

                }


                submitted =
                    true;


                if (
                    otpInput
                ) {

                    otpInput.disabled =
                        true;

                }


                if (
                    verifyButton
                ) {

                    verifyButton.disabled =
                        true;


                    verifyButton.textContent =
                        'Verifying...';

                }

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | START
    |--------------------------------------------------------------------------
    */

    if (
        remainingSeconds > 0
    ) {

        updateTimer();

    } else {

        expireOtp();

    }

})();

</script>


</body>

</html>