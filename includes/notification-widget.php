<?php

declare(strict_types=1);

if (
    session_status() !== PHP_SESSION_ACTIVE
) {
    session_start();
}

require_once __DIR__ . '/../config/functions.php';


$notificationCsrf =
    function_exists('csrf_token')
        ? (string) csrf_token()
        : (string) (
            $_SESSION['csrf_token']
            ?? ''
        );


$notificationBaseUrl =
    defined('BASE_URL')
        ? rtrim(
            (string) BASE_URL,
            '/'
        ) . '/'
        : '/ONLINE_EXAMINATION/';


$notificationRole =
    ucfirst(
        strtolower(
            (string) (
                $_SESSION['user_role']
                ?? ''
            )
        )
    );

?>

<link
    rel="stylesheet"
    href="<?= htmlspecialchars(
        $notificationBaseUrl .
        'assets/css/notifications.css',
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    ) ?>"
>

<div
    class="es-notification-wrap"
    data-notification-widget
    data-role="<?= htmlspecialchars(
        $notificationRole,
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    ) ?>"
    data-csrf="<?= htmlspecialchars(
        $notificationCsrf,
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    ) ?>"
>

    <button
        type="button"
        class="es-notification-button"
        id="esNotificationBell"
        aria-label="Notifications"
        aria-expanded="false"
    >

        <i class="fa-regular fa-bell"></i>

        <span
            class="es-notification-badge"
            id="esNotificationBadge"
            hidden
        >
            0
        </span>

    </button>


    <div
        class="es-notification-dropdown"
        id="esNotificationDropdown"
        hidden
    >

        <div
            class="es-notification-header"
        >

            <div>

                <span>
                    NOTIFICATIONS
                </span>

                <strong>
                    Your latest updates
                </strong>

            </div>

            <button
                type="button"
                class="es-notification-mark-all"
                id="esNotificationMarkAll"
            >
                Mark all read
            </button>

        </div>


        <div
            class="es-notification-list"
            id="esNotificationList"
        >

            <div
                class="es-notification-loading"
            >

                <i
                    class="fa-solid fa-spinner fa-spin"
                ></i>

                Loading notifications…

            </div>

        </div>

    </div>

</div>


<script>
window.EXAMSPHERE_NOTIFICATION_BASE =
<?= json_encode(
    $notificationBaseUrl,
    JSON_UNESCAPED_SLASHES |
    JSON_UNESCAPED_UNICODE
) ?>;
</script>


<script
    src="<?= htmlspecialchars(
        $notificationBaseUrl .
        'assets/js/notifications.js',
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    ) ?>"
    defer
></script>