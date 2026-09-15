<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/auth.php';

require_login('student');


$studentId = (int)(
    $_SESSION['user_id'] ?? 0
);


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function plans_escape(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function plans_date(mixed $value): string
{
    if (
        $value === null ||
        trim((string)$value) === ''
    ) {
        return '—';
    }

    try {
        return (
            new DateTimeImmutable(
                (string)$value
            )
        )->format('d M Y');

    } catch (Throwable) {
        return '—';
    }
}


function plans_status_class(
    string $status
): string {

    return match ($status) {

        'Active' =>
            'status-active',

        'Approved' =>
            'status-approved',

        'Rejected' =>
            'status-rejected',

        default =>
            'status-pending'
    };
}


/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/

$plans = [];

$subscriptions = [];

$requests = [];

$activeSubscription = null;

$pageError = '';


try {


    /*
    |--------------------------------------------------------------------------
    | PLANS
    |--------------------------------------------------------------------------
    */

    $plansStatement = $conn->query(
        "
        SELECT

            id,
            name,
            duration_months,
            price,
            description,
            benefits,
            status

        FROM subscription_plans

        WHERE
            status = 'Active'

        ORDER BY
            duration_months ASC,
            id ASC
        "
    );


    $plans = $plansStatement->fetchAll(
        PDO::FETCH_ASSOC
    );


    /*
    |--------------------------------------------------------------------------
    | SUBSCRIPTIONS
    |--------------------------------------------------------------------------
    */

    $subscriptionStatement = $conn->prepare(
        "
        SELECT

            s.id,
            s.plan_id,
            s.start_date,
            s.end_date,
            s.status,

            p.name AS plan_name,
            p.duration_months,
            p.price,
            p.description,
            p.benefits

        FROM subscriptions s

        INNER JOIN subscription_plans p
            ON p.id = s.plan_id

        WHERE
            s.student_id = ?

        ORDER BY
            s.start_date DESC,
            s.id DESC
        "
    );


    $subscriptionStatement->execute([
        $studentId
    ]);


    $subscriptions =
        $subscriptionStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | ACTIVE MEMBERSHIP
    |--------------------------------------------------------------------------
    */

    $today =
        new DateTimeImmutable('today');


    foreach (
        $subscriptions
        as $subscription
    ) {

        if (

            $subscription['status'] === 'Active'

            &&

            $subscription['start_date']
                <=
            $today->format('Y-m-d')

            &&

            $subscription['end_date']
                >=
            $today->format('Y-m-d')

        ) {

            $activeSubscription =
                $subscription;

            break;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | REQUEST HISTORY
    |--------------------------------------------------------------------------
    */

    $requestStatement = $conn->prepare(
        "
        SELECT

            r.id,
            r.plan_id,
            r.amount,
            r.request_type,
            r.status,
            r.admin_note,
            r.submitted_at,
            r.reviewed_at,

            p.name AS plan_name,
            p.duration_months

        FROM subscription_requests r

        INNER JOIN subscription_plans p
            ON p.id = r.plan_id

        WHERE
            r.student_id = ?

        ORDER BY
            r.submitted_at DESC,
            r.id DESC

        LIMIT 10
        "
    );


    $requestStatement->execute([
        $studentId
    ]);


    $requests =
        $requestStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $exception) {

    error_log(
        'Student plans page failed: ' .
        $exception->getMessage()
    );

    $pageError =
        'Unable to load subscription plans right now.';
}


$csrf =
    csrf_token();


/*
|--------------------------------------------------------------------------
| ACTIVE ID
|--------------------------------------------------------------------------
*/

$activePlanId = $activeSubscription
    ? (int)$activeSubscription['plan_id']
    : 0;


/*
|--------------------------------------------------------------------------
| FLASH
|--------------------------------------------------------------------------
*/

$flashMessage =
    trim(
        (string)(
            $_GET['message']
            ?? ''
        )
    );


$flashType =
    trim(
        (string)(
            $_GET['type']
            ?? ''
        )
    );


?>

<!doctype html>

<html lang="en">

<head>

<meta charset="UTF-8">


<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>


<meta
    name="theme-color"
    content="#F5F5DC"
>


<title>
    Plans | ExamSphere
</title>


<link
    rel="stylesheet"
    href="../assets/css/main.css"
>


<link
    rel="stylesheet"
    href="assets/css/student-nav.css"
>


<link
    rel="stylesheet"
    href="
        https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css
    "
>


<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>


<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
>


<link
    href="
        https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap
    "
    rel="stylesheet"
>


<style>

/* =========================================================
   ROOT
========================================================= */

:root{

    --plans-bg:#F5F5DC;

    --plans-white:#FFFFFF;

    --plans-brown:#5D4037;

    --plans-brown-dark:#3E2723;

    --plans-brown-soft:#F2E9E2;

    --plans-olive:#556B2F;

    --plans-olive-dark:#465923;

    --plans-olive-soft:#ECF2E2;

    --plans-gold:#A47B29;

    --plans-gold-soft:#FFF2D7;

    --plans-muted:#766D65;

    --plans-light:#968C83;

    --plans-line:#E4DDD3;

    --plans-line-soft:#EEE8E0;

    --plans-red:#A84538;

    --plans-red-soft:#FBEAE7;

}


/* =========================================================
   PAGE
========================================================= */

.student-plans-page{

    width:100%;

    min-height:100vh;

    padding:
        17px 0 65px !important;

    margin:0 !important;

    background:

        radial-gradient(
            circle at 4% 3%,
            rgba(85,107,47,.065),
            transparent 24%
        ),

        radial-gradient(
            circle at 97% 8%,
            rgba(93,64,55,.055),
            transparent 24%
        ),

        linear-gradient(
            180deg,
            #FBFAF6 0%,
            #F5F5DC 62%,
            #EFEEE6 100%
        );

}


.student-plans-page section{

    padding:
        0 !important;

    margin:
        0 !important;

}


.student-plans-container{

    width:
        min(
            1270px,
            calc(
                100% - 32px
            )
        );

    margin:
        0 auto;

}


/* =========================================================
   HERO
========================================================= */

.plans-hero{

    position:relative;

    overflow:hidden;

    min-height:
        245px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:35px;

    padding:
        31px 34px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .08
        );

    border-radius:
        25px;

    color:#fff;

    background:

        linear-gradient(
            135deg,
            #5D4037 0%,
            #46312B 57%,
            #556B2F 100%
        );

    box-shadow:
        0
        22px
        55px
        rgba(
            62,
            39,
            35,
            .15
        );

}


.plans-hero::before{

    content:'';

    position:absolute;

    width:
        420px;

    height:
        420px;

    right:
        -160px;

    top:
        -270px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .10
        );

    border-radius:
        50%;

}


.plans-hero::after{

    content:'';

    position:absolute;

    width:
        240px;

    height:
        240px;

    right:
        80px;

    bottom:
        -185px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .07
        );

    border-radius:
        50%;

}


