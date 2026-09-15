<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

require_login('teacher');

$teacherId = current_user_id();
$pageTitle = 'Settings';

function teacher_settings_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$teacher = [
    'full_name' => 'Teacher',
    'teacher_code' => '',
    'email' => '',
    'phone' => '',
    'status' => 'Active',
    'profile_photo' => ''
];

try {
    $stmt = $conn->prepare(
        "
        SELECT
            full_name,
            teacher_code,
            email,
            phone,
            mobile,
            status,
            profile_photo
        FROM teachers
        WHERE id = ?
        LIMIT 1
        "
    );

    $stmt->execute([
        $teacherId
    ]);

    $loadedTeacher = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if ($loadedTeacher) {
        $teacher = array_merge(
            $teacher,
            $loadedTeacher
        );
    }
} catch (Throwable $exception) {
    error_log(
        'ExamSphere teacher settings load failed: ' .
        $exception->getMessage()
    );
}

$teacherName = trim(
    (string)$teacher['full_name']
);

$teacherInitials = strtoupper(
    implode(
        '',
        array_slice(
            preg_split(
                '/\s+/u',
                $teacherName
            ) ?: [],
            0,
            2
        )
    )
);

if ($teacherInitials === '') {
    $teacherInitials = 'TE';
}

$photoUrl = '';

if (!empty($teacher['profile_photo'])) {
    $photoUrl =
        '../uploads/teachers/' .
        rawurlencode(
            basename(
                (string)$teacher['profile_photo']
            )
        );
}

$displayPhone = trim(
    (string)($teacher['mobile'] ?? '')
);

if ($displayPhone === '') {
    $displayPhone = trim(
        (string)($teacher['phone'] ?? '')
    );
}

?>
<!doctype html>
<html lang="en">
<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<meta
    name="theme-color"
    content="#f5f5dc"
>

<title>Settings | ExamSphere</title>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
>

<link
    rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
>

<link
    rel="stylesheet"
    href="../assets/css/portal.css"
>

<style>
:root{
    --earth:#5D4037;
    --earth-dark:#3E2723;
    --olive:#556B2F;
    --beige:#F5F5DC;
    --ink:#352B26;
    --muted:#7E746C;
    --line:#E8E1D8;
    --soft:#FBFAF6;
    --white:#fff;
}

.settings-page{
    width:min(1180px, calc(100vw - 30px));
    margin:0 auto;
    padding:8px 0 45px;
}

.settings-hero{
    margin-top:2px;
    background:
        radial-gradient(circle at 92% 15%, rgba(85,107,47,.09), transparent 24%),
        linear-gradient(135deg, #fbfaf6, #f2eee7);
    border:1px solid var(--line);
    border-radius:24px;
    padding:30px 32px;
    box-shadow:0 12px 30px rgba(62,39,35,.05);
}

.settings-kicker{
    display:inline-flex;
    align-items:center;
    gap:8px;
    color:var(--olive);
    text-transform:uppercase;
    letter-spacing:.14em;
    font-size:.68rem;
    font-weight:800;
}

.settings-hero h1{
    margin:9px 0 5px;
    color:var(--earth);
    font-size:2.25rem;
    font-weight:800;
}

.settings-hero p{
    margin:0;
    color:var(--muted);
    font-size:.9rem;
}

.settings-grid{
    display:grid;
    grid-template-columns:minmax(0,1fr) 320px;
    gap:20px;
    margin-top:20px;
}

.settings-card{
    background:var(--white);
    border:1px solid var(--line);
    border-radius:20px;
    overflow:hidden;
    box-shadow:0 10px 25px rgba(62,39,35,.05);
}

.settings-card-head{
    padding:20px 22px 16px;
    border-bottom:1px solid var(--line);
}

.settings-card-head h2{
    margin:0;
    color:var(--earth);
    font-size:1.02rem;
    font-weight:800;
}

.settings-card-head p{
    margin:6px 0 0;
    color:var(--muted);
    font-size:.78rem;
}

.settings-card-body{
    padding:20px 22px;
}

.setting-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:18px;
    padding:16px 0;
    border-bottom:1px solid #eee8e0;
}

.setting-row:first-child{
    padding-top:0;
}

.setting-row:last-child{
    border-bottom:0;
    padding-bottom:0;
}

.setting-copy strong{
    display:block;
    color:var(--earth);
    font-size:.84rem;
    font-weight:800;
}

.setting-copy span{
    display:block;
    margin-top:4px;
    color:var(--muted);
    font-size:.72rem;
    line-height:1.55;
}

