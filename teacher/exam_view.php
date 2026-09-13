<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../config/exam_validation.php';

require_login('teacher');

$teacherId = current_user_id();
$examId = filter_var(
    $_GET['id'] ?? '',
    FILTER_VALIDATE_INT
);

if ($examId === false || $examId <= 0) {
    http_response_code(400);
    exit('Invalid examination.');
}

function exam_view_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function exam_view_date(mixed $value): string
{
    if (empty($value)) {
        return 'Not scheduled';
    }

    $timestamp = strtotime((string)$value);

    return $timestamp !== false
        ? date('d M Y, h:i A', $timestamp)
        : 'Not available';
}

try {

    $examStmt = $conn->prepare(
        "
        SELECT
            e.id,
            e.subject_id,
            e.teacher_id,
            e.title,
            e.description,
            e.exam_type,
            e.duration_minutes,
            e.required_question_count,
            e.total_marks,
            e.passing_marks,
            e.negative_marking,
            e.exam_fee,
            e.subscription_required,
            e.starts_at,
            e.ends_at,
            e.status,
            e.created_at,
            e.updated_at,

            s.name AS subject_name,
            s.code AS subject_code

        FROM exams e

        INNER JOIN subjects s
            ON s.id = e.subject_id

        WHERE
            e.id = ?
            AND e.teacher_id = ?

        LIMIT 1
        "
    );

    $examStmt->execute([
        (int)$examId,
        $teacherId
    ]);

    $exam = $examStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$exam) {
        http_response_code(404);
        exit('Examination not found or access denied.');
    }

    $questionStmt = $conn->prepare(
        "
        SELECT
            eq.position,
            eq.question_id,

            q.question_text,
            q.question_type,
            q.option_a,
            q.option_b,
            q.option_c,
            q.option_d,
            q.correct_answer,
            q.explanation,
            q.marks,
            q.negative_marks,
            q.estimated_time_seconds,
            q.difficulty,
            q.status,

            t.name AS topic_name

        FROM exam_questions eq

        INNER JOIN questions q
            ON q.id = eq.question_id

        LEFT JOIN topics t
            ON t.id = q.topic_id

        WHERE
            eq.exam_id = ?
            AND q.created_by_teacher_id = ?

        ORDER BY
            eq.position ASC,
            eq.question_id ASC
        "
    );

    $questionStmt->execute([
        (int)$examId,
        $teacherId
    ]);

    $questions =
        $questionStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    $attemptStmt = $conn->prepare(
        "
        SELECT
            COUNT(*) AS attempt_count,
            SUM(
                CASE
                    WHEN status IN ('Started', 'In Progress', 'Running')
                    THEN 1
                    ELSE 0
                END
            ) AS active_attempt_count,
            SUM(
                CASE
                    WHEN status IN ('Completed', 'Submitted')
                    THEN 1
                    ELSE 0
                END
            ) AS completed_attempt_count
        FROM exam_attempts
        WHERE exam_id = ?
        "
    );

    $attemptStmt->execute([
        (int)$examId
    ]);

    $attemptData =
        $attemptStmt->fetch(
            PDO::FETCH_ASSOC
        ) ?: [];

    $attemptCount =
        (int)(
            $attemptData['attempt_count'] ?? 0
        );

    $activeAttemptCount =
        (int)(
            $attemptData['active_attempt_count'] ?? 0
        );

    $completedAttemptCount =
        (int)(
            $attemptData['completed_attempt_count'] ?? 0
        );

} catch (Throwable $exception) {

    error_log(
        'ExamSphere teacher exam view failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);
    exit('Unable to load examination details.');
}

$requiredCount =
    (int)(
        $exam['required_question_count'] ?? 0
    );

$actualCount =
    count($questions);

$configuredMarks =
    round(
        (float)(
            $exam['total_marks'] ?? 0
        ),
        2
    );

$actualMarks = 0.0;

foreach ($questions as $question) {
    $actualMarks +=
        (float)$question['marks'];
}

$actualMarks =
    round(
        $actualMarks,
        2
    );

$countReady =
    $requiredCount > 0 &&
    $actualCount === $requiredCount;

$marksReady =
    $configuredMarks > 0 &&
    abs(
        $actualMarks -
        $configuredMarks
    ) < 0.000001;

$isReady =
    $countReady &&
    $marksReady;

$marksDifference =
    round(
        $actualMarks -
        $configuredMarks,
        2
    );

$countDifference =
    $actualCount -
    $requiredCount;

$displayStatus =
    (string)$exam['status'];

if (
    $displayStatus === 'Active' &&
    !empty($exam['starts_at']) &&
    strtotime((string)$exam['starts_at']) > time()
) {
    $displayStatus = 'Upcoming';
}

if (
    $displayStatus === 'Active' &&
    !empty($exam['ends_at']) &&
    strtotime((string)$exam['ends_at']) !== false &&
    strtotime((string)$exam['ends_at']) < time()
) {
    $displayStatus = 'Completed';
}

$negativeMarking =
    (float)(
        $exam['negative_marking'] ?? 0
    );

$examFee =
    (float)(
        $exam['exam_fee'] ?? 0
    );

$subscriptionRequired =
    (int)(
        $exam['subscription_required'] ?? 0
    );

$marksPerQuestion = null;
$sameMarks = true;

if ($questions) {

    $firstMarks =
        (float)$questions[0]['marks'];

    foreach ($questions as $question) {

        if (
            abs(
                (float)$question['marks'] -
                $firstMarks
            ) > 0.000001
        ) {
            $sameMarks = false;
            break;
        }
    }

    if ($sameMarks) {
        $marksPerQuestion =
            round(
                $firstMarks,
                2
            );
    }
}

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
    View Exam | <?= exam_view_e($exam['title']) ?>
</title>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    rel="stylesheet"
>

<style>

:root{
    --earth:#5d4037;
    --earth-dark:#3f2b25;
    --olive:#556b2f;
    --cream:#f5f5dc;
    --page:#f6f4ed;
    --white:#fff;
    --text:#352c27;
    --muted:#857a71;
    --line:#e8dfd5;
    --soft:#fbfaf7;
    --green:#4f7040;
    --red:#9c4d44;
    --amber:#9a773c;
}

*{
    box-sizing:border-box;
}

body{
    margin:0;
    font-family:'Poppins',sans-serif;
    color:var(--text);
    background:
        radial-gradient(
            circle at 5% 0%,
            rgba(85,107,47,.08),
            transparent 25%
        ),
        linear-gradient(
            135deg,
            #faf8f2,
            #eeebe4
        );
}

.page{
    max-width:1220px;
    margin:0 auto;
    padding:20px 18px 60px;
}

.page-head{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:20px;
    margin-bottom:20px;
}

.kicker{
    color:var(--olive);
    font-size:.59rem;
    font-weight:900;
    letter-spacing:.14em;
}

.page-head h1{
    margin:7px 0 4px;
    color:var(--earth);
    font-size:1.9rem;
    font-weight:900;
    letter-spacing:-.035em;
}

.page-head p{
    margin:0;
    color:var(--muted);
    font-size:.68rem;
}

.head-actions{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}

.btn-soft{
    border-radius:11px;
    font-size:.65rem;
    font-weight:800;
}

.btn-earth{
    border:0;
    border-radius:11px;
    background:var(--earth);
    color:#fff;
    font-size:.65rem;
    font-weight:850;
}

.btn-earth:hover{
    background:var(--earth-dark);
    color:#fff;
}

.card-shell{
    border:1px solid rgba(93,64,55,.08);
    border-radius:20px;
    background:rgba(255,255,255,.88);
    box-shadow:0 18px 45px rgba(62,45,37,.08);
    overflow:hidden;
}

.card-shell + .card-shell{
    margin-top:20px;
}

.hero{
    position:relative;
    padding:24px;
}

.hero-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:20px;
}

