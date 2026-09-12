<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../config/exam_validation.php';
require_once '../../config/exam_builder.php';

if (
    (int)(
        $_SESSION['user_id'] ?? 0
    ) <= 0
    ||
    (
        $_SESSION['user_role'] ?? ''
    ) !== 'admin'
) {

    header(
        'Location: ../../auth/login.php'
    );

    exit;
}

$page_title =
    'Create Exam | ExamSphere';

$page_css =
    'admin-exam-builder.css';

$error =
    '';

$message =
    '';

$success_id =
    0;

/*
|--------------------------------------------------------------------------
| FORM VALUES
|--------------------------------------------------------------------------
*/

$examTitle =
    trim(
        (string)(
            $_POST['exam_title']
            ?? ''
        )
    );

$categoryId =
    filter_var(
        $_POST['category_id']
        ?? '',
        FILTER_VALIDATE_INT
    );

$subjectId =
    filter_var(
        $_POST['subject_id']
        ?? '',
        FILTER_VALIDATE_INT
    );

$teacherId =
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
            ?? 'Practice'
        )
    );

$questionCount =
    filter_var(
        $_POST['question_count']
        ?? '',
        FILTER_VALIDATE_INT
    );

$marksPerQuestion =
    filter_var(
        $_POST['marks_per_question']
        ?? '',
        FILTER_VALIDATE_FLOAT
    );

$passingMarks =
    filter_var(
        $_POST['passing_marks']
        ?? '',
        FILTER_VALIDATE_FLOAT
    );

$negativeMarking =
    isset(
        $_POST['negative_marking']
    );

$negativeMarks =
    filter_var(
        $_POST['negative_marks']
        ?? '0',
        FILTER_VALIDATE_FLOAT
    );

$duration =
    filter_var(
        $_POST['duration_minutes']
        ?? '60',
        FILTER_VALIDATE_INT
    );

$examFee =
    filter_var(
        $_POST['exam_fee']
        ?? '0',
        FILTER_VALIDATE_FLOAT
    );

$subscriptionRequired =
    isset(
        $_POST['subscription_required']
    );

$startsAtInput =
    trim(
        (string)(
            $_POST['start_datetime']
            ?? ''
        )
    );

$endsAtInput =
    trim(
        (string)(
            $_POST['end_datetime']
            ?? ''
        )
    );

$action =
    trim(
        (string)(
            $_POST['action']
            ?? ''
        )
    );

$source =
    trim(
        (string)(
            $_POST['question_source']
            ?? 'manual'
        )
    );

$selectedIds =
    $_POST['question_ids']
    ??
    [];

if (
    !is_array(
        $selectedIds
    )
) {

    $selectedIds =
        [];

}

$selectedIds =
    array_values(
        array_unique(
            array_filter(
                array_map(
                    'intval',
                    $selectedIds
                ),
                static fn(
                    int $id
                ): bool =>
                    $id > 0
            )
        )
    );

$questionCount =
    (
        $questionCount !== false
        &&
        $questionCount !== null
    )
        ? $questionCount
        : 0;

$marksPerQuestion =
    (
        $marksPerQuestion !== false
        &&
        $marksPerQuestion !== null
    )
        ? $marksPerQuestion
        : 0.0;

$passingMarks =
    (
        $passingMarks !== false
        &&
        $passingMarks !== null
    )
        ? $passingMarks
        : 0.0;

$negativeMarks =
    (
        $negativeMarks !== false
        &&
        $negativeMarks !== null
    )
        ? $negativeMarks
        : 0.0;

$duration =
    (
        $duration !== false
        &&
        $duration !== null
    )
        ? $duration
        : 60;

$examFee =
    (
        $examFee !== false
        &&
        $examFee !== null
    )
        ? $examFee
        : 0.0;

/*
|--------------------------------------------------------------------------
| DATABASE DATA
|--------------------------------------------------------------------------
*/

$categories =
    [];

$subjects =
    [];

$teachers =
    [];

$questions =
    [];

try {

    $categories =
        $conn
            ->query(
                "
                SELECT
                    id,
                    category_name

                FROM categories

                WHERE
                    status = 'Active'

                ORDER BY
                    category_name ASC,
                    id ASC
                "
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            );

    $subjects =
        $conn
            ->query(
                "
                SELECT
                    id,
                    category_id,
                    name

                FROM subjects

                WHERE
                    status = 'Active'

                ORDER BY
                    name ASC,
                    id ASC
                "
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            );

    $teachers =
        $conn
            ->query(
                "
                SELECT
                    id,
                    full_name

                FROM teachers

                WHERE
                    status = 'Active'

                ORDER BY
                    full_name ASC,
                    id ASC
                "
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            );

    $questions =
        $conn
            ->query(
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
                    q.status = 'Active'

                ORDER BY
                    q.id DESC
                "
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            );

} catch (
    Throwable $e
) {

    error_log(
        'Admin create exam data load failed: ' .
        $e->getMessage()
    );

    $error =
        'Unable to load exam configuration data.';
}

