<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {
    header('Location: ../auth/login.php');
    exit;
}


$studentId =
    (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function subscription_escape(
    mixed $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function subscription_price(
    mixed $value
): string {

    return number_format(
        (float) $value,
        2,
        '.',
        ','
    );
}


function subscription_date(
    mixed $value
): string {

    if (
        empty($value)
    ) {
        return '-';
    }


    try {

        return (
            new DateTimeImmutable(
                (string) $value
            )
        )->format(
            'd M Y'
        );

    } catch (Throwable) {

        return '-';
    }
}


/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$flashMessage =
    trim(
        (string) (
            $_SESSION['payment_success']
            ?? ''
        )
    );


$flashType =
    'success';


unset(
    $_SESSION['payment_success']
);


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$csrfToken =
    function_exists('csrf_token')
        ? csrf_token()
        : '';


/*
|--------------------------------------------------------------------------
| POST → SELECT PLAN
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $postedToken =
        trim(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        );


    $csrfValid =
        $postedToken !== '' &&
        $csrfToken !== '' &&
        hash_equals(
            $csrfToken,
            $postedToken
        );


    if (
        !$csrfValid
    ) {

        $_SESSION['payment_success'] =
            'Your session has expired. Please refresh the page and try again.';

        header(
            'Location: subscriptions.php'
        );

        exit;
    }


    $planId =
        filter_input(
            INPUT_POST,
            'plan_id',
            FILTER_VALIDATE_INT
        );


    if (
        $planId === false ||
        $planId === null ||
        $planId <= 0
    ) {

        $_SESSION['payment_success'] =
            'Please select a valid subscription plan.';

        header(
            'Location: subscriptions.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY ACTIVE PLAN
    |--------------------------------------------------------------------------
    */

    try {

        $planValidation =
            $conn->prepare("
                SELECT

                    id,
                    name,
                    duration_months,
                    price,
                    status

                FROM subscription_plans

                WHERE

                    id = ?

                    AND status = 'Active'

                LIMIT 1
            ");


        $planValidation->execute([
            $planId
        ]);


        $selectedPlan =
            $planValidation->fetch(
                PDO::FETCH_ASSOC
            );


    } catch (Throwable $exception) {

        error_log(
            'Subscription plan validation failed: ' .
            $exception->getMessage()
        );


        $_SESSION['payment_success'] =
            'Unable to validate the selected plan. Please try again.';

        header(
            'Location: subscriptions.php'
        );

        exit;
    }


    if (
        !$selectedPlan
    ) {

        $_SESSION['payment_success'] =
            'The selected subscription plan is not available.';

        header(
            'Location: subscriptions.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | REDIRECT TO CHECKOUT
    |--------------------------------------------------------------------------
    */

    header(
        'Location: checkout.php?plan_id=' .
        (int) $selectedPlan['id']
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| EXPIRE CURRENT STUDENT SUBSCRIPTIONS
|--------------------------------------------------------------------------
|
| Only the logged-in student's outdated Active subscriptions are changed.
|--------------------------------------------------------------------------
*/

try {

    $expireStatement =
        $conn->prepare("
            UPDATE subscriptions

            SET
                status = 'Expired'

            WHERE

                student_id = ?

                AND status = 'Active'

                AND end_date < CURDATE()
        ");


    $expireStatement->execute([
        $studentId
    ]);

} catch (Throwable $exception) {

    error_log(
        'Student subscription expiration update failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| LOAD ACTIVE PLANS
|--------------------------------------------------------------------------
*/

$plans = [];


try {

    $plansStatement =
        $conn->query("
            SELECT

                id,
                name,
                duration_months,
                price,
                description,
                benefits,
                status,
                created_at

            FROM subscription_plans

            WHERE
                status = 'Active'

            ORDER BY

                duration_months ASC,
                price ASC,
                id ASC
        ");


    $plans =
        $plansStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Subscription plans load failed: ' .
        $exception->getMessage()
    );

    $plans = [];
}


/*
|--------------------------------------------------------------------------
| ACTIVE SUBSCRIPTION
|--------------------------------------------------------------------------
*/

$activeSubscription =
    null;


try {

    $activeStatement =
        $conn->prepare("
            SELECT

                s.id,
                s.student_id,
                s.plan_id,

                s.start_date,
                s.end_date,

                s.status,
                s.created_at,

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

                AND s.status = 'Active'

                AND s.start_date <= CURDATE()

                AND s.end_date >= CURDATE()

            ORDER BY

                s.end_date DESC,
                s.id DESC

            LIMIT 1
        ");


    $activeStatement->execute([
        $studentId
    ]);


    $activeSubscription =
        $activeStatement->fetch(
            PDO::FETCH_ASSOC
        ) ?: null;

} catch (Throwable $exception) {

    error_log(
        'Active subscription load failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| HAS SUBSCRIPTION
|--------------------------------------------------------------------------
*/

$hasSubscription =
    $activeSubscription !== null;


/*
|--------------------------------------------------------------------------
| DAYS REMAINING
|--------------------------------------------------------------------------
*/

$daysRemaining =
    0;


if (
    $activeSubscription
)
{
    try {

        $today =
            new DateTimeImmutable(
                'today'
            );


        $endDate =
            new DateTimeImmutable(
                (string) $activeSubscription[
                    'end_date'
                ]
            );


        $daysRemaining =
            max(
                0,
                (int) $today->diff(
                    $endDate
                )->format('%r%a')
            );

    } catch (Throwable) {

        $daysRemaining =
            0;
    }
}


/*
|--------------------------------------------------------------------------
| PAYMENT HISTORY
|--------------------------------------------------------------------------
*/

$paymentHistory = [];


try {

    $historyStatement =
        $conn->prepare("
            SELECT

                sp.id,

                sp.plan_id,
                sp.subscription_id,

                sp.amount,

                sp.reference_no,

                sp.payment_status,
                sp.payment_method,
                sp.gateway_order_id,
                sp.gateway_payment_id,

                sp.paid_at,
                sp.created_at,

                p.name AS plan_name,

                p.duration_months

            FROM subscription_payments sp

            INNER JOIN subscription_plans p
                ON p.id = sp.plan_id

            WHERE

                sp.student_id = ?

            ORDER BY

                sp.created_at DESC,
                sp.id DESC

            LIMIT 10
        ");


    $historyStatement->execute([
        $studentId
    ]);


    $paymentHistory =
        $historyStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Subscription payment history failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| PAYMENT COUNTERS
|--------------------------------------------------------------------------
*/

$totalPayments =
    count(
        $paymentHistory
    );


$paidPayments =
    0;


$pendingPayments =
    0;


$failedPayments =
    0;


foreach (
    $paymentHistory as $payment
) {

    $status =
        (string) (
            $payment['payment_status']
            ?? ''
        );


    if (
        $status === 'Paid'
    ) {

        $paidPayments++;

    } elseif (
        $status === 'Pending'
    ) {

        $pendingPayments++;

    } elseif (
        $status === 'Failed'
        ||
        $status === 'Cancelled'
    ) {

        $failedPayments++;
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT PLAN ID
|--------------------------------------------------------------------------
*/

$currentPlanId =
    $hasSubscription
        ? (int) $activeSubscription[
            'plan_id'
        ]
        : 0;


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
        content="#5D4037"
    >


    <title>
        Subscription Plans | ExamSphere
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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
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
        href="assets/css/dashboard.css"
    >


    <link
        rel="stylesheet"
        href="../assets/css/main.css"
    >


    <style>

        :root {

            --sub-brown-dark:
                #3E2723;

            --sub-brown:
                #5D4037;

            --sub-brown-light:
                #795548;

            --sub-olive:
                #556B2F;

            --sub-olive-dark:
                #465925;

            --sub-cream:
                #F5F5DC;

            --sub-cream-light:
                #FBFAF5;

            --sub-text:
                #332E2A;

            --sub-muted:
                #78716B;

            --sub-border:
                #E4DED4;

            --sub-green:
                #2E7D4D;

            --sub-red:
                #A7443B;

            --sub-orange:
                #B86D1C;
        }


        * {
            box-sizing:
                border-box;
        }


        body {

            background:

                radial-gradient(
                    circle at 5% 0%,
                    rgba(
                        85,
                        107,
                        47,
                        .09
                    ),
                    transparent 24%
                ),

                radial-gradient(
                    circle at 100% 40%,
                    rgba(
                        93,
                        64,
                        55,
                        .08
                    ),
                    transparent 28%
                ),

                var(--sub-cream);

            color:
                var(--sub-text);

            font-family:
                Poppins,
                Arial,
                sans-serif;
        }


        .subscription-page {

            padding:
                35px 0 70px;
        }


        .subscription-hero {

            position:
                relative;

            overflow:
                hidden;

            padding:
                48px 30px;

            border-radius:
                30px;

            color:
                #FFFFFF;

            background:
                linear-gradient(
                    125deg,
                    var(--sub-brown-dark),
                    var(--sub-brown)
                    48%,
                    var(--sub-olive)
                );

            box-shadow:
                0 25px 70px
                rgba(
                    62,
                    39,
                    35,
                    .16
                );
        }


        .subscription-hero::before,
        .subscription-hero::after {

            content:
                "";

            position:
                absolute;

            border-radius:
                50%;

            pointer-events:
                none;
        }


        .subscription-hero::before {

            width:
                330px;

            height:
                330px;

            top:
                -170px;

            right:
                -80px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .07
                );
        }


        .subscription-hero::after {

            width:
                190px;

            height:
                190px;

            bottom:
                -100px;

            left:
                -50px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .05
                );
        }


        .subscription-kicker {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            margin-bottom:
                10px;

            font-size:
                10px;

            font-weight:
                800;

            letter-spacing:
                1.5px;

            text-transform:
                uppercase;

            opacity:
                .85;
        }


        .subscription-hero h1 {

            position:
                relative;

            z-index:
                1;

            margin:
                0;

            font-size:
                clamp(
                    28px,
                    5vw,
                    46px
                );

            font-weight:
                800;

            line-height:
                1.15;
        }


        .subscription-hero h1 em {

            color:
                #DDE8C8;

            font-style:
                normal;
        }


        .subscription-hero p {

            position:
                relative;

            z-index:
                1;

            max-width:
                760px;

            margin:
                14px auto 0;

            color:
                rgba(
                    255,
                    255,
                    255,
                    .86
                );

            font-size:
                14px;

            line-height:
                1.7;
        }


        .subscription-feature-list {

            position:
                relative;

            z-index:
                1;

            display:
                flex;

            flex-wrap:
                wrap;

            justify-content:
                center;

            gap:
                9px;

            margin-top:
                24px;
        }


        .subscription-feature {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            padding:
                9px 13px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .17
                );

            border-radius:
                999px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .08
                );

            font-size:
                10px;

            font-weight:
                600;
        }


        .flash-alert {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                11px;

            margin:
                20px 0 0;

            padding:
                14px 16px;

            border:
                1px solid
                #D4E2C7;

            border-radius:
                16px;

            color:
                #385522;

            background:
                #F1F6EC;
        }


        .flash-alert i {

            margin-top:
                2px;
        }


        .active-membership {

            margin-top:
                22px;

            padding:
                22px;

            border:
                1px solid
                #D6E4C9;

            border-radius:
                23px;

            background:
                linear-gradient(
                    135deg,
                    #F4F8EF,
                    #EDF4E7
                );

            box-shadow:
                0 16px 40px
                rgba(
                    85,
                    107,
                    47,
                    .07
                );
        }


        .active-membership-top {

            display:
                flex;

            align-items:
                flex-start;

            justify-content:
                space-between;

            gap:
                16px;
        }


        .active-membership-title {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;
        }


        .active-membership-icon {

            width:
                44px;

            height:
                44px;

            flex:
                0 0 44px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                14px;

            color:
                #FFFFFF;

            background:
                var(--sub-olive);

            box-shadow:
                0 10px 25px
                rgba(
                    85,
                    107,
                    47,
                    .18
                );
        }


        .active-membership-title small {

            display:
                block;

            margin-bottom:
                3px;

            color:
                var(--sub-olive);

            font-size:
                9px;

            font-weight:
                800;

            letter-spacing:
                1.2px;

            text-transform:
                uppercase;
        }


        .active-membership-title strong {

            display:
                block;

            color:
                var(--sub-brown-dark);

            font-size:
                17px;

            font-weight:
                800;
        }


        .active-badge {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            padding:
                8px 11px;

            border-radius:
                999px;

            color:
                var(--sub-green);

            background:
                #FFFFFF;

            font-size:
                10px;

            font-weight:
                800;
        }


        .membership-meta {

            display:
                grid;

            grid-template-columns:
                repeat(
                    3,
                    1fr
                );

            gap:
                10px;

            margin-top:
                18px;
        }


        .membership-meta-box {

            padding:
                13px;

            border:
                1px solid
                rgba(
                    85,
                    107,
                    47,
                    .12
                );

            border-radius:
                15px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .70
                );
        }


        .membership-meta-box small {

            display:
                block;

            color:
                var(--sub-muted);

            font-size:
                9px;
        }


        .membership-meta-box strong {

            display:
                block;

            margin-top:
                4px;

            color:
                var(--sub-brown);

            font-size:
                15px;

            font-weight:
                800;
        }


        .membership-note {

            margin:
                14px 0 0;

            color:
                var(--sub-muted);

            font-size:
                10px;

            line-height:
                1.6;
        }


        .plan-section {

            margin-top:
                36px;
        }


        .section-heading {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                flex-end;

            gap:
                15px;

            margin-bottom:
                18px;
        }


        .section-heading small {

            display:
                block;

            color:
                var(--sub-olive);

            font-size:
                9px;

            font-weight:
                800;

            letter-spacing:
                1.5px;
        }


        .section-heading h2 {

            margin:
                4px 0 0;

            color:
                var(--sub-brown-dark);

            font-size:
                25px;

            font-weight:
                800;
        }


        .section-heading span {

            color:
                var(--sub-muted);

            font-size:
                10px;

            font-weight:
                600;
        }


        .plan-grid {

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
                18px;
        }


        .plan-card {

            position:
                relative;

            overflow:
                hidden;

            min-height:
                100%;

            padding:
                25px;

            border:
                1px solid
                var(--sub-border);

            border-top:
                5px solid
                var(--sub-brown-light);

            border-radius:
                24px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .95
                );

            box-shadow:
                0 15px 42px
                rgba(
                    62,
                    39,
                    35,
                    .07
                );

            transition:
                transform .25s ease,
                box-shadow .25s ease,
                border-color .25s ease;
        }


        .plan-card:hover {

            transform:
                translateY(
                    -7px
                );

            box-shadow:
                0 24px 55px
                rgba(
                    62,
                    39,
                    35,
                    .12
                );
        }


        .plan-card.featured {

            border-top-color:
                var(--sub-olive);

            box-shadow:
                0 22px 60px
                rgba(
                    85,
                    107,
                    47,
                    .12
                );
        }


        .plan-ribbon {

            position:
                absolute;

            top:
                17px;

            right:
                17px;

            padding:
                6px 9px;

            border-radius:
                999px;

            color:
                #FFFFFF;

            background:
                var(--sub-olive);

            font-size:
                8px;

            font-weight:
                800;

            letter-spacing:
                .7px;

            text-transform:
                uppercase;
        }


        .plan-icon {

            width:
                49px;

            height:
                49px;

            display:
                grid;

            place-items:
                center;

            margin-bottom:
                16px;

            border-radius:
                15px;

            color:
                var(--sub-brown);

            background:
                var(--sub-cream);

            font-size:
                18px;
        }


        .featured .plan-icon {

            color:
                var(--sub-olive);

            background:
                #EEF4E7;
        }


        .plan-card h3 {

            margin:
                0;

            color:
                var(--sub-brown-dark);

            font-size:
                21px;

            font-weight:
                800;
        }


        .plan-duration {

            margin-top:
                3px;

            color:
                var(--sub-muted);

            font-size:
                10px;

            font-weight:
                500;
        }


        .plan-price {

            margin:
                18px 0 10px;

            color:
                var(--sub-brown);

            font-size:
                34px;

            font-weight:
                800;

            line-height:
                1;
        }


        .plan-price span {

            color:
                var(--sub-muted);

            font-size:
                10px;

            font-weight:
                600;
        }


        .plan-description {

            min-height:
                42px;

            margin:
                0;

            color:
                var(--sub-muted);

            font-size:
                11px;

            line-height:
                1.65;
        }


        .plan-benefits {

            margin:
                18px 0 20px;

            padding:
                15px;

            border:
                1px solid
                var(--sub-border);

            border-radius:
                15px;

            background:
                var(--sub-cream-light);
        }


        .plan-benefits-title {

            margin-bottom:
                9px;

            color:
                var(--sub-brown);

            font-size:
                10px;

            font-weight:
                800;
        }


        .plan-benefits-text {

            color:
                var(--sub-muted);

            font-size:
                10px;

            line-height:
                1.65;
        }


        .plan-button {

            width:
                100%;

            min-height:
                48px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            border:
                0;

            border-radius:
                13px;

            color:
                #FFFFFF;

            background:
                var(--sub-brown);

            font-size:
                11px;

            font-weight:
                800;

            cursor:
                pointer;

            transition:
                .2s ease;
        }


        .plan-button:hover {

            background:
                var(--sub-olive);

            transform:
                translateY(
                    -1px
                );
        }


        .plan-button.current {

            color:
                var(--sub-green);

            background:
                #EEF5E9;
        }


        .plan-button.current:hover {

            color:
                #FFFFFF;

            background:
                var(--sub-olive);
        }


        .payment-section {

            margin-top:
                40px;

            padding:
                24px;

            border:
                1px solid
                var(--sub-border);

            border-radius:
                23px;

            background:
                #FFFFFF;

            box-shadow:
                0 15px 42px
                rgba(
                    62,
                    39,
                    35,
                    .06
                );
        }


        .payment-table {

            margin:
                0;

            --bs-table-bg:
                transparent;
        }


        .payment-table th {

            color:
                var(--sub-muted);

            font-size:
                9px;

            font-weight:
                800;

            letter-spacing:
                .5px;

            text-transform:
                uppercase;

            border-bottom:
                1px solid
                var(--sub-border);
        }


        .payment-table td {

            color:
                var(--sub-text);

            font-size:
                11px;

            vertical-align:
                middle;

            border-bottom:
                1px solid
                #F1ECE6;
        }


        .payment-table tbody tr:last-child td {

            border-bottom:
                0;
        }


        .status-badge {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                5px;

            padding:
                6px 8px;

            border-radius:
                999px;

            font-size:
                8px;

            font-weight:
                800;
        }


        .status-paid {

            color:
                var(--sub-green);

            background:
                #EDF7EF;
        }


        .status-pending {

            color:
                #8A5A13;

            background:
                #FFF4DF;
        }


        .status-failed {

            color:
                var(--sub-red);

            background:
                #FFF0EE;
        }


        .history-empty {

            padding:
                35px 15px;

            text-align:
                center;

            color:
                var(--sub-muted);
        }


        .history-empty i {

            display:
                block;

            margin-bottom:
                10px;

            color:
                var(--sub-olive);

            font-size:
                27px;
        }


        .trust-strip {

            display:
                grid;

            grid-template-columns:
                repeat(
                    3,
                    1fr
                );

            gap:
                12px;

            margin-top:
                28px;
        }


        .trust-item {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                11px;

            padding:
                16px;

            border:
                1px solid
                var(--sub-border);

            border-radius:
                17px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .84
                );
        }


        .trust-icon {

            width:
                35px;

            height:
                35px;

            flex:
                0 0 35px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                11px;

            color:
                var(--sub-olive);

            background:
                #EEF3E8;
        }


        .trust-item strong {

            display:
                block;

            color:
                var(--sub-brown);

            font-size:
                10px;

            font-weight:
                800;
        }


        .trust-item small {

            display:
                block;

            margin-top:
                2px;

            color:
                var(--sub-muted);

            font-size:
                9px;

            line-height:
                1.55;
        }


        @media (
            max-width: 1000px
        ) {

            .plan-grid {

                grid-template-columns:
                    1fr 1fr;
            }


            .trust-strip {

                grid-template-columns:
                    1fr 1fr;
            }

        }


        @media (
            max-width: 720px
        ) {

            .subscription-page {

                padding-top:
                    20px;
            }


            .subscription-hero {

                padding:
                    35px 18px;

                border-radius:
                    23px;
            }


            .membership-meta {

                grid-template-columns:
                    1fr;
            }


            .active-membership-top {

                flex-direction:
                    column;
            }


            .plan-grid {

                grid-template-columns:
                    1fr;
            }


            .trust-strip {

                grid-template-columns:
                    1fr;
            }


            .section-heading {

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }


            .payment-section {

                padding:
                    16px;
            }


            .payment-table {

                min-width:
                    700px;
            }

        }


    </style>

</head>


<body>


<?php include 'includes/navbar.php'; ?>


<main class="subscription-page">

    <div class="container">


        <!-- =================================================
             HERO
        ================================================== -->

        <section class="subscription-hero text-center">

            <span class="subscription-kicker">

                <i
                    class="fa-solid fa-crown"
                ></i>

                ExamSphere Membership

            </span>


            <h1>

                Choose your
                <em>learning advantage.</em>

            </h1>


            <p>

                Unlock subscription-enabled study materials,
                premium practice examinations and eligible
                live-exam access through one simple membership.

            </p>


            <div class="subscription-feature-list">

                <span class="subscription-feature">

                    <i
                        class="
                            fa-solid
                            fa-book-open
                        "
                    ></i>

                    Restricted study material

                </span>


                <span class="subscription-feature">

                    <i
                        class="
                            fa-solid
                            fa-pen-to-square
                        "
                    ></i>

                    Premium practice exams

                </span>


                <span class="subscription-feature">

                    <i
                        class="
                            fa-solid
                            fa-tower-broadcast
                        "
                    ></i>

                    Eligible live exams

                </span>


                <span class="subscription-feature">

                    <i
                        class="
                            fa-solid
                            fa-chart-line
                        "
                    ></i>

                    Continuous preparation

                </span>

            </div>

        </section>


        <!-- =================================================
             FLASH
        ================================================== -->

        <?php if (
            $flashMessage !== ''
        ): ?>

            <div
                class="
                    flash-alert
                "
                role="alert"
            >

                <i
                    class="
                        fa-solid
                        fa-circle-check
                    "
                ></i>


                <div>

                    <?= subscription_escape(
                        $flashMessage
                    ) ?>

                </div>

            </div>

        <?php endif; ?>


        <!-- =================================================
             ACTIVE MEMBERSHIP
        ================================================== -->

        <?php if (
            $activeSubscription
        ): ?>

            <section
                class="active-membership"
            >

                <div
                    class="
                        active-membership-top
                    "
                >

                    <div
                        class="
                            active-membership-title
                        "
                    >

                        <div
                            class="active-membership-icon"
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-crown
                                "
                            ></i>

                        </div>


                        <div>

                            <small>
                                Current membership
                            </small>


                            <strong>

                                <?= subscription_escape(
                                    $activeSubscription[
                                        'plan_name'
                                    ]
                                ) ?>

                            </strong>

                        </div>

                    </div>


                    <span
                        class="active-badge"
                    >

                        <i
                            class="
                                fa-solid
                                fa-circle-check
                            "
                        ></i>

                        Active

                    </span>

                </div>


                <div
                    class="membership-meta"
                >


                    <div
                        class="membership-meta-box"
                    >

                        <small>
                            Started
                        </small>


                        <strong>

                            <?= subscription_date(
                                $activeSubscription[
                                    'start_date'
                                ]
                            ) ?>

                        </strong>

                    </div>


                    <div
                        class="membership-meta-box"
                    >

                        <small>
                            Valid until
                        </small>


                        <strong>

                            <?= subscription_date(
                                $activeSubscription[
                                    'end_date'
                                ]
                            ) ?>

                        </strong>

                    </div>


                    <div
                        class="membership-meta-box"
                    >

                        <small>
                            Remaining
                        </small>


                        <strong>

                            <?= $daysRemaining ?>

                            <?= $daysRemaining === 1
                                ? 'day'
                                : 'days'
                            ?>

                        </strong>

                    </div>

                </div>


                <p
                    class="membership-note"
                >

                    Your current membership automatically
                    satisfies subscription-required access
                    while its validity period remains active.

                </p>

            </section>

        <?php endif; ?>


        <!-- =================================================
             PLANS
        ================================================== -->

        <section class="plan-section">


            <div class="section-heading">

                <div>

                    <small>
                        MEMBERSHIP OPTIONS
                    </small>


                    <h2>
                        Select your plan
                    </h2>

                </div>


                <span>

                    <?= count($plans) ?>

                    active

                    <?= count($plans) === 1
                        ? 'plan'
                        : 'plans'
                    ?>

                </span>

            </div>


            <?php if (
                !empty($plans)
            ): ?>

                <div class="plan-grid">


                    <?php foreach (
                        $plans as $plan
                    ): ?>


                        <?php

                        $planId =
                            (int) $plan['id'];


                        $duration =
                            (int) $plan[
                                'duration_months'
                            ];


                        $isFeatured =
                            $duration === 6;


                        $isCurrent =
                            $currentPlanId ===
                            $planId;

                        ?>


                        <article
                            class="
                                plan-card
                                <?= $isFeatured
                                    ? 'featured'
                                    : ''
                                ?>
                            "
                        >


                            <?php if (
                                $isFeatured
                            ): ?>

                                <span
                                    class="plan-ribbon"
                                >

                                    Best Value

                                </span>

                            <?php endif; ?>


                            <div
                                class="plan-icon"
                            >

                                <i
                                    class="
                                        fa-solid
                                        <?= $duration >= 6
                                            ? 'fa-gem'
                                            : (
                                                $duration >= 3
                                                    ? 'fa-star'
                                                    : 'fa-bolt'
                                            )
                                        ?>
                                    "
                                ></i>

                            </div>


                            <h3>

                                <?= subscription_escape(
                                    $plan['name']
                                ) ?>

                            </h3>


                            <div
                                class="plan-duration"
                            >

                                <?= $duration ?>

                                <?= $duration === 1
                                    ? 'month'
                                    : 'months'
                                ?>

                                of access

                            </div>


                            <div
                                class="plan-price"
                            >

                                ₹<?= subscription_escape(
                                    number_format(
                                        (float) $plan['price'],
                                        0
                                    )
                                ) ?>


                                <span>

                                    /

                                    <?= $duration === 1
                                        ? 'month'
                                        : $duration . ' months'
                                    ?>

                                </span>

                            </div>


                            <p
                                class="plan-description"
                            >

                                <?= subscription_escape(
                                    $plan['description']
                                    ?? ''
                                ) ?>

                            </p>


                            <div
                                class="plan-benefits"
                            >

                                <div
                                    class="
                                        plan-benefits-title
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-check
                                            me-1
                                        "
                                    ></i>

                                    Included benefits

                                </div>


                                <div
                                    class="
                                        plan-benefits-text
                                    "
                                >

                                    <?= nl2br(
                                        subscription_escape(
                                            $plan['benefits']
                                            ?? ''
                                        )
                                    ) ?>

                                </div>

                            </div>


                            <form
                                method="POST"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= subscription_escape(
                                        $csrfToken
                                    ) ?>"
                                >


                                <input
                                    type="hidden"
                                    name="plan_id"
                                    value="<?= $planId ?>"
                                >


                                <button
                                    type="submit"
                                    class="
                                        plan-button
                                        <?= $isCurrent
                                            ? 'current'
                                            : ''
                                        ?>
                                    "
                                >

                                    <?php if (
                                        $isCurrent
                                    ): ?>

                                        <i
                                            class="
                                                fa-solid
                                                fa-rotate
                                            "
                                        ></i>

                                        Renew / extend

                                    <?php else: ?>

                                        <i
                                            class="
                                                fa-solid
                                                fa-lock
                                            "
                                        ></i>

                                        Choose payment method

                                    <?php endif; ?>


                                    <i
                                        class="
                                            fa-solid
                                            fa-arrow-right
                                        "
                                    ></i>

                                </button>

                            </form>

                        </article>

                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <section
                    class="
                        history-empty
                        border
                        rounded-4
                        bg-white
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-circle-exclamation
                        "
                    ></i>


                    No active subscription plans
                    are available at the moment.

                </section>


            <?php endif; ?>

        </section>


        <!-- =================================================
             PAYMENT HISTORY
        ================================================== -->

        <section
            class="payment-section"
        >

            <div class="section-heading">

                <div>

                    <small>
                        ACCOUNT HISTORY
                    </small>


                    <h2>
                        Membership purchases
                    </h2>

                </div>


                <span>

                    <?= $totalPayments ?>

                    recorded

                </span>

            </div>


            <?php if (
                !empty($paymentHistory)
            ): ?>

                <div
                    class="table-responsive"
                >

                    <table
                        class="
                            table
                            payment-table
                            align-middle
                        "
                    >

                        <thead>

                            <tr>

                                <th>
                                    Plan
                                </th>

                                <th>
                                    Amount
                                </th>

                                <th>
                                    Method
                                </th>

                                <th>
                                    Reference
                                </th>

                                <th>
                                    Razorpay
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $paymentHistory
                            as $payment
                        ): ?>


                            <?php

                            $paymentStatus =
                                (string) (
                                    $payment[
                                        'payment_status'
                                    ]
                                    ?? ''
                                );


                            $statusClass =
                                match (
                                    $paymentStatus
                                ) {

                                    'Paid' =>
                                        'status-paid',

                                    'Pending' =>
                                        'status-pending',

                                    default =>
                                        'status-failed'
                                };


                            $statusIcon =
                                match (
                                    $paymentStatus
                                ) {

                                    'Paid' =>
                                        'fa-circle-check',

                                    'Pending' =>
                                        'fa-clock',

                                    default =>
                                        'fa-circle-xmark'
                                };

                            ?>


                            <tr>


                                <td>

                                    <div
                                        class="
                                            fw-semibold
                                        "
                                    >

                                        <?= subscription_escape(
                                            $payment[
                                                'plan_name'
                                            ]
                                        ) ?>

                                    </div>


                                    <small
                                        class="text-muted"
                                    >

                                        <?= (int) (
                                            $payment[
                                                'duration_months'
                                            ]
                                            ?? 0
                                        ) ?>

                                        months

                                    </small>

                                </td>


                                <td>

                                    <strong>

                                        ₹<?= subscription_escape(
                                            number_format(
                                                (float) $payment[
                                                    'amount'
                                                ],
                                                2
                                            )
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= subscription_escape(
                                        $payment[
                                            'payment_method'
                                        ]
                                        ?: '—'
                                    ) ?>

                                </td>


                                <td>

                                    <code>

                                        <?= subscription_escape(
                                            $payment[
                                                'reference_no'
                                            ]
                                        ) ?>

                                    </code>

                                </td>


                                <td>

                                    <?php if (!empty($payment['gateway_order_id'])): ?>
                                        <small class="d-block">
                                            Order: <?= subscription_escape($payment['gateway_order_id']) ?>
                                        </small>
                                    <?php endif; ?>

                                    <?php if (!empty($payment['gateway_payment_id'])): ?>
                                        <small class="d-block">
                                            Payment: <?= subscription_escape($payment['gateway_payment_id']) ?>
                                        </small>
                                    <?php endif; ?>

                                    <?php if (empty($payment['gateway_order_id']) && empty($payment['gateway_payment_id'])): ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= subscription_date(
                                        $payment[
                                            'created_at'
                                        ]
                                    ) ?>

                                </td>


                                <td>

                                    <span
                                        class="
                                            status-badge
                                            <?= $statusClass ?>
                                        "
                                    >

                                        <i
                                            class="
                                                fa-solid
                                                <?= $statusIcon ?>
                                            "
                                        ></i>

                                        <?= subscription_escape(
                                            $paymentStatus
                                        ) ?>

                                    </span>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="history-empty">

                    <i
                        class="
                            fa-solid
                            fa-receipt
                        "
                    ></i>


                    <div>

                        No subscription payment history
                        is available yet.

                    </div>

                </div>


            <?php endif; ?>

        </section>


        <!-- =================================================
             TRUST STRIP
        ================================================== -->

        <section class="trust-strip">


            <div class="trust-item">

                <span class="trust-icon">

                    <i
                        class="
                            fa-solid
                            fa-shield-halved
                        "
                    ></i>

                </span>


                <span>

                    <strong>
                        Secure account access
                    </strong>


                    <small>

                        Subscription access is linked
                        directly to your authenticated
                        student account.

                    </small>

                </span>

            </div>


            <div class="trust-item">

                <span class="trust-icon">

                    <i
                        class="
                            fa-solid
                            fa-calendar-check
                        "
                    ></i>

                </span>


                <span>

                    <strong>
                        Date-based validity
                    </strong>


                    <small>

                        Membership remains active only
                        within its configured start and
                        end dates.

                    </small>

                </span>

            </div>


            <div class="trust-item">

                <span class="trust-icon">

                    <i
                        class="
                            fa-solid
                            fa-arrow-right
                        "
                    ></i>

                </span>


                <span>

                    <strong>
                        Access follows your plan
                    </strong>


                    <small>

                        Premium features check your current
                        subscription status before access.

                    </small>

                </span>

            </div>


        </section>


    </div>

</main>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const forms =
            document.querySelectorAll(
                '.plan-card form'
            );


        forms.forEach(
            function (form) {

                form.addEventListener(
                    'submit',
                    function (event) {

                        const button =
                            form.querySelector(
                                'button[type="submit"]'
                            );


                        if (
                            !button
                        ) {
                            return;
                        }


                        if (
                            button.dataset.submitting ===
                            '1'
                        ) {

                            event.preventDefault();

                            return;
                        }


                        button.dataset.submitting =
                            '1';


                        button.disabled =
                            true;


                        button.innerHTML = `

                            <i
                                class="
                                    fa-solid
                                    fa-spinner
                                    fa-spin
                                "
                            ></i>

                            Preparing checkout...

                        `;
                    }
                );

            }
        );

    }
);

</script>


</body>

</html>