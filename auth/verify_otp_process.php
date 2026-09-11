<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';


/*
|--------------------------------------------------------------------------
| REDIRECT HELPERS
|--------------------------------------------------------------------------
*/

function otp_error_redirect(
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

    otp_error_redirect(
        'Your session expired. Please try again.'
    );
}


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
        'Your registration session has expired. Please register again.';

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
            'Your registration session is incomplete. Please register again.';

        header(
            'Location: register.php'
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| NORMALIZE PENDING DATA
|--------------------------------------------------------------------------
*/

$fullName =
    trim(
        (string)
        $data['full_name']
    );


$email =
    strtolower(
        trim(
            (string)
            $data['email']
        )
    );


$mobile =
    trim(
        (string)
        $data['mobile']
    );


$gender =
    trim(
        (string)
        $data['gender']
    );


$dob =
    trim(
        (string)
        $data['dob']
    );


$address =
    trim(
        (string)
        $data['address']
    );


$city =
    trim(
        (string)
        $data['city']
    );


$state =
    trim(
        (string)
        $data['state']
    );


$pincode =
    trim(
        (string)
        $data['pincode']
    );


$passwordHash =
    (string)
    $data['password'];


$otpHash =
    (string)
    $data['otp'];


$otpExpires =
    (int)
    $data['otp_expires'];


$otpAttempts =
    (int)
    $data['otp_attempts'];


$otpSentAt =
    (int)
    $data['otp_sent_at'];


/*
|--------------------------------------------------------------------------
| PENDING DATA VALIDATION
|--------------------------------------------------------------------------
|
| This is a second server-side validation boundary.
| Even though the values are stored in the server session, the final
| registration step should never assume that the session state is valid.
|
|--------------------------------------------------------------------------
*/

if (
    $fullName === ''
    ||
    mb_strlen($fullName) > 100
) {

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );

    $_SESSION['register_error'] =
        'Invalid registration information. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
}


if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
    ||
    strlen($email) > 150
) {

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );

    $_SESSION['register_error'] =
        'Invalid email address. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
}


if (
    !preg_match(
        '/^[0-9]{10,15}$/',
        $mobile
    )
) {

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );

    $_SESSION['register_error'] =
        'Invalid mobile number. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
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

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );

    $_SESSION['register_error'] =
        'Invalid gender information. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
}


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
        $dateErrors['warning_count'] > 0
        ||
        $dateErrors['error_count'] > 0
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

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );

    $_SESSION['register_error'] =
        'Invalid date of birth. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
}


if (
    $date >
    new DateTimeImmutable(
        'today'
    )
) {

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );

    $_SESSION['register_error'] =
        'Invalid date of birth. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
}


if (
    mb_strlen($city) < 2
    ||
    mb_strlen($city) > 80
) {

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );

    $_SESSION['register_error'] =
        'Invalid city information. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
}


if (
    mb_strlen($state) < 2
    ||
    mb_strlen($state) > 80
) {

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );

    $_SESSION['register_error'] =
        'Invalid state information. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
}


if (
    !preg_match(
        '/^[0-9]{4,10}$/',
        $pincode
    )
) {

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );

    $_SESSION['register_error'] =
        'Invalid pincode. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
}


