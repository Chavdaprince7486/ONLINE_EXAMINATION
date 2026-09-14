<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {
    header('Location: ../auth/login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$studentId = (int) $_SESSION['user_id'];
$studentName = (string) ($_SESSION['user_name'] ?? 'Student');
$studentPhoto = '../assets/images/default-user.png';

try {
    $statement = $conn->prepare('SELECT full_name, profile_photo FROM students WHERE id = ? AND status = \'Active\' LIMIT 1');
    $statement->execute([$studentId]);
    $studentNav = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$studentNav) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool)($params['secure'] ?? false),
                (bool)($params['httponly'] ?? true)
            );
        }

        session_destroy();

        header('Location: ../auth/login.php');
        exit;
    }

    $studentName = trim(
        (string)($studentNav['full_name'] ?? $studentName)
    ) ?: 'Student';

    $photoName = trim(
        (string)($studentNav['profile_photo'] ?? '')
    );

    $photoFile =
        dirname(__DIR__, 2) .
        '/uploads/students/' .
        $photoName;

    if (
        $photoName !== '' &&
        is_file($photoFile)
    ) {
        $studentPhoto =
            '../uploads/students/' .
            rawurlencode($photoName);
    }

} catch (Throwable $exception) {

    error_log(
        'Student navbar load failed: ' .
        $exception->getMessage()
    );
}

$navItems = [
    ['dashboard.php', 'fa-house', 'Dashboard'],
    ['practice_exams.php', 'fa-file-pen', 'My Exams'],
    ['results.php', 'fa-chart-column', 'Results'],
    ['performance.php', 'fa-chart-line', 'Performance'],
    ['live_exams.php', 'fa-tower-broadcast', 'Live Exams'],
    ['materials.php', 'fa-book-open', 'Materials'],
    ['leaderboard.php', 'fa-ranking-star', 'Leaderboard'],
    ['subscriptions.php', 'fa-gem', 'Plans'],
];
?>

<link
    rel="stylesheet"
    href="assets/css/student-nav.css"
>

<script>
window.EXAMSPHERE_CSRF_TOKEN =
    <?php
    echo json_encode(
        csrf_token(),
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    );
    ?>;
</script>

<script
    src="assets/js/student-nav.js"
    defer
></script>


<nav
    class="student-navbar"
    aria-label="Student navigation"
>

    <div class="student-nav-inner">


        <a
            class="student-brand"
            href="dashboard.php"
            aria-label="ExamSphere Dashboard"
        >

            <img
                src="../assets/images/exam_logo.png"
                alt="ExamSphere"
            >

            <span
                class="student-brand-copy"
            >

                <strong>
                    ExamSphere
                </strong>

                <small>
                    Smart · Secure · Success
                </small>

            </span>

        </a>


        <button
            class="student-nav-toggle"
            type="button"
            aria-expanded="false"
            aria-controls="studentNavMenu"
            aria-label="Open navigation"
        >

            <i
                class="fa-solid fa-bars"
            ></i>

        </button>


        <div
            class="student-nav-menu"
            id="studentNavMenu"
        >

            <?php foreach (
                $navItems
                as [$page, $icon, $label]
            ): ?>

                <a
                    class="<?= $currentPage === $page
                        ? 'is-active'
                        : '' ?>"
                    href="<?= htmlspecialchars(
                        $page,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                    <i
                        class="fa-solid <?= htmlspecialchars(
                            $icon,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    ></i>

                    <span>
                        <?= htmlspecialchars(
                            $label,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                </a>

            <?php endforeach; ?>


            <a
                href="../index.php"
            >

                <i
                    class="fa-solid fa-house-chimney"
                ></i>

                <span>
                    Home
                </span>

            </a>

        </div>


        <div
            class="student-nav-actions"
        >


            <!-- =================================================
                 NOTIFICATION
            ================================================== -->

            <button
                class="student-notification-btn"
                type="button"
                id="notificationBell"
                aria-label="Notifications"
                aria-expanded="false"
            >

                <i
                    class="fa-regular fa-bell"
                ></i>

                <span
                    class="notification-count"
                    id="notificationCount"
                    hidden
                >
                    0
                </span>

            </button>


            <div
                class="student-notification-panel"
                id="notificationDropdown"
                hidden
            >

                <div
                    class="student-notification-head"
                >

                    <div>

                        <strong>
                            Notifications
                        </strong>

                        <small>
                            Latest account updates
                        </small>

                    </div>

                    <a
                        href="profile.php"
                    >
                        Profile
                    </a>

                </div>


                <div
                    class="student-notification-body"
                    id="notificationBody"
                >

                    <div
                        class="
                            student-notification-empty
                        "
                    >

                        <i
                            class="
                                fa-solid
                                fa-spinner
                                fa-spin
                            "
                        ></i>

                        Loading…

                    </div>

                </div>

            </div>


            <!-- =================================================
                 STUDENT ACCOUNT
            ================================================== -->

            <details
                class="student-account"
            >

                <summary>

                    <img
                        src="<?= htmlspecialchars(
                            $studentPhoto,
                            ENT_QUOTES |
                            ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>"
                        alt="Student profile"
                    >


                    <span>

                        <strong>

                            <?= htmlspecialchars(
                                $studentName,
                                ENT_QUOTES |
                                ENT_SUBSTITUTE,
                                'UTF-8'
                            ) ?>

                        </strong>


                        <small>
                            Student
                        </small>

                    </span>


                    <i
                        class="
                            fa-solid
                            fa-chevron-down
                        "
                    ></i>

                </summary>


                <div
                    class="student-account-menu"
                >


                    <a
                        href="profile.php"
                    >

                        <i
                            class="
                                fa-solid
                                fa-user
                            "
                        ></i>

                        My profile

                    </a>


                    <!-- =================================================
                         ONLY NEW OPTION
                    ================================================== -->

                    <a
                        href="profile.php?change_password=1"
                        data-change-password-link
                    >

                        <i
                            class="
                                fa-solid
                                fa-key
                            "
                        ></i>

                        Change Password

                    </a>


                    <a
                        href="settings.php"
                    >

                        <i
                            class="
                                fa-solid
                                fa-gear
                            "
                        ></i>

                        Settings

                    </a>


                    <a
                        href="../index.php"
                    >

                        <i
                            class="
                                fa-solid
                                fa-house
                            "
                        ></i>

                        Home page

                    </a>


                    <a
                        class="is-danger"
                        href="../auth/logout.php"
                    >

                        <i
                            class="
                                fa-solid
                                fa-right-from-bracket
                            "
                        ></i>

                        Logout

                    </a>


                </div>

            </details>


        </div>

    </div>

</nav>