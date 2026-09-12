<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| EXAMSPHERE LOGIN PROCESS
|--------------------------------------------------------------------------
|
| Supports:
|
| - Student login
| - Teacher login
| - Admin login
| - password_hash() / password_verify()
| - bcrypt
| - Argon2
| - legacy MD5
| - legacy SHA1
| - legacy plain-text passwords
|
| Legacy/plain passwords are automatically migrated to password_hash()
| after a successful login.
|
|--------------------------------------------------------------------------
*/

require_once '../config/config.php';
require_once '../config/session.php';
require_once '../config/auth.php';


/*
|--------------------------------------------------------------------------
| POST ONLY
|--------------------------------------------------------------------------
*/

if (
    strtoupper(
        (string)(
            $_SERVER['REQUEST_METHOD']
            ??
            'GET'
        )
    ) !==
    'POST'
) {

    header(
        'Location: login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF PROTECTION
|--------------------------------------------------------------------------
*/

if (
    !verify_csrf_token(
        $_POST['csrf_token']
        ??
        null
    )
) {

    $_SESSION['error'] =
        'Your session expired. Please refresh the login page and try again.';

    header(
        'Location: login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$role =
    strtolower(
        trim(
            (string)(
                $_POST['role']
                ??
                ''
            )
        )
    );


$email =
    strtolower(
        trim(
            (string)(
                $_POST['email']
                ??
                ''
            )
        )
    );


$password =
    (string)(
        $_POST['password']
        ??
        ''
    );


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $role === ''
    ||
    $email === ''
    ||
    $password === ''
) {

    $_SESSION['error'] =
        'Please fill in all login fields.';

    header(
        'Location: login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ROLE VALIDATION
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $role,
        [
            'student',
            'teacher',
            'admin'
        ],
        true
    )
) {

    $_SESSION['error'] =
        'Invalid login role.';

    header(
        'Location: login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| EMAIL VALIDATION
|--------------------------------------------------------------------------
*/

if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    $_SESSION['error'] =
        'Please enter a valid email address.';

    header(
        'Location: login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| TABLE CONFIGURATION
|--------------------------------------------------------------------------
*/

$tableMap = [

    'student' => [

        'table' =>
            'students',

        'dashboard' =>
            BASE_URL .
            'student/dashboard.php'

    ],

    'teacher' => [

        'table' =>
            'teachers',

        'dashboard' =>
            BASE_URL .
            'teacher/dashboard.php'

    ],

    'admin' => [

        'table' =>
            'admins',

        'dashboard' =>
            BASE_URL .
            'admin/dashboard.php'

    ]

];


$table =
    $tableMap[
        $role
    ]['table'];


$dashboard =
    $tableMap[
        $role
    ]['dashboard'];


/*
|--------------------------------------------------------------------------
| FIND USER
|--------------------------------------------------------------------------
*/

try {

    $stmt =
        $conn->prepare(
            "
            SELECT *

            FROM {$table}

            WHERE email = ?

            LIMIT 1
            "
        );

    $stmt->execute([
        $email
    ]);

    $user =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $exception
) {

    error_log(
        'ExamSphere login database query failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to process login right now. Please try again.';

    header(
        'Location: login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| USER NOT FOUND
|--------------------------------------------------------------------------
*/

if (
    !$user
) {

    /*
     * Keep the browser message generic enough
     * to avoid exposing unnecessary account data.
     */

    $_SESSION['error'] =
        'Invalid email or password.';

    header(
        'Location: login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ACCOUNT STATUS
|--------------------------------------------------------------------------
*/

$status =
    trim(
        (string)(
            $user['status']
            ??
            ''
        )
    );


if (
    strcasecmp(
        $status,
        'Active'
    ) !==
    0
) {

    $_SESSION['error'] =
        'Your account is inactive. Please contact the administrator.';

    header(
        'Location: login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| STORED PASSWORD
|--------------------------------------------------------------------------
*/

$storedPassword =
    trim(
        (string)(
            $user['password']
            ??
            ''
        )
    );


if (
    $storedPassword === ''
) {

    $_SESSION['error'] =
        'This account does not have a valid password. Please contact the administrator.';

    header(
        'Location: login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| PASSWORD VERIFICATION
|--------------------------------------------------------------------------
*/

$passwordValid =
    false;


/*
|--------------------------------------------------------------------------
| PASSWORD HASH VERIFICATION
|--------------------------------------------------------------------------
|
| password_verify() supports:
|
| - bcrypt
| - Argon2
| - other password_hash() algorithms
|
|--------------------------------------------------------------------------
*/

try {

    $passwordValid =
        password_verify(
            $password,
            $storedPassword
        );

} catch (
    Throwable $exception
) {

    $passwordValid =
        false;
}


/*
|--------------------------------------------------------------------------
| PASSWORD MIGRATION FLAG
|--------------------------------------------------------------------------
|
| true when successful authentication was performed against a
| legacy password representation.
|
|--------------------------------------------------------------------------
*/

$legacyPassword =
    false;


/*
|--------------------------------------------------------------------------
| LEGACY MD5
|--------------------------------------------------------------------------
|
| Supported only for migration of an existing local installation.
|
|--------------------------------------------------------------------------
*/

if (
    !$passwordValid
    &&
    preg_match(
        '/^[a-f0-9]{32}$/i',
        $storedPassword
    )
) {

    $legacyHash =
        md5(
            $password
        );

    if (
        hash_equals(
            strtolower(
                $storedPassword
            ),
            strtolower(
                $legacyHash
            )
        )
    ) {

        $passwordValid =
            true;

        $legacyPassword =
            true;
    }
}


/*
|--------------------------------------------------------------------------
| LEGACY SHA1
|--------------------------------------------------------------------------
*/

if (
    !$passwordValid
    &&
    preg_match(
        '/^[a-f0-9]{40}$/i',
        $storedPassword
    )
) {

    $legacyHash =
        sha1(
            $password
        );

    if (
        hash_equals(
            strtolower(
                $storedPassword
            ),
            strtolower(
                $legacyHash
            )
        )
    ) {

        $passwordValid =
            true;

        $legacyPassword =
            true;
    }
}


/*
|--------------------------------------------------------------------------
| LEGACY PLAIN TEXT
|--------------------------------------------------------------------------
|
| Used only if the stored value does not look like a password_hash()
| value and is not MD5/SHA1.
|
|--------------------------------------------------------------------------
*/

if (
    !$passwordValid
    &&
    !preg_match(
        '/^\$2[aby]\$/',
        $storedPassword
    )
    &&
    !preg_match(
        '/^\$argon2(id|i|d)\$/',
        $storedPassword
    )
    &&
    !preg_match(
        '/^[a-f0-9]{32}$/i',
        $storedPassword
    )
    &&
    !preg_match(
        '/^[a-f0-9]{40}$/i',
        $storedPassword
    )
) {

    if (
        hash_equals(
            $storedPassword,
            $password
        )
    ) {

        $passwordValid =
            true;

        $legacyPassword =
            true;
    }
}


/*
|--------------------------------------------------------------------------
| FINAL PASSWORD CHECK
|--------------------------------------------------------------------------
*/

if (
    !$passwordValid
) {

    $_SESSION['error'] =
        'Invalid email or password.';

    header(
        'Location: login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| MIGRATE LEGACY PASSWORD
|--------------------------------------------------------------------------
|
| After successful authentication, immediately convert legacy passwords
| to the modern secure password_hash() format.
|
|--------------------------------------------------------------------------
*/

if (
    $legacyPassword
) {

    try {

        $newHash =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );

        if (
            $newHash !== false
        ) {

            $updatePassword =
                $conn->prepare(
                    "
                    UPDATE {$table}

                    SET password = ?

                    WHERE id = ?

                    LIMIT 1
                    "
                );

            $updatePassword->execute([

                $newHash,

                (int)$user['id']

            ]);

        }

    } catch (
        Throwable $exception
    ) {

        /*
         * Login should still succeed.
         * Password migration failure is logged,
         * but does not block the authenticated user.
         */

        error_log(
            'ExamSphere password migration failed: ' .
            $exception->getMessage()
        );

    }
}


/*
|--------------------------------------------------------------------------
| OPTIONAL PASSWORD REHASH
|--------------------------------------------------------------------------
|
| If an already-hashed password uses an outdated work factor,
| migrate it automatically.
|
|--------------------------------------------------------------------------
*/

if (
    !$legacyPassword
    &&
    $passwordValid
) {

    try {

        if (
            password_needs_rehash(
                $storedPassword,
                PASSWORD_DEFAULT
            )
        ) {

            $newHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            if (
                $newHash !== false
            ) {

                $rehash =
                    $conn->prepare(
                        "
                        UPDATE {$table}

                        SET password = ?

                        WHERE id = ?

                        LIMIT 1
                        "
                    );

                $rehash->execute([

                    $newHash,

                    (int)$user['id']

                ]);

            }

        }

    } catch (
        Throwable $exception
    ) {

        error_log(
            'ExamSphere password rehash failed: ' .
            $exception->getMessage()
        );

    }
}


/*
|--------------------------------------------------------------------------
| LAST LOGIN
|--------------------------------------------------------------------------
*/

try {

    if (
        array_key_exists(
            'last_login',
            $user
        )
    ) {

        $updateLogin =
            $conn->prepare(
                "
                UPDATE {$table}

                SET last_login = NOW()

                WHERE id = ?

                LIMIT 1
                "
            );

        $updateLogin->execute([
            (int)$user['id']
        ]);

    }

} catch (
    Throwable $exception
) {

    /*
     * Do not block successful authentication
     * because of an analytics/login-time update problem.
     */

    error_log(
        'ExamSphere last login update failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| SECURE SESSION REGENERATION
|--------------------------------------------------------------------------
*/

session_regenerate_id(
    true
);


/*
|--------------------------------------------------------------------------
| SESSION USER DATA
|--------------------------------------------------------------------------
*/

$_SESSION['user_id'] =
    (int)(
        $user['id']
        ??
        0
    );


$_SESSION['user_name'] =
    (string)(
        $user['full_name']
        ??
        'User'
    );


$_SESSION['user_email'] =
    (string)(
        $user['email']
        ??
        $email
    );


$_SESSION['user_role'] =
    $role;


/*
|--------------------------------------------------------------------------
| LEGACY SESSION CLEANUP
|--------------------------------------------------------------------------
*/

unset(
    $_SESSION['role'],
    $_SESSION['student_id']
);


/*
|--------------------------------------------------------------------------
| REFRESH ACTIVITY
|--------------------------------------------------------------------------
*/

$_SESSION['last_activity'] =
    time();


/*
|--------------------------------------------------------------------------
| ENSURE CSRF TOKEN EXISTS
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['csrf_token']
    )
) {

    $_SESSION['csrf_token'] =
        bin2hex(
            random_bytes(
                32
            )
        );
}


/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

$_SESSION['success_message'] =
    'Welcome back, ' .
    (string)(
        $user['full_name']
        ??
        'User'
    ) .
    '!';


/*
|--------------------------------------------------------------------------
| ROLE DASHBOARD REDIRECT
|--------------------------------------------------------------------------
*/

header(
    'Location: ' .
    $dashboard
);

exit;

?>