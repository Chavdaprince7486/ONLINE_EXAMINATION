<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

require_login('teacher');

$teacherId = current_user_id();

$message = '';
$error = '';

function teacher_password_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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


        $currentPassword =
            (string)(
                $_POST['current_password']
                ?? ''
            );


        $newPassword =
            (string)(
                $_POST['new_password']
                ?? ''
            );


        $confirmPassword =
            (string)(
                $_POST['confirm_password']
                ?? ''
            );


        if ($currentPassword === '') {

            throw new RuntimeException(
                'Current password is required.'
            );
        }


        if ($newPassword === '') {

            throw new RuntimeException(
                'New password is required.'
            );
        }


        if (strlen($newPassword) < 8) {

            throw new RuntimeException(
                'New password must be at least 8 characters long.'
            );
        }


        if (strlen($newPassword) > 255) {

            throw new RuntimeException(
                'New password cannot exceed 255 characters.'
            );
        }


        if (
            $newPassword !==
            $confirmPassword
        ) {

            throw new RuntimeException(
                'New password and confirm password do not match.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LOAD TEACHER PASSWORD
        |--------------------------------------------------------------------------
        */

        $teacherStatement =
            $conn->prepare(
                "
                SELECT

                    id,
                    full_name,
                    password,
                    status

                FROM teachers

                WHERE
                    id = ?

                LIMIT 1
                "
            );


        $teacherStatement->execute([
            $teacherId
        ]);


        $teacher =
            $teacherStatement->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$teacher) {

            throw new RuntimeException(
                'Teacher account could not be found.'
            );
        }


        if (
            (string)$teacher['status']
            !==
            'Active'
        ) {

            throw new RuntimeException(
                'Your teacher account is not active.'
            );
        }


        $storedPassword =
            (string)(
                $teacher['password']
                ?? ''
            );


        /*
        |--------------------------------------------------------------------------
        | VERIFY CURRENT PASSWORD
        |--------------------------------------------------------------------------
        */

        if (
            $storedPassword === ''
            ||
            !password_verify(
                $currentPassword,
                $storedPassword
            )
        ) {

            throw new RuntimeException(
                'Current password is incorrect.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PREVENT SAME PASSWORD
        |--------------------------------------------------------------------------
        */

        if (
            password_verify(
                $newPassword,
                $storedPassword
            )
        ) {

            throw new RuntimeException(
                'New password must be different from the current password.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | HASH NEW PASSWORD
        |--------------------------------------------------------------------------
        */

        $hashedPassword =
            password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );


        if (
            $hashedPassword === false
        ) {

            throw new RuntimeException(
                'Unable to secure the new password.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        $update =
            $conn->prepare(
                "
                UPDATE teachers

                SET
                    password = ?

                WHERE
                    id = ?

                LIMIT 1
                "
            );


        $update->execute([
            $hashedPassword,
            $teacherId
        ]);


        $message =
            'Your password has been changed successfully.';

    } catch (
        Throwable $exception
    ) {

        error_log(
            'ExamSphere teacher password change failed: ' .
            $exception->getMessage()
        );

        $error =
            $exception->getMessage();
    }
}


$page_title =
    'Change Password | ExamSphere';

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

<title>
    Change Password | ExamSphere
</title>


<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>


<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
    crossorigin
>


<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="../assets/css/portal.css"
>


<style>

:root {

    --earth:
        #5d4037;

    --earth-dark:
        #422d26;

    --olive:
        #556b2f;

    --olive-dark:
        #435520;

    --cream:
        #f5f5dc;

    --page:
        #f7f4ed;

    --text:
        #352b26;

    --muted:
        #857970;

    --line:
        #e6ded4;

    --soft:
        #fbfaf6;

}


* {
    box-sizing:
        border-box;
}


body.portal-body {

    margin:
        0;

    min-height:
        100vh;

    color:
        var(--text);

    font-family:
        'Poppins',
        sans-serif;

    background:

        radial-gradient(
            circle at 5% 4%,
            rgba(
                85,
                107,
                47,
                .08
            ),
            transparent 25%
        ),

        radial-gradient(
            circle at 95% 8%,
            rgba(
                93,
                64,
                55,
                .08
            ),
            transparent 26%
        ),

        linear-gradient(
            135deg,
            #fbfaf5,
            #efebe4
        );

}


.password-page {

    width:
        min(
            1180px,
            calc(
                100vw - 24px
            )
        );

    margin:
        0 auto;

    padding:
        22px
        0
        60px;

}


.password-header {

    display:
        flex;

    align-items:
        flex-end;

    justify-content:
        space-between;

    gap:
        20px;

    margin-bottom:
        22px;

}


.password-header .kicker {

    color:
        var(--olive);

    font-size:
        .72rem;

    font-weight:
        900;

    letter-spacing:
        .14em;

}


.password-header h1 {

    margin:
        7px 0 5px;

    color:
        var(--earth);

    font-size:
        2.25rem;

    line-height:
        1.1;

    font-weight:
        900;

    letter-spacing:
        -.04em;

}


.password-header p {

    margin:
        0;

    color:
        var(--muted);

    font-size:
        .76rem;

    font-weight:
        600;

    line-height:
        1.65;

}


.back-button {

    min-height:
        44px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        8px;

    padding:
        0 15px;

    border:
        1px solid
        var(--line);

    border-radius:
        12px;

    color:
        var(--earth);

    background:
        #fff;

    text-decoration:
        none;

    font-size:
        .68rem;

    font-weight:
        800;

    transition:
        .2s ease;

}


.back-button:hover {

    color:
        #fff;

    background:
        var(--earth);

    border-color:
        var(--earth);

    transform:
        translateY(
            -1px
        );

}


.password-layout {

    display:
        grid;

    grid-template-columns:
        minmax(
            0,
            1.35fr
        )
        minmax(
            280px,
            .65fr
        );

    gap:
        17px;

}


.password-card {

    overflow:
        hidden;

    border:
        1px solid
        var(--line);

    border-radius:
        21px;

    background:
        rgba(
            255,
            255,
            255,
            .94
        );

    box-shadow:
        0
        18px
        48px
        rgba(
            60,
            44,
            36,
            .07
        );

}


.password-card-head {

    padding:
        23px 24px
        20px;

    border-bottom:
        1px solid
        var(--line);

    background:
        linear-gradient(
            135deg,
            #fff,
            #faf7f0
        );

}


.password-card-head .kicker {

    color:
        var(--olive);

    font-size:
        .62rem;

    font-weight:
        900;

    letter-spacing:
        .12em;

}


.password-card-head h2 {

    margin:
        5px 0 4px;

    color:
        var(--earth);

    font-size:
        1.22rem;

    font-weight:
        900;

}


.password-card-head p {

    margin:
        0;

    color:
        var(--muted);

    font-size:
        .68rem;

}


.password-card-body {

    padding:
        24px;

}


.password-field {

    margin-bottom:
        16px;

}


.password-field label {

    display:
        block;

    margin-bottom:
        7px;

    color:
        var(--earth);

    font-size:
        .69rem;

    font-weight:
        900;

}


.password-wrap {

    position:
        relative;

}


.password-input {

    width:
        100%;

    min-height:
        54px;

    padding:
        0 51px
        0 14px;

    border:
        1px solid
        #d8cec3;

    border-radius:
        12px;

    outline:
        none;

    color:
        var(--text);

    background:
        #fff;

    font-size:
        .76rem;

    font-weight:
        600;

    transition:
        .2s ease;

}


.password-input:focus {

    border-color:
        var(--olive);

    box-shadow:
        0
        0
        0
        .2rem
        rgba(
            85,
            107,
            47,
            .09
        );

}


.password-toggle {

    position:
        absolute;

    right:
        7px;

    top:
        7px;

    width:
        40px;

    height:
        40px;

    display:
        grid;

    place-items:
        center;

    border:
        0;

    border-radius:
        9px;

    color:
        var(--earth);

    background:
        #f4f0e8;

    cursor:
        pointer;

    transition:
        .2s ease;

}


.password-toggle:hover {

    color:
        var(--olive);

    background:
        #ece7dc;

}


.password-help {

    margin-top:
        6px;

    color:
        var(--muted);

    font-size:
        .58rem;

    font-weight:
        600;

    line-height:
        1.55;

}


.password-rules {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        8px;

    margin-top:
        5px;

}


.password-rule {

    padding:
        10px 11px;

    border:
        1px solid
        #ebe5dc;

    border-radius:
        11px;

    color:
        var(--muted);

    background:
        var(--soft);

    font-size:
        .59rem;

    font-weight:
        700;

}


.password-rule i {

    margin-right:
        5px;

    color:
        var(--olive);

}


.password-actions {

    display:
        flex;

    justify-content:
        flex-end;

    gap:
        9px;

    padding-top:
        20px;

    margin-top:
        21px;

    border-top:
        1px solid
        var(--line);

}


.password-save,
.password-cancel {

    min-height:
        46px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        8px;

    padding:
        0 16px;

    border-radius:
        11px;

    font-size:
        .68rem;

    font-weight:
        900;

    text-decoration:
        none;

}


.password-save {

    border:
        0;

    color:
        #fff;

    background:
        var(--earth);

    cursor:
        pointer;

}


.password-save:hover {

    color:
        #fff;

    background:
        var(--earth-dark);

}


.password-cancel {

    border:
        1px solid
        var(--line);

    color:
        var(--earth);

    background:
        #fff;

}


.password-cancel:hover {

    color:
        var(--earth-dark);

    background:
        #f6f2eb;

}


.security-card {

    padding:
        23px;

    border:
        1px solid
        var(--line);

    border-radius:
        21px;

    background:
        rgba(
            255,
            255,
            255,
            .92
        );

    box-shadow:
        0
        18px
        46px
        rgba(
            60,
            44,
            36,
            .07
        );

}


.security-icon {

    width:
        53px;

    height:
        53px;

    display:
        grid;

    place-items:
        center;

    margin-bottom:
        14px;

    border-radius:
        15px;

    color:
        var(--olive);

    background:
        #eaf1df;

    font-size:
        18px;

}


.security-card h3 {

    margin:
        0;

    color:
        var(--earth);

    font-size:
        1.02rem;

    font-weight:
        900;

}


.security-card > p {

    margin:
        6px 0 0;

    color:
        var(--muted);

    font-size:
        .68rem;

    line-height:
        1.7;

}


.security-list {

    margin:
        17px 0 0;

    padding:
        0;

    list-style:
        none;

}


.security-list li {

    display:
        flex;

    align-items:
        flex-start;

    gap:
        8px;

    padding:
        10px 0;

    border-bottom:
        1px solid
        #eee8df;

    color:
        #6c625a;

    font-size:
        .63rem;

    line-height:
        1.55;

}


.security-list li:last-child {

    border-bottom:
        0;

}


.security-list i {

    margin-top:
        3px;

    color:
        var(--olive);

}


.password-alert {

    border:
        0;

    border-radius:
        12px;

    font-size:
        .69rem;

    font-weight:
        700;

}


@media (
    max-width:
    900px
) {

    .password-layout {

        grid-template-columns:
            1fr;

    }

}


@media (
    max-width:
    600px
) {

    .password-page {

        width:
            calc(
                100vw -
                14px
            );

        padding:
            15px
            0
            45px;

    }


    .password-header {

        align-items:
            flex-start;

        flex-direction:
            column;

    }


    .back-button {

        width:
            100%;

    }


    .password-rules {

        grid-template-columns:
            1fr;

    }


    .password-card-body {

        padding:
            19px;

    }


    .password-actions {

        flex-direction:
            column-reverse;

    }


    .password-save,
    .password-cancel {

        width:
            100%;

    }

}

</style>

</head>


<body class="portal-body">


<div class="portal-layout">


<?php include 'includes/sidebar.php'; ?>


<main class="portal-main">


<div class="password-page">


<header class="password-header">


<div>

<div class="kicker">

<i class="fa-solid fa-key me-1"></i>

FACULTY SECURITY

</div>


<h1>
    Change Password
</h1>


<p>
    Update your faculty account password and keep your ExamSphere account protected.
</p>

</div>


<a
    href="profile.php"
    class="back-button"
>

<i class="fa-solid fa-arrow-left"></i>

Back to Profile

</a>


</header>


<div class="password-layout">


<section class="password-card">


<div class="password-card-head">

<div class="kicker">
    ACCOUNT SECURITY
</div>


<h2>
    Update your password
</h2>


<p>
    Enter your current password, then create a new secure password.
</p>

</div>


<div class="password-card-body">


<?php if ($message !== ''): ?>

<div
    class="
        alert
        alert-success
        password-alert
        mb-4
    "
>

<i class="fa-solid fa-circle-check me-1"></i>

<?= teacher_password_e(
    $message
) ?>

</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div
    class="
        alert
        alert-danger
        password-alert
        mb-4
    "
>

<i class="fa-solid fa-circle-exclamation me-1"></i>

<?= teacher_password_e(
    $error
) ?>

</div>

<?php endif; ?>


<form
    method="post"
    id="teacherPasswordForm"
    autocomplete="off"
>


<?= csrf_field() ?>


<div class="password-field">


<label for="current_password">

Current Password

</label>


<div class="password-wrap">


<input
    id="current_password"
    name="current_password"
    type="password"
    class="password-input"
    maxlength="255"
    autocomplete="current-password"
    required
>


<button
    type="button"
    class="password-toggle"
    data-target="current_password"
    aria-label="Show or hide current password"
>

<i class="fa-solid fa-eye"></i>

</button>


</div>


</div>


<div class="password-field">


<label for="new_password">

New Password

</label>


<div class="password-wrap">


<input
    id="new_password"
    name="new_password"
    type="password"
    class="password-input"
    minlength="8"
    maxlength="255"
    autocomplete="new-password"
    required
>


<button
    type="button"
    class="password-toggle"
    data-target="new_password"
    aria-label="Show or hide new password"
>

<i class="fa-solid fa-eye"></i>

</button>


</div>


<div class="password-help">

Minimum 8 characters.
Use a password that you do not use on other accounts.

</div>


</div>


<div class="password-field">


<label for="confirm_password">

Confirm New Password

</label>


<div class="password-wrap">


<input
    id="confirm_password"
    name="confirm_password"
    type="password"
    class="password-input"
    minlength="8"
    maxlength="255"
    autocomplete="new-password"
    required
>


<button
    type="button"
    class="password-toggle"
    data-target="confirm_password"
    aria-label="Show or hide confirm password"
>

<i class="fa-solid fa-eye"></i>

</button>


</div>


</div>


<div class="password-rules">


<div class="password-rule">

<i class="fa-solid fa-check"></i>

Minimum 8 characters

</div>


<div class="password-rule">

<i class="fa-solid fa-check"></i>

Current password verification

</div>


<div class="password-rule">

<i class="fa-solid fa-check"></i>

New password must be different

</div>


<div class="password-rule">

<i class="fa-solid fa-check"></i>

Passwords must match

</div>


</div>


<div class="password-actions">


<a
    href="profile.php"
    class="password-cancel"
>

Cancel

</a>


<button
    type="submit"
    class="password-save"
    id="passwordSaveButton"
>

<i class="fa-solid fa-shield-halved"></i>

Change Password

</button>


</div>


</form>


</div>


</section>


<aside class="security-card">


<div class="security-icon">

<i class="fa-solid fa-lock"></i>

</div>


<h3>

Protect your faculty account

</h3>


<p>

Your password protects your exams,
question bank, materials and student results.

</p>


<ul class="security-list">


<li>

<i class="fa-solid fa-circle-check"></i>

<span>

Never share your ExamSphere password
with students or other users.

</span>

</li>


<li>

<i class="fa-solid fa-circle-check"></i>

<span>

Use a unique password that is not reused
on other websites.

</span>

</li>


<li>

<i class="fa-solid fa-circle-check"></i>

<span>

Always use your own faculty account
when managing academic content.

</span>

</li>


<li>

<i class="fa-solid fa-circle-check"></i>

<span>

Your new password is securely stored
using password hashing.

</span>

</li>


</ul>


</aside>


</div>


</div>


</main>


</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        /*
        |--------------------------------------------------------------------------
        | PASSWORD VISIBILITY
        |--------------------------------------------------------------------------
        */

        const toggles =
            document.querySelectorAll(
                '.password-toggle'
            );


        toggles.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        const targetId =
                            button.getAttribute(
                                'data-target'
                            );


                        const input =
                            document.getElementById(
                                targetId
                            );


                        const icon =
                            button.querySelector(
                                'i'
                            );


                        if (
                            !input ||
                            !icon
                        ) {
                            return;
                        }


                        if (
                            input.type ===
                            'password'
                        ) {

                            input.type =
                                'text';


                            icon.className =
                                'fa-solid fa-eye-slash';

                        } else {

                            input.type =
                                'password';


                            icon.className =
                                'fa-solid fa-eye';

                        }

                    }
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | FORM VALIDATION
        |--------------------------------------------------------------------------
        */

        const form =
            document.getElementById(
                'teacherPasswordForm'
            );


        const newPassword =
            document.getElementById(
                'new_password'
            );


        const confirmPassword =
            document.getElementById(
                'confirm_password'
            );


        const saveButton =
            document.getElementById(
                'passwordSaveButton'
            );


        if (
            !form ||
            !newPassword ||
            !confirmPassword
        ) {

            return;

        }


        form.addEventListener(
            'submit',
            function (event) {


                if (
                    !form.checkValidity()
                ) {

                    return;

                }


                if (
                    newPassword.value
                    !==
                    confirmPassword.value
                ) {

                    event.preventDefault();


                    alert(
                        'New password and confirm password do not match.'
                    );


                    confirmPassword.focus();


                    return;

                }


                if (
                    newPassword.value.length
                    < 8
                ) {

                    event.preventDefault();


                    alert(
                        'New password must be at least 8 characters long.'
                    );


                    newPassword.focus();


                    return;

                }


                if (
                    saveButton
                ) {

                    saveButton.disabled =
                        true;


                    saveButton.innerHTML =
                        '<i class="fa-solid fa-spinner fa-spin"></i> Changing Password...';

                }

            }
        );

    }
);

</script>


</body>

</html>