.plans-hero-content{

    position:relative;

    z-index:3;

    max-width:
        850px;

}


.plans-hero-kicker{

    display:inline-flex;

    align-items:center;

    gap:
        7px;

    color:
        #D6CC9F;

    font-size:
        7px;

    font-weight:
        900;

    letter-spacing:
        1.25px;

}


.plans-hero-title{

    margin:
        10px 0 8px;

    font-size:
        clamp(
            35px,
            4.5vw,
            51px
        );

    line-height:
        .99;

    letter-spacing:
        -.055em;

    font-weight:
        900;

}


.plans-hero-title span{

    color:
        #D4E09F;

}


.plans-hero-text{

    max-width:
        740px;

    margin:0;

    color:
        #DBD0C7;

    font-size:
        8.5px;

    line-height:
        1.75;

}


.plans-current-box{

    position:relative;

    z-index:3;

    width:
        300px;

    flex:
        0 0 300px;

    padding:
        17px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .11
        );

    border-radius:
        17px;

    background:
        rgba(
            255,
            255,
            255,
            .06
        );

    backdrop-filter:
        blur(8px);

}


.plans-current-top{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:
        10px;

}


.plans-current-top span{

    color:
        #BFB3A9;

    font-size:
        6px;

    font-weight:
        900;

    letter-spacing:
        1px;

}


