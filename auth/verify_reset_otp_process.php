<?php

declare(strict_types=1);

require_once '../config/session.php';


/*
|--------------------------------------------------------------------------
| ERROR REDIRECT
|--------------------------------------------------------------------------
*/

function reset_otp_error(
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
| REGISTRATION / RESET ERROR
|--------------------------------------------------------------------------
*/

function reset_otp_restart(
    string $message
): never {

    unset(
        $_SESSION['forgot_password'],
        $_SESSION['password_reset_verified'],
        $_SESSION['reset_otp_error'],
        $_SESSION['reset_otp_success']
    );

    $_SESSION['forgot_error'] =
        $message;

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
    $_SERVER['REQUEST_METHOD'] !== 'POST'
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

    reset_otp_error(
        'Your session expired. Please try again.'
    );
}


/*
|--------------------------------------------------------------------------
| RESET SESSION
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['forgot_password']
    )
    ||
    !is_array(
        $_SESSION['forgot_password']
    )
) {

    reset_otp_restart(
        'Your password reset session has expired. Please request a new OTP.'
    );
}


$data =
    $_SESSION['forgot_password'];


/*
|--------------------------------------------------------------------------
| REQUIRED RESET DATA
|--------------------------------------------------------------------------
*/

$requiredFields = [

    'id',
    'email',
    'otp',
    'expiry',
    'otp_attempts',
    'otp_sent_at',
    'verified'

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

        reset_otp_restart(
            'Your password reset session is incomplete. Please request a new OTP.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| RESET ACCOUNT ID
|--------------------------------------------------------------------------
*/

$studentId =
    (int)
    $data['id'];


if (
    $studentId <= 0
) {

    reset_otp_restart(
        'Your password reset session is invalid. Please request a new OTP.'
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
            (string)
            $data['email']
        )
    );


if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    reset_otp_restart(
        'Your password reset session is invalid. Please request a new OTP.'
    );
}


/*
|--------------------------------------------------------------------------
| ALREADY VERIFIED
|--------------------------------------------------------------------------
*/

if (
    !empty(
        $data['verified']
    )
) {

    header(
        'Location: reset_password.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| OTP HASH
|--------------------------------------------------------------------------
*/

$otpHash =
    (string)
    $data['otp'];


if (
    $otpHash === ''
    ||
    password_get_info(
        $otpHash
    )['algo'] === 0
) {

    reset_otp_restart(
        'Your password reset verification is no longer valid. Please request a new OTP.'
    );
}


/*
|--------------------------------------------------------------------------
| OTP EXPIRY
|--------------------------------------------------------------------------
*/

$expiry =
    (int)
    $data['expiry'];


if (
    $expiry <= 0
) {

    reset_otp_restart(
        'Your password reset verification is no longer valid. Please request a new OTP.'
    );
}


/*
|--------------------------------------------------------------------------
| EXACT OTP EXPIRY CHECK
|--------------------------------------------------------------------------
|
| At the expiry timestamp itself the OTP is invalid.
|
*/

if (
    time() >= $expiry
) {

    reset_otp_restart(
        'Your OTP has expired. Please request a new OTP.'
    );
}


/*
|--------------------------------------------------------------------------
| OTP ATTEMPTS
|--------------------------------------------------------------------------
*/

$attempts =
    max(
        0,
        (int)
        $data['otp_attempts']
    );


if (
    $attempts >= 5
) {

    reset_otp_restart(
        'Too many incorrect OTP attempts. Please request a new OTP.'
    );
}


/*
|--------------------------------------------------------------------------
| OTP INPUT
|--------------------------------------------------------------------------
*/

$enteredOtp =
    trim(
        (string) (
            $_POST['otp']
            ?? ''
        )
    );


if (
    !preg_match(
        '/^[0-9]{6}$/',
        $enteredOtp
    )
) {

    reset_otp_error(
        'Please enter a valid 6-digit OTP.'
    );
}


/*
|--------------------------------------------------------------------------
| RECHECK EXPIRY BEFORE VERIFY
|--------------------------------------------------------------------------
|
| This protects against the request arriving exactly around the expiry
| boundary.
|
*/

if (
    time() >= $expiry
) {

    reset_otp_restart(
        'Your OTP has expired. Please request a new OTP.'
    );
}


/*
|--------------------------------------------------------------------------
| VERIFY OTP
|--------------------------------------------------------------------------
*/

if (
    !password_verify(
        $enteredOtp,
        $otpHash
    )
) {

    $attempts++;


    /*
    |--------------------------------------------------------------------------
    | STORE FAILURE COUNT
    |--------------------------------------------------------------------------
    */

    $_SESSION[
        'forgot_password'
    ]['otp_attempts'] =
        $attempts;


    /*
    |--------------------------------------------------------------------------
    | FINAL ATTEMPT
    |--------------------------------------------------------------------------
    */

    if (
        $attempts >= 5
    ) {

        unset(
            $_SESSION[
                'forgot_password'
            ]
        );


        unset(
            $_SESSION[
                'password_reset_verified'
            ]
        );


        $_SESSION[
            'forgot_error'
        ] =
            'Too many incorrect OTP attempts. Please request a new OTP.';


        header(
            'Location: forgot_password.php'
        );

        exit;
    }


    reset_otp_error(
        'Invalid OTP. Please check the code and try again.'
    );
}


/*
|--------------------------------------------------------------------------
| SUCCESSFUL VERIFICATION
|--------------------------------------------------------------------------
*/

$_SESSION[
    'forgot_password'
]['verified'] =
    true;


/*
|--------------------------------------------------------------------------
| OTP MUST NEVER BE REUSED
|--------------------------------------------------------------------------
|
| Remove both the OTP hash and the original OTP timing information.
|
*/

unset(

    $_SESSION[
        'forgot_password'
    ]['otp'],

    $_SESSION[
        'forgot_password'
    ]['expiry'],

    $_SESSION[
        'forgot_password'
    ]['otp_attempts'],

    $_SESSION[
        'forgot_password'
    ]['otp_sent_at']

);


/*
|--------------------------------------------------------------------------
| AUTHORIZATION FOR PASSWORD RESET
|--------------------------------------------------------------------------
*/

$_SESSION[
    'password_reset_verified'
] =
    true;


/*
|--------------------------------------------------------------------------
| CLEAR OTP MESSAGES
|--------------------------------------------------------------------------
*/

unset(

    $_SESSION[
        'reset_otp_error'
    ],

    $_SESSION[
        'reset_otp_success'
    ],

    $_SESSION[
        'forgot_error'
    ]

);


/*
|--------------------------------------------------------------------------
| PASSWORD RESET STAGE
|--------------------------------------------------------------------------
*/

$_SESSION[
    'reset_password_stage'
] =
    'verified';


/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/

header(
    'Location: reset_password.php'
);

exit;