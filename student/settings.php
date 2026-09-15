<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';


/*
|--------------------------------------------------------------------------
| STUDENT AUTH
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {
    header('Location: ../auth/login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$csrf = csrf_token();


/*
|--------------------------------------------------------------------------
| ESCAPE
|--------------------------------------------------------------------------
*/

$e = static function ($value): string {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

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
    Settings | ExamSphere
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
    href="
        https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap
    "
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="
        https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css
    "
>


<link
    rel="stylesheet"
    href="assets/css/dashboard.css?v=<?= time() ?>"
>


<link
    rel="stylesheet"
    href="assets/css/profile.css?v=<?= time() ?>"
>


<style>

/* =========================================================
   SETTINGS PAGE
========================================================= */

.settings-page{

    width:
        min(
            1380px,
            calc(100% - 32px)
        );

    margin:
        0 auto;

    padding:
        26px 0 50px;

}


.settings-hero{

    position:
        relative;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        25px;

    margin-bottom:
        20px;

    padding:
        30px 34px;

    border:
        1px solid
        #e5ded3;

    border-radius:
        24px;

    background:
        linear-gradient(
            135deg,
            #fffdf9,
            #f6f5ec
        );

    box-shadow:
        0 16px 40px
        rgba(
            62,
            39,
            35,
            .07
        );

    overflow:
        hidden;

}


.settings-hero::after{

    content:
        "";

    position:
        absolute;

    width:
        220px;

    height:
        220px;

    right:
        -90px;

    top:
        -110px;

    border:
        1px solid
        rgba(
            85,
            107,
            47,
            .12
        );

    border-radius:
        50%;

}


.settings-hero > *{

    position:
        relative;

    z-index:
        2;

}


.kicker{

    display:
        inline-flex;

    align-items:
        center;

    gap:
        7px;

    color:
        #556b2f;

    font-size:
        9px;

    font-weight:
        800;

    text-transform:
        uppercase;

    letter-spacing:
        1px;

}


.settings-hero h1{

    margin:
        8px 0 8px;

    color:
        #3e2723;

    font-size:
        clamp(
            34px,
            4vw,
            52px
        );

    line-height:
        1;

    letter-spacing:
        -1.6px;

    font-weight:
        800;

}


.settings-hero p{

    margin:
        0;

    max-width:
        700px;

    color:
        #766d65;

    font-size:
        13px;

    line-height:
        1.7;

}


.back-btn{

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        8px;

    min-height:
        45px;

    padding:
        0 17px;

    border:
        1px solid
        #e1d9ce;

    border-radius:
        12px;

    background:
        #ffffff;

    color:
        #5d4037;

    font-size:
        11px;

    font-weight:
        800;

    text-decoration:
        none;

    white-space:
        nowrap;

}


/* =========================================================
   GRID
========================================================= */

.settings-grid{

    display:
        grid;

    grid-template-columns:
        minmax(
            0,
            1.25fr
        )
        minmax(
            330px,
            .75fr
        );

    gap:
        20px;

}


/* =========================================================
   CARD
========================================================= */

.settings-page .card{

    border:
        1px solid
        #e5ded3;

    border-radius:
        22px;

    background:
        rgba(
            255,
            255,
            255,
            .95
        );

    box-shadow:
        0 14px 36px
        rgba(
            62,
            39,
            35,
            .06
        );

    overflow:
        hidden;

}


.card-head{

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    padding:
        24px 26px 19px;

    border-bottom:
        1px solid
        #eee7de;

}


.card-head h2{

    margin:
        6px 0 0;

    color:
        #3e2723;

    font-size:
        21px;

    font-weight:
        800;

}


.card-body{

    padding:
        25px 26px 28px;

}


/* =========================================================
   PASSWORD FORM
========================================================= */

.security-form{

    display:
        grid;

    grid-template-columns:
        repeat(
            3,
            minmax(
                0,
                1fr
            )
        );

    gap:
        17px;

}


.field{

    display:
        flex;

    flex-direction:
        column;

    gap:
        8px;

}


.field label{

    color:
        #756b63;

    font-size:
        9px;

    font-weight:
        800;

    text-transform:
        uppercase;

    letter-spacing:
        .65px;

}


.field input{

    width:
        100%;

    height:
        48px;

    padding:
        0 14px;

    border:
        1px solid
        #ddd5cb;

    border-radius:
        12px;

    outline:
        none;

    background:
        #fffdf9;

    color:
        #3e2723;

    font-family:
        Poppins,
        sans-serif;

    font-size:
        12px;

    transition:
        .2s ease;

}


.field input:focus{

    border-color:
        rgba(
            85,
            107,
            47,
            .65
        );

    box-shadow:
        0 0 0 4px
        rgba(
            85,
            107,
            47,
            .09
        );

}


.form-note{

    grid-column:
        1 / -1;

    display:
        flex;

    align-items:
        center;

    gap:
        7px;

    margin:
        0;

    color:
        #766d65;

    font-size:
        10px;

    line-height:
        1.6;

}


.form-note i{

    color:
        #556b2f;

}


.save-row{

    grid-column:
        1 / -1;

    display:
        flex;

    justify-content:
        flex-end;

    padding-top:
        3px;

}


.btn-primary{

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        8px;

    min-height:
        46px;

    padding:
        0 18px;

    border:
        0;

    border-radius:
        12px;

    background:
        linear-gradient(
            135deg,
            #5d4037,
            #7b5138
        );

    color:
        #ffffff;

    font-family:
        Poppins,
        sans-serif;

    font-size:
        11px;

    font-weight:
        800;

    cursor:
        pointer;

}


/* =========================================================
   SECURITY HABITS
========================================================= */

.habit-list{

    display:
        grid;

    gap:
        12px;

    padding:
        22px 24px 25px;

}


.habit{

    display:
        flex;

    align-items:
        flex-start;

    gap:
        12px;

    padding:
        14px;

    border:
        1px solid
        #e8e1d8;

    border-radius:
        15px;

    background:
        #fcfaf5;

}


.habit-icon{

    display:
        grid;

    place-items:
        center;

    flex:
        0 0 auto;

    width:
        39px;

    height:
        39px;

    border-radius:
        12px;

    color:
        #556b2f;

    background:
        #edf3e3;

}


.habit strong{

    display:
        block;

    margin-bottom:
        4px;

    color:
        #3e2723;

    font-size:
        11px;

    font-weight:
        800;

}


.habit small{

    display:
        block;

    color:
        #766d65;

    font-size:
        9px;

    line-height:
        1.6;

}


/* =========================================================
   MOBILE
========================================================= */

@media(
    max-width:1050px
){

    .settings-grid{

        grid-template-columns:
            1fr;

    }

    .security-form{

        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );

    }

}