.plans-current-status{

    display:inline-flex;

    align-items:center;

    gap:
        5px;

    padding:
        4px 7px;

    border-radius:
        999px;

    color:
        #D9E5BB;

    background:
        rgba(
            255,
            255,
            255,
            .07
        );

    font-size:
        5.5px;

    font-weight:
        900;

}


.plans-current-status i{

    font-size:
        5px;

}


.plans-current-name{

    margin-top:
        11px;

    color:#fff;

    font-size:
        16px;

    font-weight:
        900;

}


.plans-current-details{

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        7px;

    margin-top:
        11px;

}


.plans-current-detail{

    padding:
        8px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .09
        );

    border-radius:
        9px;

    background:
        rgba(
            255,
            255,
            255,
            .04
        );

}


.plans-current-detail span{

    display:block;

    color:
        #AFA39A;

    font-size:
        5px;

    font-weight:
        700;

    text-transform:
        uppercase;

}


.plans-current-detail strong{

    display:block;

    margin-top:
        3px;

    color:#fff;

    font-size:
        7px;

    font-weight:
        900;

}


/* =========================================================
   PAGE TITLE
========================================================= */

.plans-section-heading{

    margin:
        16px 0 11px;

}


.plans-section-heading > span{

    display:block;

    color:
        var(--plans-olive);

    font-size:
        7px;

    font-weight:
        900;

    letter-spacing:
        1.1px;

}


.plans-section-heading h2{

    margin:
        4px 0 3px;

    color:
        var(--plans-brown-dark);

    font-size:
        21px;

    line-height:
        1.15;

    font-weight:
        900;

    letter-spacing:
        -.025em;

}


.plans-section-heading p{

    margin:0;

    color:
        var(--plans-muted);

    font-size:
        7px;

    font-weight:
        500;

}


/* =========================================================
   FLASH
========================================================= */

.plans-alert{

    margin-top:
        10px;

    padding:
        11px 13px;

    border-radius:
        11px;

    font-size:
        7px;

    line-height:
        1.55;

    font-weight:
        700;

}


.plans-alert.success{

    color:
        var(--plans-olive-dark);

    border:
        1px solid
        #DDE8CE;

    background:
        var(--plans-olive-soft);

}


.plans-alert.error{

    color:
        var(--plans-red);

    border:
        1px solid
        #ECD0CA;

    background:
        var(--plans-red-soft);

}


/* =========================================================
   PLANS GRID
========================================================= */

.plans-grid{

    display:grid;

    grid-template-columns:
        repeat(
            3,
            minmax(
                0,
                1fr
            )
        );

    gap:
        12px;

    align-items:
        stretch;

}


/* =========================================================
   CARD
========================================================= */

.plan-card{

    position:relative;

    overflow:hidden;

    display:flex;

    flex-direction:column;

    min-height:
        390px;

    padding:
        18px;

    border:
        1px solid
        var(--plans-line);

    border-radius:
        19px;

    background:
        rgba(
            255,
            255,
            255,
            .97
        );

    box-shadow:
        0
        14px
        36px
        rgba(
            62,
            39,
            35,
            .045
        );

    transition:
        transform .22s ease,
        box-shadow .22s ease,
        border-color .22s ease;

}


.plan-card::before{

    content:'';

    position:absolute;

    right:
        -72px;

    top:
        -78px;

    width:
        160px;

    height:
        160px;

    border-radius:
        50%;

    background:
        rgba(
            85,
            107,
            47,
            .045
        );

}


.plan-card::after{

    content:'';

    position:absolute;

    left:
        23%;

    bottom:
        -2px;

    width:
        46%;

    height:
        4px;

    border-radius:
        999px;

    background:
        var(--plans-olive);

    opacity:
        .85;

}


.plan-card:hover{

    transform:
        translateY(
            -4px
        );

    border-color:
        #D8D0C4;

    box-shadow:
        0
        22px
        50px
        rgba(
            62,
            39,
            35,
            .085
        );

}


.plan-card.trial{

    border:
        1.5px solid
        #B6C48E;

    box-shadow:
        0
        18px
        44px
        rgba(
            85,
            107,
            47,
            .09
        );

}


.plan-card.trial::after{

    background:
        var(--plans-olive);

}


.plan-card.active{

    border:
        1.5px solid
        #B4C68A;

}


.plan-card.active::after{

    background:
        var(--plans-olive);

}


