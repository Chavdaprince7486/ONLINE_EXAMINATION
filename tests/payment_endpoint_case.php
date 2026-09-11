<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Test doubles are restricted to this CLI process; application files are not replaced.
require_once dirname(__DIR__) . '/config/functions.php';

$scenario = $argv[1] ?? '';
$paid = str_starts_with($scenario, 'paid_');
$gatewayCalls = 0;
$signatureValid = !str_contains($scenario, 'bad_signature');
$payment = [
    'id' => 1, 'student_id' => 7, 'plan_id' => 2, 'subscription_id' => $paid ? 9 : null,
    'amount' => '99.00', 'reference_no' => 'SUB-test', 'payment_status' => $paid ? 'Paid' : 'Pending',
    'gateway_order_id' => 'order_test', 'gateway_payment_id' => $paid ? 'pay_test' : null,
    'gateway_signature' => null, 'gateway_status' => $paid ? 'captured' : 'created',
    'gateway_currency' => 'INR', 'plan_name' => 'Test plan', 'duration_months' => 1, 'plan_price' => '99.00',
];

final class PaymentTestPDO extends PDO
{
    public array $writes = [];
    public bool $transaction = false;
    public int $commits = 0;
    public int $rollbacks = 0;
    public int $activations = 0;
    private int $writeCheckpoint = 0;

    public function __construct(public array $payment, public string $scenario) {}
    public function beginTransaction(): bool
    {
        $this->transaction = true;
        $this->writeCheckpoint = count($this->writes);
        return true;
    }
    public function inTransaction(): bool { return $this->transaction; }
    public function commit(): bool { $this->transaction = false; $this->commits++; return true; }
    public function rollBack(): bool
    {
        $this->transaction = false;
        $this->writes = array_slice($this->writes, 0, $this->writeCheckpoint);
        $this->rollbacks++;
        return true;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new PaymentTestStatement($this, $query);
    }
    public function lastInsertId(?string $name = null): string|false { return '10'; }
}

final class PaymentTestStatement extends PDOStatement
{
    public array $parameters = [];
    public function __construct(private PaymentTestPDO $db, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $this->parameters = $params ?? [];
        if (substr_count($this->sql, '?') !== count($this->parameters)) {
            throw new LogicException('SQL placeholder/binding mismatch.');
        }
        if (preg_match('/^\s*(UPDATE|INSERT)/', $this->sql)) {
            $this->db->writes[] = ['sql' => $this->sql, 'parameters' => $this->parameters];
            if (str_contains($this->sql, 'INSERT INTO subscriptions')) {
                $this->db->activations++;
            }
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if ($this->db->scenario === 'unknown_order') return false;
        return $this->db->payment;
    }
    public function fetchColumn(int $column = 0): mixed
    {
        if (str_contains($this->sql, 'FROM students')) return $this->db->scenario === 'inactive_student' ? false : 7;
        if (str_contains($this->sql, 'FROM subscriptions')) return $this->db->scenario === 'renewal' ? '2027-01-15' : false;
        return false;
    }
    public function rowCount(): int { return 1; }
}

function csrf_token(): string { return 'test-csrf'; }
function razorpay_is_configured(): bool { return true; }
function razorpay_verify_signature(string $order, string $id, string $signature): bool
{
    return $GLOBALS['signatureValid'];
}
function razorpay_fetch_payment(string $id): array
{
    $GLOBALS['gatewayCalls']++;
    $scenario = $GLOBALS['scenario'];
    return [
        'order_id' => $scenario === 'wrong_order' ? 'order_other' : 'order_test',
        'amount' => $scenario === 'wrong_amount' ? 1 : 9900,
        'currency' => $scenario === 'wrong_currency' ? 'USD' : 'INR',
        'status' => match ($scenario) { 'authorized' => 'authorized', 'gateway_failed' => 'failed', default => 'captured' },
        'method' => 'upi',
    ];
}
function razorpay_payment_method_label(string $method): string { return $method; }
define('RAZORPAY_CURRENCY', 'INR');

$conn = new PaymentTestPDO($payment, $scenario);
$_SESSION = ['user_id' => 7, 'user_role' => 'student'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf_token' => 'test-csrf', 'razorpay_order_id' => 'order_test',
    'razorpay_payment_id' => $scenario === 'paid_different_payment' ? 'pay_other' : 'pay_test',
    'razorpay_signature' => str_repeat('a', 64),
];
if ($scenario === 'unauthenticated') $_SESSION = [];
if ($scenario === 'wrong_role') $_SESSION['user_role'] = 'teacher';
if ($scenario === 'invalid_csrf') $_POST['csrf_token'] = 'wrong';
if ($scenario === 'get_request') $_SERVER['REQUEST_METHOD'] = 'GET';
if ($scenario === 'invalid_id') $_POST['razorpay_payment_id'] = 'invalid';

ob_start();
register_shutdown_function(static function () use ($conn): void {
    $output = ob_get_clean();
    echo json_encode([
        'response' => json_decode($output, true), 'http' => http_response_code(),
        'writes' => $conn->writes, 'activations' => $conn->activations,
        'gateway_calls' => $GLOBALS['gatewayCalls'], 'commits' => $conn->commits,
        'rollbacks' => $conn->rollbacks, 'transaction_open' => $conn->inTransaction(),
    ], JSON_THROW_ON_ERROR);
});

$endpoint = $scenario === 'client_failure'
    ? 'record_subscription_payment_failure.php'
    : 'verify_subscription_payment.php';
$source = file_get_contents(dirname(__DIR__) . '/student/ajax/' . $endpoint);
$source = preg_replace('/^require_once [^;]+;\R/m', '', $source);
$source = preg_replace('/^<\?php\s*/', '', $source);
// Execute the real endpoint branch logic with isolated database/gateway doubles.
eval($source);
