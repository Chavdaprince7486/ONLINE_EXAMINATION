<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/exam_validation.php';
require_once '../config/exam_builder.php';

if (!is_array($_SESSION ?? null) || (int)($_SESSION['user_id'] ?? 0) <= 0 || ($_SESSION['user_role'] ?? '') !== 'teacher') {
    header('Location: ../auth/login.php');
    exit;
}

$teacherId = (int)$_SESSION['user_id'];
$error = '';
$message = '';
$successExamId = 0;

$title = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$subjectId = filter_var($_POST['subject_id'] ?? '', FILTER_VALIDATE_INT);
$examType = trim((string)($_POST['exam_type'] ?? 'Practice'));
$questionCount = filter_var($_POST['question_count'] ?? '', FILTER_VALIDATE_INT);
$marksPerQuestion = filter_var($_POST['marks_per_question'] ?? '', FILTER_VALIDATE_FLOAT);
$passingMarks = filter_var($_POST['passing_marks'] ?? '', FILTER_VALIDATE_FLOAT);
$negativeEnabled = isset($_POST['negative_marking']);
$negativeMarks = filter_var($_POST['negative_marks'] ?? '0', FILTER_VALIDATE_FLOAT);
$duration = filter_var($_POST['duration_minutes'] ?? '60', FILTER_VALIDATE_INT);
$examFee = filter_var($_POST['exam_fee'] ?? '0', FILTER_VALIDATE_FLOAT);
$subscriptionRequired = isset($_POST['subscription_required']);
$startsAtInput = trim((string)($_POST['starts_at'] ?? ''));
$endsAtInput = trim((string)($_POST['ends_at'] ?? ''));

$action = trim((string)($_POST['action'] ?? ''));

/*
|--------------------------------------------------------------------------
| CSV IS NOW THE DEFAULT / PRIMARY METHOD
|--------------------------------------------------------------------------
*/

$source = trim((string)($_POST['question_source'] ?? 'csv'));

$selectedIds = $_POST['question_ids'] ?? [];

if (!is_array($selectedIds)) {
    $selectedIds = [];
}

$selectedIds = array_values(
    array_unique(
        array_filter(
            array_map(
                'intval',
                $selectedIds
            ),
            static fn(int $id): bool => $id > 0
        )
    )
);

$questionCount = (
    $questionCount !== false &&
    $questionCount !== null
)
    ? $questionCount
    : 0;

$marksPerQuestion = (
    $marksPerQuestion !== false &&
    $marksPerQuestion !== null
)
    ? $marksPerQuestion
    : 0.0;

$passingMarks = (
    $passingMarks !== false &&
    $passingMarks !== null
)
    ? $passingMarks
    : 0.0;

$negativeMarks = (
    $negativeMarks !== false &&
    $negativeMarks !== null
)
    ? $negativeMarks
    : 0.0;

$duration = (
    $duration !== false &&
    $duration !== null
)
    ? $duration
    : 60;

$examFee = (
    $examFee !== false &&
    $examFee !== null
)
    ? $examFee
    : 0.0;

$subjects = [];
$questions = [];

