<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/auth.php';
require_once '../config/notification_events.php';

require_login('teacher');

$teacherId = (int)($_SESSION['user_id'] ?? 0);

$error = '';
$success = '';
$imported = 0;
$skipped = 0;
$firstImportedQuestionId = null;

$subjects = [];

function teacher_import_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function teacher_import_normalize_header(mixed $value): string
{
    $value = preg_replace(
        '/^\xEF\xBB\xBF/',
        '',
        (string)$value
    );

    return strtolower(
        trim(
            (string)$value
        )
    );
}

function teacher_import_parse_nullable_int(
    string $value,
    string $field,
    int $row
): ?int {
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    $parsed = filter_var(
        $value,
        FILTER_VALIDATE_INT
    );

    if ($parsed === false || $parsed < 0) {
        throw new RuntimeException(
            "CSV row {$row}: {$field} must be a non-negative integer or blank."
        );
    }

    return (int)$parsed;
}

function teacher_import_parse_float(
    string $value,
    string $field,
    int $row,
    float $minimum = 0.0
): float {
    $value = trim($value);

    $parsed = filter_var(
        $value,
        FILTER_VALIDATE_FLOAT
    );

    if (
        $parsed === false ||
        !is_finite((float)$parsed) ||
        (float)$parsed < $minimum
    ) {
        throw new RuntimeException(
            "CSV row {$row}: {$field} must be a valid number greater than or equal to {$minimum}."
        );
    }

    return round(
        (float)$parsed,
        2
    );
}

