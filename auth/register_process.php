<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/mail.php';


/*
|--------------------------------------------------------------------------
| ERROR REDIRECT
|--------------------------------------------------------------------------
*/

function registration_error(
    string $message,
    array $old = []
): never {

    $_SESSION['register_error'] =
        $message;

    $_SESSION['register_old'] =
        $old;

    header(
        'Location: register.php'
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
        'Location: register.php'
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

    registration_error(
        'Your session expired. Please submit the registration form again.'
    );
}


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$fullName =
    trim(
        (string) (
            $_POST['full_name']
            ?? ''
        )
    );


$email =
    strtolower(
        trim(
            (string) (
                $_POST['email']
                ?? ''
            )
        )
    );


$mobile =
    trim(
        (string) (
            $_POST['mobile']
            ?? ''
        )
    );


$gender =
    trim(
        (string) (
            $_POST['gender']
            ?? ''
        )
    );


$dob =
    trim(
        (string) (
            $_POST['dob']
            ?? ''
        )
    );


$city =
    trim(
        (string) (
            $_POST['city']
            ?? ''
        )
    );


$state =
    trim(
        (string) (
            $_POST['state']
            ?? ''
        )
    );


$pincode =
    trim(
        (string) (
            $_POST['pincode']
            ?? ''
        )
    );


$address =
    trim(
        (string) (
            $_POST['address']
            ?? ''
        )
    );


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
| OLD FORM VALUES
|--------------------------------------------------------------------------
|
| Never store passwords here.
|
*/

$old = [

    'full_name' =>
        $fullName,

    'email' =>
        $email,

    'mobile' =>
        $mobile,

    'gender' =>
        $gender,

    'dob' =>
        $dob,

    'city' =>
        $city,

    'state' =>
        $state,

    'pincode' =>
        $pincode,

    'address' =>
        $address

];


/*
|--------------------------------------------------------------------------
| REQUIRED FIELDS
|--------------------------------------------------------------------------
*/

if (
    $fullName === ''
    ||
    $email === ''
    ||
    $mobile === ''
    ||
    $gender === ''
    ||
    $dob === ''
    ||
    $city === ''
    ||
    $state === ''
    ||
    $pincode === ''
    ||
    $address === ''
    ||
    $password === ''
    ||
    $confirmPassword === ''
) {

    registration_error(
        'Please fill in all fields.',
        $old
    );
}


/*
|--------------------------------------------------------------------------
| FULL NAME
|--------------------------------------------------------------------------
*/

if (
    mb_strlen(
        $fullName
    ) < 2
    ||
    mb_strlen(
        $fullName
    ) > 100
) {

    registration_error(
        'Full name must contain between 2 and 100 characters.',
        $old
    );
}


/*
|--------------------------------------------------------------------------
| FULL NAME FORMAT
|--------------------------------------------------------------------------
|
| Allows letters, spaces, apostrophes and hyphens without allowing
| arbitrary control characters or HTML-like input.
|
*/

if (
    !preg_match(
        "/^[\p{L}][\p{L}\s.'-]*$/u",
        $fullName
    )
) {

    registration_error(
        'Please enter a valid full name.',
        $old
    );
}


/*
|--------------------------------------------------------------------------
| EMAIL
|--------------------------------------------------------------------------
*/

if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    registration_error(
        'Please enter a valid email address.',
        $old
    );
}


if (
    strlen($email) > 150
) {

    registration_error(
        'Email address is too long.',
        $old
    );
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        '/^[0-9]{10,15}$/',
        $mobile
    )
) {

    registration_error(
        'Please enter a valid 10 to 15 digit mobile number.',
        $old
    );
}


/*
|--------------------------------------------------------------------------
| GENDER
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $gender,
        [
            'Male',
            'Female',
            'Other'
        ],
        true
    )
) {

    registration_error(
        'Please select a valid gender.',
        $old
    );
}


/*
|--------------------------------------------------------------------------
| DATE OF BIRTH
|--------------------------------------------------------------------------
*/

$date =
    DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $dob
    );


$dateErrors =
    DateTimeImmutable::getLastErrors();


$dateHasErrors =
    is_array(
        $dateErrors
    )
    &&
    (
        $dateErrors[
            'warning_count'
        ] > 0
        ||
        $dateErrors[
            'error_count'
        ] > 0
    );


if (
    !$date
    ||
    $dateHasErrors
    ||
    $date->format(
        'Y-m-d'
    ) !== $dob
) {

    registration_error(
        'Please enter a valid date of birth.',
        $old
    );
}


/*
|--------------------------------------------------------------------------
| AGE
|--------------------------------------------------------------------------
*/

$today =
    new DateTimeImmutable(
        'today'
    );


if (
    $date > $today
) {

    registration_error(
        'Date of birth cannot be in the future.',
        $old
    );
}