try {

    $subjects = $conn->query(
        "
        SELECT
            id,
            name,
            code

        FROM subjects

        WHERE
            status = 'Active'

        ORDER BY
            name ASC,
            id ASC
        "
    )->fetchAll(
        PDO::FETCH_ASSOC
    );


    $questionStmt = $conn->prepare(
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

            ON s.id =
               q.subject_id

           AND s.status =
               'Active'

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

} catch (
    Throwable $e
) {

    error_log(
        'Teacher create exam load failed: ' .
        $e->getMessage()
    );

    $error =
        'Unable to load exam configuration data.';
}


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    $error === ''
) {

    if (
        !verify_csrf_token(
            $_POST['csrf_token']
            ??
            null
        )
    ) {

        $error =
            'Invalid security token. Refresh the page and try again.';

    } elseif (
        $title === ''
        ||
        mb_strlen(
            $title
        ) > 180
    ) {

        $error =
            'Exam title is required and must not exceed 180 characters.';

    } elseif (
        $subjectId === false
        ||
        $subjectId < 1
    ) {

        $error =
            'Please select a subject.';

    } elseif (
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

    } elseif (
        $questionCount < 1
        ||
        $questionCount > 65535
    ) {

        $error =
            'Question count must be between 1 and 65535.';

    } elseif (
        $marksPerQuestion <= 0
    ) {

        $error =
            'Marks per question must be greater than zero.';

    } elseif (
        (
            $questionCount *
            $marksPerQuestion
        )
        >
        EXAM_BUILDER_MAX_TOTAL_MARKS
    ) {

        $error =
            'Total marks cannot exceed 300. Reduce the question count or marks per question.';

    } elseif (
        $passingMarks < 0
        ||
        $passingMarks >
        (
            $questionCount *
            $marksPerQuestion
        )
    ) {

        $error =
            'Passing marks must be between 0 and the calculated total marks.';

    } elseif (
        $duration < 1
        ||
        $duration > 65535
    ) {

        $error =
            'Duration must be between 1 and 65535 minutes.';

    } elseif (
        $negativeMarks < 0
        ||
        $negativeMarks >
        $marksPerQuestion
    ) {

        $error =
            'Negative marks must be between 0 and marks per question.';

    }


    if (
        $error === ''
        &&
        !$negativeEnabled
    ) {

        $negativeMarks =
            0.0;

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
        $error === ''
        &&
        (
            $startsAtInput !== ''
            &&
            $startsAt === null
        )
    ) {

        $error =
            'Invalid start date and time.';

    }


    if (
        $error === ''
        &&
        (
            $endsAtInput !== ''
            &&
            $endsAt === null
        )
    ) {

        $error =
            'Invalid end date and time.';

    }


    if (
        $error === ''
        &&
        $startsAt !== null
        &&
        $endsAt !== null
        &&
        strtotime(
            $endsAt
        )
        <=
        strtotime(
            $startsAt
        )
    ) {

        $error =
            'End date and time must be after start date and time.';

    }


    if (
        $error === ''
        &&
        $examType === 'Live'
        &&
        $action === 'publish' &&
        $startsAt === null
    ) {

        $error =
            'A Live exam requires a start date and time before publishing.';

    }


    if (
        $error === ''
        &&
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

    $questionRows =
        [];

    $existingRows =
        [];


    /*
    |--------------------------------------------------------------------------
    | PUBLISH QUESTION VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        &&
        $action === 'publish'
    ) {

        /*
        |--------------------------------------------------------------------------
        | PRIMARY: CSV
        |--------------------------------------------------------------------------
        */

        if (
            $source === 'csv'
        ) {

            $upload =
                $_FILES[
                    'questions_csv'
                ]
                ??
                null;


            if (
                !is_array(
                    $upload
                )
                ||
                (
                    $upload[
                        'error'
                    ]
                    ??
                    UPLOAD_ERR_NO_FILE
                )
                !==
                UPLOAD_ERR_OK
            ) {

                $error =
                    'Please select a valid CSV file.';

            } else {

                try {

                    $questionRows =
                        exam_builder_parse_csv_questions(

                            (string)
                            $upload[
                                'tmp_name'
                            ],

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
                    Throwable $e
                ) {

                    $error =
                        $e->getMessage();
                }

            }


        }


        /*
        |--------------------------------------------------------------------------
        | OPTIONAL: QUESTION BANK
        |--------------------------------------------------------------------------
        */

        else {

            if (
                count(
                    $selectedIds
                )
                !==
                $questionCount
            ) {

                $error =
                    'You must select exactly ' .
                    $questionCount .
                    ' questions before publishing this exam.';

            } else {

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
                    Throwable $e
                ) {

                    $error =
                        $e->getMessage();

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
        $error === ''
        &&
        $action === 'draft'
    ) {

        if (
            $source === 'manual'
            &&
            count(
                $selectedIds
            )
            >
            $questionCount
        ) {

            $error =
                'Draft cannot contain more questions than the configured question count.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        &&
        in_array(
            $action,
            [
                'draft',
                'publish'
            ],
            true
        )
    ) {

        $status =
            'Draft';


        if (
            $action ===
            'publish'
        ) {

            $status =
                exam_builder_publish_status(

                    $examType,

                    $startsAt,

                    $endsAt

                );


            if (
                $examType === 'Practice'
                &&
                $status === 'Running'
            ) {

                $status =
                    'Active';

            }

        }


        /*
        |--------------------------------------------------------------------------
        | EXACT QUESTION COUNT
        |--------------------------------------------------------------------------
        */

        if (
            $action === 'publish'
            &&
            count(
                $questionRows
            )
            !==
            $questionCount
            &&
            count(
                $existingRows
            )
            !==
            $questionCount
        ) {

            $error =
                'The exam cannot be published until exactly ' .
                $questionCount .
                ' questions are ready.';

        }


        if (
            $error === ''
        ) {

            try {

                $exam = [

                    'subject_id' =>
                        (int)
                        $subjectId,

                    'teacher_id' =>
                        $teacherId,

                    'title' =>
                        $title,

                    'description' =>
                        $description,

                    'exam_type' =>
                        $examType,

                    'duration_minutes' =>
                        (int)
                        $duration,

                    'required_question_count' =>
                        (int)
                        $questionCount,

                    'marks_per_question' =>
                        (float)
                        $marksPerQuestion,

                    'passing_marks' =>
                        (float)
                        $passingMarks,

                    'negative_marking' =>
                        $negativeEnabled
                            ? 1
                            : 0,

                    'exam_fee' =>
                        max(
                            0.0,
                            (float)
                            $examFee
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


                $successExamId =
                    exam_builder_create_exam(

                        $conn,

                        $exam,

                        $selectedIds,

                        $questionRows,

                        $creatorTeacherId

                    );


                $message =
                    $action === 'publish'

                        ? 'Exam published successfully. It is now available according to its status and schedule.'

                        : 'Draft saved successfully. Students will not see it until it is published with the exact question count.';


                $title =
                    '';

                $description =
                    '';

                $subjectId =
                    null;

                $questionCount =
                    0;

                $marksPerQuestion =
                    0.0;

                $passingMarks =
                    0.0;

                $negativeEnabled =
                    false;

                $negativeMarks =
                    0.0;

                $duration =
                    60;

                $examFee =
                    0.0;

                $subscriptionRequired =
                    false;

                $startsAtInput =
                    '';

                $endsAtInput =
                    '';

                $selectedIds =
                    [];

                $source =
                    'csv';


            } catch (
                Throwable $e
            ) {

                $error =
                    'Exam could not be created: ' .
                    $e->getMessage();

            }

        }

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

body{
    background:#f5f3ec;
}

.builder-wrap{
    max-width:1280px;
    margin:auto;
}

.builder-card{
    border:1px solid #e8dfd3;
    border-radius:20px;
    background:#fff;
    box-shadow:
        0 16px 45px
        rgba(
            62,
            39,
            35,
            .07
        );
    padding:24px;
}

.builder-title{
    color:#5d4037;
}

.rule-card{
    background:#f7f5ef;
    border:1px solid #e9e0d5;
    border-radius:16px;
    padding:15px;
}

.form-label{
    font-size:.72rem;
    font-weight:700;
    color:#5e524a;
}

.form-control,
.form-select{
    border-radius:11px;
    font-size:.74rem;
    min-height:44px;
    border-color:#ded5ca;
}

.form-control:focus,
.form-select:focus{
    border-color:#8b6754;
    box-shadow:
        0 0 0 3px
        rgba(
            93,
            64,
            55,
            .07
        );
}

.question-source{
    display:grid;
    grid-template-columns:
        1fr
        1fr;
    gap:10px;
}

.source-option{
    padding:15px;
    border:1px solid #e6ded2;
    border-radius:14px;
    cursor:pointer;
    background:#fcfaf6;
    transition:
        .2s
        ease;
}

.source-option:hover{
    transform:translateY(-1px);
}

.source-option.active{
    border-color:#6d7f2b;
    background:#f0f5e9;
    box-shadow:
        0 8px 20px
        rgba(
            85,
            107,
            47,
            .08
        );
}

.source-option input{
    display:none;
}

.source-option strong{
    display:block;
    font-size:.73rem;
}

.source-option small{
    display:block;
    color:#8a8078;
    font-size:.59rem;
    line-height:1.5;
    margin-top:4px;
}

.source-recommended{
    display:inline-flex;
    align-items:center;
    gap:5px;
    margin-top:7px;
    padding:4px 7px;
    border-radius:999px;
    background:#e4eed9;
    color:#556b2f;
    font-size:.5rem;
    font-weight:800;
}

.question-list{
    max-height:430px;
    overflow:auto;
    border:1px solid #e6ded2;
    border-radius:14px;
}

.question-row{
    display:flex;
    gap:10px;
    padding:11px 12px;
    border-bottom:1px solid #eee7dd;
}

.question-row:last-child{
    border-bottom:0;
}

.question-search{
    margin-bottom:10px;
}

.calc-box{
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

.calc-box .label{
    font-size:.6rem;
    color:#81766f;
}

.calc-box .value{
    font-size:1.25rem;
    font-weight:800;
    color:#5d4037;
}

.action-bar{
    display:flex;
    gap:10px;
    justify-content:flex-end;
    padding-top:18px;
    border-top:1px solid #ece5db;
}

.status-note{
    font-size:.61rem;
    color:#81766f;
    line-height:1.5;
}

.csv-help{
    font-size:.62rem;
    color:#81766f;
    line-height:1.55;
}

.csv-code{
    padding:12px;
    border-radius:11px;
    background:#211c19;
    color:#eee;
    font-size:.58rem;
    overflow:auto;
}

.hidden{
    display:none!important;
}

.primary-upload-note{
    display:flex;
    align-items:flex-start;
    gap:8px;
    margin-bottom:11px;
    padding:10px 12px;
    border-radius:11px;
    background:#eef5e7;
    border:1px solid #dbe8cf;
    color:#64704e;
    font-size:.58rem;
    line-height:1.5;
}

.primary-upload-note i{
    color:#5d782c;
    margin-top:2px;
}

@media(
    max-width:700px
){

    .question-source{
        grid-template-columns:1fr;
    }

    .action-bar{
        flex-direction:column;
    }

    .action-bar .btn,
    .action-bar a{
        width:100%;
    }

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
            from question count × marks per question.
            Maximum 300 marks.

        </p>

    </div>

    <a
        class="btn btn-outline-dark"
        href="exams.php"
    >

        <i
            class="
                fa-solid
                fa-arrow-left
                me-1
            "
        ></i>

        My Exams

    </a>

</header>


<div class="builder-wrap">


<?php if (
    $error !== ''
): ?>

<div class="alert alert-danger">

    <i
        class="
            fa-solid
            fa-circle-exclamation
            me-2
        "
    ></i>

    <?= exam_builder_e(
        $error
    ) ?>

</div>

<?php endif; ?>


<?php if (
    $message !== ''
): ?>

<div class="alert alert-success">

    <i
        class="
            fa-solid
            fa-circle-check
            me-2
        "
    ></i>

    <?= exam_builder_e(
        $message
    ) ?>

    <?php if (
        $successExamId
    ): ?>

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

<h2
    class="
        h5
        builder-title
        mb-1
    "
>
    1. Examination Details
</h2>

<p class="small text-muted mb-0">

    Configure the examination structure.
    Total marks are calculated automatically.

</p>

</div>


<div class="col-lg-8">

<label class="form-label">
    Exam Title *
</label>

<input
    class="form-control"
    name="_display_title"
    form="examForm"
    value="<?= exam_builder_e(
        $title
    ) ?>"
    data-mirror="title"
    placeholder="e.g. UPSC General Studies Mock Test"
    required
>

</div>


<div class="col-lg-4">

<label class="form-label">
    Exam Type *
</label>

<select
    class="form-select"
    name="_display_exam_type"
    form="examForm"
    data-mirror="exam_type"
>

<option value="Practice">
    Practice
</option>

<option value="Live">
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
    value="<?= exam_builder_e(
        $title
    ) ?>"
>


<input
    type="hidden"
    id="exam_type"
    name="exam_type"
    value="<?= exam_builder_e(
        $examType
    ) ?>"
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
    value="<?= (int)$subject['id'] ?>"
    <?= (
        $subjectId !== false
        &&
        $subjectId !== null
        &&
        $subjectId ===
        (int)$subject['id']
    )
        ? 'selected'
        : ''
    ?>
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
    placeholder="Write exam instructions or a short description..."
><?= exam_builder_e(
    $description
) ?></textarea>

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
    placeholder="e.g. 50"
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
        : ''
    ?>"
    placeholder="e.g. 2"
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
    placeholder="e.g. 40"
    required
>

</div>


<div class="col-md-3">

<label class="form-label">
    Duration (Minutes) *
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
    class="
        calc-box
        d-flex
        justify-content-between
        align-items-center
        flex-wrap
        gap-3
    "
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

    Maximum total marks: 300

</div>

</div>

</div>


<div class="col-md-6">

<div
    class="
        form-check
        form-switch
        mt-2
    "
>

<input
    class="form-check-input"
    type="checkbox"
    name="negative_marking"
    id="negativeMarking"
    <?= $negativeEnabled
        ? 'checked'
        : ''
    ?>
>

<label
    class="
        form-check-label
        small
        fw-semibold
    "
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
    placeholder="e.g. 0.50"
>

</div>


<div class="col-md-6">

<label class="form-label">
    Exam Fee
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
    placeholder="0"
>

</div>


<div class="col-md-6">

<div
    class="
        form-check
        form-switch
        mt-4
    "
>

<input
    class="form-check-input"
    type="checkbox"
    name="subscription_required"
    id="subscriptionRequired"
    <?= $subscriptionRequired
        ? 'checked'
        : ''
    ?>
>

<label
    class="
        form-check-label
        small
        fw-semibold
    "
    for="subscriptionRequired"
>

    Require active subscription

</label>

</div>

</div>


<div class="col-md-6">

<label class="form-label">
    Start Date & Time
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
    End Date & Time
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

<h2
    class="
        h5
        builder-title
        mb-1
    "
>

    2. Add Questions

</h2>

<p class="small text-muted mb-0">

    CSV is the recommended method.
    Question Bank remains available for
    reusing existing questions.

</p>

</div>


<div class="col-12">


<div
    class="
        primary-upload-note
    "
>

<i
    class="
        fa-solid
        fa-bolt
    "
></i>

<div>

<strong>
    Recommended workflow:
</strong>

Upload your complete CSV directly.
You do not need to create every question
manually first.

The system will automatically create
and attach exactly the configured number
of questions.

</div>

</div>


<div
    class="
        question-source
    "
>


<label
    class="
        source-option
        <?= $source === 'csv'
            ? 'active'
            : ''
        ?>"
>

<input
    type="radio"
    name="question_source"
    value="csv"
    <?= $source === 'csv'
        ? 'checked'
        : ''
    ?>
>

<strong>

<i
    class="
        fa-solid
        fa-file-csv
        me-1
    "
></i>

Upload CSV Directly

</strong>

<small>

Recommended for creating complete
exams quickly.

The system creates the questions
and attaches them automatically.

</small>

<span
    class="
        source-recommended
    "
>

<i
    class="
        fa-solid
        fa-star
    "
></i>

Recommended

</span>

</label>


<label
    class="
        source-option
        <?= $source === 'manual'
            ? 'active'
            : ''
        ?>"
>

<input
    type="radio"
    name="question_source"
    value="manual"
    <?= $source === 'manual'
        ? 'checked'
        : ''
    ?>
>

<strong>

<i
    class="
        fa-solid
        fa-list-check
        me-1
    "
></i>

Question Bank

</strong>

<small>

Optional reusable question
selection from your existing
teacher question bank.

</small>

</label>


</div>

</div>


<div
    id="csvSource"
    class="
        col-12
        <?= $source === 'csv'
            ? ''
            : 'hidden'
        ?>"
>


<label class="form-label">

    Complete Question CSV *

</label>


<input
    class="form-control"
    type="file"
    name="questions_csv"
    accept=".csv,text/csv"
>


<div class="csv-help mt-2">

Required:

<strong>

question_text,
option_a,
option_b,
correct_answer

</strong>

<br>

Optional:

option_c,
option_d,
question_type,
explanation,
difficulty,
topic_id,
estimated_time_seconds.

<br>

Marks and negative marks are automatically
taken from the exam configuration.

</div>


<pre
    class="
        csv-code
        mt-2
        mb-0
    "
>question_text,option_a,option_b,option_c,option_d,correct_answer,difficulty
What is 2 + 2?,3,4,5,6,B,Easy
What is the capital of India?,Mumbai,Delhi,Kolkata,Chennai,B,Easy</pre>


<div
    class="
        status-note
        mt-2
    "
>

Example:

If Total Questions = 20,
CSV must contain exactly 20 question rows.

</div>

</div>


<div
    id="manualSource"
    class="
        col-12
        <?= $source === 'manual'
            ? ''
            : 'hidden'
        ?>"
>


<input
    id="questionSearch"
    class="
        form-control
        question-search
    "
    type="search"
    placeholder="Search your question bank..."
>


<div
    class="
        d-flex
        justify-content-between
        small
        text-muted
        mb-2
    "
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
    class="
        question-row
    "
    data-question-text="<?= exam_builder_e(
        strtolower(
            $q[
                'question_text'
            ]
            . ' ' .
            $q[
                'subject_name'
            ]
            . ' ' .
            $q[
                'difficulty'
            ]
        )
    ) ?>"
>

<input
    class="
        form-check-input
        question-checkbox
    "
    type="checkbox"
    name="question_ids[]"
    value="<?= (int)$q['id'] ?>"
    <?= in_array(
        (int)$q['id'],
        $selectedIds,
        true
    )
        ? 'checked'
        : ''
    ?>
>

<span>

<strong>

<?= exam_builder_e(
    $q[
        'question_text'
    ]
) ?>

</strong>

<small
    class="
        d-block
        text-muted
    "
>

Marks:

<?= exam_builder_e(
    $q[
        'marks'
    ]
) ?>

·

Negative:

<?= exam_builder_e(
    $q[
        'negative_marks'
    ]
) ?>

·

<?= exam_builder_e(
    $q[
        'difficulty'
    ]
) ?>

</small>

</span>

</label>

<?php endforeach; ?>


<?php if (
    !$questions
): ?>

<div
    class="
        text-center
        p-5
        text-muted
    "
>

<i
    class="
        fa-solid
        fa-circle-question
        mb-2
        d-block
        fs-4
    "
></i>

No active questions
are available in your
question bank.

<br>

Use CSV upload to create
the exam directly.

</div>

<?php endif; ?>

</div>

</div>

</div>


<div
    class="
        mt-4
        p-3
        rule-card
    "
>

<strong
    class="
        d-block
        mb-1
    "
>

Publishing Rules

</strong>


<div class="status-note">

The system requires exactly
the configured number of questions.

Total marks are automatically
calculated and cannot exceed 300.

Draft exams stay private.

Published exams become available
according to their type, status
and schedule.

</div>

</div>


<div
    class="
        action-bar
        mt-4
    "
>

<a
    class="
        btn
        btn-light
    "
    href="exams.php"
>

Cancel

</a>


<button
    class="
        btn
        btn-outline-dark
    "
    type="submit"
    name="action"
    value="draft"
>

<i
    class="
        fa-solid
        fa-file-pen
        me-1
    "
></i>

Save Draft

</button>


<button
    id="publishBtn"
    class="
        btn
        btn-success
    "
    type="submit"
    name="action"
    value="publish"
>

<i
    class="
        fa-solid
        fa-paper-plane
        me-1
    "
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

    'use strict';


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


    const cards =
        [
            ...document.querySelectorAll(
                '.question-row'
            )
        ];


    const checks =
        [
            ...document.querySelectorAll(
                '.question-checkbox'
            )
        ];


    function recalc()
    {

        const count =
            Math.max(
                0,
                parseInt(
                    qCount.value ||
                    '0',
                    10
                )
            );


        const marksValue =
            Math.max(
                0,
                parseFloat(
                    marks.value ||
                    '0'
                )
            );


        const total =
            Math.round(
                count *
                marksValue *
                100
            )
            /
            100;


        totalPreview.textContent =
            total.toFixed(
                2
            )
            +
            ' / 300';


        totalPreview.style.color =
            total > 300

                ? '#b34235'

                : '#5d4037';


        requiredLabel.textContent =
            count;


        selectedLabel.textContent =
            checks.filter(
                checkbox =>
                    checkbox.checked
            ).length;


        publishBtn.disabled =
            total <= 0
            ||
            total > 300
            ||
            count < 1;


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
                marksValue || 0;

        } else {

            negative.disabled =
                true;

            negative.value =
                '0';

        }

    }


    if (
        titleDisplay
    ) {

        titleDisplay.addEventListener(
            'input',
            () => {

                titleInput.value =
                    titleDisplay.value;

            }
        );

    }


    if (
        typeDisplay
    ) {

        typeDisplay.addEventListener(
            'change',
            () => {

                typeInput.value =
                    typeDisplay.value;

            }
        );

    }


    [
        qCount,
        marks,
        passing,
        negative,
        negativeToggle
    ].forEach(
        element => {

            element?.addEventListener(
                'input',
                recalc
            );

            element?.addEventListener(
                'change',
                recalc
            );

        }
    );


    checks.forEach(
        checkbox => {

            checkbox.addEventListener(
                'change',
                () => {

                    const limit =
                        parseInt(
                            qCount.value ||
                            '0',
                            10
                        );


                    const count =
                        checks.filter(
                            check =>
                                check.checked
                        ).length;


                    if (
                        limit > 0
                        &&
                        count >
                        limit
                    ) {

                        checkbox.checked =
                            false;

                    }


                    recalc();

                }
            );

        }
    );


    search?.addEventListener(
        'input',
        () => {

            const needle =
                (
                    search.value ||
                    ''
                )
                    .toLowerCase()
                    .trim();


            cards.forEach(
                row => {

                    row.hidden =
                        needle !== ''
                        &&
                        !(
                            row.dataset
                                .questionText
                            ||
                            ''
                        ).includes(
                            needle
                        );

                }
            );

        }
    );


    document
        .querySelectorAll(
            '.source-option input'
        )
        .forEach(
            radio => {

                radio.addEventListener(
                    'change',
                    () => {

                        document
                            .querySelectorAll(
                                '.source-option'
                            )
                            .forEach(
                                item => {

                                    item.classList.remove(
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


                        const manual =
                            document.getElementById(
                                'manualSource'
                            );


                        const csv =
                            document.getElementById(
                                'csvSource'
                            );


                        manual.classList.toggle(
                            'hidden',
                            radio.value !==
                            'manual'
                        );


                        csv.classList.toggle(
                            'hidden',
                            radio.value !==
                            'csv'
                        );

                    }
                );

            }
        );


    form?.addEventListener(
        'submit',
        event => {

            titleInput.value =
                titleDisplay?.value
                    ?.trim()
                ||
                '';


            typeInput.value =
                typeDisplay?.value
                ||
                'Practice';


            recalc();


            const action =
                event
                    .submitter
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
                )?.value
                ||
                'csv';


            if (
                action ===
                'publish'
                &&
                source ===
                'manual'
                &&
                checks.filter(
                    checkbox =>
                        checkbox.checked
                ).length
                !==
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


            if (
                action ===
                'publish'
                &&
                source ===
                'csv'
            ) {

                const file =
                    document.querySelector(
                        'input[name="questions_csv"]'
                    );


                if (
                    !file
                    ||
                    !file.files
                    ||
                    !file.files.length
                ) {

                    event.preventDefault();


                    alert(
                        'Choose the complete CSV file before publishing.'
                    );


                    return;

                }

            }

        }
    );


    recalc();

})();

</script>

</body>

</html>