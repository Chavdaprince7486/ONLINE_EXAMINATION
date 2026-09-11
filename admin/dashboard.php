<?php

require_once '../config/config.php';
require_once '../config/auth.php';

require_login('admin');

function dashboard_total($conn, $table) {
    return (int)$conn->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}
$total_students = dashboard_total($conn, 'students');
$total_teachers = dashboard_total($conn, 'teachers');
$total_exams = dashboard_total($conn, 'exams');
$total_results = dashboard_total($conn, 'results');

$page_title = "Admin Dashboard";

include 'includes/header.php';
?>

<div class="dashboard-wrapper">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">

        <?php include 'includes/navbar.php'; ?>

        <div class="dashboard-content">

            <!-- Welcome Row -->

<div class="dashboard-header">

    <div>

        <h1>Welcome back,</h1>

        <h2><?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>

        <div class="title-line"></div>

    </div>

</div>

<!-- Statistics Cards -->

<div class="stats-grid">

    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon students">

                <i class="fa-solid fa-users"></i>

            </div>

            <div>

                <h2><?= $total_students ?></h2>

                <p>Total Students</p>

            </div>

        </div>

        <div class="stat-bottom">

            <span class="growth positive">

                <i class="fa-solid fa-arrow-trend-up"></i>

                Live data

            </span>

            <small>Registered students</small>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon teachers">

                <i class="fa-solid fa-user-graduate"></i>

            </div>

            <div>

                <h2><?= $total_teachers ?></h2>

                <p>Total Teachers</p>

            </div>

        </div>

        <div class="stat-bottom">

            <span class="growth positive">

                <i class="fa-solid fa-arrow-trend-up"></i>

                Live data

            </span>

            <small>Registered teachers</small>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon exams">

                <i class="fa-solid fa-file-lines"></i>

            </div>

            <div>

                <h2><?= $total_exams ?></h2>

                <p>Total Exams</p>

            </div>

        </div>

        <div class="stat-bottom">

            <span class="growth positive">

                <i class="fa-solid fa-arrow-trend-up"></i>

                Live data

            </span>

            <small>Created exams</small>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon results">

                <i class="fa-solid fa-chart-column"></i>

            </div>

            <div>

                <h2><?= $total_results ?></h2>

                <p>Total Results</p>

            </div>

        </div>

        <div class="stat-bottom">

            <span class="growth positive">

                <i class="fa-solid fa-arrow-trend-up"></i>

                Live data

            </span>

            <small>Evaluated attempts</small>

        </div>

    </div>

</div>

<div class="dashboard-grid">

    <!-- Chart Section -->
    <div class="chart-card">

        <div class="card-header">
            <h3>Exam Overview</h3>
            <span>This Month</span>
        </div>

        <canvas id="examChart"></canvas>

    </div>

    <!-- Recent Activities -->
    <div class="activity-card">

        <div class="card-header">
            <h3>Recent Activities</h3>
        </div>

        <ul class="activity-list">

            <li>
                <div class="activity-icon success">
                    <i class="fa-solid fa-user-plus"></i>
                </div>

                <div>
                    <h5>New Student Registered</h5>
                    <small>5 Minutes Ago</small>
                </div>
            </li>

            <li>
                <div class="activity-icon primary">
                    <i class="fa-solid fa-book"></i>
                </div>

                <div>
                    <h5>New Exam Created</h5>
                    <small>20 Minutes Ago</small>
                </div>
            </li>

            <li>
                <div class="activity-icon warning">
                    <i class="fa-solid fa-chart-column"></i>
                </div>

                <div>
                    <h5>Results Published</h5>
                    <small>1 Hour Ago</small>
                </div>
            </li>

            <li>
                <div class="activity-icon danger">
                    <i class="fa-solid fa-right-to-bracket"></i>
                </div>

                <div>
                    <h5>Admin Login</h5>
                    <small>Today 10:15 AM</small>
                </div>
            </li>

        </ul>

    </div>

</div>

<!-- Quick Actions -->

<div class="quick-actions">

    <div class="action-card">
        <i class="fa-solid fa-user-plus"></i>
        <h4>Add Student</h4>
    </div>

    <div class="action-card">
        <i class="fa-solid fa-user-tie"></i>
        <h4>Add Teacher</h4>
    </div>

    <div class="action-card">
        <i class="fa-solid fa-file-circle-plus"></i>
        <h4>Create Exam</h4>
    </div>

    <div class="action-card">
        <i class="fa-solid fa-chart-line"></i>
        <h4>View Reports</h4>
    </div>

</div>

<div class="bottom-grid">

    <!-- Performance -->

    <div class="performance-card">

        <div class="card-header">

            <h3>System Performance</h3>

        </div>

        <div class="progress-item">

            <div class="progress-title">
                <span>Students Registered</span>
                <span>92%</span>
            </div>

            <div class="progress">
                <div class="progress-bar student-progress"></div>
            </div>

        </div>

        <div class="progress-item">

            <div class="progress-title">
                <span>Exam Completion</span>
                <span>78%</span>
            </div>

            <div class="progress">
                <div class="progress-bar exam-progress"></div>
            </div>

        </div>

        <div class="progress-item">

            <div class="progress-title">
                <span>Results Published</span>
                <span>86%</span>
            </div>

            <div class="progress">
                <div class="progress-bar result-progress"></div>
            </div>

        </div>

    </div>

    <!-- Calendar -->

    <div class="calendar-card">

        <div class="card-header">

            <h3>Today's Date</h3>

        </div>

        <div class="calendar-box">

            <h1 id="today-date"></h1>

            <h4 id="today-day"></h4>

            <p id="today-month"></p>

        </div>

    </div>

</div>

        </div>

    </div>

</div>

<?php include 'includes/footer.php'; ?>