.hero h2{
    margin:0;
    color:var(--earth);
    font-size:1.25rem;
    font-weight:900;
}

.hero-description{
    max-width:850px;
    margin:10px 0 0;
    color:var(--muted);
    font-size:.65rem;
    line-height:1.75;
}

.status{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 11px;
    border-radius:999px;
    font-size:.55rem;
    font-weight:900;
    white-space:nowrap;
}

.status.ready{
    background:#eaf3e6;
    color:var(--green);
}

.status.pending{
    background:#fff4de;
    color:var(--amber);
}

.status.blocked{
    background:#fcebea;
    color:var(--red);
}

.stats{
    display:grid;
    grid-template-columns:
        repeat(5,minmax(0,1fr));
    gap:12px;
    margin-top:22px;
}

.stat{
    padding:15px;
    border:1px solid var(--line);
    border-radius:15px;
    background:var(--soft);
}

.stat small{
    display:block;
    color:var(--muted);
    font-size:.54rem;
    font-weight:750;
}

.stat strong{
    display:block;
    margin-top:5px;
    color:var(--earth);
    font-size:1rem;
    font-weight:900;
}

.section-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    padding:18px 20px;
    border-bottom:1px solid var(--line);
}

.section-head h3{
    margin:0;
    color:var(--earth);
    font-size:.91rem;
    font-weight:900;
}

