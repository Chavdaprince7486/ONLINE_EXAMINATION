<?php
require_once '../config/session.php'; require_once '../config/config.php';
if(empty($_SESSION['user_id'])||($_SESSION['user_role']??'')!=='admin'){header('Location: ../auth/login.php');exit;}
$page_title='Settings | ExamSphere'; $page_css='admin-subjects.css'; include 'includes/header.php';
?>
<div class="dashboard-wrapper"><?php include 'includes/sidebar.php';?><div class="main-content"><?php include 'includes/navbar.php';?><main class="dashboard-content subject-page"><div class="subject-page-heading"><div><span><i class="fa-solid fa-gear"></i> PLATFORM CONTROL</span><h1>Settings</h1><p>Review the active configuration for your ExamSphere installation.</p></div></div><section class="subject-list-card settings-card"><div class="subject-card-heading"><div><span class="mini-kicker">CURRENT CONFIGURATION</span><h2>Platform details</h2></div><i class="fa-solid fa-sliders"></i></div><dl class="settings-list"><div><dt><i class="fa-solid fa-cube"></i> Application</dt><dd><?=htmlspecialchars(SITE_NAME,ENT_QUOTES,'UTF-8')?></dd></div><div><dt><i class="fa-solid fa-database"></i> Database</dt><dd><?=htmlspecialchars(DB_NAME,ENT_QUOTES,'UTF-8')?></dd></div><div><dt><i class="fa-solid fa-credit-card"></i> Payments</dt><dd>
<?php
$paymentMode =
    strtolower(
        trim(
            (string) (
                getenv('PAYMENT_MODE')
                ?: 'test'
            )
        )
    );

echo htmlspecialchars(
    ucfirst(
        $paymentMode
    ) .
    ' mode',
    ENT_QUOTES,
    'UTF-8'
);
?>
</dd></div><div><dt><i class="fa-solid fa-user-shield"></i> Roles</dt><dd>Admin, Teacher, Student</dd></div></dl><p class="settings-note">
<i class="fa-solid fa-circle-info"></i>
Database and payment credentials are managed through the server configuration.
Secrets must never be exposed on public pages.
</p></section></main></div></div><script src="assets/js/admin-shell.js"></script></body></html>
