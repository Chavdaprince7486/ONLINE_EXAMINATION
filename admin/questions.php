<?php
declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = 'Question Bank | ExamSphere';

$message = '';
$error = '';

$old = [
    'subject_id' => '',
    'topic_id' => '',
    'question_text' => '',
    'option_a' => '',
    'option_b' => '',
    'option_c' => '',
    'option_d' => '',
    'correct_answer' => 'A',
    'explanation' => '',
    'marks' => '1',
    'negative_marks' => '0',
    'estimated_time_seconds' => '',
    'difficulty' => 'Medium',
    'status' => 'Active'
];

function question_escape(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Load subjects
|--------------------------------------------------------------------------
*/

$subjects = [];

try {

    $subjectStatement = $conn->query("
        SELECT
            id,
            name,
            status
        FROM subjects
        ORDER BY
            name ASC,
            id ASC
    ");

    $subjects = $subjectStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $exception) {

    error_log(
        'Admin question subjects failed: ' .
        $exception->getMessage()
    );

    $error =
        'Unable to load subjects.';
}

/*
|--------------------------------------------------------------------------
| Load topics for selected subject
|--------------------------------------------------------------------------
*/

$topics = [];

$postedSubjectId = filter_var(
    $_POST['subject_id'] ?? '',
    FILTER_VALIDATE_INT
);

if (
    $postedSubjectId !== false &&
    $postedSubjectId !== null &&
    $postedSubjectId > 0
) {

    try {

        $topicStatement = $conn->prepare("
            SELECT
                id,
                name,
                status
            FROM topics
            WHERE
                subject_id = ?
            ORDER BY
                name ASC,
                id ASC
        ");

        $topicStatement->execute([
            (int)$postedSubjectId
        ]);

        $topics = $topicStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

    } catch (Throwable $exception) {

        error_log(
            'Admin question topics failed: ' .
            $exception->getMessage()
        );
    }
}

/*
|--------------------------------------------------------------------------
| POST actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = trim(
        (string)(
            $_POST['action'] ?? ''
        )
    );

    /*
    |--------------------------------------------------------------------------
    | ADD QUESTION
    |--------------------------------------------------------------------------
    */

    if ($action === 'add') {

        if (
            !verify_csrf_token(
                $_POST['csrf_token'] ?? null
            )
        ) {

            $error =
                'Security verification failed. Please refresh the page and try again.';

        } else {

            $subjectId = filter_var(
                $_POST['subject_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            $topicIdRaw = filter_var(
                $_POST['topic_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            $topicId =
                (
                    $topicIdRaw !== false &&
                    $topicIdRaw !== null &&
                    $topicIdRaw > 0
                )
                    ? (int)$topicIdRaw
                    : null;

            $questionText = trim(
                (string)(
                    $_POST['question_text'] ?? ''
                )
            );

            $optionA = trim(
                (string)(
                    $_POST['option_a'] ?? ''
                )
            );

            $optionB = trim(
                (string)(
                    $_POST['option_b'] ?? ''
                )
            );

            $optionC = trim(
                (string)(
                    $_POST['option_c'] ?? ''
                )
            );

            $optionD = trim(
                (string)(
                    $_POST['option_d'] ?? ''
                )
            );

            $correctAnswer = strtoupper(
                trim(
                    (string)(
                        $_POST['correct_answer'] ?? ''
                    )
                )
            );

            $explanation = trim(
                (string)(
                    $_POST['explanation'] ?? ''
                )
            );

            $marks = filter_var(
                $_POST['marks'] ?? '',
                FILTER_VALIDATE_FLOAT
            );

            $negativeMarks = filter_var(
                $_POST['negative_marks'] ?? '0',
                FILTER_VALIDATE_FLOAT
            );

            $estimatedTime = filter_var(
                $_POST['estimated_time_seconds'] ?? '',
                FILTER_VALIDATE_INT
            );

            $difficulty = trim(
                (string)(
                    $_POST['difficulty'] ?? ''
                )
            );

            $status = trim(
                (string)(
                    $_POST['status'] ?? ''
                )
            );

            $old = [
                'subject_id' =>
                    (
                        $subjectId !== false &&
                        $subjectId !== null
                    )
                        ? (string)$subjectId
                        : '',

                'topic_id' =>
                    $topicId !== null
                        ? (string)$topicId
                        : '',

                'question_text' =>
                    $questionText,

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

                'marks' =>
                    (
                        $marks !== false &&
                        $marks !== null
                    )
                        ? (string)$marks
                        : '',

                'negative_marks' =>
                    (
                        $negativeMarks !== false &&
                        $negativeMarks !== null
                    )
                        ? (string)$negativeMarks
                        : '',

                'estimated_time_seconds' =>
                    (
                        $estimatedTime !== false &&
                        $estimatedTime !== null
                    )
                        ? (string)$estimatedTime
                        : '',

                'difficulty' =>
                    $difficulty,

                'status' =>
                    $status
            ];

            /*
            |--------------------------------------------------------------------------
            | Basic validation
            |--------------------------------------------------------------------------
            */

            if (
                $subjectId === false ||
                $subjectId === null ||
                $subjectId <= 0
            ) {

                $error =
                    'Please select a valid subject.';

            } elseif ($questionText === '') {

                $error =
                    'Question text is required.';

            } elseif (
                mb_strlen($questionText) > 65535
            ) {

                $error =
                    'Question text is too long.';

            } elseif ($optionA === '') {

                $error =
                    'Option A is required.';

            } elseif ($optionB === '') {

                $error =
                    'Option B is required.';

            } elseif ($optionC === '') {

                $error =
                    'Option C is required.';

            } elseif ($optionD === '') {

                $error =
                    'Option D is required.';

            } elseif (
                !in_array(
                    $correctAnswer,
                    ['A', 'B', 'C', 'D'],
                    true
                )
            ) {

                $error =
                    'Please select a valid correct answer.';

            } elseif (
                $marks === false ||
                $marks === null ||
                $marks <= 0
            ) {

                $error =
                    'Marks must be greater than zero.';

            } elseif (
                $negativeMarks === false ||
                $negativeMarks === null ||
                $negativeMarks < 0
            ) {

                $error =
                    'Negative marks cannot be less than zero.';

            } elseif (
                $negativeMarks > $marks
            ) {

                $error =
                    'Negative marks cannot be greater than marks.';

            } elseif (
                $estimatedTime !== false &&
                $estimatedTime !== null &&
                $estimatedTime <= 0
            ) {

                $error =
                    'Estimated time must be greater than zero when provided.';

            } elseif (
                !in_array(
                    $difficulty,
                    ['Easy', 'Medium', 'Hard'],
                    true
                )
            ) {

                $error =
                    'Please select a valid difficulty level.';

            } elseif (
                !in_array(
                    $status,
                    ['Active', 'Inactive'],
                    true
                )
            ) {

                $error =
                    'Please select a valid status.';
            }

            /*
            |--------------------------------------------------------------------------
            | Subject validation
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

                try {

                    $subjectCheck = $conn->prepare("
                        SELECT
                            id,
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

                    if (!$subject) {

                        $error =
                            'Selected subject does not exist.';

                    } elseif (
                        (string)$subject['status'] !==
                        'Active'
                    ) {

                        $error =
                            'Selected subject is inactive.';
                    }

                } catch (Throwable $exception) {

                    error_log(
                        'Admin subject validation failed: ' .
                        $exception->getMessage()
                    );

                    $error =
                        'Unable to validate the selected subject.';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Topic validation
            |--------------------------------------------------------------------------
            */

            if (
                $error === '' &&
                $topicId !== null
            ) {

                try {

                    $topicCheck = $conn->prepare("
                        SELECT
                            id,
                            subject_id,
                            status
                        FROM topics
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $topicCheck->execute([
                        $topicId
                    ]);

                    $topic =
                        $topicCheck->fetch(
                            PDO::FETCH_ASSOC
                        );

                    if (!$topic) {

                        $error =
                            'Selected topic does not exist.';

                    } elseif (
                        (int)$topic['subject_id'] !==
                        (int)$subjectId
                    ) {

                        $error =
                            'Selected topic does not belong to the selected subject.';

                    } elseif (
                        (string)$topic['status'] !==
                        'Active'
                    ) {

                        $error =
                            'Selected topic is inactive.';
                    }

                } catch (Throwable $exception) {

                    error_log(
                        'Admin topic validation failed: ' .
                        $exception->getMessage()
                    );

                    $error =
                        'Unable to validate the selected topic.';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Duplicate question check
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

                try {

                    $duplicateCheck = $conn->prepare("
                        SELECT
                            id
                        FROM questions
                        WHERE
                            question_text = ?
                        LIMIT 1
                    ");

                    $duplicateCheck->execute([
                        $questionText
                    ]);

                    if (
                        $duplicateCheck->fetch(
                            PDO::FETCH_ASSOC
                        )
                    ) {

                        $error =
                            'A question with the same text already exists in the question bank.';
                    }

                } catch (Throwable $exception) {

                    error_log(
                        'Admin question duplicate check failed: ' .
                        $exception->getMessage()
                    );

                    $error =
                        'Unable to validate duplicate question.';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Insert
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

                try {

                    $insert = $conn->prepare("
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

                            'MCQ',
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

                    $insert->execute([
                        (int)$subjectId,
                        $topicId,

                        $questionText,

                        $optionA,
                        $optionB,
                        $optionC,
                        $optionD,

                        $correctAnswer,

                        $explanation !== ''
                            ? $explanation
                            : null,

                        (float)$marks,
                        (float)$negativeMarks,

                        (
                            $estimatedTime !== false &&
                            $estimatedTime !== null &&
                            $estimatedTime > 0
                        )
                            ? (int)$estimatedTime
                            : null,

                        $difficulty,
                        $status
                    ]);

                    $message =
                        'Question added successfully.';

                    $old = [
                        'subject_id' => '',
                        'topic_id' => '',
                        'question_text' => '',
                        'option_a' => '',
                        'option_b' => '',
                        'option_c' => '',
                        'option_d' => '',
                        'correct_answer' => 'A',
                        'explanation' => '',
                        'marks' => '1',
                        'negative_marks' => '0',
                        'estimated_time_seconds' => '',
                        'difficulty' => 'Medium',
                        'status' => 'Active'
                    ];

                } catch (Throwable $exception) {

                    error_log(
                        'Admin question insert failed: ' .
                        $exception->getMessage()
                    );

                    $error =
                        'Unable to save the question. Please try again.';
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE QUESTION
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete') {

        if (
            !verify_csrf_token(
                $_POST['csrf_token'] ?? null
            )
        ) {

            $error =
                'Security verification failed.';

        } else {

            $id = filter_var(
                $_POST['id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (
                $id === false ||
                $id === null ||
                $id <= 0
            ) {

                $error =
                    'Invalid question.';

            } else {

                try {

                    $used = $conn->prepare("
                        SELECT
                            COUNT(*)
                        FROM exam_questions
                        WHERE question_id = ?
                    ");

                    $used->execute([
                        (int)$id
                    ]);

                    $usage =
                        (int)$used->fetchColumn();

                    if ($usage > 0) {

                        $error =
                            'This question is already used in an exam and cannot be deleted. Deactivate it instead.';

                    } else {

                        $delete = $conn->prepare("
                            DELETE FROM questions
                            WHERE id = ?
                        ");

                        $delete->execute([
                            (int)$id
                        ]);

                        if (
                            $delete->rowCount() === 1
                        ) {

                            $message =
                                'Question deleted successfully.';

                        } else {

                            $error =
                                'Question was not found.';
                        }
                    }

                } catch (Throwable $exception) {

                    error_log(
                        'Admin question delete failed: ' .
                        $exception->getMessage()
                    );

                    $error =
                        'Unable to delete the question.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load questions
|--------------------------------------------------------------------------
*/

$questions = [];

try {

    $questionStatement = $conn->query("
        SELECT

            q.id,
            q.subject_id,
            q.topic_id,

            q.question_type,
            q.question_text,

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

            q.created_at,
            q.updated_at,

            s.name AS subject_name,
            t.name AS topic_name

        FROM questions q

        LEFT JOIN subjects s
            ON s.id = q.subject_id

        LEFT JOIN topics t
            ON t.id = q.topic_id

        ORDER BY
            q.created_at DESC,
            q.id DESC
    ");

    $questions =
        $questionStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Admin question listing failed: ' .
        $exception->getMessage()
    );

    $error =
        'Unable to load questions.';
}

$totalQuestions =
    count($questions);

$activeQuestions = 0;
$inactiveQuestions = 0;
$mcqQuestions = 0;

foreach ($questions as $question) {

    if (
        (string)$question['status'] === 'Active'
    ) {
        $activeQuestions++;
    } else {
        $inactiveQuestions++;
    }

    if (
        (string)$question['question_type'] === 'MCQ'
    ) {
        $mcqQuestions++;
    }
}

$page_css = 'admin-subjects.css';

include 'includes/header.php';

?>

<div class="dashboard-wrapper">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">

        <?php include 'includes/navbar.php'; ?>

        <main class="dashboard-content subject-page">

            <div class="subject-page-heading">

                <div>

                    <span>
                        <i class="fa-solid fa-circle-question"></i>
                        ASSESSMENT SETUP
                    </span>

                    <h1>
                        Question Bank
                    </h1>

                    <p>
                        Manage questions using subject and topic hierarchy.
                    </p>

                </div>

                <a
                    class="subject-anchor-button"
                    href="#add-question"
                >
                    <i class="fa-solid fa-plus"></i>
                    Add Question
                </a>

            </div>

            <?php if ($message !== ''): ?>

                <div class="subject-alert success">

                    <i class="fa-solid fa-circle-check"></i>

                    <?= question_escape($message) ?>

                </div>

            <?php endif; ?>

            <?php if ($error !== ''): ?>

                <div class="subject-alert error">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <?= question_escape($error) ?>

                </div>

            <?php endif; ?>

            <section class="subject-stat-row">

                <article>

                    <i class="fa-solid fa-circle-question"></i>

                    <span>
                        <small>
                            Total Questions
                        </small>

                        <b>
                            <?= $totalQuestions ?>
                        </b>
                    </span>

                </article>

                <article>

                    <i class="fa-solid fa-check-double"></i>

                    <span>
                        <small>
                            Active
                        </small>

                        <b>
                            <?= $activeQuestions ?>
                        </b>
                    </span>

                </article>

                <article>

                    <i class="fa-solid fa-toggle-off"></i>

                    <span>
                        <small>
                            Inactive
                        </small>

                        <b>
                            <?= $inactiveQuestions ?>
                        </b>
                    </span>

                </article>

                <article>

                    <i class="fa-solid fa-list-check"></i>

                    <span>
                        <small>
                            MCQ
                        </small>

                        <b>
                            <?= $mcqQuestions ?>
                        </b>
                    </span>

                </article>

            </section>

            <div class="subject-layout question-layout">

                <section
                    class="subject-form-card"
                    id="add-question"
                >

                    <div class="subject-card-heading">

                        <div>

                            <span class="mini-kicker">
                                CREATE
                            </span>

                            <h2>
                                Add MCQ Question
                            </h2>

                        </div>

                        <i class="fa-solid fa-square-plus"></i>

                    </div>

                    <?php if (!$subjects): ?>

                        <div class="subject-alert error">

                            <i class="fa-solid fa-book"></i>

                            Create a subject before adding questions.

                        </div>

                    <?php else: ?>

                        <form
                            method="post"
                            id="adminQuestionForm"
                        >

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="action"
                                value="add"
                            >

                            <label for="question-subject">
                                Subject
                            </label>

                            <div class="subject-input">

                                <i class="fa-solid fa-book"></i>

                                <select
                                    id="question-subject"
                                    name="subject_id"
                                    required
                                >

                                    <option value="">
                                        Select subject
                                    </option>

                                    <?php foreach ($subjects as $subject): ?>

                                        <?php
                                        if (
                                            (string)$subject['status']
                                            !== 'Active'
                                        ) {
                                            continue;
                                        }
                                        ?>

                                        <option
                                            value="<?= (int)$subject['id'] ?>"
                                            <?= $old['subject_id'] ===
                                                (string)$subject['id']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= question_escape(
                                                $subject['name']
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <label for="question-topic">
                                Topic
                            </label>

                            <div class="subject-input">

                                <i class="fa-solid fa-list-check"></i>

                                <select
                                    id="question-topic"
                                    name="topic_id"
                                >

                                    <option value="">
                                        No Topic / Select Subject First
                                    </option>

                                    <?php foreach ($topics as $topic): ?>

                                        <?php
                                        if (
                                            (string)$topic['status']
                                            !== 'Active'
                                        ) {
                                            continue;
                                        }
                                        ?>

                                        <option
                                            value="<?= (int)$topic['id'] ?>"
                                            <?= $old['topic_id'] ===
                                                (string)$topic['id']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= question_escape(
                                                $topic['name']
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <label for="question-text">
                                Question
                            </label>

                            <div class="subject-input textarea">

                                <i class="fa-solid fa-pen"></i>

                                <textarea
                                    id="question-text"
                                    name="question_text"
                                    placeholder="Write the question clearly"
                                    required
                                ><?= question_escape(
                                    $old['question_text']
                                ) ?></textarea>

                            </div>

                            <label>
                                Answer Options
                            </label>

                            <div class="question-option-grid">

                                <div class="subject-input">

                                    <b>A</b>

                                    <input
                                        name="option_a"
                                        value="<?= question_escape(
                                            $old['option_a']
                                        ) ?>"
                                        placeholder="Option A"
                                        required
                                    >

                                </div>

                                <div class="subject-input">

                                    <b>B</b>

                                    <input
                                        name="option_b"
                                        value="<?= question_escape(
                                            $old['option_b']
                                        ) ?>"
                                        placeholder="Option B"
                                        required
                                    >

                                </div>

                                <div class="subject-input">

                                    <b>C</b>

                                    <input
                                        name="option_c"
                                        value="<?= question_escape(
                                            $old['option_c']
                                        ) ?>"
                                        placeholder="Option C"
                                        required
                                    >

                                </div>

                                <div class="subject-input">

                                    <b>D</b>

                                    <input
                                        name="option_d"
                                        value="<?= question_escape(
                                            $old['option_d']
                                        ) ?>"
                                        placeholder="Option D"
                                        required
                                    >

                                </div>

                            </div>

                            <div class="question-settings">

                                <div>

                                    <label for="correct-answer">
                                        Correct Answer
                                    </label>

                                    <div class="subject-input">

                                        <select
                                            id="correct-answer"
                                            name="correct_answer"
                                            required
                                        >

                                            <?php
                                            foreach (
                                                [
                                                    'A',
                                                    'B',
                                                    'C',
                                                    'D'
                                                ]
                                                as $answer
                                            ):
                                            ?>

                                                <option
                                                    value="<?= $answer ?>"
                                                    <?= $old['correct_answer'] ===
                                                        $answer
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    <?= $answer ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>

                                </div>

                                <div>

                                    <label for="question-marks">
                                        Marks
                                    </label>

                                    <div class="subject-input">

                                        <input
                                            id="question-marks"
                                            name="marks"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            value="<?= question_escape(
                                                $old['marks']
                                            ) ?>"
                                            required
                                        >

                                    </div>

                                </div>

                                <div>

                                    <label for="negative-marks">
                                        Negative Marks
                                    </label>

                                    <div class="subject-input">

                                        <input
                                            id="negative-marks"
                                            name="negative_marks"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value="<?= question_escape(
                                                $old['negative_marks']
                                            ) ?>"
                                            required
                                        >

                                    </div>

                                </div>

                            </div>

                            <div class="question-settings">

                                <div>

                                    <label for="difficulty">
                                        Difficulty
                                    </label>

                                    <div class="subject-input">

                                        <select
                                            id="difficulty"
                                            name="difficulty"
                                            required
                                        >

                                            <option
                                                value="Easy"
                                                <?= $old['difficulty'] ===
                                                    'Easy'
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                Easy
                                            </option>

                                            <option
                                                value="Medium"
                                                <?= $old['difficulty'] ===
                                                    'Medium'
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                Medium
                                            </option>

                                            <option
                                                value="Hard"
                                                <?= $old['difficulty'] ===
                                                    'Hard'
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                Hard
                                            </option>

                                        </select>

                                    </div>

                                </div>

                                <div>

                                    <label for="estimated-time">
                                        Estimated Time
                                    </label>

                                    <div class="subject-input">

                                        <input
                                            id="estimated-time"
                                            name="estimated_time_seconds"
                                            type="number"
                                            min="1"
                                            step="1"
                                            value="<?= question_escape(
                                                $old['estimated_time_seconds']
                                            ) ?>"
                                            placeholder="Seconds"
                                        >

                                    </div>

                                </div>

                                <div>

                                    <label for="question-status">
                                        Status
                                    </label>

                                    <div class="subject-input">

                                        <select
                                            id="question-status"
                                            name="status"
                                            required
                                        >

                                            <option
                                                value="Active"
                                                <?= $old['status'] ===
                                                    'Active'
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                Active
                                            </option>

                                            <option
                                                value="Inactive"
                                                <?= $old['status'] ===
                                                    'Inactive'
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                Inactive
                                            </option>

                                        </select>

                                    </div>

                                </div>

                            </div>

                            <label for="question-explanation">
                                Explanation
                            </label>

                            <div class="subject-input textarea">

                                <i class="fa-solid fa-lightbulb"></i>

                                <textarea
                                    id="question-explanation"
                                    name="explanation"
                                    placeholder="Explain the correct answer..."
                                ><?= question_escape(
                                    $old['explanation']
                                ) ?></textarea>

                            </div>

                            <button
                                type="submit"
                                class="save-subject"
                            >

                                <i class="fa-solid fa-plus"></i>

                                Add Question

                            </button>

                        </form>

                    <?php endif; ?>

                </section>

                <section class="subject-list-card">

                    <div
                        class="subject-card-heading list-heading"
                    >

                        <div>

                            <span class="mini-kicker">
                                OVERVIEW
                            </span>

                            <h2>
                                All Questions
                            </h2>

                        </div>

                        <div class="list-tools">

                            <div class="subject-search">

                                <i
                                    class="fa-solid fa-magnifying-glass"
                                ></i>

                                <input
                                    id="questionSearch"
                                    type="search"
                                    placeholder="Search questions, subjects, topics..."
                                >

                            </div>

                            <select
                                id="questionDifficultyFilter"
                                class="table-filter"
                            >

                                <option value="">
                                    All levels
                                </option>

                                <option value="Easy">
                                    Easy
                                </option>

                                <option value="Medium">
                                    Medium
                                </option>

                                <option value="Hard">
                                    Hard
                                </option>

                            </select>

                            <select
                                id="questionStatusFilter"
                                class="table-filter"
                            >

                                <option value="">
                                    All status
                                </option>

                                <option value="Active">
                                    Active
                                </option>

                                <option value="Inactive">
                                    Inactive
                                </option>

                            </select>

                        </div>

                    </div>

                    <div class="subject-table-wrap">

                        <table
                            class="subject-table"
                            id="questionTable"
                        >

                            <thead>

                                <tr>

                                    <th>
                                        Question
                                    </th>

                                    <th>
                                        Subject
                                    </th>

                                    <th>
                                        Topic
                                    </th>

                                    <th>
                                        Answer
                                    </th>

                                    <th>
                                        Marks
                                    </th>

                                    <th>
                                        Level
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($questions as $question): ?>

                                <tr
                                    data-search="<?= question_escape(
                                        strtolower(
                                            (string)$question['question_text']
                                            . ' ' .
                                            (string)$question['subject_name']
                                            . ' ' .
                                            (string)$question['topic_name']
                                        )
                                    ) ?>"
                                    data-difficulty="<?= question_escape(
                                        $question['difficulty']
                                    ) ?>"
                                    data-status="<?= question_escape(
                                        $question['status']
                                    ) ?>"
                                >

                                    <td
                                        data-label="Question"
                                    >

                                        <b>

                                            <?= question_escape(
                                                $question['question_text']
                                            ) ?>

                                        </b>

                                        <small>
                                            <?= question_escape(
                                                $question['question_type']
                                            ) ?>
                                        </small>

                                    </td>

                                    <td
                                        data-label="Subject"
                                    >

                                        <span class="code-pill">

                                            <?= question_escape(
                                                $question['subject_name']
                                                ?: 'No Subject'
                                            ) ?>

                                        </span>

                                    </td>

                                    <td
                                        data-label="Topic"
                                    >

                                        <span class="code-pill">

                                            <?= question_escape(
                                                $question['topic_name']
                                                ?: 'No Topic'
                                            ) ?>

                                        </span>

                                    </td>

                                    <td
                                        data-label="Answer"
                                    >

                                        <span class="count-pill">

                                            <?= question_escape(
                                                $question['correct_answer']
                                            ) ?>

                                        </span>

                                    </td>

                                    <td
                                        data-label="Marks"
                                    >

                                        <b>

                                            <?= question_escape(
                                                $question['marks']
                                            ) ?>

                                            <?php if (
                                                (float)$question['negative_marks']
                                                > 0
                                            ): ?>

                                                /
                                                -
                                                <?= question_escape(
                                                    $question['negative_marks']
                                                ) ?>

                                            <?php endif; ?>

                                        </b>

                                    </td>

                                    <td
                                        data-label="Level"
                                    >

                                        <span
                                            class="status-pill active"
                                        >

                                            <?= question_escape(
                                                $question['difficulty']
                                            ) ?>

                                        </span>

                                    </td>

                                    <td
                                        data-label="Status"
                                    >

                                        <span
                                            class="status-pill <?= strtolower(
                                                (string)$question['status']
                                            ) ?>"
                                        >

                                            <?= question_escape(
                                                $question['status']
                                            ) ?>

                                        </span>

                                    </td>

                                    <td
                                        data-label="Action"
                                    >

                                        <form
                                            method="post"
                                            class="delete-confirm"
                                            onsubmit="return confirm('Are you sure you want to delete this question?');"
                                        >

                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$question['id'] ?>"
                                            >

                                            <button
                                                class="delete-subject"
                                                type="submit"
                                                aria-label="Delete question"
                                                title="Delete question"
                                            >

                                                <i
                                                    class="fa-solid fa-trash"
                                                ></i>

                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            <tr
                                class="subject-empty"
                                id="no-question-results"
                                style="<?= $questions ? 'display:none;' : '' ?>"
                            >

                                <td colspan="8">

                                    <i
                                        class="fa-solid fa-circle-question"
                                    ></i>

                                    No questions found.

                                </td>

                            </tr>

                            </tbody>

                        </table>

                    </div>

                </section>

            </div>

        </main>

    </div>

</div>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const subjectSelect =
            document.getElementById(
                'question-subject'
            );

        const topicSelect =
            document.getElementById(
                'question-topic'
            );

        const searchInput =
            document.getElementById(
                'questionSearch'
            );

        const difficultyFilter =
            document.getElementById(
                'questionDifficultyFilter'
            );

        const statusFilter =
            document.getElementById(
                'questionStatusFilter'
            );

        const table =
            document.getElementById(
                'questionTable'
            );

        if (
            subjectSelect &&
            topicSelect
        ) {

            subjectSelect.addEventListener(
                'change',
                async function () {

                    const subjectId =
                        this.value;

                    topicSelect.innerHTML =
                        '<option value="">Loading topics...</option>';

                    if (!subjectId) {

                        topicSelect.innerHTML =
                            '<option value="">No Topic / Select Subject First</option>';

                        return;
                    }

                    try {

                        const response =
                            await fetch(
                                '../teacher/get_topics.php?subject_id=' +
                                encodeURIComponent(
                                    subjectId
                                ),
                                {
                                    method: 'GET',
                                    headers: {
                                        'Accept':
                                            'application/json'
                                    }
                                }
                            );

                        if (!response.ok) {
                            throw new Error(
                                'Topic request failed.'
                            );
                        }

                        const data =
                            await response.json();

                        topicSelect.innerHTML =
                            '<option value="">No Topic</option>';

                        if (
                            Array.isArray(data)
                        ) {

                            data.forEach(
                                function (topic) {

                                    const option =
                                        document.createElement(
                                            'option'
                                        );

                                    option.value =
                                        topic.id;

                                    option.textContent =
                                        topic.name;

                                    topicSelect.appendChild(
                                        option
                                    );

                                }
                            );
                        }

                    } catch (error) {

                        topicSelect.innerHTML =
                            '<option value="">Unable to load topics</option>';
                    }
                }
            );
        }

        function filterQuestions() {

            if (!table) {
                return;
            }

            const rows =
                table.querySelectorAll(
                    'tbody tr[data-search]'
                );

            const search =
                (
                    searchInput
                        ? searchInput.value
                        : ''
                )
                    .trim()
                    .toLowerCase();

            const difficulty =
                difficultyFilter
                    ? difficultyFilter.value
                    : '';

            const status =
                statusFilter
                    ? statusFilter.value
                    : '';

            let visibleCount = 0;

            rows.forEach(
                function (row) {

                    const rowSearch =
                        (
                            row.dataset.search
                            || ''
                        ).toLowerCase();

                    const rowDifficulty =
                        row.dataset.difficulty
                        || '';

                    const rowStatus =
                        row.dataset.status
                        || '';

                    const matchesSearch =
                        search === '' ||
                        rowSearch.includes(
                            search
                        );

                    const matchesDifficulty =
                        difficulty === '' ||
                        rowDifficulty ===
                            difficulty;

                    const matchesStatus =
                        status === '' ||
                        rowStatus ===
                            status;

                    const visible =
                        matchesSearch &&
                        matchesDifficulty &&
                        matchesStatus;

                    row.style.display =
                        visible
                            ? ''
                            : 'none';

                    if (visible) {
                        visibleCount++;
                    }
                }
            );

            const emptyRow =
                document.getElementById(
                    'no-question-results'
                );

            if (emptyRow) {

                emptyRow.style.display =
                    visibleCount === 0
                        ? ''
                        : 'none';
            }
        }

        if (searchInput) {

            searchInput.addEventListener(
                'input',
                filterQuestions
            );
        }

        if (difficultyFilter) {

            difficultyFilter.addEventListener(
                'change',
                filterQuestions
            );
        }

        if (statusFilter) {

            statusFilter.addEventListener(
                'change',
                filterQuestions
            );
        }

    }
);

</script>

<script src="assets/js/admin-shell.js"></script>
<script src="assets/js/admin-questions.js"></script>

</body>
</html>