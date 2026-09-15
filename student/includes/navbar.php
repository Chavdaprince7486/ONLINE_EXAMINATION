<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';

$studentId = (int) ($_SESSION['user_id'] ?? 0);
$studentName = (string) (
    $_SESSION['user_name']
    ?? $_SESSION['student_name']
    ?? 'Student'
);

$studentBaseUrl = defined('BASE_URL')
    ? rtrim((string) BASE_URL, '/') . '/'
    : '/ONLINE_EXAMINATION/';

/*
 * Load the current profile photo from the database on every request.
 * This keeps the navbar synchronized after a photo change and after refresh.
 */
$studentPhotoUrl = $studentBaseUrl . 'student/assets/images/default-user.png';

if ($studentId > 0 && isset($conn) && $conn instanceof PDO) {
    try {
        $photoStatement = $conn->prepare(
            'SELECT profile_photo FROM students WHERE id = ? LIMIT 1'
        );
        $photoStatement->execute([$studentId]);

        $photoName = trim(
            (string) ($photoStatement->fetchColumn() ?: '')
        );

        if ($photoName !== '') {
            $photoName = basename($photoName);

            $photoFile = dirname(__DIR__, 2)
                . DIRECTORY_SEPARATOR
                . 'uploads'
                . DIRECTORY_SEPARATOR
                . 'students'
                . DIRECTORY_SEPARATOR
                . $photoName;

            if (is_file($photoFile)) {
                $studentPhotoUrl =
                    $studentBaseUrl
                    . 'uploads/students/'
                    . rawurlencode($photoName)
                    . '?v='
                    . (string) filemtime($photoFile);
            }
        }
    } catch (Throwable $exception) {
        error_log(
            'ExamSphere student navbar photo load failed: '
            . $exception->getMessage()
        );
    }
}

$currentPage = basename(
    (string) ($_SERVER['PHP_SELF'] ?? '')
);

$activeClass = static function (string $page) use ($currentPage): string {
    return $currentPage === $page ? 'is-active' : '';
};

$e = static function (mixed $value): string {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};
?>

<!-- Student navigation styles are loaded here so every student page gets the same navbar. -->
<link
    rel="stylesheet"
    href="<?= $e($studentBaseUrl . 'student/assets/css/student-nav.css?v=20260915-navbar') ?>"
>

