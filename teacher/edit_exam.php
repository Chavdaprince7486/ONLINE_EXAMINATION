<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../config/exam_validation.php';
require_once '../config/exam_builder.php';

require_login('teacher');

$teacherId = current_user_id();

$examId = filter_var(
    $_GET['id'] ?? $_POST['exam_id'] ?? '',
    FILTER_VALIDATE_INT
);

if ($examId === false || $examId <= 0) {
    http_response_code(400);
    exit('Invalid examination.');
}

$error = '';
$success = '';

function teacher_edit_exam_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function teacher_edit_exam_datetime_value(mixed $value): string
{
    if (empty($value)) {
        return '';
    }

    $timestamp = strtotime((string)$value);

    return $timestamp === false
        ? ''
        : date('Y-m-d\TH:i', $timestamp);
}

function teacher_edit_exam_load(
    PDO $conn,
    int $examId,
    int $teacherId
): array {
    $stmt = $conn->prepare(
        "
        SELECT
            e.*,
            s.name AS subject_name,
            s.code AS subject_code
        FROM exams e
        LEFT JOIN subjects s
            ON s.id = e.subject_id
        WHERE
            e.id = ?
            AND e.teacher_id = ?
        LIMIT 1
        "
    );

    $stmt->execute([
        $examId,
        $teacherId
    ]);

    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        http_response_code(404);
        exit('Examination not found or access denied.');
    }

    return $exam;
}

