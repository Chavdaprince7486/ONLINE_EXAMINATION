<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/razorpay.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    header('Location: ../auth/login.php');
    exit;
}

$studentId = (int)$_SESSION['user_id'];
$planId = filter_input(INPUT_GET, 'plan_id', FILTER_VALIDATE_INT);

if ($planId === false || $planId === null || $planId <= 0) {
    header('Location: subscriptions.php');
    exit;
}

try {
    $stmt = $conn->prepare(
        "SELECT id, name, duration_months, price, description, benefits
         FROM subscription_plans
         WHERE id = ?
           AND status = 'Active'
         LIMIT 1"
    );
    $stmt->execute([(int)$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Checkout plan load failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load the selected subscription plan.');
}

if (!$plan) {
    header('Location: subscriptions.php');
    exit;
}

$durationMonths = (int)$plan['duration_months'];
$price = (float)$plan['price'];
$razorpayReady = razorpay_is_configured() && $price > 0 && $durationMonths > 0;
$csrfToken = function_exists('csrf_token') ? csrf_token() : '';
$studentName = trim((string)($_SESSION['user_name'] ?? $_SESSION['full_name'] ?? ''));
$studentEmail = trim((string)($_SESSION['user_email'] ?? ''));

if ($studentEmail === '') {
    try {
        $studentStmt = $conn->prepare('SELECT full_name, email FROM students WHERE id = ? LIMIT 1');
        $studentStmt->execute([$studentId]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
        $studentName = $studentName !== '' ? $studentName : trim((string)($student['full_name'] ?? ''));
        $studentEmail = trim((string)($student['email'] ?? ''));
    } catch (Throwable $e) {
        error_log('Checkout student prefill load failed: ' . $e->getMessage());
    }
}

function checkout_e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function checkout_benefits(?string $value): array
{
    $value = trim((string)$value);

    if ($value === '') {
        return [];
    }

    $parts = preg_split('/[\r\n;]+/', $value) ?: [];
    return array_values(array_filter(array_map('trim', $parts)));
}

$benefits = checkout_benefits($plan['benefits'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#5D4037">
    <title>Secure Checkout | ExamSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        :root {
            --earth: #5D4037;
            --olive: #556B2F;
            --cream: #F5F5DC;
            --ink: #333333;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 10% 5%, rgba(85,107,47,.12), transparent 32%),
                radial-gradient(circle at 90% 90%, rgba(93,64,55,.12), transparent 34%),
                linear-gradient(135deg, var(--cream), #faf7f0 52%, #eef3ea);
            color: var(--ink);
        }

        .checkout-wrap {
            min-height: calc(100vh - 76px);
            display: flex;
            align-items: center;
            padding: 48px 0;
        }

        .checkout-card {
            border: 1px solid rgba(93,64,55,.12);
            background: rgba(255,255,255,.88);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(51,51,51,.13);
        }

        .plan-panel {
            background: linear-gradient(155deg, rgba(93,64,55,.98), rgba(85,107,47,.96));
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .plan-panel::after {
            content: '';
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            right: -110px;
            top: -90px;
            background: rgba(255,255,255,.08);
        }

        .secure-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 13px;
            border-radius: 999px;
            background: rgba(255,255,255,.12);
            font-size: .8rem;
            font-weight: 700;
        }

        .price {
            font-size: clamp(2.7rem, 7vw, 4.8rem);
            line-height: .95;
            font-weight: 800;
            letter-spacing: -2px;
        }

        .benefit-list {
            display: grid;
            gap: 12px;
            padding: 0;
            margin: 24px 0 0;
            list-style: none;
        }

        .benefit-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: rgba(255,255,255,.88);
        }

        .payment-panel {
            padding: 38px;
        }

        .payment-logo {
            width: 48px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            background: rgba(85,107,47,.11);
            color: var(--olive);
            font-size: 1.25rem;
        }

        .pay-btn {
            min-height: 56px;
            border: 0;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--earth), var(--olive));
            color: #fff;
            font-weight: 800;
            box-shadow: 0 14px 28px rgba(93,64,55,.18);
        }

        .pay-btn:disabled {
            opacity: .72;
            cursor: not-allowed;
        }

        .trust-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 20px;
        }

        .trust-chip {
            border: 1px solid rgba(93,64,55,.10);
            background: rgba(250,247,240,.72);
            border-radius: 14px;
            padding: 12px 10px;
            text-align: center;
            font-size: .78rem;
            color: #665e59;
        }

        .alert-area { min-height: 0; }

        @media (max-width: 991.98px) {
            .payment-panel { padding: 30px; }
        }

        @media (max-width: 575.98px) {
            .checkout-wrap { padding: 24px 0; }
            .payment-panel { padding: 24px; }
            .trust-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<main class="checkout-wrap">
    <div class="container">
        <div class="checkout-card mx-auto" style="max-width: 1080px;">
            <div class="row g-0">
                <section class="col-lg-5 plan-panel p-4 p-md-5">
                    <span class="secure-badge">
                        <i class="fa-solid fa-shield-halved"></i>
                        Secure ExamSphere Checkout
                    </span>

                    <p class="text-uppercase small fw-bold opacity-75 mt-5 mb-2">Selected plan</p>
                    <h1 class="display-6 fw-bold mb-2"><?= checkout_e($plan['name']) ?></h1>
                    <p class="opacity-75 mb-4"><?= checkout_e($plan['description'] ?: 'Premium ExamSphere preparation access.') ?></p>

                    <div class="price">₹<?= number_format($price, 2) ?></div>
                    <div class="opacity-75 mt-2">
                        <?= $durationMonths ?> month<?= $durationMonths === 1 ? '' : 's' ?> of access
                    </div>

                    <?php if ($benefits): ?>
                        <ul class="benefit-list">
                            <?php foreach ($benefits as $benefit): ?>
                                <li>
                                    <i class="fa-solid fa-circle-check mt-1"></i>
                                    <span><?= checkout_e($benefit) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="col-lg-7 payment-panel">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="payment-logo"><i class="fa-solid fa-lock"></i></span>
                        <div>
                            <h2 class="h4 fw-bold mb-1">Pay securely</h2>
                            <p class="text-muted mb-0">You will complete payment in Razorpay Checkout.</p>
                        </div>
                    </div>

                    <div id="alertArea" class="alert-area"></div>

                    <div class="border rounded-4 p-4 mb-4" style="background:rgba(250,247,240,.7);">
                        <div class="d-flex justify-content-between gap-3 mb-3">
                            <span class="text-muted">Plan</span>
                            <strong><?= checkout_e($plan['name']) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between gap-3 mb-3">
                            <span class="text-muted">Duration</span>
                            <strong><?= $durationMonths ?> month<?= $durationMonths === 1 ? '' : 's' ?></strong>
                        </div>
                        <div class="d-flex justify-content-between gap-3">
                            <span class="text-muted">Amount</span>
                            <strong class="fs-5">₹<?= number_format($price, 2) ?></strong>
                        </div>
                    </div>

                    <?php if (!$razorpayReady): ?>
                        <div class="alert alert-warning rounded-4">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i>
                            Secure payment is not available until the server Razorpay credentials are configured.
                        </div>
                    <?php endif; ?>

                    <button
                        type="button"
                        class="btn w-100 pay-btn"
                        id="payButton"
                        <?= !$razorpayReady ? 'disabled' : '' ?>
                    >
                        <i class="fa-solid fa-credit-card me-2"></i>
                        Continue to Razorpay
                    </button>

                    <div class="trust-row">
                        <div class="trust-chip"><i class="fa-solid fa-lock d-block mb-1"></i>Encrypted checkout</div>
                        <div class="trust-chip"><i class="fa-solid fa-receipt d-block mb-1"></i>Verified payment</div>
                        <div class="trust-chip"><i class="fa-solid fa-user-shield d-block mb-1"></i>Account protected</div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="subscriptions.php" class="text-decoration-none" style="color:#5D4037;font-weight:700;">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back to subscription plans
                        </a>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(() => {
    const button = document.getElementById('payButton');
    const alertArea = document.getElementById('alertArea');
    const csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const planId = <?= (int)$plan['id'] ?>;
    const keyId = <?= json_encode(RAZORPAY_KEY_ID, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const studentName = <?= json_encode($studentName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const studentEmail = <?= json_encode($studentEmail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    let activeOrderId = '';
    let busy = false;

    function showAlert(message, type = 'danger') {
        alertArea.innerHTML = `
            <div class="alert alert-${type} rounded-4" role="alert">
                <i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'} me-2"></i>
                ${escapeHtml(message)}
            </div>
        `;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    }

    async function post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            credentials: 'same-origin',
            body: new URLSearchParams(body)
        });
        const data = await response.json().catch(() => null);
        if (!data) throw new Error('The server returned an invalid response.');
        if (!response.ok || data.success !== true) {
            throw new Error(data.message || 'Payment request failed.');
        }
        return data;
    }

    async function createOrder() {
        return post('ajax/create_subscription_order.php', {
            csrf_token: csrfToken,
            plan_id: String(planId)
        });
    }

    async function markFailed(paymentId = '') {
        try {
            await post('ajax/record_subscription_payment_failure.php', {
                csrf_token: csrfToken,
                razorpay_order_id: activeOrderId,
                razorpay_payment_id: paymentId
            });
        } catch (error) {
            console.error(error);
        }
    }

    async function markCancelled() {
        try {
            await post('ajax/cancel_subscription_payment.php', {
                csrf_token: csrfToken,
                razorpay_order_id: activeOrderId
            });
        } catch (error) {
            console.error(error);
        }
    }

    async function verifyPayment(response) {
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Verifying payment...';

        try {
            const result = await post('ajax/verify_subscription_payment.php', {
                csrf_token: csrfToken,
                razorpay_payment_id: response.razorpay_payment_id,
                razorpay_order_id: response.razorpay_order_id,
                razorpay_signature: response.razorpay_signature
            });

            window.location.href = result.data?.redirect || 'payment_success.php';
        } catch (error) {
            showAlert(error.message || 'Payment verification failed.');
            busy = false;
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-credit-card me-2"></i>Retry Secure Payment';
        }
    }

    button?.addEventListener('click', async () => {
        if (busy) return;
        busy = true;
        button.disabled = true;
        alertArea.innerHTML = '';
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Creating secure order...';

        try {
            const result = await createOrder();
            const order = result.data || {};
            activeOrderId = String(order.order_id || '');

            if (!activeOrderId) throw new Error('A valid Razorpay order was not created.');

            const razorpay = new Razorpay({
                key: keyId,
                amount: Number(order.amount),
                currency: String(order.currency || 'INR'),
                name: 'ExamSphere',
                description: <?= json_encode((string)$plan['name'] . ' subscription', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
                order_id: activeOrderId,
                prefill: {
                    name: studentName,
                    email: studentEmail
                },
                theme: {
                    color: '#5D4037'
                },
                modal: {
                    ondismiss: async function () {
                        await markCancelled();
                        busy = false;
                        button.disabled = false;
                        button.innerHTML = '<i class="fa-solid fa-credit-card me-2"></i>Try Payment Again';
                    }
                },
                handler: verifyPayment
            });

            razorpay.on('payment.failed', async function (response) {
                const paymentId = String(response?.error?.metadata?.payment_id || '');
                await markFailed(paymentId);
                showAlert(response?.error?.description || 'The payment failed. Please try again.');
                busy = false;
                button.disabled = false;
                button.innerHTML = '<i class="fa-solid fa-credit-card me-2"></i>Try Payment Again';
            });

            razorpay.open();
        } catch (error) {
            showAlert(error.message || 'Unable to start secure payment.');
            busy = false;
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-credit-card me-2"></i>Try Secure Payment Again';
        }
    });
})();
</script>
</body>
</html>