.setting-action{
    flex:0 0 auto;
    border:1px solid var(--line);
    background:#fff;
    color:var(--earth);
    border-radius:11px;
    padding:9px 12px;
    text-decoration:none;
    font-size:.72rem;
    font-weight:700;
    transition:.18s ease;
}

.setting-action:hover{
    color:var(--olive);
    border-color:#cfc6ba;
    background:#faf8f3;
}

.form-check.form-switch{
    min-height:auto;
    padding-left:3.15rem;
    margin:0;
}

.form-check-input{
    width:2.6rem !important;
    height:1.35rem;
    margin-left:-3.15rem !important;
    cursor:pointer;
}

.form-check-input:checked{
    background-color:var(--olive);
    border-color:var(--olive);
}

.profile-mini{
    display:flex;
    align-items:center;
    gap:14px;
}

.profile-avatar{
    width:58px;
    height:58px;
    border-radius:50%;
    overflow:hidden;
    display:grid;
    place-items:center;
    color:#fff;
    background:var(--earth);
    font-weight:800;
    flex:0 0 58px;
    border:3px solid #f0e9df;
}

.profile-avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

.profile-copy strong{
    display:block;
    color:var(--earth);
    font-size:.9rem;
    font-weight:800;
}

.profile-copy span{
    display:block;
    margin-top:3px;
    color:var(--muted);
    font-size:.7rem;
}

.info-list{
    display:grid;
    gap:12px;
    margin-top:20px;
}

.info-item{
    display:flex;
    align-items:flex-start;
    gap:10px;
    padding:12px;
    border:1px solid #eee8e0;
    border-radius:13px;
    background:var(--soft);
}

.info-item i{
    width:31px;
    height:31px;
    border-radius:9px;
    display:grid;
    place-items:center;
    color:var(--olive);
    background:#edf2e8;
    flex:0 0 31px;
}

.info-item small{
    display:block;
    color:#988d84;
    font-size:.63rem;
}

.info-item strong{
    display:block;
    margin-top:2px;
    color:var(--earth);
    font-size:.73rem;
    word-break:break-word;
}

.notice-bar{
    display:flex;
    align-items:flex-start;
    gap:10px;
    margin-top:20px;
    padding:14px 16px;
    border:1px solid #e1e8d8;
    border-radius:14px;
    background:#f7faf3;
    color:#59634f;
    font-size:.72rem;
    line-height:1.6;
}

.notice-bar i{
    color:var(--olive);
    margin-top:2px;
}

@media(max-width:900px){
    .settings-grid{
        grid-template-columns:1fr;
    }
}

@media(max-width:600px){
    .settings-page{
        width:min(100%, calc(100vw - 18px));
    }

    .settings-hero{
        padding:22px 20px;
        border-radius:18px;
    }

    .settings-hero h1{
        font-size:1.7rem;
    }

    .settings-card-body,
    .settings-card-head{
        padding-left:17px;
        padding-right:17px;
    }

    .setting-row{
        align-items:flex-start;
    }
}
</style>

</head>

<body class="portal-body">

<div class="portal-layout">

<?php include 'includes/sidebar.php'; ?>

<main class="portal-main">

<div class="settings-page">

<section class="settings-hero">

    <span class="settings-kicker">
        <i class="fa-solid fa-sliders"></i>
        Faculty Account
    </span>

    <h1>
        Settings

    </h1>

    <p>
        Manage your teacher account, notification preferences and quick security options.
    </p>

</section>