.section-head p{
    margin:4px 0 0;
    color:var(--muted);
    font-size:.60rem;
}

.info-grid{
    display:grid;
    grid-template-columns:
        repeat(3,minmax(0,1fr));
    gap:0;
}

.info{
    min-height:82px;
    padding:15px 18px;
    border-right:1px solid var(--line);
    border-bottom:1px solid var(--line);
}

.info:nth-child(3n){
    border-right:0;
}

.info-label{
    color:var(--muted);
    font-size:.53rem;
    font-weight:700;
}

.info-value{
    margin-top:5px;
    color:var(--earth);
    font-size:.67rem;
    font-weight:850;
    line-height:1.55;
}

.question{
    margin:14px 18px;
    padding:17px;
    border:1px solid var(--line);
    border-radius:15px;
    background:#fcfbf8;
}

.question-top{
    display:flex;
    align-items:flex-start;
    gap:12px;
}

.q-number{
    width:34px;
    height:34px;
    flex:0 0 34px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:10px;
    background:#eee8de;
    color:var(--earth);
    font-size:.62rem;
    font-weight:900;
}

.q-text{
    flex:1;
    color:var(--earth);
    font-size:.68rem;
    font-weight:750;
    line-height:1.65;
}

.q-meta{
    display:flex;
    flex-wrap:wrap;
    gap:7px;
    margin:9px 0 0 46px;
}

.q-meta span{
    padding:4px 7px;
    border-radius:7px;
    background:#f2ede5;
    color:var(--muted);
    font-size:.50rem;
    font-weight:750;
}

.options{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:9px;
    margin:13px 0 0 46px;
}

.option{
    padding:10px 12px;
    border:1px solid var(--line);
    border-radius:10px;
    background:#fff;
    font-size:.59rem;
    line-height:1.55;
}

.option b{
    color:var(--olive);
    margin-right:5px;
}

.correct{
    border-color:rgba(85,107,47,.25);
    background:#f2f6ed;
}

.question-explanation{
    margin:12px 0 0 46px;
    padding:11px 12px;
    border-radius:10px;
    background:#f7f3ea;
    color:var(--muted);
    font-size:.57rem;
    line-height:1.6;
}

.empty{
    padding:45px 20px;
    text-align:center;
    color:var(--muted);
}

.empty i{
    margin-bottom:10px;
    color:#aa9f94;
    font-size:1.7rem;
}

.empty strong{
    display:block;
    color:var(--earth);
    font-size:.72rem;
}

.empty span{
    display:block;
    margin-top:5px;
    font-size:.58rem;
}

