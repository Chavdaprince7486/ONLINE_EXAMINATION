<?php

$current_page =
    basename(
        $_SERVER['PHP_SELF']
    );

$current_folder =
    basename(
        dirname(
            $_SERVER['PHP_SELF']
        )
    );

?>

<aside
    class="sidebar"
    id="sidebar"
>

    <div class="logo-section">

        <img
            src="../assets/images/exam_logo.png"
            class="logo"
            alt="ExamSphere"
        >

    </div>

    <ul class="menu">

        <li class="<?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">

            <a href="/ONLINE_EXAMINATION/admin/dashboard.php">

                <i class="fa-solid fa-house"></i>

                <span>
                    Dashboard
                </span>

            </a>

        </li>

        <li class="<?= ($current_folder === 'students') ? 'active' : '' ?>">

            <a href="/ONLINE_EXAMINATION/admin/students/index.php">

                <i class="fa-solid fa-users"></i>

                <span>
                    Students
                </span>

            </a>

        </li>

        <li class="<?= ($current_folder === 'teachers') ? 'active' : '' ?>">

            <a href="/ONLINE_EXAMINATION/admin/teachers/index.php">

                <i class="fa-solid fa-user-tie"></i>

                <span>
                    Teachers
                </span>

            </a>

        </li>

        <li class="<?= ($current_folder === 'categories') ? 'active' : '' ?>">

            <a href="/ONLINE_EXAMINATION/admin/categories/index.php">

                <i class="fa-solid fa-layer-group"></i>

                <span>
                    Categories
                </span>

            </a>

        </li>

        <li class="<?= ($current_folder === 'subjects') ? 'active' : '' ?>">

            <a href="/ONLINE_EXAMINATION/admin/subjects/index.php">

                <i class="fa-solid fa-book"></i>

                <span>
                    Subjects
                </span>

            </a>

        </li>

        <li class="<?= ($current_folder === 'topics') ? 'active' : '' ?>">

            <a href="/ONLINE_EXAMINATION/admin/topics/index.php">

                <i class="fa-solid fa-list-check"></i>

                <span>
                    Topics
                </span>

            </a>

        </li>

        <li class="<?= ($current_page === 'questions.php' || $current_folder === 'question-bank') ? 'active' : '' ?>">

            <a href="/ONLINE_EXAMINATION/admin/questions.php">

                <i class="fa-solid fa-circle-question"></i>

                <span>
                    Question Bank
                </span>

            </a>

        </li>

        <li class="<?= ($current_page === 'exams.php' || $current_folder === 'exams') ? 'active' : '' ?>">

            <a href="/ONLINE_EXAMINATION/admin/exams.php">

                <i class="fa-solid fa-file-lines"></i>

                <span>
                    Exams
                </span>

            </a>

        </li>

        <li class="<?= ($current_page === 'materials.php') ? 'active' : '' ?>">

            <a href="/ONLINE_EXAMINATION/admin/materials.php">

                <i class="fa-solid fa-book-open"></i>

                <span>
                    Materials
                </span>

            </a>

        </li>

        <li class="<?= ($current_page === 'subscriptions.php') ? 'active' : '' ?>">

            <a href="/ONLINE_EXAMINATION/admin/subscriptions.php">

                <i class="fa-solid fa-gem"></i>

                <span>
                    Subscriptions
                </span>

            </a>

        </li>

        <li class="<?= ($current_page === 'results.php') ? 'active' : '' ?>">

            <a href="/ONLINE_EXAMINATION/admin/results.php">

                <i class="fa-solid fa-chart-column"></i>

                <span>
                    Results
                </span>

            </a>

        </li>

        <li class="<?= ($current_page === 'reports.php') ? 'active' : '' ?>">

            <a href="/ONLINE_EXAMINATION/admin/reports.php">

                <i class="fa-solid fa-chart-pie"></i>

                <span>
                    Reports
                </span>

            </a>

        </li>

        <li class="<?= ($current_page === 'settings.php') ? 'active' : '' ?>">

            <a href="/ONLINE_EXAMINATION/admin/settings.php">

                <i class="fa-solid fa-gear"></i>

                <span>
                    Settings
                </span>

            </a>

        </li>

        <li>

            <a href="/ONLINE_EXAMINATION/auth/logout.php">

                <i class="fa-solid fa-right-from-bracket"></i>

                <span>
                    Logout
                </span>

            </a>

        </li>

    </ul>

</aside>