try {

    $exam = teacher_edit_exam_load(
        $conn,
        $examId,
        $teacherId
    );

    $attemptStmt = $conn->prepare(
        "
        SELECT
            COUNT(*)
        FROM exam_attempts
        WHERE exam_id = ?
        "
    );

    $attemptStmt->execute([
        $examId
    ]);

    $attemptCount =
        (int)($attemptStmt->fetchColumn() ?: 0);

    $assignedStmt = $conn->prepare(
        "
        SELECT
            COUNT(*) AS question_count,
            COALESCE(SUM(q.marks), 0) AS actual_marks
        FROM exam_questions eq
        INNER JOIN questions q
            ON q.id = eq.question_id
        WHERE
            eq.exam_id = ?
        "
    );

    $assignedStmt->execute([
        $examId
    ]);

    $assignment = $assignedStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $assignedCount =
        (int)($assignment['question_count'] ?? 0);

    $actualMarks =
        round(
            (float)($assignment['actual_marks'] ?? 0),
            2
        );

} catch (Throwable $exception) {

    error_log(
        'ExamSphere teacher edit exam load failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);
    exit('Unable to load examination.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (
            !verify_csrf_token(
                $_POST['csrf_token'] ?? null
            )
        ) {
            throw new RuntimeException(
                'Security verification failed. Refresh the page and try again.'
            );
        }

        if ($attemptCount > 0) {
            throw new RuntimeException(
                'This examination cannot be edited because student attempts already exist.'
            );
        }

        $action =
            trim(
                (string)(
                    $_POST['action'] ?? 'save'
                )
            );

        if ($action !== 'save') {
            throw new RuntimeException(
                'Invalid examination action.'
            );
        }

        $title =
            trim(
                (string)(
                    $_POST['title'] ?? ''
                )
            );

        $description =
            trim(
                (string)(
                    $_POST['description'] ?? ''
                )
            );

        $subjectId = filter_var(
            $_POST['subject_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $examType =
            trim(
                (string)(
                    $_POST['exam_type'] ?? ''
                )
            );

        $requiredCount = filter_var(
            $_POST['required_question_count'] ?? '',
            FILTER_VALIDATE_INT
        );

        $marksPerQuestion = (float)(
            $_POST['marks_per_question'] ?? 0
        );

        $passingMarks = (float)(
            $_POST['passing_marks'] ?? 0
        );

        $duration = filter_var(
            $_POST['duration_minutes'] ?? '',
            FILTER_VALIDATE_INT
        );

        $negativeMarking =
            !empty(
                $_POST['negative_marking']
            )
                ? 1
                : 0;

        $examFee = (float)(
            $_POST['exam_fee'] ?? 0
        );

        $subscriptionRequired =
            !empty(
                $_POST['subscription_required']
            )
                ? 1
                : 0;

        $startsAtInput =
            trim(
                (string)(
                    $_POST['starts_at'] ?? ''
                )
            );

        $endsAtInput =
            trim(
                (string)(
                    $_POST['ends_at'] ?? ''
                )
            );

        if (
            $title === '' ||
            mb_strlen($title) > 180
        ) {
            throw new RuntimeException(
                'Exam title is required and must not exceed 180 characters.'
            );
        }

        if (
            $subjectId === false ||
            $subjectId <= 0
        ) {
            throw new RuntimeException(
                'Please select a valid subject.'
            );
        }

        if (
            !in_array(
                $examType,
                [
                    'Practice',
                    'Live'
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Invalid exam type.'
            );
        }

        if (
            $requiredCount === false ||
            $requiredCount < 1 ||
            $requiredCount > 65535
        ) {
            throw new RuntimeException(
                'Question count must be between 1 and 65535.'
            );
        }

        if (
            $marksPerQuestion <= 0 ||
            !is_finite($marksPerQuestion)
        ) {
            throw new RuntimeException(
                'Marks per question must be greater than zero.'
            );
        }

        $marksPerQuestion =
            round(
                $marksPerQuestion,
                2
            );

        $totalMarks =
            round(
                $requiredCount *
                $marksPerQuestion,
                2
            );

        if (
            !is_finite($totalMarks) ||
            $totalMarks <= 0
        ) {
            throw new RuntimeException(
                'Calculated total marks are invalid.'
            );
        }

        if (
            $totalMarks > EXAM_BUILDER_MAX_TOTAL_MARKS
        ) {
            throw new RuntimeException(
                'Total marks cannot exceed 300.'
            );
        }

        $passingMarks =
            round(
                $passingMarks,
                2
            );

        if (
            $passingMarks < 0 ||
            $passingMarks > $totalMarks
        ) {
            throw new RuntimeException(
                'Passing marks must be between 0 and the calculated total marks.'
            );
        }

        if (
            $duration === false ||
            $duration < 1 ||
            $duration > 65535
        ) {
            throw new RuntimeException(
                'Duration must be between 1 and 65535 minutes.'
            );
        }

        $examFee =
            round(
                max(
                    0,
                    $examFee
                ),
                2
            );

        if (
            $description !== '' &&
            mb_strlen($description) > 10000
        ) {
            throw new RuntimeException(
                'Exam description is too long.'
            );
        }

        $subjectCheck =
            $conn->prepare(
                "
                SELECT
                    id
                FROM subjects
                WHERE
                    id = ?
                    AND status = 'Active'
                LIMIT 1
                "
            );

        $subjectCheck->execute([
            (int)$subjectId
        ]);

        if (!$subjectCheck->fetchColumn()) {
            throw new RuntimeException(
                'Selected subject is not available.'
            );
        }

        $startsAt =
            exam_builder_datetime(
                $startsAtInput
            );

        $endsAt =
            exam_builder_datetime(
                $endsAtInput
            );

        if (
            $startsAtInput !== '' &&
            $startsAt === null
        ) {
            throw new RuntimeException(
                'Invalid start date and time.'
            );
        }

        if (
            $endsAtInput !== '' &&
            $endsAt === null
        ) {
            throw new RuntimeException(
                'Invalid end date and time.'
            );
        }

        if (
            $startsAt !== null &&
            $endsAt !== null &&
            strtotime($endsAt) <= strtotime($startsAt)
        ) {
            throw new RuntimeException(
                'End date and time must be after start date and time.'
            );
        }

        if (
            $assignedCount > $requiredCount
        ) {
            throw new RuntimeException(
                'The new required question count cannot be lower than the currently assigned ' .
                $assignedCount .
                ' question(s). Remove questions first.'
            );
        }

        if (
            $actualMarks >
            $totalMarks + 0.000001
        ) {
            throw new RuntimeException(
                'The new total marks cannot be lower than the marks of the currently assigned questions. Current assigned marks: ' .
                number_format(
                    $actualMarks,
                    2
                ) .
                '.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Preserve Draft/Cancelled semantics.
        | Published/scheduled exams are recalculated from their type + dates.
        |--------------------------------------------------------------------------
        */

        $currentStatus =
            (string)$exam['status'];

        $newStatus =
            $currentStatus;

        if (
            $currentStatus === 'Draft'
        ) {
            $newStatus = 'Draft';
        } elseif (
            $currentStatus === 'Cancelled'
        ) {
            $newStatus = 'Cancelled';
        } else {
            $newStatus =
                exam_builder_publish_status(
                    $examType,
                    $startsAt,
                    $endsAt
                );

            if (
                $examType === 'Practice' &&
                $newStatus === 'Running'
            ) {
                $newStatus = 'Active';
            }
        }

        $update =
            $conn->prepare(
                "
                UPDATE exams
                SET
                    subject_id = ?,
                    title = ?,
                    description = ?,
                    exam_type = ?,
                    duration_minutes = ?,
                    required_question_count = ?,
                    total_marks = ?,
                    passing_marks = ?,
                    negative_marking = ?,
                    exam_fee = ?,
                    subscription_required = ?,
                    starts_at = ?,
                    ends_at = ?,
                    status = ?
                WHERE
                    id = ?
                    AND teacher_id = ?
                "
            );

        $update->execute([
            (int)$subjectId,
            $title,
            $description !== ''
                ? $description
                : null,
            $examType,
            (int)$duration,
            (int)$requiredCount,
            $totalMarks,
            $passingMarks,
            $negativeMarking,
            $examFee,
            $subscriptionRequired,
            $startsAt,
            $endsAt,
            $newStatus,
            (int)$examId,
            $teacherId
        ]);

        $success =
            'Examination updated successfully.';

        $exam =
            teacher_edit_exam_load(
                $conn,
                $examId,
                $teacherId
            );

        $requiredCount =
            (int)$exam['required_question_count'];

        $marksPerQuestion =
            $requiredCount > 0
                ? round(
                    (float)$exam['total_marks'] /
                    $requiredCount,
                    2
                )
                : 0;

    } catch (Throwable $exception) {

        error_log(
            'ExamSphere teacher edit exam update failed: ' .
            $exception->getMessage()
        );

        $error =
            $exception->getMessage();
    }
}

$subjectRows = [];

try {

    $subjectRows =
        $conn
            ->query(
                "
                SELECT
                    id,
                    name,
                    code
                FROM subjects
                WHERE status = 'Active'
                ORDER BY
                    name ASC,
                    id ASC
                "
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            );

} catch (Throwable $exception) {

    error_log(
        'ExamSphere teacher edit exam subjects failed: ' .
        $exception->getMessage()
    );

    $error =
        $error !== ''
            ? $error
            : 'Unable to load subjects.';
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
    Edit Exam | ExamSphere
</title>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet"
>

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
    --earth-dark:#422d26;
    --olive:#556b2f;
    --text:#382e29;
    --muted:#847970;
    --line:#e7dfd5;
    --soft:#fbfaf6;
}

body.portal-body{
    background:
        radial-gradient(
            circle at 7% 0%,
            rgba(85,107,47,.08),
            transparent 27%
        ),
        linear-gradient(
            135deg,
            #faf8f2,
            #efebe4
        );
    font-family:'Poppins',sans-serif;
}

.edit-page{
    width:min(
        1200px,
        calc(100vw - 24px)
    );
    margin:0 auto;
    padding:20px 0 60px;
}

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:20px;
    margin-bottom:18px;
}

.kicker{
    color:var(--olive);
    font-size:.62rem;
    font-weight:900;
    letter-spacing:.14em;
}

.page-header h1{
    margin:7px 0 4px;
    color:var(--earth);
    font-size:1.9rem;
    font-weight:900;
    letter-spacing:-.035em;
}

.page-header p{
    margin:0;
    color:var(--muted);
    font-size:.67rem;
}

.card{
    overflow:hidden;
    border:1px solid rgba(93,64,55,.08);
    border-radius:20px;
    background:rgba(255,255,255,.9);
    box-shadow:
        0 18px 44px rgba(62,45,37,.07);
}

.card + .card{
    margin-top:18px;
}

.card-head{
    padding:18px 21px;
    border-bottom:1px solid var(--line);
}

.card-head h2{
    margin:0;
    color:var(--earth);
    font-size:.96rem;
    font-weight:900;
}

.card-head p{
    margin:4px 0 0;
    color:var(--muted);
    font-size:.59rem;
}

.body{
    padding:21px;
}

.grid{
    display:grid;
    grid-template-columns:
        repeat(2,minmax(0,1fr));
    gap:15px;
}

.full{
    grid-column:1/-1;
}

label{
    display:block;
    margin-bottom:6px;
    color:var(--earth);
    font-size:.59rem;
    font-weight:850;
}

.form-control,
.form-select{
    min-height:46px;
    border:1px solid #dcd3ca;
    border-radius:11px;
    font-size:.65rem;
}

.form-control:focus,
.form-select:focus{
    border-color:var(--olive);
    box-shadow:
        0 0 0 .2rem rgba(85,107,47,.10);
}

textarea.form-control{
    min-height:105px;
    resize:vertical;
}

.switch-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    min-height:46px;
    padding:11px 13px;
    border:1px solid var(--line);
    border-radius:11px;
    background:var(--soft);
}

.switch-row span{
    color:var(--earth);
    font-size:.62rem;
    font-weight:800;
}

.info{
    padding:15px;
    border-radius:14px;
    background:
        linear-gradient(
            135deg,
            #f4efe7,
            #edf2e6
        );
    border:1px solid var(--line);
}

.info strong{
    display:block;
    color:var(--earth);
    font-size:1.25rem;
    font-weight:900;
}

.info span{
    color:var(--muted);
    font-size:.54rem;
}

.warning{
    margin-bottom:18px;
    padding:13px 15px;
    border-radius:12px;
    background:#fff4df;
    color:#8b6d39;
    font-size:.59rem;
    line-height:1.6;
}

.success{
    margin-bottom:18px;
    padding:13px 15px;
    border-radius:12px;
    background:#edf5e8;
    color:#4f7040;
    font-size:.59rem;
}

.error{
    margin-bottom:18px;
    padding:13px 15px;
    border-radius:12px;
    background:#faece9;
    color:#945149;
    font-size:.59rem;
}

.actions{
    display:flex;
    justify-content:flex-end;
    gap:8px;
    padding-top:18px;
    margin-top:18px;
    border-top:1px solid var(--line);
}

.btn-earth{
    border:0;
    border-radius:10px;
    background:var(--earth);
    color:#fff;
    min-height:43px;
    padding:0 16px;
    font-size:.62rem;
    font-weight:850;
}

.btn-earth:hover{
    background:var(--earth-dark);
    color:#fff;
}

.btn-light-soft{
    border:1px solid var(--line);
    border-radius:10px;
    min-height:43px;
    padding:0 15px;
    font-size:.62rem;
    font-weight:800;
    color:var(--earth);
    background:#fff;
}

@media(max-width:700px){

    .edit-page{
        width:calc(100vw - 14px);
    }

    .page-header{
        align-items:flex-start;
        flex-direction:column;
    }

    .page-header h1{
        font-size:1.55rem;
    }

    .grid{
        grid-template-columns:1fr;
    }

    .full{
        grid-column:auto;
    }

    .actions{
        flex-direction:column;
    }

    .actions a,
    .actions button{
        width:100%;
    }

}

</style>

</head>

<body class="portal-body">

<div class="portal-layout">

<?php include 'includes/sidebar.php'; ?>

<main class="portal-main">

<div class="edit-page">

<header class="page-header">

<div>

<div class="kicker">
    <i class="fa-solid fa-pen-to-square me-1"></i>
    EXAM MANAGEMENT
</div>

<h1>
    Edit Exam
</h1>

<p>
    Update the examination configuration without changing its existing question set automatically.
</p>

</div>

<a
    href="exams.php"
    class="btn btn-light-soft"
>

<i class="fa-solid fa-arrow-left me-1"></i>

My Exams

</a>

</header>


<?php if ($success !== ''): ?>

<div class="success">

<i class="fa-solid fa-circle-check me-1"></i>

<?= teacher_edit_exam_e(
    $success
) ?>

</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div class="error">

<i class="fa-solid fa-circle-exclamation me-1"></i>

<?= teacher_edit_exam_e(
    $error
) ?>

</div>

<?php endif; ?>


<?php if ($attemptCount > 0): ?>

<div class="warning">

<i class="fa-solid fa-lock me-1"></i>

This exam is locked for editing because
<strong><?= (int)$attemptCount ?></strong>
student attempt(s) already exist.

You can still view the examination and its results.

</div>

<?php endif; ?>


<section class="card">

<div class="card-head">

<h2>
    <?= teacher_edit_exam_e(
        $exam['title']
    ) ?>
</h2>

<p>
    Subject:
    <?= teacher_edit_exam_e(
        $exam['subject_name']
            ?? '—'
    ) ?>

    &nbsp; · &nbsp;

    Current assigned:
    <?= (int)$assignedCount ?>

    &nbsp; · &nbsp;

    Actual marks:
    <?= number_format(
        $actualMarks,
        2
    ) ?>
</p>

</div>


<div class="body">

<div class="row g-3 mb-4">

<div class="col-md-4">

<div class="info">

<strong>
    <?= (int)$assignedCount ?>
</strong>

<span>
    Currently assigned questions
</span>

</div>

</div>


<div class="col-md-4">

<div class="info">

<strong>
    <?= number_format(
        $actualMarks,
        2
    ) ?>
</strong>

<span>
    Marks from assigned questions
</span>

</div>

</div>


<div class="col-md-4">

<div class="info">

<strong>
    <?= (int)$exam['required_question_count'] ?>
</strong>

<span>
    Configured required questions
</span>

</div>

</div>

</div>


<form
    method="post"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="exam_id"
    value="<?= (int)$examId ?>"
>


<div class="grid">


<div>

<label for="title">
    Exam Title
</label>

<input
    id="title"
    class="form-control"
    type="text"
    name="title"
    maxlength="180"
    required
    value="<?= teacher_edit_exam_e(
        $exam['title']
    ) ?>"
    <?= $attemptCount > 0 ? 'disabled' : '' ?>
>

</div>


<div>

<label for="subject_id">
    Subject
</label>

<select
    id="subject_id"
    class="form-select"
    name="subject_id"
    required
    <?= $attemptCount > 0 ? 'disabled' : '' ?>
>

<?php foreach (
    $subjectRows as $subject
): ?>

<option
    value="<?= (int)$subject['id'] ?>"
    <?= (int)$exam['subject_id'] ===
        (int)$subject['id']
        ? 'selected'
        : ''
    ?>
>

<?= teacher_edit_exam_e(
    $subject['name']
) ?>

<?php if (
    !empty(
        $subject['code']
    )
): ?>

(
<?= teacher_edit_exam_e(
    $subject['code']
) ?>
)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="full">

<label for="description">
    Description
</label>

<textarea
    id="description"
    class="form-control"
    name="description"
    maxlength="10000"
    <?= $attemptCount > 0 ? 'disabled' : '' ?>
><?= teacher_edit_exam_e(
    $exam['description']
) ?></textarea>

</div>


<div>

<label for="exam_type">
    Exam Type
</label>

<select
    id="exam_type"
    class="form-select"
    name="exam_type"
    required
    <?= $attemptCount > 0 ? 'disabled' : '' ?>
>

<option
    value="Practice"
    <?= $exam['exam_type'] === 'Practice'
        ? 'selected'
        : ''
    ?>
>
    Practice
</option>

<option
    value="Live"
    <?= $exam['exam_type'] === 'Live'
        ? 'selected'
        : ''
    ?>
>
    Live
</option>

</select>

</div>


<div>

<label for="duration_minutes">
    Duration (minutes)
</label>

<input
    id="duration_minutes"
    class="form-control"
    type="number"
    name="duration_minutes"
    min="1"
    max="65535"
    required
    value="<?= (int)$exam['duration_minutes'] ?>"
    <?= $attemptCount > 0 ? 'disabled' : '' ?>
>

</div>


<div>

<label for="required_question_count">
    Required Questions
</label>

<input
    id="required_question_count"
    class="form-control"
    type="number"
    name="required_question_count"
    min="1"
    max="65535"
    required
    value="<?= (int)$exam['required_question_count'] ?>"
    <?= $attemptCount > 0 ? 'disabled' : '' ?>
>

</div>


<div>

<label for="marks_per_question">
    Marks / Question
</label>

<input
    id="marks_per_question"
    class="form-control"
    type="number"
    name="marks_per_question"
    min="0.01"
    max="300"
    step="0.01"
    required
    value="<?= number_format(
        (float)$exam['total_marks'] /
        max(
            1,
            (int)$exam['required_question_count']
        ),
        2,
        '.',
        ''
    ) ?>"
    <?= $attemptCount > 0 ? 'disabled' : '' ?>
>

</div>


<div>

<label for="passing_marks">
    Passing Marks
</label>

<input
    id="passing_marks"
    class="form-control"
    type="number"
    name="passing_marks"
    min="0"
    step="0.01"
    required
    value="<?= number_format(
        (float)$exam['passing_marks'],
        2,
        '.',
        ''
    ) ?>"
    <?= $attemptCount > 0 ? 'disabled' : '' ?>