.readiness{
    margin:18px 20px 20px;
    padding:15px;
    border-radius:13px;
    font-size:.60rem;
    line-height:1.7;
}

.readiness.ready{
    background:#edf5e8;
    color:#4b693f;
}

.readiness.pending{
    background:#fff5df;
    color:#8b6a34;
}

.readiness strong{
    font-weight:900;
}

@media(max-width:1000px){

    .stats{
        grid-template-columns:
            repeat(3,minmax(0,1fr));
    }

    .info-grid{
        grid-template-columns:
            repeat(2,minmax(0,1fr));
    }

    .info:nth-child(3n){
        border-right:1px solid var(--line);
    }

    .info:nth-child(2n){
        border-right:0;
    }

}

@media(max-width:700px){

    .page{
        padding-left:9px;
        padding-right:9px;
    }

    .page-head{
        align-items:flex-start;
        flex-direction:column;
    }

    .page-head h1{
        font-size:1.55rem;
    }

    .stats{
        grid-template-columns:
            repeat(2,minmax(0,1fr));
    }

    .info-grid{
        grid-template-columns:1fr;
    }

    .info,
    .info:nth-child(2n),
    .info:nth-child(3n){
        border-right:0;
    }

    .hero-top{
        flex-direction:column;
    }

    .options{
        grid-template-columns:1fr;
        margin-left:0;
    }

    .q-meta,
    .question-explanation{
        margin-left:0;
    }

}

@media(max-width:450px){

    .stats{
        grid-template-columns:1fr;
    }

    .question{
        margin-left:10px;
        margin-right:10px;
    }

}

</style>

</head>

<body>

<?php include 'includes/sidebar.php'; ?>

<main class="portal-main">

<div class="page">

<header class="page-head">

<div>

<div class="kicker">
    <i class="fa-solid fa-eye me-1"></i>
    EXAM PREVIEW
</div>

<h1>
    View Examination
</h1>

<p>
    Complete read-only overview of the examination configuration and question set.
</p>

</div>

<div class="head-actions">

<a
    href="exams.php"
    class="btn btn-outline-secondary btn-soft"
>

<i class="fa-solid fa-arrow-left me-1"></i>

My Exams

</a>

<a
    href="exam_questions.php?exam_id=<?= (int)$examId ?>"
    class="btn btn-earth"
>

<i class="fa-solid fa-list-check me-1"></i>

Manage Questions

</a>

</div>

</header>


<section class="card-shell hero">

<div class="hero-top">

<div>

<h2>
    <?= exam_view_e(
        $exam['title']
    ) ?>
</h2>

<?php if (
    !empty(
        $exam['description']
    )
): ?>

<p class="hero-description">
    <?= nl2br(
        exam_view_e(
            $exam['description']
        )
    ) ?>
</p>

<?php else: ?>

<p class="hero-description">
    No examination description has been added.
</p>

<?php endif; ?>

</div>


<span
    class="status <?= $isReady ? 'ready' : 'pending' ?>"
>

<i class="fa-solid <?= $isReady ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>

<?= $isReady ? 'READY' : 'NOT READY' ?>

</span>

</div>


<div class="stats">

<div class="stat">

<small>
    Required Questions
</small>

<strong>
    <?= (int)$requiredCount ?>
</strong>

</div>


<div class="stat">

<small>
    Assigned Questions
</small>

<strong>
    <?= (int)$actualCount ?>
</strong>

</div>


<div class="stat">

<small>
    Configured Marks
</small>

<strong>
    <?= number_format(
        $configuredMarks,
        2
    ) ?>
</strong>

</div>


<div class="stat">

<small>
    Actual Marks
</small>

<strong>
    <?= number_format(
        $actualMarks,
        2
    ) ?>
</strong>

</div>


<div class="stat">

<small>
    Duration
</small>

<strong>
    <?= (int)$exam['duration_minutes'] ?>
    min
</strong>

</div>

</div>


