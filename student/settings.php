<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';

require_role('student');

$studentId = current_user_id();
$student = null;

try {

    $stmt = $conn->prepare(
        'SELECT
            full_name,
            email,
            email_verified,
            status,
            last_login,
            created_at

         FROM students

         WHERE id = ?

         LIMIT 1'
    );

    $stmt->execute([
        $studentId
    ]);

    $student =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        ) ?: null;

} catch (Throwable $e) {

    error_log(
        'Student settings load failed: ' .
        $e->getMessage()
    );
}

if (!$student) {

    clear_invalid_auth_session();

    header(
        'Location: ../auth/login.php'
    );

    exit;
}

function settings_e(
    mixed $value
): string {

    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function settings_date(
    ?string $value
): string {

    if (!$value) {
        return '—';
    }

    try {

        return (
            new DateTimeImmutable(
                $value
            )
        )->format(
            'd M Y, h:i A'
        );

    } catch (Throwable) {

        return '—';
    }
}

?>

<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="<?= settings_e(
            csrf_token()
        ) ?>"
    >

    <title>
        Account Settings | ExamSphere
    </title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/student-nav.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/dashboard.css"
    >

    <style>

        .settings-page{
            width:min(1180px,94%);
            margin:35px auto 55px
        }

        .settings-hero{
            padding:28px;
            border-radius:24px;
            background:
                linear-gradient(
                    135deg,
                    #5D4037,
                    #556B2F
                );
            color:#fff;
            box-shadow:
                0 20px 48px
                rgba(62,39,35,.14);
            margin-bottom:22px
        }

        .settings-hero h1{
            font-size:1.65rem;
            margin:0 0 6px
        }

        .settings-hero p{
            margin:0;
            opacity:.85;
            font-size:.72rem
        }

        .settings-grid{
            display:grid;
            grid-template-columns:
                1.2fr
                .8fr;
            gap:18px
        }

        .settings-card{
            background:#fff;
            border:1px solid #e9e1d6;
            border-radius:20px;
            padding:22px;
            box-shadow:
                0 16px 45px
                rgba(72,48,37,.08)
        }

        .settings-card h2{
            font-size:.98rem;
            margin:0
        }

        .settings-card p{
            font-size:.67rem;
            color:#81766f;
            line-height:1.55
        }

        .settings-row{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            padding:15px 0;
            border-bottom:1px solid #eee7de
        }

        .settings-row:last-child{
            border-bottom:0
        }

        .settings-row span{
            font-size:.65rem;
            color:#81766f
        }

        .settings-row strong{
            font-size:.7rem;
            color:#433831;
            text-align:right
        }

        .settings-links{
            display:grid;
            gap:10px;
            margin-top:14px
        }

        .settings-link{
            display:flex;
            align-items:center;
            gap:12px;
            padding:13px;
            border:1px solid #ece5da;
            border-radius:14px;
            background:#fcfaf6;
            transition:.2s
        }

        .settings-link:hover{
            transform:translateY(-2px);
            background:#fff
        }

        .settings-link>i:first-child{
            width:36px;
            height:36px;
            display:grid;
            place-items:center;
            border-radius:11px;
            background:#f0eadf;
            color:#5D4037
        }

        .settings-link div{
            min-width:0
        }

        .settings-link strong{
            display:block;
            font-size:.68rem
        }

        .settings-link small{
            display:block;
            margin-top:2px;
            color:#91877f;
            font-size:.58rem
        }

        .settings-link>i:last-child{
            margin-left:auto;
            color:#9a9088;
            font-size:.63rem
        }

        @media(max-width:820px){

            .settings-grid{
                grid-template-columns:1fr
            }

        }

        @media(max-width:560px){

            .settings-page{
                width:
                    calc(100% - 24px)
            }

            .settings-card{
                padding:18px
            }

            .settings-row{
                align-items:flex-start;
                flex-direction:column;
                gap:4px
            }

            .settings-row strong{
                text-align:left
            }

        }

    </style>

</head>

<body>

<?php include 'includes/navbar.php'; ?>

