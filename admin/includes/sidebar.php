<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ExamSphere Admin Sidebar
|--------------------------------------------------------------------------
*/

$currentPage = basename($_SERVER['PHP_SELF']);
$currentFolder = basename(dirname($_SERVER['PHP_SELF']));

/*
|--------------------------------------------------------------------------
| ACTIVE HELPER
|--------------------------------------------------------------------------
*/

function admin_menu_active(
    array $pages = [],
    array $folders = []
): bool {

    global $currentPage, $currentFolder;

    if (
        in_array(
            $currentPage,
            $pages,
            true
        )
    ) {
        return true;
    }

    if (
        in_array(
            $currentFolder,
            $folders,
            true
        )
    ) {
        return true;
    }

    return false;
}

?>

<aside
    class="sidebar"
    id="sidebar"
>

    <!-- =========================================================
         LOGO
         ========================================================= -->

    <div class="logo-section">

        <a
            href="/ONLINE_EXAMINATION/admin/dashboard.php"
            class="admin-logo-link"
            aria-label="ExamSphere Admin Dashboard"
        >

            <img
                src="../assets/images/exam_logo.png"
                class="logo"
                alt="ExamSphere"
            >

        </a>

        <div class="admin-panel-label">
            ADMIN PANEL
        </div>

    </div>


    <!-- =========================================================
         NAVIGATION
         ========================================================= -->

    <nav
        class="admin-navigation"
        aria-label="Admin navigation"
    >

        <ul class="menu">


            <!-- =================================================
                 DASHBOARD
                 ================================================= -->

            <li
                class="<?= admin_menu_active(
                    ['dashboard.php']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/dashboard.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-house
                        "
                    ></i>

                    <span>
                        Dashboard
                    </span>

                </a>

            </li>


            <!-- =================================================
                 USER MANAGEMENT
                 ================================================= -->

            <li class="menu-section-title">

                USER MANAGEMENT

            </li>


            <!-- STUDENTS -->

            <li
                class="<?= admin_menu_active(
                    [],
                    ['students']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/students/index.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-users
                        "
                    ></i>

                    <span>
                        Students
                    </span>

                </a>

            </li>


            <!-- TEACHERS -->

            <li
                class="<?= admin_menu_active(
                    [],
                    ['teachers']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/teachers/index.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-user-tie
                        "
                    ></i>

                    <span>
                        Teachers
                    </span>

                </a>

            </li>

            <li class="<?= admin_menu_active(['notifications.php']) ? 'active' : '' ?>">

    <a
        href="/ONLINE_EXAMINATION/admin/notifications.php"
    >

        <i class="fa-solid fa-bell"></i>

        <span>
            Notifications
        </span>

    </a>

</li>


            <!-- =================================================
                 ACADEMIC MANAGEMENT
                 ================================================= -->

            <li class="menu-section-title">

                ACADEMIC MANAGEMENT

            </li>


            <!-- CATEGORIES -->

            <li
                class="<?= admin_menu_active(
                    [],
                    ['categories']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/categories/index.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-layer-group
                        "
                    ></i>

                    <span>
                        Categories
                    </span>

                </a>

            </li>


            <!-- SUBJECTS -->

            <li
                class="<?= admin_menu_active(
                    [],
                    ['subjects']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/subjects/index.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-book
                        "
                    ></i>

                    <span>
                        Subjects
                    </span>

                </a>

            </li>


            <!-- TOPICS -->

            <li
                class="<?= admin_menu_active(
                    [],
                    ['topics']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/topics/index.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-diagram-project
                        "
                    ></i>

                    <span>
                        Topics
                    </span>

                </a>

            </li>


            <!-- =================================================
                 EXAM MANAGEMENT
                 ================================================= -->

            <li class="menu-section-title">

                EXAM MANAGEMENT

            </li>


            <!-- EXAMS -->

            <li
                class="<?= admin_menu_active(
                    [],
                    ['exams']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/exams/index.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-file-circle-check
                        "
                    ></i>

                    <span>
                        Exams
                    </span>

                </a>

            </li>


            <!-- QUESTION BANK -->

            <li
                class="<?= admin_menu_active(
                    [],
                    ['question-bank']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/question-bank/index.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-circle-question
                        "
                    ></i>

                    <span>
                        Question Bank
                    </span>

                </a>

            </li>


            <!-- MATERIALS -->

            <li
                class="<?= admin_menu_active(
                    ['materials.php']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/materials.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-folder-open
                        "
                    ></i>

                    <span>
                        Study Materials
                    </span>

                </a>

            </li>


            <!-- =================================================
                 SUBSCRIPTION MANAGEMENT
                 ================================================= -->

            <li class="menu-section-title">

                SUBSCRIPTION MANAGEMENT

            </li>


            <!-- SUBSCRIPTION PLANS -->

            <li
                class="<?= admin_menu_active(
                    [],
                    ['subscription-plans']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/subscription-plans/index.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-gem
                        "
                    ></i>

                    <span>
                        Subscription Plans
                    </span>

                </a>

            </li>


            <!-- STUDENT SUBSCRIPTIONS -->

            <li
                class="<?= admin_menu_active(
                    ['subscriptions.php'],
                    ['subscriptions']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/subscriptions.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-user-check
                        "
                    ></i>

                    <span>
                        Subscription Management
                    </span>

                </a>

            </li>

            <li>

    <a
        href="/ONLINE_EXAMINATION/admin/subscription_requests.php"
    >

        <i
            class="
                fa-solid
                fa-file-invoice
            "
        ></i>

        <span>
            Payment Requests
        </span>

    </a>

</li>

            <!-- =================================================
                 RESULTS & REPORTING
                 ================================================= -->

            <li class="menu-section-title">

                RESULTS & REPORTING

            </li>


            <!-- RESULTS -->

            <li
                class="<?= admin_menu_active(
                    ['results.php']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/results.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-chart-column
                        "
                    ></i>

                    <span>
                        Results
                    </span>

                </a>

            </li>


            <!-- REPORTS -->

            <li
                class="<?= admin_menu_active(
                    ['reports.php']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/reports.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-chart-pie
                        "
                    ></i>

                    <span>
                        Reports
                    </span>

                </a>

            </li>


            <!-- =================================================
                 SYSTEM
                 ================================================= -->

            <li class="menu-section-title">

                SYSTEM

            </li>


            <!-- SETTINGS -->

            <li
                class="<?= admin_menu_active(
                    ['settings.php']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/settings.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-gear
                        "
                    ></i>

                    <span>
                        Settings
                    </span>

                </a>

            </li>


            <!-- PROFILE -->

            <li
                class="<?= admin_menu_active(
                    ['profile.php']
                )
                    ? 'active'
                    : ''
                ?>"
            >

                <a
                    href="/ONLINE_EXAMINATION/admin/profile.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-user
                        "
                    ></i>

                    <span>
                        My Profile
                    </span>

                </a>

            </li>


            <!-- =================================================
                 LOGOUT
                 ================================================= -->

            <li class="menu-logout">

                <a
                    href="/ONLINE_EXAMINATION/auth/logout.php"
                >

                    <i
                        class="
                            fa-solid
                            fa-right-from-bracket
                        "
                    ></i>

                    <span>
                        Logout
                    </span>

                </a>

            </li>


        </ul>

    </nav>

</aside>