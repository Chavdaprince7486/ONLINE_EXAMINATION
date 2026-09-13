<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

require_login('teacher');

$teacherId = current_user_id();

$resultId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $resultId === false ||
    $resultId === null ||
    $resultId <= 0
) {
    http_response_code(400);
    exit('Invalid result.');
}

function teacher_result_view_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function teacher_result_view_number(mixed $value): string
{
    $number = (float)$value;

    return rtrim(
        rtrim(
            number_format(
                $number,
                2,
                '.',
                ''
            ),
            '0'
        ),
        '.'
    );
}

function teacher_result_view_date(mixed $value): string
{
    if (empty($value)) {
        return '—';
    }

    try {
        return (
            new DateTimeImmutable(
                (string)$value
            )
        )->format(
            'd M Y, h:i A'
        );
    } catch (Throwable) {
        return '—';
    }
}

try {

    $resultStmt = $conn->prepare(
        "
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
            r.created_at,

            s.student_code,
            s.full_name,
            s.email,

            e.title AS exam_title,
            e.exam_type,
            e.duration_minutes,
            e.passing_marks,
            e.negative_marking,

            sub.name AS subject_name,
            sub.code AS subject_code,

            ea.started_at,
            ea.submitted_at,
            ea.status AS attempt_status

        FROM results r

        INNER JOIN exams e
            ON e.id = r.exam_id
            AND e.teacher_id = ?

        INNER JOIN students s
            ON s.id = r.student_id

        LEFT JOIN subjects sub
            ON sub.id = e.subject_id

        INNER JOIN exam_attempts ea
            ON ea.id = r.attempt_id
            AND ea.student_id = r.student_id
            AND ea.exam_id = r.exam_id

        WHERE
            r.id = ?

        LIMIT 1
        "
    );

    $resultStmt->execute([
        $teacherId,
        (int)$resultId
    ]);

    $result =
        $resultStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$result) {
        http_response_code(404);
        exit('Result not found or access denied.');
    }

    $answerStmt = $conn->prepare(
        "
        SELECT

            q.id AS question_id,
            q.question_text,
            q.option_a,
            q.option_b,
            q.option_c,
            q.option_d,
            q.correct_answer,
            q.explanation,
            q.marks,
            q.negative_marks,

            a.selected_answer,
            a.question_status,
            a.is_correct,
            a.marks_awarded

        FROM exam_questions eq

        INNER JOIN questions q
            ON q.id = eq.question_id

        LEFT JOIN answers a
            ON a.question_id = q.id
            AND a.attempt_id = ?

        WHERE
            eq.exam_id = ?

        ORDER BY
            eq.position ASC
        "
    );

    $answerStmt->execute([
        (int)$result['attempt_id'],
        (int)$result['exam_id']
    ]);

    $questionAnalysis =
        $answerStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'ExamSphere teacher result view failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);
    exit('Unable to load result details.');
}

$isPass =
    (string)$result['result_status'] === 'Pass';

?>
<!doctype html>
<html lang="en">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>
    Result Details | ExamSphere
</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="../assets/css/portal.css"
>

<style>

:root{
    --earth:#5d4037;
    --olive:#556b2f;
    --muted:#857a72;
    --line:#e7dfd5;
    --soft:#fbfaf6;
    --green:#4e713f;
    --red:#9b5148;
}

body{
    margin:0;
    font-family:'Poppins',sans-serif;
    background:#f5f2ea;
    color:#382e29;
}

.result-view-page{
    width:min(1200px,calc(100vw - 24px));
    margin:0 auto;
    padding:20px 0 55px;
}

.result-head{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:18px;
    margin-bottom:18px;
}

.result-head h1{
    margin:0;
    color:var(--earth);
    font-size:1.65rem;
    font-weight:900;
}

.result-head p{
    margin:5px 0 0;
    color:var(--muted);
    font-size:.68rem;
}

.actions{
    display:flex;
    gap:8px;
}

.action{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    min-height:36px;
    padding:0 11px;
    border-radius:10px;
    text-decoration:none;
    font-size:.54rem;
    font-weight:850;
}

.action.back{
    border:1px solid var(--line);
    background:#fff;
    color:var(--earth);
}

.action.pdf{
    background:#eef5e9;
    color:var(--olive);
}

.summary,
.card{
    border:1px solid rgba(93,64,55,.08);
    border-radius:18px;
    background:rgba(255,255,255,.89);
    box-shadow:0 15px 38px rgba(64,47,38,.07);
    overflow:hidden;
}

.summary{
    padding:20px;
}

.summary-top{
    display:flex;
    justify-content:space-between;
    gap:18px;
    align-items:flex-start;
}

.summary-title{
    color:var(--earth);
    font-size:1.10rem;
    font-weight:900;
}

.summary-meta{
    margin-top:5px;
    color:var(--muted);
    font-size:.64rem;
}

.status{
    padding:7px 10px;
    border-radius:999px;
    font-size:.57rem;
    font-weight:900;
}

