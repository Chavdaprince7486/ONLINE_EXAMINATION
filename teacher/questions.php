<?php
declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';

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

function teacher_questions_e(mixed $value): string
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

$subjects = [];

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
        'Teacher subject loading failed: ' .
        $exception->getMessage()
    );

    $error =
        'Unable to load subjects.';
}

/*
|--------------------------------------------------------------------------
| Load topics through selected subject
|--------------------------------------------------------------------------
*/

$topics = [];

$selectedSubjectId = filter_var(
    $_POST['subject_id'] ?? '',
    FILTER_VALIDATE_INT
);

if (
    $selectedSubjectId !== false &&
    $selectedSubjectId !== null &&
    $selectedSubjectId > 0
) {

    try {

        $topicStatement = $conn->prepare("
            SELECT
                id,
                name
            FROM topics
            WHERE
                subject_id = ?
                AND status = 'Active'
            ORDER BY
                name ASC,
                id ASC
        ");

        $topicStatement->execute([
            (int)$selectedSubjectId
        ]);

        $topics = $topicStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

    } catch (Throwable $exception) {

        error_log(
            'Teacher topic loading failed: ' .
            $exception->getMessage()
        );
    }
}

/*
|--------------------------------------------------------------------------
| Create question
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($old as $key => $value) {

        if (
            isset($_POST[$key]) &&
            !is_array($_POST[$key])
        ) {

            $old[$key] =
                trim(
                    (string)$_POST[$key]
                );
        }
    }

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

    /*
     * Preserve values for the form.
     */
    $old['subject_id'] =
        $subjectId !== false &&
        $subjectId !== null
            ? (string)$subjectId
            : '';

    $old['topic_id'] =
        $topicId !== null
            ? (string)$topicId
            : '';

    $old['question_text'] =
        $questionText;

    $old['option_a'] =
        $optionA;

    $old['option_b'] =
        $optionB;

    $old['option_c'] =
        $optionC;

    $old['option_d'] =
        $optionD;

    $old['correct_answer'] =
        $correctAnswer;

    $old['explanation'] =
        $explanation;

    $old['marks'] =
        $marks !== false &&
        $marks !== null
            ? (string)$marks
            : '';

    $old['negative_marks'] =
        $negativeMarks !== false &&
        $negativeMarks !== null
            ? (string)$negativeMarks
            : '';

    $old['estimated_time_seconds'] =
        $estimatedTime !== false &&
        $estimatedTime !== null
            ? (string)$estimatedTime
            : '';

    $old['difficulty'] =
        $difficulty;

    $old['status'] =
        $status;

    /*
     * CSRF
     */
    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? null
        )
    ) {

        $error =
            'Security verification failed. Please refresh the page and try again.';
    }

    /*
     * Subject validation
     */
    if ($error === '') {

        if (
            $subjectId === false ||
            $subjectId === null ||
            $subjectId <= 0
        ) {

            $error =
                'Please select a valid subject.';

        } else {

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
                    (string)$subject['status'] !== 'Active'
                ) {

                    $error =
                        'Selected subject is inactive.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Subject validation failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to validate subject.';
            }
        }
    }

    /*
     * Topic validation
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
                (string)$topic['status'] !== 'Active'
            ) {

                $error =
                    'Selected topic is inactive.';
            }

        } catch (Throwable $exception) {

            error_log(
                'Topic validation failed: ' .
                $exception->getMessage()
            );

            $error =
                'Unable to validate topic.';
        }
    }

    /*
     * Question validation
     */
    if ($error === '') {

        if ($questionText === '') {

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
            $estimatedTime < 0
        ) {

            $error =
                'Estimated time cannot be negative.';

        } elseif (
            !in_array(
                $difficulty,
                ['Easy', 'Medium', 'Hard'],
                true
            )
        ) {

            $error =
                'Please select a valid difficulty.';

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
    }

    /*
     * Duplicate protection for this teacher.
     */
    if ($error === '') {

        try {

            $duplicateCheck = $conn->prepare("
                SELECT
                    id
                FROM questions
                WHERE
                    created_by_teacher_id = ?
                    AND question_text = ?
                LIMIT 1
            ");

            $duplicateCheck->execute([
                $teacherId,
                $questionText
            ]);

            if (
                $duplicateCheck->fetch(
                    PDO::FETCH_ASSOC
                )
            ) {

                $error =
                    'You already have a question with the same text in your question bank.';
            }

        } catch (Throwable $exception) {

            error_log(
                'Question duplicate validation failed: ' .
                $exception->getMessage()
            );

            $error =
                'Unable to validate duplicate question.';
        }
    }

    /*
     * Insert
     */
    if ($error === '') {

        try {

            $insertStatement = $conn->prepare("
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

            $insertStatement->execute([
                (int)$subjectId,
                $topicId,
                $teacherId,

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

            $_SESSION['success'] =
                'Question added successfully to your question bank.';

            header(
                'Location: questions.php'
            );

            exit;

        } catch (PDOException $exception) {

            error_log(
                'Teacher question insert failed: ' .
                $exception->getMessage()
            );

            $error =
                'Unable to save the question. Please try again.';

        } catch (Throwable $exception) {

            error_log(
                'Teacher question insert failed: ' .
                $exception->getMessage()
            );

            $error =
                'Unable to save the question.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Success message
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['success'])) {

    $message =
        (string)$_SESSION['success'];

    unset(
        $_SESSION['success']
    );
}

/*
|--------------------------------------------------------------------------
| Fetch teacher questions
|--------------------------------------------------------------------------
*/

$items = [];

try {

    $questionStatement = $conn->prepare("
        SELECT

            q.id,
            q.subject_id,
            q.topic_id,
            q.question_type,
            q.question_text,

            q.correct_answer,

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

        WHERE
            q.created_by_teacher_id = ?

        ORDER BY
            q.created_at DESC,
            q.id DESC
    ");

    $questionStatement->execute([
        $teacherId
    ]);

    $items =
        $questionStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Teacher question list failed: ' .
        $exception->getMessage()
    );

    if ($error === '') {

        $error =
            'Unable to load your questions.';
    }
}

$totalQuestions =
    count($items);

$activeQuestions = 0;
$inactiveQuestions = 0;

foreach ($items as $item) {

    if (
        (string)$item['status'] ===
        'Active'
    ) {

        $activeQuestions++;

    } else {

        $inactiveQuestions++;
    }
}

include 'includes/topbar.php';
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
        Teacher Question Bank | ExamSphere
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

        .question-page {
            max-width: 1500px;
            margin: 0 auto;
        }

        .question-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .question-header h1 {
            margin: 0;
            font-weight: 900;
            letter-spacing: -.03em;
        }

        .question-subtitle {
            margin: 7px 0 0;
            color: #746d68;
        }

        .question-stats {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .question-stat {
            padding: 18px;
            border: 1px solid rgba(93,64,55,.08);
            border-radius: 18px;
            background: rgba(255,255,255,.80);
            box-shadow:
                0 14px 35px rgba(62,45,37,.07);
        }

        .question-stat-label {
            color: #746d68;
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .question-stat-value {
            margin-top: 5px;
            color: #5d4037;
            font-size: 1.7rem;
            font-weight: 900;
        }

        .question-panel {
            border: 1px solid rgba(93,64,55,.08);
            border-radius: 20px;
            background: rgba(255,255,255,.82);
            box-shadow:
                0 18px 45px rgba(62,45,37,.08);
        }

        .question-panel-header {
            padding: 20px 22px;
            border-bottom: 1px solid #eee7df;
        }

        .question-panel-title {
            margin: 0;
            color: #5d4037;
            font-size: 1.05rem;
            font-weight: 900;
        }

        .question-panel-subtitle {
            margin-top: 4px;
            color: #746d68;
            font-size: .85rem;
        }

        .question-form {
            padding: 22px;
        }

        .question-form label {
            margin-bottom: 6px;
            color: #5d4037;
            font-size: .80rem;
            font-weight: 800;
        }

        .question-form .form-control,
        .question-form .form-select {
            border-color: #ddd3ca;
            border-radius: 11px;
            min-height: 45px;
        }

        .question-form textarea.form-control {
            min-height: 115px;
            resize: vertical;
        }

        .question-form .form-control:focus,
        .question-form .form-select:focus {
            border-color: #556b2f;
            box-shadow:
                0 0 0 .2rem rgba(85,107,47,.10);
        }

        .question-form .section-label {
            margin-top: 17px;
            margin-bottom: 10px;
            color: #556b2f;
            font-size: .76rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .question-submit {
            min-height: 47px;
            border: 0;
            border-radius: 12px;
            background: #5d4037;
            color: #fff;
            font-weight: 850;
            box-shadow:
                0 12px 25px rgba(93,64,55,.16);
        }

        .question-submit:hover {
            background: #4e352e;
            color: #fff;
        }

        .question-table-wrap {
            overflow-x: auto;
        }

        .question-table {
            min-width: 900px;
        }

        .question-table th {
            color: #5d4037;
            background: #faf7f0;
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .question-table td,
        .question-table th {
            padding: 14px 15px;
            vertical-align: middle;
        }

        .question-preview {
            max-width: 390px;
            color: #3f3936;
            line-height: 1.45;
        }

        .question-topic {
            font-size: .78rem;
            color: #746d68;
            margin-top: 3px;
        }

        .question-badge {
            display: inline-flex;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: .73rem;
            font-weight: 800;
        }

        .question-badge.active {
            color: #556b2f;
            background:
                rgba(85,107,47,.12);
        }

        .question-badge.inactive {
            color: #a83232;
            background:
                rgba(168,50,50,.10);
        }

        .question-empty {
            padding: 50px 20px;
            text-align: center;
            color: #746d68;
        }

        .question-empty-icon {
            width: 65px;
            height: 65px;
            margin: 0 auto 15px;
            display: grid;
            place-items: center;
            border-radius: 18px;
            color: #5d4037;
            background: #faf7f0;
            font-size: 25px;
        }

        @media (max-width: 1100px) {

            .question-stats {
                grid-template-columns:
                    repeat(3, minmax(0, 1fr));
            }

        }

        @media (max-width: 700px) {

            .question-stats {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body class="portal-body">

<div class="portal-layout">

    <?php include 'includes/sidebar.php'; ?>

    <main class="portal-main">

        <div class="question-page">

            <header class="question-header">

                <div>

                    <h1>
                        Question Bank
                    </h1>

                    <p class="question-subtitle">
                        Create structured questions using subjects and topics.
                    </p>

                </div>

            </header>

            <?php if ($message !== ''): ?>

                <div class="alert alert-success">

                    <i class="fa-solid fa-circle-check me-1"></i>

                    <?= teacher_questions_e($message) ?>

                </div>

            <?php endif; ?>

            <?php if ($error !== ''): ?>

                <div class="alert alert-danger">

                    <i class="fa-solid fa-circle-exclamation me-1"></i>

                    <?= teacher_questions_e($error) ?>

                </div>

            <?php endif; ?>

            <div class="question-stats">

                <div class="question-stat">

                    <div class="question-stat-label">
                        My Questions
                    </div>

                    <div class="question-stat-value">
                        <?= $totalQuestions ?>
                    </div>

                </div>

                <div class="question-stat">

                    <div class="question-stat-label">
                        Active
                    </div>

                    <div class="question-stat-value">
                        <?= $activeQuestions ?>
                    </div>

                </div>

                <div class="question-stat">

                    <div class="question-stat-label">
                        Inactive
                    </div>

                    <div class="question-stat-value">
                        <?= $inactiveQuestions ?>
                    </div>

                </div>

            </div>

            <div class="row g-4">

                <div class="col-xl-5">

                    <section class="question-panel">

                        <div class="question-panel-header">

                            <h2 class="question-panel-title">
                                Add MCQ Question
                            </h2>

                            <div class="question-panel-subtitle">
                                Connect every question to the correct academic structure.
                            </div>

                        </div>

                        <form
                            method="post"
                            class="question-form"
                        >

                            <?= csrf_field() ?>

                            <div class="section-label">
                                Academic Structure
                            </div>

                            <div class="mb-3">

                                <label for="subject_id">
                                    Subject *
                                </label>

                                <select
                                    class="form-select"
                                    id="subject_id"
                                    name="subject_id"
                                    required
                                    onchange="loadTeacherTopics(this.value)"
                                >

                                    <option value="">
                                        Select Subject
                                    </option>

                                    <?php foreach ($subjects as $subject): ?>

                                        <option
                                            value="<?= (int)$subject['id'] ?>"
                                            <?= (string)$old['subject_id'] ===
                                                (string)$subject['id']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= teacher_questions_e(
                                                $subject['name']
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="mb-3">

                                <label for="topic_id">
                                    Topic
                                </label>

                                <select
                                    class="form-select"
                                    id="topic_id"
                                    name="topic_id"
                                >

                                    <option value="">
                                        No Topic / Select Subject First
                                    </option>

                                    <?php foreach ($topics as $topic): ?>

                                        <option
                                            value="<?= (int)$topic['id'] ?>"
                                            <?= (string)$old['topic_id'] ===
                                                (string)$topic['id']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= teacher_questions_e(
                                                $topic['name']
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="section-label">
                                Question
                            </div>

                            <div class="mb-3">

                                <label for="question_text">
                                    Question Text *
                                </label>

                                <textarea
                                    class="form-control"
                                    id="question_text"
                                    name="question_text"
                                    required
                                ><?= teacher_questions_e(
                                    $old['question_text']
                                ) ?></textarea>

                            </div>

                            <div class="mb-3">

                                <label for="option_a">
                                    Option A *
                                </label>

                                <input
                                    class="form-control"
                                    id="option_a"
                                    name="option_a"
                                    value="<?= teacher_questions_e(
                                        $old['option_a']
                                    ) ?>"
                                    required
                                >

                            </div>

                            <div class="mb-3">

                                <label for="option_b">
                                    Option B *
                                </label>

                                <input
                                    class="form-control"
                                    id="option_b"
                                    name="option_b"
                                    value="<?= teacher_questions_e(
                                        $old['option_b']
                                    ) ?>"
                                    required
                                >

                            </div>

                            <div class="mb-3">

                                <label for="option_c">
                                    Option C *
                                </label>

                                <input
                                    class="form-control"
                                    id="option_c"
                                    name="option_c"
                                    value="<?= teacher_questions_e(
                                        $old['option_c']
                                    ) ?>"
                                    required
                                >

                            </div>

                            <div class="mb-3">

                                <label for="option_d">
                                    Option D *
                                </label>

                                <input
                                    class="form-control"
                                    id="option_d"
                                    name="option_d"
                                    value="<?= teacher_questions_e(
                                        $old['option_d']
                                    ) ?>"
                                    required
                                >

                            </div>

                            <div class="row g-2">

                                <div class="col-md-4">

                                    <label>
                                        Correct Answer *
                                    </label>

                                    <select
                                        class="form-select"
                                        name="correct_answer"
                                        required
                                    >

                                        <?php foreach (
                                            [
                                                'A',
                                                'B',
                                                'C',
                                                'D'
                                            ] as $answer
                                        ): ?>

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

                                <div class="col-md-4">

                                    <label>
                                        Marks *
                                    </label>

                                    <input
                                        class="form-control"
                                        type="number"
                                        name="marks"
                                        min="0.01"
                                        step="0.01"
                                        value="<?= teacher_questions_e(
                                            $old['marks']
                                        ) ?>"
                                        required
                                    >

                                </div>

                                <div class="col-md-4">

                                    <label>
                                        Negative Marks
                                    </label>

                                    <input
                                        class="form-control"
                                        type="number"
                                        name="negative_marks"
                                        min="0"
                                        step="0.01"
                                        value="<?= teacher_questions_e(
                                            $old['negative_marks']
                                        ) ?>"
                                    >

                                </div>

                            </div>

                            <div class="row g-2 mt-1">

                                <div class="col-md-6">

                                    <label>
                                        Difficulty
                                    </label>

                                    <select
                                        class="form-select"
                                        name="difficulty"
                                    >

                                        <option
                                            value="Easy"
                                            <?= $old['difficulty'] === 'Easy'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Easy
                                        </option>

                                        <option
                                            value="Medium"
                                            <?= $old['difficulty'] === 'Medium'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Medium
                                        </option>

                                        <option
                                            value="Hard"
                                            <?= $old['difficulty'] === 'Hard'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Hard
                                        </option>

                                    </select>

                                </div>

                                <div class="col-md-6">

                                    <label>
                                        Estimated Time (Seconds)
                                    </label>

                                    <input
                                        class="form-control"
                                        type="number"
                                        name="estimated_time_seconds"
                                        min="1"
                                        step="1"
                                        value="<?= teacher_questions_e(
                                            $old['estimated_time_seconds']
                                        ) ?>"
                                        placeholder="e.g. 45"
                                    >

                                </div>

                            </div>

                            <div class="section-label">
                                Explanation
                            </div>

                            <div class="mb-3">

                                <textarea
                                    class="form-control"
                                    name="explanation"
                                    placeholder="Explain why the selected answer is correct..."
                                ><?= teacher_questions_e(
                                    $old['explanation']
                                ) ?></textarea>

                            </div>

                            <div class="mb-3">

                                <label>
                                    Status
                                </label>

                                <select
                                    class="form-select"
                                    name="status"
                                >

                                    <option
                                        value="Active"
                                        <?= $old['status'] === 'Active'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Active
                                    </option>

                                    <option
                                        value="Inactive"
                                        <?= $old['status'] === 'Inactive'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Inactive
                                    </option>

                                </select>

                            </div>

                            <button
                                type="submit"
                                class="btn question-submit w-100"
                            >

                                <i class="fa-solid fa-plus me-1"></i>

                                Add Question

                            </button>

                        </form>

                    </section>

                </div>

                <div class="col-xl-7">

                    <section class="question-panel">

                        <div class="question-panel-header">

                            <h2 class="question-panel-title">
                                My Questions
                            </h2>

                            <div class="question-panel-subtitle">
                                Questions created by your teacher account.
                            </div>

                        </div>

                        <?php if (!$items): ?>

                            <div class="question-empty">

                                <div class="question-empty-icon">

                                    <i class="fa-regular fa-circle-question"></i>

                                </div>

                                <h5>
                                    No questions yet
                                </h5>

                                <p>
                                    Create your first question using the form.
                                </p>

                            </div>

                        <?php else: ?>

                            <div class="question-table-wrap">

                                <table
                                    class="table table-hover mb-0 question-table"
                                >

                                    <thead>

                                        <tr>

                                            <th>
                                                Question
                                            </th>

                                            <th>
                                                Subject / Topic
                                            </th>

                                            <th>
                                                Marks
                                            </th>

                                            <th>
                                                Difficulty
                                            </th>

                                            <th>
                                                Status
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach ($items as $item): ?>

                                            <tr>

                                                <td>

                                                    <div class="question-preview">

                                                        <?= teacher_questions_e(
                                                            mb_strimwidth(
                                                                (string)$item['question_text'],
                                                                0,
                                                                100,
                                                                '…'
                                                            )
                                                        ) ?>

                                                    </div>

                                                </td>

                                                <td>

                                                    <strong>

                                                        <?= teacher_questions_e(
                                                            $item['subject_name']
                                                            ?: 'No Subject'
                                                        ) ?>

                                                    </strong>

                                                    <div class="question-topic">

                                                        <?= teacher_questions_e(
                                                            $item['topic_name']
                                                            ?: 'No Topic'
                                                        ) ?>

                                                    </div>

                                                </td>

                                                <td>

                                                    <?= teacher_questions_e(
                                                        $item['marks']
                                                    ) ?>

                                                    <?php if (
                                                        (float)$item['negative_marks'] > 0
                                                    ): ?>

                                                        <div
                                                            class="small text-danger"
                                                        >
                                                            -
                                                            <?= teacher_questions_e(
                                                                $item['negative_marks']
                                                            ) ?>

                                                        </div>

                                                    <?php endif; ?>

                                                </td>

                                                <td>

                                                    <?= teacher_questions_e(
                                                        $item['difficulty']
                                                    ) ?>

                                                </td>

                                                <td>

                                                    <span
                                                        class="question-badge <?= strtolower(
                                                            (string)$item['status']
                                                        ) ?>"
                                                    >
                                                        <?= teacher_questions_e(
                                                            $item['status']
                                                        ) ?>
                                                    </span>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php endif; ?>

                    </section>

                </div>

            </div>

        </div>

    </main>

</div>

<script>

async function loadTeacherTopics(subjectId) {

    const topicSelect =
        document.getElementById('topic_id');

    if (!topicSelect) {
        return;
    }

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
                'get_topics.php?subject_id=' +
                encodeURIComponent(subjectId),
                {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

        if (!response.ok) {
            throw new Error(
                'Unable to load topics.'
            );
        }

        const data =
            await response.json();

        topicSelect.innerHTML =
            '<option value="">No Topic</option>';

        if (
            Array.isArray(data)
        ) {

            data.forEach(function(topic) {

                const option =
                    document.createElement('option');

                option.value =
                    topic.id;

                option.textContent =
                    topic.name;

                topicSelect.appendChild(
                    option
                );

            });
        }

    } catch (error) {

        topicSelect.innerHTML =
            '<option value="">Unable to load topics</option>';

    }
}

</script>

</body>
</html>