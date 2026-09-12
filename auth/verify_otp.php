<?php

declare(strict_types=1);

require_once '../config/session.php';

/*
|--------------------------------------------------------------------------
| PENDING REGISTRATION CHECK
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
| REQUIRED REGISTRATION DATA
|--------------------------------------------------------------------------
*/

$requiredFields = [

    'email',
    'otp',
    'otp_expires',
    'otp_attempts'

];

foreach (
    $requiredFields as $field
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
    (int)(
        $data['otp_expires']
    );

$currentTime =
    time();

if (
    $otpExpires <=
    $currentTime
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
        (int)(
            $data['otp_attempts']
        )
    );

$maxOtpAttempts =
    5;

$remainingAttempts =
    max(
        0,
        $maxOtpAttempts -
        $otpAttempts
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
| EMAIL
|--------------------------------------------------------------------------
*/

$email =
    htmlspecialchars(
        (string)(
            $data['email']
        ),
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
    (string)(
        $_SESSION['otp_error']
        ?? ''
    );

$message =
    (string)(
        $_SESSION['otp_message']
        ?? ''
    );

unset(
    $_SESSION['otp_error'],
    $_SESSION['otp_message']
);

/*
|--------------------------------------------------------------------------
| CSRF
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
| REMAINING TIMER
|--------------------------------------------------------------------------
*/

$remainingSeconds =
    max(
        0,
        $otpExpires -
        $currentTime
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
        Verify Your Email | ExamSphere
    </title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="../assets/css/login.css"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
        }

        body {

            min-height: 100vh;

            margin: 0;

            padding: 20px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                linear-gradient(
                    135deg,
                    #f8f5ef 0%,
                    #f1ebdb 100%
                );

            font-family:
                'Poppins',
                Arial,
                Helvetica,
                sans-serif;

            color:
                #332a25;

        }

        .otp-card {

            width:
                min(
                    440px,
                    100%
                );

            padding:
                40px;

            background:
                #ffffff;

            border:
                1px solid
                #ebe4da;

            border-radius:
                24px;

            box-shadow:
                0 20px 60px
                rgba(
                    62,
                    39,
                    35,
                    .10
                );

            text-align:
                center;

        }

        .otp-logo {

            width:
                78px;

            height:
                78px;

            margin:
                0 auto 20px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                #f5f5dc;

            border-radius:
                20px;

        }

        .otp-logo img {

            width:
                58px;

            height:
                58px;

            object-fit:
                contain;

        }

        .otp-card h1 {

            margin:
                0 0 10px;

            color:
                #5D4037;

            font-size:
                28px;

            line-height:
                1.25;

            font-weight:
                800;

        }

        .otp-description {

            margin:
                0 0 20px;

            color:
                #71675f;

            font-size:
                14px;

            line-height:
                1.6;

        }

        .email-box {

            margin:
                0 0 20px;

            padding:
                12px 15px;

            background:
                #f8f6f2;

            border-radius:
                12px;

            color:
                #5D4037;

            font-size:
                14px;

            font-weight:
                700;

            word-break:
                break-word;

        }

        .alert {

            margin:
                0 0 18px;

            padding:
                13px 15px;

            border-radius:
                12px;

            font-size:
                13px;

            line-height:
                1.5;

        }

        .alert-error {

            background:
                #fff0f0;

            color:
                #a33d35;

        }

        .alert-success {

            background:
                #eef8ef;

            color:
                #2c7440;

        }

        .attempt-box {

            margin:
                0 0 15px;

            color:
                #81766f;

            font-size:
                13px;

        }

        .attempt-box strong {

            color:
                #5D4037;

        }

        .otp-input {

            width:
                100%;

            height:
                62px;

            padding:
                0 12px;

            border:
                2px solid
                #ded7ce;

            border-radius:
                14px;

            outline:
                none;

            background:
                #ffffff;

            color:
                #3a312c;

            text-align:
                center;

            font-family:
                'Poppins',
                Arial,
                Helvetica,
                sans-serif;

            font-size:
                28px;

            font-weight:
                600;

            letter-spacing:
                8px;

            transition:
                .2s ease;

        }

        .otp-input::placeholder {

            color:
                #aaa19a;

        }

        .otp-input:focus {

            border-color:
                #8a6248;

            box-shadow:
                0 0 0 4px
                rgba(
                    93,
                    64,
                    55,
                    .08
                );

        }

        /*
         * Important:
         *
         * We use readonly while submitting instead of disabled.
         * Disabled form elements are NOT included in POST data.
         * Readonly inputs ARE included in POST data.
         */

        .otp-input[readonly] {

            background:
                #f8f6f2;

            color:
                #5D4037;

            cursor:
                wait;

        }

        .verify-btn {

            width:
                100%;

            min-height:
                54px;

            margin-top:
                17px;

            border:
                0;

            border-radius:
                14px;

            background:
                #5D4037;

            color:
                #ffffff;

            font-family:
                'Poppins',
                Arial,
                Helvetica,
                sans-serif;

            font-size:
                15px;

            font-weight:
                700;

            cursor:
                pointer;

            transition:
                opacity .2s ease,
                transform .2s ease,
                background .2s ease;

        }

        .verify-btn:hover:not(:disabled) {

            opacity:
                .93;

            transform:
                translateY(-1px);

        }

        .verify-btn:disabled {

            opacity:
                .55;

            cursor:
                not-allowed;

            transform:
                none;

        }

        .timer {

            margin-top:
                17px;

            color:
                #81766f;

            font-size:
                13px;

        }

        .timer strong {

            color:
                #5D4037;

        }

        .timer.expired {

            color:
                #a33d35;

            font-weight:
                700;

        }

        .expired-message {

            display:
                none;

            margin-top:
                14px;

            padding:
                12px 14px;

            background:
                #fff0f0;

            color:
                #a33d35;

            border-radius:
                12px;

            font-size:
                13px;

            line-height:
                1.5;

        }

        .expired-message.show {

            display:
                block;

        }

        .back-link {

            display:
                inline-block;

            margin-top:
                21px;

            color:
                #5D4037;

            font-size:
                13px;

            font-weight:
                700;

            text-decoration:
                none;

        }

        .back-link:hover {

            text-decoration:
                underline;

        }

        @media (
            max-width: 520px
        ) {

            body {

                padding:
                    14px;

            }

            .otp-card {

                padding:
                    28px 20px;

                border-radius:
                    20px;

            }

            .otp-card h1 {

                font-size:
                    24px;

            }

            .otp-description {

                font-size:
                    13px;

            }

            .otp-input {

                height:
                    58px;

                font-size:
                    24px;

                letter-spacing:
                    7px;

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

    <p class="otp-description">
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
            role="status"
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
        id="otpForm"
        action="verify_otp_process.php"
        method="POST"
        autocomplete="off"
        novalidate
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= $csrfToken ?>"
        >

        <input
            id="otpInput"
            class="otp-input"
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
            id="verifyButton"
            class="verify-btn"
            type="submit"
            disabled
        >
            Verify Email
        </button>

    </form>

    <div
        id="otpTimerContainer"
        class="timer"
    >

        OTP expires in

        <strong id="otpTimer">
            --:--
        </strong>

    </div>

    <div
        id="expiredMessage"
        class="expired-message"
        role="alert"
    >

        Your OTP has expired.

        Please return to registration and request
        a new verification code.

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

    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    let remainingSeconds =
        <?= (int)$remainingSeconds ?>;

    let submitted =
        false;

    /*
    |--------------------------------------------------------------------------
    | ELEMENTS
    |--------------------------------------------------------------------------
    */

    const form =
        document.getElementById(
            'otpForm'
        );

    const otpInput =
        document.getElementById(
            'otpInput'
        );

    const verifyButton =
        document.getElementById(
            'verifyButton'
        );

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

    /*
    |--------------------------------------------------------------------------
    | FORMAT TIME
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
            String(minutes) +
            ':' +
            String(remainder)
                .padStart(
                    2,
                    '0'
                )
        );

    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE OTP
    |--------------------------------------------------------------------------
    */

    function normalizeOtp() {

        if (
            !otpInput
        ) {

            return '';

        }

        /*
         * Keep numeric characters only.
         * This also handles copied OTP values containing spaces
         * or hidden formatting characters.
         */

        otpInput.value =
            String(
                otpInput.value ||
                ''
            )
                .replace(
                    /[^0-9]/g,
                    ''
                )
                .slice(
                    0,
                    6
                );

        return otpInput.value;

    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE BUTTON STATE
    |--------------------------------------------------------------------------
    */

    function updateButtonState() {

        if (
            !verifyButton
        ) {

            return;

        }

        const value =
            normalizeOtp();

        verifyButton.disabled =
            submitted
            ||
            remainingSeconds <= 0
            ||
            value.length !== 6;

    }

    /*
    |--------------------------------------------------------------------------
    | EXPIRE OTP
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

            /*
             * readonly is enough here and keeps POST semantics safe.
             */

            otpInput.readOnly =
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

        updateButtonState();

        window.setTimeout(
            updateTimer,
            1000
        );

    }

    /*
    |--------------------------------------------------------------------------
    | INPUT
    |--------------------------------------------------------------------------
    */

    if (
        otpInput
    ) {

        otpInput.addEventListener(
            'input',
            function () {

                normalizeOtp();

                updateButtonState();

            }
        );

        otpInput.addEventListener(
            'paste',
            function () {

                window.setTimeout(
                    function () {

                        normalizeOtp();

                        updateButtonState();

                    },
                    0
                );

            }
        );

        otpInput.addEventListener(
            'keydown',
            function (event) {

                /*
                 * Allow:
                 * - Backspace
                 * - Delete
                 * - Tab
                 * - Arrow keys
                 * - Home / End
                 */

                const allowedKeys = [

                    'Backspace',
                    'Delete',
                    'Tab',
                    'ArrowLeft',
                    'ArrowRight',
                    'Home',
                    'End'

                ];

                if (
                    allowedKeys.includes(
                        event.key
                    )
                ) {

                    return;

                }

                if (
                    !/^[0-9]$/
                        .test(
                            event.key
                        )
                ) {

                    event.preventDefault();

                }

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
            function (event) {

                /*
                 * Stop duplicate submissions.
                 */

                if (
                    submitted
                ) {

                    event.preventDefault();

                    return;

                }

                /*
                 * Stop submission after expiry.
                 */

                if (
                    remainingSeconds <=
                    0
                ) {

                    event.preventDefault();

                    expireOtp();

                    return;

                }

                /*
                 * Normalize one last time before submit.
                 */

                const value =
                    normalizeOtp();

                /*
                 * Require exactly six digits.
                 */

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

                    updateButtonState();

                    return;

                }

                /*
                 * Mark submission state.
                 */

                submitted =
                    true;

                /*
                 * IMPORTANT FIX
                 *
                 * Do NOT use:
                 *
                 * otpInput.disabled = true;
                 *
                 * Disabled inputs are not submitted with
                 * the HTML form.
                 *
                 * readonly inputs ARE submitted.
                 */

                if (
                    otpInput
                ) {

                    otpInput.readOnly =
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
    | INITIALIZE
    |--------------------------------------------------------------------------
    */

    normalizeOtp();

    updateButtonState();

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