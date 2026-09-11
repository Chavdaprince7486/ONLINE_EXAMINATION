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
        'status' => 'error',
        'message' => 'Unauthorized Access'
    ]);

    exit;
}

$studentId = (int) $_SESSION['user_id'];

try {

    /*
    |--------------------------------------------------------------------------
    | UNREAD COUNT
    |--------------------------------------------------------------------------
    */

    $countQuery = $conn->prepare(
        "SELECT COUNT(*)
         FROM notifications
         WHERE recipient_type = 'Student'
           AND recipient_id = ?
           AND is_read = 0"
    );

    $countQuery->execute([
        $studentId
    ]);

    $unreadCount = (int) $countQuery->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | NOTIFICATIONS
    |--------------------------------------------------------------------------
    */

    $listQuery = $conn->prepare(
        "SELECT
            id,
            title,
            message,
            notification_type,
            reference_type,
            reference_id,
            is_read,
            created_at
         FROM notifications
         WHERE recipient_type = 'Student'
           AND recipient_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 8"
    );

    $listQuery->execute([
        $studentId
    ]);

    $notifications = $listQuery->fetchAll(
        PDO::FETCH_ASSOC
    );


    /*
    |--------------------------------------------------------------------------
    | ICON MAP
    |--------------------------------------------------------------------------
    */

    $iconMap = [

        'exam' =>
            'fa-solid fa-file-lines',

        'result' =>
            'fa-solid fa-chart-column',

        'material' =>
            'fa-solid fa-book-open',

        'subscription' =>
            'fa-solid fa-crown',

        'payment' =>
            'fa-solid fa-credit-card',

        'system' =>
            'fa-solid fa-bell'
    ];


    /*
    |--------------------------------------------------------------------------
    | BUILD HTML
    |--------------------------------------------------------------------------
    */

    $html = '';

    foreach ($notifications as $row) {

        $type = strtolower(
            (string) (
                $row['notification_type']
                ?? 'system'
            )
        );

        $icon =
            $iconMap[$type]
            ?? 'fa-solid fa-bell';


        /*
        |--------------------------------------------------------------------------
        | DESTINATION
        |--------------------------------------------------------------------------
        */

        $link = '#';

        if (
            ($row['reference_type'] ?? '') === 'exam'
            &&
            !empty($row['reference_id'])
        ) {

            $link =
                'exam-details.php?exam_id='
                . (int) $row['reference_id'];

        } elseif (
            ($row['reference_type'] ?? '') === 'result'
            &&
            !empty($row['reference_id'])
        ) {

            $link =
                'result.php?id='
                . (int) $row['reference_id'];

        } elseif (
            ($row['reference_type'] ?? '') === 'material'
        ) {

            $link = 'materials.php';

        } elseif (
            ($row['reference_type'] ?? '') === 'subscription'
        ) {

            $link = 'subscriptions.php';
        }


        /*
        |--------------------------------------------------------------------------
        | READ STATUS
        |--------------------------------------------------------------------------
        */

        $isUnread =
            (int) $row['is_read'] === 0;


        /*
        |--------------------------------------------------------------------------
        | HTML
        |--------------------------------------------------------------------------
        */

        $html .=
            '<a'
            . ' class="notification-item '
            . ($isUnread ? 'unread' : '')
            . '"'
            . ' data-id="'
            . (int) $row['id']
            . '"'
            . ' href="'
            . htmlspecialchars(
                $link,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            )
            . '">'

            . '<div class="notification-icon">'
            . '<i class="'
            . htmlspecialchars(
                $icon,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            )
            . '"></i>'
            . '</div>'

            . '<div class="notification-content">'

            . '<h5>'
            . htmlspecialchars(
                (string) $row['title'],
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            )
            . '</h5>'

            . '<p>'
            . htmlspecialchars(
                (string) $row['message'],
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            )
            . '</p>'

            . '<small>'
            . htmlspecialchars(
                date(
                    'd M Y h:i A',
                    strtotime(
                        (string) $row['created_at']
                    )
                ),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            )
            . '</small>'

            . '</div>'

            . '</a>';
    }


    /*
    |--------------------------------------------------------------------------
    | EMPTY STATE
    |--------------------------------------------------------------------------
    */

    if ($html === '') {

        $html =
            '<div class="notification-loading">'
            . 'No Notifications Found'
            . '</div>';
    }


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'status' =>
            'success',

        'unread_count' =>
            $unreadCount,

        'html' =>
            $html

    ]);

} catch (Throwable $exception) {

    error_log(
        'Student notification load failed: '
        . $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode([

        'status' =>
            'error',

        'unread_count' =>
            0,

        'html' =>
            '<div class="notification-loading">'
            . 'Unable to load notifications.'
            . '</div>'

    ]);
}