@media(
    max-width:680px
){

    .settings-page{

        width:
            calc(
                100% - 18px
            );

        padding:
            16px 0 35px;

    }

    .settings-hero{

        display:
            block;

        padding:
            24px 21px;

    }

    .settings-hero h1{

        font-size:
            34px;

    }

    .settings-hero p{

        font-size:
            11px;

    }

    .back-btn{

        margin-top:
            16px;

    }

    .security-form{

        grid-template-columns:
            1fr;

    }

    .save-row{

        justify-content:
            stretch;

    }

    .btn-primary{

        width:
            100%;

    }

}

</style>

</head>


<body>


<?php include 'includes/navbar.php'; ?>


<main class="settings-page">


<!-- =====================================================
     HERO
====================================================== -->

<section class="settings-hero">


<div>


<span class="kicker">

<i
    class="fa-solid fa-gear"
></i>

Account Settings

</span>


<h1>
    Security & settings.
</h1>


<p>
    Protect your ExamSphere account and keep
    your login credentials secure.
</p>


</div>


<a
    class="back-btn"
    href="profile.php"
>


<i
    class="
        fa-solid
        fa-arrow-left
    "
></i>


Back to profile


</a>


</section>


<!-- =====================================================
     SETTINGS GRID
====================================================== -->

<section class="settings-grid">


<!-- =====================================================
     CHANGE PASSWORD
====================================================== -->

<article class="card">


<div class="card-head">


<div>


<span class="kicker">
    Security
</span>


<h2>
    Change password
</h2>


</div>


<i
    class="
        fa-solid
        fa-shield-halved
    "
    style="
        color:#556b2f;
        font-size:24px;
    "
></i>


</div>


<div class="card-body">


<form
    id="passwordForm"
    class="security-form"
    novalidate
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= $e($csrf) ?>"
>


<div class="field">


<label
    for="currentPassword"
>

Current password

</label>


<input
    id="currentPassword"
    type="password"
    name="current_password"
    autocomplete="current-password"
    required
>


</div>


<div class="field">


<label
    for="newPassword"
>

New password

</label>


<input
    id="newPassword"
    type="password"
    name="new_password"
    minlength="8"
    maxlength="72"
    autocomplete="new-password"
    required
>


</div>


<div class="field">


<label
    for="confirmPassword"
>

Confirm new password

</label>


<input
    id="confirmPassword"
    type="password"
    name="confirm_password"
    minlength="8"
    maxlength="72"
    autocomplete="new-password"
    required
>


</div>


<p class="form-note">


<i
    class="
        fa-solid
        fa-circle-info
    "
></i>


Use at least 8 characters.
A longer passphrase is stronger.


</p>


<div class="save-row">


<button
    class="btn-primary"
    type="submit"
>


<i
    class="
        fa-solid
        fa-key
    "
></i>


Update password


</button>


</div>


</form>


</div>


</article>


<!-- =====================================================
     SECURITY HABITS
====================================================== -->

<aside>


<article class="card">


<div class="card-head">


<div>


<span class="kicker">

Account protection

</span>


<h2>

Good security habits

</h2>


</div>


</div>


<div class="habit-list">


<div class="habit">


<span class="habit-icon">


<i
    class="
        fa-solid
        fa-lock
    "
></i>


</span>


<div>


<strong>
    Never share your password
</strong>


<small>
    ExamSphere staff will never need your password.
</small>


</div>


</div>


<div class="habit">


<span class="habit-icon">


<i
    class="
        fa-solid
        fa-right-from-bracket
    "
></i>


</span>


<div>


<strong>
    Log out on shared devices
</strong>


<small>
    Especially on lab, library and college computers.
</small>


</div>


</div>


<div class="habit">


<span class="habit-icon">


<i
    class="
        fa-solid
        fa-user-shield
    "
></i>


</span>


<div>


<strong>
    Keep your profile current
</strong>


<small>
    Accurate contact details help with account recovery.
</small>


</div>


</div>


</div>


</article>


</aside>


</section>


</main>


<?php include 'includes/footer.php'; ?>


<script
    src="
        https://cdn.jsdelivr.net/npm/sweetalert2@11
    "
></script>


<script
    src="assets/js/profile.js?v=<?= time() ?>"
    defer
></script>


</body>

</html>