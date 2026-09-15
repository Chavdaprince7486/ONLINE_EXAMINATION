<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/mail.php';
require_once '../../vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=UTF-8');

function result_email_json(bool $success, string $message, array $data = [], int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode(
        array_merge(
            [
                'status' => $success,
                'message' => $message,
            ],
            $data
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    result_email_json(false, 'Invalid request method.', [], 405);
}

if (
    empty($_SESSION['user_id']) ||
    (string)($_SESSION['user_role'] ?? '') !== 'student'
) {
    result_email_json(false, 'Unauthorized access.', [], 401);
}

$studentId = (int)$_SESSION['user_id'];
$attemptId = filter_var($_POST['attempt_id'] ?? null, FILTER_VALIDATE_INT);
$csrfToken = trim((string)($_POST['csrf_token'] ?? ''));

if ($attemptId === false || $attemptId === null || $attemptId <= 0) {
    result_email_json(false, 'Invalid examination attempt.', [], 422);
}

$sessionCsrf = (string)($_SESSION['csrf_token'] ?? '');
if ($csrfToken === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrfToken)) {
    result_email_json(false, 'Security verification failed. Please refresh the result page.', [], 419);
}

try {
    $stmt = $conn->prepare("
        SELECT
            r.id AS result_id,
            r.attempt_id,
            r.student_id,
            r.exam_id,
            r.total_questions,
            r.attempted_questions,
            r.correct_answers,
            r.wrong_answers,
            r.unanswered_questions,
            r.total_marks,
            r.obtained_marks,
            r.percentage,
            r.grade,
            r.result_status,
            r.created_at AS result_created_at,

            ea.started_at,
            ea.submitted_at,
            ea.status AS attempt_status,

            e.title AS exam_title,
            e.description AS exam_description,
            e.exam_type,
            e.duration_minutes,
            e.required_question_count,
            e.passing_marks,
            e.negative_marking,

            s.full_name,
            s.student_code,
            s.email,

            sub.name AS subject_name,
            sub.code AS subject_code
        FROM results r
        INNER JOIN exam_attempts ea
            ON ea.id = r.attempt_id
        INNER JOIN exams e
            ON e.id = r.exam_id
        INNER JOIN students s
            ON s.id = r.student_id
        LEFT JOIN subjects sub
            ON sub.id = e.subject_id
        WHERE
            r.attempt_id = ?
            AND r.student_id = ?
        LIMIT 1
    ");

    $stmt->execute([(int)$attemptId, $studentId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('ExamSphere result email query failed: ' . $exception->getMessage());
    result_email_json(false, 'Unable to load the result. Please try again.', [], 500);
}

if (!$result) {
    result_email_json(false, 'Result not found.', [], 404);
}

if (!in_array((string)$result['attempt_status'], ['Submitted', 'Auto Submitted'], true)) {
    result_email_json(false, 'This result is not finalized yet.', [], 409);
}

$recipient = trim((string)($result['email'] ?? ''));
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    result_email_json(false, 'Your registered email address is invalid.', [], 422);
}

$totalQuestions = (int)$result['total_questions'];
$attemptedQuestions = (int)$result['attempted_questions'];
$correctAnswers = (int)$result['correct_answers'];
$wrongAnswers = (int)$result['wrong_answers'];
$unansweredQuestions = (int)$result['unanswered_questions'];
$totalMarks = (float)$result['total_marks'];
$obtainedMarks = (float)$result['obtained_marks'];
$percentage = (float)$result['percentage'];
$passingMarks = (float)$result['passing_marks'];
$negativeMarking = (int)$result['negative_marking'] === 1;
$isPassed = (string)$result['result_status'] === 'Pass';
$accuracy = $attemptedQuestions > 0
    ? round(($correctAnswers / $attemptedQuestions) * 100, 2)
    : 0.0;

$timeTakenMinutes = null;
try {
    if (!empty($result['started_at']) && !empty($result['submitted_at'])) {
        $startedAt = new DateTimeImmutable((string)$result['started_at']);
        $submittedAt = new DateTimeImmutable((string)$result['submitted_at']);
        $seconds = max(0, $submittedAt->getTimestamp() - $startedAt->getTimestamp());
        $timeTakenMinutes = (int)ceil($seconds / 60);
    }
} catch (Throwable) {
    $timeTakenMinutes = null;
}

if ($percentage >= 90) {
    $remark = 'Outstanding Performance';
    $remarkText = 'Your score demonstrates excellent preparation and strong consistency.';
} elseif ($percentage >= 75) {
    $remark = 'Excellent Performance';
    $remarkText = 'You have built a strong foundation. Keep the same consistency in your preparation.';
} elseif ($percentage >= 60) {
    $remark = 'Very Good Performance';
    $remarkText = 'You are progressing well. Continue practising and focus on improving weak areas.';
} elseif ($percentage >= 40) {
    $remark = 'Good Effort';
    $remarkText = 'Review incorrect answers and practise regularly to improve your next attempt.';
} else {
    $remark = 'Keep Practising';
    $remarkText = 'Review your incorrect answers and continue practising for a stronger next attempt.';
}

function email_escape(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function email_number(float $value): string
{
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
}

$tempDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp';
$templateFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'result_pdf.php';
$cssFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'pdf.css';

try {
    if (!is_dir($tempDirectory) && !mkdir($tempDirectory, 0775, true) && !is_dir($tempDirectory)) {
        throw new RuntimeException('Unable to prepare PDF storage.');
    }

    if (!is_file($templateFile)) {
        throw new RuntimeException('PDF template file was not found.');
    }

    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'P',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 13,
        'tempDir' => $tempDirectory,
        'default_font' => 'dejavusans',
    ]);

    if (is_file($cssFile)) {
        $css = file_get_contents($cssFile);
        if ($css !== false && $css !== '') {
            // Apply the stylesheet as a real <style> block so mPDF never prints the CSS as visible text.
            $mpdf->WriteHTML('<style>\n' . $css . '\n</style>');
        }
    }

    ob_start();
    require $templateFile;
    $html = ob_get_clean();

    // Render the report body after the stylesheet has been registered.
    $mpdf->WriteHTML($html);
    $pdfBinary = $mpdf->Output('', Destination::STRING_RETURN);

    if (!is_string($pdfBinary) || $pdfBinary === '') {
        throw new RuntimeException('Unable to create the result PDF.');
    }

    $mail = getMailer();
    $mail->addAddress($recipient, (string)$result['full_name']);
    $mail->Subject = 'ExamSphere Result - ' . (string)$result['exam_title'];

    $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$result['exam_title']);
    $safeName = trim((string)$safeName, '_');
    if ($safeName === '') {
        $safeName = 'Exam';
    }

    $mail->addStringAttachment(
        $pdfBinary,
        'ExamSphere_Result_' . $safeName . '_' . (int)$attemptId . '.pdf',
        'base64',
        'application/pdf'
    );

    $timeLabel = $timeTakenMinutes === null
        ? 'Not available'
        : $timeTakenMinutes . ' min';

    $mail->Body = '
        <div style="font-family:Arial,sans-serif;max-width:700px;margin:0 auto;padding:24px;background:#F5F5DC;color:#3E2723;">
            <div style="background:#FFFFFF;border:1px solid #E3DDD2;border-radius:18px;overflow:hidden;">
                <div style="padding:22px 24px;background:#5D4037;color:#FFFFFF;">
                    <div style="font-size:24px;font-weight:700;">ExamSphere</div>
                    <div style="opacity:.85;font-size:13px;margin-top:4px;">Official Examination Result</div>
                </div>
                <div style="padding:24px;">
                    <p style="margin:0 0 12px;font-size:16px;">Hello <strong>' . email_escape((string)$result['full_name']) . '</strong>,</p>
                    <p style="margin:0 0 18px;color:#6E625A;line-height:1.6;">Your finalized examination result is attached as a PDF.</p>

                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <tr><td style="padding:8px 0;color:#786F68;">Exam</td><td style="padding:8px 0;text-align:right;font-weight:700;">' . email_escape((string)$result['exam_title']) . '</td></tr>
                        <tr><td style="padding:8px 0;color:#786F68;">Score</td><td style="padding:8px 0;text-align:right;font-weight:700;">' . email_number($obtainedMarks) . ' / ' . email_number($totalMarks) . '</td></tr>
                        <tr><td style="padding:8px 0;color:#786F68;">Percentage</td><td style="padding:8px 0;text-align:right;font-weight:700;">' . email_number($percentage) . '%</td></tr>
                        <tr><td style="padding:8px 0;color:#786F68;">Grade</td><td style="padding:8px 0;text-align:right;font-weight:700;">' . email_escape((string)$result['grade']) . '</td></tr>
                        <tr><td style="padding:8px 0;color:#786F68;">Result</td><td style="padding:8px 0;text-align:right;font-weight:700;color:' . ($isPassed ? '#27723C' : '#A33A3A') . ';">' . ($isPassed ? 'PASS' : 'FAIL') . '</td></tr>
                        <tr><td style="padding:8px 0;color:#786F68;">Accuracy</td><td style="padding:8px 0;text-align:right;font-weight:700;">' . email_number($accuracy) . '%</td></tr>
                        <tr><td style="padding:8px 0;color:#786F68;">Time Used</td><td style="padding:8px 0;text-align:right;font-weight:700;">' . email_escape($timeLabel) . '</td></tr>
                    </table>

                    <div style="margin-top:20px;padding:14px 16px;border-radius:12px;background:#FAF8F4;border:1px solid #ECE5DC;line-height:1.6;">
                        <strong>' . email_escape($remark) . '</strong><br>
                        <span style="color:#6E625A;">' . email_escape($remarkText) . '</span>
                    </div>

                    <p style="margin:22px 0 0;color:#8A817A;font-size:12px;">Attempt #' . (int)$attemptId . ' · Result #' . (int)$result['result_id'] . '</p>
                </div>
            </div>
            <p style="text-align:center;color:#8A817A;font-size:11px;margin:14px 0 0;">ExamSphere · Smart Assessment · Seamless Learning · Real Results</p>
        </div>';

    $mail->AltBody =
        'ExamSphere Result\n' .
        'Exam: ' . (string)$result['exam_title'] . '\n' .
        'Score: ' . email_number($obtainedMarks) . ' / ' . email_number($totalMarks) . '\n' .
        'Percentage: ' . email_number($percentage) . '%\n' .
        'Grade: ' . (string)$result['grade'] . '\n' .
        'Result: ' . ($isPassed ? 'PASS' : 'FAIL');

    $mail->send();

    result_email_json(true, 'Result emailed successfully to your registered email address.', [
        'attempt_id' => (int)$attemptId,
        'result_id' => (int)$result['result_id'],
        'email' => $recipient,
    ]);
} catch (Throwable $exception) {
    error_log('ExamSphere result email failed: ' . $exception->getMessage());
    result_email_json(false, 'Unable to send the result email. Please check the mail configuration and try again.', [], 500);
}
