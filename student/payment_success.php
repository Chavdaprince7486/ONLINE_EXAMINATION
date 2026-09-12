<?php
declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    header('Location: ../auth/login.php');
    exit;
}

$studentId = (int)$_SESSION['user_id'];
$receiptSession = $_SESSION['payment_receipt'] ?? null;

if (!is_array($receiptSession) || empty($receiptSession['reference'])) {
    header('Location: subscriptions.php');
    exit;
}

$reference = trim((string)$receiptSession['reference']);
$receipt = null;

try {
    $stmt = $conn->prepare(
        "SELECT
            sp.id AS payment_id,
            sp.amount,
            sp.reference_no,
            sp.payment_status,
            sp.payment_method,
            sp.gateway_order_id,
            sp.gateway_payment_id,
            sp.paid_at,
            sp.created_at,
            p.id AS plan_id,
            p.name AS plan_name,
            p.duration_months,
            s.id AS subscription_id,
            s.start_date,
            s.end_date,
            s.status AS subscription_status
         FROM subscription_payments sp
         INNER JOIN subscription_plans p ON p.id = sp.plan_id
         INNER JOIN subscriptions s
             ON s.id = sp.subscription_id
            AND s.student_id = sp.student_id
         WHERE sp.student_id = ?
           AND sp.reference_no = ?
           AND sp.payment_status = 'Paid'
         LIMIT 1"
    );

    $stmt->execute([$studentId, $reference]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        unset($_SESSION['payment_receipt']);
        header('Location: subscriptions.php');
        exit;
    }

    $receipt = [
        'plan' => (string)$payment['plan_name'],
        'amount' => (float)$payment['amount'],
        'method' => (string)($payment['payment_method'] ?? 'N/A'),
        'reference' => (string)$payment['reference_no'],
        'start_date' => (string)$payment['start_date'],
        'end_date' => (string)$payment['end_date'],
        'paid_at' => (string)($payment['paid_at'] ?? $payment['created_at']),
        'status' => (string)$payment['payment_status'],
        'subscription_status' => (string)$payment['subscription_status'],
        'razorpay_order_id' => (string)($payment['gateway_order_id'] ?? ''),
        'razorpay_payment_id' => (string)($payment['gateway_payment_id'] ?? ''),
    ];

    unset($_SESSION['payment_receipt']);

} catch (Throwable $e) {
    error_log($e->getMessage());

    unset($_SESSION['payment_receipt']);

    header('Location: subscriptions.php');
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatDateValue(string $date): string
{
    $timestamp = strtotime($date);

    return $timestamp !== false
        ? date('d M Y', $timestamp)
        : $date;
}

function formatDateTimeValue(string $dateTime): string
{
    $timestamp = strtotime($dateTime);

    return $timestamp !== false
        ? date('d M Y, h:i A', $timestamp)
        : $dateTime;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Payment Successful | ExamSphere</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/main.css">

    <style>
        body {
            background:
                linear-gradient(
                    135deg,
                    #f5f5dc 0%,
                    #faf7f0 48%,
                    #eef3ea 100%
                );
            min-height: 100vh;
        }

        .success-page {
            min-height: calc(100vh - 80px);
            display: flex;
            align-items: center;
        }

        .success-card {
            border: 1px solid rgba(93, 64, 55, .12);
            border-radius: 28px;
            background: rgba(255, 255, 255, .88);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: 0 24px 70px rgba(51, 51, 51, .13);
            overflow: hidden;
        }

        .success-icon {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.35rem;
            background: linear-gradient(135deg, #556b2f, #6f8d46);
            box-shadow: 0 15px 35px rgba(85, 107, 47, .25);
        }

        .receipt-box {
            border: 1px solid rgba(93, 64, 55, .10);
            border-radius: 20px;
            background: rgba(250, 247, 240, .82);
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 14px 0;
            border-bottom: 1px solid rgba(93, 64, 55, .10);
        }

        .receipt-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .receipt-row:first-child {
            padding-top: 0;
        }

        .receipt-label {
            color: #6b625e;
        }

        .receipt-value {
            color: #333333;
            text-align: right;
            font-weight: 700;
        }

        .reference-box {
            background: #ffffff;
            border: 1px dashed rgba(93, 64, 55, .25);
            border-radius: 14px;
            word-break: break-word;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(85, 107, 47, .10);
            color: #556b2f;
            font-size: .82rem;
            font-weight: 700;
        }

        @media (max-width: 575.98px) {
            .receipt-row {
                flex-direction: column;
                gap: 5px;
            }

            .receipt-value {
                text-align: left;
            }
        }
    </style>
</head>

<body>

<?php include 'includes/navbar.php'; ?>

<main class="success-page py-5">
    <div class="container">

        <div class="success-card mx-auto" style="max-width: 760px;">

            <div class="p-4 p-md-5 text-center">

                <span class="brand-badge mb-4">
                    <i class="fa-solid fa-shield-check"></i>
                    ExamSphere Subscription
                </span>

                <div class="mb-4">
                    <div class="success-icon text-white mx-auto">
                        <i class="fa-solid fa-check"></i>
                    </div>
                </div>

                <h1 class="fw-bold mb-2">
                    Payment Successful
                </h1>

                <p class="text-muted mb-0">
                    Your payment has been verified and your subscription
                    has been activated according to the recorded transaction.
                </p>

                <div class="receipt-box text-start p-4 mt-4">

                    <div class="receipt-row">
                        <span class="receipt-label">
                            Plan
                        </span>

                        <span class="receipt-value">
                            <?= e($receipt['plan']) ?>
                        </span>
                    </div>

                    <div class="receipt-row">
                        <span class="receipt-label">
                            Amount Paid
                        </span>

                        <span class="receipt-value">
                            ₹<?= number_format($receipt['amount'], 2) ?>
                        </span>
                    </div>

                    <div class="receipt-row">
                        <span class="receipt-label">
                            Payment Method
                        </span>

                        <span class="receipt-value">
                            <?= e($receipt['method']) ?>
                        </span>
                    </div>

                    <div class="receipt-row">
                        <span class="receipt-label">
                            Razorpay Order
                        </span>

                        <span class="receipt-value">
                            <?= e($receipt['razorpay_order_id'] ?: 'N/A') ?>
                        </span>
                    </div>

                    <div class="receipt-row">
                        <span class="receipt-label">
                            Razorpay Payment
                        </span>

                        <span class="receipt-value">
                            <?= e($receipt['razorpay_payment_id'] ?: 'N/A') ?>
                        </span>
                    </div>

                    <div class="receipt-row">
                        <span class="receipt-label">
                            Payment Status
                        </span>

                        <span class="receipt-value text-success">
                            <i class="fa-solid fa-circle-check me-1"></i>
                            <?= e($receipt['status']) ?>
                        </span>
                    </div>

                    <div class="receipt-row">
                        <span class="receipt-label">
                            Subscription Started
                        </span>

                        <span class="receipt-value">
                            <?= e(formatDateValue($receipt['start_date'])) ?>
                        </span>
                    </div>

                    <div class="receipt-row">
                        <span class="receipt-label">
                            Active Until
                        </span>

                        <span class="receipt-value">
                            <?= e(formatDateValue($receipt['end_date'])) ?>
                        </span>
                    </div>

                    <div class="receipt-row">
                        <span class="receipt-label">
                            Paid At
                        </span>

                        <span class="receipt-value">
                            <?= e(formatDateTimeValue($receipt['paid_at'])) ?>
                        </span>
                    </div>

                    <div class="mt-4 pt-3">

                        <div class="small text-muted mb-2">
                            Transaction Reference
                        </div>

                        <div class="reference-box px-3 py-3">
                            <code class="text-dark fw-semibold">
                                <?= e($receipt['reference']) ?>
                            </code>
                        </div>

                    </div>

                </div>

                <div
                    class="alert alert-info border-0 mt-4 mb-4 text-start"
                    role="alert"
                >
                    <i class="fa-solid fa-shield-halved me-2"></i>
                    <strong>Verified transaction:</strong>
                    this receipt is displayed only after a matching paid
                    transaction and subscription record are found for your account.
                </div>

                <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">

                    <a
                        class="btn btn-primary px-4"
                        href="subscriptions.php"
                    >
                        <i class="fa-solid fa-credit-card me-2"></i>
                        View My Subscription
                    </a>

                    <a
                        class="btn btn-outline-secondary px-4"
                        href="dashboard.php"
                    >
                        <i class="fa-solid fa-house me-2"></i>
                        Go to Dashboard
                    </a>

                </div>

            </div>

        </div>

    </div>
</main>

</body>
</html>