<?php

declare(strict_types=1);

ini_set('pcre.backtrack_limit', '10000000');
ini_set('pcre.recursion_limit', '10000000');

session_start();

require_once '../../config/config.php';
require_once '../../vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {
    http_response_code(403);
    exit('Unauthorized access.');
}

$studentId = (int)$_SESSION['user_id'];

$attemptId = filter_input(INPUT_GET, 'attempt_id', FILTER_VALIDATE_INT);

if ($attemptId === false || $attemptId === null || $attemptId <= 0) {
    $attemptId = filter_input(INPUT_POST, 'attempt_id', FILTER_VALIDATE_INT);
}

if ($attemptId === false || $attemptId === null || $attemptId <= 0) {
    http_response_code(400);
    exit('Invalid attempt ID.');
}

function pdf_escape(?string $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function pdf_number(float $value): string
{
    return rtrim(
        rtrim(number_format($value, 2, '.', ''), '0'),
        '.'
    );
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
            e.total_marks AS exam_total_marks,
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

    $stmt->execute([
        $attemptId,
        $studentId
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $exception) {
    error_log('Result PDF query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load result.');
}

if (!$result) {
    http_response_code(404);
    exit('Result not found.');
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
$isPassed = $result['result_status'] === 'Pass';

$accuracy = $attemptedQuestions > 0
    ? round(($correctAnswers / $attemptedQuestions) * 100, 2)
    : 0;

$timeTakenMinutes = null;

try {
    if (!empty($result['started_at']) && !empty($result['submitted_at'])) {
        $started = new DateTimeImmutable($result['started_at']);
        $submitted = new DateTimeImmutable($result['submitted_at']);
        $seconds = max(0, $submitted->getTimestamp() - $started->getTimestamp());
        $timeTakenMinutes = (int)ceil($seconds / 60);
    }
} catch (Throwable $exception) {
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

$tempDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp';

if (!is_dir($tempDirectory) && !mkdir($tempDirectory, 0775, true) && !is_dir($tempDirectory)) {
    http_response_code(500);
    exit('Unable to prepare PDF storage.');
}

$templateFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'result_pdf.php';
$cssFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'pdf.css';

if (!is_file($templateFile)) {
    http_response_code(500);
    exit('PDF template file was not found.');
}

try {
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'P',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 13,
        'tempDir' => $tempDirectory,
        'default_font' => 'dejavusans'
    ]);

    $css = '';
    if (is_file($cssFile)) {
        $cssContents = file_get_contents($cssFile);
        if ($cssContents !== false) {
            $css = $cssContents;
        }
    }

    ob_start();
    require $templateFile;
    $html = ob_get_clean();

    // Important: send one complete HTML document to mPDF.
    // This avoids PDF outputting the raw CSS as visible text on older mPDF builds.
    $document = '<!doctype html><html><head><meta charset="UTF-8"><style>'
        . $css
        . '</style></head><body>'
        . $html
        . '</body></html>';

    $mpdf->WriteHTML($document);

    $pdfPath = $tempDirectory . DIRECTORY_SEPARATOR . 'result_' . $attemptId . '.pdf';

    $mpdf->Output($pdfPath, Destination::FILE);
    $mpdf->Output(
        'ExamSphere_Result_' . $attemptId . '.pdf',
        Destination::DOWNLOAD
    );

} catch (Throwable $exception) {
    error_log('Result PDF generation failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to generate result PDF.');
}

exit;