<div class="settings-grid">

    <div>

        <section class="settings-card">

            <div class="settings-card-head">
                <h2>Notification Preferences</h2>
                <p>
                    These preferences are saved in this browser for your teacher account.
                </p>
            </div>

            <div class="settings-card-body">

                <div class="setting-row">
                    <div class="setting-copy">
                        <strong>Exam Updates</strong>
                        <span>
                            Show notifications when your examinations are created or updated.
                        </span>
                    </div>

                    <div class="form-check form-switch">
                        <input
                            class="form-check-input teacher-setting-toggle"
                            type="checkbox"
                            id="settingExamUpdates"
                            data-setting-key="exam_updates"
                            checked
                        >
                    </div>
                </div>

                <div class="setting-row">
                    <div class="setting-copy">
                        <strong>Question & Material Updates</strong>
                        <span>
                            Keep question-bank, exam-question and study-material alerts enabled.
                        </span>
                    </div>

                    <div class="form-check form-switch">
                        <input
                            class="form-check-input teacher-setting-toggle"
                            type="checkbox"
                            id="settingContentUpdates"
                            data-setting-key="content_updates"
                            checked
                        >
                    </div>
                </div>

                <div class="setting-row">
                    <div class="setting-copy">
                        <strong>Admin Messages</strong>
                        <span>
                            Receive important messages sent by the administrator.
                        </span>
                    </div>

                    <div class="form-check form-switch">
                        <input
                            class="form-check-input teacher-setting-toggle"
                            type="checkbox"
                            id="settingAdminMessages"
                            data-setting-key="admin_messages"
                            checked
                        >
                    </div>
                </div>

            </div>

        </section>

        <section class="settings-card mt-4">

            <div class="settings-card-head">
                <h2>Account & Security</h2>
                <p>
                    Open the existing ExamSphere pages for account maintenance.
                </p>
            </div>

            <div class="settings-card-body">

                <div class="setting-row">
                    <div class="setting-copy">
                        <strong>Profile</strong>
                        <span>
                            Update your name, contact details, qualification, experience and profile photo.
                        </span>
                    </div>

                    <a
                        class="setting-action"
                        href="profile.php"
                    >
                        <i class="fa-regular fa-user me-1"></i>
                        Open Profile
                    </a>
                </div>

                <div class="setting-row">
                    <div class="setting-copy">
                        <strong>Password</strong>
                        <span>
                            Change your teacher account password securely.
                        </span>
                    </div>

                    <a
                        class="setting-action"
                        href="change_password.php"
                    >
                        <i class="fa-solid fa-key me-1"></i>
                        Change Password
                    </a>
                </div>

            </div>

        </section>

        <div class="notice-bar">
            <i class="fa-solid fa-circle-info"></i>
            <span>
                Notification preferences on this page control the teacher browser experience. Your actual ExamSphere notification delivery remains handled by the central notification system.
            </span>
        </div>

    </div>

    <aside>

        <section class="settings-card">

            <div class="settings-card-head">
                <h2>Your Teacher Account</h2>
                <p>Current account information.</p>
            </div>

            <div class="settings-card-body">

                <div class="profile-mini">

                    <div class="profile-avatar">

                        <?php if ($photoUrl !== ''): ?>

                            <img
                                src="<?= teacher_settings_e($photoUrl) ?>"
                                alt="Teacher profile photo"
                            >

                        <?php else: ?>

                            <?= teacher_settings_e(
                                $teacherInitials
                            ) ?>

                        <?php endif; ?>

                    </div>

                    <div class="profile-copy">
                        <strong>
                            <?= teacher_settings_e(
                                $teacherName
                            ) ?>
                        </strong>
                        <span>
                            <?= teacher_settings_e(
                                $teacher['teacher_code']
                                    ?: 'Teacher Account'
                            ) ?>
                        </span>
                    </div>

                </div>

                <div class="info-list">

                    <div class="info-item">
                        <i class="fa-solid fa-envelope"></i>
                        <div>
                            <small>Email</small>
                            <strong>
                                <?= teacher_settings_e(
                                    $teacher['email'] ?: 'Not set'
                                ) ?>
                            </strong>
                        </div>
                    </div>

                    <div class="info-item">
                        <i class="fa-solid fa-phone"></i>
                        <div>
                            <small>Phone</small>
                            <strong>
                                <?= teacher_settings_e(
                                    $displayPhone ?: 'Not set'
                                ) ?>
                            </strong>
                        </div>
                    </div>

                    <div class="info-item">
                        <i class="fa-solid fa-shield-halved"></i>
                        <div>
                            <small>Account Status</small>
                            <strong>
                                <?= teacher_settings_e(
                                    $teacher['status'] ?: 'Active'
                                ) ?>
                            </strong>
                        </div>
                    </div>

                </div>

            </div>

        </section>

    </aside>

</div>

</div>

</main>

</div>

<script>
(function () {
    'use strict';

    const storageKey = 'examsphereTeacherSettings';

    let state = {};

    try {
        state = JSON.parse(
            localStorage.getItem(storageKey) || '{}'
        );
    } catch (error) {
        state = {};
    }

    document
        .querySelectorAll('.teacher-setting-toggle')
        .forEach(function (toggle) {

            const key =
                toggle.getAttribute('data-setting-key');

            if (Object.prototype.hasOwnProperty.call(state, key)) {
                toggle.checked = state[key] === true;
            }

            toggle.addEventListener('change', function () {

                state[key] = toggle.checked;

                try {
                    localStorage.setItem(
                        storageKey,
                        JSON.stringify(state)
                    );
                } catch (error) {
                    // Ignore browser storage errors.
                }
            });
        });
})();
</script>

</body>
</html>
