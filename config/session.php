<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| EXAMSPHERE SESSION CONFIGURATION
|--------------------------------------------------------------------------
|
| Central session bootstrap for the entire application.
|
| Responsibilities:
|
| - Secure session cookies
| - Strict session handling
| - 2-hour inactivity timeout
| - Safe timeout cleanup
| - CSRF token generation
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| CONFIGURATION
|--------------------------------------------------------------------------
*/

const EXAMSPHERE_SESSION_TIMEOUT = 7200;


/*
|--------------------------------------------------------------------------
| START SESSION
|--------------------------------------------------------------------------
*/

if (
    session_status() === PHP_SESSION_NONE
) {

    /*
    |--------------------------------------------------------------------------
    | SECURITY HEADERS
    |--------------------------------------------------------------------------
    */

    if (
        !headers_sent()
    ) {

        header(
            'X-Content-Type-Options: nosniff'
        );

        header(
            'X-Frame-Options: SAMEORIGIN'
        );

        header(
            'Referrer-Policy: strict-origin-when-cross-origin'
        );

        header(
            'Permissions-Policy: geolocation=(), microphone=(), camera=()'
        );

        if (
            (
                !empty(
                    $_SERVER['HTTPS']
                )
                &&
                strtolower(
                    (string) $_SERVER['HTTPS']
                ) !== 'off'
            )
            ||
            (
                isset(
                    $_SERVER['SERVER_PORT']
                )
                &&
                (int) $_SERVER['SERVER_PORT'] === 443
            )
        ) {

            header(
                'Strict-Transport-Security: max-age=31536000; includeSubDomains'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HTTPS DETECTION
    |--------------------------------------------------------------------------
    */

    $isHttps =
        (
            !empty(
                $_SERVER['HTTPS']
            )
            &&
            strtolower(
                (string)
                $_SERVER['HTTPS']
            ) !== 'off'
        )
        ||
        (
            isset(
                $_SERVER['SERVER_PORT']
            )
            &&
            (int)
            $_SERVER['SERVER_PORT'] === 443
        );


    /*
    |--------------------------------------------------------------------------
    | PHP SESSION SETTINGS
    |--------------------------------------------------------------------------
    |
    | Set these before session_start().
    |
    */

    ini_set(
        'session.use_strict_mode',
        '1'
    );


    ini_set(
        'session.use_only_cookies',
        '1'
    );


    ini_set(
        'session.use_trans_sid',
        '0'
    );


    ini_set(
        'session.cookie_httponly',
        '1'
    );


    ini_set(
        'session.cookie_secure',
        $isHttps
            ? '1'
            : '0'
    );


    ini_set(
        'session.cookie_samesite',
        'Lax'
    );


    /*
    |--------------------------------------------------------------------------
    | SESSION GARBAGE COLLECTION
    |--------------------------------------------------------------------------
    |
    | Keep server-side session lifetime aligned with the application's
    | inactivity timeout.
    |
    */

    ini_set(
        'session.gc_maxlifetime',
        (string)
        EXAMSPHERE_SESSION_TIMEOUT
    );


    /*
    |--------------------------------------------------------------------------
    | COOKIE PARAMETERS
    |--------------------------------------------------------------------------
    */

    session_set_cookie_params([

        'lifetime' =>
            0,

        'path' =>
            '/',

        'domain' =>
            '',

        'secure' =>
            $isHttps,

        'httponly' =>
            true,

        'samesite' =>
            'Lax'

    ]);


    /*
    |--------------------------------------------------------------------------
    | START
    |--------------------------------------------------------------------------
    */

    session_start();

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATED PAGE CACHE POLICY
    |--------------------------------------------------------------------------
    */

    if (
        !headers_sent()
    ) {

        header(
            'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
        );

        header(
            'Pragma: no-cache'
        );
    }
}


/*
|--------------------------------------------------------------------------
| TIME
|--------------------------------------------------------------------------
*/

$now =
    time();


/*
|--------------------------------------------------------------------------
| AUTHENTICATED SESSION DETECTION
|--------------------------------------------------------------------------
*/

$isAuthenticated =
    !empty(
        $_SESSION['user_id']
    )
    &&
    !empty(
        $_SESSION['user_role']
    );


/*
|--------------------------------------------------------------------------
| INACTIVITY TIMEOUT
|--------------------------------------------------------------------------
*/

$sessionExpired =
    false;


if (
    $isAuthenticated
    &&
    isset(
        $_SESSION['last_activity']
    )
) {

    $lastActivity =
        (int)
        $_SESSION[
            'last_activity'
        ];


    /*
    |--------------------------------------------------------------------------
    | Invalid timestamp protection
    |--------------------------------------------------------------------------
    */

    if (
        $lastActivity <= 0
    ) {

        $sessionExpired =
            true;

    } elseif (
        (
            $now -
            $lastActivity
        )
        >
        EXAMSPHERE_SESSION_TIMEOUT
    ) {

        $sessionExpired =
            true;
    }
}


/*
|--------------------------------------------------------------------------
| DESTROY EXPIRED SESSION
|--------------------------------------------------------------------------
*/

if (
    $sessionExpired
) {

    /*
    |--------------------------------------------------------------------------
    | Preserve the fact that the user timed out.
    |--------------------------------------------------------------------------
    */

    $expiredMessage =
        'Your session expired due to inactivity. Please log in again.';


    /*
    |--------------------------------------------------------------------------
    | Clear server-side session values
    |--------------------------------------------------------------------------
    */

    $_SESSION = [];


    /*
    |--------------------------------------------------------------------------
    | Remove session cookie
    |--------------------------------------------------------------------------
    */

    if (
        (bool)
        ini_get(
            'session.use_cookies'
        )
    ) {

        $cookieParams =
            session_get_cookie_params();


        setcookie(

            session_name(),

            '',

            [

                'expires' =>
                    $now - 42000,

                'path' =>
                    $cookieParams['path']
                    ?? '/',

                'domain' =>
                    $cookieParams['domain']
                    ?? '',

                'secure' =>
                    (bool)
                    (
                        $cookieParams['secure']
                        ?? false
                    ),

                'httponly' =>
                    (bool)
                    (
                        $cookieParams['httponly']
                        ?? true
                    ),

                'samesite' =>
                    $cookieParams['samesite']
                    ?? 'Lax'

            ]

        );
    }


    /*
    |--------------------------------------------------------------------------
    | Destroy old session
    |--------------------------------------------------------------------------
    */

    session_destroy();


    /*
    |--------------------------------------------------------------------------
    | Start a completely new session
    |--------------------------------------------------------------------------
    */

    session_start();


    /*
    |--------------------------------------------------------------------------
    | Mark timeout message
    |--------------------------------------------------------------------------
    */

    $_SESSION[
        'error'
    ] =
        $expiredMessage;


    /*
    |--------------------------------------------------------------------------
    | Reset activity
    |--------------------------------------------------------------------------
    */

    $_SESSION[
        'last_activity'
    ] =
        $now;
}


/*
|--------------------------------------------------------------------------
| UPDATE ACTIVITY
|--------------------------------------------------------------------------
|
| Every valid request refreshes the inactivity timer.
|
*/

$_SESSION[
    'last_activity'
] =
    $now;


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['csrf_token']
    )
) {

    $_SESSION[
        'csrf_token'
    ] =
        bin2hex(
            random_bytes(32)
        );
}


/*
|--------------------------------------------------------------------------
| EXAM-SPECIFIC CSRF TOKEN
|--------------------------------------------------------------------------
|
| This is intentionally NOT created automatically here.
|
| The exam flow should generate its own token when the student enters
| an active examination.
|
*/


/*
|--------------------------------------------------------------------------
| GET CSRF TOKEN
|--------------------------------------------------------------------------
*/

function csrf_token(): string
{
    return (string) (
        $_SESSION[
            'csrf_token'
        ]
        ?? ''
    );
}


/*
|--------------------------------------------------------------------------
| CSRF INPUT FIELD
|--------------------------------------------------------------------------
*/

function csrf_field(): string
{
    return
        '<input type="hidden" name="csrf_token" value="'
        .
        htmlspecialchars(
            csrf_token(),
            ENT_QUOTES |
            ENT_SUBSTITUTE,
            'UTF-8'
        )
        .
        '">';
}


/*
|--------------------------------------------------------------------------
| VERIFY CSRF TOKEN
|--------------------------------------------------------------------------
*/

function verify_csrf_token(
    ?string $token
): bool {

    if (
        $token === null
        ||
        $token === ''
    ) {

        return false;
    }


    $sessionToken =
        (string) (
            $_SESSION[
                'csrf_token'
            ]
            ?? ''
        );


    if (
        $sessionToken === ''
    ) {

        return false;
    }


    return hash_equals(
        $sessionToken,
        $token
    );
}