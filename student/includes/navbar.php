<?php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/auth.php';

require_role('student');

$current_page =
    basename(
        $_SERVER['PHP_SELF']
    );

$studentPhoto =
    '../assets/images/default-user.png';

if (
    isset($conn)
) {

    try {

        $photoStatement =
            $conn->prepare(
                'SELECT profile_photo
                 FROM students
                 WHERE id = ?
                 LIMIT 1'
            );

        $photoStatement->execute([
            current_user_id()
        ]);

        $photoName =
            (string)
            $photoStatement->fetchColumn();

        if (
            $photoName !== '' &&
            is_file(
                '../uploads/students/' .
                basename($photoName)
            )
        ) {

            $studentPhoto =
                '../uploads/students/' .
                rawurlencode(
                    basename(
                        $photoName
                    )
                );
        }

    } catch (
        Throwable $exception
    ) {

        error_log(
            'Student navbar photo load failed: ' .
            $exception->getMessage()
        );

    }

}

?>

<link
    rel="stylesheet"
    href="assets/css/student-nav.css"
>

<script
    src="assets/js/student-nav.js"
    defer
></script>

<nav class="navbar">

    <div class="nav-left">

        <div class="logo">

            <img
                src="../assets/images/exam_logo.png"
                alt="ExamSphere"
            >

            <div>

                <h2>
                    ExamSphere
                </h2>

                <span>
                    Smart • Secure • Success
                </span>

            </div>

        </div>

    </div>

    <ul class="nav-menu">

        <li
            class="<?= (
                $current_page ===
                'dashboard.php'
            )
                ? 'active'
                : '' ?>"
        >

            <a href="dashboard.php">

                <i class="fa-solid fa-house"></i>

                Dashboard

            </a>

        </li>

        <li
            class="<?= (
                $current_page ===
                'practice_exams.php'
            )
                ? 'active'
                : '' ?>"
        >

            <a href="practice_exams.php">

                <i class="fa-solid fa-file-pen"></i>

                Practice Exams

            </a>

        </li>

        <li
            class="<?= (
                $current_page ===
                'results.php'
            )
                ? 'active'
                : '' ?>"
        >

            <a href="results.php">

                <i class="fa-solid fa-chart-column"></i>

                Results

            </a>

        </li>

        <li
            class="<?= (
                $current_page ===
                'live_exams.php'
            )
                ? 'active'
                : '' ?>"
        >

            <a href="live_exams.php">

                <i class="fa-solid fa-tower-broadcast"></i>

                Live Exams

            </a>

        </li>

        <li
            class="<?= (
                $current_page ===
                'profile.php'
            )
                ? 'active'
                : '' ?>"
        >

            <a href="profile.php">

                <i class="fa-solid fa-user"></i>

                Profile

            </a>

        </li>

        <li
            class="<?= (
                $current_page ===
                'leaderboard.php'
            )
                ? 'active'
                : '' ?>"
        >

            <a href="leaderboard.php">

                <i class="fa-solid fa-ranking-star"></i>

                Leaderboard

            </a>

        </li>

        <li>

            <a href="../index.php">

                <i class="fa-solid fa-house-chimney"></i>

                Home

            </a>

        </li>

        <li
            class="<?= (
                $current_page ===
                'subscriptions.php'
            )
                ? 'active'
                : '' ?>"
        >

            <a href="subscriptions.php">

                <i class="fa-solid fa-gem"></i>

                Plans

            </a>

        </li>

        <li
            class="<?= (
                $current_page ===
                'materials.php'
            )
                ? 'active'
                : '' ?>"
        >

            <a href="materials.php">

                <i class="fa-solid fa-book-open"></i>

                Materials

            </a>

        </li>

    </ul>

    <div class="nav-right">

        <div
            class="notification"
            id="notificationBell"
            title="Notifications"
        >

            <i class="fa-regular fa-bell"></i>

            <span
                class="notification-count"
                id="notificationCount"
            >

                <?= isset(
                    $_SESSION[
                        'notification_count'
                    ]
                )
                    ? (int)
                        $_SESSION[
                            'notification_count'
                        ]
                    : 0 ?>

            </span>

            <div
                class="notification-dropdown"
                id="notificationDropdown"
            >

                <div
                    class="notification-header"
                >

                    <h4>
                        Notifications
                    </h4>

                    <a href="profile.php">
                        My Profile
                    </a>

                </div>

                <div
                    class="notification-body"
                    id="notificationBody"
                >

                    <div
                        class="notification-loading"
                    >

                        <i
                            class="
                                fa-solid
                                fa-spinner
                                fa-spin
                            "
                        ></i>

                        Loading Notifications...

                    </div>

                </div>

            </div>

        </div>

        <details
            class="student-account-menu"
        >

            <summary class="profile">

                <img
                    src="<?= htmlspecialchars(
                        $studentPhoto,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    alt="Profile photo"
                >

                <div>

                    <h4>

                        <?= htmlspecialchars(
                            (string)(
                                $_SESSION[
                                    'user_name'
                                ] ?? 'Student'
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h4>

                    <span>
                        Student
                    </span>

                </div>

                <i
                    class="
                        fa-solid
                        fa-angle-down
                    "
                ></i>

            </summary>

            <div class="account-dropdown">

                <a href="profile.php">

                    <i
                        class="fa-solid fa-user"
                    ></i>

                    My profile

                </a>

                <a href="../index.php">

                    <i
                        class="fa-solid fa-house"
                    ></i>

                    Home page

                </a>

                <a
                    class="account-logout"
                    href="../auth/logout.php"
                    onclick="
                        return confirm(
                            'Are you sure you want to logout?'
                        );
                    "
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

</nav>