/*
|--------------------------------------------------------------------------
| FORM PROCESSING
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] ===
    'POST'
    &&
    $error === ''
) {

    if (
        !verify_csrf_token(
            $_POST['csrf_token']
            ?? null
        )
    ) {

        $error =
            'Invalid security token. Refresh the page and try again.';

    } elseif (
        $examTitle === ''
        ||
        mb_strlen(
            $examTitle
        ) > 180
    ) {

        $error =
            'Exam title is required and must not exceed 180 characters.';

    } elseif (
        $categoryId === false
        ||
        $categoryId < 1
    ) {

        $error =
            'Please select a category.';

    } elseif (
        $subjectId === false
        ||
        $subjectId < 1
    ) {

        $error =
            'Please select a subject.';

    } elseif (
        $teacherId === false
        ||
        $teacherId < 1
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
            'Invalid exam type.';

    } elseif (
        $questionCount < 1
        ||
        $questionCount >
        EXAM_BUILDER_MAX_QUESTIONS
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

    } elseif (
        $examFee < 0
    ) {

        $error =
            'Exam fee cannot be negative.';

    } elseif (
        mb_strlen(
            $examDescription
        ) > 5000
    ) {

        $error =
            'Exam description cannot exceed 5000 characters.';

    }

    if (
        $error === ''
        &&
        !$negativeMarking
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
        $startsAtInput !== ''
        &&
        $startsAt === null
    ) {

        $error =
            'Invalid start date and time.';

    }

    if (
        $error === ''
        &&
        $endsAtInput !== ''
        &&
        $endsAt === null
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

    /*
    |--------------------------------------------------------------------------
    | VERIFY SUBJECT + CATEGORY + TEACHER
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
    ) {

        $subjectCheck =
            $conn->prepare(
                "
                SELECT
                    id

                FROM subjects

                WHERE
                    id = ?

                    AND category_id = ?

                    AND status = 'Active'

                LIMIT 1
                "
            );

        $subjectCheck->execute([

            (int)$subjectId,

            (int)$categoryId

        ]);

        if (
            !$subjectCheck->fetchColumn()
        ) {

            $error =
                'Selected subject does not belong to the selected active category.';

        }

        $teacherCheck =
            $conn->prepare(
                "
                SELECT
                    id

                FROM teachers

                WHERE
                    id = ?

                    AND status = 'Active'

                LIMIT 1
                "
            );

        $teacherCheck->execute([

            (int)$teacherId

        ]);

        if (
            $error === ''
            &&
            !$teacherCheck->fetchColumn()
        ) {

            $error =
                'Selected teacher is not active.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | QUESTIONS
    |--------------------------------------------------------------------------
    */

    $questionRows =
        [];

    $existingRows =
        [];

    if (
        $error === ''
        &&
        $action === 'publish'
    ) {

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
                    $upload['error']
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
                            $upload['tmp_name'],

                            (int)
                            $subjectId,

                            (int)
                            $teacherId,

                            (float)
                            $marksPerQuestion,

                            (float)
                            $negativeMarks,

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

        } else {

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

                            (int)
                            $teacherId,

                            (float)
                            $marksPerQuestion,

                            (float)
                            $negativeMarks

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

    if (
        $error === ''
        &&
        $action === 'draft'
        &&
        count(
            $selectedIds
        ) >
        $questionCount
    ) {

        $error =
            'Draft cannot contain more questions than the configured question count.';
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
            $action === 'draft'
                ? 'Draft'
                : exam_builder_publish_status(
                    $examType,
                    $startsAt,
                    $endsAt
                );

        if (
            $examType === 'Practice'
            &&
            $action === 'publish'
            &&
            $status === 'Running'
        ) {

            $status =
                'Active';
        }

        try {

            if (
                $action === 'publish'
                &&
                count(
                    $questionRows
                ) !==
                $questionCount
                &&
                count(
                    $existingRows
                ) !==
                $questionCount
            ) {

                throw new RuntimeException(
                    'The examination cannot be published until the exact required number of questions is ready.'
                );
            }

            $exam = [

                'subject_id' =>
                    (int)$subjectId,

                'teacher_id' =>
                    (int)$teacherId,

                'title' =>
                    $examTitle,

                'description' =>
                    $examDescription,

                'exam_type' =>
                    $examType,

                'duration_minutes' =>
                    (int)$duration,

                'required_question_count' =>
                    (int)$questionCount,

                'marks_per_question' =>
                    (float)$marksPerQuestion,

                'passing_marks' =>
                    (float)$passingMarks,

                'negative_marking' =>
                    $negativeMarking
                        ? 1
                        : 0,

                'exam_fee' =>
                    (float)$examFee,

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

            $success_id =
                exam_builder_create_exam(

                    $conn,

                    $exam,

                    $selectedIds,

                    $questionRows,

                    0

                );

            $message =
                $action === 'publish'

                    ? 'Exam published successfully. Students will see it automatically when its status and schedule make it available.'

                    : 'Draft saved successfully. Students will not see a draft.';

            $examTitle =
                '';

            $examDescription =
                '';

            $categoryId =
                null;

            $subjectId =
                null;

            $teacherId =
                null;

            $questionCount =
                0;

            $marksPerQuestion =
                0;

            $passingMarks =
                0;

            $negativeMarking =
                false;

            $negativeMarks =
                0;

            $duration =
                60;

            $examFee =
                0;

            $subscriptionRequired =
                false;

            $startsAtInput =
                '';

            $endsAtInput =
                '';

            $selectedIds =
                [];

        } catch (
            Throwable $e
        ) {

            $error =
                'Exam could not be created: ' .
                $e->getMessage();

        }

    } elseif (
        $error === ''
        &&
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

include '../includes/header.php';

?>

<div class="dashboard-wrapper">

    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">

        <?php include '../includes/navbar.php'; ?>

        <div class="dashboard-content">

            <div class="exam-builder-page">

                <div class="exam-builder-header">

                    <div>

                        <span
                            class="
                                exam-builder-kicker
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-file-circle-plus
                                "
                            ></i>

                            EXAM CREATOR

                        </span>

                        <h1>
                            Create New Examination
                        </h1>

                        <p>

                            Build a professional
                            practice or live exam
                            with flexible questions,
                            marks and automated
                            publishing rules.

                        </p>

                    </div>

                    <a
                        href="index.php"
                        class="
                            exam-builder-back
                        "
                    >

                        <i
                            class="
                                fa-solid
                                fa-arrow-left
                            "
                        ></i>

                        Back to Exams

                    </a>

                </div>

                <?php if (
                    $error !== ''
                ): ?>

                    <div
                        class="
                            exam-builder-alert
                            error
                        "
                    >

                        <i
                            class="
                                fa-solid
                                fa-circle-exclamation
                            "
                        ></i>

                        <span>
                            <?= exam_builder_e(
                                $error
                            ) ?>
                        </span>

                    </div>

                <?php endif; ?>

                <?php if (
                    $message !== ''
                ): ?>

                    <div
                        class="
                            exam-builder-alert
                            success
                        "
                    >

                        <i
                            class="
                                fa-solid
                                fa-circle-check
                            "
                        ></i>

                        <span>

                            <?= exam_builder_e(
                                $message
                            ) ?>

                        </span>

                        <a
                            href="index.php"
                        >
                            View Exams
                        </a>

                    </div>

                <?php endif; ?>

                <form
                    id="adminExamForm"
                    method="post"
                    enctype="multipart/form-data"
                >

                    <?= csrf_field() ?>

                    <div class="builder-layout">

                        <div class="builder-main">

                            <section
                                class="
                                    exam-card
                                "
                            >

                                <div
                                    class="
                                        exam-card-header
                                    "
                                >

                                    <div
                                        class="
                                            section-number
                                        "
                                    >
                                        01
                                    </div>

                                    <div>

                                        <h2>
                                            Basic Information
                                        </h2>

                                        <p>
                                            Define the main details
                                            of your examination.
                                        </p>

                                    </div>

                                </div>

                                <div
                                    class="
                                        exam-form-grid
                                    "
                                >

                                    <div
                                        class="
                                            form-field
                                            span-2
                                        "
                                    >

                                        <label>
                                            Exam Title
                                            <span>*</span>
                                        </label>

                                        <div
                                            class="
                                                field-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-heading
                                                "
                                            ></i>

                                            <input
                                                type="text"
                                                name="exam_title"
                                                maxlength="180"
                                                value="<?= exam_builder_e(
                                                    $examTitle
                                                ) ?>"
                                                placeholder="e.g. UPSC General Studies Mock Test 01"
                                                required
                                            >

                                        </div>

                                    </div>

                                    <div
                                        class="
                                            form-field
                                        "
                                    >

                                        <label>
                                            Exam Type
                                            <span>*</span>
                                        </label>

                                        <div
                                            class="
                                                field-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-layer-group
                                                "
                                            ></i>

                                            <select
                                                name="exam_type"
                                                id="adminExamType"
                                                required
                                            >

                                                <option
                                                    value="Practice"
                                                    <?= $examType ===
                                                        'Practice'
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    Practice Exam
                                                </option>

                                                <option
                                                    value="Live"
                                                    <?= $examType ===
                                                        'Live'
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    Live Exam
                                                </option>

                                            </select>

                                        </div>

                                    </div>

                                    <div
                                        class="
                                            form-field
                                        "
                                    >

                                        <label>
                                            Category
                                            <span>*</span>
                                        </label>

                                        <div
                                            class="
                                                field-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-folder-tree
                                                "
                                            ></i>

                                            <select
                                                name="category_id"
                                                id="adminCategory"
                                                required
                                            >

                                                <option value="">
                                                    Select category
                                                </option>

                                                <?php foreach (
                                                    $categories
                                                    as $category
                                                ): ?>

                                                    <option
                                                        value="<?= (int)$category['id'] ?>"
                                                        <?= $categoryId ===
                                                            (int)$category['id']
                                                            ? 'selected'
                                                            : '' ?>
                                                    >

                                                        <?= exam_builder_e(
                                                            $category[
                                                                'category_name'
                                                            ]
                                                        ) ?>

                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>

                                    </div>

                                    <div
                                        class="
                                            form-field
                                        "
                                    >

                                        <label>
                                            Subject
                                            <span>*</span>
                                        </label>

                                        <div
                                            class="
                                                field-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-book
                                                "
                                            ></i>

                                            <select
                                                name="subject_id"
                                                id="adminSubject"
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
                                                        data-category-id="<?= (int)$subject['category_id'] ?>"
                                                        <?= $subjectId ===
                                                            (int)$subject['id']
                                                            ? 'selected'
                                                            : '' ?>
                                                    >

                                                        <?= exam_builder_e(
                                                            $subject[
                                                                'name'
                                                            ]
                                                        ) ?>

                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>

                                    </div>

                                    <div
                                        class="
                                            form-field
                                        "
                                    >

                                        <label>
                                            Assigned Teacher
                                            <span>*</span>
                                        </label>

                                        <div
                                            class="
                                                field-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-user-tie
                                                "
                                            ></i>

                                            <select
                                                name="teacher_id"
                                                required
                                            >

                                                <option value="">
                                                    Select teacher
                                                </option>

                                                <?php foreach (
                                                    $teachers
                                                    as $teacher
                                                ): ?>

                                                    <option
                                                        value="<?= (int)$teacher['id'] ?>"
                                                        <?= $teacherId ===
                                                            (int)$teacher['id']
                                                            ? 'selected'
                                                            : '' ?>
                                                    >

                                                        <?= exam_builder_e(
                                                            $teacher[
                                                                'full_name'
                                                            ]
                                                        ) ?>

                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>

                                    </div>

                                    <div
                                        class="
                                            form-field
                                            span-2
                                        "
                                    >

                                        <label>
                                            Description
                                        </label>

                                        <div
                                            class="
                                                field-control
                                                textarea-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-align-left
                                                "
                                            ></i>

                                            <textarea
                                                name="exam_description"
                                                maxlength="5000"
                                                rows="4"
                                                placeholder="Write a short description, instructions or exam purpose..."
                                            ><?= exam_builder_e(
                                                $examDescription
                                            ) ?></textarea>

                                        </div>

                                    </div>

                                </div>

                            </section>

                            <section
                                class="
                                    exam-card
                                "
                            >

                                <div
                                    class="
                                        exam-card-header
                                    "
                                >

                                    <div
                                        class="
                                            section-number
                                        "
                                    >
                                        02
                                    </div>

                                    <div>

                                        <h2>
                                            Question & Marking Structure
                                        </h2>

                                        <p>

                                            Total marks are calculated
                                            automatically.

                                        </p>

                                    </div>

                                </div>

                                <div
                                    class="
                                        exam-form-grid
                                    "
                                >

                                    <div
                                        class="
                                            form-field
                                        "
                                    >

                                        <label>
                                            Total Questions
                                            <span>*</span>
                                        </label>

                                        <div
                                            class="
                                                field-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-list-ol
                                                "
                                            ></i>

                                            <input
                                                id="adminQuestionCount"
                                                type="number"
                                                name="question_count"
                                                min="1"
                                                max="65535"
                                                step="1"
                                                value="<?= $questionCount > 0
                                                    ? exam_builder_e(
                                                        $questionCount
                                                    )
                                                    : '' ?>"
                                                placeholder="e.g. 50"
                                                required
                                            >

                                        </div>

                                    </div>

                                    <div
                                        class="
                                            form-field
                                        "
                                    >

                                        <label>
                                            Marks / Question
                                            <span>*</span>
                                        </label>

                                        <div
                                            class="
                                                field-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-star
                                                "
                                            ></i>

                                            <input
                                                id="adminMarksPerQuestion"
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
                                                placeholder="e.g. 2"
                                                required
                                            >

                                        </div>

                                    </div>

                                    <div
                                        class="
                                            form-field
                                        "
                                    >

                                        <label>
                                            Passing Marks
                                            <span>*</span>
                                        </label>

                                        <div
                                            class="
                                                field-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-bullseye
                                                "
                                            ></i>

                                            <input
                                                id="adminPassingMarks"
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

                                    </div>

                                    <div
                                        class="
                                            form-field
                                        "
                                    >

                                        <label>
                                            Duration
                                            <span>*</span>
                                        </label>

                                        <div
                                            class="
                                                field-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-regular
                                                    fa-clock
                                                "
                                            ></i>

                                            <input
                                                type="number"
                                                name="duration_minutes"
                                                min="1"
                                                max="65535"
                                                value="<?= exam_builder_e(
                                                    $duration
                                                ) ?>"
                                                placeholder="Minutes"
                                                required
                                            >

                                            <span
                                                class="
                                                    field-suffix
                                                "
                                            >
                                                min
                                            </span>

                                        </div>

                                    </div>

                                </div>

                                <div
                                    class="
                                        total-marks-box
                                    "
                                >

                                    <div>

                                        <span>
                                            AUTOMATIC TOTAL MARKS
                                        </span>

                                        <strong
                                            id="adminTotalMarks"
                                        >
                                            0.00
                                        </strong>

                                    </div>

                                    <div
                                        class="
                                            formula-box
                                        "
                                    >

                                        <span>
                                            Formula
                                        </span>

                                        <strong>
                                            Questions × Marks / Question
                                        </strong>

                                        <small>
                                            Maximum allowed: 300 marks
                                        </small>

                                    </div>

                                </div>

                                <div
                                    class="
                                        exam-form-grid
                                        mt-18
                                    "
                                >

                                    <div
                                        class="
                                            form-field
                                        "
                                    >

                                        <label>
                                            Negative Marking
                                        </label>

                                        <label
                                            class="
                                                toggle-card
                                            "
                                        >

                                            <input
                                                id="adminNegative"
                                                type="checkbox"
                                                name="negative_marking"
                                                <?= $negativeMarking
                                                    ? 'checked'
                                                    : '' ?>
                                            >

                                            <span
                                                class="
                                                    toggle-switch
                                                "
                                            ></span>

                                            <span>

                                                <strong>
                                                    Enable
                                                </strong>

                                                <small>
                                                    Deduct marks for wrong answers
                                                </small>

                                            </span>

                                        </label>

                                    </div>

                                    <div
                                        class="
                                            form-field
                                        "
                                    >

                                        <label>
                                            Negative Marks / Wrong Answer
                                        </label>

                                        <div
                                            class="
                                                field-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-circle-minus
                                                "
                                            ></i>

                                            <input
                                                id="adminNegativeMarks"
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

                                    </div>

                                    <div
                                        class="
                                            form-field
                                        "
                                    >

                                        <label>
                                            Exam Fee
                                        </label>

                                        <div
                                            class="
                                                field-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-indian-rupee-sign
                                                "
                                            ></i>

                                            <input
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

                                    </div>

                                    <div
                                        class="
                                            form-field
                                        "
                                    >

                                        <label>
                                            Subscription Access
                                        </label>

                                        <label
                                            class="
                                                toggle-card
                                            "
                                        >

                                            <input
                                                type="checkbox"
                                                name="subscription_required"
                                                <?= $subscriptionRequired
                                                    ? 'checked'
                                                    : '' ?>
                                            >

                                            <span
                                                class="
                                                    toggle-switch
                                                "
                                            ></span>

                                            <span>

                                                <strong>
                                                    Premium access
                                                </strong>

                                                <small>
                                                    Require an active subscription
                                                </small>

                                            </span>

                                        </label>

                                    </div>

                                </div>

                            </section>

                            <section
                                class="
                                    exam-card
                                "
                            >

                                <div
                                    class="
                                        exam-card-header
                                    "
                                >

                                    <div
                                        class="
                                            section-number
                                        "
                                    >
                                        03
                                    </div>

                                    <div>

                                        <h2>
                                            Schedule
                                        </h2>

                                        <p>

                                            Optional timing for
                                            publication and live exams.

                                        </p>

                                    </div>

                                </div>

                                <div
                                    class="
                                        exam-form-grid
                                    "
                                >

                                    <div
                                        class="
                                            form-field
                                        "
                                    >

                                        <label>
                                            Start Date & Time
                                        </label>

                                        <div
                                            class="
                                                field-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-regular
                                                    fa-calendar
                                                "
                                            ></i>

                                            <input
                                                type="datetime-local"
                                                name="start_datetime"
                                                value="<?= exam_builder_e(
                                                    $startsAtInput
                                                ) ?>"
                                            >

                                        </div>

                                    </div>

                                    <div
                                        class="
                                            form-field
                                        "
                                    >

                                        <label>
                                            End Date & Time
                                        </label>

                                        <div
                                            class="
                                                field-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-regular
                                                    fa-calendar-check
                                                "
                                            ></i>

                                            <input
                                                type="datetime-local"
                                                name="end_datetime"
                                                value="<?= exam_builder_e(
                                                    $endsAtInput
                                                ) ?>"
                                            >

                                        </div>

                                    </div>

                                </div>

                                <div
                                    class="
                                        schedule-info
                                    "
                                >

                                    <div>

                                        <i
                                            class="
                                                fa-solid
                                                fa-circle-info
                                            "
                                        ></i>

                                        <span>

                                            <strong>
                                                Practice exam
                                            </strong>

                                            can be published
                                            immediately or scheduled.

                                        </span>

                                    </div>

                                    <div>

                                        <i
                                            class="
                                                fa-solid
                                                fa-clock
                                            "
                                        ></i>

                                        <span>

                                            <strong>
                                                Live exam
                                            </strong>

                                            uses the schedule to determine
                                            Upcoming, Running and Completed.

                                        </span>

                                    </div>

                                </div>

                            </section>

                            <section
                                class="
                                    exam-card
                                "
                            >

                                <div
                                    class="
                                        exam-card-header
                                    "
                                >

                                    <div
                                        class="
                                            section-number
                                        "
                                    >
                                        04
                                    </div>

                                    <div>

                                        <h2>
                                            Add Questions
                                        </h2>

                                        <p>

                                            Choose questions manually
                                            or upload them directly using CSV.

                                        </p>

                                    </div>

                                </div>

                                <div
                                    class="
                                        source-tabs
                                    "
                                >

                                    <label
                                        class="
                                            source-tab
                                            active
                                        "
                                    >

                                        <input
                                            type="radio"
                                            name="question_source"
                                            value="manual"
                                            checked
                                        >

                                        <i
                                            class="
                                                fa-solid
                                                fa-list-check
                                            "
                                        ></i>

                                        <span>

                                            <strong>
                                                Question Bank
                                            </strong>

                                            <small>
                                                Select existing questions
                                            </small>

                                        </span>

                                    </label>

                                    <label
                                        class="
                                            source-tab
                                        "
                                    >

                                        <input
                                            type="radio"
                                            name="question_source"
                                            value="csv"
                                        >

                                        <i
                                            class="
                                                fa-solid
                                                fa-file-csv
                                            "
                                        ></i>

                                        <span>

                                            <strong>
                                                Direct CSV Upload
                                            </strong>

                                            <small>
                                                Upload all questions at once
                                            </small>

                                        </span>

                                    </label>

                                </div>

                                <div
                                    id="adminManualSource"
                                >

                                    <div
                                        class="
                                            question-toolbar
                                        "
                                    >

                                        <div
                                            class="
                                                field-control
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-magnifying-glass
                                                "
                                            ></i>

                                            <input
                                                id="adminQuestionSearch"
                                                type="search"
                                                placeholder="Search question, subject or difficulty..."
                                            >

                                        </div>

                                        <div
                                            class="
                                                question-counter
                                            "
                                        >

                                            <span>
                                                Selected
                                            </span>

                                            <strong
                                                id="adminSelectedCount"
                                            >
                                                0
                                            </strong>

                                            <span>
                                                /
                                            </span>

                                            <strong
                                                id="adminRequiredCount"
                                            >
                                                0
                                            </strong>

                                        </div>

                                    </div>

                                    <div
                                        class="
                                            question-picker
                                        "
                                    >

                                        <?php if (
                                            $questions
                                        ): ?>

                                            <?php foreach (
                                                $questions
                                                as $question
                                            ): ?>

                                                <label
                                                    class="
                                                        question-item
                                                        admin-question-row
                                                    "
                                                    data-search="<?= exam_builder_e(
                                                        strtolower(
                                                            $question[
                                                                'question_text'
                                                            ]
                                                            . ' ' .
                                                            $question[
                                                                'subject_name'
                                                            ]
                                                            . ' ' .
                                                            $question[
                                                                'difficulty'
                                                            ]
                                                        )
                                                    ) ?>"
                                                >

                                                    <input
                                                        class="
                                                            admin-question-check
                                                        "
                                                        type="checkbox"
                                                        name="question_ids[]"
                                                        value="<?= (int)$question['id'] ?>"
                                                        <?= in_array(
                                                            (int)$question['id'],
                                                            $selectedIds,
                                                            true
                                                        )
                                                            ? 'checked'
                                                            : '' ?>
                                                    >

                                                    <span
                                                        class="
                                                            question-check-ui
                                                        "
                                                    ></span>

                                                    <span
                                                        class="
                                                            question-content
                                                        "
                                                    >

                                                        <strong>

                                                            <?= exam_builder_e(
                                                                $question[
                                                                    'question_text'
                                                                ]
                                                            ) ?>

                                                        </strong>

                                                        <small>

                                                            <?= exam_builder_e(
                                                                $question[
                                                                    'subject_name'
                                                                ]
                                                            ) ?>

                                                            <span>
                                                                •
                                                            </span>

                                                            Marks:
                                                            <?= exam_builder_e(
                                                                $question[
                                                                    'marks'
                                                                ]
                                                            ) ?>

                                                            <span>
                                                                •
                                                            </span>

                                                            Negative:
                                                            <?= exam_builder_e(
                                                                $question[
                                                                    'negative_marks'
                                                                ]
                                                            ) ?>

                                                            <span>
                                                                •
                                                            </span>

                                                            <?= exam_builder_e(
                                                                $question[
                                                                    'difficulty'
                                                                ]
                                                            ) ?>

                                                        </small>

                                                    </span>

                                                </label>

                                            <?php endforeach; ?>

                                        <?php else: ?>

                                            <div
                                                class="
                                                    question-empty
                                                "
                                            >

                                                <i
                                                    class="
                                                        fa-solid
                                                        fa-circle-question
                                                    "
                                                ></i>

                                                <strong>
                                                    No active questions available
                                                </strong>

                                                <small>
                                                    Add questions to the question bank first.
                                                </small>

                                            </div>

                                        <?php endif; ?>

                                    </div>

                                </div>

                                <div
                                    id="adminCsvSource"
                                    class="csv-panel"
                                    hidden
                                >

                                    <div
                                        class="
                                            csv-upload-box
                                        "
                                    >

                                        <div
                                            class="
                                                csv-icon
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-file-csv
                                                "
                                            ></i>

                                        </div>

                                        <div>

                                            <h3>
                                                Upload Question CSV
                                            </h3>

                                            <p>

                                                The system will create
                                                the questions and attach
                                                them to this exam automatically.

                                            </p>

                                        </div>

                                        <label
                                            class="
                                                csv-select-btn
                                            "
                                        >

                                            <input
                                                type="file"
                                                name="questions_csv"
                                                accept=".csv,text/csv"
                                            >

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-upload
                                                "
                                            ></i>

                                            Choose CSV

                                        </label>

                                    </div>

                                    <div
                                        class="
                                            csv-requirements
                                        "
                                    >

                                        <h3>
                                            CSV Format
                                        </h3>

                                        <p>

                                            Required columns:

                                            <strong>
                                                question_text,
                                                option_a,
                                                option_b,
                                                correct_answer
                                            </strong>

                                        </p>

                                        <p>

                                            Optional:

                                            <strong>
                                                option_c,
                                                option_d,
                                                question_type,
                                                explanation,
                                                difficulty,
                                                topic_id,
                                                estimated_time_seconds
                                            </strong>

                                        </p>

                                        <pre>question_text,option_a,option_b,option_c,option_d,correct_answer,difficulty
