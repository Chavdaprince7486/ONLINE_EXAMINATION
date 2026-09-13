<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/exam_validation.php';
require_once '../config/exam_builder.php';

if (
    !is_array($_SESSION ?? null) ||
    (int)($_SESSION['user_id'] ?? 0) <= 0 ||
    ($_SESSION['user_role'] ?? '') !== 'teacher'
) {
    header('Location: ../auth/login.php');
    exit;
}

$teacherId = (int) $_SESSION['user_id'];

$error = '';
$message = '';
$successExamId = 0;

$title = trim(
    (string) ($_POST['title'] ?? '')
);

$description = trim(
    (string) ($_POST['description'] ?? '')
);

$subjectId = filter_var(
    $_POST['subject_id'] ?? '',
    FILTER_VALIDATE_INT
);

$examType = trim(
    (string) ($_POST['exam_type'] ?? 'Practice')
);

$questionCount = filter_var(
    $_POST['question_count'] ?? '',
    FILTER_VALIDATE_INT
);

$marksPerQuestion = filter_var(
    $_POST['marks_per_question'] ?? '',
    FILTER_VALIDATE_FLOAT
);

$passingMarks = filter_var(
    $_POST['passing_marks'] ?? '',
    FILTER_VALIDATE_FLOAT
);

$negativeEnabled =
    isset($_POST['negative_marking']);

$negativeMarks = filter_var(
    $_POST['negative_marks'] ?? '0',
    FILTER_VALIDATE_FLOAT
);

$duration = filter_var(
    $_POST['duration_minutes'] ?? '60',
    FILTER_VALIDATE_INT
);

$examFee = filter_var(
    $_POST['exam_fee'] ?? '0',
    FILTER_VALIDATE_FLOAT
);

$subscriptionRequired =
    isset($_POST['subscription_required']);

$startsAtInput = trim(
    (string) ($_POST['starts_at'] ?? '')
);

$endsAtInput = trim(
    (string) ($_POST['ends_at'] ?? '')
);

$action = trim(
    (string) ($_POST['action'] ?? '')
);

$source = trim(
    (string) ($_POST['question_source'] ?? 'manual')
);

$selectedIds =
    $_POST['question_ids'] ?? [];

if (!is_array($selectedIds)) {
    $selectedIds = [];
}

$selectedIds =
    array_values(
        array_unique(
            array_filter(
                array_map(
                    'intval',
                    $selectedIds
                ),
                static function (
                    int $id
                ): bool {
                    return $id > 0;
                }
            )
        )
    );

$questionCount =
    (
        $questionCount !== false &&
        $questionCount !== null
    )
        ? $questionCount
        : 0;

$marksPerQuestion =
    (
        $marksPerQuestion !== false &&
        $marksPerQuestion !== null
    )
        ? $marksPerQuestion
        : 0.0;

$passingMarks =
    (
        $passingMarks !== false &&
        $passingMarks !== null
    )
        ? $passingMarks
        : 0.0;

$negativeMarks =
    (
        $negativeMarks !== false &&
        $negativeMarks !== null
    )
        ? $negativeMarks
        : 0.0;

$duration =
    (
        $duration !== false &&
        $duration !== null
    )
        ? $duration
        : 60;

$examFee =
    (
        $examFee !== false &&
        $examFee !== null
    )
        ? $examFee
        : 0.0;


/*
|--------------------------------------------------------------------------
| LOAD SUBJECTS + QUESTION BANK
|--------------------------------------------------------------------------
*/

$subjects = [];
$questions = [];

try {

    $subjects =
        $conn->query(
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
        )->fetchAll(
            PDO::FETCH_ASSOC
        );


    $questionStmt =
        $conn->prepare(
            "
            SELECT
                q.id,
                q.question_text,
                q.marks,
                q.negative_marks,
                q.difficulty,
                s.name AS subject_name
            FROM questions q
            INNER JOIN subjects s
                ON s.id = q.subject_id
                AND s.status = 'Active'
            WHERE
                q.created_by_teacher_id = ?
                AND q.status = 'Active'
            ORDER BY
                q.id DESC
            "
        );


    $questionStmt->execute([
        $teacherId
    ]);


    $questions =
        $questionStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Teacher create exam load failed: ' .
        $exception->getMessage()
    );

    $error =
        'Unable to load exam configuration data.';
}