>

</div>


<div>

<label>
    Negative Marking
</label>

<div class="switch-row">

<span>
    Enable negative marking
</span>

<div class="form-check form-switch m-0">

<input
    class="form-check-input"
    type="checkbox"
    name="negative_marking"
    value="1"
    <?= (int)$exam['negative_marking'] === 1
        ? 'checked'
        : ''
    ?>
    <?= $attemptCount > 0 ? 'disabled' : '' ?>
>

</div>

</div>

</div>


<div>

<label for="exam_fee">
    Exam Fee
</label>

<input
    id="exam_fee"
    class="form-control"
    type="number"
    name="exam_fee"
    min="0"
    step="0.01"
    value="<?= number_format(
        (float)$exam['exam_fee'],
        2,
        '.',
        ''
    ) ?>"
    <?= $attemptCount > 0 ? 'disabled' : '' ?>
>

</div>


<div>

<label>
    Subscription Access
</label>

<div class="switch-row">

<span>
    Require active subscription
</span>

<div class="form-check form-switch m-0">

<input
    class="form-check-input"
    type="checkbox"
    name="subscription_required"
    value="1"
    <?= (int)$exam['subscription_required'] === 1
        ? 'checked'
        : ''
    ?>
    <?= $attemptCount > 0 ? 'disabled' : '' ?>
