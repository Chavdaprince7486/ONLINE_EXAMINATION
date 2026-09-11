<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

function failure_response(bool $success, string $message, int $status = 200): never
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    failure_response(false, 'Invalid request method.', 405);
}

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    failure_response(false, 'Your student session is not valid.', 401);
}

$csrf = trim((string)($_POST['csrf_token'] ?? ''));
$sessionCsrf = function_exists('csrf_token') ? csrf_token() : '';

if ($csrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
    failure_response(false, 'Your session security token is invalid.', 419);
}

$orderId = trim((string)($_POST['razorpay_order_id'] ?? ''));
$paymentId = trim((string)($_POST['razorpay_payment_id'] ?? ''));

if (!preg_match('/^order_[A-Za-z0-9]+$/', $orderId)) {
    failure_response(false, 'Invalid payment order.', 422);
}

if ($paymentId !== '' && !preg_match('/^pay_[A-Za-z0-9]+$/', $paymentId)) {
    $paymentId = '';
}

try {
    $stmt = $conn->prepare(
        "UPDATE subscription_payments
         SET
            payment_status = 'Failed',
            gateway_payment_id = COALESCE(NULLIF(?, ''), gateway_payment_id),
            gateway_status = 'failed'
         WHERE student_id = ?
           AND gateway_order_id = ?
           AND payment_status = 'Pending'
         LIMIT 1"
    );

    $stmt->execute([
        $paymentId,
        (int)$_SESSION['user_id'],
        $orderId,
    ]);

    failure_response(true, 'The failed payment attempt was recorded safely.');
} catch (Throwable $e) {
    error_log('Subscription payment failure recording failed: ' . $e->getMessage());
    failure_response(false, 'Unable to record the failed payment attempt.', 500);
}