/* =========================================================
   BADGES
========================================================= */

.plan-badge{

    position:absolute;

    top:
        12px;

    right:
        12px;

    z-index:3;

    display:inline-flex;

    align-items:center;

    gap:
        4px;

    min-height:
        20px;

    padding:
        0 7px;

    border-radius:
        999px;

    color:
        #fff;

    background:
        var(--plans-olive);

    font-size:
        5.7px;

    font-weight:
        900;

    letter-spacing:
        .55px;

}


.plan-badge.trial-badge{

    color:
        var(--plans-olive);

    background:
        var(--plans-olive-soft);

}


.plan-badge.active-badge{

    color:
        var(--plans-olive-dark);

    background:
        #E5EED7;

}


/* =========================================================
   CARD TOP
========================================================= */

.plan-number{

    color:
        #B0A69E;

    font-size:
        6px;

    font-weight:
        800;

}


.plan-icon{

    width:
        46px;

    height:
        46px;

    display:grid;

    place-items:center;

    margin-top:
        7px;

    border-radius:
        13px;

    color:#fff;

    background:
        var(--plans-brown);

    font-size:
        13px;

    box-shadow:
        0
        9px
        18px
        rgba(
            93,
            64,
            55,
            .14
        );

}


.plan-card:nth-child(1)
.plan-icon{

    background:
        var(--plans-olive);

}


.plan-card:nth-child(2)
.plan-icon{

    background:
        var(--plans-brown);

}


.plan-card:nth-child(3)
.plan-icon{

    background:
        #7A6031;

}


.plan-card:nth-child(4n)
.plan-icon{

    background:
        var(--plans-brown);

}


/* =========================================================
   CARD TITLE
========================================================= */

.plan-title{

    margin:
        15px 0 0;

    color:
        var(--plans-brown-dark);

    font-size:
        17px;

    line-height:
        1.15;

    font-weight:
        900;

    letter-spacing:
        -.02em;

}


/* =========================================================
   PRICE
========================================================= */

.plan-price-line{

    display:flex;

    align-items:
        baseline;

    gap:
        5px;

    margin-top:
        8px;

}


.plan-currency{

    color:
        var(--plans-olive);

    font-size:
        15px;

    font-weight:
        900;

}


.plan-price{

    color:
        var(--plans-brown-dark);

    font-size:
        31px;

    line-height:
        1;

    font-weight:
        900;

    letter-spacing:
        -.035em;

}


.plan-period{

    color:
        var(--plans-light);

    font-size:
        7px;

    font-weight:
        600;

}


.plan-free{

    color:
        var(--plans-olive);

    font-size:
        31px;

    line-height:
        1;

    font-weight:
        900;

}


/* =========================================================
   DURATION
========================================================= */

.plan-duration{

    margin-top:
        3px;

    color:
        var(--plans-light);

    font-size:
        6.5px;

    font-weight:
        700;

}


/* =========================================================
   DESCRIPTION
========================================================= */

.plan-description{

    min-height:
        42px;

    margin:
        12px 0 0;

    color:
        var(--plans-muted);

    font-size:
        7.4px;

    line-height:
        1.65;

}


/* =========================================================
   APPROX RATE
========================================================= */

.plan-monthly-rate{

    display:flex;

    align-items:center;

    gap:
        5px;

    margin-top:
        10px;

    color:
        var(--plans-olive);

    font-size:
        6.7px;

    font-weight:
        800;

}


.plan-monthly-rate i{

    font-size:
        7px;

}


/* =========================================================
   DIVIDER
========================================================= */

.plan-divider{

    height:
        1px;

    margin:
        13px 0 10px;

    background:
        var(--plans-line-soft);

}


/* =========================================================
   BENEFITS
========================================================= */

.plan-benefits{

    display:grid;

    gap:
        7px;

    margin:
        0;

    padding:
        0;

    list-style:none;

}


.plan-benefits li{

    display:flex;

    align-items:flex-start;

    gap:
        6px;

    color:
        #69615B;

    font-size:
        6.7px;

    line-height:
        1.5;

    font-weight:
        500;

}


.plan-benefits li i{

    margin-top:
        2px;

    color:
        var(--plans-olive);

    font-size:
        6px;

}


