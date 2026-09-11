<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../config/razorpay.php';

header('Content-Type: application/json; charset=utf-8');

function verify_response(bool $success, string $message, array $data = [], int $status = 200): never
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
    verify_response(false, 'Invalid request method.', [], 405);
}

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    verify_response(false, 'Your student session is not valid.', [], 401);
}

if (!razorpay_is_configured()) {
    verify_response(false, 'Razorpay is not configured on the server.', [], 503);
}

$csrf = trim((string)($_POST['csrf_token'] ?? ''));
$sessionCsrf = function_exists('csrf_token') ? csrf_token() : '';

if ($csrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
    verify_response(false, 'Your session security token is invalid. Refresh the page and try again.', [], 419);
}

$studentId = (int)$_SESSION['user_id'];
$paymentId = trim((string)($_POST['razorpay_payment_id'] ?? ''));
$returnedOrderId = trim((string)($_POST['razorpay_order_id'] ?? ''));
$signature = trim((string)($_POST['razorpay_signature'] ?? ''));

if (!preg_match('/^pay_[A-Za-z0-9]+$/', $paymentId)) {
    verify_response(false, 'Invalid Razorpay payment ID.', [], 422);
}

if (!preg_match('/^order_[A-Za-z0-9]+$/', $returnedOrderId)) {
    verify_response(false, 'Invalid Razorpay order ID.', [], 422);
}

if (!preg_match('/^[a-f0-9]{64}$/i', $signature)) {
    verify_response(false, 'Invalid payment signature.', [], 422);
}

