<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/auth.php';

require_login('student');

$studentId =
    (int)(
        $_SESSION['user_id']
        ?? 0
    );


function sub_e(
    mixed $value
): string {

    return htmlspecialchars(
        (string)(
            $value
            ??
            ''
        ),
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );

}


function sub_date(
    mixed $value
): string {

    if (!$value) {
        return '—';
    }

    try {

        return (
            new DateTimeImmutable(
                (string)$value
            )
        )->format(
            'd M Y'
        );

    } catch (
        Throwable
    ) {

        return '—';

    }

}


$plans = [];

$subscriptions = [];

$requests = [];

$active = null;

$error = '';


try {

    /*
    |--------------------------------------------------------------------------
    | ACTIVE PLANS
    |--------------------------------------------------------------------------
    */

    $plans =
        $conn
            ->query(
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

                WHERE status = 'Active'

                ORDER BY
                    duration_months ASC,
                    id ASC
                "
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            );


    /*
    |--------------------------------------------------------------------------
    | STUDENT SUBSCRIPTIONS
    |--------------------------------------------------------------------------
    */

    $subscriptionStatement =
        $conn->prepare(
            "
            SELECT

                s.id,
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
        $subscriptionStatement
            ->fetchAll(
                PDO::FETCH_ASSOC
            );


    /*
    |--------------------------------------------------------------------------
    | ACTIVE SUBSCRIPTION
    |--------------------------------------------------------------------------
    */

    $today =
        new DateTimeImmutable(
            'today'
        );


    foreach (
        $subscriptions
        as $item
    ) {

        if (

            $item['status'] ===
            'Active'

            &&

            $item['start_date']
            <=
            $today->format(
                'Y-m-d'
            )

            &&

            $item['end_date']
            >=
            $today->format(
                'Y-m-d'
            )

        ) {

            $active =
                $item;

            break;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | REQUEST HISTORY
    |--------------------------------------------------------------------------
    */

    $requestStatement =
        $conn->prepare(
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
        $requestStatement
            ->fetchAll(
                PDO::FETCH_ASSOC
            );


} catch (
    Throwable $exception
) {

    error_log(
        'Student subscription page failed: ' .
        $exception->getMessage()
    );


    $error =
        'Subscription information could not be loaded right now.';

}


$flash =
    trim(
        (string)(
            $_GET['message']
            ??
            ''
        )
    );


$flashType =
    trim(
        (string)(
            $_GET['type']
            ??
            ''
        )
    );


$csrf =
    csrf_token();

?>


<!DOCTYPE html>

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
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
>


<style>

:root{

    --sp-bg:#F5F5DC;
    --sp-white:#FFFFFF;

    --sp-brown:#5D4037;
    --sp-dark:#3E2723;

    --sp-olive:#556B2F;
    --sp-olive-dark:#465923;
    --sp-olive-soft:#ECF2E2;

    --sp-gold:#A47B29;
    --sp-gold-soft:#FFF2D7;

    --sp-red:#A84538;
    --sp-red-soft:#FBEAE7;

    --sp-muted:#756B63;
    --sp-light:#968C83;

    --sp-line:#E3DCD2;
    --sp-line-soft:#EEE8E0;

}


.student-plans-page{

    min-height:100vh;

    padding:
        16px 0 55px !important;

    background:

        radial-gradient(
            circle at 4% 3%,
            rgba(85,107,47,.065),
            transparent 23%
        ),

        linear-gradient(
            180deg,
            #FBFAF6 0%,
            #F5F5DC 58%,
            #EFEEE5 100%
        );

}


.student-plans-container{

    width:
        min(
            1280px,
            calc(
                100% - 30px
            )
        );

    margin:auto;

}


/* HERO */

.student-plans-hero{

    position:relative;

    overflow:hidden;

    min-height:235px;

    padding:
        31px;

    border-radius:
        24px;

    background:
        linear-gradient(
            135deg,
            #5D4037,
            #47312B 55%,
            #556B2F
        );

    color:#fff;

    box-shadow:
        0 22px 55px
        rgba(
            62,
            39,
            35,
            .14
        );

}


.student-plans-hero::before{

    content:'';

    position:absolute;

    right:-125px;
    top:-180px;

    width:350px;
    height:350px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .10
        );

    border-radius:50%;

}


.student-plans-hero-content{

    position:relative;

    z-index:2;

    max-width:820px;

}


.student-plan-kicker{

    display:inline-flex;

    align-items:center;

    gap:7px;

    color:#D9D1A2;

    font-size:8px;

    font-weight:900;

    letter-spacing:1.2px;

}


.student-plans-hero h1{

    margin:
        10px 0 8px;

    font-size:
        41px;

    line-height:
        1;

    letter-spacing:
        -.05em;

    font-weight:
        900;

}


.student-plans-hero p{

    margin:0;

    color:#DED5CD;

    font-size:9px;

    line-height:1.75;

}


.student-active-box{

    position:relative;

    z-index:2;

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:8px;

    margin-top:19px;

}


.student-active-item{

    padding:
        10px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .11
        );

    border-radius:
        11px;

    background:
        rgba(
            255,
            255,
            255,
            .055
        );

}


.student-active-item small{

    display:block;

    color:#BFB2A9;

    font-size:6px;

    font-weight:800;

    text-transform:uppercase;

}


.student-active-item strong{

    display:block;

    margin-top:4px;

    color:#fff;

    font-size:9px;

    font-weight:900;

}


/* SECTION */

.student-plans-section-head{

    margin:
        16px 0 9px;

}


.student-plans-section-head span{

    display:block;

    color:
        var(--sp-olive);

    font-size:
        7px;

    font-weight:
        900;

    letter-spacing:
        1.05px;

}


.student-plans-section-head h2{

    margin:
        4px 0 2px;

    color:
        var(--sp-dark);

    font-size:
        21px;

    font-weight:
        900;

    letter-spacing:
        -.025em;

}


.student-plans-section-head p{

    margin:0;

    color:
        var(--sp-muted);

    font-size:
        7.8px;

}


/* PLAN GRID */

.student-plan-grid{

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
        11px;

}


.student-plan-card{

    position:relative;

    overflow:hidden;

    display:flex;

    flex-direction:column;

    min-height:
        310px;

    padding:
        19px;

    border:
        1px solid
        var(--sp-line);

    border-radius:
        18px;

    background:#fff;

    box-shadow:
        0 13px 33px
        rgba(
            62,
            39,
            35,
            .05
        );

    transition:
        transform .22s ease,
        box-shadow .22s ease;

}


.student-plan-card:hover{

    transform:
        translateY(-4px);

    box-shadow:
        0 21px 45px
        rgba(
            62,
            39,
            35,
            .09
        );

}


.student-plan-card.trial{

    border:
        1px solid
        #B1BF88;

    box-shadow:
        0 16px 38px
        rgba(
            85,
            107,
            47,
            .10
        );

}


.student-plan-badge{

    position:absolute;

    top:12px;

    right:12px;

    padding:
        4px 7px;

    border-radius:
        999px;

    color:
        var(--sp-olive);

    background:
        var(--sp-olive-soft);

    font-size:
        6px;

    font-weight:
        900;

    letter-spacing:
        .5px;

}


.student-plan-card h3{

    margin:0;

    color:
        var(--sp-dark);

    font-size:
        17px;

    font-weight:
        900;

}


.student-plan-price{

    margin:
        9px 0 2px;

    color:
        var(--sp-olive);

    font-size:
        28px;

    line-height:
        1;

    font-weight:
        900;

}


.student-plan-duration{

    color:
        var(--sp-light);

    font-size:
        7px;

    font-weight:
        700;

}


.student-plan-description{

    min-height:
        52px;

    margin:
        9px 0 0;

    color:
        var(--sp-muted);

    font-size:
        7.5px;

    line-height:
        1.65;

}


.student-plan-benefits{

    display:grid;

    gap:
        5px;

    margin:
        10px 0 0;

    padding:
        0;

    list-style:none;

}


.student-plan-benefits li{

    display:flex;

    align-items:flex-start;

    gap:6px;

    color:
        #625A54;

    font-size:
        6.8px;

    line-height:
        1.5;

}


.student-plan-benefits i{

    margin-top:2px;

    color:
        var(--sp-olive);

}


.student-plan-button{

    width:100%;

    min-height:
        40px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:7px;

    margin-top:auto;

    padding:
        0 11px;

    border:0;

    border-radius:
        10px;

    color:#fff;

    background:
        var(--sp-brown);

    font-size:
        7.4px;

    font-weight:
        900;

    text-decoration:none;

    cursor:pointer;

    transition:.2s ease;

}


.student-plan-button:hover{

    color:#fff;

    background:
        var(--sp-olive);

}


.student-plan-button.trial{

    background:
        var(--sp-olive);

}


/* NOTE */

.student-plan-note{

    margin-top:
        11px;

    padding:
        11px 13px;

    border:
        1px dashed
        #D6CCC0;

    border-radius:
        11px;

    color:
        var(--sp-muted);

    background:
        #FBF8F1;

    font-size:
        6.8px;

    line-height:
        1.6;

}


/* ALERT */

.student-plan-alert{

    margin-top:
        10px;

    padding:
        11px 13px;

    border-radius:
        10px;

    font-size:
        7px;

    font-weight:
        700;

}


.student-plan-alert.success{

    color:
        var(--sp-olive);

    background:
        var(--sp-olive-soft);

    border:
        1px solid
        #DDE8CE;

}


.student-plan-alert.error{

    color:
        var(--sp-red);

    background:
        var(--sp-red-soft);

    border:
        1px solid
        #EDD0CB;

}


/* HISTORY */

.student-request-history{

    margin-top:
        14px;

    padding:
        17px;

    border:
        1px solid
        var(--sp-line);

    border-radius:
        18px;

    background:#fff;

    box-shadow:
        0 12px 30px
        rgba(
            62,
            39,
            35,
            .04
        );

}


.student-request-history h2{

    margin:
        0 0 11px;

    color:
        var(--sp-dark);

    font-size:
        16px;

    font-weight:
        900;

}


.student-history-wrap{

    overflow-x:auto;

}


.student-history-table{

    width:100%;

    min-width:
        650px;

    border-collapse:
        separate;

    border-spacing:0;

}


.student-history-table th{

    padding:
        9px;

    color:#8D847B;

    background:#FAF7F1;

    border-bottom:
        1px solid
        var(--sp-line);

    font-size:
        6px;

    font-weight:
        900;

    letter-spacing:
        .6px;

    text-align:left;

    text-transform:uppercase;

}


.student-history-table td{

    padding:
        10px 9px;

    color:
        var(--sp-muted);

    border-bottom:
        1px solid
        var(--sp-line-soft);

    font-size:
        7px;

    font-weight:
        600;

}


.student-history-table tr:last-child td{

    border-bottom:0;

}


.student-status{

    display:inline-flex;

    align-items:center;

    min-height:
        21px;

    padding:
        0 7px;

    border-radius:
        999px;

    font-size:
        6px;

    font-weight:
        900;

}


.student-status.pending{

    color:#8A651E;

    background:#FFF3D8;

}


.student-status.approved{

    color:var(--sp-olive);

    background:var(--sp-olive-soft);

}


.student-status.rejected,
.student-status.cancelled{

    color:var(--sp-red);

    background:var(--sp-red-soft);

}


@media(max-width:950px){

    .student-plan-grid{

        grid-template-columns:
            1fr 1fr;

    }

}


@media(max-width:620px){

    .student-plans-container{

        width:
            calc(
                100% - 16px
            );

    }

    .student-plans-hero{

        padding:
            23px 18px;

        border-radius:
            20px;

    }

    .student-plans-hero h1{

        font-size:
            30px;

    }

    .student-active-box{

        grid-template-columns:
            1fr;

    }

    .student-plan-grid{

        grid-template-columns:
            1fr;

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


<section
    class="
        student-plans-hero
    "
>


<div
    class="
        student-plans-hero-content
    "
>


<div
    class="
        student-plan-kicker
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


<h1>
    Unlock more. Prepare better.
</h1>


<p>

Choose a plan to unlock eligible Live Exams
and subscription-only study materials.

Paid plans use manual payment verification.
Free Trial plans activate instantly.

</p>


<?php if (
    $active
): ?>


<div
    class="
        student-active-box
    "
>


<div
    class="
        student-active-item
    "
>

<small>
    Current Plan
</small>

<strong>

<?= sub_e(
    $active[
        'plan_name'
    ]
) ?>

</strong>

</div>


<div
    class="
        student-active-item
    "
>

<small>
    Valid Until
</small>

<strong>

<?= sub_date(
    $active[
        'end_date'
    ]
) ?>

</strong>

</div>


<div
    class="
        student-active-item
    "
>

<small>
    Status
</small>

<strong>
    Active
</strong>

</div>


</div>


<?php endif; ?>


</div>


</section>


<?php if (
    $flash !== ''
): ?>


<div
    class="
        student-plan-alert
        <?= $flashType === 'success'
            ? 'success'
            : 'error' ?>"
>

<?= sub_e(
    $flash
) ?>

</div>


<?php endif; ?>


<?php if (
    $error !== ''
): ?>


<div
    class="
        student-plan-alert
        error
    "
>

<?= sub_e(
    $error
) ?>

</div>


<?php endif; ?>


<div
    class="
        student-plans-section-head
    "
>

<span>
    CHOOSE YOUR ACCESS
</span>


<h2>
    Membership plans
</h2>


<p>
    Select a plan and continue to its activation flow.
</p>

</div>


<section
    class="
        student-plan-grid
    "
>


<?php foreach (
    $plans
    as $index => $plan
): ?>


<?php

$price =
    (float)(
        $plan[
            'price'
        ]
    );


$isTrial =
    $price <= 0;


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


$isBestValue =
    !$isTrial
    &&
    (
        $index ===
        count($plans) - 1
    );

?>


<article
    class="
        student-plan-card
        <?= $isTrial
            ? 'trial'
            : '' ?>"
>


<?php if (
    $isTrial
): ?>


<span
    class="
        student-plan-badge
    "
>

FREE TRIAL

</span>


<?php elseif (
    $isBestValue
): ?>


<span
    class="
        student-plan-badge
    "
>

BEST VALUE

</span>


<?php endif; ?>


<h3>

<?= sub_e(
    $plan[
        'name'
    ]
) ?>

</h3>


<div
    class="
        student-plan-price
    "
>

<?= $isTrial
    ? 'FREE'
    : '₹' .
        number_format(
            $price,
            2
        ) ?>

</div>


<div
    class="
        student-plan-duration
    "
>

<?= (int)$plan[
    'duration_months'
] ?>

month(s) access

</div>


<p
    class="
        student-plan-description
    "
>

<?= sub_e(
    $plan[
        'description'
    ]
    ?:
    'ExamSphere membership access.'
) ?>

</p>


<ul
    class="
        student-plan-benefits
    "
>


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

<?= sub_e(
    $benefit
) ?>

</span>

</li>


<?php endforeach; ?>


</ul>


<a
    href="
        checkout.php?plan_id=
        <?= (int)$plan['id'] ?>
    "
    class="
        student-plan-button
        <?= $isTrial
            ? 'trial'
            : '' ?>"
>

<i
    class="
        fa-solid
        <?= $isTrial
            ? 'fa-bolt'
            : 'fa-arrow-right' ?>
    "
></i>


<?= $isTrial
    ? 'Start Free Trial'
    : 'Continue to Checkout' ?>


</a>


</article>


<?php endforeach; ?>


</section>


<div
    class="
        student-plan-note
    "
>

<i
    class="
        fa-solid
        fa-shield-halved
    "
    style="
        color:#556B2F;
        margin-right:5px;
    "
></i>

Practice Exams remain free.

Plans priced above ₹0 require QR payment
and payment screenshot verification.

A plan priced at ₹0 is treated as a
Free Trial and activates directly without
payment proof or Admin approval.

</div>


<?php if (
    !empty($requests)
): ?>


<section
    class="
        student-request-history
    "
>


<h2>
    Recent activation requests
</h2>


<div
    class="
        student-history-wrap
    "
>


<table
    class="
        student-history-table
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

<?= sub_e(
    $request[
        'plan_name'
    ]
) ?>

</td>


<td>

<?= sub_e(
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
        student-status
        <?= strtolower(
            (string)$request[
                'status'
            ]
        ) ?>
    "
>

<?= sub_e(
    $request[
        'status'
    ]
) ?>

</span>

</td>


<td>

<?= sub_date(
    $request[
        'submitted_at'
    ]
) ?>

</td>


<td>

<?= sub_date(
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


<?php if (
    !empty(
        $subscriptions
    )
): ?>


<section
    class="
        student-request-history
    "
>


<h2>
    Membership history
</h2>


<div
    class="
        student-history-wrap
    "
>


<table
    class="
        student-history-table
    "
>


<thead>

<tr>

<th>
    Plan
</th>

<th>
    Start
</th>

<th>
    End
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

<?= sub_e(
    $subscription[
        'plan_name'
    ]
) ?>

</td>


<td>

<?= sub_date(
    $subscription[
        'start_date'
    ]
) ?>

</td>


<td>

<?= sub_date(
    $subscription[
        'end_date'
    ]
) ?>

</td>


<td>

<span
    class="
        student-status
        <?= strtolower(
            (string)$subscription[
                'status'
            ]
        ) ?>
    "
>

<?= sub_e(
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