<?php

declare(strict_types=1);

session_start();

header(
    'Content-Type: application/json; charset=UTF-8'
);

require_once '../../config/config.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {

    echo json_encode([

        'status' =>
            'error',

        'message' =>
            'Unauthorized Access'

    ]);

    exit;
}

$csrfToken =
    trim(
        (string) (
            $_POST['csrf_token']
            ?? ''
        )
    );

if (!verify_csrf_token($csrfToken)) {
    http_response_code(419);
    echo json_encode([
        'status' => 'error',
        'message' => 'Security token validation failed.'
    ]);
    exit;
}

$studentId =
    (int) $_SESSION['user_id'];

$notificationId =
    filter_input(
        INPUT_POST,
        'notification_id',
        FILTER_VALIDATE_INT
    );

if (
    !$notificationId ||
    $notificationId <= 0
) {

    echo json_encode([

        'status' =>
            'error',

        'message' =>
            'Notification ID Missing'

    ]);

    exit;
}

try {

    $update = $conn->prepare(
        "UPDATE notifications
         SET
             is_read = 1,
             read_at = NOW()
         WHERE id = ?
           AND recipient_type = 'Student'
           AND recipient_id = ?"
    );

    $update->execute([

        $notificationId,
        $studentId

    ]);

    if (
        $update->rowCount() === 0
    ) {

        echo json_encode([

            'status' =>
                'success',

            'message' =>
                'Already read or not found'

        ]);

        exit;
    }

    echo json_encode([

        'status' =>
            'success',

        'message' =>
            'Notification Updated'

    ]);

} catch (Throwable $exception) {

    error_log(
        'Student notification update failed: '
        . $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode([

        'status' =>
            'error',

        'message' =>
            'Unable to update notification.'

    ]);
}