if (
    mb_strlen($address) < 5
    ||
    mb_strlen($address) > 2000
) {

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );

    $_SESSION['register_error'] =
        'Invalid address information. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| PASSWORD HASH VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $passwordHash === ''
    ||
    password_get_info(
        $passwordHash
    )['algo'] === 0
) {

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );

    $_SESSION['register_error'] =
        'Invalid password information. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| OTP SESSION VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $otpHash === ''
    ||
    password_get_info(
        $otpHash
    )['algo'] === 0
) {

    unset(
        $_SESSION[
            'pending_registration'
        ]
    );

    $_SESSION['register_error'] =
        'Invalid verification session. Please register again.';

    header(
        'Location: register.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| OTP INPUT
|--------------------------------------------------------------------------
*/

$userOtp =
    trim(
        (string) (
            $_POST['otp']
            ?? ''
        )
    );


if (
    !preg_match(
        '/^[0-9]{6}$/',
        $userOtp
    )
) {

    otp_error_redirect(
        'Please enter a valid 6-digit OTP.'
    );
}


/*
|--------------------------------------------------------------------------
| OTP EXPIRY
|--------------------------------------------------------------------------
|
| Use >= so an OTP becomes invalid exactly at its expiry timestamp.
|
|--------------------------------------------------------------------------
*/

if (
    time()
    >=
    $otpExpires
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
| OTP ATTEMPT VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $otpAttempts < 0
) {

    $otpAttempts =
        0;
}


if (
    $otpAttempts >= 5
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
| OTP VERIFY
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
    | EARLY DUPLICATE CHECK
    |--------------------------------------------------------------------------
    |
    | This provides a user-friendly message.
    |
    | The UNIQUE database constraint remains the final protection.
    |
    */

    $existingQuery =
        $conn->prepare(
            "
            SELECT
                id

            FROM students

            WHERE email = ?

            LIMIT 1
            "
        );


    $existingQuery->execute([
        $email
    ]);


    if (
        $existingQuery->fetchColumn()
    ) {

        unset(
            $_SESSION[
                'pending_registration'
            ]
        );


        $_SESSION['register_error'] =
            'This email address is already registered.';


        header(
            'Location: register.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | TRANSACTION
    |--------------------------------------------------------------------------
    */

    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | RECHECK EMAIL INSIDE TRANSACTION
    |--------------------------------------------------------------------------
    */

    $lockedDuplicate =
        $conn->prepare(
            "
            SELECT
                id

            FROM students

            WHERE email = ?

            LIMIT 1

            FOR UPDATE
            "
        );


    $lockedDuplicate->execute([
        $email
    ]);


    if (
        $lockedDuplicate->fetchColumn()
    ) {

        throw new RuntimeException(
            'This email address is already registered.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TEMPORARY STUDENT CODE
    |--------------------------------------------------------------------------
    |
    | student_code is replaced with the final STU00001-style code after
    | the auto-increment ID is known.
    |
    |--------------------------------------------------------------------------
    */

    $temporaryCode =
        'TMP'
        .
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
            "
            INSERT INTO students
            (
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

            VALUES
            (
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

                'Yes',

                'Active'
            )
            "
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
            $passwordHash

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
        'STU'
        .
        str_pad(
            (string)
            $studentId,
            5,
            '0',
            STR_PAD_LEFT
        );


    /*
    |--------------------------------------------------------------------------
    | UPDATE STUDENT CODE
    |--------------------------------------------------------------------------
    */

    $updateCode =
        $conn->prepare(
            "
            UPDATE students

            SET
                student_code = ?

            WHERE
                id = ?
            "
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
    | VERIFY FINAL RECORD
    |--------------------------------------------------------------------------
    */

    $verifyStudent =
        $conn->prepare(
            "
            SELECT

                id,
                student_code,
                full_name,
                email,
                email_verified,
                status

            FROM students

            WHERE

                id = ?

                AND email = ?

                AND student_code = ?

                AND email_verified = 'Yes'

                AND status = 'Active'

            LIMIT 1
            "
        );


    $verifyStudent->execute([

        $studentId,

        $email,

        $studentCode

    ]);


    $verifiedStudent =
        $verifyStudent->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$verifiedStudent
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
    | SESSION REGENERATION
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(
        true
    );


    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATED SESSION
    |--------------------------------------------------------------------------
    */

    $_SESSION['user_id'] =
        $studentId;


    $_SESSION['user_name'] =
        (string)
        $verifiedStudent[
            'full_name'
        ];


    $_SESSION['user_email'] =
        (string)
        $verifiedStudent[
            'email'
        ];


    $_SESSION['user_role'] =
        'student';


    $_SESSION['last_activity'] =
        time();


    /*
    |--------------------------------------------------------------------------
    | FRESH CSRF TOKEN
    |--------------------------------------------------------------------------
    */

    $_SESSION['csrf_token'] =
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
    | CLEAR REGISTRATION STATE
    |--------------------------------------------------------------------------
    */

    unset(

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
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    $_SESSION[
        'success_message'
    ] =
        'Your account has been verified and created successfully. Welcome to ExamSphere!';


    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    header(
        'Location: ../student/dashboard.php'
    );

    exit;


} catch (
    PDOException $exception
) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    if (
        $conn->inTransaction()
    ) {

        $conn->rollBack();
    }


    error_log(
        'ExamSphere student registration database error: '
        .
        $exception->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | DUPLICATE EMAIL RACE
    |--------------------------------------------------------------------------
    */

    if (
        $exception->getCode()
        ===
        '23000'
    ) {

        unset(
            $_SESSION[
                'pending_registration'
            ]
        );

        $_SESSION['register_error'] =
            'This email address is already registered.';

        header(
            'Location: register.php'
        );

        exit;
    }


    $_SESSION['otp_error'] =
        'Unable to complete registration right now. Please try again.';


    header(
        'Location: verify_otp.php'
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


    error_log(
        'ExamSphere OTP registration error: '
        .
        $exception->getMessage()
    );


    if (
        str_contains(
            strtolower(
                $exception->getMessage()
            ),
            'already registered'
        )
    ) {

        unset(
            $_SESSION[
                'pending_registration'
            ]
        );

        $_SESSION['register_error'] =
            $exception->getMessage();

        header(
            'Location: register.php'
        );

        exit;
    }


    $_SESSION['otp_error'] =
        'Unable to complete registration right now. Please try again.';


    header(
        'Location: verify_otp.php'
    );

    exit;


} catch (
    Throwable $exception
) {

    if (
        $conn->inTransaction()
    ) {

        $conn->rollBack();
    }


    error_log(
        'ExamSphere OTP verification failed: '
        .
        $exception->getMessage()
    );


    $_SESSION['otp_error'] =
        'Unable to complete registration right now. Please try again.';


    header(
        'Location: verify_otp.php'
    );

    exit;
}