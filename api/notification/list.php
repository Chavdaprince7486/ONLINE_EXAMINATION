<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=UTF-8');
header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);
header('Pragma: no-cache');

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
    http_response_code(401);

    echo json_encode(
        [
            'status' => 'error',
            'message' => 'Unauthorized access.',
            'unread_count' => 0,
            'notifications' => []
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

/**
 * Database recipient type.
 */
$type = match ($role) {
    'admin' => 'Admin',
    'teacher' => 'Teacher',
    default => 'Student'
};

/**
 * Application base URL.
 */
$base = rtrim(
    (string) BASE_URL,
    '/'
) . '/';

/**
 * Create a target URL for notification reference.
 */
function es_notification_link(
    string $role,
    ?string $referenceType,
    ?int $referenceId,
    string $base
): ?string {

    $referenceType = strtolower(
        trim((string) $referenceType)
    );

    $referenceId = (int) $referenceId;

    if (
        $referenceType === '' ||
        $referenceId <= 0
    ) {
        return null;
    }

    return match ($role) {

        'student' => match ($referenceType) {

            'exam' =>
                $base .
                'student/exam-details.php?exam_id=' .
                $referenceId,

            'result' =>
                $base .
                'student/result.php?id=' .
                $referenceId,

            'material' =>
                $base .
                'student/materials.php',

            'subscription' =>
                $base .
                'student/subscriptions.php',

            'subject' =>
                $base .
                'student/dashboard.php',

            'category' =>
                $base .
                'student/dashboard.php',

            'topic' =>
                $base .
                'student/dashboard.php',

            default => null
        },

        'teacher' => match ($referenceType) {

            'exam' =>
                $base .
                'teacher/exams.php',

            'material' =>
                $base .
                'teacher/materials.php',

            'question' =>
                $base .
                'teacher/questions.php',

            'subject' =>
                $base .
                'teacher/dashboard.php',

            'category' =>
                $base .
                'teacher/dashboard.php',

            'topic' =>
                $base .
                'teacher/dashboard.php',

            default => null
        },

        'admin' => match ($referenceType) {

            'subscription' =>
                $base .
                'admin/subscriptions.php',

            'exam' =>
                $base .
                'admin/exams/index.php',

            'material' =>
                $base .
                'admin/materials.php',

            'question' =>
                $base .
                'admin/question-bank/index.php',

            'subject' =>
                $base .
                'admin/subjects/index.php',

            'category' =>
                $base .
                'admin/categories/index.php',

            'topic' =>
                $base .
                'admin/topics/index.php',

            'message' =>
                $base .
                'admin/notifications/index.php',

            default => null
        },

        default => null
    };
}

try {

    /*
     * Unread count.
     */
    $countStmt = $conn->prepare(
        'SELECT COUNT(*)
         FROM notifications
         WHERE recipient_type = ?
           AND recipient_id = ?
           AND is_read = 0'
    );

    $countStmt->execute([
        $type,
        $userId
    ]);

    $unreadCount = (int) $countStmt->fetchColumn();

    /*
     * Latest 20 notifications.
     */
    $notificationStmt = $conn->prepare(
        'SELECT
            id,
            title,
            message,
            notification_type,
            reference_type,
            reference_id,
            is_read,
            created_at,
            read_at
         FROM notifications
         WHERE recipient_type = ?
           AND recipient_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 20'
    );

    $notificationStmt->execute([
        $type,
        $userId
    ]);

    $rows = $notificationStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

    $notifications = [];

    foreach ($rows as $row) {

        $referenceType =
            $row['reference_type'] !== null
                ? (string) $row['reference_type']
                : null;

        $referenceId =
            $row['reference_id'] !== null
                ? (int) $row['reference_id']
                : null;

        $notifications[] = [
            'id' => (int) $row['id'],

            'title' =>
                (string) $row['title'],

            'message' =>
                (string) $row['message'],

            'notification_type' =>
                (string) (
                    $row['notification_type']
                    ?? 'system'
                ),

            'reference_type' =>
                $referenceType,

            'reference_id' =>
                $referenceId,

            'is_read' =>
                (int) $row['is_read'],

            'created_at' =>
                (string) $row['created_at'],

            'read_at' =>
                $row['read_at'] !== null
                    ? (string) $row['read_at']
                    : null,

            'link' =>
                es_notification_link(
                    $role,
                    $referenceType,
                    $referenceId,
                    $base
                )
        ];
    }

    echo json_encode(
        [
            'status' => 'success',
            'unread_count' => $unreadCount,
            'notifications' => $notifications
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

} catch (Throwable $e) {

    error_log(
        'ExamSphere notification list failed: ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode(
        [
            'status' => 'error',
            'message' => 'Unable to load notifications.',
            'unread_count' => 0,
            'notifications' => []
        ],
        JSON_UNESCAPED_UNICODE
    );
}