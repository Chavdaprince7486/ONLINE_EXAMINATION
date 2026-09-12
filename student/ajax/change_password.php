<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/auth.php';

header(
    'Content-Type: application/json; charset=UTF-8'
);

if (
    current_user_id() <= 0 ||
    current_user_role() !== 'student'
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Access Denied',

        'message' =>
            'Please login again.'

    ]);

    exit;
}

$data =
    json_decode(
        file_get_contents(
            'php://input'
        ),
        true
    );

if (
    !is_array($data)
) {

    $data = [];

}

if (
    !verify_csrf_token(
        $data['csrf_token'] ?? null
    )
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Security Check Failed',

        'message' =>
            'Your session security token is invalid. Please refresh the page.'

    ]);

    exit;
}

$currentPassword =
    (string)(
        $data['current_password']
        ?? ''
    );

$newPassword =
    (string)(
        $data['new_password']
        ?? ''
    );

$confirmPassword =
    (string)(
        $data['confirm_password']
        ?? ''
    );

if (
    $currentPassword === '' ||
    $newPassword === '' ||
    $confirmPassword === ''
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Validation Error',

        'message' =>
            'All password fields are required.'

    ]);

    exit;
}

if (
    strlen($newPassword) < 8 ||
    strlen($newPassword) > 72 ||
    !preg_match(
        '/[A-Za-z]/',
        $newPassword
    ) ||
    !preg_match(
        '/[0-9]/',
        $newPassword
    )
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Weak Password',

        'message' =>
            'Password must be 8 to 72 characters and contain at least one letter and one number.'

    ]);

    exit;
}

if (
    $newPassword !==
    $confirmPassword
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Password Mismatch',

        'message' =>
            'New password and confirm password do not match.'

    ]);

    exit;
}

$studentId =
    current_user_id();

try {

    $stmt =
        $conn->prepare(
            'SELECT password
             FROM students
             WHERE id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $studentId
    ]);

    $student =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$student) {

        echo json_encode([

            'status' =>
                'error',

            'title' =>
                'Student Not Found',

            'message' =>
                'Unable to find your account.'

        ]);

        exit;
    }

    if (
        !password_verify(
            $currentPassword,
            (string)
            $student['password']
        )
    ) {

        echo json_encode([

            'status' =>
                'error',

            'title' =>
                'Incorrect Password',

            'message' =>
                'Current password is incorrect.'

        ]);

        exit;
    }

    if (
        password_verify(
            $newPassword,
            (string)
            $student['password']
        )
    ) {

        echo json_encode([

            'status' =>
                'error',

            'title' =>
                'Same Password',

            'message' =>
                'Choose a new password different from the current password.'

        ]);

        exit;
    }

    $hash =
        password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

    if (
        $hash === false
    ) {

        throw new RuntimeException(
            'Password hashing failed.'
        );

    }

    $update =
        $conn->prepare(
            'UPDATE students
             SET password = ?
             WHERE id = ?'
        );

    $update->execute([

        $hash,

        $studentId

    ]);

    echo json_encode([

        'status' =>
            'success',

        'title' =>
            'Password Updated',

        'message' =>
            'Your password has been changed successfully.'

    ]);

} catch (
    Throwable $e
) {

    error_log(
        'Student password update failed: ' .
        $e->getMessage()
    );

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Update Failed',

        'message' =>
            'Unable to update your password right now.'

    ]);

}