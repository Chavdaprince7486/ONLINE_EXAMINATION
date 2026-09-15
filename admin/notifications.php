<?php

declare(strict_types=1);

require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../config/notifications.php';

require_login('admin');

$page_title =
    'Notifications | ExamSphere';

$page_css =
    'admin-subjects.css';

$error =
    '';

$message =
    '';


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    try {

        if (
            !verify_csrf_token(
                $_POST['csrf_token'] ?? null
            )
        ) {

            throw new RuntimeException(
                'Security verification failed. Refresh the page and try again.'
            );
        }


        $action =
            trim(
                (string)(
                    $_POST['action'] ?? ''
                )
            );


        $title =
            trim(
                (string)(
                    $_POST['title'] ?? ''
                )
            );


        $body =
            trim(
                (string)(
                    $_POST['message'] ?? ''
                )
            );


        $type =
            trim(
                (string)(
                    $_POST[
                        'notification_type'
                    ] ?? 'system'
                )
            );


        if (
            $title === '' ||
            mb_strlen($title) > 180
        ) {

            throw new RuntimeException(
                'Please enter a valid title.'
            );
        }


        if (
            $body === '' ||
            mb_strlen($body) > 5000
        ) {

            throw new RuntimeException(
                'Please enter a valid message.'
            );
        }


        $sent =
            0;


        if (
            $action === 'both'
        ) {

            $sent =
                examsphere_notify_students_and_teachers(
                    $conn,
                    $title,
                    $body,
                    $type
                );


            $message =
                $sent .
                ' notification(s) sent to all active students and teachers.';

        } elseif (
            $action === 'teachers'
        ) {

            $sent =
                examsphere_notify_teachers(
                    $conn,
                    $title,
                    $body,
                    $type
                );


            $message =
                $sent .
                ' notification(s) sent to all active teachers.';

        } else {

            throw new RuntimeException(
                'Invalid notification action.'
            );
        }

    } catch (
        Throwable $exception
    ) {

        $error =
            $exception->getMessage();
    }
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

<title>
    <?= htmlspecialchars(
        $page_title,
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</title>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
>

<link
    rel="stylesheet"
    href="/ONLINE_EXAMINATION/admin/assets/css/admin-dashboard.css"
>

<link
    rel="stylesheet"
    href="/ONLINE_EXAMINATION/assets/css/accessibility.css"
>

<link
    rel="stylesheet"
    href="/ONLINE_EXAMINATION/assets/css/notifications.css"
>

<style>

.notify-page {
    padding: 30px;
}

.notify-wrap {
    max-width: 1050px;
    margin: auto;
}

.notify-head {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    align-items: end;
    margin-bottom: 22px;
}

.notify-head h1 {
    margin: 0;
    color: #5d4037;
    font-weight: 900;
}

.notify-head p {
    margin: 8px 0 0;
    color: #756b62;
}

.notify-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.notify-card {
    background: #fff;
    border: 1px solid #e6ded3;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 16px 40px rgba(62,39,35,.06);
}

.notify-card h2 {
    margin: 0 0 7px;
    color: #5d4037;
    font-size: 1.25rem;
}

.notify-card p {
    color: #756b62;
    font-size: .9rem;
    line-height: 1.6;
}

.notify-card label {
    display: block;
    margin: 16px 0 7px;
    font-size: .78rem;
    font-weight: 800;
    color: #5d4037;
}

.notify-card input,
.notify-card textarea,
.notify-card select {
    width: 100%;
    border: 1px solid #ddd4c9;
    border-radius: 12px;
    padding: 12px 13px;
    background: #fff;
    font: inherit;
}

.notify-card textarea {
    min-height: 145px;
    resize: vertical;
}

.notify-btn {
    margin-top: 18px;
    border: 0;
    border-radius: 12px;
    padding: 12px 17px;
    background: #5d4037;
    color: #fff;
    font-weight: 800;
    cursor: pointer;
}

.notify-btn.secondary {
    background: #556b2f;
}

.flash {
    padding: 13px 15px;
    border-radius: 12px;
    margin-bottom: 18px;
    font-weight: 700;
}

.ok {
    background: #eaf3e6;
    color: #3f6533;
}

.err {
    background: #fae8e5;
    color: #8f413b;
}

@media (max-width: 800px) {

    .notify-grid {
        grid-template-columns: 1fr;
    }

    .notify-page {
        padding: 18px;
    }

    .notify-head {
        align-items: flex-start;
        flex-direction: column;
    }
}

</style>

</head>

<body>

<div class="dashboard-wrapper">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">

<?php include 'includes/navbar.php'; ?>

<main class="notify-page">

<div class="notify-wrap">

<div class="notify-head">

<div>

<h1>
    Notification Center
</h1>

<p>
    Send platform announcements directly to the people who need them.
</p>

</div>

</div>


<?php if ($message !== ''): ?>

<div class="flash ok">

<?= htmlspecialchars(
    $message,
    ENT_QUOTES,
    'UTF-8'
) ?>

</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div class="flash err">

<?= htmlspecialchars(
    $error,
    ENT_QUOTES,
    'UTF-8'
) ?>

</div>

<?php endif; ?>


<div class="notify-grid">


<form
    class="notify-card"
    method="post"
>

<h2>
    <i class="fa-solid fa-bullhorn"></i>
    Students + Teachers
</h2>

<p>
    Use this for an announcement that should reach both groups.
</p>

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars(
        csrf_token(),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

<input
    type="hidden"
    name="action"
    value="both"
>

<label>
    Notification title
</label>

<input
    name="title"
    maxlength="180"
    required
    placeholder="Platform announcement"
>

<label>
    Message
</label>

<textarea
    name="message"
    maxlength="5000"
    required
    placeholder="Write your announcement..."
></textarea>

<label>
    Notification type
</label>

<select
    name="notification_type"
>

<option value="system">
    System
</option>

<option value="exam">
    Exam
</option>

<option value="material">
    Material
</option>

<option value="subject">
    Subject
</option>

<option value="category">
    Category
</option>

<option value="topic">
    Topic
</option>

</select>

<button
    class="notify-btn"
    type="submit"
>
    Send to Students &amp; Teachers
</button>

</form>


<form
    class="notify-card"
    method="post"
>

<h2>
    <i class="fa-solid fa-chalkboard-user"></i>
    Teachers Only
</h2>

<p>
    Use this for teacher-facing updates such as new subjects,
    categories, rules or faculty instructions.
</p>

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars(
        csrf_token(),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

<input
    type="hidden"
    name="action"
    value="teachers"
>

<label>
    Notification title
</label>

<input
    name="title"
    maxlength="180"
    required
    placeholder="Faculty update"
>

<label>
    Message
</label>

<textarea
    name="message"
    maxlength="5000"
    required
    placeholder="Write the teacher message..."
></textarea>

<label>
    Notification type
</label>

<select
    name="notification_type"
>

<option value="system">
    System
</option>

<option value="subject">
    Subject
</option>

<option value="category">
    Category
</option>

<option value="topic">
    Topic
</option>

</select>

<button
    class="notify-btn secondary"
    type="submit"
>
    Send to Teachers Only
</button>

</form>

</div>

</div>

</main>

</div>

</div>

<script
    src="/ONLINE_EXAMINATION/admin/assets/js/dashboard.js"
></script>

</body>

</html>