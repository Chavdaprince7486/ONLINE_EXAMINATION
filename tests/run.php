<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/functions.php';
require_once dirname(__DIR__) . '/config/exam_validation.php';

$passed = 0;
$failed = 0;
function check(bool $condition, string $name): void
{
    global $passed, $failed;
    $condition ? $passed++ : $failed++;
    echo ($condition ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
}

$questions = array_map(static fn(int $id): array => ['id' => $id, 'marks' => '1.00', 'status' => 'Active'], range(1, 50));
check(validate_exam_question_configuration(50, $questions, 50)['valid'], 'Exactly 50 active questions accepted');
check(!validate_exam_question_configuration(49, array_slice($questions, 0, 49), 50)['valid'], '49 questions rejected');
check(!validate_exam_question_configuration(51, [...$questions, ['id' => 51, 'marks' => 1]], 50)['valid'], '51 questions rejected');
$duplicate = $questions;
$duplicate[49] = $duplicate[0];
check(!validate_exam_question_configuration(50, $duplicate, 50)['valid'], 'Duplicate question rejected');
$inactive = $questions;
$inactive[0]['status'] = 'Inactive';
check(!validate_exam_question_configuration(50, $inactive, 50)['valid'], 'Inactive question rejected');
check(!validate_exam_question_configuration(51, $questions, 50)['valid'], 'Incorrect total marks rejected');
check(!validate_exam_question_configuration(50, $questions, 0)['valid'], 'Invalid stored question count rejected');
check(examsphere_grade_from_percentage(75) === 'A', 'Canonical grade boundary');
check(examsphere_accuracy_percentage(7, 10) === 70.0, 'Accuracy calculation');
check(examsphere_accuracy_percentage(0, 0) === 0.0, 'Zero-answer accuracy');

foreach ([['2026-01-01', 1, '2026-01-31'], ['2026-01-31', 1, '2026-02-27'], ['2028-01-31', 1, '2028-02-28'], ['2026-12-15', 3, '2027-03-14']] as [$start, $months, $expected]) {
    [, $end] = subscription_period(new DateTimeImmutable($start), $months);
    check($end->format('Y-m-d') === $expected, 'Calendar anniversary expiry: ' . $start);
}
try {
    subscription_period(new DateTimeImmutable('today'), 0);
    check(false, 'Invalid duration rejected');
} catch (InvalidArgumentException) {
    check(true, 'Invalid duration rejected');
}

$cases = [
    'bad_signature' => [422, false], 'paid_bad_signature' => [422, false],
    'paid_retry' => [200, true], 'paid_different_payment' => [500, false],
    'wrong_amount' => [422, false], 'wrong_currency' => [422, false],
    'wrong_order' => [422, false], 'authorized' => [422, false],
    'gateway_failed' => [422, false], 'captured' => [200, true],
    'renewal' => [200, true], 'unauthenticated' => [401, false],
    'wrong_role' => [401, false], 'invalid_csrf' => [419, false],
    'get_request' => [405, false], 'invalid_id' => [422, false],
    'unknown_order' => [500, false], 'inactive_student' => [500, false],
    'client_failure' => [200, true],
];
foreach ($cases as $scenario => [$http, $success]) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, __DIR__ . '/payment_endpoint_case.php', $scenario], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot launch isolated PHP test.');
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $result = json_decode($output, true);
    if ($exit !== 0 || !is_array($result)) {
        check(false, $scenario . ': ' . $errors);
        continue;
    }
    check($result['http'] === $http && ($result['response']['success'] ?? null) === $success, $scenario . ': response');
    check(!$result['transaction_open'], $scenario . ': transaction closed');
    if (in_array($scenario, ['captured', 'renewal'], true)) {
        check($result['activations'] === 1 && $result['commits'] === 1, $scenario . ': one atomic activation');
        $inserts = array_values(array_filter($result['writes'], static fn(array $write): bool => str_contains($write['sql'], 'INSERT INTO subscriptions')));
        if ($scenario === 'renewal') check($inserts[0]['parameters'][2] === '2027-01-16', 'Renewal preserves existing paid access');
    } else {
        check($result['activations'] === 0, $scenario . ': no activation');
    }
    if (!in_array($scenario, ['captured', 'renewal', 'authorized', 'gateway_failed', 'client_failure'], true)) {
        check($result['writes'] === [], $scenario . ': no record mutations');
    }
    if (str_starts_with($scenario, 'paid_')) {
        check($result['gateway_calls'] === 0, $scenario . ': no redundant gateway request');
    }
    if (in_array($scenario, ['authorized', 'gateway_failed', 'client_failure'], true)) {
        check(!str_contains(json_encode($result['writes']), 'gateway_payment_id'), $scenario . ': cannot reserve a payment ID');
    }
}

echo PHP_EOL . $passed . ' passed; ' . $failed . " failed.\n";
echo "Endpoint tests use isolated doubles, not Razorpay or a live database.\n";
exit($failed > 0 ? 1 : 0);
