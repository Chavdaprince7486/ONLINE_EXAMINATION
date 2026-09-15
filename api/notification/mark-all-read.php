<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');


function notification_response(
    int $statusCode,
    array $data
): never {

    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| AUTH
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

    notification_response(
        401,
        [
            'status' => 'error',
            'message' => 'Unauthorized access.'
        ]
    );
}


/*
|--------------------------------------------------------------------------
| REQUEST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    notification_response(
        405,
        [
            'status' => 'error',
            'message' => 'POST request required.'
        ]
    );
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/
$sessionCsrf = (string) (
    $_SESSION['csrf_token']
    ?? $_SESSION['_csrf_token']
    ?? ''
);

$submittedCsrf = trim(
    (string) (
        $_POST['csrf_token']
        ?? ''
    )
);

if (
    $sessionCsrf === '' ||
    $submittedCsrf === '' ||
    !hash_equals(
        $sessionCsrf,
        $submittedCsrf
    )
) {

    notification_response(
        403,
        [
            'status' => 'error',
            'message' => 'Security verification failed.'
        ]
    );
}


/*
|--------------------------------------------------------------------------
| RECIPIENT TYPE
|--------------------------------------------------------------------------
*/
$recipientType = match ($role) {
    'admin' => 'Admin',
    'teacher' => 'Teacher',
    default => 'Student'
};


try {

    $stmt = $conn->prepare(
        'UPDATE notifications
         SET
            is_read = 1,
            read_at = NOW()
         WHERE
            recipient_type = ?
            AND recipient_id = ?
            AND is_read = 0'
    );

    $stmt->execute([
        $recipientType,
        $userId
    ]);


    /*
     * Return the new unread count so frontend can update
     * without another API request.
     */
    $countStmt = $conn->prepare(
        'SELECT COUNT(*)
         FROM notifications
         WHERE recipient_type = ?
           AND recipient_id = ?
           AND is_read = 0'
    );

    $countStmt->execute([
        $recipientType,
        $userId
    ]);

    $unreadCount = (int) (
        $countStmt->fetchColumn()
    );


    notification_response(
        200,
        [
            'status' => 'success',
            'message' => 'All notifications marked as read.',
            'updated' => $stmt->rowCount(),
            'unread_count' => $unreadCount
        ]
    );

} catch (Throwable $e) {

    error_log(
        'ExamSphere mark all notification read failed: ' .
        $e->getMessage()
    );

    notification_response(
        500,
        [
            'status' => 'error',
            'message' => 'Unable to update notifications.'
        ]
    );
}