What is 2 + 2?,3,4,5,6,B,Easy</pre>

                                    </div>

                                </div>

                                <div
                                    class="
                                        exact-rule
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-shield-halved
                                        "
                                    ></i>

                                    <span>

                                        <strong>
                                            Exact question rule
                                        </strong>

                                        Publishing is allowed only when
                                        the number of questions exactly
                                        matches the configured total.

                                        Example:

                                        <b>
                                            30 required = exactly 30 questions
                                        </b>

                                    </span>

                                </div>

                            </section>

                        </div>

                        <aside
                            class="
                                builder-side
                            "
                        >

                            <div
                                class="
                                    publish-card
                                "
                            >

                                <span
                                    class="
                                        publish-kicker
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-rocket
                                        "
                                    ></i>

                                    READY TO PUBLISH

                                </span>

                                <h3>
                                    Exam Summary
                                </h3>

                                <p>

                                    Review the main settings
                                    before creating the exam.

                                </p>

                                <div
                                    class="
                                        summary-list
                                    "
                                >

                                    <div>

                                        <span>
                                            Type
                                        </span>

                                        <strong
                                            id="summaryExamType"
                                        >
                                            Practice
                                        </strong>

                                    </div>

                                    <div>

                                        <span>
                                            Questions
                                        </span>

                                        <strong
                                            id="summaryQuestions"
                                        >
                                            0
                                        </strong>

                                    </div>

                                    <div>

                                        <span>
                                            Marks / Question
                                        </span>

                                        <strong
                                            id="summaryMarksPerQuestion"
                                        >
                                            0
                                        </strong>

                                    </div>

                                    <div>

                                        <span>
                                            Total Marks
                                        </span>

                                        <strong
                                            id="summaryTotalMarks"
                                        >
                                            0.00
                                        </strong>

                                    </div>

                                    <div>

                                        <span>
                                            Passing
                                        </span>

                                        <strong
                                            id="summaryPassingMarks"
                                        >
                                            0
                                        </strong>

                                    </div>

                                    <div>

                                        <span>
                                            Negative
                                        </span>

                                        <strong
                                            id="summaryNegative"
                                        >
                                            Disabled
                                        </strong>

                                    </div>

                                </div>

                                <div
                                    class="
                                        publish-status
                                    "
                                    id="publishStatus"
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-circle-info
                                        "
                                    ></i>

                                    <span>
                                        Complete the exam details and exact question count.
                                    </span>

                                </div>

                            </div>

                            <div
                                class="
                                    rule-card
                                "
                            >

                                <div
                                    class="
                                        rule-icon
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-calculator
                                        "
                                    ></i>

                                </div>

                                <h3>
                                    Automatic Mark Calculation
                                </h3>

                                <p>

                                    Total marks are never typed manually.

                                    <strong>
                                        Questions × Marks per Question
                                    </strong>

                                </p>

                                <div
                                    class="
                                        mini-formula
                                    "
                                >

                                    <span>
                                        25 questions
                                    </span>

                                    <b>
                                        ×
                                    </b>

                                    <span>
                                        4 marks
                                    </span>

                                    <b>
                                        =
                                    </b>

                                    <strong>
                                        100 marks
                                    </strong>

                                </div>

                            </div>

                            <div
                                class="
                                    rule-card
                                "
                            >

                                <div
                                    class="
                                        rule-icon green
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-users
                                        "
                                    ></i>

                                </div>

                                <h3>
                                    Student Visibility
                                </h3>

                                <p>

                                    Only a valid published
                                    active practice exam is
                                    shown to students.

                                </p>

                            </div>

                        </aside>

                    </div>

                    <div
                        class="
                            builder-footer
                        "
                    >

                        <a
                            href="index.php"
                            class="
                                footer-btn
                                cancel
                            "
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            name="action"
                            value="draft"
                            class="
                                footer-btn
                                draft
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-file-pen
                                "
                            ></i>

                            Save Draft

                        </button>

                        <button
                            type="submit"
                            name="action"
                            value="publish"
                            id="adminPublishBtn"
                            class="
                                footer-btn
                                publish
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-paper-plane
                                "
                            ></i>

                            Publish Exam

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