<header class="student-navbar" id="studentNavbar">

    <div class="student-nav-inner">

        <!-- =====================================================
             BRAND
        ====================================================== -->
        <a
            href="<?= $e($studentBaseUrl . 'student/dashboard.php') ?>"
            class="student-brand"
            aria-label="ExamSphere Student Dashboard"
        >

            <img
                src="<?= $e($studentBaseUrl . 'student/assets/images/exam_logo.png') ?>"
                alt="ExamSphere"
                width="44"
                height="44"
                loading="eager"
                decoding="async"
            >

            <span class="student-brand-copy">

                <strong>
                    ExamSphere
                </strong>

                <small>
                    Smart • Secure • Success
                </small>

            </span>

        </a>


        <!-- =====================================================
             NAV MENU
        ====================================================== -->
        <nav
            class="student-nav-menu"
            aria-label="Student navigation"
        >

            <a
                href="<?= $e($studentBaseUrl . 'student/dashboard.php') ?>"
                class="<?= $e($activeClass('dashboard.php')) ?>"
            >
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>

            <a
                href="<?= $e($studentBaseUrl . 'student/practice_exams.php') ?>"
                class="<?= $e($activeClass('practice_exams.php')) ?>"
            >
                <i class="fa-solid fa-pen-to-square"></i>
                <span>Practice Exams</span>
            </a>

            <a
                href="<?= $e($studentBaseUrl . 'student/live_exams.php') ?>"
                class="<?= $e($activeClass('live_exams.php')) ?>"
            >
                <i class="fa-solid fa-bolt"></i>
                <span>Live Exams</span>
            </a>

            <a
                href="<?= $e($studentBaseUrl . 'student/my_exams.php') ?>"
                class="<?= $e($activeClass('my_exams.php')) ?>"
            >
                <i class="fa-solid fa-folder-open"></i>
                <span>My Exams</span>
            </a>

            <a
                href="<?= $e($studentBaseUrl . 'student/results.php') ?>"
                class="<?= $e($activeClass('results.php')) ?>"
            >
                <i class="fa-solid fa-chart-column"></i>
                <span>Results</span>
            </a>

            <a
                href="<?= $e($studentBaseUrl . 'student/materials.php') ?>"
                class="<?= $e($activeClass('materials.php')) ?>"
            >
                <i class="fa-solid fa-book-open"></i>
                <span>Materials</span>
            </a>

            <a
                href="<?= $e($studentBaseUrl . 'student/performance.php') ?>"
                class="<?= $e($activeClass('performance.php')) ?>"
            >
                <i class="fa-solid fa-chart-line"></i>
                <span>Performance</span>
            </a>

        </nav>


        <!-- =====================================================
             MOBILE TOGGLE
        ====================================================== -->
        <button
            type="button"
            class="student-nav-toggle"
            id="studentNavToggle"
            aria-label="Toggle navigation"
            aria-expanded="false"
            aria-controls="studentNavMenu"
        >
            <i class="fa-solid fa-bars"></i>
        </button>


        <!-- =====================================================
             RIGHT SIDE
        ====================================================== -->
        <div class="student-nav-actions">

            <?php
            /*
             * Keep the existing global notification widget.
             */
            require __DIR__ . '/../../includes/notification-widget.php';
            ?>


            <details class="student-account">

                <summary
                    aria-label="Student account menu"
                >

                    <img
                        id="studentNavbarPhoto"
                        data-student-photo
                        src="<?= $e($studentPhotoUrl) ?>"
                        alt="<?= $e($studentName) ?>"
                        width="40"
                        height="40"
                        loading="eager"
                        decoding="async"
                    >

                    <span>

                        <strong>
                            <?= $e($studentName) ?>
                        </strong>

                        <small>
                            Student
                        </small>

                    </span>

                    <i class="fa-solid fa-chevron-down"></i>

                </summary>


                <div class="student-account-menu">

                    <a
                        href="<?= $e($studentBaseUrl . 'student/profile.php') ?>"
                    >
                        <i class="fa-regular fa-user"></i>
                        <span>My Profile</span>
                    </a>

                    <a
                        href="<?= $e($studentBaseUrl . 'student/settings.php') ?>"
                    >
                        <i class="fa-solid fa-gear"></i>
                        <span>Settings</span>
                    </a>

                    <a
                        href="<?= $e($studentBaseUrl . 'student/subscriptions.php') ?>"
                    >
                        <i class="fa-solid fa-crown"></i>
                        <span>Subscription</span>
                    </a>

                    <a
                        href="<?= $e($studentBaseUrl . 'student/logout.php') ?>"
                        class="is-danger"
                    >
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Logout</span>
                    </a>

                </div>

            </details>

        </div>

    </div>

</header>

<script>
(function () {
    'use strict';

    const navbar = document.getElementById('studentNavbar');
    const toggle = document.getElementById('studentNavToggle');
    const menu = navbar?.querySelector('.student-nav-menu');

    if (!navbar || !toggle || !menu) {
        return;
    }

    menu.id = 'studentNavMenu';
    toggle.setAttribute('aria-controls', 'studentNavMenu');

    toggle.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        const isOpen = navbar.classList.toggle('is-open');
        toggle.setAttribute(
            'aria-expanded',
            isOpen ? 'true' : 'false'
        );

        const icon = toggle.querySelector('i');
        if (icon) {
            icon.className = isOpen
                ? 'fa-solid fa-xmark'
                : 'fa-solid fa-bars';
        }
    });

    document.addEventListener('click', function (event) {
        if (!navbar.contains(event.target)) {
            navbar.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');

            const icon = toggle.querySelector('i');
            if (icon) {
                icon.className = 'fa-solid fa-bars';
            }
        }
    });
})();
</script>
