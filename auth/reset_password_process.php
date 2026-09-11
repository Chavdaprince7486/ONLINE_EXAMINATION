<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';


/*
|--------------------------------------------------------------------------
| ERROR REDIRECT
|--------------------------------------------------------------------------
*/

function reset_password_error(
    string $message
): never {

    $_SESSION['reset_password_error'] =
        $message;

    header(
        'Location: reset_password.php'
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

    reset_password_error(
        'Your session expired. Please try again.'
    );
}


/*
|--------------------------------------------------------------------------
| RESET AUTHORIZATION
|--------------------------------------------------------------------------
|
| OTP verification is the authorization boundary.
|
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
    ||
    empty(
        $_SESSION['password_reset_verified']
    )
    ||
    empty(
        $_SESSION[
            'forgot_password'
        ]['verified']
    )
) {

    /*
    |--------------------------------------------------------------------------
    | Reset authorization is missing.
    |--------------------------------------------------------------------------
    */

    unset(
        $_SESSION['reset_password_error']
    );

    header(
        'Location: forgot_password.php'
    );

    exit;
}


$data =
    $_SESSION[
        'forgot_password'
    ];


/*
|--------------------------------------------------------------------------
| REQUIRED RESET DATA
|--------------------------------------------------------------------------
*/

if (
    !array_key_exists(
        'id',
        $data
    )
    ||
    !array_key_exists(
        'email',
        $data
    )
    ||
    !array_key_exists(
        'verified',
        $data
    )
) {

    unset(
        $_SESSION[
            'forgot_password'
        ],
        $_SESSION[
            'password_reset_verified'
        ]
    );

    $_SESSION['forgot_error'] =
        'Your password reset session is invalid. Please request a new OTP.';

    header(
        'Location: forgot_password.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| RESET SESSION MUST BE VERIFIED
|--------------------------------------------------------------------------
*/

if (
    $data['verified'] !== true
) {

    unset(
        $_SESSION[
            'forgot_password'
        ],
        $_SESSION[
            'password_reset_verified'
        ]
    );

    $_SESSION['forgot_error'] =
        'Please verify your OTP before changing the password.';

    header(
        'Location: forgot_password.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| STUDENT ID
|--------------------------------------------------------------------------
*/

$studentId =
    (int)
    $data['id'];


if (
    $studentId <= 0
) {

    unset(
        $_SESSION[
            'forgot_password'
        ],
        $_SESSION[
            'password_reset_verified'
        ]
    );

    $_SESSION['forgot_error'] =
        'Invalid password reset session. Please request a new OTP.';

    header(
        'Location: forgot_password.php'
    );

    exit;
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

    unset(
        $_SESSION[
            'forgot_password'
        ],
        $_SESSION[
            'password_reset_verified'
        ]
    );

    $_SESSION['forgot_error'] =
        'Invalid password reset session. Please request a new OTP.';

    header(
        'Location: forgot_password.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$password =
    (string) (
        $_POST['password']
        ?? ''
    );


$confirmPassword =
    (string) (
        $_POST['confirm_password']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| REQUIRED
|--------------------------------------------------------------------------
*/

if (
    $password === ''
    ||
    $confirmPassword === ''
) {

    reset_password_error(
        'Please fill in all password fields.'
    );
}


/*
|--------------------------------------------------------------------------
| PASSWORD LENGTH
|--------------------------------------------------------------------------
*/

if (
    strlen($password) < 8
) {

    reset_password_error(
        'Password must contain at least 8 characters.'
    );
}


if (
    strlen($password) > 72
) {

    reset_password_error(
        'Password is too long.'
    );
}


/*
|--------------------------------------------------------------------------
| PASSWORD COMPLEXITY
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        '/[A-Za-z]/',
        $password
    )
) {

    reset_password_error(
        'Password must contain at least one letter.'
    );
}


if (
    !preg_match(
        '/[0-9]/',
        $password
    )
) {

    reset_password_error(
        'Password must contain at least one number.'
    );
}


/*
|--------------------------------------------------------------------------
| PASSWORD MATCH
|--------------------------------------------------------------------------
*/

if (
    $password !==
    $confirmPassword
) {

    reset_password_error(
        'Passwords do not match.'
    );
}


/*
|--------------------------------------------------------------------------
| HASH NEW PASSWORD
|--------------------------------------------------------------------------
*/

$hashedPassword =
    password_hash(
        $password,
        PASSWORD_DEFAULT
    );


if (
    $hashedPassword === false
) {

    reset_password_error(
        'Unable to secure your new password.'
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | LOCK STUDENT ACCOUNT
    |--------------------------------------------------------------------------
    */

    $check =
        $conn->prepare(
            "
            SELECT

                id,

                email,

                password,

                status

            FROM students

            WHERE id = ?

            LIMIT 1

            FOR UPDATE
            "
        );


    $check->execute([
        $studentId
    ]);


    $student =
        $check->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | ACCOUNT CHECK
    |--------------------------------------------------------------------------
    */

    if (
        !$student
        ||
        ($student['status'] ?? '')
        !== 'Active'
    ) {

        throw new RuntimeException(
            'This student account is not available.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EMAIL CONSISTENCY
    |--------------------------------------------------------------------------
    |
    | The account must still correspond to the email that passed OTP
    | verification.
    |
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(
            trim(
                (string)
                $student['email']
            )
        )
        !==
        $email
    ) {

        throw new RuntimeException(
            'The password reset session no longer matches this account.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PREVENT IMMEDIATE PASSWORD REUSE
    |--------------------------------------------------------------------------
    */

    $currentPassword =
        (string) (
            $student['password']
            ?? ''
        );


    if (
        $currentPassword !== ''
        &&
        password_verify(
            $password,
            $currentPassword
        )
    ) {

        throw new RuntimeException(
            'Your new password must be different from your current password.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE PASSWORD
    |--------------------------------------------------------------------------
    */

    $update =
        $conn->prepare(
            "
            UPDATE students

            SET

                password = ?

            WHERE

                id = ?

                AND email = ?

                AND status = 'Active'
            "
        );


    $update->execute([

        $hashedPassword,

        $studentId,

        $email

    ]);


    /*
    |--------------------------------------------------------------------------
    | VERIFY UPDATE
    |--------------------------------------------------------------------------
    |
    | rowCount() can be 0 in some database configurations when the value
    | is considered unchanged, so confirm the stored hash instead.
    |
    |--------------------------------------------------------------------------
    */

    $verify =
        $conn->prepare(
            "
            SELECT

                password

            FROM students

            WHERE

                id = ?

                AND email = ?

                AND status = 'Active'

            LIMIT 1
            "
        );


    $verify->execute([

        $studentId,

        $email

    ]);


    $updatedPassword =
        (string)
        $verify->fetchColumn();


    if (
        $updatedPassword === ''
        ||
        !password_verify(
            $password,
            $updatedPassword
        )
    ) {

        throw new RuntimeException(
            'The new password could not be verified.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | INVALIDATE RESET AUTHORIZATION
    |--------------------------------------------------------------------------
    */

    unset(

        $_SESSION[
            'forgot_password'
        ],

        $_SESSION[
            'password_reset_verified'
        ],

        $_SESSION[
            'reset_password_stage'
        ],

        $_SESSION[
            'reset_password_error'
        ],

        $_SESSION[
            'reset_otp_error'
        ],

        $_SESSION[
            'reset_otp_success'
        ],

        $_SESSION[
            'forgot_success'
        ],

        $_SESSION[
            'forgot_error'
        ],

        $_SESSION[
            'forgot_old_email'
        ]

    );


    /*
    |--------------------------------------------------------------------------
    | CLEAR RESET REQUEST COOLDOWN
    |--------------------------------------------------------------------------
    |
    | The student has successfully completed the reset flow.
    |
    |--------------------------------------------------------------------------
    */

    unset(
        $_SESSION[
            'forgot_password_request_at'
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | SESSION FIXATION PROTECTION
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(
        true
    );


    /*
    |--------------------------------------------------------------------------
    | FRESH CSRF
    |--------------------------------------------------------------------------
    */

    $_SESSION[
        'csrf_token'
    ] =
        bin2hex(
            random_bytes(32)
        );


    /*
    |--------------------------------------------------------------------------
    | CLEAR EXAM TOKEN
    |--------------------------------------------------------------------------
    */

    unset(
        $_SESSION[
            'exam_csrf_token'
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    $_SESSION[
        'success_message'
    ] =
        'Password has been reset successfully. Please login with your new password.';


    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */

    header(
        'Location: login.php'
    );

    exit;


} catch (
    RuntimeException $exception
) {

    if (
        $conn->inTransaction()
    ) {

        $conn->rollBack();
    }


    reset_password_error(
        $exception->getMessage()
    );


} catch (
    PDOException $exception
) {

    if (
        $conn->inTransaction()
    ) {

        $conn->rollBack();
    }


    error_log(
        'ExamSphere password reset database error: '
        .
        $exception->getMessage()
    );


    reset_password_error(
        'Unable to reset your password right now. Please try again.'
    );


} catch (
    Throwable $exception
) {

    if (
        $conn->inTransaction()
    ) {

        $conn->rollBack();
    }


    error_log(
        'ExamSphere password reset failed: '
        .
        $exception->getMessage()
    );


    reset_password_error(
        'Unable to reset your password right now. Please try again.'
    );
}