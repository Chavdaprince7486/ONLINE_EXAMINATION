<?php $teacher_page = basename($_SERVER['PHP_SELF']); ?>
<link rel="stylesheet" href="../../assets/css/portal.css">
<link rel="stylesheet" href="assets/css/premium-teacher.css">
<link rel="stylesheet" href="../../assets/css/accessibility.css">
<link rel="stylesheet" href="assets/css/sidebar-scroll.css">
<script defer src="assets/js/teacher-panel.js"></script>
<?php include __DIR__ . '/topbar.php'; ?>
<aside class="portal-sidebar"><div class="teacher-logo"><img src="../assets/images/exam_logo.png" alt="ExamSphere"></div><p class="portal-role">Faculty Workspace</p>
<nav><a class="<?= $teacher_page === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a><a class="<?= $teacher_page === 'exams.php' ? 'active' : '' ?>" href="exams.php"><i class="fa-solid fa-file-circle-check"></i> My Exams</a><a class="<?= $teacher_page === 'create_exam.php' ? 'active' : '' ?>" href="create_exam.php"><i class="fa-solid fa-file-circle-plus"></i> Create Exam</a><a class="<?= $teacher_page === 'questions.php' ? 'active' : '' ?>" href="questions.php"><i class="fa-solid fa-circle-question"></i> Question Bank</a><a class="<?= $teacher_page === 'import_questions.php' ? 'active' : '' ?>" href="import_questions.php"><i class="fa-solid fa-file-csv"></i> Import CSV</a><a class="<?= $teacher_page === 'materials.php' ? 'active' : '' ?>" href="materials.php"><i class="fa-solid fa-book-open"></i> Materials</a><a class="<?= $teacher_page === 'results.php' ? 'active' : '' ?>" href="results.php"><i class="fa-solid fa-chart-line"></i> Results</a><a class="<?= $teacher_page === 'profile.php' ? 'active' : '' ?>" href="profile.php"><i class="fa-solid fa-user-gear"></i> My Profile</a></nav><a class="portal-logout" href="/ONLINE_EXAMINATION/auth/logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a></aside>

<style>
.portal-sidebar nav a,
.portal-sidebar nav a:hover,
.portal-sidebar nav a:focus,
.portal-sidebar nav a:active,
.portal-sidebar .portal-logout,
.portal-sidebar .portal-logout:hover,
.portal-sidebar .portal-logout:focus,
.portal-sidebar .portal-logout:active{
    text-decoration: none !important;
}

.portal-sidebar nav a{
    display:flex !important;
    align-items:center !important;
    gap:10px !important;
    line-height:1.2 !important;
}

.portal-sidebar nav a i{
    flex:0 0 20px !important;
    text-decoration:none !important;
}

.portal-sidebar .portal-logout{
    display:flex !important;
    align-items:center !important;
    gap:10px !important;
    line-height:1.2 !important;
}
</style>
