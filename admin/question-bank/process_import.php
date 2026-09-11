<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {

    header(
        "Location: ../../auth/login.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Request method
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    header(
        "Location: import.php"
    );

    exit;
}


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

    $_SESSION['error'] =
        "Security verification failed. Please try again.";

    header(
        "Location: import.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| File validation
|--------------------------------------------------------------------------
*/

if (
    !isset(
        $_FILES['import_file']
    )
) {

    $_SESSION['error'] =
        "Please select a CSV file.";

    header(
        "Location: import.php"
    );

    exit;
}


$file =
    $_FILES['import_file'];


if (
    !is_array($file) ||
    ($file['error'] ?? UPLOAD_ERR_NO_FILE) !==
        UPLOAD_ERR_OK
) {

    $_SESSION['error'] =
        "The CSV upload failed. Please select a valid file.";

    header(
        "Location: import.php"
    );

    exit;
}


$tmpName =
    (string)(
        $file['tmp_name']
        ?? ''
    );


$originalName =
    (string)(
        $file['name']
        ?? ''
    );


$fileSize =
    (int)(
        $file['size']
        ?? 0
    );


if (
    $tmpName === '' ||
    !is_uploaded_file($tmpName)
) {

    $_SESSION['error'] =
        "Invalid uploaded file.";

    header(
        "Location: import.php"
    );

    exit;
}


$extension =
    strtolower(
        pathinfo(
            $originalName,
            PATHINFO_EXTENSION
        )
    );


if (
    $extension !== 'csv'
) {

    $_SESSION['error'] =
        "Only CSV files are allowed.";

    header(
        "Location: import.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| 5 MB maximum
|--------------------------------------------------------------------------
*/

$maxFileSize =
    5 * 1024 * 1024;


if (
    $fileSize <= 0
) {

    $_SESSION['error'] =
        "The uploaded CSV file is empty.";

    header(
        "Location: import.php"
    );

    exit;
}


if (
    $fileSize > $maxFileSize
) {

    $_SESSION['error'] =
        "Maximum CSV file size is 5 MB.";

    header(
        "Location: import.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Open CSV
|--------------------------------------------------------------------------
*/

$handle =
    fopen(
        $tmpName,
        'rb'
    );


if (
    $handle === false
) {

    $_SESSION['error'] =
        "Unable to open the CSV file.";

    header(
        "Location: import.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Expected CSV structure
|--------------------------------------------------------------------------
|
| Required order:
|
| exam_id
| topic_id
| question_text
| question_type
| option_a
| option_b
| option_c
| option_d
| correct_answer
| explanation
| difficulty
| marks
| negative_marks
| estimated_time_seconds
| status
| position
|
|--------------------------------------------------------------------------
*/

$expectedColumns = [

    'exam_id',

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

    'status',

    'position'

];


/*
|--------------------------------------------------------------------------
| Header
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

    fclose(
        $handle
    );

    $_SESSION['error'] =
        "CSV file must contain a header row.";

    header(
        "Location: import.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Normalize header
|--------------------------------------------------------------------------
*/

$normalizedHeader =
    [];


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

    fclose(
        $handle
    );

    $_SESSION['error'] =
        "Invalid CSV header. Please use the supplied sample format.";

    header(
        "Location: import.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Counters
|--------------------------------------------------------------------------
*/

$imported = 0;

$skipped = 0;

$errors = [];

$rowNumber = 1;


/*
|--------------------------------------------------------------------------
| Running counters
|--------------------------------------------------------------------------
|
| Important:
|
| We cannot only check the database count for every row.
| Multiple Active rows in the SAME CSV must also count
| against the exam's configured question limit.
|
|--------------------------------------------------------------------------
*/

$runningActiveCounts = [];


/*
|--------------------------------------------------------------------------
| Running positions
|--------------------------------------------------------------------------
|
| Prevent the same CSV from inserting the same exam position twice.
|
|--------------------------------------------------------------------------
*/

$runningPositions = [];


/*
|--------------------------------------------------------------------------
| Cached exams
|--------------------------------------------------------------------------
*/

$examCache = [];


/*
|--------------------------------------------------------------------------
| Cached topics
|--------------------------------------------------------------------------
*/

$topicCache = [];


/*
|--------------------------------------------------------------------------
| Transaction
|--------------------------------------------------------------------------
*/

try {

    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Reusable statements
    |--------------------------------------------------------------------------
    */

    $examStatement =
        $conn->prepare("
            SELECT

                e.id,
                e.subject_id,
                e.required_question_count,
                e.status,

                s.status AS subject_status

            FROM exams e

            LEFT JOIN subjects s
                ON s.id = e.subject_id

            WHERE
                e.id = ?

            LIMIT 1
        ");


    $topicStatement =
        $conn->prepare("
            SELECT

                t.id,
                t.subject_id,
                t.name,
                t.status

            FROM topics t

            WHERE
                t.id = ?

            LIMIT 1
        ");


    $activeCountStatement =
        $conn->prepare("
            SELECT
                COUNT(*)

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE
                eq.exam_id = ?

                AND q.status = 'Active'
        ");


    $positionStatement =
        $conn->prepare("
            SELECT
                question_id

            FROM exam_questions

            WHERE
                exam_id = ?

                AND position = ?

            LIMIT 1
        ");


    $duplicateStatement =
        $conn->prepare("
            SELECT
                q.id

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE
                eq.exam_id = ?

                AND q.question_text = ?

            LIMIT 1
        ");


    /*
    |--------------------------------------------------------------------------
    | Duplicate within the CSV itself
    |--------------------------------------------------------------------------
    */

    $csvDuplicateKeys = [];


    /*
    |--------------------------------------------------------------------------
    | Insert question
    |--------------------------------------------------------------------------
    */

    $insertQuestion =
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
                NULL,

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


    /*
    |--------------------------------------------------------------------------
    | Assign question to exam
    |--------------------------------------------------------------------------
    */

    $insertAssignment =
        $conn->prepare("
            INSERT INTO exam_questions
            (
                exam_id,
                question_id,
                position
            )
            VALUES
            (
                ?,
                ?,
                ?
            )
        ");


    /*
    |--------------------------------------------------------------------------
    | Read rows
    |--------------------------------------------------------------------------
    */

    while (
        (
            $row =
                fgetcsv(
                    $handle,
                    0,
                    ','
                )
        ) !== false
    ) {

        $rowNumber++;


        /*
        |--------------------------------------------------------------------------
        | Completely empty row
        |--------------------------------------------------------------------------
        */

        $nonEmpty =
            array_filter(
                $row,
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
            count($row) !==
            count($expectedColumns)
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: expected " .
                count($expectedColumns) .
                " columns, found " .
                count($row) .
                ".";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Extract fields
        |--------------------------------------------------------------------------
        */

        $examId =
            filter_var(
                trim(
                    (string)$row[0]
                ),
                FILTER_VALIDATE_INT
            );


        $topicIdRaw =
            trim(
                (string)$row[1]
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

                $skipped++;

                $errors[] =
                    "Row {$rowNumber}: topic_id must be a valid positive integer or blank.";

                continue;
            }
        }


        $questionText =
            trim(
                (string)$row[2]
            );


        $questionType =
            trim(
                (string)$row[3]
            );


        $optionA =
            trim(
                (string)$row[4]
            );


        $optionB =
            trim(
                (string)$row[5]
            );


        $optionC =
            trim(
                (string)$row[6]
            );


        $optionD =
            trim(
                (string)$row[7]
            );


        $correctAnswer =
            strtoupper(
                trim(
                    (string)$row[8]
                )
            );


        $explanation =
            trim(
                (string)$row[9]
            );


        $difficulty =
            ucfirst(
                strtolower(
                    trim(
                        (string)$row[10]
                    )
                )
            );


        $marks =
            filter_var(
                trim(
                    (string)$row[11]
                ),
                FILTER_VALIDATE_FLOAT
            );


        $negativeMarks =
            filter_var(
                trim(
                    (string)$row[12]
                ),
                FILTER_VALIDATE_FLOAT
            );


        $estimatedTimeRaw =
            trim(
                (string)$row[13]
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

                $skipped++;

                $errors[] =
                    "Row {$rowNumber}: estimated_time_seconds must be greater than zero when provided.";

                continue;
            }
        }


        $status =
            ucfirst(
                strtolower(
                    trim(
                        (string)$row[14]
                    )
                )
            );


        $position =
            filter_var(
                trim(
                    (string)$row[15]
                ),
                FILTER_VALIDATE_INT
            );


        /*
        |--------------------------------------------------------------------------
        | Exam validation
        |--------------------------------------------------------------------------
        */

        if (
            $examId === false ||
            $examId <= 0
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: invalid exam_id.";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Required text fields
        |--------------------------------------------------------------------------
        */

        if (
            $questionText === ''
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: question_text is required.";

            continue;
        }


        if (
            mb_strlen($questionText) > 65535
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: question_text is too long.";

            continue;
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

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: question_type must be MCQ or TrueFalse.";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Correct answer / options
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

                $skipped++;

                $errors[] =
                    "Row {$rowNumber}: TrueFalse correct_answer must be A or B.";

                continue;
            }

        } else {

            if (
                $optionA === '' ||
                $optionB === '' ||
                $optionC === '' ||
                $optionD === ''
            ) {

                $skipped++;

                $errors[] =
                    "Row {$rowNumber}: MCQ requires options A, B, C and D.";

                continue;
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

                $skipped++;

                $errors[] =
                    "Row {$rowNumber}: MCQ correct_answer must be A, B, C or D.";

                continue;
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

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: marks must be greater than zero.";

            continue;
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

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: negative_marks must be zero or greater.";

            continue;
        }


        if (
            $negativeMarks > $marks
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: negative_marks cannot exceed marks.";

            continue;
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

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: difficulty must be Easy, Medium or Hard.";

            continue;
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

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: status must be Active or Inactive.";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Position
        |--------------------------------------------------------------------------
        */

        if (
            $position === false ||
            $position === null ||
            $position <= 0
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: position must be greater than zero.";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Load exam once
        |--------------------------------------------------------------------------
        */

        if (
            !isset(
                $examCache[(int)$examId]
            )
        ) {

            $examStatement->execute([
                (int)$examId
            ]);

            $exam =
                $examStatement->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$exam
            ) {

                $examCache[(int)$examId] =
                    null;

            } else {

                $examCache[(int)$examId] =
                    $exam;
            }
        }


        $exam =
            $examCache[(int)$examId];


        if (
            !$exam
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: exam {$examId} does not exist.";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Exam subject
        |--------------------------------------------------------------------------
        */

        $examSubjectId =
            (int)(
                $exam['subject_id']
                ?? 0
            );


        if (
            $examSubjectId <= 0
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: exam {$examId} has no subject.";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Subject must be active
        |--------------------------------------------------------------------------
        */

        if (
            (string)(
                $exam['subject_status']
                ?? ''
            ) !== 'Active'
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: exam {$examId} subject is inactive.";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Exam status
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                (string)(
                    $exam['status']
                    ?? ''
                ),
                [
                    'Cancelled',
                    'Completed'
                ],
                true
            )
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: exam {$examId} cannot receive new questions in its current status.";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Required question count
        |--------------------------------------------------------------------------
        */

        $requiredCount =
            (int)(
                $exam['required_question_count']
                ?? 0
            );


        if (
            $requiredCount <= 0
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: exam {$examId} has no valid required question count.";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Position cannot exceed exam size
        |--------------------------------------------------------------------------
        */

        if (
            $position > $requiredCount
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: position {$position} exceeds the exam question limit of {$requiredCount}.";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Load current database active count once
        |--------------------------------------------------------------------------
        */

        if (
            !isset(
                $runningActiveCounts[(int)$examId]
            )
        ) {

            $activeCountStatement->execute([
                (int)$examId
            ]);

            $runningActiveCounts[(int)$examId] =
                (int)$activeCountStatement->fetchColumn();
        }


        /*
        |--------------------------------------------------------------------------
        | Check active question limit
        |--------------------------------------------------------------------------
        */

        if (
            $status === 'Active'
        ) {

            if (
                $runningActiveCounts[(int)$examId]
                >=
                $requiredCount
            ) {

                $skipped++;

                $errors[] =
                    "Row {$rowNumber}: exam {$examId} already has the maximum {$requiredCount} active questions.";

                continue;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Topic validation
        |--------------------------------------------------------------------------
        */

        if (
            $topicId !== null
        ) {

            if (
                !array_key_exists(
                    (int)$topicId,
                    $topicCache
                )
            ) {

                $topicStatement->execute([
                    (int)$topicId
                ]);

                $topic =
                    $topicStatement->fetch(
                        PDO::FETCH_ASSOC
                    );

                $topicCache[(int)$topicId] =
                    $topic ?: null;
            }


            $topic =
                $topicCache[(int)$topicId];


            if (
                !$topic
            ) {

                $skipped++;

                $errors[] =
                    "Row {$rowNumber}: topic {$topicId} does not exist.";

                continue;
            }


            if (
                (int)$topic['subject_id'] !==
                $examSubjectId
            ) {

                $skipped++;

                $errors[] =
                    "Row {$rowNumber}: topic {$topicId} does not belong to exam {$examId}'s subject.";

                continue;
            }


            if (
                (string)$topic['status'] !== 'Active'
            ) {

                $skipped++;

                $errors[] =
                    "Row {$rowNumber}: topic {$topicId} is inactive.";

                continue;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Duplicate question text in current exam
        |--------------------------------------------------------------------------
        */

        $duplicateStatement->execute([
            (int)$examId,
            $questionText
        ]);


        if (
            $duplicateStatement->fetchColumn()
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: duplicate question text already exists in exam {$examId}.";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Duplicate question text inside this CSV
        |--------------------------------------------------------------------------
        */

        $csvDuplicateKey =
            (int)$examId .
            '|' .
            md5(
                mb_strtolower(
                    $questionText
                )
            );


        if (
            isset(
                $csvDuplicateKeys[$csvDuplicateKey]
            )
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: duplicate question text already appears earlier in this CSV for exam {$examId}.";

            continue;
        }


        $csvDuplicateKeys[$csvDuplicateKey] =
            true;


        /*
        |--------------------------------------------------------------------------
        | Position collision with database
        |--------------------------------------------------------------------------
        */

        $positionStatement->execute([
            (int)$examId,
            (int)$position
        ]);


        if (
            $positionStatement->fetchColumn()
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: position {$position} is already occupied in exam {$examId}.";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Position collision inside CSV
        |--------------------------------------------------------------------------
        */

        if (
            !isset(
                $runningPositions[(int)$examId]
            )
        ) {

            $runningPositions[(int)$examId] =
                [];
        }


        if (
            isset(
                $runningPositions[(int)$examId]
                    [(int)$position]
            )
        ) {

            $skipped++;

            $errors[] =
                "Row {$rowNumber}: position {$position} is duplicated in this CSV for exam {$examId}.";

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Insert question
        |--------------------------------------------------------------------------
        */

        $insertQuestion->execute([

            $examSubjectId,

            $topicId !== null
                ? (int)$topicId
                : null,

            $questionType,

            $questionText,

            $optionA !== ''
                ? $optionA
                : null,

            $optionB !== ''
                ? $optionB
                : null,

            $optionC !== ''
                ? $optionC
                : null,

            $optionD !== ''
                ? $optionD
                : null,

            $correctAnswer,

            $explanation !== ''
                ? $explanation
                : null,

            (float)$marks,

            (float)$negativeMarks,

            $estimatedTime !== null
                ? (int)$estimatedTime
                : null,

            $difficulty,

            $status

        ]);


        $questionId =
            (int)$conn->lastInsertId();


        if (
            $questionId <= 0
        ) {

            throw new RuntimeException(
                "Unable to create question on CSV row {$rowNumber}."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Assign question to exam
        |--------------------------------------------------------------------------
        */

        $insertAssignment->execute([

            (int)$examId,

            $questionId,

            (int)$position

        ]);


        /*
        |--------------------------------------------------------------------------
        | Update running counters
        |--------------------------------------------------------------------------
        */

        if (
            $status === 'Active'
        ) {

            $runningActiveCounts[(int)$examId]++;
        }


        $runningPositions[(int)$examId]
            [(int)$position] =
            $questionId;


        $imported++;
    }


    fclose(
        $handle
    );


    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Success summary
    |--------------------------------------------------------------------------
    */

    $resultMessage =
        "CSV import completed. " .
        "Imported: {$imported}. " .
        "Skipped: {$skipped}.";


    if (
        !empty($errors)
    ) {

        $displayErrors =
            array_slice(
                $errors,
                0,
                20
            );


        $resultMessage .=
            " First issues: " .
            implode(
                " | ",
                $displayErrors
            );


        if (
            count($errors) > 20
        ) {

            $resultMessage .=
                " | +" .
                (
                    count($errors) - 20
                ) .
                " more issue(s).";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Keep result in session
    |--------------------------------------------------------------------------
    */

    $_SESSION['success'] =
        $resultMessage;


    header(
        "Location: import.php"
    );

    exit;


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
        'Admin question CSV import failed: ' .
        $exception->getMessage()
    );


    $_SESSION['error'] =
        "Question import failed. No database changes were saved. Please check the CSV format and try again.";


    header(
        "Location: import.php"
    );

    exit;
}