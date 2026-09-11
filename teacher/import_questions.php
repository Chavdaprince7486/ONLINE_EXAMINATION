<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'teacher'
) {
    header('Location: ../auth/login.php');
    exit;
}

$teacherId = (int)$_SESSION['user_id'];

$message = '';
$error = '';

$subjects = [];


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function teacher_import_escape(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Load active subjects
|--------------------------------------------------------------------------
*/

try {

    $subjectStatement = $conn->query("
        SELECT
            id,
            name
        FROM subjects
        WHERE status = 'Active'
        ORDER BY
            name ASC,
            id ASC
    ");

    $subjects = $subjectStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $exception) {

    error_log(
        'Teacher CSV subject loading failed: ' .
        $exception->getMessage()
    );

    $error =
        'Unable to load subjects.';
}


/*
|--------------------------------------------------------------------------
| POST IMPORT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? null
        )
    ) {

        $error =
            'Security verification failed. Please refresh the page and try again.';

    } else {

        $upload =
            $_FILES['questions_csv'] ?? null;

        if (
            !is_array($upload)
        ) {

            $error =
                'Please select a CSV file.';
        }

        elseif (
            ($upload['error'] ?? UPLOAD_ERR_NO_FILE)
            !== UPLOAD_ERR_OK
        ) {

            $error =
                'The CSV upload failed. Please try again.';
        }

        else {

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

            $extension =
                strtolower(
                    pathinfo(
                        $originalName,
                        PATHINFO_EXTENSION
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | File checks
            |--------------------------------------------------------------------------
            */

            if (
                $tmpName === '' ||
                !is_uploaded_file($tmpName)
            ) {

                $error =
                    'Invalid uploaded file.';
            }

            elseif (
                $extension !== 'csv'
            ) {

                $error =
                    'Only CSV files are allowed.';
            }

            elseif (
                $fileSize <= 0
            ) {

                $error =
                    'The uploaded CSV file is empty.';
            }

            elseif (
                $fileSize > 5 * 1024 * 1024
            ) {

                $error =
                    'Maximum CSV file size is 5 MB.';
            }

            else {

                $handle =
                    fopen(
                        $tmpName,
                        'rb'
                    );


                if (
                    $handle === false
                ) {

                    $error =
                        'Unable to read the uploaded CSV file.';
                }

                else {

                    try {

                        /*
                        |--------------------------------------------------------------------------
                        | Expected canonical format
                        |--------------------------------------------------------------------------
                        */

                        $expectedColumns = [

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


                        /*
                        |--------------------------------------------------------------------------
                        | Subject
                        |--------------------------------------------------------------------------
                        |
                        | Teacher selects the subject separately.
                        |
                        */

                        $subjectId =
                            filter_var(
                                $_POST['subject_id'] ?? '',
                                FILTER_VALIDATE_INT
                            );


                        if (
                            $subjectId === false ||
                            $subjectId === null ||
                            $subjectId <= 0
                        ) {

                            throw new RuntimeException(
                                'Please select a valid subject.'
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Validate subject
                        |--------------------------------------------------------------------------
                        */

                        $subjectCheck =
                            $conn->prepare("
                                SELECT
                                    id,
                                    name,
                                    status
                                FROM subjects
                                WHERE id = ?
                                LIMIT 1
                            ");

                        $subjectCheck->execute([
                            (int)$subjectId
                        ]);

                        $subject =
                            $subjectCheck->fetch(
                                PDO::FETCH_ASSOC
                            );


                        if (
                            !$subject
                        ) {

                            throw new RuntimeException(
                                'Selected subject does not exist.'
                            );
                        }


                        if (
                            (string)$subject['status']
                            !== 'Active'
                        ) {

                            throw new RuntimeException(
                                'Selected subject is inactive.'
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Read CSV header
                        |--------------------------------------------------------------------------
                        */

                        $header =
                            fgetcsv(
                                $handle,
                                0,
                                ','
                            );


                        if (
                            $header === false
                        ) {

                            throw new RuntimeException(
                                'CSV file must contain a header row.'
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Normalize header
                        |--------------------------------------------------------------------------
                        */

                        $normalizedHeader = [];


                        foreach (
                            $header
                            as $column
                        ) {

                            $column =
                                preg_replace(
                                    '/^\xEF\xBB\xBF/',
                                    '',
                                    (string)$column
                                );

                            $normalizedHeader[] =
                                strtolower(
                                    trim(
                                        (string)$column
                                    )
                                );
                        }


                        if (
                            $normalizedHeader !==
                            $expectedColumns
                        ) {

                            throw new RuntimeException(
                                'Invalid CSV header. Please download and use the current sample format.'
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Read rows
                        |--------------------------------------------------------------------------
                        */

                        $rows = [];

                        $lineNumber = 1;

                        $csvDuplicateKeys = [];


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

                            $lineNumber++;


                            /*
                            |--------------------------------------------------------------------------
                            | Skip blank rows
                            |--------------------------------------------------------------------------
                            */

                            $nonEmpty =
                                array_filter(
                                    $data,
                                    static function (
                                        mixed $value
                                    ): bool {

                                        return trim(
                                            (string)$value
                                        ) !== '';

                                    }
                                );


                            if (
                                empty($nonEmpty)
                            ) {

                                continue;
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Exact column count
                            |--------------------------------------------------------------------------
                            */

                            if (
                                count($data) !==
                                count($expectedColumns)
                            ) {

                                throw new RuntimeException(
                                    "CSV row {$lineNumber} must contain exactly " .
                                    count($expectedColumns) .
                                    " columns."
                                );
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Extract
                            |--------------------------------------------------------------------------
                            */

                            $topicIdRaw =
                                trim(
                                    (string)$data[0]
                                );


                            $topicId =
                                null;


                            if (
                                $topicIdRaw !== ''
                            ) {

                                $topicId =
                                    filter_var(
                                        $topicIdRaw,
                                        FILTER_VALIDATE_INT
                                    );


                                if (
                                    $topicId === false ||
                                    $topicId <= 0
                                ) {

                                    throw new RuntimeException(
                                        "CSV row {$lineNumber}: topic_id must be a positive integer or blank."
                                    );
                                }
                            }


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
                                filter_var(
                                    trim(
                                        (string)$data[10]
                                    ),
                                    FILTER_VALIDATE_FLOAT
                                );


                            $negativeMarks =
                                filter_var(
                                    trim(
                                        (string)$data[11]
                                    ),
                                    FILTER_VALIDATE_FLOAT
                                );


                            $estimatedTimeRaw =
                                trim(
                                    (string)$data[12]
                                );


                            $estimatedTime =
                                null;


                            if (
                                $estimatedTimeRaw !== ''
                            ) {

                                $estimatedTime =
                                    filter_var(
                                        $estimatedTimeRaw,
                                        FILTER_VALIDATE_INT
                                    );


                                if (
                                    $estimatedTime === false ||
                                    $estimatedTime <= 0
                                ) {

                                    throw new RuntimeException(
                                        "CSV row {$lineNumber}: estimated_time_seconds must be greater than zero."
                                    );
                                }
                            }


                            $status =
                                ucfirst(
                                    strtolower(
                                        trim(
                                            (string)$data[13]
                                        )
                                    )
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | Question validation
                            |--------------------------------------------------------------------------
                            */

                            if (
                                $questionText === ''
                            ) {

                                throw new RuntimeException(
                                    "CSV row {$lineNumber}: question_text is required."
                                );
                            }


                            if (
                                mb_strlen($questionText) > 65535
                            ) {

                                throw new RuntimeException(
                                    "CSV row {$lineNumber}: question_text is too long."
                                );
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Question type
                            |--------------------------------------------------------------------------
                            */

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
                                    "CSV row {$lineNumber}: question_type must be MCQ or TrueFalse."
                                );
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | MCQ / TrueFalse
                            |--------------------------------------------------------------------------
                            */

                            if (
                                $questionType === 'TrueFalse'
                            ) {

                                $optionA =
                                    'True';

                                $optionB =
                                    'False';

                                $optionC =
                                    null;

                                $optionD =
                                    null;


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
                                        "CSV row {$lineNumber}: TrueFalse correct_answer must be A or B."
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
                                        "CSV row {$lineNumber}: MCQ requires options A, B, C and D."
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
                                        "CSV row {$lineNumber}: MCQ correct_answer must be A, B, C or D."
                                    );
                                }
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Marks
                            |--------------------------------------------------------------------------
                            */

                            if (
                                $marks === false ||
                                $marks === null ||
                                $marks <= 0
                            ) {

                                throw new RuntimeException(
                                    "CSV row {$lineNumber}: marks must be greater than zero."
                                );
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Negative marks
                            |--------------------------------------------------------------------------
                            */

                            if (
                                $negativeMarks === false ||
                                $negativeMarks === null ||
                                $negativeMarks < 0
                            ) {

                                throw new RuntimeException(
                                    "CSV row {$lineNumber}: negative_marks cannot be negative."
                                );
                            }


                            if (
                                $negativeMarks > $marks
                            ) {

                                throw new RuntimeException(
                                    "CSV row {$lineNumber}: negative_marks cannot exceed marks."
                                );
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Difficulty
                            |--------------------------------------------------------------------------
                            */

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
                                    "CSV row {$lineNumber}: difficulty must be Easy, Medium or Hard."
                                );
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Status
                            |--------------------------------------------------------------------------
                            */

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
                                    "CSV row {$lineNumber}: status must be Active or Inactive."
                                );
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Topic validation
                            |--------------------------------------------------------------------------
                            */

                            if (
                                $topicId !== null
                            ) {

                                $topicCheck =
                                    $conn->prepare("
                                        SELECT
                                            id,
                                            subject_id,
                                            status
                                        FROM topics
                                        WHERE id = ?
                                        LIMIT 1
                                    ");

                                $topicCheck->execute([
                                    (int)$topicId
                                ]);

                                $topic =
                                    $topicCheck->fetch(
                                        PDO::FETCH_ASSOC
                                    );


                                if (
                                    !$topic
                                ) {

                                    throw new RuntimeException(
                                        "CSV row {$lineNumber}: topic {$topicId} does not exist."
                                    );
                                }


                                if (
                                    (int)$topic['subject_id'] !==
                                    (int)$subjectId
                                ) {

                                    throw new RuntimeException(
                                        "CSV row {$lineNumber}: topic {$topicId} does not belong to the selected subject."
                                    );
                                }


                                if (
                                    (string)$topic['status']
                                    !== 'Active'
                                ) {

                                    throw new RuntimeException(
                                        "CSV row {$lineNumber}: topic {$topicId} is inactive."
                                    );
                                }
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Duplicate in CSV
                            |--------------------------------------------------------------------------
                            */

                            $duplicateKey =
                                md5(
                                    (
                                        (string)$subjectId
                                        . '|'
                                        . (string)(
                                            $topicId ?? ''
                                        )
                                        . '|'
                                        . mb_strtolower(
                                            $questionText
                                        )
                                    )
                                );


                            if (
                                isset(
                                    $csvDuplicateKeys[
                                        $duplicateKey
                                    ]
                                )
                            ) {

                                throw new RuntimeException(
                                    "CSV row {$lineNumber}: duplicate question appears earlier in this file."
                                );
                            }


                            $csvDuplicateKeys[
                                $duplicateKey
                            ] = true;


                            /*
                            |--------------------------------------------------------------------------
                            | Prepare row
                            |--------------------------------------------------------------------------
                            */

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
                                    $explanation,

                                'difficulty' =>
                                    $difficulty,

                                'marks' =>
                                    (float)$marks,

                                'negative_marks' =>
                                    (float)$negativeMarks,

                                'estimated_time_seconds' =>
                                    $estimatedTime !== null
                                        ? (int)$estimatedTime
                                        : null,

                                'status' =>
                                    $status

                            ];
                        }


                        if (
                            empty($rows)
                        ) {

                            throw new RuntimeException(
                                'The CSV file contains no question rows.'
                            );
                        }


                        fclose(
                            $handle
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | Duplicate check + INSERT transaction
                        |--------------------------------------------------------------------------
                        */

                        $conn->beginTransaction();


                        $duplicateDbCheck =
                            $conn->prepare("
                                SELECT
                                    id
                                FROM questions
                                WHERE
                                    created_by_teacher_id = ?
                                    AND question_text = ?
                                LIMIT 1
                            ");


                        $insert =
                            $conn->prepare("
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
                            ");


                        $imported = 0;

                        $skipped = 0;


                        foreach (
                            $rows
                            as $rowIndex => $row
                        ) {

                            $duplicateDbCheck->execute([

                                $teacherId,

                                $row[
                                    'question_text'
                                ]

                            ]);


                            if (
                                $duplicateDbCheck->fetch(
                                    PDO::FETCH_ASSOC
                                )
                            ) {

                                $skipped++;

                                continue;
                            }


                            $insert->execute([

                                (int)$subjectId,

                                $row[
                                    'topic_id'
                                ],

                                $teacherId,

                                $row[
                                    'question_type'
                                ],

                                $row[
                                    'question_text'
                                ],

                                $row[
                                    'option_a'
                                ],

                                $row[
                                    'option_b'
                                ],

                                $row[
                                    'option_c'
                                ],

                                $row[
                                    'option_d'
                                ],

                                $row[
                                    'correct_answer'
                                ],

                                (
                                    $row['explanation'] !== ''
                                        ? $row['explanation']
                                        : null
                                ),

                                $row[
                                    'marks'
                                ],

                                $row[
                                    'negative_marks'
                                ],

                                $row[
                                    'estimated_time_seconds'
                                ],

                                $row[
                                    'difficulty'
                                ],

                                $row[
                                    'status'
                                ]

                            ]);


                            $imported++;
                        }


                        if (
                            $imported === 0
                        ) {

                            $conn->rollBack();

                            throw new RuntimeException(
                                'No new questions were imported. All questions already exist in your question bank.'
                            );
                        }


                        $conn->commit();


                        $message =
                            $imported .
                            ' question(s) imported successfully.';


                        if (
                            $skipped > 0
                        ) {

                            $message .=
                                ' ' .
                                $skipped .
                                ' duplicate question(s) skipped.';
                        }

                    } catch (Throwable $exception) {

                        if (
                            $conn->inTransaction()
                        ) {

                            $conn->rollBack();
                        }


                        if (
                            is_resource($handle)
                        ) {

                            fclose(
                                $handle
                            );
                        }


                        error_log(
                            'Teacher CSV import failed: ' .
                            $exception->getMessage()
                        );


                        $error =
                            $exception->getMessage();
                    }
                }
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Import Questions | ExamSphere
    </title>

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

        .import-page {
            max-width: 1150px;
            margin: 0 auto;
        }

        .import-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .import-header h1 {
            margin: 0;
            font-weight: 900;
            letter-spacing: -.03em;
        }

        .import-header p {
            margin: 7px 0 0;
            color: #746d68;
        }

        .import-card {
            border: 1px solid rgba(93,64,55,.08);
            border-radius: 22px;
            background: rgba(255,255,255,.82);
            box-shadow:
                0 18px 45px rgba(62,45,37,.08);
            overflow: hidden;
        }

        .import-card-head {
            padding: 22px 24px;
            border-bottom: 1px solid #eee7df;
        }

        .import-card-head h2 {
            margin: 0;
            color: #5d4037;
            font-weight: 900;
            font-size: 1.12rem;
        }

        .import-card-head p {
            margin: 5px 0 0;
            color: #746d68;
            font-size: .86rem;
        }

        .import-card-body {
            padding: 24px;
        }

        .import-label {
            display: block;
            margin-bottom: 7px;
            color: #5d4037;
            font-size: .82rem;
            font-weight: 850;
        }

        .import-control {
            min-height: 47px;
            border-radius: 12px;
            border-color: #ddd3ca;
        }

        .import-control:focus {
            border-color: #556b2f;
            box-shadow:
                0 0 0 .2rem rgba(85,107,47,.10);
        }

        .import-submit {
            min-height: 48px;
            border: 0;
            border-radius: 12px;
            background: #5d4037;
            color: #fff;
            font-weight: 850;
        }

        .import-submit:hover {
            background: #4e352e;
            color: #fff;
        }

        .format-box {
            padding: 17px;
            border-radius: 14px;
            background: #faf7f0;
            border: 1px solid #ebe1d8;
        }

        .format-box code {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
            color: #5d4037;
            font-size: .77rem;
        }

        .format-note {
            margin-top: 14px;
            color: #746d68;
            font-size: .84rem;
            line-height: 1.65;
        }

        .rules {
            margin: 0;
            padding-left: 20px;
            color: #746d68;
            line-height: 1.75;
            font-size: .88rem;
        }

        .rules strong {
            color: #5d4037;
        }

        .alert {
            border-radius: 13px;
        }

    </style>

</head>

<body class="portal-body">

<div class="portal-layout">

    <?php include 'includes/sidebar.php'; ?>

    <main class="portal-main">

        <div class="import-page">

            <header class="import-header">

                <div>

                    <h1>
                        Import Questions
                    </h1>

                    <p>
                        Bulk-import MCQ and True/False questions into your teacher question bank.
                    </p>

                </div>

                <a
                    href="questions.php"
                    class="btn btn-outline-secondary"
                >
                    <i class="fa-solid fa-circle-question me-1"></i>
                    Question Bank
                </a>

            </header>

            <?php if ($message !== ''): ?>

                <div class="alert alert-success">

                    <i class="fa-solid fa-circle-check me-1"></i>

                    <?= teacher_import_escape(
                        $message
                    ) ?>

                </div>

            <?php endif; ?>

            <?php if ($error !== ''): ?>

                <div class="alert alert-danger">

                    <i class="fa-solid fa-circle-exclamation me-1"></i>

                    <?= teacher_import_escape(
                        $error
                    ) ?>

                </div>

            <?php endif; ?>

            <div class="row g-4">

                <div class="col-lg-6">

                    <section class="import-card">

                        <div class="import-card-head">

                            <h2>
                                Upload CSV
                            </h2>

                            <p>
                                The selected subject applies to every imported question.
                            </p>

                        </div>

                        <div class="import-card-body">

                            <?php if (!$subjects): ?>

                                <div class="alert alert-warning">

                                    No active subject is available.

                                    Create or activate a subject before importing questions.

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
                                            class="form-select import-control"
                                            id="subject_id"
                                            name="subject_id"
                                            required
                                        >

                                            <option value="">
                                                Select subject
                                            </option>

                                            <?php foreach ($subjects as $subject): ?>

                                                <option
                                                    value="<?= (int)$subject['id'] ?>"
                                                >

                                                    <?= teacher_import_escape(
                                                        $subject['name']
                                                    ) ?>

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
                                            class="form-control import-control"
                                            id="questions_csv"
                                            name="questions_csv"
                                            type="file"
                                            accept=".csv,text/csv"
                                            required
                                        >

                                        <div class="form-text">
                                            Maximum file size: 5 MB
                                        </div>

                                    </div>

                                    <button
                                        type="submit"
                                        class="btn import-submit w-100"
                                    >

                                        <i
                                            class="fa-solid fa-file-import me-1"
                                        ></i>

                                        Import Questions

                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </section>

                </div>

                <div class="col-lg-6">

                    <section class="import-card">

                        <div class="import-card-head">

                            <h2>
                                Official CSV Format
                            </h2>

                            <p>
                                Use this exact column order.
                            </p>

                        </div>

                        <div class="import-card-body">

                            <div class="format-box">

                                <code>
topic_id,question_text,question_type,option_a,option_b,option_c,option_d,correct_answer,explanation,difficulty,marks,negative_marks,estimated_time_seconds,status
                                </code>

                            </div>

                            <div class="format-note">

                                <strong>
                                    Topic:
                                </strong>
                                use a topic ID belonging to the selected subject,
                                or leave it blank.

                                <br>

                                <strong>
                                    Question Type:
                                </strong>
                                <code>MCQ</code>
                                or
                                <code>TrueFalse</code>.

                                <br>

                                <strong>
                                    Correct Answer:
                                </strong>
                                A/B/C/D for MCQ and A/B for True/False.

                            </div>

                        </div>

                    </section>

                </div>

                <div class="col-12">

                    <section class="import-card">

                        <div class="import-card-head">

                            <h2>
                                Import Rules
                            </h2>

                        </div>

                        <div class="import-card-body">

                            <ul class="rules">

                                <li>
                                    <strong>Subject:</strong>
                                    All imported questions belong to the selected active subject.
                                </li>

                                <li>
                                    <strong>Topic:</strong>
                                    Topic IDs must belong to that selected subject and must be active.
                                </li>

                                <li>
                                    <strong>Duplicates:</strong>
                                    Existing questions created by this teacher are skipped.
                                </li>

                                <li>
                                    <strong>Marks:</strong>
                                    Marks must be greater than zero and negative marks cannot exceed marks.
                                </li>

                                <li>
                                    <strong>Status:</strong>
                                    Only Active or Inactive is accepted.
                                </li>

                                <li>
                                    <strong>Difficulty:</strong>
                                    Easy, Medium or Hard.
                                </li>

                                <li>
                                    <strong>Safety:</strong>
                                    If the import fails during database processing, the transaction is rolled back.
                                </li>

                            </ul>

                        </div>

                    </section>

                </div>

            </div>

        </div>

    </main>

</div>

</body>

</html>