<script>

(() => {

    'use strict';

    const form =
        document.getElementById(
            'adminExamForm'
        );

    const category =
        document.getElementById(
            'adminCategory'
        );

    const subject =
        document.getElementById(
            'adminSubject'
        );

    const examType =
        document.getElementById(
            'adminExamType'
        );

    const questionCount =
        document.getElementById(
            'adminQuestionCount'
        );

    const marksPerQuestion =
        document.getElementById(
            'adminMarksPerQuestion'
        );

    const passingMarks =
        document.getElementById(
            'adminPassingMarks'
        );

    const negativeToggle =
        document.getElementById(
            'adminNegative'
        );

    const negativeMarks =
        document.getElementById(
            'adminNegativeMarks'
        );

    const totalMarks =
        document.getElementById(
            'adminTotalMarks'
        );

    const selectedCount =
        document.getElementById(
            'adminSelectedCount'
        );

    const requiredCount =
        document.getElementById(
            'adminRequiredCount'
        );

    const publishButton =
        document.getElementById(
            'adminPublishBtn'
        );

    const search =
        document.getElementById(
            'adminQuestionSearch'
        );

    const questionRows =
        Array.from(
            document.querySelectorAll(
                '.admin-question-row'
            )
        );

    const questionChecks =
        Array.from(
            document.querySelectorAll(
                '.admin-question-check'
            )
        );

    const sourceRadios =
        Array.from(
            document.querySelectorAll(
                'input[name="question_source"]'
            )
        );

    const manualSource =
        document.getElementById(
            'adminManualSource'
        );

    const csvSource =
        document.getElementById(
            'adminCsvSource'
        );

    const summaryType =
        document.getElementById(
            'summaryExamType'
        );

    const summaryQuestions =
        document.getElementById(
            'summaryQuestions'
        );

    const summaryMarks =
        document.getElementById(
            'summaryMarksPerQuestion'
        );

    const summaryTotal =
        document.getElementById(
            'summaryTotalMarks'
        );

    const summaryPassing =
        document.getElementById(
            'summaryPassingMarks'
        );

    const summaryNegative =
        document.getElementById(
            'summaryNegative'
        );

    const publishStatus =
        document.getElementById(
            'publishStatus'
        );


    function numberValue(
        input
    ) {

        const value =
            parseFloat(
                input?.value ||
                '0'
            );

        return Number.isFinite(
            value
        )
            ? value
            : 0;

    }


    function integerValue(
        input
    ) {

        const value =
            parseInt(
                input?.value ||
                '0',
                10
            );

        return Number.isFinite(
            value
        )
            ? value
            : 0;

    }


    function updateSubjects() {

        if (
            !subject
        ) {

            return;

        }

        const selectedCategory =
            category?.value ||
            '';

        Array.from(
            subject.options
        ).forEach(
            option => {

                if (
                    !option.value
                ) {

                    option.hidden =
                        false;

                    return;

                }

                const belongs =
                    option.dataset
                        .categoryId ===
                    selectedCategory;

                option.hidden =
                    Boolean(
                        selectedCategory
                    )
                    &&
                    !belongs;

            }
        );

        if (
            subject.value &&
            subject.selectedOptions[0]?.hidden
        ) {

            subject.value =
                '';

        }

    }


    function updateSourceUI() {

        const selected =
            document.querySelector(
                'input[name="question_source"]:checked'
            );

        sourceRadios.forEach(
            radio => {

                const wrapper =
                    radio.closest(
                        '.source-tab'
                    );

                wrapper?.classList.toggle(
                    'active',
                    radio ===
                    selected
                );

            }
        );

        const source =
            selected?.value ||
            'manual';

        if (
            manualSource
        ) {

            manualSource.hidden =
                source !==
                'manual';

        }

        if (
            csvSource
        ) {

            csvSource.hidden =
                source !==
                'csv';

        }

    }


    function selectedQuestions() {

        return questionChecks.filter(
            check =>
                check.checked
        ).length;

    }


    function updateSummary() {

        const questions =
            integerValue(
                questionCount
            );

        const marks =
            numberValue(
                marksPerQuestion
            );

        const passing =
            numberValue(
                passingMarks
            );

        const negative =
            numberValue(
                negativeMarks
            );

        const total =
            Math.round(
                questions *
                marks *
                100
            )
            / 100;

        if (
            totalMarks
        ) {

            totalMarks.textContent =
                total.toFixed(
                    2
                );

            totalMarks.classList.toggle(
                'danger',
                total >
                    300
            );

        }

        if (
            requiredCount
        ) {

            requiredCount.textContent =
                String(
                    questions
                );

        }

        if (
            selectedCount
        ) {

            selectedCount.textContent =
                String(
                    selectedQuestions()
                );

        }

        if (
            summaryType
        ) {

            summaryType.textContent =
                examType?.value ||
                'Practice';

        }

        if (
            summaryQuestions
        ) {

            summaryQuestions.textContent =
                String(
                    questions
                );

        }

        if (
            summaryMarks
        ) {

            summaryMarks.textContent =
                marks > 0
                    ? marks
                        .toFixed(
                            2
                        )
                        .replace(
                            /\.00$/,
                            ''
                        )
                    : '0';

        }

        if (
            summaryTotal
        ) {

            summaryTotal.textContent =
                total.toFixed(
                    2
                );

        }

        if (
            summaryPassing
        ) {

            summaryPassing.textContent =
                passing
                    .toFixed(
                        2
                    )
                    .replace(
                        /\.00$/,
                        ''
                    );

        }

        if (
            summaryNegative
        ) {

            summaryNegative.textContent =
                negativeToggle?.checked
                    ? (
                        negative > 0
                            ? negative
                                .toFixed(
                                    2
                                )
                                .replace(
                                    /\.00$/,
                                    ''
                                )
                            : '0'
                    )
                    : 'Disabled';

        }

        if (
            negativeMarks
        ) {

            negativeMarks.disabled =
                !Boolean(
                    negativeToggle?.checked
                );

            if (
                !negativeToggle?.checked
            ) {

                negativeMarks.value =
                    '0';

            }

            negativeMarks.max =
                marks > 0
                    ? String(
                        marks
                    )
                    : '';

        }

        if (
            publishButton
        ) {

            const invalidTotal =
                total <= 0 ||
                total > 300;

            const invalidPassing =
                passing < 0 ||
                passing >
                total;

            const invalidCount =
                questions < 1;

            publishButton.disabled =
                invalidTotal ||
                invalidPassing ||
                invalidCount;

        }

        if (
            publishStatus
        ) {

            const source =
                document.querySelector(
                    'input[name="question_source"]:checked'
                )?.value ||
                'manual';

            const selected =
                selectedQuestions();

            let text =
                'Complete the exam details and exact question count.';

            let type =
                'info';

            if (
                questions > 0
                &&
                total > 300
            ) {

                text =
                    'Total marks cannot exceed 300.';

                type =
                    'danger';

            } else if (
                passing >
                total &&
                total > 0
            ) {

                text =
                    'Passing marks cannot exceed total marks.';

                type =
                    'danger';

            } else if (
                source ===
                'manual'
                &&
                questions > 0
                &&
                selected !==
                questions
            ) {

                text =
                    'Select exactly ' +
                    questions +
                    ' questions to publish.';

                type =
                    'warning';

            } else if (
                source ===
                'csv'
                &&
                questions > 0
            ) {

                text =
                    'CSV upload must contain exactly ' +
                    questions +
                    ' questions.';

                type =
                    'info';

            } else if (
                questions > 0
                &&
                total <= 300
            ) {

                text =
                    'Exam configuration is ready for validation.';

                type =
                    'success';

            }

            publishStatus.dataset.type =
                type;

            publishStatus.querySelector(
                'span'
            ).textContent =
                text;

        }

    }


    questionChecks.forEach(
        check => {

            check.addEventListener(
                'change',
                () => {

                    const maximum =
                        integerValue(
                            questionCount
                        );

                    const selected =
                        selectedQuestions();

                    if (
                        maximum > 0
                        &&
                        selected >
                        maximum
                    ) {

                        check.checked =
                            false;

                    }

                    updateSummary();

                }
            );

        }
    );


    search?.addEventListener(
        'input',
        () => {

            const query =
                (
                    search.value ||
                    ''
                )
                    .toLowerCase()
                    .trim();

            questionRows.forEach(
                row => {

                    const value =
                        (
                            row.dataset
                                .search
                            ||
                            ''
                        ).toLowerCase();

                    row.hidden =
                        query !== ''
                        &&
                        !value.includes(
                            query
                        );

                }
            );

        }
    );


    sourceRadios.forEach(
        radio => {

            radio.addEventListener(
                'change',
                updateSourceUI
            );

        }
    );


    category?.addEventListener(
        'change',
        updateSubjects
    );


    [
        questionCount,
        marksPerQuestion,
        passingMarks,
        negativeMarks,
        negativeToggle,
        examType
    ].forEach(
        input => {

            input?.addEventListener(
                'input',
                updateSummary
            );

            input?.addEventListener(
                'change',
                updateSummary
            );

        }
    );


    form?.addEventListener(
        'submit',
        event => {

            updateSubjects();

            updateSummary();

            const submitter =
                event.submitter;

            const action =
                submitter?.value ||
                '';

            if (
                action !==
                'publish'
            ) {

                return;

            }

            const questions =
                integerValue(
                    questionCount
                );

            const marks =
                numberValue(
                    marksPerQuestion
                );

            const passing =
                numberValue(
                    passingMarks
                );

            const source =
                document.querySelector(
                    'input[name="question_source"]:checked'
                )?.value ||
                'manual';

            const total =
                Math.round(
                    questions *
                    marks *
                    100
                )
                / 100;

            if (
                total > 300
            ) {

                event.preventDefault();

                alert(
                    'Total marks cannot exceed 300.'
                );

                return;

            }

            if (
                passing >
                total
            ) {

                event.preventDefault();

                alert(
                    'Passing marks cannot exceed total marks.'
                );

                return;

            }

            if (
                source ===
                'manual'
                &&
                selectedQuestions() !==
                questions
            ) {

                event.preventDefault();

                alert(
                    'Please select exactly ' +
                    questions +
                    ' questions before publishing.'
                );

                return;

            }

            if (
                source ===
                'csv'
            ) {

                const file =
                    document.querySelector(
                        'input[name="questions_csv"]'
                    );

                if (
                    !file?.files?.length
                ) {

                    event.preventDefault();

                    alert(
                        'Please choose a CSV file before publishing.'
                    );

                    return;

                }

            }

            submitter.disabled =
                true;

            submitter.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> Publishing...';

        }
    );


    updateSubjects();

    updateSourceUI();

    updateSummary();

})();

</script>

</body>

</html>