try {
    $conn->beginTransaction();

    $paymentStatement = $conn->prepare(
        "SELECT
            sp.id,
            sp.student_id,
            sp.plan_id,
            sp.subscription_id,
            sp.amount,
            sp.reference_no,
            sp.payment_status,
            sp.gateway_order_id,
            sp.gateway_payment_id,
            sp.gateway_signature,
            sp.gateway_status,
            sp.gateway_currency,
            p.name AS plan_name,
            p.duration_months,
            p.price AS plan_price
         FROM subscription_payments sp
         INNER JOIN subscription_plans p ON p.id = sp.plan_id
         WHERE sp.student_id = ?
           AND sp.gateway_order_id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $paymentStatement->execute([$studentId, $returnedOrderId]);
    $payment = $paymentStatement->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new RuntimeException('This payment order does not belong to the logged-in student.');
    }

    $serverOrderId = trim((string)$payment['gateway_order_id']);

    if (!hash_equals($serverOrderId, $returnedOrderId)) {
        throw new RuntimeException('The payment order could not be matched securely.');
    }

    if (!razorpay_verify_signature($serverOrderId, $paymentId, $signature)) {
        $markFailed = $conn->prepare(
            "UPDATE subscription_payments
             SET payment_status = 'Failed', gateway_status = 'signature_failed'
             WHERE id = ?
             LIMIT 1"
        );
        $markFailed->execute([(int)$payment['id']]);
        $conn->commit();
        verify_response(false, 'Payment verification failed. The subscription was not activated.', [], 422);
    }

    $gatewayPayment = razorpay_fetch_payment($paymentId);

    $gatewayOrderId = trim((string)($gatewayPayment['order_id'] ?? ''));
    $gatewayAmount = (int)($gatewayPayment['amount'] ?? 0);
    $gatewayCurrency = strtoupper(trim((string)($gatewayPayment['currency'] ?? '')));
    $gatewayStatus = strtolower(trim((string)($gatewayPayment['status'] ?? '')));
    $gatewayMethod = trim((string)($gatewayPayment['method'] ?? ''));

    $expectedAmount = (int)round((float)$payment['amount'] * 100);

    if ($gatewayOrderId !== $serverOrderId || $gatewayAmount !== $expectedAmount || $gatewayCurrency !== RAZORPAY_CURRENCY) {
        $markFailed = $conn->prepare(
            "UPDATE subscription_payments
             SET
                payment_status = 'Failed',
                gateway_payment_id = ?,
                gateway_signature = ?,
                gateway_status = 'amount_or_order_mismatch',
                gateway_method = ?
             WHERE id = ?
             LIMIT 1"
        );
        $markFailed->execute([$paymentId, $signature, razorpay_payment_method_label($gatewayMethod), (int)$payment['id']]);
        $conn->commit();
        verify_response(false, 'Payment details did not match the selected subscription amount. Access was not granted.', [], 422);
    }

    if ($gatewayStatus !== 'captured') {
        $localStatus = $gatewayStatus === 'failed' ? 'Failed' : 'Pending';

        $updatePending = $conn->prepare(
            "UPDATE subscription_payments
             SET
                payment_status = ?,
                gateway_payment_id = ?,
                gateway_signature = ?,
                gateway_status = ?,
                gateway_method = ?
             WHERE id = ?
             LIMIT 1"
        );
        $updatePending->execute([
            $localStatus,
            $paymentId,
            $signature,
            $gatewayStatus,
            razorpay_payment_method_label($gatewayMethod),
            (int)$payment['id'],
        ]);

        $conn->commit();
        verify_response(false, $gatewayStatus === 'failed'
            ? 'Razorpay reported the payment as failed. Please try again.'
            : 'Payment is not captured yet. Your subscription remains inactive until payment is confirmed.', [], 422);
    }

    if ((string)$payment['payment_status'] === 'Paid' && !empty($payment['subscription_id'])) {
        $conn->commit();

        $_SESSION['payment_receipt'] = [
            'reference' => (string)$payment['reference_no'],
        ];

        verify_response(true, 'Payment was already verified successfully.', [
            'redirect' => 'payment_success.php',
        ]);
    }

    $durationMonths = (int)$payment['duration_months'];
    $planPrice = (float)$payment['plan_price'];

    if ($durationMonths <= 0 || $planPrice <= 0 || abs($planPrice - (float)$payment['amount']) > 0.001) {
        throw new RuntimeException('The subscription plan changed unexpectedly.');
    }

    $startDate = new DateTimeImmutable('today');
    $endDate = $startDate
        ->modify('+' . $durationMonths . ' months')
        ->modify('-1 day');

    $expireOld = $conn->prepare(
        "UPDATE subscriptions
         SET status = 'Expired'
         WHERE student_id = ?
           AND status = 'Active'"
    );
    $expireOld->execute([$studentId]);

    $subscriptionInsert = $conn->prepare(
        "INSERT INTO subscriptions
        (
            student_id,
            plan_id,
            start_date,
            end_date,
            status
        )
        VALUES (?, ?, ?, ?, 'Active')"
    );
    $subscriptionInsert->execute([
        $studentId,
        (int)$payment['plan_id'],
        $startDate->format('Y-m-d'),
        $endDate->format('Y-m-d'),
    ]);

    $subscriptionId = (int)$conn->lastInsertId();

    if ($subscriptionId <= 0) {
        throw new RuntimeException('Subscription activation failed.');
    }

    $updatePayment = $conn->prepare(
        "UPDATE subscription_payments
         SET
            subscription_id = ?,
            payment_status = 'Paid',
            payment_method = ?,
            gateway_payment_id = ?,
            gateway_signature = ?,
            gateway_status = 'captured',
            gateway_method = ?,
            gateway_currency = ?,
            paid_at = NOW()
         WHERE id = ?
         LIMIT 1"
    );
    $updatePayment->execute([
        $subscriptionId,
        razorpay_payment_method_label($gatewayMethod),
        $paymentId,
        $signature,
        razorpay_payment_method_label($gatewayMethod),
        RAZORPAY_CURRENCY,
        (int)$payment['id'],
    ]);

    if ($updatePayment->rowCount() < 1) {
        throw new RuntimeException('Payment activation record could not be finalized.');
    }

    $conn->commit();

    $_SESSION['payment_receipt'] = [
        'payment_id' => (int)$payment['id'],
        'subscription_id' => $subscriptionId,
        'plan_id' => (int)$payment['plan_id'],
        'plan' => (string)$payment['plan_name'],
        'amount' => $planPrice,
        'method' => razorpay_payment_method_label($gatewayMethod) ?? 'Razorpay',
        'reference' => (string)$payment['reference_no'],
        'razorpay_order_id' => $serverOrderId,
        'razorpay_payment_id' => $paymentId,
        'start_date' => $startDate->format('Y-m-d'),
        'end_date' => $endDate->format('Y-m-d'),
    ];

    verify_response(true, 'Payment verified and subscription activated successfully.', [
        'redirect' => 'payment_success.php',
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Subscription Razorpay verification failed: ' . $e->getMessage());
    verify_response(false, 'We could not verify this payment safely. No subscription access was granted.', [], 500);
}
