<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../config/razorpay.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function live_order_json(bool $status, string $message, array $data = [], int $code = 200): never
{
    http_response_code($code);
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') live_order_json(false, 'Invalid request method.', [], 405);
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') live_order_json(false, 'Unauthorized access.', [], 401);

$studentId = (int)$_SESSION['user_id'];
$examId = filter_var($_POST['exam_id'] ?? null, FILTER_VALIDATE_INT);
$csrf = trim((string)($_POST['csrf_token'] ?? ''));

if ($examId === false || $examId <= 0) live_order_json(false, 'Invalid live examination.', [], 422);
if (!function_exists('verify_csrf_token') || !verify_csrf_token($csrf)) live_order_json(false, 'Security verification failed.', [], 419);
if (!razorpay_is_configured()) live_order_json(false, 'Razorpay credentials are not configured on the server.', [], 503);

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("SELECT e.id,e.title,e.exam_type,e.exam_fee,e.subscription_required,e.starts_at,e.ends_at,e.status,e.required_question_count,e.total_marks FROM exams e WHERE e.id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$exam || (string)$exam['exam_type'] !== 'Live') throw new RuntimeException('This live examination is not available.');

    $now = new DateTimeImmutable();
    if (!in_array((string)$exam['status'], ['Scheduled','Live','Active','Upcoming','Running'], true)) throw new RuntimeException('This live examination is no longer available.');
    if (!empty($exam['starts_at']) && $now < new DateTimeImmutable((string)$exam['starts_at'])) throw new RuntimeException('This live examination has not started yet.');
    if (!empty($exam['ends_at']) && $now > new DateTimeImmutable((string)$exam['ends_at'])) throw new RuntimeException('This live examination has ended.');
    if ((int)$exam['required_question_count'] !== 50) throw new RuntimeException('This live examination must contain exactly 50 questions.');
    if ((float)$exam['exam_fee'] <= 0) throw new RuntimeException('This live examination does not require payment.');

    $access = live_exam_access_message($conn, $studentId, $exam);
    if ($access === '' || !str_contains(strtolower($access), 'payment')) throw new RuntimeException($access !== '' ? $access : 'Payment has already been completed for this live exam.');

    $q = $conn->prepare("SELECT COUNT(DISTINCT eq.question_id) FROM exam_questions eq INNER JOIN questions q ON q.id=eq.question_id WHERE eq.exam_id=? AND q.status='Active'");
    $q->execute([$examId]);
    if ((int)$q->fetchColumn() !== 50) throw new RuntimeException('This live examination is not ready. Exactly 50 active questions are required.');

    $amount = round((float)$exam['exam_fee'], 2);
    $recent = $conn->prepare("SELECT id,gateway_order_id,amount FROM live_exam_payments WHERE student_id=? AND exam_id=? AND payment_status='Pending' AND gateway_order_id IS NOT NULL AND created_at >= (NOW() - INTERVAL 30 MINUTE) ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $recent->execute([$studentId, $examId]);
    $existing = $recent->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $conn->commit();
        live_order_json(true, 'Payment order is ready.', [
            'key_id' => RAZORPAY_KEY_ID,
            'order_id' => (string)$existing['gateway_order_id'],
            'amount' => (int)round(((float)$existing['amount']) * 100),
            'currency' => RAZORPAY_CURRENCY,
            'exam_title' => (string)$exam['title'],
            'notes' => ['exam_id' => (string)$examId, 'student_id' => (string)$studentId],
        ]);
    }

    $receipt = 'LIVE-' . $examId . '-' . $studentId . '-' . date('ymdHis') . '-' . random_int(100,999);
    $order = razorpay_create_order((int)round($amount * 100), $receipt, ['exam_id' => (string)$examId, 'student_id' => (string)$studentId]);
    $orderId = trim((string)($order['id'] ?? ''));
    if (!preg_match('/^order_[A-Za-z0-9]+$/', $orderId)) throw new RuntimeException('The payment gateway returned an invalid order ID.');

    $insert = $conn->prepare("INSERT INTO live_exam_payments (student_id,exam_id,amount,reference_no,payment_status,gateway_order_id,gateway_status,gateway_currency,created_at) VALUES (?,?,?,?, 'Pending', ?, 'created', ?, NOW())");
    $insert->execute([$studentId,$examId,$amount,$receipt,$orderId,RAZORPAY_CURRENCY]);

    $conn->commit();

    live_order_json(true, 'Payment order created.', [
        'key_id' => RAZORPAY_KEY_ID,
        'order_id' => $orderId,
        'amount' => (int)round($amount * 100),
        'currency' => RAZORPAY_CURRENCY,
        'exam_title' => (string)$exam['title'],
        'prefill' => [],
        'notes' => ['exam_id' => (string)$examId, 'student_id' => (string)$studentId],
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Live exam order creation failed: ' . $e->getMessage());
    live_order_json(false, $e instanceof RuntimeException ? $e->getMessage() : 'Unable to create the payment order.', [], 422);
}
