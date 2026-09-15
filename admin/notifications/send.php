<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/notifications.php';

$baseUrl = defined('BASE_URL')
    ? rtrim((string) BASE_URL, '/') . '/'
    : '/ONLINE_EXAMINATION/';

function redirect_with_error(
    string $baseUrl,
    string $message
): never {

    $_SESSION['notification_error'] = $message;

    header(
        'Location: ' .
        $baseUrl .
        'admin/notifications/index.php'
    );

    exit;
}

function redirect_with_success(
    string $baseUrl,
    string $message
): never {

    $_SESSION['notification_success'] = $message;

    header(
        'Location: ' .
        $baseUrl .
        'admin/notifications/index.php'
    );

    exit;
}


/*
 * ---------------------------------------------------------
 * AUTHENTICATION
 * ---------------------------------------------------------
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

$adminId = (int) (
    $_SESSION['user_id']
    ?? $_SESSION['admin_id']
    ?? $_SESSION['id']
    ?? 0
);

if (
    $role !== 'admin' ||
    $adminId <= 0
) {

    http_response_code(403);

    exit(
        'You do not have permission to send notifications.'
    );
}


/*
 * ---------------------------------------------------------
 * REQUEST METHOD
 * ---------------------------------------------------------
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: ' .
        $baseUrl .
        'admin/notifications/index.php'
    );

    exit;
}


/*
 * ---------------------------------------------------------
 * CSRF
 * ---------------------------------------------------------
 */

$sessionCsrf =
    (string) (
        $_SESSION['csrf_token']
        ?? $_SESSION['_csrf_token']
        ?? ''
    );

$submittedCsrf =
    trim(
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

    redirect_with_error(
        $baseUrl,
        'Security verification failed. Please refresh the page and try again.'
    );
}


/*
 * ---------------------------------------------------------
 * INPUT
 * ---------------------------------------------------------
 */

$audience =
    strtolower(
        trim(
            (string) (
                $_POST['audience']
                ?? ''
            )
        )
    );

$title =
    trim(
        (string) (
            $_POST['title']
            ?? ''
        )
    );

$message =
    trim(
        (string) (
            $_POST['message']
            ?? ''
        )
    );

$notificationType =
    strtolower(
        trim(
            (string) (
                $_POST['notification_type']
                ?? 'announcement'
            )
        )
    );

$referenceType =
    trim(
        (string) (
            $_POST['reference_type']
            ?? ''
        )
    );

$referenceIdRaw =
    trim(
        (string) (
            $_POST['reference_id']
            ?? ''
        )
    );

$referenceId =
    $referenceIdRaw !== ''
        ? (int) $referenceIdRaw
        : null;


/*
 * ---------------------------------------------------------
 * VALIDATION
 * ---------------------------------------------------------
 */

$allowedAudiences = [
    'students',
    'teachers',
    'both'
];

$allowedTypes = [
    'announcement',
    'important',
    'exam',
    'material',
    'system'
];

$allowedReferences = [
    '',
    'exam',
    'material',
    'subject',
    'category',
    'topic'
];


if (
    !in_array(
        $audience,
        $allowedAudiences,
        true
    )
) {

    redirect_with_error(
        $baseUrl,
        'Please select a valid audience.'
    );
}


if (
    $title === '' ||
    mb_strlen($title) > 180
) {

    redirect_with_error(
        $baseUrl,
        'Notification title is required and must be within 180 characters.'
    );
}


if (
    $message === '' ||
    mb_strlen($message) > 10000
) {

    redirect_with_error(
        $baseUrl,
        'Notification message is required and must be within 10000 characters.'
    );
}


if (
    !in_array(
        $notificationType,
        $allowedTypes,
        true
    )
) {

    redirect_with_error(
        $baseUrl,
        'Invalid notification type.'
    );
}


if (
    !in_array(
        $referenceType,
        $allowedReferences,
        true
    )
) {

    redirect_with_error(
        $baseUrl,
        'Invalid reference type.'
    );
}


if (
    $referenceType !== '' &&
    (
        $referenceId === null ||
        $referenceId <= 0
    )
) {

    redirect_with_error(
        $baseUrl,
        'Reference ID is required when a reference type is selected.'
    );
}


/*
 * ---------------------------------------------------------
 * SEND
 * ---------------------------------------------------------
 */

try {

    $totalSent = 0;

    /*
     * We attach the admin-created message to a generic
     * "message" reference when no academic reference exists.
     *
     * This makes the notification traceable without forcing
     * a fake reference ID.
     */
    $finalReferenceType =
        $referenceType !== ''
            ? $referenceType
            : 'message';

    $finalReferenceId =
        $referenceType !== ''
            ? $referenceId
            : null;


    if ($audience === 'students') {

        $totalSent =
            examsphere_notify_students(
                $conn,
                $title,
                $message,
                $notificationType,
                $finalReferenceType,
                $finalReferenceId
            );

    } elseif ($audience === 'teachers') {

        $totalSent =
            examsphere_notify_teachers(
                $conn,
                $title,
                $message,
                $notificationType,
                $finalReferenceType,
                $finalReferenceId
            );

    } elseif ($audience === 'both') {

        $totalSent =
            examsphere_notify_students_and_teachers(
                $conn,
                $title,
                $message,
                $notificationType,
                $finalReferenceType,
                $finalReferenceId
            );
    }


    if ($totalSent <= 0) {

        redirect_with_error(
            $baseUrl,
            'No notifications were sent. Please check that active recipients exist.'
        );
    }


    /*
     * Optional audit notification to admins:
     *
     * We do NOT automatically send this to the admin who created
     * the message because the sender already knows about it.
     *
     * This keeps the admin notification center clean.
     */


    redirect_with_success(
        $baseUrl,
        'Notification sent successfully to ' .
        number_format($totalSent) .
        ' recipient(s).'
    );

} catch (Throwable $e) {

    error_log(
        'ExamSphere admin notification send failed: ' .
        $e->getMessage()
    );

    redirect_with_error(
        $baseUrl,
        'An unexpected error occurred while sending the notification.'
    );
}