<main class="settings-page">

    <section class="settings-hero">

        <h1>
            Account Settings
        </h1>

        <p>
            Manage your student account
            securely from one place.
        </p>

    </section>

    <section class="settings-grid">

        <article class="settings-card">

            <h2>
                Account information
            </h2>

            <p>
                These values are read directly
                from your student account.
            </p>

            <div class="settings-row">

                <span>
                    Name
                </span>

                <strong>
                    <?= settings_e(
                        $student['full_name']
                    ) ?>
                </strong>

            </div>

            <div class="settings-row">

                <span>
                    Email
                </span>

                <strong>
                    <?= settings_e(
                        $student['email']
                    ) ?>
                </strong>

            </div>

            <div class="settings-row">

                <span>
                    Email verification
                </span>

                <strong>

                    <?= $student['email_verified'] === 'Yes'
                        ? 'Verified'
                        : 'Pending'
                    ?>

                </strong>

            </div>

            <div class="settings-row">

                <span>
                    Account status
                </span>

                <strong>
                    <?= settings_e(
                        $student['status']
                    ) ?>
                </strong>

            </div>

            <div class="settings-row">

                <span>
                    Last login
                </span>

                <strong>
                    <?= settings_date(
                        $student['last_login']
                    ) ?>
                </strong>

            </div>

            <div class="settings-row">

                <span>
                    Member since
                </span>

                <strong>
                    <?= settings_date(
                        $student['created_at']
                    ) ?>
                </strong>

            </div>

        </article>

        <article class="settings-card">

            <h2>
                Security & profile
            </h2>

            <p>
                Use these controls to manage your
                personal information and password.
            </p>

            <div class="settings-links">

                <a
                    class="settings-link"
                    href="profile.php#personalSection"
                >

                    <i class="fa-solid fa-user-pen"></i>

                    <div>

                        <strong>
                            Edit profile
                        </strong>

                        <small>
                            Update your personal
                            and address details.
                        </small>

                    </div>

                    <i class="fa-solid fa-arrow-right"></i>

                </a>

                <button
                    type="button"
                    class="settings-link"
                    id="settingsChangePassword"
                    style="
                        width:100%;
                        font:inherit;
                        text-align:left;
                        cursor:pointer
                    "
                >

                    <i class="fa-solid fa-key"></i>

                    <div>

                        <strong>
                            Change password
                        </strong>

                        <small>
                            Set a new secure
                            login password.
                        </small>

                    </div>

                    <i class="fa-solid fa-arrow-right"></i>

                </button>

                <a
                    class="settings-link"
                    href="subscriptions.php"
                >

                    <i class="fa-solid fa-gem"></i>

                    <div>

                        <strong>
                            Subscription plans
                        </strong>

                        <small>
                            View and manage
                            available plans.
                        </small>

                    </div>

                    <i class="fa-solid fa-arrow-right"></i>

                </a>

                <a
                    class="settings-link"
                    href="dashboard.php"
                >

                    <i class="fa-solid fa-house"></i>

                    <div>

                        <strong>
                            Back to dashboard
                        </strong>

                        <small>
                            Return to your
                            student home.
                        </small>

                    </div>

                    <i class="fa-solid fa-arrow-right"></i>

                </a>

            </div>

        </article>

    </section>

</main>

<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11"
></script>

<script>

(() => {

    const btn =
        document.getElementById(
            'settingsChangePassword'
        );

    if (!btn) {
        return;
    }

    const csrf =
        document.querySelector(
            'meta[name="csrf-token"]'
        )?.content
        ||
        '';

    btn.addEventListener(
        'click',
        async () => {

            const result =
                await Swal.fire({

                    title:
                        'Change Password',

                    html:
                        '<input id="currentPassword" class="swal2-input" type="password" placeholder="Current password">' +
                        '<input id="newPassword" class="swal2-input" type="password" placeholder="New password">' +
                        '<input id="confirmPassword" class="swal2-input" type="password" placeholder="Confirm new password">',

                    showCancelButton:true,

                    confirmButtonText:
                        'Update Password',

                    confirmButtonColor:
                        '#556B2F',

                    preConfirm:() => {

                        const current =
                            document.getElementById(
                                'currentPassword'
                            )?.value
                            || '';

                        const next =
                            document.getElementById(
                                'newPassword'
                            )?.value
                            || '';

                        const confirm =
                            document.getElementById(
                                'confirmPassword'
                            )?.value
                            || '';

                        if (
                            !current ||
                            !next ||
                            !confirm
                        ) {

                            Swal.showValidationMessage(
                                'All fields are required.'
                            );

                            return false;
                        }

                        if (
                            next.length < 8 ||
                            next.length > 72 ||
                            !/[A-Za-z]/.test(next) ||
                            !/[0-9]/.test(next)
                        ) {

                            Swal.showValidationMessage(
                                'Use 8–72 characters with at least one letter and one number.'
                            );

                            return false;
                        }

                        if (
                            next !==
                            confirm
                        ) {

                            Swal.showValidationMessage(
                                'Passwords do not match.'
                            );

                            return false;
                        }

                        return {

                            current_password:
                                current,

                            new_password:
                                next,

                            confirm_password:
                                confirm

                        };

                    }

                });

            if (
                !result.isConfirmed
            ) {
                return;
            }

            try {

                const response =
                    await fetch(
                        'ajax/change_password.php',
                        {

                            method:'POST',

                            credentials:
                                'same-origin',

                            headers:{

                                'Content-Type':
                                    'application/json',

                                'Accept':
                                    'application/json'

                            },

                            body:
                                JSON.stringify({

                                    ...result.value,

                                    csrf_token:
                                        csrf

                                })

                        }
                    );

                const data =
                    await response.json();

                Swal.fire({

                    icon:
                        data.status ||
                        'info',

                    title:
                        data.title ||
                        'ExamSphere',

                    text:
                        data.message ||
                        '',

                    confirmButtonColor:
                        '#556B2F'

                });

            } catch (error) {

                Swal.fire({

                    icon:'error',

                    title:'Request Failed',

                    text:
                        'Unable to update your password.',

                    confirmButtonColor:
                        '#556B2F'

                });

            }

        }
    );

})();

</script>

</body>

</html>