>

</div>

</div>

</div>


<div>

<label for="starts_at">
    Start Date & Time
</label>

<input
    id="starts_at"
    class="form-control"
    type="datetime-local"
    name="starts_at"
    value="<?= teacher_edit_exam_e(
        teacher_edit_exam_datetime_value(
            $exam['starts_at']
        )
    ) ?>"
    <?= $attemptCount > 0 ? 'disabled' : '' ?>
>

</div>


<div>

<label for="ends_at">
    End Date & Time
</label>

<input
    id="ends_at"
    class="form-control"
    type="datetime-local"
    name="ends_at"
    value="<?= teacher_edit_exam_e(
        teacher_edit_exam_datetime_value(
            $exam['ends_at']
        )
    ) ?>"
    <?= $attemptCount > 0 ? 'disabled' : '' ?>
>

</div>


</div>


<div class="info mt-4">

<strong id="totalMarks">
    <?= number_format(
        (float)$exam['total_marks'],
        2
    ) ?>
    / 300
</strong>

<span>
    Automatic Total Marks = Required Questions × Marks / Question
</span>

</div>


<div class="actions">

<a
    href="exam_view.php?id=<?= (int)$examId ?>"
    class="btn btn-light-soft"
>

View Exam

</a>

<a
    href="exam_questions.php?exam_id=<?= (int)$examId ?>"
    class="btn btn-light-soft"
>

Manage Questions

</a>

<?php if ($attemptCount === 0): ?>

<button
    type="submit"
    name="action"
    value="save"
    class="btn btn-earth"
>

<i class="fa-solid fa-floppy-disk me-1"></i>

Save Changes

</button>

<?php endif; ?>

</div>

</form>

</div>

</section>

</div>

</main>

<script>

(() => {

    const count =
        document.getElementById(
            'required_question_count'
        );

    const marks =
        document.getElementById(
            'marks_per_question'
        );

    const total =
        document.getElementById(
            'totalMarks'
        );

    if (
        !count ||
        !marks ||
        !total
    ) {
        return;
    }

    const calculate = () => {

        const questionCount =
            Math.max(
                0,
                Number(
                    count.value
                ) || 0
            );

        const marksPerQuestion =
            Math.max(
                0,
                Number(
                    marks.value
                ) || 0
            );

        const value =
            questionCount *
            marksPerQuestion;

        total.textContent =
            value.toFixed(2) +
            ' / 300';
    };

    count.addEventListener(
        'input',
        calculate
    );

    marks.addEventListener(
        'input',
        calculate
    );

})();

</script>

</body>

</html>