.status.pass{
    background:#edf5e9;
    color:var(--green);
}

.status.fail{
    background:#f9eae7;
    color:var(--red);
}

.stats{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:10px;
    margin-top:18px;
}

.stat{
    padding:13px;
    border:1px solid var(--line);
    border-radius:13px;
    background:var(--soft);
}

.stat small{
    display:block;
    color:var(--muted);
    font-size:.50rem;
    font-weight:700;
}

.stat strong{
    display:block;
    margin-top:4px;
    color:var(--earth);
    font-size:1.02rem;
    font-weight:900;
}

.row-card{
    padding:18px 20px;
    border-top:1px solid var(--line);
}

.row-card h2{
    margin:0 0 13px;
    color:var(--earth);
    font-size:.92rem;
    font-weight:900;
}

.info-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:12px;
}

.info{
    padding:12px;
    border:1px solid var(--line);
    border-radius:12px;
    background:#fcfaf7;
}

.info span{
    display:block;
    color:var(--muted);
    font-size:.50rem;
}

.info strong{
    display:block;
    margin-top:4px;
    color:var(--earth);
    font-size:.59rem;
}

.question{
    padding:15px 18px;
    border-top:1px solid var(--line);
}

.q-title{
    color:var(--earth);
    font-size:.70rem;
    font-weight:800;
    line-height:1.6;
}

.q-meta{
    margin-top:6px;
    color:var(--muted);
    font-size:.54rem;
}

.answer-line{
    margin-top:8px;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}

.answer-pill{
    padding:5px 7px;
    border-radius:8px;
    background:#f3eee5;
    color:#675a51;
    font-size:.48rem;
    font-weight:750;
}

.answer-pill.correct{
    background:#edf5e9;
    color:var(--green);
}

.answer-pill.wrong{
    background:#f9eae7;
    color:var(--red);
}

.empty{
    padding:35px;
    text-align:center;
    color:var(--muted);
    font-size:.58rem;
}

@media(max-width:850px){

    .stats{
        grid-template-columns:repeat(2,1fr);
    }

    .info-grid{
        grid-template-columns:repeat(2,1fr);
    }

    .result-head{
        align-items:flex-start;
        flex-direction:column;
    }

}

@media(max-width:500px){

    .stats,
    .info-grid{
        grid-template-columns:1fr;
    }

    .actions{
        width:100%;
    }

    .action{
        flex:1;
    }

}

/* Big & Bold readability upgrade — layout preserved */
.result-head h1{font-size:2rem;font-weight:900;line-height:1.2;}
.result-head p{font-size:.82rem;font-weight:600;line-height:1.6;}
.action{min-height:42px;padding:0 15px;font-size:.72rem;font-weight:900;}
.summary{padding:24px;}
.summary-title{font-size:1.35rem;font-weight:900;line-height:1.35;}
.summary-meta{font-size:.78rem;font-weight:600;line-height:1.6;}
.status{padding:9px 13px;font-size:.70rem;font-weight:900;}
.stat{padding:16px;}
.stat small{font-size:.66rem;font-weight:800;line-height:1.4;}
.stat strong{font-size:1.22rem;font-weight:900;line-height:1.25;}
.row-card{padding:22px 24px;}
.row-card h2{font-size:1.12rem;font-weight:900;line-height:1.35;}
.info{padding:15px;}
.info span{font-size:.65rem;font-weight:700;line-height:1.4;}
.info strong{font-size:.76rem;font-weight:850;line-height:1.5;}
.question{padding:19px 22px;}
.q-title{font-size:.86rem;font-weight:850;line-height:1.75;}
.q-meta{font-size:.68rem;font-weight:650;line-height:1.55;}
.answer-pill{padding:7px 10px;font-size:.65rem;font-weight:850;line-height:1.4;}
.empty{padding:42px;font-size:.72rem;font-weight:650;line-height:1.7;}
@media(max-width:850px){
.result-head h1{font-size:1.7rem;}
.result-head p{font-size:.76rem;}
}
@media(max-width:500px){
.result-head h1{font-size:1.5rem;}
.result-head p{font-size:.72rem;}
.action{font-size:.68rem;}
.summary-title{font-size:1.15rem;}
.stat strong{font-size:1.1rem;}
.q-title{font-size:.8rem;}
}

</style>

</head>

<body class="portal-body">

<div class="portal-layout">

<?php include 'includes/sidebar.php'; ?>

<main class="portal-main">

<div class="result-view-page">

<header class="result-head">

<div>

<h1>
    Result Details
</h1>

<p>
    <?= teacher_result_view_e(
        $result['exam_title']
    ) ?>

    ·

    <?= teacher_result_view_e(
        $result['full_name']
    ) ?>
</p>

</div>

<div class="actions">

<a
    href="results.php"
    class="action back"
>

<i class="fa-solid fa-arrow-left"></i>

Back

</a>

