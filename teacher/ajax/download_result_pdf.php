<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('pcre.backtrack_limit', '10000000');
ini_set('pcre.recursion_limit', '10000000');

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/auth.php';
require_once '../../vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

require_login('teacher');
$teacherId = current_user_id();
$resultId = filter_input(INPUT_GET, 'result_id', FILTER_VALIDATE_INT);
if ($resultId === false || $resultId === null || $resultId <= 0) {
    http_response_code(400);
    exit('Invalid result ID.');
}

function tp_e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function tp_num(float $value): string { return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'); }
function tp_date(?string $value): string { if (!$value) return '—'; try { return (new DateTimeImmutable($value))->format('d M Y, h:i A'); } catch(Throwable) { return '—'; } }

$stmt = $conn->prepare("
    SELECT
        r.id AS result_id, r.attempt_id, r.total_questions, r.attempted_questions,
        r.correct_answers, r.wrong_answers, r.unanswered_questions, r.total_marks,
        r.obtained_marks, r.percentage, r.grade, r.result_status, r.created_at,
        ea.started_at, ea.submitted_at,
        e.title AS exam_title, e.exam_type, e.description AS exam_description,
        e.duration_minutes, e.passing_marks, e.negative_marking,
        s.full_name, s.student_code, s.email,
        sub.name AS subject_name, sub.code AS subject_code
    FROM results r
    INNER JOIN exam_attempts ea ON ea.id = r.attempt_id
    INNER JOIN exams e ON e.id = r.exam_id
    INNER JOIN students s ON s.id = r.student_id
    LEFT JOIN subjects sub ON sub.id = e.subject_id
    WHERE r.id = ? AND e.teacher_id = ?
    LIMIT 1
");
$stmt->execute([$resultId, $teacherId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$result) {
    http_response_code(404);
    exit('Result not found or access denied.');
}

$qstmt = $conn->prepare("
    SELECT sa.question_id, sa.selected_option, sa.obtained_marks, sa.is_correct, sa.question_status,
           q.question_text, q.marks,
           (SELECT qo.option_label FROM question_options qo WHERE qo.question_id=q.id AND qo.is_correct=1 LIMIT 1) AS correct_option,
           (SELECT qo.option_text FROM question_options qo WHERE qo.question_id=q.id AND qo.option_label=sa.selected_option LIMIT 1) AS selected_option_text,
           (SELECT qo.option_text FROM question_options qo WHERE qo.question_id=q.id AND qo.is_correct=1 LIMIT 1) AS correct_option_text
    FROM student_answers sa
    INNER JOIN questions q ON q.id=sa.question_id
    WHERE sa.attempt_id=?
    ORDER BY q.id ASC
");
$qstmt->execute([(int)$result['attempt_id']]);
$answers = $qstmt->fetchAll(PDO::FETCH_ASSOC);

$totalQuestions=(int)$result['total_questions'];
$attempted=(int)$result['attempted_questions'];
$correct=(int)$result['correct_answers'];
$wrong=(int)$result['wrong_answers'];
$unanswered=(int)$result['unanswered_questions'];
$totalMarks=(float)$result['total_marks'];
$obtainedMarks=(float)$result['obtained_marks'];
$percentage=(float)$result['percentage'];
$accuracy=$attempted>0 ? round(($correct/$attempted)*100,1) : 0.0;
$isPass=(string)$result['result_status']==='Pass';
$timeUsed='—';
if (!empty($result['started_at']) && !empty($result['submitted_at'])) {
    try {
        $seconds=max(0,(new DateTimeImmutable((string)$result['submitted_at']))->getTimestamp()-(new DateTimeImmutable((string)$result['started_at']))->getTimestamp());
        $timeUsed=gmdate('H:i:s',$seconds);
    } catch(Throwable) {}
}

$logoPath = realpath(__DIR__ . '/../../assets/images/exam_logo.png');

$mpdf = new Mpdf([
    'format' => 'A4',
    'margin_left' => 12,
    'margin_right' => 12,
    'margin_top' => 16,
    'margin_bottom' => 16,
    'tempDir' => sys_get_temp_dir(),
]);
$mpdf->SetTitle('ExamSphere Result - '.$result['full_name']);
$mpdf->SetAuthor('ExamSphere');

if ($logoPath && is_file($logoPath)) {
    $mpdf->Image($logoPath, 12, 8, 24, 24, '', '', true, false);
}

$html = '<!doctype html><html><head><meta charset="UTF-8"><style>
body{font-family:dejavusans,sans-serif;color:#302823;font-size:10.5pt}.brand{margin-left:30px}.brand h1{margin:0;font-size:22pt;color:#5d4037}.brand p{margin:2px 0;color:#746961;font-size:9pt}.line{border-top:2px solid #556b2f;margin:9px 0 14px}.title{font-size:18pt;font-weight:bold;color:#422d26;margin:0}.subtitle{color:#746961;margin:3px 0 10px}.status{font-weight:bold;font-size:12pt;padding:6px 12px;border-radius:14px}.pass{background:#eaf5ea;color:#426b35}.fail{background:#f8e9e7;color:#944b43}.hero{width:100%;border-collapse:separate;border-spacing:0;margin-bottom:10px}.box{border:1px solid #e1d8cc;border-radius:8px;padding:10px}.kpi{width:25%;vertical-align:top}.kpi .label{font-size:8.5pt;color:#746961}.kpi .value{font-size:15pt;font-weight:bold;margin-top:4px}.section-title{font-size:12pt;font-weight:bold;color:#5d4037;background:#f7f3ed;padding:7px 9px;border-left:4px solid #556b2f;margin-top:12px}.info{width:100%;border-collapse:collapse}.info td{border:1px solid #e1d8cc;padding:6px}.info td:first-child{width:32%;font-weight:bold;background:#fbfaf6}.summary{width:100%;border-collapse:separate;border-spacing:6px}.summary td{border:1px solid #e1d8cc;padding:8px}.qtable{width:100%;border-collapse:collapse;margin-top:6px}.qtable th{background:#5d4037;color:#fff;padding:6px;font-size:8.5pt}.qtable td{border:1px solid #e1d8cc;padding:5px;font-size:8pt;vertical-align:top}.correct{color:#426b35;font-weight:bold}.wrong{color:#944b43;font-weight:bold}.skip{color:#8e6a2e;font-weight:bold}.footer{margin-top:15px;border-top:1px solid #e1d8cc;padding-top:7px;text-align:center;color:#746961;font-size:8.5pt}
</style></head><body>';
$html .= '<div class="brand"><h1>ExamSphere</h1><p>Smart • Secure • Instant Online Examination</p></div><div class="line"></div>';
$html .= '<table class="hero"><tr><td><div class="title">'.tp_e($result['exam_title']).'</div><div class="subtitle">'.tp_e($result['subject_name'] ?? 'General').' • '.tp_e($result['exam_type'] ?? '').'</div></td><td style="width:110px;text-align:right"><span class="status '.($isPass?'pass':'fail').'">'.($isPass?'PASS':'FAIL').'</span></td></tr></table>';
$html .= '<table class="summary"><tr>';
$html .= '<td class="kpi"><div class="label">Obtained Marks</div><div class="value">'.tp_num($obtainedMarks).' / '.tp_num($totalMarks).'</div></td>';
$html .= '<td class="kpi"><div class="label">Percentage</div><div class="value">'.tp_num($percentage).'%</div></td>';
$html .= '<td class="kpi"><div class="label">Grade</div><div class="value">'.tp_e($result['grade'] ?? '—').'</div></td>';
$html .= '<td class="kpi"><div class="label">Accuracy</div><div class="value">'.tp_num($accuracy).'%</div></td>';
$html .= '</tr></table>';
$html .= '<div class="section-title">Student Information</div><table class="info">';
$html .= '<tr><td>Student Name</td><td>'.tp_e($result['full_name']).'</td><td>Student Code</td><td>'.tp_e($result['student_code']).'</td></tr>';
$html .= '<tr><td>Email</td><td>'.tp_e($result['email']).'</td><td>Result ID</td><td>#'.(int)$result['result_id'].'</td></tr></table>';
$html .= '<div class="section-title">Exam Information</div><table class="info">';
$html .= '<tr><td>Total Questions</td><td>'.$totalQuestions.'</td><td>Attempted</td><td>'.$attempted.'</td></tr>';
$html .= '<tr><td>Correct</td><td>'.$correct.'</td><td>Wrong</td><td>'.$wrong.'</td></tr>';
$html .= '<tr><td>Unanswered</td><td>'.$unanswered.'</td><td>Passing Marks</td><td>'.tp_num((float)$result['passing_marks']).'</td></tr>';
$html .= '<tr><td>Time Used</td><td>'.tp_e($timeUsed).'</td><td>Submitted</td><td>'.tp_e(tp_date((string)$result['submitted_at'])).'</td></tr></table>';
$html .= '<div class="section-title">Question-wise Result</div><table class="qtable"><thead><tr><th width="5%">#</th><th width="39%">Question</th><th width="17%">Your Answer</th><th width="17%">Correct Answer</th><th width="11%">Status</th><th width="11%">Marks</th></tr></thead><tbody>';
if ($answers) {
    $n=1;
    foreach ($answers as $a) {
        $status=((int)$a['is_correct']===1)?'correct':(((string)($a['question_status']??'')==='ANSWERED')?'wrong':'skip');
        $statusText=$status==='correct'?'Correct':($status==='wrong'?'Wrong':'Skipped');
        $selected=trim((string)($a['selected_option']??''))!==''?tp_e($a['selected_option']):'—';
        $correctOpt=trim((string)($a['correct_option']??''))!==''?tp_e($a['correct_option']):'—';
        if (!empty($a['selected_option_text'])) $selected.='<br><span style="color:#746961">'.tp_e($a['selected_option_text']).'</span>';
        if (!empty($a['correct_option_text'])) $correctOpt.='<br><span style="color:#746961">'.tp_e($a['correct_option_text']).'</span>';
        $html.='<tr><td>'.$n++.'</td><td>'.nl2br(tp_e($a['question_text'])).'</td><td>'.$selected.'</td><td>'.$correctOpt.'</td><td class="'.$status.'">'.$statusText.'</td><td>'.tp_num((float)$a['obtained_marks']).' / '.tp_num((float)$a['marks']).'</td></tr>';
    }
} else {
    $html.='<tr><td colspan="6" style="text-align:center;color:#746961">No question-wise answer data available.</td></tr>';
}
$html .= '</tbody></table>';
$html .= '<div class="footer">Generated by ExamSphere • Result ID #'.(int)$result['result_id'].' • '.date('d M Y, h:i A').'</div></body></html>';

$mpdf->WriteHTML($html);
$filename='ExamSphere_Result_'.(int)$result['result_id'].'.pdf';
$mpdf->Output($filename, Destination::DOWNLOAD);
exit;