$age =
    $date->diff(
        $today
    )->y;


if (
    $age < 5
) {

    registration_error(
        'Please enter a valid date of birth.',
        $old
    );
}


/*
|--------------------------------------------------------------------------
| CITY
|--------------------------------------------------------------------------
*/

if (
    mb_strlen(
        $city
    ) < 2
    ||
    mb_strlen(
        $city
    ) > 80
) {

    registration_error(
        'City must contain between 2 and 80 characters.',
        $old
    );
}


/*
|--------------------------------------------------------------------------
| STATE
|--------------------------------------------------------------------------
*/

if (
    mb_strlen(
        $state
    ) < 2
    ||
    mb_strlen(
        $state
    ) > 80
) {

    registration_error(
        'State must contain between 2 and 80 characters.',
        $old
    );
}


/*
|--------------------------------------------------------------------------
| PINCODE
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        '/^[0-9]{4,10}$/',
        $pincode
    )
) {

    registration_error(
        'Please enter a valid pincode.',
        $old
    );
}


/*
|--------------------------------------------------------------------------
| ADDRESS
|--------------------------------------------------------------------------
*/

if (
    mb_strlen(
        $address
    ) < 5
    ||
    mb_strlen(
        $address
    ) > 2000
) {

    registration_error(
        'Address must contain between 5 and 2000 characters.',
        $old
    );
}


/*
|--------------------------------------------------------------------------
| PASSWORD
|--------------------------------------------------------------------------
*/

if (
    strlen($password) < 8
) {

    registration_error(
        'Password must contain at least 8 characters.',
        $old
    );
}


if (
    strlen($password) > 72
) {

    registration_error(
        'Password is too long.',
        $old
    );
}


if (
    !preg_match(
        '/[A-Za-z]/',
        $password
    )
    ||
    !preg_match(
        '/[0-9]/',
        $password
    )
) {

    registration_error(
        'Password must contain at least one letter and one number.',
        $old
    );
}