<div
    class="readiness <?= $isReady ? 'ready' : 'pending' ?>"
>

<?php if ($isReady): ?>

<i class="fa-solid fa-circle-check me-1"></i>

<strong>
    Examination configuration is ready.
</strong>

Assigned question count exactly matches the configured count and actual question marks exactly match the configured total.

<?php else: ?>

<i class="fa-solid fa-circle-exclamation me-1"></i>

<strong>
    Examination is not ready.
</strong>

Question count difference:
<strong>
    <?= $countDifference >= 0 ? '+' : '' ?><?= (int)$countDifference ?>
</strong>.

Marks difference:
<strong>
    <?= $marksDifference >= 0 ? '+' : '' ?><?= number_format($marksDifference, 2) ?>
</strong>.

Open Manage Questions to correct the assignment.

<?php endif; ?>

</div>

</section>


<section class="card-shell">

<div class="section-head">

<div>

<h3>
    Examination Configuration
</h3>

<p>
    Values stored in the current examination record.
</p>

</div>

<span class="status <?= $isReady ? 'ready' : 'pending' ?>">

<?= exam_view_e(
    $displayStatus
) ?>

</span>

</div>


<div class="info-grid">


<div class="info">
<span class="info-label">Subject</span>
<div class="info-value">
    <?= exam_view_e(
        $exam['subject_name']
    ) ?>

    <?php if (
        !empty(
            $exam['subject_code']
        )
    ): ?>

    <span class="text-muted">
        (<?= exam_view_e(
            $exam['subject_code']
        ) ?>)
    </span>

    <?php endif; ?>
</div>
</div>


<div class="info">
<span class="info-label">Exam Type</span>
<div class="info-value">
    <?= exam_view_e(
        $exam['exam_type']
    ) ?>
</div>
</div>


<div class="info">
<span class="info-label">Status</span>
<div class="info-value">
    <?= exam_view_e(
        $displayStatus
    ) ?>
</div>
</div>


<div class="info">
<span class="info-label">Passing Marks</span>
<div class="info-value">
    <?= number_format(
        (float)$exam['passing_marks'],
        2
    ) ?>
</div>
</div>


<div class="info">
<span class="info-label">Marks / Question</span>
<div class="info-value">
    <?= $marksPerQuestion !== null
        ? number_format(
            $marksPerQuestion,
            2
        )
        : 'Mixed'
    ?>
</div>
</div>


<div class="info">
<span class="info-label">Negative Marking</span>
<div class="info-value">
    <?= number_format(
        $negativeMarking,
        2
    ) ?>
</div>
</div>


<div class="info">
<span class="info-label">Exam Fee</span>
<div class="info-value">
    <?= $examFee > 0
        ? '₹' . number_format(
            $examFee,
            2
        )
        : 'Free'
    ?>
</div>
</div>


<div class="info">
<span class="info-label">Subscription Required</span>
<div class="info-value">
    <?= $subscriptionRequired
        ? 'Yes'
        : 'No'
    ?>
</div>
</div>


<div class="info">
<span class="info-label">Start</span>
<div class="info-value">
    <?= exam_view_date(
        $exam['starts_at']
    ) ?>
</div>
</div>


<div class="info">
<span class="info-label">End</span>
<div class="info-value">
    <?= exam_view_date(
        $exam['ends_at']
    ) ?>
</div>
</div>


<div class="info">
<span class="info-label">Created</span>
<div class="info-value">
    <?= exam_view_date(
        $exam['created_at']
    ) ?>
</div>
</div>


<div class="info">
<span class="info-label">Last Updated</span>
<div class="info-value">
    <?= exam_view_date(
        $exam['updated_at']
    ) ?>
</div>
</div>


</div>

</section>


<section class="card-shell">

<div class="section-head">

<div>

<h3>
    Attempt Overview
</h3>

<p>
    Current student attempt counts for this examination.
</p>

</div>

<span class="status <?= $attemptCount > 0 ? 'pending' : 'ready' ?>">