try {
    $subjectStatement = $conn->query(
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
    );

    $subjects = $subjectStatement->fetchAll(
        PDO::FETCH_ASSOC
    );
} catch (Throwable $exception) {
    error_log(
        'Teacher question-import subject load failed: ' .
        $exception->getMessage()
    );

    $error =
        'Unable to load active subjects.';
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $error === ''
) {
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

        $subjectId = filter_var(
            $_POST['subject_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        if (
            $subjectId === false ||
            $subjectId <= 0
        ) {
            throw new RuntimeException(
                'Please select a valid subject.'
            );
        }

        $subjectStatement = $conn->prepare(
            "
            SELECT
                id,
                name,
                status
            FROM subjects
            WHERE id = ?
            LIMIT 1
            "
        );

        $subjectStatement->execute([
            (int)$subjectId
        ]);

        $subject =
            $subjectStatement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$subject) {
            throw new RuntimeException(
                'Selected subject does not exist.'
            );
        }

        if (
            (string)$subject['status'] !==
            'Active'
        ) {
            throw new RuntimeException(
                'Selected subject is inactive.'
            );
        }

        $upload =
            $_FILES['questions_csv'] ?? null;

        if (!is_array($upload)) {
            throw new RuntimeException(
                'Please select a CSV file.'
            );
        }

        if (
            (int)(
                $upload['error']
                ?? UPLOAD_ERR_NO_FILE
            ) !== UPLOAD_ERR_OK
        ) {
            throw new RuntimeException(
                'CSV upload failed. Please try again.'
            );
        }

        $tmpName =
            (string)(
                $upload['tmp_name'] ?? ''
            );

        $originalName =
            (string)(
                $upload['name'] ?? ''
            );

        $fileSize =
            (int)(
                $upload['size'] ?? 0
            );

        if (
            $tmpName === '' ||
            !is_uploaded_file($tmpName)
        ) {
            throw new RuntimeException(
                'Invalid uploaded file.'
            );
        }

        if (
            strtolower(
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                )
            ) !== 'csv'
        ) {
            throw new RuntimeException(
                'Only CSV files are allowed.'
            );
        }

        if ($fileSize <= 0) {
            throw new RuntimeException(
                'The uploaded CSV file is empty.'
            );
        }

        if ($fileSize > 5 * 1024 * 1024) {
            throw new RuntimeException(
                'Maximum CSV file size is 5 MB.'
            );
        }

        $handle = fopen(
            $tmpName,
            'rb'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'Unable to read the uploaded CSV file.'
            );
        }

        try {
            /*
            |--------------------------------------------------------------------------
            | Canonical question-bank CSV format
            |--------------------------------------------------------------------------
            |
            | Exam assignment is NOT stored on questions.
            | Questions belong to the teacher + subject and are later connected
            | to exams through exam_questions.
            |
            |--------------------------------------------------------------------------
            */

            $expectedHeader = [
                'topic_id',
                'question_text',
                'question_type',
                'option_a',
                'option_b',
                'option_c',
                'option_d',
                'correct_answer',
                'explanation',
                'difficulty',
                'marks',
                'negative_marks',
                'estimated_time_seconds',
                'status'
            ];

            $header = fgetcsv(
                $handle,
                0,
                ','
            );

            if ($header === false) {
                throw new RuntimeException(
                    'CSV file must contain a header row.'
                );
            }

            $normalizedHeader = array_map(
                'teacher_import_normalize_header',
                $header
            );

            if (
                $normalizedHeader !==
                $expectedHeader
            ) {
                throw new RuntimeException(
                    'Invalid CSV header. Use the ExamSphere question-bank CSV format shown on this page.'
                );
            }

            $rows = [];
            $duplicateKeys = [];
            $rowNumber = 1;

            while (
                (
                    $data =
                        fgetcsv(
                            $handle,
                            0,
                            ','
                        )
                ) !== false
            ) {
                $rowNumber++;

                if (
                    count($data) === 1 &&
                    trim((string)$data[0]) === ''
                ) {
                    continue;
                }

                if (
                    count($data) !==
                    count($expectedHeader)
                ) {
                    throw new RuntimeException(
                        "CSV row {$rowNumber} must contain exactly " .
                        count($expectedHeader) .
                        " columns."
                    );
                }

                $topicId =
                    teacher_import_parse_nullable_int(
                        (string)$data[0],
                        'topic_id',
                        $rowNumber
                    );

                $questionText =
                    trim(
                        (string)$data[1]
                    );

                $questionType =
                    trim(
                        (string)$data[2]
                    );

                $optionA =
                    trim(
                        (string)$data[3]
                    );

                $optionB =
                    trim(
                        (string)$data[4]
                    );

                $optionC =
                    trim(
                        (string)$data[5]
                    );

                $optionD =
                    trim(
                        (string)$data[6]
                    );

                $correctAnswer =
                    strtoupper(
                        trim(
                            (string)$data[7]
                        )
                    );

                $explanation =
                    trim(
                        (string)$data[8]
                    );

                $difficulty =
                    ucfirst(
                        strtolower(
                            trim(
                                (string)$data[9]
                            )
                        )
                    );

                $marks =
                    teacher_import_parse_float(
                        (string)$data[10],
                        'marks',
                        $rowNumber,
                        0.01
                    );

                $negativeMarks =
                    teacher_import_parse_float(
                        (string)$data[11],
                        'negative_marks',
                        $rowNumber,
                        0.0
                    );

                $estimatedTime =
                    teacher_import_parse_nullable_int(
                        (string)$data[12],
                        'estimated_time_seconds',
                        $rowNumber
                    );

                $status =
                    ucfirst(
                        strtolower(
                            trim(
                                (string)$data[13]
                            )
                        )
                    );

                if ($questionText === '') {
                    throw new RuntimeException(
                        "CSV row {$rowNumber}: question_text is required."
                    );
                }

                if (mb_strlen($questionText) > 65535) {
                    throw new RuntimeException(
                        "CSV row {$rowNumber}: question_text is too long."
                    );
                }

                if (
                    !in_array(
                        $questionType,
                        [
                            'MCQ',
                            'TrueFalse'
                        ],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        "CSV row {$rowNumber}: question_type must be MCQ or TrueFalse."
                    );
                }

                if (
                    $questionType === 'TrueFalse'
                ) {
                    $optionA = 'True';
                    $optionB = 'False';
                    $optionC = null;
                    $optionD = null;

                    if (
                        !in_array(
                            $correctAnswer,
                            [
                                'A',
                                'B'
                            ],
                            true
                        )
                    ) {
                        throw new RuntimeException(
                            "CSV row {$rowNumber}: TrueFalse correct_answer must be A or B."
                        );
                    }
                } else {
                    if (
                        $optionA === '' ||
                        $optionB === '' ||
                        $optionC === '' ||
                        $optionD === ''
                    ) {
                        throw new RuntimeException(
                            "CSV row {$rowNumber}: MCQ requires options A, B, C and D."
                        );
                    }

                    if (
                        !in_array(
                            $correctAnswer,
                            [
                                'A',
                                'B',
                                'C',
                                'D'
                            ],
                            true
                        )
                    ) {
                        throw new RuntimeException(
                            "CSV row {$rowNumber}: MCQ correct_answer must be A, B, C or D."
                        );
                    }
                }

                if ($negativeMarks > $marks) {
                    throw new RuntimeException(
                        "CSV row {$rowNumber}: negative_marks cannot exceed marks."
                    );
                }

                if (
                    !in_array(
                        $difficulty,
                        [
                            'Easy',
                            'Medium',
                            'Hard'
                        ],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        "CSV row {$rowNumber}: difficulty must be Easy, Medium or Hard."
                    );
                }

                if (
                    !in_array(
                        $status,
                        [
                            'Active',
                            'Inactive'
                        ],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        "CSV row {$rowNumber}: status must be Active or Inactive."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Topic must belong to selected subject
                |--------------------------------------------------------------------------
                */

                if ($topicId !== null) {
                    $topicStatement =
                        $conn->prepare(
                            "
                            SELECT
                                id,
                                subject_id,
                                status
                            FROM topics
                            WHERE id = ?
                            LIMIT 1
                            "
                        );

                    $topicStatement->execute([
                        $topicId
                    ]);

                    $topic =
                        $topicStatement->fetch(
                            PDO::FETCH_ASSOC
                        );

                    if (!$topic) {
                        throw new RuntimeException(
                            "CSV row {$rowNumber}: selected topic does not exist."
                        );
                    }

                    if (
                        (int)$topic['subject_id'] !==
                        (int)$subjectId
                    ) {
                        throw new RuntimeException(
                            "CSV row {$rowNumber}: topic does not belong to the selected subject."
                        );
                    }

                    if (
                        (string)$topic['status'] !==
                        'Active'
                    ) {
                        throw new RuntimeException(
                            "CSV row {$rowNumber}: topic is inactive."
                        );
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Duplicate inside CSV
                |--------------------------------------------------------------------------
                */

                $duplicateKey =
                    strtolower(
                        trim(
                            (string)$questionText
                        )
                    );

                if (
                    isset(
                        $duplicateKeys[
                            $duplicateKey
                        ]
                    )
                ) {
                    throw new RuntimeException(
                        "CSV row {$rowNumber}: duplicate question text appears more than once."
                    );
                }

                $duplicateKeys[
                    $duplicateKey
                ] = true;

                $rows[] = [
                    'topic_id' =>
                        $topicId,

                    'question_text' =>
                        $questionText,

                    'question_type' =>
                        $questionType,

                    'option_a' =>
                        $optionA,

                    'option_b' =>
                        $optionB,

                    'option_c' =>
                        $optionC,

                    'option_d' =>
                        $optionD,

                    'correct_answer' =>
                        $correctAnswer,

                    'explanation' =>
                        $explanation !== ''
                            ? $explanation
                            : null,

                    'difficulty' =>
                        $difficulty,

                    'marks' =>
                        $marks,

                    'negative_marks' =>
                        $negativeMarks,

                    'estimated_time_seconds' =>
                        $estimatedTime,

                    'status' =>
                        $status
                ];
            }

            if (!$rows) {
                throw new RuntimeException(
                    'The CSV file contains no question rows.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Database transaction
            |--------------------------------------------------------------------------
            */

            $conn->beginTransaction();

            $existingQuestionStatement =
                $conn->prepare(
                    "
                    SELECT
                        id
                    FROM questions
                    WHERE
                        created_by_teacher_id = ?
                        AND subject_id = ?
                        AND question_text = ?
                    LIMIT 1
                    "
                );

            $insertStatement =
                $conn->prepare(
                    "
                    INSERT INTO questions
                    (
                        subject_id,
                        topic_id,
                        created_by_teacher_id,

                        question_type,
                        question_text,
                        question_image,

                        option_a,
                        option_b,
                        option_c,
                        option_d,

                        correct_answer,
                        explanation,

                        marks,
                        negative_marks,
                        estimated_time_seconds,

                        difficulty,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,

                        ?,
                        ?,
                        NULL,

                        ?,
                        ?,
                        ?,
                        ?,

                        ?,
                        ?,

                        ?,
                        ?,
                        ?,

                        ?,
                        ?
                    )
                    "
                );

            foreach (
                $rows as $row
            ) {
                $existingQuestionStatement->execute([
                    $teacherId,
                    (int)$subjectId,
                    $row['question_text']
                ]);

                $existing =
                    $existingQuestionStatement->fetch(
                        PDO::FETCH_ASSOC
                    );

                if ($existing) {
                    $skipped++;
                    continue;
                }

                $insertStatement->execute([
                    (int)$subjectId,
                    $row['topic_id'],
                    $teacherId,

                    $row['question_type'],
                    $row['question_text'],

                    $row['option_a'],
                    $row['option_b'],
                    $row['option_c'],
                    $row['option_d'],

                    $row['correct_answer'],
                    $row['explanation'],

                    $row['marks'],
                    $row['negative_marks'],
                    $row['estimated_time_seconds'],

                    $row['difficulty'],
                    $row['status']
                ]);

                $imported++;

                if ($firstImportedQuestionId === null) {
                    $firstImportedQuestionId = (int)$conn->lastInsertId();
                }
            }

            if ($imported === 0) {
                $conn->rollBack();

                throw new RuntimeException(
                    'No new questions were imported. All uploaded questions already exist in your question bank.'
                );
            }

            $conn->commit();

            $subjectNameStatement = $conn->prepare(
                'SELECT name FROM subjects WHERE id = ? LIMIT 1'
            );
            $subjectNameStatement->execute([(int)$subjectId]);
            $subjectName = (string)($subjectNameStatement->fetchColumn() ?: '');

            examsphere_event_imported_questions(
                $conn,
                $subjectName,
                (int)$imported,
                $firstImportedQuestionId
            );

            $success =
                $imported .
                ' question(s) imported successfully.';

            if ($skipped > 0) {
                $success .=
                    ' ' .
                    $skipped .
                    ' duplicate question(s) skipped.';
            }

        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

    } catch (Throwable $exception) {

        if (
            isset($conn) &&
            $conn instanceof PDO &&
            $conn->inTransaction()
        ) {
            $conn->rollBack();
        }

        error_log(
            'Teacher CSV question import failed: ' .
            $exception->getMessage()
        );

        $error =
            $exception->getMessage();
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
    Import Questions | ExamSphere
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

:root{
    --earth:#5d4037;
    --earth-dark:#3e2723;
    --olive:#556b2f;
    --cream:#f5f5dc;
    --page:#f6f4ed;
    --text:#3f342e;
    --muted:#857a72;
    --line:#e6ded4;
    --white:#fff;
}

body.portal-body{
    background:
        radial-gradient(
            circle at 10% 10%,
            rgba(168,200,188,.10),
            transparent 25%
        ),
        linear-gradient(
            135deg,
            #f8f6f0,
            #eeeae2
        );
    font-family:'Poppins',sans-serif;
}

.import-page{
    width:min(1180px,100%);
    margin:0 auto;
    padding:18px 0 55px;
}

.import-header{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:20px;
    margin-bottom:20px;
}

.import-kicker{
    font-size:.78rem !important;
    font-weight:800 !important;

    color:var(--olive);
    font-size:.60rem;
    font-weight:800;
    letter-spacing:.14em;
}

.import-header h1{
    font-size:2.45rem !important;
    font-weight:800 !important;

    margin:7px 0 4px;
    color:var(--earth);
    font-size:2rem;
    font-weight:900;
    letter-spacing:-.03em;
}

.import-header p{
    font-size:.84rem !important;
    font-weight:500 !important;

    margin:0;
    color:var(--muted);
    font-size:.74rem;
}

.import-card{
    overflow:hidden;
    border:1px solid rgba(93,64,55,.08);
    border-radius:20px;
    background:rgba(255,255,255,.86);
    box-shadow:
        0 18px 45px rgba(62,45,37,.08);
}

.import-card-head{
    padding:20px 22px;
    border-bottom:1px solid var(--line);
}

.import-card-head h2{
    font-size:1.35rem !important;
    font-weight:800 !important;

    margin:0;
    color:var(--earth);
    font-size:1rem;
    font-weight:850;
}

.import-card-head p{
    font-size:.82rem !important;
    font-weight:500 !important;

    margin:5px 0 0;
    color:var(--muted);
    font-size:.66rem;
}

.import-card-body{
    padding:22px;
}

.import-label{
    font-size:1rem !important;
    font-weight:800 !important;
    color:var(--earth);

    display:block;
    margin-bottom:7px;
    color:var(--earth);
    font-size:.67rem;
    font-weight:800;
}

.import-control{
    font-size:1rem !important;
    font-weight:600 !important;
    min-height:52px !important;

    min-height:44px;
    border-radius:11px;
    border-color:#ddd3ca;
    font-size:.72rem;
}

.import-control:focus{
    border-color:var(--olive);
    box-shadow:
        0 0 0 .2rem rgba(85,107,47,.10);
}

.import-submit{
    font-size:.92rem !important;
    font-weight:800 !important;
    min-height:52px !important;

    min-height:45px;
    border:0;
    border-radius:11px;
    background:var(--earth);
    color:#fff;
    font-size:.72rem;
    font-weight:800;
}

.import-submit:hover{
    background:var(--earth-dark);
    color:#fff;
}

.import-back{
    font-size:.82rem !important;
    font-weight:800 !important;

    border-radius:10px;
    font-size:.68rem;
    font-weight:700;
}

.format-box{
    padding:15px;
    border-radius:13px;
    border:1px solid var(--line);
    background:#faf7f0;
}

.format-box code{
    font-size:.76rem !important;
    font-weight:700 !important;

    display:block;
    overflow-x:auto;
    white-space:nowrap;
    color:var(--earth);
    font-size:.58rem;
    line-height:1.7;
}

.sample-box{
    margin-top:14px;
    padding:14px;
    border-radius:13px;
    background:#211c19;
    color:#f2eee8;
    overflow:auto;
}

.sample-box pre{
    font-size:.75rem !important;
    font-weight:600 !important;

    margin:0;
    color:#f2eee8;
    font-size:.57rem;
    line-height:1.7;
}

.rule-grid{
    display:grid;
    grid-template-columns:
        repeat(3,minmax(0,1fr));
    gap:12px;
}

.rule-item{
    padding:15px;
    border:1px solid var(--line);
    border-radius:14px;
    background:#fcfaf6;
}

.rule-item i{
    color:var(--olive);
    margin-bottom:8px;
}

.rule-item strong{
    font-size:.88rem !important;
    font-weight:800 !important;

    display:block;
    color:var(--earth);
    font-size:.68rem;
}

.rule-item span{
    font-size:.74rem !important;
    font-weight:500 !important;
    line-height:1.7 !important;

    display:block;
    margin-top:4px;
    color:var(--muted);
    font-size:.58rem;
    line-height:1.6;
}

.alert{
    font-size:.82rem !important;
    font-weight:700 !important;

    border-radius:12px;
    font-size:.70rem;
}

.upload-note{
    font-size:.78rem !important;
    font-weight:500 !important;

    color:var(--muted);
    font-size:.58rem;
    line-height:1.55;
}

@media(max-width:900px){

    .import-header{
        align-items:flex-start;
        flex-direction:column;
    }

    .rule-grid{
        grid-template-columns:1fr 1fr;
    }

}

@media(max-width:600px){

    .import-page{
        padding-left:7px;
        padding-right:7px;
    }

    .import-header h1{
        font-size:1.55rem;
    }

    .rule-grid{
        grid-template-columns:1fr;
    }

}



/* BIG + BOLD READABILITY UPGRADE — layout preserved */
.import-page,.import-page *{letter-spacing:.01em;}
.import-page h1,.import-page h2,.import-page h3,.import-page strong{font-weight:800;}
.import-page p,.import-page span,.import-page label,.import-page small{line-height:1.65;}
.import-page input,.import-page select,.import-page button{font-family:'Poppins',sans-serif;}
@media(max-width:600px){.import-header h1{font-size:2rem !important}.import-card-head h2{font-size:1.15rem !important}.import-label{font-size:.92rem !important}.import-control{font-size:.92rem !important;min-height:50px !important}.import-submit{font-size:.88rem !important}.rule-item span{font-size:.72rem !important}.upload-note{font-size:.74rem !important}}
</style>

</head>

<body class="portal-body">

<div class="portal-layout">

<?php include 'includes/sidebar.php'; ?>

<main class="portal-main">

<div class="import-page">

<header class="import-header">

<div>

<span class="import-kicker">

<i class="fa-solid fa-file-csv me-1"></i>

TEACHER QUESTION BANK

</span>

<h1>
    Import Questions
</h1>

<p>
    Bulk upload questions directly into your Question Bank.
    Exams are connected later through the Exam Questions builder.
</p>

</div>

<a
    href="questions.php"
    class="btn btn-outline-secondary import-back"
>

<i class="fa-solid fa-arrow-left me-1"></i>

Question Bank

</a>

</header>


<?php if ($success !== ''): ?>

<div
    class="alert alert-success mb-3"
>

<i
    class="fa-solid fa-circle-check me-2"
></i>

<?= teacher_import_e(
    $success
) ?>

</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div
    class="alert alert-danger mb-3"
>

<i
    class="fa-solid fa-circle-exclamation me-2"
></i>

<?= teacher_import_e(
    $error
) ?>

</div>

<?php endif; ?>


<div class="row g-4">


<div class="col-lg-7">

<section class="import-card">

<div class="import-card-head">

<h2>

Upload CSV

</h2>

<p>

Select a subject and import any valid number of questions.

</p>

</div>


<div class="import-card-body">


<?php if (!$subjects): ?>

<div class="alert alert-warning">

No active subject is available.
Please create or activate a subject first.

</div>

<?php else: ?>


<form
    method="post"
    enctype="multipart/form-data"
>

<?= csrf_field() ?>


<div class="mb-3">

<label
    class="import-label"
    for="subject_id"
>

Subject *

</label>

<select
    id="subject_id"
    name="subject_id"
    class="form-select import-control"
    required
>

<option value="">

Select subject

</option>

<?php foreach (
    $subjects as $subject
): ?>

<option
    value="<?= (int)$subject['id'] ?>"
>

<?= teacher_import_e(
    $subject['name']
) ?>

<?php if (
    !empty(
        $subject['code']
    )
): ?>

(
<?= teacher_import_e(
    $subject['code']
) ?>
)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="mb-3">

<label
    class="import-label"
    for="questions_csv"
>

CSV File *

</label>

<input
    id="questions_csv"
    name="questions_csv"
    class="form-control import-control"
    type="file"
    accept=".csv,text/csv"
    required
>

<div class="upload-note mt-2">

Maximum file size: 5 MB.

The CSV can contain any number of valid question rows.
There is no fixed 50/51 question restriction here.

</div>

</div>


<button
    class="btn import-submit w-100"
    type="submit"
>

<i
    class="fa-solid fa-file-arrow-up me-1"
></i>

Import Questions

</button>


</form>

<?php endif; ?>


</div>

</section>

</div>


<div class="col-lg-5">

<section class="import-card">

<div class="import-card-head">

<h2>

Official CSV Format

</h2>

<p>

Use this exact header order.

</p>

</div>


<div class="import-card-body">


<div class="format-box">

<code>topic_id,question_text,question_type,option_a,option_b,option_c,option_d,correct_answer,explanation,difficulty,marks,negative_marks,estimated_time_seconds,status</code>

</div>


<div class="sample-box">

<pre>topic_id,question_text,question_type,option_a,option_b,option_c,option_d,correct_answer,explanation,difficulty,marks,negative_marks,estimated_time_seconds,status
12,"What is 2 + 2?","MCQ","3","4","5","6","B","Basic arithmetic.","Easy",1,0,30,"Active"</pre>

</div>


<p
    class="upload-note mt-3 mb-0"
>

For True/False questions,
use question_type = TrueFalse and
correct_answer = A or B.

</p>


</div>

</section>

</div>


<div class="col-12">

<section class="import-card">

<div class="import-card-head">

<h2>

Import Rules

</h2>

<p>

Questions are stored in the teacher-owned Question Bank.
Exam assignment remains a separate step.

</p>

</div>


<div class="import-card-body">

<div class="rule-grid">


<div class="rule-item">

<i class="fa-solid fa-layer-group"></i>

<strong>
Subject & Topic
</strong>

<span>
Every question belongs to the selected active subject.
A topic, when provided, must belong to that subject and be active.
</span>

</div>


<div class="rule-item">

<i class="fa-solid fa-calculator"></i>

<strong>
Dynamic Marks
</strong>

<span>
Each question can have its own marks and negative marks.
Exam-level total marks are validated later when questions are assigned.
</span>

</div>


<div class="rule-item">

<i class="fa-solid fa-shield-halved"></i>

<strong>
Secure Import
</strong>

<span>
CSRF verification, file validation, row validation, duplicate checks
and transactional database insertion are applied.
</span>

</div>


</div>

</div>

</section>

</div>


</div>

</div>

</main>

</div>

</body>

</html>
