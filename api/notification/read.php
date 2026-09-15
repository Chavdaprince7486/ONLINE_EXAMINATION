<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');


/*
|--------------------------------------------------------------------------
| JSON RESPONSE HELPER
|--------------------------------------------------------------------------
*/
function notification_json_response(
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
    notification_json_response(
        401,
        [
            'status' => 'error',
            'message' => 'Unauthorized access.'
        ]
    );
}


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notification_json_response(
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
    notification_json_response(
        403,
        [
            'status' => 'error',
            'message' => 'Security verification failed.'
        ]
    );
}


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/
$notificationId = (int) (
    $_POST['notification_id']
    ?? 0
);

if ($notificationId <= 0) {
    notification_json_response(
        422,
        [
            'status' => 'error',
            'message' => 'Invalid notification ID.'
        ]
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE RECIPIENT TYPE
|--------------------------------------------------------------------------
*/
$recipientType = match ($role) {
    'admin' => 'Admin',
    'teacher' => 'Teacher',
    default => 'Student'
};


try {

    /*
     * IMPORTANT:
     *
     * Notification can ONLY be marked read when it belongs
     * to the logged-in user.
     *
     * This prevents one user from modifying another user's
     * notification.
     */
    $stmt = $conn->prepare(
        'UPDATE notifications
         SET
            is_read = 1,
            read_at = NOW()
         WHERE
            id = ?
            AND recipient_type = ?
            AND recipient_id = ?
            AND is_read = 0'
    );

    $stmt->execute([
        $notificationId,
        $recipientType,
        $userId
    ]);


    notification_json_response(
        200,
        [
            'status' => 'success',
            'message' => 'Notification marked as read.',
            'updated' => $stmt->rowCount()
        ]
    );

} catch (Throwable $e) {

    error_log(
        'ExamSphere notification read failed: ' .
        $e->getMessage()
    );

    notification_json_response(
        500,
        [
            'status' => 'error',
            'message' => 'Unable to update notification.'
        ]
    );
}