/* =========================================================
   BUTTON
========================================================= */

.plan-action{

    position:relative;

    z-index:4;

    width:
        100%;

    min-height:
        42px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:
        7px;

    margin-top:auto;

    padding:
        0 12px;

    border:
        0;

    border-radius:
        10px;

    color:#fff;

    background:
        var(--plans-brown);

    font-size:
        7px;

    font-weight:
        900;

    text-decoration:none;

    cursor:pointer;

    transition:
        background .2s ease,
        transform .2s ease;

}


.plan-action:hover{

    color:#fff;

    background:
        var(--plans-olive);

    transform:
        translateY(
            -1px
        );

}


.plan-action.trial{

    background:
        var(--plans-olive);

}


.plan-action.trial:hover{

    background:
        var(--plans-olive-dark);

}


.plan-action.active-action{

    color:
        var(--plans-olive-dark);

    background:
        var(--plans-olive-soft);

}


.plan-action.active-action:hover{

    color:
        var(--plans-olive-dark);

    background:
        #E2ECD4;

}


/* =========================================================
   FOOT NOTE
========================================================= */

.plans-foot-note{

    display:flex;

    align-items:flex-start;

    gap:
        7px;

    margin-top:
        11px;

    padding:
        11px 13px;

    border:
        1px dashed
        #D4CABE;

    border-radius:
        11px;

    color:
        var(--plans-muted);

    background:
        #FBF8F1;

    font-size:
        6.7px;

    line-height:
        1.6;

}


.plans-foot-note i{

    margin-top:
        2px;

    color:
        var(--plans-olive);

}


/* =========================================================
   HISTORY
========================================================= */

.plans-history{

    margin-top:
        13px;

    padding:
        17px;

    border:
        1px solid
        var(--plans-line);

    border-radius:
        18px;

    background:
        #fff;

    box-shadow:
        0
        11px
        28px
        rgba(
            62,
            39,
            35,
            .035
        );

}


.plans-history-head{

    display:flex;

    align-items:end;

    justify-content:space-between;

    gap:
        12px;

    margin-bottom:
        11px;

}


.plans-history-head span{

    display:block;

    color:
        var(--plans-olive);

    font-size:
        6px;

    font-weight:
        900;

    letter-spacing:
        1px;

}


.plans-history-head h3{

    margin:
        4px 0 0;

    color:
        var(--plans-brown-dark);

    font-size:
        16px;

    font-weight:
        900;

}


.plans-history-wrap{

    overflow-x:
        auto;

}


.plans-history-table{

    width:
        100%;

    min-width:
        650px;

    border-collapse:
        separate;

    border-spacing:
        0;

}


.plans-history-table th{

    padding:
        9px;

    color:
        #8E857D;

    background:
        #FAF8F3;

    border-bottom:
        1px solid
        var(--plans-line);

    font-size:
        5.8px;

    font-weight:
        900;

    letter-spacing:
        .55px;

    text-align:left;

    text-transform:
        uppercase;

}


.plans-history-table td{

    padding:
        10px 9px;

    color:
        var(--plans-muted);

    border-bottom:
        1px solid
        var(--plans-line-soft);

    font-size:
        6.8px;

    font-weight:
        600;

}


.plans-history-table tr:last-child td{

    border-bottom:
        0;

}


.request-badge{

    display:inline-flex;

    align-items:center;

    min-height:
        20px;

    padding:
        0 6px;

    border-radius:
        999px;

    font-size:
        5.7px;

    font-weight:
        900;

}


.status-pending{

    color:
        #8A651E;

    background:
        #FFF3D8;

}


.status-approved,
.status-active{

    color:
        var(--plans-olive);

    background:
        var(--plans-olive-soft);

}


