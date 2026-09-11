<?php
session_start();

// Remove all session data
$_SESSION = [];

// Destroy session
session_destroy();

// Remove session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Redirect to Login Page
header("Location: ../auth/login.php");
exit();