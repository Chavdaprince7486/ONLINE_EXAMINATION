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


$page_title =
    "Add Exam";


$error =
    "";


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$examTitle =
    (string)(
        $_POST['exam_title'] ?? ''
    );

$categoryId =
    (string)(
        $_POST['category_id'] ?? ''
    );

$subjectId =
    (string)(
        $_POST['subject_id'] ?? ''
    );

$teacherId =
    (string)(
        $_POST['teacher_id'] ?? ''
    );

$examDescription =
    (string)(
        $_POST['exam_description'] ?? ''
    );

$examType =
    (string)(
        $_POST['exam_type'] ?? 'Practice'
    );

$totalMarks =
    (string)(
        $_POST['total_marks'] ?? ''
    );

$passingMarks =
    (string)(
        $_POST['passing_marks'] ?? ''
    );

$durationMinutes =
    (string)(
        $_POST['duration_minutes'] ?? ''
    );

$negativeMarking =
    isset(
        $_POST['negative_marking']
    )
        ? 1
        : 0;

$examFee =
    (string)(
        $_POST['exam_fee'] ?? '0.00'
    );

$subscriptionRequired =
    isset(
        $_POST['subscription_required']
    )
        ? 1
        : 0;

$startDatetime =
    (string)(
        $_POST['start_datetime'] ?? ''
    );

