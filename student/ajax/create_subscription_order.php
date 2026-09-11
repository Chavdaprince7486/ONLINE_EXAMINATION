<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../config/razorpay.php';

header('Content-Type: application/json; charset=utf-8');

function order_response(bool $success, string $message, array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    order_response(false, 'Invalid request method.', [], 405);
}

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    order_response(false, 'Your student session is not valid.', [], 401);
}

if (!razorpay_is_configured()) {
    order_response(false, 'Razorpay is not configured on the server. Add the Razorpay API credentials first.', [], 503);
}

$csrf = trim((string)($_POST['csrf_token'] ?? ''));
$sessionCsrf = function_exists('csrf_token') ? csrf_token() : '';

if ($csrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
    order_response(false, 'Your session security token is invalid. Refresh the page and try again.', [], 419);
}

$studentId = (int)$_SESSION['user_id'];
$planId = filter_var($_POST['plan_id'] ?? null, FILTER_VALIDATE_INT);

if ($planId === false || $planId === null || $planId <= 0) {
    order_response(false, 'Please select a valid subscription plan.', [], 422);
}

try {
    $conn->beginTransaction();

    $planStatement = $conn->prepare(
        "SELECT id, name, duration_months, price
         FROM subscription_plans
         WHERE id = ?
           AND status = 'Active'
         LIMIT 1
         FOR UPDATE"
    );
    $planStatement->execute([(int)$planId]);
    $plan = $planStatement->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new RuntimeException('The selected subscription plan is no longer available.');
    }

    $durationMonths = (int)$plan['duration_months'];
    $price = (float)$plan['price'];

    if ($durationMonths <= 0 || $price <= 0) {
        throw new RuntimeException('The selected subscription plan has an invalid price or duration.');
    }

    $amountPaise = (int)round($price * 100);

    if ($amountPaise <= 0) {
        throw new RuntimeException('The selected subscription amount is invalid.');
    }

    $existingOrderStatement = $conn->prepare(
        "SELECT
            id,
            reference_no,
            gateway_order_id,
            amount,
            gateway_currency,
            created_at
         FROM subscription_payments
         WHERE student_id = ?
           AND plan_id = ?
           AND payment_status = 'Pending'
           AND gateway_order_id IS NOT NULL
           AND created_at >= (NOW() - INTERVAL 15 MINUTE)
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $existingOrderStatement->execute([$studentId, (int)$planId]);
    $existingOrder = $existingOrderStatement->fetch(PDO::FETCH_ASSOC);

    if ($existingOrder) {
        $conn->commit();

        order_response(true, 'Existing payment order is ready.', [
            'order_id' => (string)$existingOrder['gateway_order_id'],
            'amount' => (int)round((float)$existingOrder['amount'] * 100),
            'currency' => (string)($existingOrder['gateway_currency'] ?: RAZORPAY_CURRENCY),
            'reference' => (string)$existingOrder['reference_no'],
        ]);
    }

    $reference = 'SUB-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(5)));

    $razorpayOrder = razorpay_create_order(
        $amountPaise,
        $reference,
        [
            'student_id' => (string)$studentId,
            'plan_id' => (string)(int)$plan['id'],
            'reference' => $reference,
        ]
    );

    $gatewayOrderId = trim((string)($razorpayOrder['id'] ?? ''));
    $gatewayCurrency = strtoupper(trim((string)($razorpayOrder['currency'] ?? RAZORPAY_CURRENCY)));
    $gatewayAmount = (int)($razorpayOrder['amount'] ?? 0);

    if ($gatewayOrderId === '' || $gatewayAmount !== $amountPaise || $gatewayCurrency !== RAZORPAY_CURRENCY) {
        throw new RuntimeException('The payment gateway returned an unexpected order amount or currency.');
    }

    $insert = $conn->prepare(
        "INSERT INTO subscription_payments
        (
            student_id,
            plan_id,
            amount,
            reference_no,
            gateway_order_id,
            gateway_status,
            gateway_currency,
            payment_status
        )
        VALUES
        (?, ?, ?, ?, ?, 'created', ?, 'Pending')"
    );

    $insert->execute([
        $studentId,
        (int)$plan['id'],
        $price,
        $reference,
        $gatewayOrderId,
        $gatewayCurrency,
    ]);

    $conn->commit();

    order_response(true, 'Razorpay order created successfully.', [
        'order_id' => $gatewayOrderId,
        'amount' => $gatewayAmount,
        'currency' => $gatewayCurrency,
        'reference' => $reference,
        'plan_name' => (string)$plan['name'],
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Subscription Razorpay order creation failed: ' . $e->getMessage());
    order_response(false, 'Unable to start secure payment right now. Please try again.', [], 500);
}
