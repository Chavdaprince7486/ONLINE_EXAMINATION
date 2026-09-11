<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../config/razorpay.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function live_verify_json(bool $status, string $message, array $data = [], int $code = 200): never
{
    http_response_code($code);
    echo json_encode(array_merge(['status'=>$status,'message'=>$message],$data), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') live_verify_json(false,'Invalid request method.',[],405);
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') live_verify_json(false,'Unauthorized access.',[],401);

$studentId=(int)$_SESSION['user_id'];
$examId=filter_var($_POST['exam_id'] ?? null,FILTER_VALIDATE_INT);
$csrf=trim((string)($_POST['csrf_token'] ?? ''));
$orderId=trim((string)($_POST['razorpay_order_id'] ?? ''));
$paymentId=trim((string)($_POST['razorpay_payment_id'] ?? ''));
$signature=trim((string)($_POST['razorpay_signature'] ?? ''));

if ($examId===false||$examId<=0) live_verify_json(false,'Invalid live examination.',[],422);
if (!function_exists('verify_csrf_token')||!verify_csrf_token($csrf)) live_verify_json(false,'Security verification failed.',[],419);
if (!preg_match('/^order_[A-Za-z0-9]+$/',$orderId)||!preg_match('/^pay_[A-Za-z0-9]+$/',$paymentId)||$signature==='') live_verify_json(false,'Invalid payment response.',[],422);
if (!razorpay_is_configured()) live_verify_json(false,'Razorpay credentials are not configured on the server.',[],503);

try {
    $conn->beginTransaction();

    $paymentStmt=$conn->prepare("SELECT * FROM live_exam_payments WHERE student_id=? AND exam_id=? AND gateway_order_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $paymentStmt->execute([$studentId,$examId,$orderId]);
    $record=$paymentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) throw new RuntimeException('Payment order was not found for this student and examination.');
    if (!razorpay_verify_signature((string)$record['gateway_order_id'],$paymentId,$signature)) throw new RuntimeException('Payment signature verification failed.');
    if ((string)$record['payment_status'] === 'Paid') {
        if ((string)($record['gateway_payment_id'] ?? '') === $paymentId) {
            $conn->commit();
            live_verify_json(true, 'Payment already verified.', ['redirect' => 'live_exams.php#payment-' . $examId]);
        }
        throw new RuntimeException('This payment order has already been completed.');
    }

    $examStmt=$conn->prepare("SELECT id,title,exam_type,exam_fee,subscription_required,starts_at,ends_at,status,required_question_count FROM exams WHERE id=? LIMIT 1 FOR UPDATE");
    $examStmt->execute([$examId]);
    $exam=$examStmt->fetch(PDO::FETCH_ASSOC);
    if (!$exam || (string)$exam['exam_type']!=='Live') throw new RuntimeException('This live examination is not available.');
    if (abs(((float)$exam['exam_fee']) - ((float)$record['amount'])) > 0.00001) throw new RuntimeException('Payment amount does not match the examination fee.');
    $gatewayPayment=razorpay_fetch_payment($paymentId);
    $gatewayOrderId=(string)($gatewayPayment['order_id']??'');
    $gatewayAmount=(int)($gatewayPayment['amount']??0);
    $gatewayCurrency=(string)($gatewayPayment['currency']??'');
    $gatewayStatus=(string)($gatewayPayment['status']??'');
    $gatewayMethod=razorpay_payment_method_label((string)($gatewayPayment['method']??''));

    $expectedAmount=(int)round((float)$record['amount']*100);
    if ($gatewayOrderId!==$orderId) throw new RuntimeException('Payment order verification failed.');
    if ($gatewayAmount!==$expectedAmount) throw new RuntimeException('Payment amount verification failed.');
    if ($gatewayCurrency!==RAZORPAY_CURRENCY) throw new RuntimeException('Payment currency verification failed.');
    if ($gatewayStatus!=='captured') throw new RuntimeException('Payment is not captured. Subscription access has not been granted.');

    $update=$conn->prepare("UPDATE live_exam_payments SET payment_status='Paid', gateway_payment_id=?, gateway_signature=?, gateway_status=?, gateway_method=?, gateway_currency=?, payment_method=?, paid_at=NOW() WHERE id=? AND student_id=? AND exam_id=? AND gateway_order_id=? AND payment_status<>'Paid'");
    $methodForSchema = in_array($gatewayMethod,['creditcard','debitcard','upi'],true) ? strtoupper($gatewayMethod)==='UPI'?'UPI':(str_contains($gatewayMethod,'debit')?'Debit Card':'Credit Card') : null;
    $update->execute([$paymentId,$signature,$gatewayStatus,$gatewayMethod,$gatewayCurrency,$methodForSchema,(int)$record['id'],$studentId,$examId,$orderId]);

    $conn->commit();
    live_verify_json(true,'Payment verified successfully. Live exam access is now unlocked.', ['redirect'=>'live_exams.php#payment-'.$examId]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Live exam payment verification failed: '.$e->getMessage());
    live_verify_json(false,'Unable to verify this payment safely. Please retry or contact support with your payment reference.',[],422);
}
