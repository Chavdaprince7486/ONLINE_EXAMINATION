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


$planId =
    filter_var(
        $_GET['plan_id']
        ??
        null,
        FILTER_VALIDATE_INT
    );


if (
    !$planId
) {

    header(
        'Location: subscriptions.php'
    );

    exit;
}


function checkout_e(
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


$plan = null;

$activeSubscription = null;

$pendingRequest = null;

$error = '';


try {


    /*
    |--------------------------------------------------------------------------
    | PLAN
    |--------------------------------------------------------------------------
    */

    $planStatement =
        $conn->prepare(
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
                id = ?

                AND status = 'Active'

            LIMIT 1
            "
        );


    $planStatement->execute([
        $planId
    ]);


    $plan =
        $planStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$plan
    ) {

        header(
            'Location: subscriptions.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | ACTIVE SUBSCRIPTION
    |--------------------------------------------------------------------------
    */

    $today =
        date('Y-m-d');


    $activeStatement =
        $conn->prepare(
            "
            SELECT
                id,
                plan_id,
                start_date,
                end_date,
                status

            FROM subscriptions

            WHERE

                student_id = ?

                AND status = 'Active'

                AND start_date <= ?

                AND end_date >= ?

            ORDER BY
                end_date DESC,
                id DESC

            LIMIT 1
            "
        );


    $activeStatement->execute([
        $studentId,
        $today,
        $today
    ]);


    $activeSubscription =
        $activeStatement
            ->fetch(
                PDO::FETCH_ASSOC
            );


    /*
    |--------------------------------------------------------------------------
    | PENDING REQUEST
    |--------------------------------------------------------------------------
    */

    $pendingStatement =
        $conn->prepare(
            "
            SELECT
                id,
                status

            FROM subscription_requests

            WHERE

                student_id = ?

                AND plan_id = ?

                AND status = 'Pending'

            LIMIT 1
            "
        );


    $pendingStatement->execute([
        $studentId,
        $planId
    ]);


    $pendingRequest =
        $pendingStatement->fetch(
            PDO::FETCH_ASSOC
        );


} catch (
    Throwable $exception
) {

    error_log(
        'Subscription checkout failed: ' .
        $exception->getMessage()
    );


    $error =
        'Unable to load the selected plan.';
}


$price =
    $plan
        ? (float)(
            $plan[
                'price'
            ]
        )
        : 0;


$isTrial =
    $price <= 0;


$qrPath =
    dirname(__DIR__) .
    '/assets/images/payment-qr.png';


$qrExists =
    is_file(
        $qrPath
    );


$csrf =
    csrf_token();

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
    Checkout | ExamSphere
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


<style>

:root{

    --co-cream:#F5F5DC;

    --co-white:#FFFFFF;

    --co-brown:#5D4037;

    --co-dark:#3E2723;

    --co-olive:#556B2F;

    --co-olive-dark:#465923;

    --co-olive-soft:#ECF2E2;

    --co-gold:#A47B29;

    --co-red:#A84538;

    --co-muted:#756B63;

    --co-light:#968C83;

    --co-line:#E3DCD2;

    --co-line-soft:#EEE8E0;

}


.checkout-page{

    min-height:100vh;

    padding:
        16px 0 58px !important;

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


.checkout-container{

    width:
        min(
            1100px,
            calc(
                100% - 30px
            )
        );

    margin:auto;

}


.checkout-shell{

    display:grid;

    grid-template-columns:
        minmax(
            0,
            1.05fr
        )
        minmax(
            320px,
            .75fr
        );

    gap:12px;

}


/* CARD */

.checkout-card{

    border:
        1px solid
        var(--co-line);

    border-radius:
        20px;

    background:
        #fff;

    box-shadow:
        0 15px 40px
        rgba(
            62,
            39,
            35,
            .055
        );

}


/* MAIN */

.checkout-main{

    padding:
        24px;

}


.checkout-kicker{

    color:
        var(--co-olive);

    font-size:
        7px;

    font-weight:
        900;

    letter-spacing:
        1.1px;

}


.checkout-title{

    margin:
        7px 0 6px;

    color:
        var(--co-dark);

    font-size:
        29px;

    line-height:
        1.05;

    font-weight:
        900;

    letter-spacing:
        -.04em;

}


.checkout-subtitle{

    margin:0;

    color:
        var(--co-muted);

    font-size:
        8px;

    line-height:
        1.7;

}


/* SUMMARY */

.checkout-summary{

    margin-top:
        16px;

    padding:
        13px;

    border:
        1px solid
        var(--co-line-soft);

    border-radius:
        13px;

    background:
        #FCFBF8;

}


.checkout-summary-row{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:
        12px;

    padding:
        8px 0;

    border-bottom:
        1px solid
        var(--co-line-soft);

    font-size:
        7px;

}


.checkout-summary-row:last-child{

    border-bottom:0;

}


.checkout-summary-row span{

    color:
        var(--co-muted);

}


.checkout-summary-row strong{

    color:
        var(--co-dark);

    font-weight:
        900;

}


/* STEPS */

.checkout-steps{

    display:grid;

    gap:
        7px;

    margin-top:
        14px;

}


.checkout-step{

    display:flex;

    align-items:flex-start;

    gap:
        8px;

    padding:
        10px;

    border:
        1px solid
        var(--co-line-soft);

    border-radius:
        10px;

    background:
        #FCFAF7;

}


.checkout-step-number{

    width:
        25px;

    height:
        25px;

    flex:
        0 0 25px;

    display:grid;

    place-items:center;

    border-radius:
        8px;

    color:#fff;

    background:
        var(--co-brown);

    font-size:
        7px;

    font-weight:
        900;

}


.checkout-step strong{

    display:block;

    color:
        var(--co-dark);

    font-size:
        7px;

    font-weight:
        900;

}


.checkout-step span{

    display:block;

    margin-top:
        2px;

    color:
        var(--co-muted);

    font-size:
        6.5px;

    line-height:
        1.45;

}


/* UPLOAD */

.checkout-upload{

    margin-top:
        14px;

    padding:
        14px;

    border:
        1px dashed
        #CFC5B8;

    border-radius:
        13px;

    background:
        #FBF8F0;

}


.checkout-upload label{

    display:block;

    margin-bottom:
        6px;

    color:
        var(--co-brown);

    font-size:
        7px;

    font-weight:
        900;

}


.checkout-file{

    width:
        100%;

    padding:
        8px;

    border:
        1px solid
        #DDD5CA;

    border-radius:
        9px;

    background:#fff;

    font-size:
        7px;

}


.checkout-help{

    margin:
        6px 0 0;

    color:
        var(--co-muted);

    font-size:
        6.3px;

    line-height:
        1.55;

}


/* CHECK */

.checkout-confirm{

    display:flex;

    align-items:flex-start;

    gap:
        7px;

    margin-top:
        10px;

    color:
        var(--co-muted);

    font-size:
        6.8px;

    line-height:
        1.5;

}


.checkout-confirm input{

    margin-top:
        1px;

}


/* SUBMIT */

.checkout-submit{

    width:
        100%;

    min-height:
        43px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:
        7px;

    margin-top:
        14px;

    border:0;

    border-radius:
        10px;

    color:#fff;

    background:
        var(--co-olive);

    font-size:
        7.5px;

    font-weight:
        900;

    cursor:pointer;

}


.checkout-submit:hover{

    background:
        var(--co-olive-dark);

}


.checkout-submit:disabled{

    opacity:.55;

    cursor:
        not-allowed;

}


/* FREE */

.checkout-free{

    margin-top:
        14px;

    padding:
        14px;

    border:
        1px solid
        #DCE7CC;

    border-radius:
        12px;

    color:
        var(--co-olive);

    background:
        #F5F8EF;

    font-size:
        7px;

    line-height:
        1.65;

}


/* BACK */

.checkout-back{

    display:inline-flex;

    align-items:center;

    gap:
        6px;

    margin-top:
        11px;

    color:
        var(--co-brown);

    font-size:
        6.8px;

    font-weight:
        900;

    text-decoration:none;

}


.checkout-back:hover{

    color:
        var(--co-olive);

}


/* SIDE */

.checkout-side{

    position:relative;

    overflow:hidden;

    padding:
        21px;

    color:#fff;

    border-color:
        transparent;

    background:
        linear-gradient(
            145deg,
            #5D4037,
            #432E28 56%,
            #556B2F 145%
        );

    box-shadow:
        0 24px 48px
        rgba(
            62,
            39,
            35,
            .17
        );

}


.checkout-side::before{

    content:'';

    position:absolute;

    right:
        -100px;

    top:
        -130px;

    width:
        275px;

    height:
        275px;

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


.checkout-side-content{

    position:relative;

    z-index:2;

}


.checkout-side-kicker{

    color:
        #D3C7BC;

    font-size:
        7px;

    font-weight:
        900;

    letter-spacing:
        1px;

}


.checkout-side h2{

    margin:
        7px 0 5px;

    color:#fff;

    font-size:
        25px;

    font-weight:
        900;

}


.checkout-side p{

    margin:0;

    color:
        #D8CCC3;

    font-size:
        7px;

    line-height:
        1.65;

}


.checkout-side-price{

    margin-top:
        11px;

    color:#fff;

    font-size:
        30px;

    font-weight:
        900;

}


.checkout-qr{

    display:flex;

    justify-content:center;

    margin:
        13px 0;

}


.checkout-qr img{

    width:
        190px;

    height:
        190px;

    object-fit:contain;

    padding:
        8px;

    border-radius:
        13px;

    background:#fff;

}


.checkout-qr-placeholder{

    width:
        190px;

    height:
        190px;

    display:grid;

    place-items:center;

    padding:
        18px;

    border:
        1px dashed
        rgba(
            255,
            255,
            255,
            .25
        );

    border-radius:
        13px;

    background:
        rgba(
            255,
            255,
            255,
            .05
        );

    text-align:center;

}


.checkout-qr-placeholder strong{

    display:block;

    color:#fff;

    font-size:
        8px;

}


.checkout-qr-placeholder span{

    display:block;

    margin-top:
        5px;

    color:
        #D5C9C0;

    font-size:
        6px;

    line-height:
        1.5;

}


.checkout-side-note{

    margin-top:
        10px;

    padding:
        10px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .10
        );

    border-radius:
        10px;

    color:
        #D5C9C0;

    background:
        rgba(
            255,
            255,
            255,
            .055
        );

    font-size:
        6.5px;

    line-height:
        1.55;

}


/* PENDING / ACTIVE */

.checkout-block-message{

    padding:
        16px;

}


.checkout-message{

    padding:
        13px;

    border:
        1px solid
        #E8DEC8;

    border-radius:
        12px;

    color:
        #80611F;

    background:
        #FFF8E8;

    font-size:
        7.2px;

    line-height:
        1.6;

}


.checkout-dashboard-button{

    width:
        100%;

    min-height:
        40px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    margin-top:
        10px;

    border-radius:
        10px;

    color:
        var(--co-brown);

    background:
        #F5F0E8;

    font-size:
        7px;

    font-weight:
        900;

    text-decoration:none;

}


@media(max-width:820px){

    .checkout-shell{

        grid-template-columns:
            1fr;

    }

    .checkout-side{

        order:
            -1;

    }

}


@media(max-width:600px){

    .checkout-container{

        width:
            calc(
                100% - 16px
            );

    }

    .checkout-main,
    .checkout-side{

        padding:
            18px;

    }

    .checkout-title{

        font-size:
            24px;

    }

    .checkout-qr img,
    .checkout-qr-placeholder{

        width:
            165px;

        height:
            165px;

    }

}

</style>

</head>


<body>


<?php include 'includes/navbar.php'; ?>


<main
    class="
        checkout-page
    "
>


<div
    class="
        checkout-container
    "
>


<?php if (
    $error !== ''
): ?>


<div
    class="
        checkout-card
        checkout-block-message
    "
>


<div
    class="
        checkout-message
    "
>

<?= checkout_e(
    $error
) ?>

</div>


<a
    href="subscriptions.php"
    class="
        checkout-dashboard-button
    "
>

Back to Plans

</a>


</div>


<?php elseif (
    $activeSubscription
    &&
    (int)$activeSubscription[
        'plan_id'
    ]
    !==
    (int)$plan['id']
): ?>


<div
    class="
        checkout-card
        checkout-block-message
    "
>


<div
    class="
        checkout-message
    "
>

<strong>
    You already have an active subscription.
</strong>

<br>

Please use or manage your current membership
before requesting another plan.

</div>


<a
    href="subscriptions.php"
    class="
        checkout-dashboard-button
    "
>

View Membership

</a>


</div>


<?php elseif (
    $pendingRequest
): ?>


<div
    class="
        checkout-card
        checkout-block-message
    "
>


<div
    class="
        checkout-message
    "
>

<strong>
    Your request is already pending.
</strong>

<br>

Please wait for the administrator to review
your submitted activation request.

</div>


<a
    href="subscriptions.php"
    class="
        checkout-dashboard-button
    "
>

View Request Status

</a>


</div>


<?php else: ?>


<div
    class="
        checkout-shell
    "
>


<section
    class="
        checkout-card
        checkout-main
    "
>


<div
    class="
        checkout-kicker
    "
>

CHECKOUT

</div>


<h1
    class="
        checkout-title
    "
>

<?= checkout_e(
    $plan['name']
) ?>

</h1>


<p
    class="
        checkout-subtitle
    "
>

<?=
$isTrial

    ? 'This is a free trial. No payment, screenshot or administrator approval is required.'

    : 'Complete the payment using the ExamSphere QR code, upload the successful payment screenshot and send the request for administrator approval.'
?>

</p>


<div
    class="
        checkout-summary
    "
>


<div
    class="
        checkout-summary-row
    "
>

<span>
    Plan
</span>

<strong>

<?= checkout_e(
    $plan['name']
) ?>

</strong>

</div>


<div
    class="
        checkout-summary-row
    "
>

<span>
    Duration
</span>

<strong>

<?= (int)$plan[
    'duration_months'
] ?>

month(s)

</strong>

</div>


<div
    class="
        checkout-summary-row
    "
>

<span>
    Activation
</span>

<strong>

<?= $isTrial
    ? 'Instant'
    : 'Admin Approval' ?>

</strong>

</div>


<div
    class="
        checkout-summary-row
    "
>

<span>
    Amount
</span>

<strong>

<?= $isTrial
    ? 'FREE'
    : '₹' .
        number_format(
            $price,
            2
        ) ?>

</strong>

</div>


</div>


<?php if (
    $isTrial
): ?>


<div
    class="
        checkout-free
    "
>


<i
    class="
        fa-solid
        fa-bolt
    "
    style="
        margin-right:5px;
    "
></i>


<strong>
    Free Trial
</strong>


<br>


Click the button below.

Your free trial will activate immediately.

You will not see a payment scanner,
payment screenshot upload or approval step.


</div>


<form
    id="trialForm"
    action="
        ajax/submit_subscription_request.php
    "
    method="POST"
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= checkout_e(
        $csrf
    ) ?>"
>


<input
    type="hidden"
    name="plan_id"
    value="<?= (int)$plan['id'] ?>"
>


<input
    type="hidden"
    name="request_type"
    value="Trial"
>


<button
    class="
        checkout-submit
    "
    type="submit"
    id="trialSubmit"
>

<i
    class="
        fa-solid
        fa-bolt
    "
></i>

Proceed & Activate Free Trial

</button>


</form>


<?php else: ?>


<div
    class="
        checkout-steps
    "
>


<div
    class="
        checkout-step
    "
>

<div
    class="
        checkout-step-number
    "
>
    1
</div>


<div>

<strong>
    Scan the payment QR
</strong>

<span>
    Pay exactly the amount shown on this page.
</span>

</div>

</div>


<div
    class="
        checkout-step
    "
>

<div
    class="
        checkout-step-number
    "
>
    2
</div>


<div>

<strong>
    Complete the payment
</strong>

<span>
    Confirm the payment successfully in your UPI or banking app.
</span>

</div>

</div>


<div
    class="
        checkout-step
    "
>

<div
    class="
        checkout-step-number
    "
>
    3
</div>


<div>

<strong>
    Upload payment screenshot
</strong>

<span>
    The screenshot must clearly show successful payment.
</span>

</div>

</div>


<div
    class="
        checkout-step
    "
>

<div
    class="
        checkout-step-number
    "
>
    4
</div>


<div>

<strong>
    Request approval
</strong>

<span>
    Admin will review the proof and activate your membership.
</span>

</div>

</div>


</div>


<form
    action="
        ajax/submit_subscription_request.php
    "
    method="POST"
    enctype="multipart/form-data"
    id="subscriptionCheckoutForm"
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= checkout_e(
        $csrf
    ) ?>"
>


<input
    type="hidden"
    name="plan_id"
    value="<?= (int)$plan['id'] ?>"
>


<input
    type="hidden"
    name="request_type"
    value="Paid"
>


<div
    class="
        checkout-upload
    "
>


<label
    for="paymentScreenshot"
>

Payment Screenshot
<span style="color:#A84538">
    *
</span>

</label>


<input
    class="
        checkout-file
    "
    id="paymentScreenshot"
    name="payment_screenshot"
    type="file"
    accept="
        image/jpeg,
        image/png,
        image/webp
    "
    required
>


<p
    class="
        checkout-help
    "
>

Required.

Only JPG, PNG and WebP.

Maximum size 5 MB.

The payment confirmation,
amount and transaction details
should be readable.

</p>


</div>


<label
    class="
        checkout-confirm
    "
>


<input
    type="checkbox"
    name="payment_confirmed"
    value="1"
    required
>


<span>

I confirm that I have completed the payment
for this plan and the uploaded screenshot
is genuine.

</span>


</label>


<button
    class="
        checkout-submit
    "
    type="submit"
    id="checkoutSubmit"
>


<i
    class="
        fa-solid
        fa-paper-plane
    "
></i>

Proceed & Request Approval

</button>


</form>


<?php endif; ?>


<a
    href="subscriptions.php"
    class="
        checkout-back
    "
>

<i
    class="
        fa-solid
        fa-arrow-left
    "
></i>

Back to Membership Plans

</a>


</section>


<aside
    class="
        checkout-card
        checkout-side
    "
>


<div
    class="
        checkout-side-content
    "
>


<div
    class="
        checkout-side-kicker
    "
>

MANUAL PAYMENT

</div>


<h2>
    Pay securely.
</h2>


<p>

Scan the ExamSphere QR code,
complete the payment and keep
your successful transaction
screenshot ready.

</p>


<?php if (
    !$isTrial
): ?>


<div
    class="
        checkout-side-price
    "
>

₹<?= number_format(
    $price,
    2
) ?>

</div>


<div
    class="
        checkout-qr
    "
>


<?php if (
    $qrExists
): ?>


<img
    src="../assets/images/payment-qr.png"
    alt="
        ExamSphere payment QR code
    "
>


<?php else: ?>


<div
    class="
        checkout-qr-placeholder
    "
>


<div>

<strong>
    Payment QR not configured
</strong>


<span>

Put your real QR image at:

<br>

<b>
    assets/images/payment-qr.png
</b>

</span>

</div>


</div>


<?php endif; ?>


</div>


<div
    class="
        checkout-side-note
    "
>

<i
    class="
        fa-solid
        fa-circle-info
    "
    style="
        margin-right:4px;
    "
></i>

After successful payment,
upload the screenshot on the left
and submit your approval request.

</div>


<?php else: ?>


<div
    class="
        checkout-side-note
    "
    style="
        margin-top:16px;
    "
>

<i
    class="
        fa-solid
        fa-gift
    "
    style="
        margin-right:4px;
    "
></i>

This is a free trial.

No QR payment,
no screenshot and
no approval is required.

</div>


<?php endif; ?>


</div>


</aside>


</div>


<?php endif; ?>


</div>


</main>


<script>


document
    .getElementById(
        'subscriptionCheckoutForm'
    )
    ?.addEventListener(
        'submit',
        function(){

            const button =
                document.getElementById(
                    'checkoutSubmit'
                );


            if (
                button
            ){

                button.disabled =
                    true;


                button.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';

            }

        }
    );


document
    .getElementById(
        'trialForm'
    )
    ?.addEventListener(
        'submit',
        function(){

            const button =
                document.getElementById(
                    'trialSubmit'
                );


            if (
                button
            ){

                button.disabled =
                    true;


                button.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> Activating...';

            }

        }
    );


</script>


</body>

</html>