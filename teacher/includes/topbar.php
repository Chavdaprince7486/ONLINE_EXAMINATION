<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

$csrfToken = '';

if (function_exists('csrf_token')) {
    $csrfToken = (string) csrf_token();
} elseif (!empty($_SESSION['csrf_token'])) {
    $csrfToken = (string) $_SESSION['csrf_token'];
}

$baseUrl = defined('BASE_URL')
    ? rtrim((string) BASE_URL, '/') . '/'
    : '/ONLINE_EXAMINATION/';

$teacherId = (int) (
    $_SESSION['user_id']
    ?? $_SESSION['teacher_id']
    ?? 0
);

$teacherName = (string) (
    $_SESSION['user_name']
    ?? $_SESSION['teacher_name']
    ?? 'Teacher'
);

$teacherPhoto = '';

if ($teacherId > 0 && isset($conn) && $conn instanceof PDO) {
    try {
        $teacherPhotoStmt = $conn->prepare(
            'SELECT full_name, profile_photo FROM teachers WHERE id = ? LIMIT 1'
        );
        $teacherPhotoStmt->execute([$teacherId]);
        $teacherData = $teacherPhotoStmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($teacherData)) {
            if (!empty($teacherData['full_name'])) {
                $teacherName = (string) $teacherData['full_name'];
            }

            $teacherPhoto = trim((string) ($teacherData['profile_photo'] ?? ''));
        }
    } catch (Throwable $exception) {
        error_log(
            'ExamSphere teacher topbar profile load failed: ' .
            $exception->getMessage()
        );
    }
}

$teacherInitials = 'T';

$nameParts = preg_split('/\s+/', trim($teacherName));

if (is_array($nameParts) && count($nameParts) >= 2) {
    $teacherInitials = strtoupper(
        mb_substr((string) $nameParts[0], 0, 1) .
        mb_substr((string) $nameParts[count($nameParts) - 1], 0, 1)
    );
} elseif (!empty($teacherName)) {
    $teacherInitials = strtoupper(
        mb_substr(trim($teacherName), 0, 2)
    );
}

$teacherPhotoUrl = '';

if ($teacherPhoto !== '') {
    $safePhoto = str_replace(['\\', '"', "'"], '', $teacherPhoto);
    $safePhoto = ltrim($safePhoto, '/');

    $candidateAbsolute = __DIR__ . '/../../uploads/teachers/' . basename($safePhoto);

    if (is_file($candidateAbsolute)) {
        $version = (string) @filemtime($candidateAbsolute);
        $teacherPhotoUrl = $baseUrl . 'uploads/teachers/' . rawurlencode(basename($safePhoto));

        if ($version !== '') {
            $teacherPhotoUrl .= '?v=' . rawurlencode($version);
        }
    }
}
?>

