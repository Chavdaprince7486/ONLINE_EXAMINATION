<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';


function otp_redirect(
    string $message,
    string $location = 'verify_otp.php'
): never {

    $_SESSION['otp_error'] =
        $message;

    header(
        'Location: ' .
        $location
    );

    exit;
}


function registration_reset(
    string $message
): never {

    unset(
        $_SESSION['pending_registration']
    );

    $_SESSION['register_error'] =
        $message;

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
    $_SERVER['REQUEST_METHOD'] !==
    'POST'
) {

    header(
        'Location: verify_otp.php'
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

    otp_redirect(
        'Your session expired. Please try again.'
    );
}


/*
|--------------------------------------------------------------------------
| PENDING REGISTRATION
|--------------------------------------------------------------------------
*/

$pending =
    $_SESSION[
        'pending_registration'
    ] ?? null;


if (
    !is_array($pending)
) {

    registration_reset(
        'Your registration session has expired. Please register again.'
    );
}


/*
|--------------------------------------------------------------------------
| REQUIRED SESSION DATA
|--------------------------------------------------------------------------
*/

$requiredFields = [

    'full_name',
    'email',
    'mobile',
    'gender',
    'dob',
    'address',
    'city',
    'state',
    'pincode',
    'password',
    'otp',
    'otp_expires',
    'otp_attempts',
    'otp_sent_at'

];


foreach (
    $requiredFields as $field
) {

    if (
        !array_key_exists(
            $field,
            $pending
        )
    ) {

        registration_reset(
            'Your verification session is incomplete. Please register again.'
        );

    }

}


/*
|--------------------------------------------------------------------------
| NORMALIZE DATA
|--------------------------------------------------------------------------
*/

$fullName =
    trim(
        (string)
        $pending['full_name']
    );


$email =
    strtolower(
        trim(
            (string)
            $pending['email']
        )
    );


$mobile =
    trim(
        (string)
        $pending['mobile']
    );


$gender =
    trim(
        (string)
        $pending['gender']
    );


$dob =
    trim(
        (string)
        $pending['dob']
    );


$address =
    trim(
        (string)
        $pending['address']
    );


$city =
    trim(
        (string)
        $pending['city']
    );


$state =
    trim(
        (string)
        $pending['state']
    );


$pincode =
    trim(
        (string)
        $pending['pincode']
    );


$passwordHash =
    (string)
    $pending['password'];


$otpHash =
    (string)
    $pending['otp'];


$otpExpires =
    (int)
    $pending['otp_expires'];


$otpAttempts =
    max(
        0,
        (int)
        $pending['otp_attempts']
    );


/*
|--------------------------------------------------------------------------
| SECOND SERVER-SIDE VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $fullName === '' ||
    mb_strlen(
        $fullName
    ) > 100
) {

    registration_reset(
        'Invalid registration information. Please register again.'
    );

}


if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    ) ||
    strlen($email) > 150
) {

    registration_reset(
        'Invalid email address. Please register again.'
    );

}


if (
    !preg_match(
        '/^[0-9]{10,15}$/',
        $mobile
    )
) {

    registration_reset(
        'Invalid mobile number. Please register again.'
    );

}


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

    registration_reset(
        'Invalid gender information. Please register again.'
    );

}


/*
|--------------------------------------------------------------------------
| DATE VALIDATION
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
    !$date ||
    $dateHasErrors ||
    $date->format(
        'Y-m-d'
    ) !== $dob ||
    $date >
    new DateTimeImmutable(
        'today'
    )
) {

    registration_reset(
        'Invalid date of birth. Please register again.'
    );

}


if (
    mb_strlen(
        $city
    ) < 2 ||
    mb_strlen(
        $city
    ) > 80
) {

    registration_reset(
        'Invalid city information. Please register again.'
    );

}


if (
    mb_strlen(
        $state
    ) < 2 ||
    mb_strlen(
        $state
    ) > 80
) {

    registration_reset(
        'Invalid state information. Please register again.'
    );

}


if (
    !preg_match(
        '/^[0-9]{4,10}$/',
        $pincode
    )
) {

    registration_reset(
        'Invalid pincode. Please register again.'
    );

}


if (
    mb_strlen(
        $address
    ) < 5 ||
    mb_strlen(
        $address
    ) > 2000
) {

    registration_reset(
        'Invalid address information. Please register again.'
    );

}


/*
|--------------------------------------------------------------------------
| PASSWORD HASH VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $passwordHash === '' ||
    password_get_info(
        $passwordHash
    )['algo'] === 0
) {

    registration_reset(
        'Invalid password information. Please register again.'
    );

}


/*
|--------------------------------------------------------------------------
| OTP HASH VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $otpHash === '' ||
    password_get_info(
        $otpHash
    )['algo'] === 0
) {

    registration_reset(
        'Invalid verification session. Please register again.'
    );

}


/*
|--------------------------------------------------------------------------
| OTP INPUT
|--------------------------------------------------------------------------
|
| This is the important fix.
| Any spaces, hidden formatting characters, or pasted separators
| are removed before checking the six digits.
|
|--------------------------------------------------------------------------
*/

$userOtp =
    preg_replace(
        '/[^0-9]/',
        '',
        (string)(
            $_POST['otp'] ?? ''
        )
    ) ?? '';


if (
    !preg_match(
        '/^[0-9]{6}$/',
        $userOtp
    )
) {

    otp_redirect(
        'Please enter a valid 6-digit OTP.'
    );

}


/*
|--------------------------------------------------------------------------
| OTP EXPIRY
|--------------------------------------------------------------------------
*/

if (
    time() >= $otpExpires
) {

    registration_reset(
        'Your OTP has expired. Please register again.'
    );

}


/*
|--------------------------------------------------------------------------
| OTP ATTEMPT LIMIT
|--------------------------------------------------------------------------
*/

if (
    $otpAttempts >= 5
) {

    registration_reset(
        'Too many incorrect OTP attempts. Please register again.'
    );

}


/*
|--------------------------------------------------------------------------
| VERIFY OTP
|--------------------------------------------------------------------------
*/

if (
    !password_verify(
        $userOtp,
        $otpHash
    )
) {

    $otpAttempts++;

    if (
        $otpAttempts >= 5
    ) {

        registration_reset(
            'Too many incorrect OTP attempts. Please register again.'
        );

    }

    $_SESSION[
        'pending_registration'
    ]['otp_attempts'] =
        $otpAttempts;

    $_SESSION['otp_error'] =
        'Invalid OTP. Please check the code and try again.';

    header(
        'Location: verify_otp.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| DATABASE REGISTRATION
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | CHECK EXISTING EMAIL
    |--------------------------------------------------------------------------
    */

    $existing =
        $conn->prepare(
            'SELECT id
             FROM students
             WHERE email = ?
             LIMIT 1'
        );


    $existing->execute([
        $email
    ]);


    if (
        $existing->fetchColumn()
    ) {

        registration_reset(
            'This email address is already registered.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | TRANSACTION
    |--------------------------------------------------------------------------
    */

    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | TEMPORARY UNIQUE STUDENT CODE
    |--------------------------------------------------------------------------
    */

    $temporaryCode =
        'TMP' .
        strtoupper(
            bin2hex(
                random_bytes(12)
            )
        );


    /*
    |--------------------------------------------------------------------------
    | INSERT STUDENT
    |--------------------------------------------------------------------------
    */

    $insert =
        $conn->prepare(
            'INSERT INTO students (
                student_code,
                full_name,
                email,
                mobile,
                gender,
                dob,
                address,
                city,
                state,
                pincode,
                password,
                email_verified,
                status
            )
            VALUES (
                :student_code,
                :full_name,
                :email,
                :mobile,
                :gender,
                :dob,
                :address,
                :city,
                :state,
                :pincode,
                :password,
                :email_verified,
                :status
            )'
        );


    $insert->execute([

        ':student_code' =>
            $temporaryCode,

        ':full_name' =>
            $fullName,

        ':email' =>
            $email,

        ':mobile' =>
            $mobile,

        ':gender' =>
            $gender,

        ':dob' =>
            $dob,

        ':address' =>
            $address,

        ':city' =>
            $city,

        ':state' =>
            $state,

        ':pincode' =>
            $pincode,

        ':password' =>
            $passwordHash,

        ':email_verified' =>
            'Yes',

        ':status' =>
            'Active'

    ]);


    /*
    |--------------------------------------------------------------------------
    | STUDENT ID
    |--------------------------------------------------------------------------
    */

    $studentId =
        (int)
        $conn->lastInsertId();


    if (
        $studentId <= 0
    ) {

        throw new RuntimeException(
            'Unable to create the student account.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | FINAL STUDENT CODE
    |--------------------------------------------------------------------------
    */

    $studentCode =
        'STU' .
        str_pad(
            (string)
            $studentId,
            5,
            '0',
            STR_PAD_LEFT
        );


    /*
    |--------------------------------------------------------------------------
    | SAVE FINAL STUDENT CODE
    |--------------------------------------------------------------------------
    */

    $updateCode =
        $conn->prepare(
            'UPDATE students
             SET student_code = ?
             WHERE id = ?'
        );


    $updateCode->execute([

        $studentCode,

        $studentId

    ]);


    if (
        $updateCode->rowCount()
        !==
        1
    ) {

        throw new RuntimeException(
            'Unable to finalize the student code.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | FINAL ACCOUNT VERIFICATION
    |--------------------------------------------------------------------------
    */

    $verifyStudent =
        $conn->prepare(
            "SELECT
                id,
                student_code,
                full_name,
                email,
                email_verified,
                status
             FROM students
             WHERE id = ?
               AND email = ?
               AND student_code = ?
               AND email_verified = 'Yes'
               AND status = 'Active'
             LIMIT 1"
        );


    $verifyStudent->execute([

        $studentId,

        $email,

        $studentCode

    ]);


    $student =
        $verifyStudent->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$student
    ) {

        throw new RuntimeException(
            'The student account could not be verified after registration.'
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
    | REGENERATE SESSION
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(
        true
    );


    /*
    |--------------------------------------------------------------------------
    | STUDENT LOGIN SESSION
    |--------------------------------------------------------------------------
    */

    $_SESSION['user_id'] =
        $studentId;


    $_SESSION['user_name'] =
        (string)
        $student['full_name'];


    $_SESSION['user_email'] =
        (string)
        $student['email'];


    $_SESSION['user_role'] =
        'student';


    $_SESSION['last_activity'] =
        time();


    /*
    |--------------------------------------------------------------------------
    | NEW CSRF TOKEN
    |--------------------------------------------------------------------------
    */

    $_SESSION['csrf_token'] =
        bin2hex(
            random_bytes(32)
        );


    /*
    |--------------------------------------------------------------------------
    | CLEAR TEMP STATE
    |--------------------------------------------------------------------------
    */

    unset(

        $_SESSION[
            'exam_csrf_token'
        ],

        $_SESSION[
            'pending_registration'
        ],

        $_SESSION[
            'register_old'
        ],

        $_SESSION[
            'register_error'
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
    | SUCCESS MESSAGE
    |--------------------------------------------------------------------------
    */

    $_SESSION[
        'success_message'
    ] =
        'Your account has been verified and created successfully. Welcome to ExamSphere!';


    /*
    |--------------------------------------------------------------------------
    | STUDENT DASHBOARD
    |--------------------------------------------------------------------------
    */

    header(
        'Location: ../student/dashboard.php'
    );

    exit;


} catch (
    PDOException $exception
) {

    if (
        $conn->inTransaction()
    ) {

        $conn->rollBack();

    }


    error_log(
        'ExamSphere student registration database error: ' .
        $exception->getMessage()
    );


    if (
        $exception->getCode()
        ===
        '23000'
    ) {

        registration_reset(
            'This email address is already registered.'
        );

    }


    otp_redirect(
        'Unable to complete registration right now. Please try again.'
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
        'ExamSphere OTP verification failed: ' .
        $exception->getMessage()
    );


    otp_redirect(
        'Unable to complete registration right now. Please try again.'
    );

}