<?= (int)$attemptCount ?>
attempt<?= $attemptCount === 1 ? '' : 's' ?>

</span>

</div>


<div class="stats">

<div class="stat">
<small>Total Attempts</small>
<strong>
    <?= (int)$attemptCount ?>
</strong>
</div>

<div class="stat">
<small>Active Attempts</small>
<strong>
    <?= (int)$activeAttemptCount ?>
</strong>
</div>

<div class="stat">
<small>Completed Attempts</small>
<strong>
    <?= (int)$completedAttemptCount ?>
</strong>
</div>

<div class="stat">
<small>Question Status</small>
<strong>
    <?= $countReady
        ? 'Exact'
        : 'Mismatch'
    ?>
</strong>
</div>

<div class="stat">
<small>Marks Status</small>
<strong>
    <?= $marksReady
        ? 'Exact'
        : 'Mismatch'
    ?>
</strong>
</div>

</div>

</section>


<section class="card-shell">

<div class="section-head">

<div>

<h3>
    Assigned Questions
</h3>

<p>
    Questions are shown in their exact examination order.
</p>

</div>

<span class="status <?= $countReady ? 'ready' : 'pending' ?>">

<?= (int)$actualCount ?>
/
<?= (int)$requiredCount ?>

</span>

</div>


<?php if ($questions): ?>

<?php foreach (
    $questions as $question
): ?>

<article class="question">

<div class="question-top">

<div class="q-number">
    <?= (int)$question['position'] ?>
</div>

<div class="q-text">
    <?= nl2br(
        exam_view_e(
            $question['question_text']
        )
    ) ?>
</div>

</div>


<div class="q-meta">

<span>
    <?= exam_view_e(
        $question['question_type']
    ) ?>
</span>

<span>
    <?= number_format(
        (float)$question['marks'],
        2
    ) ?>
    marks
</span>

<span>
    -<?= number_format(
        (float)$question['negative_marks'],
        2
    ) ?>
    negative
</span>

<span>
    <?= exam_view_e(
        $question['difficulty']
    ) ?>
</span>

<?php if (
    !empty(
        $question['topic_name']
    )
): ?>

<span>
    <?= exam_view_e(
        $question['topic_name']
    ) ?>
</span>

<?php endif; ?>

</div>


<div class="options">

<div
    class="option <?= $question['correct_answer'] === 'A' ? 'correct' : '' ?>"
>

<b>A</b>

<?= exam_view_e(
    $question['option_a']
) ?>

</div>


<div
    class="option <?= $question['correct_answer'] === 'B' ? 'correct' : '' ?>"
>

<b>B</b>

<?= exam_view_e(
    $question['option_b']
) ?>

</div>


<?php if (
    !empty(
        $question['option_c']
    )
): ?>

<div
    class="option <?= $question['correct_answer'] === 'C' ? 'correct' : '' ?>"
>

<b>C</b>

<?= exam_view_e(
    $question['option_c']
) ?>

</div>

<?php endif; ?>


<?php if (
    !empty(
        $question['option_d']
    )
): ?>

<div
    class="option <?= $question['correct_answer'] === 'D' ? 'correct' : '' ?>"
>

<b>D</b>

<?= exam_view_e(
    $question['option_d']
) ?>

</div>

<?php endif; ?>

</div>


<?php if (
    !empty(
        $question['explanation']
    )
): ?>

<div class="question-explanation">

<i class="fa-regular fa-lightbulb me-1"></i>

<?= nl2br(
    exam_view_e(
        $question['explanation']
    )
) ?>

</div>

<?php endif; ?>

</article>

<?php endforeach; ?>

<?php else: ?>

<div class="empty">

<i class="fa-solid fa-file-circle-xmark"></i>

<strong>
    No questions assigned yet.
</strong>

<span>
    Open Manage Questions to build this examination's question set.
</span>

</div>

<?php endif; ?>

</section>


</div>

</main>

</body>

</html>
