<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');


function notification_count_response(
    int $statusCode,
    array $data
): never {

    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/
$role = strtolower(
    trim(
        (string) (
            $_SESSION['user_role']
            ?? $_SESSION['role']
            ?? ''
        )
    )
);

$userId = (int) (
    $_SESSION['user_id']
    ?? $_SESSION['id']
    ?? 0
);

if (
    !in_array(
        $role,
        ['admin', 'teacher', 'student'],
        true
    ) ||
    $userId <= 0
) {

    notification_count_response(
        401,
        [
            'status' => 'error',
            'unread_count' => 0
        ]
    );
}


$recipientType = match ($role) {
    'admin' => 'Admin',
    'teacher' => 'Teacher',
    default => 'Student'
};


try {

    $stmt = $conn->prepare(
        'SELECT COUNT(*)
         FROM notifications
         WHERE recipient_type = ?
           AND recipient_id = ?
           AND is_read = 0'
    );

    $stmt->execute([
        $recipientType,
        $userId
    ]);

    $unreadCount = (int) (
        $stmt->fetchColumn()
    );


    notification_count_response(
        200,
        [
            'status' => 'success',
            'unread_count' => $unreadCount
        ]
    );

} catch (Throwable $e) {

    error_log(
        'ExamSphere notification count failed: ' .
        $e->getMessage()
    );

    notification_count_response(
        500,
        [
            'status' => 'error',
            'unread_count' => 0
        ]
    );
}