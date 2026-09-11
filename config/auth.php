<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';

function normalize_user_role(mixed $role): string
{
    $role = strtolower(trim((string)$role));

    return match ($role) {
        'admin' => 'admin',
        'teacher' => 'teacher',
        'student' => 'student',
        default => ''
    };
}

function current_user_id(): int
{
    $value = $_SESSION['user_id'] ?? 0;

    return is_numeric($value)
        ? max(0, (int)$value)
        : 0;
}

function current_user_role(): string
{
    $role = normalize_user_role(
        $_SESSION['user_role'] ?? ''
    );

    if (
        $role === '' &&
        isset($_SESSION['role'])
    ) {
        $role = normalize_user_role(
            $_SESSION['role']
        );

        if ($role !== '') {
            $_SESSION['user_role'] = $role;
        }
    }

    return $role;
}

function dashboard_url_for_role(string $role): string
{
    return match (normalize_user_role($role)) {
        'admin' =>
            BASE_URL . 'admin/dashboard.php',
        'teacher' =>
            BASE_URL . 'teacher/dashboard.php',
        'student' =>
            BASE_URL . 'student/dashboard.php',
        default =>
            BASE_URL . 'auth/login.php'
    };
}

function clear_invalid_auth_session(): void
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['user_name'],
        $_SESSION['user_email'],
        $_SESSION['user_role'],
        $_SESSION['role']
    );
}

function require_login(?string $requiredRole = null): void
{
    $userId = current_user_id();
    $userRole = current_user_role();

    if (
        $userId <= 0 ||
        $userRole === ''
    ) {
        clear_invalid_auth_session();

        header(
            'Location: ' .
            BASE_URL .
            'auth/login.php'
        );

        exit;
    }

    $_SESSION['user_role'] = $userRole;

    if ($requiredRole === null) {
        return;
    }

    $requiredRole =
        normalize_user_role(
            $requiredRole
        );

    if ($requiredRole === '') {
        http_response_code(500);
        exit('Invalid authorization role.');
    }

    if ($userRole !== $requiredRole) {
        header(
            'Location: ' .
            dashboard_url_for_role(
                $userRole
            )
        );

        exit;
    }
}

function require_role(string $role): void
{
    require_login($role);
}

?>
