<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| CENTRAL SESSION CONFIGURATION
|--------------------------------------------------------------------------
*/

require_once '../config/session.php';


/*
|--------------------------------------------------------------------------
| CLEAR SESSION DATA
|--------------------------------------------------------------------------
*/

$_SESSION = [];


/*
|--------------------------------------------------------------------------
| DELETE SESSION COOKIE
|--------------------------------------------------------------------------
|
| Preserve the same cookie attributes used by config/session.php:
|
| - path
| - domain
| - secure
| - httponly
| - samesite
|
|--------------------------------------------------------------------------
*/

if (
    (bool) ini_get(
        'session.use_cookies'
    )
) {

    $params =
        session_get_cookie_params();


    setcookie(

        session_name(),

        '',

        [

            'expires' =>
                time() - 42000,

            'path' =>
                $params['path']
                ?? '/',

            'domain' =>
                $params['domain']
                ?? '',

            'secure' =>
                (bool) (
                    $params['secure']
                    ?? false
                ),

            'httponly' =>
                (bool) (
                    $params['httponly']
                    ?? true
                ),

            'samesite' =>
                $params['samesite']
                ?? 'Lax'

        ]

    );
}


/*
|--------------------------------------------------------------------------
| DESTROY SERVER SESSION
|--------------------------------------------------------------------------
*/

session_destroy();


/*
|--------------------------------------------------------------------------
| START CLEAN SESSION
|--------------------------------------------------------------------------
|
| We intentionally start a fresh session only to carry the logout
| confirmation to login.php.
|
|--------------------------------------------------------------------------
*/

session_start();


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
| LOGOUT MESSAGE
|--------------------------------------------------------------------------
|
| login.php / the shared auth UI uses success_message.
|
|--------------------------------------------------------------------------
*/

$_SESSION['success_message'] =
    'You have been logged out successfully.';


/*
|--------------------------------------------------------------------------
| CLEAR LOGIN-PERSISTENCE STATE
|--------------------------------------------------------------------------
*/

unset(
    $_SESSION['login_attempts'],
    $_SESSION['login_attempt_window'],
    $_SESSION['login_blocked_until'],
    $_SESSION['exam_csrf_token'],
    $_SESSION['user_id'],
    $_SESSION['user_name'],
    $_SESSION['user_email'],
    $_SESSION['user_role'],
    $_SESSION['last_activity']
);


/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/

header(
    'Location: login.php'
);

exit;