/*
|--------------------------------------------------------------------------
| POST PROCESSING
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $error === ''
) {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? null
        )
    ) {

        $error =
            'Invalid security token. Refresh the page and try again.';
    }


    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    elseif (
        $title === '' ||
        mb_strlen($title) > 180
    ) {

        $error =
            'Exam title is required and must not exceed 180 characters.';
    }


    elseif (
        $subjectId === false ||
        $subjectId < 1
    ) {

        $error =
            'Please select a subject.';
    }


    elseif (
        !in_array(
            $examType,
            [
                'Practice',
                'Live'
            ],
            true
        )
    ) {

        $error =
            'Invalid exam type.';
    }


    elseif (
        $questionCount < 1 ||
        $questionCount > 65535
    ) {

        $error =
            'Question count must be between 1 and 65535.';
    }


    elseif (
        $marksPerQuestion <= 0 ||
        !is_finite(
            (float) $marksPerQuestion
        )
    ) {

        $error =
            'Marks per question must be a valid value greater than zero.';
    }


    elseif (
        !is_finite(
            (float) $questionCount *
            (float) $marksPerQuestion
        )
    ) {

        $error =
            'The exam marks configuration is invalid.';
    }


    /*
    |--------------------------------------------------------------------------
    | MAXIMUM 300 TOTAL MARKS
    |--------------------------------------------------------------------------
    |
    | This is the only global marks ceiling.
    |
    | There is NO fixed question-count rule.
    |
    |--------------------------------------------------------------------------
    */

    elseif (
        (
            (float) $questionCount *
            (float) $marksPerQuestion
        )
        >
        EXAM_BUILDER_MAX_TOTAL_MARKS
    ) {

        $error =
            'Total marks cannot exceed 300. Reduce the question count or marks per question.';
    }


    /*
    |--------------------------------------------------------------------------
    | PASSING MARKS
    |--------------------------------------------------------------------------
    */

    elseif (
        $passingMarks < 0 ||
        $passingMarks >
        (
            (float) $questionCount *
            (float) $marksPerQuestion
        )
    ) {

        $error =
            'Passing marks must be between 0 and the calculated total marks.';
    }


    /*
    |--------------------------------------------------------------------------
    | DURATION
    |--------------------------------------------------------------------------
    */

    elseif (
        $duration < 1 ||
        $duration > 65535
    ) {

        $error =
            'Duration must be between 1 and 65535 minutes.';
    }


    /*
    |--------------------------------------------------------------------------
    | NEGATIVE MARKING
    |--------------------------------------------------------------------------
    */

    elseif (
        $negativeMarks < 0 ||
        $negativeMarks >
        $marksPerQuestion
    ) {

        $error =
            'Negative marks must be between 0 and marks per question.';
    }


    /*
    |--------------------------------------------------------------------------
    | DISABLE NEGATIVE MARKS WHEN TOGGLE IS OFF
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        !$negativeEnabled
    ) {

        $negativeMarks = 0.0;
    }


    /*
    |--------------------------------------------------------------------------
    | DATE / TIME
    |--------------------------------------------------------------------------
    */

    $startsAt =
        exam_builder_datetime(
            $startsAtInput
        );

    $endsAt =
        exam_builder_datetime(
            $endsAtInput
        );


    if (
        $error === '' &&
        (
            $startsAtInput !== '' &&
            $startsAt === null
        )
    ) {

        $error =
            'Invalid start date and time.';
    }


    if (
        $error === '' &&
        (
            $endsAtInput !== '' &&
            $endsAt === null
        )
    ) {

        $error =
            'Invalid end date and time.';
    }


    if (
        $error === '' &&
        $startsAt !== null &&
        $endsAt !== null &&
        strtotime($endsAt) <=
        strtotime($startsAt)
    ) {

        $error =
            'End date and time must be after start date and time.';
    }


    /*
    |--------------------------------------------------------------------------
    | LIVE EXAM START REQUIREMENT
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        $examType === 'Live' &&
        $action === 'publish' &&
        $startsAt === null
    ) {

        $error =
            'A Live exam requires a start date and time before publishing.';
    }


    /*
    |--------------------------------------------------------------------------
    | QUESTION SOURCE
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        !in_array(
            $source,
            [
                'manual',
                'csv'
            ],
            true
        )
    ) {

        $error =
            'Invalid question source.';
    }


    $creatorTeacherId =
        $teacherId;

    $questionRows = [];
    $existingRows = [];


    /*
    |--------------------------------------------------------------------------
    | PUBLISH QUESTION VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        $action === 'publish'
    ) {

        /*
        |--------------------------------------------------------------------------
        | CSV
        |--------------------------------------------------------------------------
        */

        if (
            $source === 'csv'
        ) {

            $upload =
                $_FILES[
                    'questions_csv'
                ] ?? null;


            if (
                !is_array($upload) ||
                (
                    $upload['error'] ??
                    UPLOAD_ERR_NO_FILE
                ) !== UPLOAD_ERR_OK
            ) {

                $error =
                    'Please select a valid CSV file.';
            }

            else {

                try {

                    $questionRows =
                        exam_builder_parse_csv_questions(

                            (string)
                                $upload['tmp_name'],

                            (int)
                                $subjectId,

                            $creatorTeacherId,

                            (float)
                                $marksPerQuestion,

                            $negativeEnabled
                                ? (float)
                                    $negativeMarks
                                : 0.0,

                            (string)
                                $questionCount
                        );

                } catch (
                    Throwable $exception
                ) {

                    $error =
                        $exception->getMessage();
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | MANUAL QUESTION BANK
        |--------------------------------------------------------------------------
        */

        else {

            if (
                count($selectedIds) !==
                $questionCount
            ) {

                $error =
                    'You must select exactly ' .
                    $questionCount .
                    ' questions before publishing this exam.';
            }

            else {

                try {

                    $existingRows =
                        exam_builder_load_existing_question_ids(

                            $conn,

                            $selectedIds,

                            (int)
                                $subjectId,

                            $teacherId,

                            (float)
                                $marksPerQuestion,

                            $negativeEnabled
                                ? (float)
                                    $negativeMarks
                                : 0.0
                        );

                } catch (
                    Throwable $exception
                ) {

                    $error =
                        $exception->getMessage();
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DRAFT
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        $action === 'draft'
    ) {

        $draftCount =
            $source === 'manual'
                ? count(
                    $selectedIds
                )
                : 0;


        if (
            $draftCount >
            $questionCount
        ) {

            $error =
                'Draft cannot contain more questions than the configured question count.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE EXAM
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        in_array(
            $action,
            [
                'draft',
                'publish'
            ],
            true
        )
    ) {

        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        $status =
            'Draft';


        if (
            $action === 'publish'
        ) {

            $status =
                exam_builder_publish_status(
                    $examType,
                    $startsAt,
                    $endsAt
                );


            /*
            |--------------------------------------------------------------------------
            | Practice exams use Active when immediately available.
            |--------------------------------------------------------------------------
            */

            if (
                $examType === 'Practice' &&
                $status === 'Running'
            ) {

                $status =
                    'Active';
            }
        }


        try {

            /*
            |--------------------------------------------------------------------------
            | EXAM DATA
            |--------------------------------------------------------------------------
            */

            $exam = [

                'subject_id' =>
                    (int) $subjectId,

                'teacher_id' =>
                    $teacherId,

                'title' =>
                    $title,

                'description' =>
                    $description,

                'exam_type' =>
                    $examType,

                'duration_minutes' =>
                    (int) $duration,

                /*
                |--------------------------------------------------------------------------
                | DYNAMIC QUESTION COUNT
                |--------------------------------------------------------------------------
                */

                'required_question_count' =>
                    (int) $questionCount,

                /*
                |--------------------------------------------------------------------------
                | DYNAMIC MARK CONFIGURATION
                |--------------------------------------------------------------------------
                */

                'marks_per_question' =>
                    (float) $marksPerQuestion,

                'total_marks' =>
                    round(
                        (
                            (float)
                                $questionCount *
                            (float)
                                $marksPerQuestion
                        ),
                        2
                    ),

                'passing_marks' =>
                    round(
                        (float) $passingMarks,
                        2
                    ),

                'negative_marking' =>
                    $negativeEnabled
                        ? 1
                        : 0,

                'exam_fee' =>
                    max(
                        0.0,
                        (float) $examFee
                    ),

                'subscription_required' =>
                    $subscriptionRequired
                        ? 1
                        : 0,

                'starts_at' =>
                    $startsAt,

                'ends_at' =>
                    $endsAt,

                'status' =>
                    $status
            ];


            /*
            |--------------------------------------------------------------------------
            | FINAL DYNAMIC QUESTION COUNT CHECK
            |--------------------------------------------------------------------------
            */

            if (
                $action === 'publish' &&
                count($questionRows) !==
                    $questionCount &&
                count($existingRows) !==
                    $questionCount
            ) {

                throw new RuntimeException(
                    'The examination cannot be published until the exact configured number of questions is ready.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CREATE EXAM + EXAM QUESTIONS
            |--------------------------------------------------------------------------
            */

            $successExamId =
                exam_builder_create_exam(
                    $conn,
                    $exam,
                    $selectedIds,
                    $questionRows,
                    $creatorTeacherId
                );


            /*
            |--------------------------------------------------------------------------
            | SUCCESS MESSAGE
            |--------------------------------------------------------------------------
            */

            $message =
                $action === 'publish'

                    ? 'Exam published successfully. It is now available according to its status and schedule.'

                    : 'Draft saved successfully. Students will not see it until it is published with the exact question count.';


            /*
            |--------------------------------------------------------------------------
            | RESET FORM
            |--------------------------------------------------------------------------
            */

            $title = '';
            $description = '';
            $subjectId = null;
            $questionCount = 0;
            $marksPerQuestion = 0.0;
            $passingMarks = 0.0;
            $negativeEnabled = false;
            $negativeMarks = 0.0;
            $duration = 60;
            $examFee = 0.0;
            $subscriptionRequired = false;
            $startsAtInput = '';
            $endsAtInput = '';
            $selectedIds = [];

        } catch (
            Throwable $exception
        ) {

            error_log(
                'Teacher create exam failed: ' .
                $exception->getMessage()
            );


            $error =
                'Exam could not be created: ' .
                $exception->getMessage();
        }

    }

    elseif (
        $error === '' &&
        $action !== ''
    ) {

        $error =
            'Use Save Draft or Publish Exam.';
    }
}


$csrf =
    htmlspecialchars(
        csrf_token(),
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );

?>
<!doctype html>
<html lang="en">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>

<title>
    Create Exam | ExamSphere
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
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
>

<link
    rel="stylesheet"
    href="../assets/css/portal.css"
>

<style>

body {
    background:#f5f3ec;
}

.builder-wrap {
    max-width:1280px;
    margin:auto;
}

.builder-card {
    border:1px solid #e8dfd3;
    border-radius:20px;
    background:#fff;
    box-shadow:
        0 16px 45px
        rgba(62,39,35,.07);
    padding:24px;
}

.builder-title {
    color:#5d4037;
}

.rule-card {
    background:#f7f5ef;
    border:1px solid #e9e0d5;
    border-radius:16px;
    padding:15px;
}

.rule-value {
    font-size:1.05rem;
    font-weight:800;
    color:#556b2f;
}

.form-label {
    font-size:.72rem;
    font-weight:700;
    color:#5e524a;
}

.form-control,
.form-select {
    border-radius:11px;
    font-size:.74rem;
    min-height:44px;
    border-color:#ded5ca;
}

.form-control:focus,
.form-select:focus {
    border-color:#8b6754;
    box-shadow:
        0 0 0 3px
        rgba(93,64,55,.07);
}

.question-source {
    display:grid;
    grid-template-columns:
        repeat(2,1fr);
    gap:10px;
}

.source-option {
    padding:14px;
    border:1px solid #e6ded2;
    border-radius:14px;
    cursor:pointer;
    background:#fcfaf6;
}

.source-option.active {
    border-color:#8a6753;
    background:#f4eee8;
}

.source-option input {
    display:none;
}

.source-option strong {
    display:block;
    font-size:.73rem;
}

.source-option small {
    display:block;
    color:#8a8078;
    font-size:.59rem;
    line-height:1.45;
    margin-top:3px;
}

.question-list {
    max-height:430px;
    overflow:auto;
    border:1px solid #e6ded2;
    border-radius:14px;
}

.question-row {
    display:flex;
    gap:10px;
    padding:11px 12px;
    border-bottom:1px solid #eee7dd;
}

.question-row:last-child {
    border-bottom:0;
}

.question-row label {
    font-size:.67rem;
    line-height:1.45;
}

.question-search {
    margin-bottom:10px;
}

.calc-box {
    padding:18px;
    border-radius:16px;
    background:
        linear-gradient(
            135deg,
            #f3eee6,
            #edf2e5
        );
    border:1px solid #e2dbcf;
}

.calc-box .label {
    font-size:.6rem;
    color:#81766f;
}

.calc-box .value {
    font-size:1.25rem;
    font-weight:800;
    color:#5d4037;
}

.action-bar {
    display:flex;
    gap:10px;
    justify-content:flex-end;
    padding-top:18px;
    border-top:1px solid #ece5db;
}

.status-note {
    font-size:.61rem;
    color:#81766f;
    line-height:1.5;
}

.csv-help {
    font-size:.62rem;
    color:#81766f;
    line-height:1.55;
}

.csv-code {
    padding:12px;
    border-radius:11px;
    background:#211c19;
    color:#eee;
    font-size:.58rem;
    overflow:auto;
}

.hidden {
    display:none !important;
}



/* =========================================================
   ExamSphere Typography Upgrade
   Same layout / same structure — readability only
   ========================================================= */
.builder-wrap{max-width:1280px}
.builder-card{padding:28px;border-radius:20px}
.builder-title{font-size:1.35rem!important;font-weight:800!important;line-height:1.35}
.portal-topbar h1{font-size:2rem!important;font-weight:800!important;line-height:1.2}
.portal-subtitle{font-size:.96rem!important;font-weight:500!important;line-height:1.6}
.form-label{font-size:.92rem!important;font-weight:800!important;line-height:1.4;color:#4e4038;margin-bottom:7px}
.form-control,.form-select{font-size:.95rem!important;font-weight:600!important;min-height:50px!important;padding:.7rem .9rem!important}
textarea.form-control{min-height:100px!important;line-height:1.55}
.form-check-label{font-size:.95rem!important;font-weight:800!important;line-height:1.4}
.rule-card strong{font-size:1rem!important;font-weight:800!important}
.rule-value{font-size:1.15rem!important;font-weight:800!important}
.source-option{padding:18px!important}
.source-option strong{font-size:.95rem!important;font-weight:800!important;line-height:1.4}
.source-option small{font-size:.82rem!important;font-weight:500!important;line-height:1.55!important}
.question-search{margin-bottom:12px!important}
.question-search::placeholder,.form-control::placeholder{font-size:.9rem;font-weight:500}
.question-list{max-height:470px!important}
.question-row{gap:13px!important;padding:15px 16px!important}
.question-row label{font-size:.9rem!important;font-weight:600!important;line-height:1.55!important}
.question-row strong{font-size:.96rem!important;font-weight:800!important;line-height:1.5!important}
.question-row small{font-size:.82rem!important;font-weight:600!important;line-height:1.55!important}
.calc-box{padding:20px!important}
.calc-box .label{font-size:.76rem!important;font-weight:700!important}
.calc-box .value{font-size:1.55rem!important;font-weight:800!important}
.status-note{font-size:.82rem!important;font-weight:600!important;line-height:1.6!important}
.csv-help{font-size:.82rem!important;font-weight:600!important;line-height:1.65!important}
.csv-code{font-size:.78rem!important;font-weight:600!important;line-height:1.55!important}
.alert{font-size:.9rem!important;font-weight:700!important;line-height:1.55!important}
.btn{font-size:.9rem!important;font-weight:800!important;padding:.68rem 1rem!important}
.action-bar{padding-top:22px!important}
@media(max-width:900px){
  .portal-topbar h1{font-size:1.75rem!important}
  .builder-card{padding:22px!important}
}
@media(max-width:600px){
  .portal-topbar h1{font-size:1.55rem!important}
  .portal-subtitle{font-size:.88rem!important}
  .builder-title{font-size:1.18rem!important}
  .form-label{font-size:.88rem!important}
  .form-control,.form-select{font-size:.9rem!important;min-height:48px!important}
  .question-row label{font-size:.86rem!important}
}

</style>

</head>

<body class="portal-body">

<div class="portal-layout">

<?php include 'includes/sidebar.php'; ?>

<main class="portal-main">

<header class="portal-topbar">

<div>

<h1>
    Create Exam
</h1>

<p class="portal-subtitle">
    Build a fully dynamic examination.
    Total marks are calculated automatically
    from question count × marks per question,
    with a maximum of 300 marks.
</p>

</div>

<a
    class="btn btn-outline-dark"
    href="exams.php"
>

<i
    class="fa-solid fa-arrow-left me-1"
></i>

My Exams

</a>

</header>


<div class="builder-wrap">


<?php if ($error !== ''): ?>

<div class="alert alert-danger">

<i
    class="fa-solid fa-circle-exclamation me-2"
></i>

<?= exam_builder_e($error) ?>

</div>

<?php endif; ?>


<?php if ($message !== ''): ?>

<div class="alert alert-success">

<i
    class="fa-solid fa-circle-check me-2"
></i>

<?= exam_builder_e($message) ?>

<?php if ($successExamId): ?>

<a
    class="alert-link"
    href="exams.php"
>
    View exam
</a>

<?php endif; ?>

</div>

<?php endif; ?>


<section class="builder-card mb-3">


<div class="row g-3">

<div class="col-12">

<h2 class="h5 builder-title mb-1">

1. Examination details

</h2>

<p class="small text-muted mb-0">

Choose the exam structure first.
The system calculates total marks automatically.

</p>

</div>


<div class="col-lg-8">

<label class="form-label">

Exam title *

</label>

<input
    class="form-control"
    name="_display_title"
    form="examForm"
    value="<?= exam_builder_e($title) ?>"
    data-mirror="title"
>

</div>


<div class="col-lg-4">

<label class="form-label">

Exam type *

</label>

<select
    class="form-select"
    name="_display_exam_type"
    form="examForm"
    data-mirror="exam_type"
>

<option
    value="Practice"
    <?= $examType === 'Practice'
        ? 'selected'
        : '' ?>
>

Practice

</option>

<option
    value="Live"
    <?= $examType === 'Live'
        ? 'selected'
        : '' ?>
>

Live

</option>

</select>

</div>

</div>


<form
    id="examForm"
    method="post"
    enctype="multipart/form-data"
    class="mt-3"
>


<?= csrf_field() ?>


<input
    type="hidden"
    id="title"
    name="title"
    value="<?= exam_builder_e($title) ?>"
>


<input
    type="hidden"
    id="exam_type"
    name="exam_type"
    value="<?= exam_builder_e($examType) ?>"
>


<div class="row g-3">


<div class="col-lg-4">

<label class="form-label">

Subject *

</label>

<select
    class="form-select"
    name="subject_id"
    required
>

<option value="">

Select subject

</option>


<?php foreach (
    $subjects
    as $subject
): ?>

<option
    value="<?= $subject['id'] ?>"
    <?= $subjectId ===
        (int)$subject['id']
        ? 'selected'
        : '' ?>
>

<?= exam_builder_e(
    $subject['name']
) ?>

<?php if (
    !empty(
        $subject['code']
    )
): ?>

(
<?= exam_builder_e(
    $subject['code']
) ?>
)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-lg-8">

<label class="form-label">

Description

</label>

<textarea
    class="form-control"
    name="description"
    rows="2"
    maxlength="5000"
><?= exam_builder_e($description) ?></textarea>

</div>


<div class="col-md-3">

<label class="form-label">

Total Questions *

</label>

<input
    id="questionCount"
    class="form-control"
    type="number"
    name="question_count"
    min="1"
    max="65535"
    step="1"
    value="<?= exam_builder_e(
        $questionCount ?: ''
    ) ?>"
    required
>

</div>


<div class="col-md-3">

<label class="form-label">

Marks / Question *

</label>

<input
    id="marksPerQuestion"
    class="form-control"
    type="number"
    name="marks_per_question"
    min="0.01"
    max="300"
    step="0.01"
    value="<?= $marksPerQuestion > 0
        ? exam_builder_e(
            $marksPerQuestion
        )
        : '' ?>"
    required
>

</div>


<div class="col-md-3">

<label class="form-label">

Passing Marks *

</label>

<input
    id="passingMarks"
    class="form-control"
    type="number"
    name="passing_marks"
    min="0"
    step="0.01"
    value="<?= exam_builder_e(
        $passingMarks
    ) ?>"
    required
>

</div>


<div class="col-md-3">

<label class="form-label">

Duration (minutes) *

</label>

<input
    class="form-control"
    type="number"
    name="duration_minutes"
    min="1"
    max="65535"
    value="<?= exam_builder_e(
        $duration
    ) ?>"
    required
>

</div>


<div class="col-12">


<div
    class="calc-box
           d-flex
           justify-content-between
           align-items-center
           flex-wrap
           gap-3"
>


<div>

<div class="label">

AUTOMATIC TOTAL MARKS

</div>

<div
    id="totalMarksPreview"
    class="value"
>

0.00 / 300

</div>

</div>


<div class="status-note">

Formula:
Total Questions × Marks per Question

<br>

Total marks cannot exceed 300.

</div>


</div>

</div>


<div class="col-md-6">

<div class="form-check form-switch mt-2">

<input
    class="form-check-input"
    type="checkbox"
    name="negative_marking"
    id="negativeMarking"
    <?= $negativeEnabled
        ? 'checked'
        : '' ?>
>

<label
    class="form-check-label
           small
           fw-semibold"
    for="negativeMarking"
>

Enable negative marking

</label>

</div>

</div>


<div class="col-md-6">

<label class="form-label">

Negative Marks / Wrong Answer

</label>

<input
    id="negativeMarks"
    class="form-control"
    type="number"
    name="negative_marks"
    min="0"
    step="0.01"
    value="<?= exam_builder_e(
        $negativeMarks
    ) ?>"
>

</div>


<div class="col-md-6">

<label class="form-label">

Exam fee

</label>

<input
    class="form-control"
    type="number"
    name="exam_fee"
    min="0"
    step="0.01"
    value="<?= exam_builder_e(
        $examFee
    ) ?>"
>

</div>


<div class="col-md-6">

<div class="form-check form-switch mt-4">

<input
    class="form-check-input"
    type="checkbox"
    name="subscription_required"
    id="subscriptionRequired"
    <?= $subscriptionRequired
        ? 'checked'
        : '' ?>
>

<label
    class="form-check-label
           small
           fw-semibold"
    for="subscriptionRequired"
>

Require active subscription

</label>

</div>

</div>


<div class="col-md-6">

<label class="form-label">

Start date & time

</label>

<input
    class="form-control"
    type="datetime-local"
    name="starts_at"
    value="<?= exam_builder_e(
        $startsAtInput
    ) ?>"
>

</div>


<div class="col-md-6">

<label class="form-label">

End date & time

</label>

<input
    class="form-control"
    type="datetime-local"
    name="ends_at"
    value="<?= exam_builder_e(
        $endsAtInput
    ) ?>"
>

</div>


</div>


<hr class="my-4">


<div class="row g-3">


<div class="col-12">

<h2 class="h5 builder-title mb-1">

2. Add questions

</h2>

<p class="small text-muted mb-0">

Publishing is locked until the exact
configured number of questions is ready.

</p>

</div>


<div class="col-12">


<div class="question-source">


<label
    class="source-option
        <?= $source === 'manual'
            ? 'active'
            : '' ?>"
>


<input
    type="radio"
    name="question_source"
    value="manual"
    <?= $source === 'manual'
        ? 'checked'
        : '' ?>
>


<strong>

<i
    class="fa-solid
           fa-list-check
           me-1"
></i>

Select from question bank

</strong>


<small>

Use your existing active questions.
Their stored marks and negative marks
must match the exam configuration.

</small>


</label>


<label
    class="source-option
        <?= $source === 'csv'
            ? 'active'
            : '' ?>"
>


<input
    type="radio"
    name="question_source"
    value="csv"
    <?= $source === 'csv'
        ? 'checked'
        : '' ?>
>


<strong>

<i
    class="fa-solid
           fa-file-csv
           me-1"
></i>

Upload CSV directly

</strong>


<small>

CSV questions are created and attached
to this exam automatically using the
exam marks configuration.

</small>


</label>


</div>

</div>


<div
    id="manualSource"
    class="col-12
        <?= $source === 'manual'
            ? ''
            : 'hidden' ?>"
>


<input
    id="questionSearch"
    class="form-control
           question-search"
    type="search"
    placeholder="Search your question bank..."
>


<div
    class="d-flex
           justify-content-between
           small
           text-muted
           mb-2"
>

<span>

Selected:

<strong
    id="selectedCount"
>

0

</strong>

</span>


<span>

Required:

<strong
    id="requiredCountLabel"
>

0

</strong>

</span>

</div>


<div class="question-list">


<?php foreach (
    $questions
    as $q
): ?>


<label
    class="question-row"
    data-question-text="<?=
        exam_builder_e(
            strtolower(
                $q['question_text'] .
                ' ' .
                $q['subject_name'] .
                ' ' .
                $q['difficulty']
            )
        )
    ?>"
>


<input
    class="form-check-input
           question-checkbox"
    type="checkbox"
    name="question_ids[]"
    value="<?= $q['id'] ?>"
    <?= in_array(
        (int)$q['id'],
        $selectedIds,
        true
    )
        ? 'checked'
        : '' ?>
>


<span>

<strong>

<?= exam_builder_e(
    $q['question_text']
) ?>

</strong>


<small
    class="d-block
           text-muted"
>

Marks:

<?= exam_builder_e(
    $q['marks']
) ?>

·

Negative:

<?= exam_builder_e(
    $q['negative_marks']
) ?>

·

<?= exam_builder_e(
    $q['difficulty']
) ?>

</small>

</span>


</label>


<?php endforeach; ?>


</div>

</div>


<div
    id="csvSource"
    class="col-12
        <?= $source === 'csv'
            ? ''
            : 'hidden' ?>"
>


<label class="form-label">

CSV file *

</label>


<input
    class="form-control"
    type="file"
    name="questions_csv"
    accept=".csv,text/csv"
>


<div class="csv-help mt-2">

Required columns:

<strong>
question_text, option_a, option_b, correct_answer
</strong>

<br>

Optional:

option_c, option_d, question_type,
explanation, difficulty, topic_id,
estimated_time_seconds.

<br>

Marks and negative marks are taken
automatically from this exam configuration.

</div>


<pre
    class="csv-code
           mt-2
           mb-0"
>question_text,option_a,option_b,option_c,option_d,correct_answer,difficulty
What is 2 + 2?,3,4,5,6,B,Easy</pre>


</div>


</div>


<div class="mt-4 p-3 rule-card">


<strong class="d-block mb-1">

Publishing rules

</strong>


<div class="status-note">

A published exam must contain exactly
the configured number of active questions.

Total marks are calculated automatically
and must be ≤ 300.

Practice exams publish as Active/Upcoming;

scheduled Live exams use
Upcoming/Running/Completed status
based on their dates.

</div>


</div>


<div class="action-bar mt-4">


<a
    class="btn btn-light"
    href="exams.php"
>

Cancel

</a>


<button
    class="btn btn-outline-dark"
    type="submit"
    name="action"
    value="draft"
>

<i
    class="fa-solid
           fa-file-pen
           me-1"
></i>

Save Draft

</button>


<button
    id="publishBtn"
    class="btn btn-success"
    type="submit"
    name="action"
    value="publish"
>

<i
    class="fa-solid
           fa-paper-plane
           me-1"
></i>

Publish Exam

</button>


</div>


</form>

</section>

</div>

</main>

</div>


<script>

(() => {

    const form =
        document.getElementById(
            'examForm'
        );


    const titleDisplay =
        document.querySelector(
            '[data-mirror="title"]'
        );


    const titleInput =
        document.getElementById(
            'title'
        );


    const typeDisplay =
        document.querySelector(
            '[data-mirror="exam_type"]'
        );


    const typeInput =
        document.getElementById(
            'exam_type'
        );


    const qCount =
        document.getElementById(
            'questionCount'
        );


    const marks =
        document.getElementById(
            'marksPerQuestion'
        );


    const passing =
        document.getElementById(
            'passingMarks'
        );


    const negative =
        document.getElementById(
            'negativeMarks'
        );


    const negativeToggle =
        document.getElementById(
            'negativeMarking'
        );


    const totalPreview =
        document.getElementById(
            'totalMarksPreview'
        );


    const requiredLabel =
        document.getElementById(
            'requiredCountLabel'
        );


    const selectedLabel =
        document.getElementById(
            'selectedCount'
        );


    const publishBtn =
        document.getElementById(
            'publishBtn'
        );


    const search =
        document.getElementById(
            'questionSearch'
        );


    const cards = [
        ...document.querySelectorAll(
            '.question-row'
        )
    ];


    const checks = [
        ...document.querySelectorAll(
            '.question-checkbox'
        )
    ];


    /*
    |--------------------------------------------------------------------------
    | RECALCULATE
    |--------------------------------------------------------------------------
    */

    function recalc() {

        const c =
            Math.max(
                0,
                parseInt(
                    qCount.value || '0',
                    10
                )
            );


        const m =
            Math.max(
                0,
                parseFloat(
                    marks.value || '0'
                )
            );


        const total =
            Math.round(
                c *
                m *
                100
            ) /
            100;


        totalPreview.textContent =
            total.toFixed(2) +
            ' / 300';


        totalPreview.style.color =
            total > 300
                ? '#b34235'
                : '#5d4037';


        requiredLabel.textContent =
            c;


        const count =
            checks.filter(
                (
                    checkbox
                ) =>
                    checkbox.checked
            ).length;


        selectedLabel.textContent =
            count;


        publishBtn.disabled =
            total <= 0 ||
            total > 300 ||
            c < 1;


        passing.max =
            total > 0
                ? total
                : '';


        if (
            negativeToggle.checked
        ) {

            negative.disabled =
                false;

            negative.max =
                m || 0;

        } else {

            negative.disabled =
                true;

            negative.value =
                '0';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | TITLE MIRROR
    |--------------------------------------------------------------------------
    */

    if (titleDisplay) {

        titleDisplay.addEventListener(
            'input',
            function () {

                titleInput.value =
                    titleDisplay.value;
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TYPE MIRROR
    |--------------------------------------------------------------------------
    */

    if (typeDisplay) {

        typeDisplay.addEventListener(
            'change',
            function () {

                typeInput.value =
                    typeDisplay.value;
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MARK CALCULATION EVENTS
    |--------------------------------------------------------------------------
    */

    [
        qCount,
        marks,
        passing,
        negative,
        negativeToggle
    ]
        .forEach(
            function (element) {

                if (!element) {
                    return;
                }

                element.addEventListener(
                    'input',
                    recalc
                );

                element.addEventListener(
                    'change',
                    recalc
                );
            }
        );


    /*
    |--------------------------------------------------------------------------
    | QUESTION COUNT LIMIT
    |--------------------------------------------------------------------------
    */

    checks.forEach(
        function (checkbox) {

            checkbox.addEventListener(
                'change',
                function () {

                    const configured =
                        parseInt(
                            qCount.value ||
                            '0',
                            10
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | Never allow more selected
                    | questions than configured.
                    |--------------------------------------------------------------------------
                    */

                    if (
                        checkbox.checked &&
                        configured > 0 &&
                        checks.filter(
                            (
                                item
                            ) =>
                                item.checked
                        ).length >
                        configured
                    ) {

                        checkbox.checked =
                            false;

                        alert(
                            'You cannot select more than ' +
                            configured +
                            ' questions.'
                        );
                    }


                    recalc();
                }
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | QUESTION SEARCH
    |--------------------------------------------------------------------------
    */

    if (search) {

        search.addEventListener(
            'input',
            function () {

                const needle =
                    (
                        search.value ||
                        ''
                    )
                        .toLowerCase()
                        .trim();


                cards.forEach(
                    function (row) {

                        row.hidden =
                            needle !== '' &&
                            !(
                                row.dataset
                                    .questionText
                                    ||
                                    ''
                            )
                                .includes(
                                    needle
                                );
                    }
                );
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | QUESTION SOURCE SWITCH
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            '.source-option input'
        )
        .forEach(
            function (radio) {

                radio.addEventListener(
                    'change',
                    function () {

                        document
                            .querySelectorAll(
                                '.source-option'
                            )
                            .forEach(
                                function (
                                    item
                                ) {

                                    item.classList
                                        .remove(
                                            'active'
                                        );
                                }
                            );


                        radio
                            .closest(
                                '.source-option'
                            )
                            ?.classList.add(
                                'active'
                            );


                        const manualSource =
                            document.getElementById(
                                'manualSource'
                            );


                        const csvSource =
                            document.getElementById(
                                'csvSource'
                            );


                        if (
                            manualSource
                        ) {

                            manualSource.classList.toggle(
                                'hidden',
                                radio.value !==
                                    'manual'
                            );
                        }


                        if (
                            csvSource
                        ) {

                            csvSource.classList.toggle(
                                'hidden',
                                radio.value !==
                                    'csv'
                            );
                        }
                    }
                );
            }
        );


    /*
    |--------------------------------------------------------------------------
    | FORM SUBMIT
    |--------------------------------------------------------------------------
    */

    if (form) {

        form.addEventListener(
            'submit',
            function (event) {

                titleInput.value =
                    titleDisplay
                        ?.value
                        ?.trim()
                        ||
                        '';


                typeInput.value =
                    typeDisplay
                        ?.value
                        ||
                        'Practice';


                recalc();


                const action =
                    event.submitter
                        ?.value
                        ||
                        '';


                const count =
                    parseInt(
                        qCount.value ||
                        '0',
                        10
                    );


                const source =
                    document.querySelector(
                        'input[name="question_source"]:checked'
                    )?.value ||
                    'manual';


                /*
                |--------------------------------------------------------------------------
                | PUBLISH MANUAL
                |--------------------------------------------------------------------------
                */

                if (
                    action === 'publish' &&
                    source === 'manual'
                ) {

                    const selected =
                        checks.filter(
                            (
                                checkbox
                            ) =>
                                checkbox.checked
                        ).length;


                    if (
                        selected !==
                        count
                    ) {

                        event.preventDefault();


                        alert(
                            'Select exactly ' +
                            count +
                            ' questions before publishing.'
                        );


                        return;
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | PUBLISH CSV
                |--------------------------------------------------------------------------
                */

                if (
                    action === 'publish' &&
                    source === 'csv'
                ) {

                    const file =
                        document.querySelector(
                            'input[name="questions_csv"]'
                        );


                    if (
                        !file ||
                        !file.files ||
                        !file.files.length
                    ) {

                        event.preventDefault();


                        alert(
                            'Choose a CSV file before publishing.'
                        );


                        return;
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | TOTAL MARKS
                |--------------------------------------------------------------------------
                */

                const total =
                    Math.round(
                        parseFloat(
                            qCount.value ||
                            '0'
                        ) *
                        parseFloat(
                            marks.value ||
                            '0'
                        ) *
                        100
                    ) /
                    100;


                if (
                    total <= 0 ||
                    total > 300
                ) {

                    event.preventDefault();


                    alert(
                        'Total marks must be greater than 0 and cannot exceed 300.'
                    );
                }
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | INITIAL CALCULATION
    |--------------------------------------------------------------------------
    */

    recalc();

})();

</script>


</body>

</html>