if (
    $password !==
    $confirmPassword
) {

    registration_error(
        'Passwords do not match.',
        $old
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | EXISTING ACCOUNT CHECK
    |--------------------------------------------------------------------------
    |
    | This is only an early user-friendly check.
    |
    | The UNIQUE index on students.email remains the final authority.
    |
    */

    $existingStmt =
        $conn->prepare(
            "SELECT
                id,
                email_verified,
                status
             FROM students
             WHERE email = ?
             LIMIT 1"
        );


    $existingStmt->execute([
        $email
    ]);


    $existing =
        $existingStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        $existing
    ) {

        if (
            ($existing[
                'email_verified'
            ] ?? 'No')
            ===
            'No'
        ) {

            registration_error(
                'An account with this email is already pending verification. Please complete the OTP verification.',
                $old
            );
        }


        registration_error(
            'An account with this email already exists.',
            $old
        );
    }


    /*
    |--------------------------------------------------------------------------
    | HASH PASSWORD
    |--------------------------------------------------------------------------
    */

    $passwordHash =
        password_hash(
            $password,
            PASSWORD_DEFAULT
        );


    if (
        $passwordHash === false
    ) {

        registration_error(
            'Unable to secure your password.',
            $old
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


    $otpHash =
        password_hash(
            $otp,
            PASSWORD_DEFAULT
        );


    if (
        $otpHash === false
    ) {

        registration_error(
            'Unable to create email verification code.',
            $old
        );
    }


    $otpExpiresAt =
        time() + 300;


    /*
    |--------------------------------------------------------------------------
    | CLEAR PREVIOUS PENDING REGISTRATION
    |--------------------------------------------------------------------------
    */

    unset(
        $_SESSION[
            'pending_registration'
        ],
        $_SESSION[
            'otp_error'
        ],
        $_SESSION[
            'otp_message'
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | STORE PENDING REGISTRATION
    |--------------------------------------------------------------------------
    |
    | The actual student row is intentionally NOT created until OTP
    | verification succeeds.
    |
    */

    $_SESSION[
        'pending_registration'
    ] = [

        'full_name' =>
            $fullName,

        'email' =>
            $email,

        'mobile' =>
            $mobile,

        'gender' =>
            $gender,

        'dob' =>
            $dob,

        'address' =>
            $address,

        'city' =>
            $city,

        'state' =>
            $state,

        'pincode' =>
            $pincode,

        'password' =>
            $passwordHash,

        'otp' =>
            $otpHash,

        'otp_expires' =>
            $otpExpiresAt,

        'otp_attempts' =>
            0,

        'otp_sent_at' =>
            time()

    ];


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
        $email,
        $fullName
    );


    /*
    |--------------------------------------------------------------------------
    | SAFE HTML VALUES
    |--------------------------------------------------------------------------
    */

    $safeName =
        htmlspecialchars(
            $fullName,
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
    | SUBJECT
    |--------------------------------------------------------------------------
    */

    $mail->Subject =
        'ExamSphere Email Verification OTP';


    /*
    |--------------------------------------------------------------------------
    | HTML EMAIL
    |--------------------------------------------------------------------------
    */

    $mail->Body = '

<!DOCTYPE html>

<html>

<body
    style="
        margin:0;
        padding:30px 20px;
        background:#f7f4ef;
        font-family:Arial,Helvetica,sans-serif;
    "
>


<div
    style="
        max-width:600px;
        margin:0 auto;
        background:#ffffff;
        border-radius:18px;
        overflow:hidden;
        box-shadow:0 15px 40px rgba(0,0,0,.08);
    "
>


<div
    style="
        padding:28px;
        background:#5D4037;
        color:#ffffff;
        text-align:center;
    "
>

    <h1
        style="
            margin:0;
            font-size:28px;
        "
    >
        ExamSphere
    </h1>


    <p
        style="
            margin:8px 0 0;
            opacity:.9;
        "
    >
        Smart • Secure • Trusted
    </p>

</div>


<div
    style="
        padding:35px;
    "
>


<p
    style="
        font-size:16px;
        color:#333333;
    "
>

    Hello
    <strong>'
        .
        $safeName
        .
    '</strong>,

</p>


<p
    style="
        font-size:15px;
        line-height:1.7;
        color:#555555;
    "
>

    Thank you for creating your ExamSphere
    student account. Please use the OTP below
    to verify your email address.

</p>


<div
    style="
        margin:30px 0;
        padding:22px;
        text-align:center;
        background:#f5f5dc;
        border-radius:14px;
    "
>


<div
    style="
        font-size:13px;
        color:#777777;
        margin-bottom:10px;
    "
>

    Your verification code

</div>


<div
    style="
        font-size:38px;
        font-weight:bold;
        letter-spacing:9px;
        color:#5D4037;
    "
>

    '
    .
    $safeOtp
    .
    '

</div>


</div>


<p
    style="
        font-size:14px;
        line-height:1.7;
        color:#555555;
    "
>

    This OTP is valid for
    <strong>5 minutes</strong>.

    Do not share this code with anyone.

</p>


<p
    style="
        font-size:14px;
        color:#777777;
    "
>

    If you did not request this registration,
    you can safely ignore this email.

</p>


</div>


<div
    style="
        padding:20px;
        text-align:center;
        background:#fafafa;
        border-top:1px solid #eeeeee;
        color:#888888;
        font-size:12px;
    "
>

    © ExamSphere. All rights reserved.

</div>


</div>

</body>

</html>
';


    /*
    |--------------------------------------------------------------------------
    | PLAIN TEXT
    |--------------------------------------------------------------------------
    */

    $mail->AltBody =
        'Hello '
        .
        $fullName
        .
        ','
        .
        PHP_EOL
        .
        PHP_EOL
        .
        'Your ExamSphere email verification OTP is: '
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
    | SEND EMAIL
    |--------------------------------------------------------------------------
    */

    $mail->send();


    /*
    |--------------------------------------------------------------------------
    | OTP PAGE
    |--------------------------------------------------------------------------
    */

    header(
        'Location: verify_otp.php'
    );

    exit;


} catch (
    PDOException $exception
) {

    /*
    |--------------------------------------------------------------------------
    | DUPLICATE EMAIL RACE
    |--------------------------------------------------------------------------
    |
    | The database UNIQUE constraint is the final protection.
    |
    */

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );


    error_log(
        'ExamSphere registration database error: '
        .
        $exception->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | User-friendly duplicate detection
    |--------------------------------------------------------------------------
    */

    $message =
        'Unable to create your account right now. Please try again.';


    if (
        $exception->getCode()
        ===
        '23000'
    ) {

        $message =
            'An account with this email address already exists.';
    }


    registration_error(
        $message,
        $old
    );


} catch (
    RuntimeException $exception
) {

    /*
    |--------------------------------------------------------------------------
    | MAIL / CONFIGURATION ERROR
    |--------------------------------------------------------------------------
    */

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );


    error_log(
        'ExamSphere registration configuration error: '
        .
        $exception->getMessage()
    );


    registration_error(
        'Unable to send the verification email right now. Please try again later.',
        $old
    );


} catch (
    Throwable $exception
) {

    /*
    |--------------------------------------------------------------------------
    | GENERAL ERROR
    |--------------------------------------------------------------------------
    */

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );


    error_log(
        'ExamSphere registration error: '
        .
        $exception->getMessage()
    );


    registration_error(
        'Unable to start registration right now. Please try again later.',
        $old
    );
}