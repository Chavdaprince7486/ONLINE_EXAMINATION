<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";
require_once "../../config/functions.php";
require_once "../../config/exam_validation.php";


/*
|--------------------------------------------------------------------------
| ADMIN AUTHENTICATION
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
| EXAM ID
|--------------------------------------------------------------------------
*/

$id =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );


if (
    $id === false ||
    $id === null ||
    $id <= 0
) {

    header(
        "Location: index.php"
    );

    exit;
}


$examId =
    (int)$id;


$page_title =
    "Edit Exam";


$error =
    "";


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function admin_edit_exam_escape(
    mixed $value
): string {

    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function admin_edit_exam_datetime(
    ?string $value
): string {

    if (
        $value === null ||
        trim($value) === ''
    ) {

        return '';
    }


    $timestamp =
        strtotime($value);


    if (
        $timestamp === false
    ) {

        return '';
    }


    return date(
        'Y-m-d\TH:i',
        $timestamp
    );
}


/*
|--------------------------------------------------------------------------
| LOAD EXAM
|--------------------------------------------------------------------------
*/

try {

    $examStatement =
        $conn->prepare("
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

                s.category_id

            FROM exams e

            LEFT JOIN subjects s
                ON s.id = e.subject_id

            WHERE
                e.id = ?

            LIMIT 1
        ");

    $examStatement->execute([
        $examId
    ]);


    $exam =
        $examStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$exam
    ) {

        header(
            "Location: index.php"
        );

        exit;
    }

} catch (Throwable $exception) {

    error_log(
        'Admin edit exam load failed: ' .
        $exception->getMessage()
    );

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD ATTEMPT COUNT
|--------------------------------------------------------------------------
*/

$attemptCount =
    0;


try {

    $attemptStatement =
        $conn->prepare("
            SELECT
                COUNT(*)
            FROM exam_attempts
            WHERE exam_id = ?
        ");

    $attemptStatement->execute([
        $examId
    ]);

    $attemptCount =
        (int)$attemptStatement->fetchColumn();

} catch (Throwable $exception) {

    error_log(
        'Admin edit exam attempt count failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| CURRENT QUESTION VALIDATION
|--------------------------------------------------------------------------
*/

$validation =
    validate_exam_from_database(
        $conn,
        $examId
    );


$isExamReady =
    $validation['valid'] === true;


$currentQuestionCount =
    count(
        $validation['questions']
        ?? []
    );


$currentQuestionMarks =
    0.00;


foreach (
    ($validation['questions'] ?? [])
    as $question
) {

    $currentQuestionMarks +=
        round(
            (float)(
                $question['marks'] ?? 0
            ),
            2
        );
}


$currentQuestionMarks =
    round(
        $currentQuestionMarks,
        2
    );


/*
|--------------------------------------------------------------------------
| FORM VALUES
|--------------------------------------------------------------------------
*/

$examTitle =
    (string)$exam['title'];

$categoryId =
    (string)(
        $exam['category_id']
        ?? ''
    );

$subjectId =
    (string)(
        $exam['subject_id']
        ?? ''
    );

$teacherId =
    (string)(
        $exam['teacher_id']
        ?? ''
    );

$examDescription =
    (string)(
        $exam['description']
        ?? ''
    );

$examType =
    (string)$exam['exam_type'];

$totalMarks =
    (string)$exam['total_marks'];

$passingMarks =
    (string)$exam['passing_marks'];

$durationMinutes =
    (string)$exam['duration_minutes'];

$negativeMarking =
    (int)$exam['negative_marking'];

$examFee =
    (string)$exam['exam_fee'];

$subscriptionRequired =
    (int)$exam['subscription_required'];

$startDatetime =
    admin_edit_exam_datetime(
        (string)$exam['starts_at']
    );

$endDatetime =
    admin_edit_exam_datetime(
        (string)$exam['ends_at']
    );

$status =
    (string)$exam['status'];


/*
|--------------------------------------------------------------------------
| LOAD CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

try {

    $categories =
        $conn
            ->query("
                SELECT

                    id,
                    category_name

                FROM categories

                WHERE
                    status = 'Active'

                ORDER BY
                    category_name ASC
            ")
            ->fetchAll(
                PDO::FETCH_ASSOC
            );

} catch (Throwable $exception) {

    error_log(
        'Admin edit exam categories failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| LOAD SUBJECTS
|--------------------------------------------------------------------------
*/

$subjects = [];

try {

    $subjects =
        $conn
            ->query("
                SELECT

                    id,
                    category_id,
                    name

                FROM subjects

                WHERE
                    status = 'Active'

                    OR id = " .
                    (int)$subjectId .

                "

                ORDER BY
                    name ASC
            ")
            ->fetchAll(
                PDO::FETCH_ASSOC
            );

} catch (Throwable $exception) {

    error_log(
        'Admin edit exam subjects failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| LOAD TEACHERS
|--------------------------------------------------------------------------
*/

$teachers = [];

try {

    $teachers =
        $conn
            ->query("
                SELECT

                    id,
                    full_name

                FROM teachers

                WHERE
                    status = 'Active'

                    OR id = " .
                    (int)$teacherId .

                "

                ORDER BY
                    full_name ASC
            ")
            ->fetchAll(
                PDO::FETCH_ASSOC
            );

} catch (Throwable $exception) {

    error_log(
        'Admin edit exam teachers failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    /*
    |--------------------------------------------------------------------------
    | READ VALUES
    |--------------------------------------------------------------------------
    */

    $examTitle =
        trim(
            (string)(
                $_POST['exam_title']
                ?? ''
            )
        );

    $categoryIdValue =
        filter_var(
            $_POST['category_id']
            ?? '',
            FILTER_VALIDATE_INT
        );

    $subjectIdValue =
        filter_var(
            $_POST['subject_id']
            ?? '',
            FILTER_VALIDATE_INT
        );

    $teacherIdValue =
        filter_var(
            $_POST['teacher_id']
            ?? '',
            FILTER_VALIDATE_INT
        );

    $examDescription =
        trim(
            (string)(
                $_POST['exam_description']
                ?? ''
            )
        );

    $examType =
        trim(
            (string)(
                $_POST['exam_type']
                ?? ''
            )
        );

    $totalMarksValue =
        filter_var(
            $_POST['total_marks']
            ?? '',
            FILTER_VALIDATE_FLOAT
        );

    $passingMarksValue =
        filter_var(
            $_POST['passing_marks']
            ?? '',
            FILTER_VALIDATE_FLOAT
        );

    $durationValue =
        filter_var(
            $_POST['duration_minutes']
            ?? '',
            FILTER_VALIDATE_INT
        );

    $negativeMarking =
        isset(
            $_POST['negative_marking']
        )
            ? 1
            : 0;

    $examFeeValue =
        filter_var(
            $_POST['exam_fee']
            ?? '0',
            FILTER_VALIDATE_FLOAT
        );

    $subscriptionRequired =
        isset(
            $_POST['subscription_required']
        )
            ? 1
            : 0;

    $startDatetime =
        trim(
            (string)(
                $_POST['start_datetime']
                ?? ''
            )
        );

    $endDatetime =
        trim(
            (string)(
                $_POST['end_datetime']
                ?? ''
            )
        );

    $newStatus =
        trim(
            (string)(
                $_POST['status']
                ?? 'Draft'
            )
        );


    /*
    |--------------------------------------------------------------------------
    | PRESERVE
    |--------------------------------------------------------------------------
    */

    $categoryId =
        (
            $categoryIdValue !== false &&
            $categoryIdValue !== null
        )
            ? (string)$categoryIdValue
            : '';

    $subjectId =
        (
            $subjectIdValue !== false &&
            $subjectIdValue !== null
        )
            ? (string)$subjectIdValue
            : '';

    $teacherId =
        (
            $teacherIdValue !== false &&
            $teacherIdValue !== null
        )
            ? (string)$teacherIdValue
            : '';

    $totalMarks =
        (
            $totalMarksValue !== false &&
            $totalMarksValue !== null
        )
            ? (string)$totalMarksValue
            : '';

    $passingMarks =
        (
            $passingMarksValue !== false &&
            $passingMarksValue !== null
        )
            ? (string)$passingMarksValue
            : '';

    $durationMinutes =
        (
            $durationValue !== false &&
            $durationValue !== null
        )
            ? (string)$durationValue
            : '';

    $examFee =
        (
            $examFeeValue !== false &&
            $examFeeValue !== null
        )
            ? (string)$examFeeValue
            : '0';


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
            'Invalid security token. Please try again.';
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS VALIDATION
    |--------------------------------------------------------------------------
    */

    $allowedStatuses = [

        'Draft',
        'Scheduled',
        'Live',
        'Completed',
        'Cancelled',
        'Active',
        'Upcoming',
        'Running'

    ];


    if (
        $error === '' &&
        !in_array(
            $newStatus,
            $allowedStatuses,
            true
        )
    ) {

        $error =
            'Invalid exam status.';
    }


    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        $examTitle === ''
    ) {

        $error =
            'Exam title is required.';

    } elseif (
        $error === '' &&
        mb_strlen($examTitle) > 180
    ) {

        $error =
            'Exam title cannot exceed 180 characters.';

    } elseif (
        $error === '' &&
        (
            $categoryIdValue === false ||
            $categoryIdValue === null ||
            $categoryIdValue <= 0
        )
    ) {

        $error =
            'Please select a valid category.';

    } elseif (
        $error === '' &&
        (
            $subjectIdValue === false ||
            $subjectIdValue === null ||
            $subjectIdValue <= 0
        )
    ) {

        $error =
            'Please select a valid subject.';

    } elseif (
        $error === '' &&
        (
            $teacherIdValue === false ||
            $teacherIdValue === null ||
            $teacherIdValue <= 0
        )
    ) {

        $error =
            'Please select a valid teacher.';

    } elseif (
        $error === '' &&
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
        $error === '' &&
        (
            $totalMarksValue === false ||
            $totalMarksValue === null ||
            $totalMarksValue <= 0
        )
    ) {

        $error =
            'Total marks must be greater than zero.';

    } elseif (
        $error === '' &&
        (
            $passingMarksValue === false ||
            $passingMarksValue === null ||
            $passingMarksValue < 0
        )
    ) {

        $error =
            'Passing marks cannot be negative.';

    } elseif (
        $error === '' &&
        $passingMarksValue > $totalMarksValue
    ) {

        $error =
            'Passing marks cannot be greater than total marks.';

    } elseif (
        $error === '' &&
        (
            $durationValue === false ||
            $durationValue === null ||
            $durationValue < 1 ||
            $durationValue > 65535
        )
    ) {

        $error =
            'Duration must be between 1 and 65535 minutes.';

    } elseif (
        $error === '' &&
        (
            $examFeeValue === false ||
            $examFeeValue === null ||
            $examFeeValue < 0
        )
    ) {

        $error =
            'Exam fee cannot be negative.';

    } elseif (
        $error === '' &&
        $examDescription !== '' &&
        mb_strlen($examDescription) > 5000
    ) {

        $error =
            'Description cannot exceed 5000 characters.';
    }


    /*
    |--------------------------------------------------------------------------
    | IMPORTANT:
    | The stored required count can NEVER be changed from 50.
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
    ) {

        if (
            (int)(
                $exam['required_question_count']
                ?? 0
            )
            !==
            EXAM_REQUIRED_QUESTION_COUNT
        ) {

            /*
             * Existing legacy invalid value can only be repaired
             * after the actual question configuration has exactly 50.
             */
            if (
                $currentQuestionCount !==
                EXAM_REQUIRED_QUESTION_COUNT
            ) {

                $error =
                    'This exam has an invalid stored question count. Exactly 50 questions must be assigned before it can be repaired.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DATE
    |--------------------------------------------------------------------------
    */

    $startValue = null;
    $endValue = null;


    if (
        $error === '' &&
        $startDatetime !== ''
    ) {

        $timestamp =
            strtotime(
                $startDatetime
            );


        if (
            $timestamp === false
        ) {

            $error =
                'Invalid start date and time.';

        } else {

            $startValue =
                date(
                    'Y-m-d H:i:s',
                    $timestamp
                );
        }
    }


    if (
        $error === '' &&
        $endDatetime !== ''
    ) {

        $timestamp =
            strtotime(
                $endDatetime
            );


        if (
            $timestamp === false
        ) {

            $error =
                'Invalid end date and time.';

        } else {

            $endValue =
                date(
                    'Y-m-d H:i:s',
                    $timestamp
                );
        }
    }


    if (
        $error === '' &&
        $examType === 'Live' &&
        $startValue === null
    ) {

        $error =
            'A Live exam requires a start date and time.';
    }


    if (
        $error === '' &&
        $startValue !== null &&
        $endValue !== null &&
        strtotime($endValue) <=
        strtotime($startValue)
    ) {

        $error =
            'End date and time must be greater than start date and time.';
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE CATEGORY + SUBJECT
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
    ) {

        try {

            $statement =
                $conn->prepare("
                    SELECT
                        id
                    FROM subjects
                    WHERE
                        id = ?
                        AND category_id = ?
                        AND (
                            status = 'Active'
                            OR id = ?
                        )
                    LIMIT 1
                ");

            $statement->execute([
                (int)$subjectIdValue,
                (int)$categoryIdValue,
                (int)$subjectIdValue
            ]);


            if (
                !$statement->fetchColumn()
            ) {

                $error =
                    'The selected subject does not belong to the selected category.';
            }

        } catch (Throwable $exception) {

            error_log(
                'Admin edit exam subject validation failed: ' .
                $exception->getMessage()
            );

            $error =
                'Unable to validate subject.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | TEACHER
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
    ) {

        try {

            $statement =
                $conn->prepare("
                    SELECT
                        id
                    FROM teachers
                    WHERE
                        id = ?
                        AND (
                            status = 'Active'
                            OR id = ?
                        )
                    LIMIT 1
                ");

            $statement->execute([
                (int)$teacherIdValue,
                (int)$teacherIdValue
            ]);


            if (
                !$statement->fetchColumn()
            ) {

                $error =
                    'The selected teacher does not exist.';
            }

        } catch (Throwable $exception) {

            error_log(
                'Admin edit exam teacher validation failed: ' .
                $exception->getMessage()
            );

            $error =
                'Unable to validate teacher.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT PROTECTION AFTER ATTEMPTS
    |--------------------------------------------------------------------------
    |
    | Once students have attempts, changing the core exam structure
    | can corrupt historical results.
    |--------------------------------------------------------------------------
    */

    $coreStructureChanged =
        false;


    if (
        $error === ''
    ) {

        $oldTotal =
            round(
                (float)$exam['total_marks'],
                2
            );

        $oldPassing =
            round(
                (float)$exam['passing_marks'],
                2
            );

        $oldDuration =
            (int)$exam['duration_minutes'];

        $oldSubject =
            (int)$exam['subject_id'];

        $oldTeacher =
            (int)$exam['teacher_id'];

        $oldExamType =
            (string)$exam['exam_type'];


        if (
            $oldTotal !==
            round(
                (float)$totalMarksValue,
                2
            )
            ||
            $oldPassing !==
            round(
                (float)$passingMarksValue,
                2
            )
            ||
            $oldDuration !==
            (int)$durationValue
            ||
            $oldSubject !==
            (int)$subjectIdValue
            ||
            $oldTeacher !==
            (int)$teacherIdValue
            ||
            $oldExamType !==
            $examType
        ) {

            $coreStructureChanged =
                true;
        }
    }


    if (
        $error === '' &&
        $attemptCount > 0 &&
        $coreStructureChanged
    ) {

        $error =
            'Core exam structure cannot be changed because students have already attempted this exam.';
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS RULES
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        in_array(
            $newStatus,
            [
                'Active',
                'Scheduled',
                'Live',
                'Upcoming',
                'Running'
            ],
            true
        )
    ) {

        /*
        |--------------------------------------------------------------------------
        | EXACT 50 + EXACT MARKS
        |--------------------------------------------------------------------------
        */

        $validationNow =
            validate_exam_question_configuration(

                (float)$totalMarksValue,

                $validation['questions'] ?? [],

                EXAM_REQUIRED_QUESTION_COUNT
            );


        if (
            !$validationNow['valid']
        ) {

            $error =
                'The exam cannot become active/ready: ' .
                $validationNow['message'];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | LIVE / SCHEDULED
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        $examType === 'Practice' &&
        in_array(
            $newStatus,
            [
                'Scheduled',
                'Live',
                'Upcoming',
                'Running'
            ],
            true
        )
    ) {

        $error =
            'A Practice exam cannot use a Live scheduling status.';
    }


    if (
        $error === '' &&
        $examType === 'Live' &&
        $newStatus === 'Active'
    ) {

        $error =
            'A Live exam must use Scheduled or Live status.';
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
    ) {

        try {

            $conn->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | LOCK CURRENT EXAM
            |--------------------------------------------------------------------------
            */

            $lock =
                $conn->prepare("
                    SELECT

                        id,
                        subject_id,
                        teacher_id,
                        exam_type,

                        required_question_count,

                        total_marks,
                        passing_marks,

                        status

                    FROM exams

                    WHERE
                        id = ?

                    LIMIT 1

                    FOR UPDATE
                ");

            $lock->execute([
                $examId
            ]);


            $lockedExam =
                $lock->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$lockedExam
            ) {

                throw new RuntimeException(
                    'The exam no longer exists.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | NEVER CHANGE REQUIRED COUNT FROM 50
            |--------------------------------------------------------------------------
            */

            if (
                (int)$lockedExam[
                    'required_question_count'
                ]
                !==
                EXAM_REQUIRED_QUESTION_COUNT
            ) {

                /*
                 * Only repair to 50 when exactly 50 valid questions exist.
                 */

                if (
                    $currentQuestionCount
                    !==
                    EXAM_REQUIRED_QUESTION_COUNT
                ) {

                    throw new RuntimeException(
                        'Exam cannot be saved because exactly 50 questions are required.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | RE-CHECK ATTEMPTS
            |--------------------------------------------------------------------------
            */

            $attemptLock =
                $conn->prepare("
                    SELECT
                        COUNT(*)
                    FROM exam_attempts
                    WHERE exam_id = ?
                ");

            $attemptLock->execute([
                $examId
            ]);


            $latestAttemptCount =
                (int)$attemptLock->fetchColumn();


            if (
                $latestAttemptCount > 0 &&
                $coreStructureChanged
            ) {

                throw new RuntimeException(
                    'Core exam structure cannot be changed because students have already attempted this exam.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | IF READY STATUS:
            | verify live database configuration again
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $newStatus,
                    [
                        'Active',
                        'Scheduled',
                        'Live',
                        'Upcoming',
                        'Running'
                    ],
                    true
                )
            ) {

                $freshQuestions =
                    load_exam_question_configuration(
                        $conn,
                        $examId
                    );


                $freshValidation =
                    validate_exam_question_configuration(

                        (float)$totalMarksValue,

                        $freshQuestions,

                        EXAM_REQUIRED_QUESTION_COUNT
                    );


                if (
                    !$freshValidation['valid']
                ) {

                    throw new RuntimeException(
                        $freshValidation['message']
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            $update =
                $conn->prepare("
                    UPDATE exams

                    SET

                        subject_id = ?,

                        teacher_id = ?,

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
                ");


            $update->execute([

                (int)$subjectIdValue,

                (int)$teacherIdValue,

                $examTitle,

                $examDescription !== ''
                    ? $examDescription
                    : null,

                $examType,

                (int)$durationValue,

                EXAM_REQUIRED_QUESTION_COUNT,

                number_format(
                    (float)$totalMarksValue,
                    2,
                    '.',
                    ''
                ),

                number_format(
                    (float)$passingMarksValue,
                    2,
                    '.',
                    ''
                ),

                $negativeMarking,

                number_format(
                    (float)$examFeeValue,
                    2,
                    '.',
                    ''
                ),

                $subscriptionRequired,

                $startValue,

                $endValue,

                $newStatus,

                $examId

            ]);


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            $_SESSION['success_message'] =
                'Exam updated successfully. Required question count remains fixed at 50.';


            header(
                'Location: view.php?id=' .
                $examId
            );

            exit;


        } catch (Throwable $exception) {

            if (
                $conn->inTransaction()
            ) {

                $conn->rollBack();
            }


            error_log(
                'Admin edit exam failed: ' .
                $exception->getMessage()
            );


            $error =
                $exception->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

include "../includes/header.php";

?>

<style>

    .exam-edit-page {
        max-width: 1250px;
        margin: 0 auto;
    }

    .exam-edit-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 20px;
        margin-bottom: 21px;
        flex-wrap: wrap;
    }

    .exam-edit-header h1 {
        margin: 0;
        color: #5d4037;
        font-weight: 950;
        letter-spacing: -.035em;
    }

    .exam-edit-header p {
        margin: 7px 0 0;
        color: #746d68;
    }

    .exam-head-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .exam-head-btn {
        min-height: 43px;
        padding: 0 14px;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        font-weight: 850;
    }

    .exam-head-btn.back {
        color: #5d4037;
        background: #eee9df;
    }

    .exam-head-btn.questions {
        color: #fff;
        background: #556b2f;
    }

    .exam-lock-banner {
        display: flex;
        align-items: flex-start;
        gap: 13px;
        margin-bottom: 19px;
        padding: 16px 17px;
        border-radius: 15px;
        border: 1px solid rgba(85,107,47,.15);
        background: rgba(85,107,47,.07);
    }

    .exam-lock-icon {
        flex: 0 0 38px;
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        border-radius: 11px;
        background: rgba(85,107,47,.12);
        color: #556b2f;
    }

    .exam-lock-banner strong {
        color: #5d4037;
        font-weight: 900;
    }

    .exam-lock-banner span {
        display: block;
        margin-top: 3px;
        color: #746d68;
        font-size: .83rem;
        line-height: 1.55;
    }

    .exam-edit-card {
        overflow: hidden;
        border-radius: 21px;
        border: 1px solid rgba(93,64,55,.08);
        background: rgba(255,255,255,.84);
        box-shadow:
            0 18px 45px rgba(62,45,37,.08);
    }

    .exam-edit-card-head {
        padding: 20px 22px;
        border-bottom: 1px solid #eee7df;
    }

    .exam-edit-card-head h2 {
        margin: 0;
        color: #5d4037;
        font-weight: 900;
        font-size: 1.05rem;
    }

    .exam-edit-card-head p {
        margin: 5px 0 0;
        color: #746d68;
        font-size: .82rem;
    }

    .exam-edit-card-body {
        padding: 22px;
    }

    .exam-label {
        display: block;
        margin-bottom: 6px;
        color: #5d4037;
        font-size: .80rem;
        font-weight: 850;
    }

    .exam-control {
        min-height: 46px;
        border-color: #ddd3ca;
        border-radius: 11px;
    }

    .exam-control:focus {
        border-color: #556b2f;
        box-shadow:
            0 0 0 .2rem rgba(85,107,47,.10);
    }

    textarea.exam-control {
        min-height: 115px;
        resize: vertical;
    }

    .exam-status-box {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px;
        border-radius: 12px;
        background: #faf7f0;
        border: 1px solid #e5dbd2;
    }

    .exam-ready {
        color: #556b2f;
        font-size: .76rem;
        font-weight: 850;
    }

    .exam-not-ready {
        color: #a83232;
        font-size: .76rem;
        font-weight: 850;
    }

    .exam-config-grid {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }

    .exam-config-item {
        padding: 15px;
        border-radius: 14px;
        background: #faf7f0;
    }

    .exam-config-label {
        color: #746d68;
        font-size: .73rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .exam-config-value {
        margin-top: 4px;
        color: #5d4037;
        font-weight: 900;
    }

    .exam-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding-top: 20px;
        margin-top: 20px;
        border-top: 1px solid #eee7df;
        flex-wrap: wrap;
    }

    .exam-btn {
        min-height: 46px;
        padding: 0 17px;
        border: 0;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        font-weight: 900;
    }

    .exam-btn.cancel {
        background: #eee9df;
        color: #5d4037;
    }

    .exam-btn.save {
        background: #5d4037;
        color: #fff;
    }

    .exam-note {
        margin-top: 7px;
        color: #746d68;
        font-size: .75rem;
        line-height: 1.5;
    }

    @media (max-width: 800px) {

        .exam-config-grid {
            grid-template-columns: 1fr;
        }

    }

</style>


<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content">

            <div class="exam-edit-page">

                <div class="exam-edit-header">

                    <div>

                        <h1>
                            Edit Exam
                        </h1>

                        <p>
                            Update exam metadata without breaking the 50-question configuration.
                        </p>

                    </div>

                    <div class="exam-head-actions">

                        <a
                            href="index.php"
                            class="exam-head-btn back"
                        >

                            <i class="fa-solid fa-arrow-left"></i>

                            Back

                        </a>

                        <a
                            href="../exam_questions.php?exam_id=<?= $examId ?>"
                            class="exam-head-btn questions"
                        >

                            <i class="fa-solid fa-list-check"></i>

                            Manage Questions

                        </a>

                    </div>

                </div>


                <div class="exam-lock-banner">

                    <div class="exam-lock-icon">

                        <i class="fa-solid fa-shield-check"></i>

                    </div>

                    <div>

                        <strong>
                            Exactly 50 Questions — Permanently Required
                        </strong>

                        <span>
                            This exam stores required_question_count as 50.
                            Ready/Active status is only allowed when all 50 assigned questions
                            are Active and their marks exactly equal the exam total marks.
                        </span>

                    </div>

                </div>


                <?php if ($error !== ''): ?>

                    <div class="alert alert-danger">

                        <i
                            class="fa-solid fa-circle-exclamation me-1"
                        ></i>

                        <?= admin_edit_exam_escape(
                            $error
                        ) ?>

                    </div>

                <?php endif; ?>


                <div class="exam-config-grid">

                    <div class="exam-config-item">

                        <div class="exam-config-label">
                            Assigned Questions
                        </div>

                        <div class="exam-config-value">
                            <?= $currentQuestionCount ?> / 50
                        </div>

                    </div>


                    <div class="exam-config-item">

                        <div class="exam-config-label">
                            Question Marks
                        </div>

                        <div class="exam-config-value">
                            <?= number_format(
                                $currentQuestionMarks,
                                2
                            ) ?>
                        </div>

                    </div>


                    <div class="exam-config-item">

                        <div class="exam-config-label">
                            Attempts
                        </div>

                        <div class="exam-config-value">
                            <?= $attemptCount ?>
                        </div>

                    </div>

                </div>


                <section class="exam-edit-card">

                    <div class="exam-edit-card-head">

                        <h2>
                            Examination Details
                        </h2>

                        <p>
                            Exam ID #<?= $examId ?>
                        </p>

                    </div>


                    <div class="exam-edit-card-body">

                        <form
                            method="post"
                            id="editExamForm"
                        >

                            <?= csrf_field() ?>


                            <div class="row g-3">


                                <div class="col-12">

                                    <label
                                        class="exam-label"
                                        for="exam_title"
                                    >
                                        Exam Title *
                                    </label>

                                    <input
                                        id="exam_title"
                                        class="form-control exam-control"
                                        type="text"
                                        name="exam_title"
                                        maxlength="180"
                                        required
                                        value="<?= admin_edit_exam_escape(
                                            $examTitle
                                        ) ?>"
                                    >

                                </div>


                                <div class="col-lg-4">

                                    <label
                                        class="exam-label"
                                        for="category_id"
                                    >
                                        Category *
                                    </label>

                                    <select
                                        id="category_id"
                                        name="category_id"
                                        class="form-select exam-control"
                                        required
                                    >

                                        <?php foreach (
                                            $categories
                                            as $category
                                        ): ?>

                                            <option
                                                value="<?= (int)$category['id'] ?>"
                                                <?= $categoryId ===
                                                    (string)$category['id']
                                                    ? 'selected'
                                                    : '' ?>
                                            >

                                                <?= admin_edit_exam_escape(
                                                    $category['category_name']
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="col-lg-4">

                                    <label
                                        class="exam-label"
                                        for="subject_id"
                                    >
                                        Subject *
                                    </label>

                                    <select
                                        id="subject_id"
                                        name="subject_id"
                                        class="form-select exam-control"
                                        required
                                    >

                                        <?php foreach (
                                            $subjects
                                            as $subject
                                        ): ?>

                                            <option
                                                value="<?= (int)$subject['id'] ?>"
                                                data-category-id="<?= (int)$subject['category_id'] ?>"
                                                <?= $subjectId ===
                                                    (string)$subject['id']
                                                    ? 'selected'
                                                    : '' ?>
                                            >

                                                <?= admin_edit_exam_escape(
                                                    $subject['name']
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="col-lg-4">

                                    <label
                                        class="exam-label"
                                        for="teacher_id"
                                    >
                                        Teacher *
                                    </label>

                                    <select
                                        id="teacher_id"
                                        name="teacher_id"
                                        class="form-select exam-control"
                                        required
                                    >

                                        <?php foreach (
                                            $teachers
                                            as $teacher
                                        ): ?>

                                            <option
                                                value="<?= (int)$teacher['id'] ?>"
                                                <?= $teacherId ===
                                                    (string)$teacher['id']
                                                    ? 'selected'
                                                    : '' ?>
                                            >

                                                <?= admin_edit_exam_escape(
                                                    $teacher['full_name']
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="col-lg-4">

                                    <label
                                        class="exam-label"
                                        for="exam_type"
                                    >
                                        Exam Type *
                                    </label>

                                    <select
                                        id="exam_type"
                                        name="exam_type"
                                        class="form-select exam-control"
                                        required
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


                                <div class="col-lg-4">

                                    <label
                                        class="exam-label"
                                        for="duration_minutes"
                                    >
                                        Duration (Minutes) *
                                    </label>

                                    <input
                                        id="duration_minutes"
                                        class="form-control exam-control"
                                        type="number"
                                        name="duration_minutes"
                                        min="1"
                                        max="65535"
                                        required
                                        value="<?= admin_edit_exam_escape(
                                            $durationMinutes
                                        ) ?>"
                                    >

                                </div>


                                <div class="col-lg-4">

                                    <label class="exam-label">
                                        Required Questions
                                    </label>

                                    <div class="exam-status-box">

                                        <i
                                            class="fa-solid fa-list-check"
                                            style="color:#556b2f;"
                                        ></i>

                                        <strong>
                                            Exactly 50
                                        </strong>

                                    </div>

                                </div>


                                <div class="col-lg-4">

                                    <label
                                        class="exam-label"
                                        for="total_marks"
                                    >
                                        Total Marks *
                                    </label>

                                    <input
                                        id="total_marks"
                                        class="form-control exam-control"
                                        type="number"
                                        name="total_marks"
                                        min="0.01"
                                        step="0.01"
                                        required
                                        value="<?= admin_edit_exam_escape(
                                            $totalMarks
                                        ) ?>"
                                    >

                                    <div class="exam-note">
                                        Current assigned question marks:
                                        <?= number_format(
                                            $currentQuestionMarks,
                                            2
                                        ) ?>
                                    </div>

                                </div>


                                <div class="col-lg-4">

                                    <label
                                        class="exam-label"
                                        for="passing_marks"
                                    >
                                        Passing Marks *
                                    </label>

                                    <input
                                        id="passing_marks"
                                        class="form-control exam-control"
                                        type="number"
                                        name="passing_marks"
                                        min="0"
                                        step="0.01"
                                        required
                                        value="<?= admin_edit_exam_escape(
                                            $passingMarks
                                        ) ?>"
                                    >

                                </div>


                                <div class="col-lg-4">

                                    <label
                                        class="exam-label"
                                        for="status"
                                    >
                                        Status
                                    </label>

                                    <select
                                        id="status"
                                        name="status"
                                        class="form-select exam-control"
                                        required
                                    >

                                        <?php
                                        foreach (
                                            [
                                                'Draft',
                                                'Scheduled',
                                                'Live',
                                                'Completed',
                                                'Cancelled',
                                                'Active',
                                                'Upcoming',
                                                'Running'
                                            ] as $statusOption
                                        ):
                                        ?>

                                            <option
                                                value="<?= $statusOption ?>"
                                                <?= $status ===
                                                    $statusOption
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= $statusOption ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="col-lg-4">

                                    <label
                                        class="exam-label"
                                        for="exam_fee"
                                    >
                                        Exam Fee
                                    </label>

                                    <input
                                        id="exam_fee"
                                        class="form-control exam-control"
                                        type="number"
                                        name="exam_fee"
                                        min="0"
                                        step="0.01"
                                        value="<?= admin_edit_exam_escape(
                                            $examFee
                                        ) ?>"
                                    >

                                </div>


                                <div class="col-lg-4">

                                    <label
                                        class="exam-label"
                                        for="start_datetime"
                                    >
                                        Start Date & Time
                                    </label>

                                    <input
                                        id="start_datetime"
                                        class="form-control exam-control"
                                        type="datetime-local"
                                        name="start_datetime"
                                        value="<?= admin_edit_exam_escape(
                                            $startDatetime
                                        ) ?>"
                                    >

                                </div>


                                <div class="col-lg-4">

                                    <label
                                        class="exam-label"
                                        for="end_datetime"
                                    >
                                        End Date & Time
                                    </label>

                                    <input
                                        id="end_datetime"
                                        class="form-control exam-control"
                                        type="datetime-local"
                                        name="end_datetime"
                                        value="<?= admin_edit_exam_escape(
                                            $endDatetime
                                        ) ?>"
                                    >

                                </div>


                                <div class="col-lg-6">

                                    <div class="form-check mt-2">

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="negative_marking"
                                            name="negative_marking"
                                            value="1"
                                            <?= $negativeMarking
                                                ? 'checked'
                                                : '' ?>
                                        >

                                        <label
                                            class="form-check-label"
                                            for="negative_marking"
                                        >
                                            Enable Negative Marking
                                        </label>

                                    </div>

                                </div>


                                <div class="col-lg-6">

                                    <div class="form-check mt-2">

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="subscription_required"
                                            name="subscription_required"
                                            value="1"
                                            <?= $subscriptionRequired
                                                ? 'checked'
                                                : '' ?>
                                        >

                                        <label
                                            class="form-check-label"
                                            for="subscription_required"
                                        >
                                            Require Active Subscription
                                        </label>

                                    </div>

                                </div>


                                <div class="col-12">

                                    <label
                                        class="exam-label"
                                        for="exam_description"
                                    >
                                        Description
                                    </label>

                                    <textarea
                                        id="exam_description"
                                        name="exam_description"
                                        class="form-control exam-control"
                                        maxlength="5000"
                                    ><?= admin_edit_exam_escape(
                                        $examDescription
                                    ) ?></textarea>

                                </div>

                            </div>


                            <div class="exam-actions">

                                <a
                                    href="view.php?id=<?= $examId ?>"
                                    class="exam-btn cancel"
                                >
                                    Cancel
                                </a>

                                <button
                                    type="submit"
                                    class="exam-btn save"
                                >

                                    <i
                                        class="fa-solid fa-floppy-disk"
                                    ></i>

                                    Save Changes

                                </button>

                            </div>

                        </form>

                    </div>

                </section>

            </div>

        </main>

    </div>

</div>


<script>

(function () {

    const category =
        document.getElementById(
            'category_id'
        );

    const subject =
        document.getElementById(
            'subject_id'
        );

    const examType =
        document.getElementById(
            'exam_type'
        );

    const status =
        document.getElementById(
            'status'
        );

    const startInput =
        document.getElementById(
            'start_datetime'
        );

    const endInput =
        document.getElementById(
            'end_datetime'
        );

    const form =
        document.getElementById(
            'editExamForm'
        );


    function filterSubjects()
    {

        if (
            !category ||
            !subject
        ) {

            return;
        }


        const categoryId =
            category.value;


        let currentValid =
            false;


        Array.from(
            subject.options
        ).forEach(
            function (
                option,
                index
            ) {

                if (
                    index === 0
                ) {

                    return;
                }


                const match =
                    option.dataset.categoryId ===
                    categoryId;


                option.hidden =
                    !match;


                if (
                    match &&
                    option.selected
                ) {

                    currentValid =
                        true;
                }

            }
        );


        if (
            !currentValid
        ) {

            subject.value =
                '';
        }
    }


    function validateStatus()
    {

        if (
            !examType ||
            !status
        ) {

            return true;
        }


        const liveExam =
            examType.value ===
            'Live';


        const liveStatuses = [

            'Scheduled',
            'Live'

        ];


        if (
            !liveExam &&
            liveStatuses.includes(
                status.value
            )
        ) {

            return false;
        }


        if (
            liveExam &&
            status.value ===
            'Active'
        ) {

            return false;
        }


        return true;
    }


    if (
        category
    ) {

        category.addEventListener(
            'change',
            filterSubjects
        );

        filterSubjects();
    }


    if (
        form
    ) {

        form.addEventListener(
            'submit',
            function (
                event
            ) {

                if (
                    !validateStatus()
                ) {

                    event.preventDefault();

                    alert(
                        'Practice exams cannot use Live/Scheduled status, and Live exams cannot use Active status.'
                    );

                    return;
                }


                const total =
                    Number(
                        document.getElementById(
                            'total_marks'
                        )?.value
                        || 0
                    );


                const passing =
                    Number(
                        document.getElementById(
                            'passing_marks'
                        )?.value
                        || 0
                    );


                if (
                    total <= 0 ||
                    passing < 0 ||
                    passing > total
                ) {

                    event.preventDefault();

                    alert(
                        'Please enter valid total and passing marks.'
                    );

                    return;
                }


                if (
                    startInput &&
                    endInput &&
                    startInput.value &&
                    endInput.value
                ) {

                    const start =
                        new Date(
                            startInput.value
                        );

                    const end =
                        new Date(
                            endInput.value
                        );


                    if (
                        end <= start
                    ) {

                        event.preventDefault();

                        alert(
                            'End date and time must be greater than start date and time.'
                        );
                    }
                }

            }
        );
    }

})();

</script>


<?php include "../includes/footer.php"; ?>