<a
    href="download_result_pdf.php?attempt_id=<?= (int)$result['attempt_id'] ?>"
    class="action pdf"
>

<i class="fa-solid fa-file-pdf"></i>

Download PDF

</a>

</div>

</header>


<section class="summary">

<div class="summary-top">

<div>

<div class="summary-title">

<?= teacher_result_view_e(
    $result['exam_title']
) ?>

</div>

<div class="summary-meta">

<?= teacher_result_view_e(
    $result['subject_name']
        ?? 'General'
) ?>

&nbsp; · &nbsp;

<?= teacher_result_view_e(
    $result['exam_type']
) ?>

&nbsp; · &nbsp;

<?= teacher_result_view_e(
    $result['student_code']
) ?>

</div>

</div>

<span
    class="status <?= $isPass
        ? 'pass'
        : 'fail'
    ?>"
>

<?= $isPass
    ? 'PASSED'
    : 'FAILED'
?>

</span>

</div>


<div class="stats">

<div class="stat">
<small>Score</small>
<strong>
    <?= teacher_result_view_number(
        $result['obtained_marks']
    ) ?>
    /
    <?= teacher_result_view_number(
        $result['total_marks']
    ) ?>
</strong>
</div>

<div class="stat">
<small>Percentage</small>
<strong>
    <?= teacher_result_view_number(
        $result['percentage']
    ) ?>%
</strong>
</div>

<div class="stat">
<small>Correct</small>
<strong>
    <?= (int)$result['correct_answers'] ?>
</strong>
</div>

<div class="stat">
<small>Wrong</small>
<strong>
    <?= (int)$result['wrong_answers'] ?>
</strong>
</div>

<div class="stat">
<small>Unanswered</small>
<strong>
    <?= (int)$result['unanswered_questions'] ?>
</strong>
</div>

</div>

</section>


<section class="card mt-3">

<div class="row-card">

<h2>
    Student Information
</h2>

<div class="info-grid">

<div class="info">
<span>Name</span>
<strong>
    <?= teacher_result_view_e(
        $result['full_name']
    ) ?>
</strong>
</div>

<div class="info">
<span>Student Code</span>
<strong>
    <?= teacher_result_view_e(
        $result['student_code']
    ) ?>
</strong>
</div>

<div class="info">
<span>Email</span>
<strong>
    <?= teacher_result_view_e(
        $result['email']
    ) ?>
</strong>
</div>

</div>

</div>


<div class="row-card">

<h2>
    Examination Information
</h2>

<div class="info-grid">

<div class="info">
<span>Total Questions</span>
<strong>
    <?= (int)$result['total_questions'] ?>
</strong>
</div>

<div class="info">
<span>Total Marks</span>
<strong>
    <?= teacher_result_view_number(
        $result['total_marks']
    ) ?>
</strong>
</div>

<div class="info">
<span>Passing Marks</span>
<strong>
    <?= teacher_result_view_number(
        $result['passing_marks']
    ) ?>
</strong>
</div>

<div class="info">
<span>Duration</span>
<strong>
    <?= (int)$result['duration_minutes'] ?> minutes
</strong>
</div>

<div class="info">
<span>Started</span>
<strong>
    <?= teacher_result_view_date(
        $result['started_at']
    ) ?>
</strong>
</div>

<div class="info">
<span>Submitted</span>
<strong>
    <?= teacher_result_view_date(
        $result['submitted_at']
    ) ?>
</strong>
</div>

</div>

</div>


<div class="row-card">

<h2>
    Question-wise Analysis
</h2>

<?php if ($questionAnalysis): ?>

<?php foreach (
    $questionAnalysis as $index => $question
): ?>

<article class="question">

<div class="q-title">

<?= ($index + 1) ?>.

<?= nl2br(
    teacher_result_view_e(
        $question['question_text']
    )
) ?>

</div>

<div class="q-meta">

Marks:
<?= teacher_result_view_number(
    $question['marks']
) ?>

&nbsp; · &nbsp;

Awarded:
<?= teacher_result_view_number(
    $question['marks_awarded']
) ?>

</div>

<div class="answer-line">

<span class="answer-pill">

Selected:
<?= teacher_result_view_e(
    $question['selected_answer']
        ?: 'Not answered'
) ?>

</span>

<span class="answer-pill">

Correct:
<?= teacher_result_view_e(
    $question['correct_answer']
) ?>

</span>

<span
    class="answer-pill <?= (int)$question['is_correct'] === 1
        ? 'correct'
        : 'wrong'
    ?>"
>

<?= (int)$question['is_correct'] === 1
    ? 'Correct'
    : (
        empty(
            $question['selected_answer']
        )
            ? 'Unanswered'
            : 'Wrong'
      )
?>

</span>

</div>

</article>

<?php endforeach; ?>

<?php else: ?>

<div class="empty">
    No question-wise analysis is available.
</div>

<?php endif; ?>

</div>

</section>

</div>

</main>

</div>

</body>

</html>