<style>
/* Teacher account block aligned with the existing premium-teacher.css theme. */
.teacher-account-wrap{position:relative;display:flex;align-items:center;gap:14px;margin-left:auto;}
.teacher-account-wrap .teacher-bell{width:50px;height:50px;min-width:50px;}
.teacher-profile-menu{position:relative;}
.teacher-profile-button{display:flex;align-items:center;gap:11px;padding:6px 10px 6px 7px;border:1px solid #ebe4d8;border-radius:18px;background:#fff;color:#2f241e;cursor:pointer;min-height:62px;}
.teacher-profile-button:hover{background:#fbfaf6;}
.teacher-profile-button .teacher-avatar{width:48px;height:48px;min-width:48px;overflow:hidden;display:grid;place-items:center;border-radius:50%;color:#fff;background:#704019;font-size:15px;font-weight:700;}
.teacher-profile-button .teacher-avatar img{width:100%;height:100%;display:block;object-fit:cover;}
.teacher-profile-text{text-align:left;min-width:110px;}
.teacher-profile-text strong{display:block;color:#2f241e;font-size:14px;line-height:1.25;max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.teacher-profile-text small{display:block;color:#738238;font-size:11px;margin-top:2px;}
.teacher-profile-button>i{color:#7d746c;font-size:10px;transition:transform .2s ease;}
.teacher-profile-button[aria-expanded="true"]>i{transform:rotate(180deg);}
.teacher-profile-dropdown{position:absolute;right:0;top:calc(100% + 10px);width:220px;padding:8px;border:1px solid #e7dfd3;border-radius:18px;background:#fff;box-shadow:0 18px 42px rgba(62,39,35,.14);z-index:1000;}
.teacher-profile-dropdown[hidden],.es-notification-dropdown[hidden]{display:none!important;}
.teacher-profile-dropdown a{display:flex;align-items:center;gap:11px;padding:11px 12px;border-radius:11px;color:#5b3a22;text-decoration:none;font-size:13px;font-weight:600;}
.teacher-profile-dropdown a:hover{background:#f6f1e8;}
.teacher-profile-dropdown a i{width:18px;text-align:center;color:#6d7e20;}
.teacher-profile-dropdown .teacher-profile-divider{height:1px;margin:6px 4px;background:#ece5db;}
.teacher-profile-dropdown .logout-link{color:#c94d45;}
.teacher-profile-dropdown .logout-link i{color:#c94d45;}
@media(max-width:1000px){.teacher-profile-text{min-width:90px}.teacher-profile-text strong{max-width:110px;}.teacher-account-wrap{gap:9px;}}
@media(max-width:800px){.teacher-account-wrap{gap:8px}.teacher-profile-button{padding:5px;border-radius:50%;min-height:44px}.teacher-profile-button .teacher-avatar{width:42px;height:42px;min-width:42px}.teacher-profile-text,.teacher-profile-button>i{display:none}.teacher-profile-dropdown{right:0;width:205px} .teacher-account-wrap .teacher-bell{width:42px;height:42px;min-width:42px;}}
</style>

<header class="teacher-topbar">

    <div class="teacher-topbar-left">

        <button
            type="button"
            class="teacher-menu-button"
            id="teacherMobileMenu"
            aria-label="Open teacher menu"
        >
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="teacher-page-title">
            <?php if (!empty($pageTitle)): ?>
               
            <?php else: ?>
               
            <?php endif; ?>
        </div>

    </div>

    <div class="teacher-account-wrap">

        <div
            class="es-notification-wrap"
            data-notification-widget
            data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        >
            <button
                type="button"
                class="teacher-bell es-notification-button"
                id="esNotificationBell"
                aria-label="Notifications"
                aria-expanded="false"
                aria-haspopup="true"
            >
                <i class="fa-regular fa-bell"></i>
                <b
                    class="es-notification-badge"
                    id="esNotificationBadge"
                    hidden
                >0</b>
            </button>

            <div
                class="es-notification-dropdown"
                id="esNotificationDropdown"
                hidden
            >
                <div class="es-notification-header">
                    <div>
                        <span>EXAMSPHERE</span>
                        <strong>Notifications</strong>
                    </div>
                    <button
                        type="button"
                        class="es-notification-mark-all"
                        id="esNotificationMarkAll"
                    >
                        Mark all as read
                    </button>
                </div>
                <div
                    class="es-notification-list"
                    id="esNotificationList"
                    aria-live="polite"
                ></div>
            </div>
        </div>

        <div class="teacher-profile-menu">

            <button
                type="button"
                class="teacher-profile-button"
                id="teacherProfileButton"
                aria-expanded="false"
                aria-haspopup="true"
            >
                <span class="teacher-avatar">
                    <?php if ($teacherPhotoUrl !== ''): ?>
                        <img
                            src="<?= htmlspecialchars($teacherPhotoUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                            alt="Teacher profile photo"
                            onerror="this.style.display='none';this.nextElementSibling.hidden=false;"
                        >
                        <span hidden><?= htmlspecialchars($teacherInitials, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <?php else: ?>
                        <?= htmlspecialchars($teacherInitials, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    <?php endif; ?>
                </span>

                <span class="teacher-profile-text">
                    <strong>
                        <?= htmlspecialchars($teacherName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </strong>
                    <small>Teacher</small>
                </span>

                <i class="fa-solid fa-chevron-down"></i>
            </button>

            <div
                class="teacher-profile-dropdown"
                id="teacherProfileDropdown"
                hidden
            >
                <a href="<?= htmlspecialchars($baseUrl . 'teacher/profile.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <i class="fa-regular fa-user"></i>
                    <span>My Profile</span>
                </a>

                <a href="<?= htmlspecialchars($baseUrl . 'teacher/settings.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <i class="fa-solid fa-gear"></i>
                    <span>Settings</span>
                </a>

                <a href="<?= htmlspecialchars($baseUrl . 'teacher/change_password.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <i class="fa-solid fa-key"></i>
                    <span>Change Password</span>
                </a>

                <div class="teacher-profile-divider"></div>

                <a
                    class="logout-link"
                    href="<?= htmlspecialchars($baseUrl . 'auth/logout.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                >
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </div>

        </div>

    </div>

</header>

<link
    rel="stylesheet"
    href="<?= htmlspecialchars($baseUrl . 'assets/css/notifications.css', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
>

<script>
window.EXAMSPHERE_NOTIFICATION_BASE =
    <?= json_encode($baseUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>

<script
    src="<?= htmlspecialchars($baseUrl . 'assets/js/notifications.js', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
    defer
></script>

<script>
(function () {
    'use strict';

    const profileButton = document.getElementById('teacherProfileButton');
    const profileDropdown = document.getElementById('teacherProfileDropdown');

    function closeProfileMenu() {
        if (!profileButton || !profileDropdown) return;
        profileDropdown.hidden = true;
        profileButton.setAttribute('aria-expanded', 'false');
    }

    if (profileButton && profileDropdown) {
        profileButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const isOpen = !profileDropdown.hidden;
            profileDropdown.hidden = isOpen;
            profileButton.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
        });

        document.addEventListener('click', function (event) {
            if (
                !profileDropdown.contains(event.target) &&
                !profileButton.contains(event.target)
            ) {
                closeProfileMenu();
            }
        });
    }

    const mobileMenu = document.getElementById('teacherMobileMenu');

    if (mobileMenu) {
        mobileMenu.addEventListener('click', function () {
            document.body.classList.toggle('teacher-sidebar-open');
        });
    }
})();
</script>