$endDatetime =
    (string)(
        $_POST['end_datetime'] ?? ''
    );


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function admin_add_exam_escape(
    mixed $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function admin_add_exam_datetime(
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
| LOAD CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

try {

    $statement =
        $conn->query("
            SELECT
                id,
                category_name
            FROM categories
            WHERE status = 'Active'
            ORDER BY category_name ASC
        ");

    $categories =
        $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Admin add exam categories failed: ' .
        $exception->getMessage()
    );

    $error =
        'Unable to load categories.';
}


/*
|--------------------------------------------------------------------------
| LOAD SUBJECTS
|--------------------------------------------------------------------------
*/

$subjects = [];

try {

    $statement =
        $conn->query("
            SELECT
                id,
                category_id,
                name
            FROM subjects
            WHERE status = 'Active'
            ORDER BY name ASC
        ");

    $subjects =
        $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Admin add exam subjects failed: ' .
        $exception->getMessage()
    );

    if (
        $error === ''
    ) {

        $error =
            'Unable to load subjects.';
    }
}


/*
|--------------------------------------------------------------------------
| LOAD TEACHERS
|--------------------------------------------------------------------------
*/

$teachers = [];

try {

    $statement =
        $conn->query("
            SELECT
                id,
                full_name
            FROM teachers
            WHERE status = 'Active'
            ORDER BY full_name ASC
        ");

    $teachers =
        $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Admin add exam teachers failed: ' .
        $exception->getMessage()
    );

    if (
        $error === ''
    ) {

        $error =
            'Unable to load teachers.';
    }
}


/*
|--------------------------------------------------------------------------
| CREATE DRAFT EXAM
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    /*
    |--------------------------------------------------------------------------
    | INPUT NORMALIZATION
    |--------------------------------------------------------------------------
    */

    $examTitle =
        trim(
            (string)(
                $_POST['exam_title'] ?? ''
            )
        );

    $categoryIdValue =
        filter_var(
            $_POST['category_id'] ?? '',
            FILTER_VALIDATE_INT
        );

    $subjectIdValue =
        filter_var(
            $_POST['subject_id'] ?? '',
            FILTER_VALIDATE_INT
        );

    $teacherIdValue =
        filter_var(
            $_POST['teacher_id'] ?? '',
            FILTER_VALIDATE_INT
        );

    $examDescription =
        trim(
            (string)(
                $_POST['exam_description'] ?? ''
            )
        );

    $examType =
        trim(
            (string)(
                $_POST['exam_type'] ?? 'Practice'
            )
        );

    $totalMarksValue =
        filter_var(
            $_POST['total_marks'] ?? '',
            FILTER_VALIDATE_FLOAT
        );

    $passingMarksValue =
        filter_var(
            $_POST['passing_marks'] ?? '',
            FILTER_VALIDATE_FLOAT
        );

    $durationValue =
        filter_var(
            $_POST['duration_minutes'] ?? '',
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
            $_POST['exam_fee'] ?? '0',
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
                $_POST['start_datetime'] ?? ''
            )
        );

    $endDatetime =
        trim(
            (string)(
                $_POST['end_datetime'] ?? ''
            )
        );


    /*
    |--------------------------------------------------------------------------
    | PRESERVE VALUES
    |--------------------------------------------------------------------------
    */

    $examTitle =
        $examTitle;

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
            : '0.00';


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? null
        )
    ) {

        $error =
            'Invalid security token. Please refresh the page and try again.';

    } elseif (
        $examTitle === ''
    ) {

        $error =
            'Please enter the exam title.';

    } elseif (
        mb_strlen($examTitle) > 180
    ) {

        $error =
            'Exam title cannot exceed 180 characters.';

    } elseif (
        $categoryIdValue === false ||
        $categoryIdValue === null ||
        $categoryIdValue <= 0
    ) {

        $error =
            'Please select a category.';

    } elseif (
        $subjectIdValue === false ||
        $subjectIdValue === null ||
        $subjectIdValue <= 0
    ) {

        $error =
            'Please select a subject.';

    } elseif (
        $teacherIdValue === false ||
        $teacherIdValue === null ||
        $teacherIdValue <= 0
    ) {

        $error =
            'Please select a teacher.';

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
            'Invalid exam type selected.';

    } elseif (
        $totalMarksValue === false ||
        $totalMarksValue === null ||
        $totalMarksValue <= 0
    ) {

        $error =
            'Total marks must be greater than zero.';

    } elseif (
        $totalMarksValue > 999999.99
    ) {

        $error =
            'Total marks are too large.';

    } elseif (
        $passingMarksValue === false ||
        $passingMarksValue === null ||
        $passingMarksValue < 0
    ) {

        $error =
            'Passing marks cannot be negative.';

    } elseif (
        $passingMarksValue >
        $totalMarksValue
    ) {

        $error =
            'Passing marks cannot be greater than total marks.';

    } elseif (
        $durationValue === false ||
        $durationValue === null ||
        $durationValue < 1 ||
        $durationValue > 65535
    ) {

        $error =
            'Duration must be between 1 and 65535 minutes.';

    } elseif (
        $examFeeValue === false ||
        $examFeeValue === null ||
        $examFeeValue < 0
    ) {

        $error =
            'Exam fee cannot be negative.';

    } elseif (
        $examFeeValue > 9999999999.99
    ) {

        $error =
            'Exam fee is too large.';

    } elseif (
        $examDescription !== '' &&
        mb_strlen($examDescription) > 5000
    ) {

        $error =
            'Exam description cannot exceed 5000 characters.';
    }


    /*
    |--------------------------------------------------------------------------
    | DATE VALIDATION
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
    | CATEGORY → SUBJECT
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
                        AND status = 'Active'
                    LIMIT 1
                ");

            $statement->execute([
                (int)$subjectIdValue,
                (int)$categoryIdValue
            ]);


            if (
                !$statement->fetchColumn()
            ) {

                $error =
                    'The selected subject does not belong to the selected active category.';
            }

        } catch (Throwable $exception) {

            error_log(
                'Admin add exam subject validation failed: ' .
                $exception->getMessage()
            );

            $error =
                'Unable to validate the selected subject.';
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
                        AND status = 'Active'
                    LIMIT 1
                ");

            $statement->execute([
                (int)$teacherIdValue
            ]);


            if (
                !$statement->fetchColumn()
            ) {

                $error =
                    'The selected teacher is not available.';
            }

        } catch (Throwable $exception) {

            error_log(
                'Admin add exam teacher validation failed: ' .
                $exception->getMessage()
            );

            $error =
                'Unable to validate the selected teacher.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE DRAFT
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
    ) {

        try {

            $conn->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Final recheck
            |--------------------------------------------------------------------------
            */

            $subjectCheck =
                $conn->prepare("
                    SELECT
                        id
                    FROM subjects
                    WHERE
                        id = ?
                        AND category_id = ?
                        AND status = 'Active'
                    LIMIT 1
                    FOR UPDATE
                ");

            $subjectCheck->execute([
                (int)$subjectIdValue,
                (int)$categoryIdValue
            ]);


            if (
                !$subjectCheck->fetchColumn()
            ) {

                throw new RuntimeException(
                    'Selected subject is no longer available.'
                );
            }


            $teacherCheck =
                $conn->prepare("
                    SELECT
                        id
                    FROM teachers
                    WHERE
                        id = ?
                        AND status = 'Active'
                    LIMIT 1
                    FOR UPDATE
                ");

            $teacherCheck->execute([
                (int)$teacherIdValue
            ]);


            if (
                !$teacherCheck->fetchColumn()
            ) {

                throw new RuntimeException(
                    'Selected teacher is no longer available.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | REQUIRED COUNT IS ALWAYS 50
            |--------------------------------------------------------------------------
            */

            $requiredQuestionCount =
                EXAM_REQUIRED_QUESTION_COUNT;


            /*
            |--------------------------------------------------------------------------
            | New exam always starts as Draft
            |--------------------------------------------------------------------------
            */

            $insert =
                $conn->prepare("
                    INSERT INTO exams
                    (
                        subject_id,
                        teacher_id,

                        title,
                        description,

                        exam_type,

                        duration_minutes,
                        required_question_count,

                        total_marks,
                        passing_marks,

                        negative_marking,

                        exam_fee,
                        subscription_required,

                        starts_at,
                        ends_at,

                        status
                    )

                    VALUES
                    (
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

                        ?,
                        ?,

                        ?,
                        ?,

                        'Draft'
                    )
                ");


            $insert->execute([

                (int)$subjectIdValue,

                (int)$teacherIdValue,

                $examTitle,

                $examDescription !== ''
                    ? $examDescription
                    : null,

                $examType,

                (int)$durationValue,

                $requiredQuestionCount,

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

                $endValue

            ]);


            $examId =
                (int)$conn->lastInsertId();


            if (
                $examId <= 0
            ) {

                throw new RuntimeException(
                    'The exam could not be created.'
                );
            }


            $conn->commit();


            $_SESSION['success_message'] =
                'Draft exam created successfully. Exactly 50 active questions must be assigned before the exam can become ready.';


            header(
                'Location: ../exam_questions.php?exam_id=' .
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
                'Admin add exam failed: ' .
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

    .exam-add-page {
        max-width: 1250px;
        margin: 0 auto;
    }

    .exam-add-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 22px;
    }

    .exam-add-header h1 {
        margin: 0;
        color: #5d4037;
        font-weight: 950;
        letter-spacing: -.035em;
    }

    .exam-add-header p {
        margin: 7px 0 0;
        color: #746d68;
        line-height: 1.55;
    }

    .exam-add-header a {
        min-height: 44px;
        padding: 0 15px;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        background: #eee9df;
        color: #5d4037;
        font-weight: 850;
    }

    .exam-rule {
        display: flex;
        align-items: flex-start;
        gap: 13px;
        margin-bottom: 20px;
        padding: 16px 17px;
        border-radius: 15px;
        border: 1px solid rgba(85,107,47,.15);
        background: rgba(85,107,47,.07);
    }

    .exam-rule-icon {
        flex: 0 0 38px;
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        border-radius: 11px;
        background: rgba(85,107,47,.12);
        color: #556b2f;
    }

    .exam-rule strong {
        color: #5d4037;
        font-weight: 900;
    }

    .exam-rule span {
        display: block;
        margin-top: 3px;
        color: #746d68;
        font-size: .83rem;
        line-height: 1.55;
    }

    .exam-add-card {
        overflow: hidden;
        border-radius: 21px;
        background: rgba(255,255,255,.84);
        border: 1px solid rgba(93,64,55,.08);
        box-shadow:
            0 18px 45px rgba(62,45,37,.08);
    }

    .exam-add-card-head {
        padding: 20px 22px;
        border-bottom: 1px solid #eee7df;
    }

    .exam-add-card-head h2 {
        margin: 0;
        color: #5d4037;
        font-weight: 900;
        font-size: 1.05rem;
    }

    .exam-add-card-head p {
        margin: 5px 0 0;
        color: #746d68;
        font-size: .82rem;
    }

    .exam-add-card-body {
        padding: 23px;
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

    .exam-fixed-count {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 42px;
        padding: 0 13px;
        border-radius: 999px;
        background: rgba(85,107,47,.10);
        color: #556b2f;
        font-size: .77rem;
        font-weight: 900;
    }

    .exam-check {
        min-height: 46px;
        display: flex;
        align-items: center;
        padding: 0 13px;
        border-radius: 11px;
        border: 1px solid #ddd3ca;
        background: #faf7f0;
    }

    .exam-check label {
        margin: 0;
        color: #5d4037;
        font-size: .80rem;
        font-weight: 750;
    }

    .exam-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding-top: 21px;
        margin-top: 21px;
        border-top: 1px solid #eee7df;
        flex-wrap: wrap;
    }

    .exam-btn {
        min-height: 46px;
        padding: 0 18px;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        border: 0;
        font-weight: 900;
    }

    .exam-btn.cancel {
        color: #5d4037;
        background: #eee9df;
    }

    .exam-btn.save {
        color: #fff;
        background: #5d4037;
    }

    .exam-help {
        color: #746d68;
        font-size: .76rem;
        line-height: 1.5;
        margin-top: 5px;
    }

</style>


<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content">

            <div class="exam-add-page">

                <div class="exam-add-header">

                    <div>

                        <h1>
                            Add Exam
                        </h1>

                        <p>
                            Create a draft examination and then assign its 50 required questions.
                        </p>

                    </div>

                    <a href="index.php">

                        <i class="fa-solid fa-arrow-left"></i>

                        Back

                    </a>

                </div>


                <div class="exam-rule">

                    <div class="exam-rule-icon">

                        <i class="fa-solid fa-shield-check"></i>

                    </div>

                    <div>

                        <strong>
                            ExamSphere Fixed Rule: Exactly 50 Questions
                        </strong>

                        <span>
                            Every examination must contain exactly 50 Active questions.
                            An exam remains Draft until its question configuration passes validation.
                        </span>

                    </div>

                </div>


                <?php if ($error !== ''): ?>

                    <div
                        class="alert alert-danger"
                        role="alert"
                    >

                        <i
                            class="fa-solid fa-circle-exclamation me-1"
                        ></i>

                        <?= admin_add_exam_escape(
                            $error
                        ) ?>

                    </div>

                <?php endif; ?>


                <section class="exam-add-card">

                    <div class="exam-add-card-head">

                        <h2>
                            Examination Details
                        </h2>

                        <p>
                            All fields are validated again on the server before creation.
                        </p>

                    </div>


                    <div class="exam-add-card-body">

                        <form
                            method="post"
                            id="examForm"
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
                                        type="text"
                                        name="exam_title"
                                        class="form-control exam-control"
                                        maxlength="180"
                                        value="<?= admin_add_exam_escape(
                                            $examTitle
                                        ) ?>"
                                        required
                                        placeholder="e.g. UPSC General Studies Mock Test"
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

                                        <option value="">
                                            Select Category
                                        </option>

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

                                                <?= admin_add_exam_escape(
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

                                        <option value="">
                                            Select Subject
                                        </option>

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

                                                <?= admin_add_exam_escape(
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

                                        <option value="">
                                            Select Teacher
                                        </option>

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

                                                <?= admin_add_exam_escape(
                                                    $teacher['full_name']
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="col-lg-6">

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


                                <div class="col-lg-6">

                                    <label
                                        class="exam-label"
                                        for="duration_minutes"
                                    >
                                        Duration (Minutes) *
                                    </label>

                                    <input
                                        id="duration_minutes"
                                        type="number"
                                        name="duration_minutes"
                                        class="form-control exam-control"
                                        min="1"
                                        max="65535"
                                        value="<?= admin_add_exam_escape(
                                            $durationMinutes
                                        ) ?>"
                                        required
                                    >

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
                                        type="number"
                                        name="total_marks"
                                        class="form-control exam-control"
                                        min="0.01"
                                        max="999999.99"
                                        step="0.01"
                                        value="<?= admin_add_exam_escape(
                                            $totalMarks
                                        ) ?>"
                                        required
                                    >

                                    <div class="exam-help">
                                        Must exactly match the total marks of the final 50 questions.
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
                                        type="number"
                                        name="passing_marks"
                                        class="form-control exam-control"
                                        min="0"
                                        step="0.01"
                                        value="<?= admin_add_exam_escape(
                                            $passingMarks
                                        ) ?>"
                                        required
                                    >

                                </div>


                                <div class="col-lg-4">

                                    <label class="exam-label">
                                        Required Questions
                                    </label>

                                    <div class="exam-fixed-count">

                                        <i
                                            class="fa-solid fa-list-check"
                                        ></i>

                                        Exactly 50

                                    </div>

                                    <div class="exam-help">
                                        This value is controlled by ExamSphere and cannot be changed here.
                                    </div>

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
                                        type="number"
                                        name="exam_fee"
                                        class="form-control exam-control"
                                        min="0"
                                        max="9999999999.99"
                                        step="0.01"
                                        value="<?= admin_add_exam_escape(
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
                                        type="datetime-local"
                                        name="start_datetime"
                                        class="form-control exam-control"
                                        value="<?= admin_add_exam_escape(
                                            admin_add_exam_datetime(
                                                $startDatetime
                                            )
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
                                        type="datetime-local"
                                        name="end_datetime"
                                        class="form-control exam-control"
                                        value="<?= admin_add_exam_escape(
                                            admin_add_exam_datetime(
                                                $endDatetime
                                            )
                                        ) ?>"
                                    >

                                </div>


                                <div class="col-lg-6">

                                    <div class="exam-check">

                                        <input
                                            class="form-check-input me-2"
                                            type="checkbox"
                                            id="negative_marking"
                                            name="negative_marking"
                                            value="1"
                                            <?= $negativeMarking
                                                ? 'checked'
                                                : '' ?>
                                        >

                                        <label
                                            for="negative_marking"
                                        >
                                            Enable Negative Marking
                                        </label>

                                    </div>

                                </div>


                                <div class="col-lg-6">

                                    <div class="exam-check">

                                        <input
                                            class="form-check-input me-2"
                                            type="checkbox"
                                            id="subscription_required"
                                            name="subscription_required"
                                            value="1"
                                            <?= $subscriptionRequired
                                                ? 'checked'
                                                : '' ?>
                                        >

                                        <label
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
                                        placeholder="Exam instructions, syllabus coverage, or notes..."
                                    ><?= admin_add_exam_escape(
                                        $examDescription
                                    ) ?></textarea>

                                </div>


                            </div>


                            <div class="exam-actions">

                                <a
                                    href="index.php"
                                    class="exam-btn cancel"
                                >
                                    Cancel
                                </a>

                                <button
                                    type="submit"
                                    class="exam-btn save"
                                >

                                    <i
                                        class="fa-solid fa-plus"
                                    ></i>

                                    Create Draft Exam

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
            'examForm'
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


        let currentIsValid =
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

                    option.hidden =
                        false;

                    return;
                }


                const matches =
                    option.dataset.categoryId ===
                    categoryId;


                option.hidden =
                    !matches;


                if (
                    matches &&
                    option.selected
                ) {

                    currentIsValid =
                        true;
                }

            }
        );


        if (
            !currentIsValid
        ) {

            subject.value =
                '';
        }
    }


    function toggleLiveFields()
    {

        if (
            !startInput ||
            !endInput ||
            !examType
        ) {

            return;
        }


        const live =
            examType.value ===
            'Live';


        startInput.required =
            live;


        if (
            !live
        ) {

            startInput.required =
                false;
        }
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
        examType
    ) {

        examType.addEventListener(
            'change',
            toggleLiveFields
        );

        toggleLiveFields();
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
                    !form.checkValidity()
                ) {

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
                    total <= 0
                ) {

                    event.preventDefault();

                    alert(
                        'Total marks must be greater than zero.'
                    );

                    return;
                }


                if (
                    passing < 0 ||
                    passing > total
                ) {

                    event.preventDefault();

                    alert(
                        'Passing marks must be between zero and total marks.'
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