.status-rejected{

    color:
        var(--plans-red);

    background:
        var(--plans-red-soft);

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1050px){

    .plans-hero{

        flex-direction:
            column;

        align-items:
            flex-start;

    }


    .plans-current-box{

        width:
            100%;

        max-width:
            430px;

    }


    .plans-grid{

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


@media(max-width:650px){

    .student-plans-page{

        padding:
            11px 0 45px !important;

    }


    .student-plans-container{

        width:
            calc(
                100% - 14px
            );

    }


    .plans-hero{

        padding:
            23px 18px;

        border-radius:
            20px;

    }


    .plans-hero-title{

        font-size:
            31px;

    }


    .plans-hero-text{

        font-size:
            7.5px;

    }


    .plans-current-box{

        max-width:
            none;

        padding:
            14px;

    }


    .plans-section-heading h2{

        font-size:
            19px;

    }


    .plans-grid{

        grid-template-columns:
            1fr;

    }


    .plan-card{

        min-height:
            370px;

    }

}


@media(max-width:400px){

    .plans-current-details{

        grid-template-columns:
            1fr;

    }


    .plan-card{

        min-height:
            360px;

    }

}

</style>

</head>


<body>


<?php include 'includes/navbar.php'; ?>


<main
    class="
        student-plans-page
    "
>


<div
    class="
        student-plans-container
    "
>


<!-- =====================================================
     HERO
====================================================== -->

<section
    class="
        plans-hero
    "
>


<div
    class="
        plans-hero-content
    "
>


<div
    class="
        plans-hero-kicker
    "
>

<i
    class="
        fa-solid
        fa-gem
    "
></i>

EXAMSPHERE MEMBERSHIP

</div>


<h1
    class="
        plans-hero-title
    "
>

Unlock more.

<span>
    Prepare better.
</span>

</h1>


<p
    class="
        plans-hero-text
    "
>

Choose the membership that matches your preparation.
Practice Exams remain free, while eligible Live Exams
and subscription-only materials require an active plan.

</p>


</div>


<?php if (
    $activeSubscription
): ?>


<div
    class="
        plans-current-box
    "
>


<div
    class="
        plans-current-top
    "
>


<span>
    CURRENT MEMBERSHIP
</span>


<div
    class="
        plans-current-status
    "
>

<i
    class="
        fa-solid
        fa-circle
    "
></i>

ACTIVE

</div>


</div>


<div
    class="
        plans-current-name
    "
>

<?= plans_escape(
    $activeSubscription[
        'plan_name'
    ]
) ?>

</div>


<div
    class="
        plans-current-details
    "
>


<div
    class="
        plans-current-detail
    "
>

<span>
    Started
</span>

<strong>

<?= plans_date(
    $activeSubscription[
        'start_date'
    ]
) ?>

</strong>

</div>


<div
    class="
        plans-current-detail
    "
>

<span>
    Valid Until
</span>

<strong>

<?= plans_date(
    $activeSubscription[
        'end_date'
    ]
) ?>

</strong>

</div>


</div>


</div>


<?php endif; ?>


</section>


<!-- =====================================================
     ALERTS
====================================================== -->

<?php if (
    $flashMessage !== ''
): ?>


<div
    class="
        plans-alert
        <?= $flashType === 'success'
            ? 'success'
            : 'error' ?>"
>

<?= plans_escape(
    $flashMessage
) ?>

</div>


<?php endif; ?>


<?php if (
    $pageError !== ''
): ?>


<div
    class="
        plans-alert
        error
    "
>

<?= plans_escape(
    $pageError
) ?>

</div>


<?php endif; ?>


<!-- =====================================================
     HEADING
====================================================== -->

<div
    class="
        plans-section-heading
    "
>


<span>
    CHOOSE YOUR ACCESS
</span>


<h2>
    Membership plans
</h2>


<p>
    Select a plan and continue with its activation process.
</p>


</div>


<!-- =====================================================
     PLANS
====================================================== -->

<section
    class="
        plans-grid
    "
>


<?php

$planCount =
    count($plans);


foreach (
    $plans
    as $index => $plan
):


$planId =
    (int)$plan[
        'id'
    ];


$price =
    (float)(
        $plan[
            'price'
        ]
        ??
        0
    );


$duration =
    max(
        1,
        (int)(
            $plan[
                'duration_months'
            ]
            ??
            1
        )
    );


$isTrial =
    $price <= 0;


$isCurrent =
    $activePlanId ===
    $planId;


$bestValue =
    !$isTrial
    &&
    !$isCurrent
    &&
    $index ===
    $planCount - 1;


$monthlyRate =
    $duration > 0
        ? $price / $duration
        : $price;


$benefits =
    preg_split(
        '/[;\r\n]+/',
        (string)(
            $plan[
                'benefits'
            ]
            ??
            ''
        )
    );


$benefits =
    array_values(
        array_filter(
            array_map(
                'trim',
                $benefits
            )
        )
    );


$iconClasses = [

    'fa-seedling',

    'fa-rocket',

    'fa-bolt',

    'fa-crown'

];


$icon =
    $iconClasses[
        $index % count(
            $iconClasses
        )
    ];

?>


<article
    class="
        plan-card
        <?= $isTrial
            ? 'trial'
            : '' ?>
        <?= $isCurrent
            ? 'active'
            : '' ?>
    "
>


<?php if (
    $isCurrent
): ?>


<div
    class="
        plan-badge
        active-badge
    "
>

<i
    class="
        fa-solid
        fa-circle-check
    "
></i>

CURRENT

</div>


<?php elseif (
    $isTrial
): ?>


<div
    class="
        plan-badge
        trial-badge
    "
>

<i
    class="
        fa-solid
        fa-gift
    "
></i>

FREE TRIAL

</div>


<?php elseif (
    $bestValue
): ?>


<div
    class="
        plan-badge
    "
>

<i
    class="
        fa-solid
        fa-crown
    "
></i>

BEST VALUE

</div>


<?php endif; ?>


<div
    class="
        plan-number
    "
>

<?= str_pad(
    (string)($index + 1),
    2,
    '0',
    STR_PAD_LEFT
) ?>

</div>


<div
    class="
        plan-icon
    "
>

<i
    class="
        fa-solid
        <?= $icon ?>
    "
></i>

</div>


<h3
    class="
        plan-title
    "
>

<?= plans_escape(
    $plan[
        'name'
    ]
) ?>

</h3>


<div
    class="
        plan-price-line
    "
>


<?php if (
    $isTrial
): ?>


<span
    class="
        plan-free
    "
>

FREE

</span>


<?php else: ?>


<span
    class="
        plan-currency
    "
>

₹

</span>


<span
    class="
        plan-price
    "
>

<?= number_format(
    $price,
    0
) ?>

</span>


<span
    class="
        plan-period
    "
>

/

<?= $duration ?>

<?= $duration === 1
    ? 'month'
    : 'months' ?>

</span>


<?php endif; ?>


</div>


<div
    class="
        plan-duration
    "
>

<?= $duration ?>

<?= $duration === 1
    ? 'month'
    : 'months' ?>

access

</div>


<p
    class="
        plan-description
    "
>

<?= plans_escape(
    $plan[
        'description'
    ]
    ?:
    'ExamSphere membership access.'
) ?>

</p>


<?php if (
    !$isTrial
): ?>


<div
    class="
        plan-monthly-rate
    "
>

<i
    class="
        fa-solid
        fa-calculator
    "
></i>

Approx.
₹<?= number_format(
    $monthlyRate,
    2
) ?>

/ month

</div>


<?php else: ?>


<div
    class="
        plan-monthly-rate
    "
>

<i
    class="
        fa-solid
        fa-gift
    "
></i>

₹0.00 / month

</div>


<?php endif; ?>


<div
    class="
        plan-divider
    "
></div>


<ul
    class="
        plan-benefits
    "
>


<?php if (
    empty(
        $benefits
    )
): ?>


<li>

<i
    class="
        fa-solid
        fa-circle-check
    "
></i>

<span>
    ExamSphere membership access
</span>

</li>


<?php else: ?>


<?php foreach (
    $benefits
    as $benefit
): ?>


<li>

<i
    class="
        fa-solid
        fa-circle-check
    "
></i>

<span>

<?= plans_escape(
    $benefit
) ?>

</span>

</li>


<?php endforeach; ?>


<?php endif; ?>


</ul>


<?php if (
    $isCurrent
): ?>


<a
    href="subscriptions.php"
    class="
        plan-action
        active-action
    "
>

<i
    class="
        fa-solid
        fa-circle-check
    "
></i>

View Membership

</a>


<?php elseif (
    $isTrial
): ?>


<a
    href="
        checkout.php?plan_id=
        <?= $planId ?>
    "
    class="
        plan-action
        trial
    "
>

<i
    class="
        fa-solid
        fa-bolt
    "
></i>

Start Free Trial

</a>


<?php else: ?>


<a
    href="
        checkout.php?plan_id=
        <?= $planId ?>
    "
    class="
        plan-action
    "
>

<i
    class="
        fa-solid
        fa-arrow-right
    "
></i>

Continue to Checkout

</a>


<?php endif; ?>


</article>


<?php endforeach; ?>


</section>


<!-- =====================================================
     NOTE
====================================================== -->

<div
    class="
        plans-foot-note
    "
>

<i
    class="
        fa-solid
        fa-shield-halved
    "
></i>


<span>

<strong>
    Secure activation:
</strong>

Practice Exams always remain free.

Paid plans use QR payment + required payment
screenshot + Admin verification.

A ₹0 plan is treated as a Free Trial and
activates instantly without payment or approval.

</span>

</div>


<!-- =====================================================
     REQUEST HISTORY
====================================================== -->

<?php if (
    !empty($requests)
): ?>


<section
    class="
        plans-history
    "
>


<div
    class="
        plans-history-head
    "
>


<div>

<span>
    ACTIVATION REQUESTS
</span>


<h3>
    Recent requests
</h3>

</div>


<span
    style="
        color:#968C83;
        letter-spacing:0;
        font-weight:600;
    "
>

<?= count(
    $requests
) ?>

request<?= count(
    $requests
) === 1
    ? ''
    : 's' ?>

</span>


</div>


<div
    class="
        plans-history-wrap
    "
>


<table
    class="
        plans-history-table
    "
>


<thead>

<tr>

<th>
    Plan
</th>

<th>
    Type
</th>

<th>
    Amount
</th>

<th>
    Status
</th>

<th>
    Submitted
</th>

<th>
    Reviewed
</th>

</tr>

</thead>


<tbody>


<?php foreach (
    $requests
    as $request
): ?>


<tr>


<td>

<strong
    style="
        color:#3E2723;
        font-size:7.5px;
        font-weight:900;
    "
>

<?= plans_escape(
    $request[
        'plan_name'
    ]
) ?>

</strong>

</td>


<td>

<?= plans_escape(
    $request[
        'request_type'
    ]
) ?>

</td>


<td>

<?= (
    (float)$request[
        'amount'
    ] <= 0
)

    ? 'FREE'

    :

    '₹' .
    number_format(
        (float)$request[
            'amount'
        ],
        2
    )

?>

</td>


<td>

<span
    class="
        request-badge
        <?= plans_status_class(
            (string)$request[
                'status'
            ]
        ) ?>
    "
>

<?= plans_escape(
    $request[
        'status'
    ]
) ?>

</span>

</td>


<td>

<?= plans_date(
    $request[
        'submitted_at'
    ]
) ?>

</td>


<td>

<?= plans_date(
    $request[
        'reviewed_at'
    ]
) ?>

</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


</section>


<?php endif; ?>


<!-- =====================================================
     MEMBERSHIP HISTORY
====================================================== -->

<?php if (
    !empty(
        $subscriptions
    )
): ?>


<section
    class="
        plans-history
    "
>


<div
    class="
        plans-history-head
    "
>


<div>

<span>
    MEMBERSHIP HISTORY
</span>


<h3>
    Your subscriptions
</h3>

</div>


</div>


<div
    class="
        plans-history-wrap
    "
>


<table
    class="
        plans-history-table
    "
>


<thead>

<tr>

<th>
    Plan
</th>

<th>
    Started
</th>

<th>
    Valid Until
</th>

<th>
    Status
</th>

</tr>

</thead>


<tbody>


<?php foreach (
    $subscriptions
    as $subscription
): ?>


<tr>


<td>

<?= plans_escape(
    $subscription[
        'plan_name'
    ]
) ?>

</td>


<td>

<?= plans_date(
    $subscription[
        'start_date'
    ]
) ?>

</td>


<td>

<?= plans_date(
    $subscription[
        'end_date'
    ]
) ?>

</td>


<td>

<span
    class="
        request-badge
        <?= plans_status_class(
            (string)$subscription[
                'status'
            ]
        ) ?>
    "
>

<?= plans_escape(
    $subscription[
        'status'
    ]
) ?>

</span>

</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


</section>


<?php endif; ?>


</div>


</main>


</body>

</html>