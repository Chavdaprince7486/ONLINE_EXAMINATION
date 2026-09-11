<?php
declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'teacher'
) {
    header('Location: ../auth/login.php');
    exit;
}

$teacherId = (int) $_SESSION['user_id'];

$message = '';
$error = '';

$title = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
$examType = (string)($_POST['exam_type'] ?? 'Practice');
$duration = filter_input(INPUT_POST, 'duration_minutes', FILTER_VALIDATE_INT);
$totalMarks = filter_input(INPUT_POST, 'total_marks', FILTER_VALIDATE_FLOAT);
$marksPerQuestion = filter_input(INPUT_POST, 'marks_per_question', FILTER_VALIDATE_FLOAT);
$passingMarks = filter_input(INPUT_POST, 'passing_marks', FILTER_VALIDATE_FLOAT);
$negativeEnabled = isset($_POST['negative_marking']);
$negativeMarks = filter_input(INPUT_POST, 'negative_marks', FILTER_VALIDATE_FLOAT);
$startsAt = trim((string)($_POST['starts_at'] ?? ''));
$endsAt = trim((string)($_POST['ends_at'] ?? ''));
$action = (string)($_POST['action'] ?? '');

if ($duration === false || $duration === null) $duration = 60;
if ($negativeMarks === false || $negativeMarks === null) $negativeMarks = 0.0;
if ($passingMarks === false || $passingMarks === null) $passingMarks = 0.0;

$posted = $_POST['question_ids'] ?? [];
if (!is_array($posted)) $posted = [];

$questionIds = [];
foreach ($posted as $id) {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    if ($id !== false && $id > 0) $questionIds[] = (int)$id;
}
$questionIds = array_values(array_unique($questionIds));

$csrfToken = function_exists('csrf_token')
    ? csrf_token()
    : (string)($_SESSION['csrf_token'] ?? '');

$subjects = $conn->query(
    "SELECT id, name, code
     FROM subjects
     WHERE status='Active'
     ORDER BY name ASC, id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$qStmt = $conn->prepare(
    "SELECT
        q.id,
        q.question_text,
        q.option_a,
        q.option_b,
        q.option_c,
        q.option_d,
        q.correct_answer,
        q.marks,
        q.negative_marks,
        q.difficulty,
        q.subject_id,
        s.name AS subject_name
     FROM questions q
     INNER JOIN subjects s
        ON s.id=q.subject_id
       AND s.status='Active'
     WHERE q.created_by_teacher_id=?
       AND q.status='Active'
     ORDER BY s.name ASC, q.id DESC"
);
$qStmt->execute([$teacherId]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

$requiredQuestions = 0;
$selectedMarks = 0.0;
$validQuestions = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $requestToken = trim((string)($_POST['csrf_token'] ?? ''));
    $csrfValid = false;

    if (function_exists('verify_csrf_token')) {
        $csrfValid = verify_csrf_token($requestToken);
    } elseif (
        isset($_SESSION['csrf_token']) &&
        $requestToken !== ''
    ) {
        $csrfValid = hash_equals(
            (string)$_SESSION['csrf_token'],
            $requestToken
        );
    }

    if (!$csrfValid) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } elseif ($title === '') {
        $error = 'Please enter an exam title.';
    } elseif (mb_strlen($title) > 180) {
        $error = 'Exam title cannot exceed 180 characters.';
    } elseif (
        $subjectId === false ||
        $subjectId === null ||
        $subjectId <= 0
    ) {
        $error = 'Please select a subject.';
    } elseif (!in_array($examType, ['Practice','Live'], true)) {
        $error = 'Invalid exam type.';
    } elseif (
        $duration === false ||
        $duration === null ||
        $duration < 1 ||
        $duration > 1440
    ) {
        $error = 'Duration must be between 1 and 1440 minutes.';
    } elseif (mb_strlen($description) > 1000) {
        $error = 'Exam description cannot exceed 1000 characters.';
    } elseif (
        $totalMarks === false ||
        $totalMarks === null ||
        $totalMarks <= 0
    ) {
        $error = 'Total Marks must be greater than 0.';
    } elseif (
        $marksPerQuestion === false ||
        $marksPerQuestion === null ||
        $marksPerQuestion <= 0
    ) {
        $error = 'Marks Per Question must be greater than 0.';
    }

    if ($error === '') {
        $ratio = (float)$totalMarks / (float)$marksPerQuestion;
        if (abs($ratio - round($ratio)) > 0.000001) {
            $error = 'Invalid Exam Configuration: Total Marks must be exactly divisible by Marks Per Question.';
        } else {
            $requiredQuestions = (int)round($ratio);
        }
    }

    if (
        $error === '' &&
        $requiredQuestions !== 50 &&
        $action === 'publish'
    ) {
        $error =
            'The current ExamSphere engine requires exactly 50 questions for a publishable exam. Configure Total Marks and Marks Per Question accordingly.';
    }

    if (
        $error === '' &&
        ($passingMarks < 0 || $passingMarks > (float)$totalMarks)
    ) {
        $error = 'Passing Marks must be between 0 and Total Marks.';
    }

    if ($negativeMarks < 0) {
        $error = 'Negative Marks must be 0 or greater.';
    }

    if (!$negativeEnabled) {
        $negativeMarks = 0.0;
    }

    if (
        $error === '' &&
        $negativeEnabled &&
        $negativeMarks > (float)$marksPerQuestion
    ) {
        $error = 'Negative Marks cannot be greater than Marks Per Question.';
    }

    $startValue = null;
    $endValue = null;

    if ($error === '' && $examType === 'Live') {

        if ($startsAt === '') {
            $error = 'A Live exam requires a Start Date & Time.';
        } else {

            $startTimestamp = strtotime($startsAt);

            if ($startTimestamp === false) {
                $error = 'Please enter a valid Live exam Start Date & Time.';
            } elseif ($startTimestamp <= time()) {
                $error = 'Live exam Start Date & Time must be in the future.';
            } else {
                $startValue = date('Y-m-d H:i:s', $startTimestamp);
            }
        }

        if (
            $error === '' &&
            $endsAt !== ''
        ) {

            $endTimestamp = strtotime($endsAt);

            if ($endTimestamp === false) {
                $error = 'Please enter a valid End Date & Time.';
            } elseif (
                $startValue !== null &&
                $endTimestamp <= strtotime($startValue)
            ) {
                $error = 'End Date & Time must be after Start Date & Time.';
            } else {
                $endValue = date('Y-m-d H:i:s', $endTimestamp);
            }
        }
    }

    if ($error === '') {

        $subjectCheck = $conn->prepare(
            "SELECT id
             FROM subjects
             WHERE id=?
               AND status='Active'
             LIMIT 1"
        );
        $subjectCheck->execute([(int)$subjectId]);

        if (!$subjectCheck->fetchColumn()) {
            $error = 'The selected subject is not available.';
        }
    }

    if (
        $error === '' &&
        !empty($questionIds)
    ) {

        $placeholders = implode(
            ',',
            array_fill(0, count($questionIds), '?')
        );

        $validationStmt = $conn->prepare(
            "SELECT
                id,
                marks,
                negative_marks
             FROM questions
             WHERE created_by_teacher_id=?
               AND subject_id=?
               AND status='Active'
               AND id IN ($placeholders)"
        );

        $validationStmt->execute(
            array_merge(
                [
                    $teacherId,
                    (int)$subjectId
                ],
                $questionIds
            )
        );

        $validQuestions =
            $validationStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        $validIds = array_map(
            'intval',
            array_column($validQuestions, 'id')
        );

        sort($validIds);

        $requestedIds = $questionIds;
        sort($requestedIds);

        if ($validIds !== $requestedIds) {
            $error =
                'One or more selected questions are unavailable, inactive, or do not belong to your question bank.';
        }
    }

    $selectedCount = count($validQuestions);

    foreach ($validQuestions as $q) {
        $selectedMarks += (float)$q['marks'];
    }

    $selectedMarks = round(
        $selectedMarks,
        2
    );

    if (
        $error === '' &&
        $action === 'draft' &&
        $selectedCount > $requiredQuestions
    ) {
        $error =
            'A draft cannot contain more than ' .
            $requiredQuestions .
            ' selected questions.';
    }

    if (
        $error === '' &&
        $action === 'publish' &&
        $selectedCount !== $requiredQuestions
    ) {

        if ($selectedCount < $requiredQuestions) {
            $missing =
                $requiredQuestions -
                $selectedCount;

            $error =
                'You must add exactly ' .
                $requiredQuestions .
                ' questions before publishing this exam. ' .
                $missing .
                ' more question' .
                ($missing === 1 ? '' : 's') .
                ' required.';
        } else {
            $extra =
                $selectedCount -
                $requiredQuestions;

            $error =
                'You selected ' .
                $selectedCount .
                ' questions. Remove ' .
                $extra .
                ' question' .
                ($extra === 1 ? '' : 's') .
                '.';
        }
    }

    if (
        $error === '' &&
        $action === 'publish' &&
        abs($selectedMarks - (float)$totalMarks) > 0.000001
    ) {
        $error =
            'Total question marks are ' .
            number_format($selectedMarks, 2) .
            ', but the exam requires ' .
            number_format((float)$totalMarks, 2) .
            ' marks.';
    }

    if (
        $error === '' &&
        $action === 'publish'
    ) {

        foreach ($validQuestions as $q) {

            if (
                abs(
                    (float)$q['marks'] -
                    (float)$marksPerQuestion
                ) > 0.000001
            ) {
                $error =
                    'All selected questions must use exactly ' .
                    number_format((float)$marksPerQuestion, 2) .
                    ' marks per question.';
                break;
            }
        }
    }

    if (
        $error === '' &&
        $action === ''
    ) {
        $error = 'Please choose Save Draft or Publish Exam.';
    }

    if ($error === '') {

        $status =
            $action === 'publish'
                ? 'Active'
                : 'Draft';

        try {

            $conn->beginTransaction();

            $insert = $conn->prepare(
                "INSERT INTO exams
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
                (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );

            $insert->execute([
                (int)$subjectId,
                $teacherId,
                $title,
                $description !== ''
                    ? $description
                    : null,
                $examType,
                (int)$duration,
                $requiredQuestions,
                round((float)$totalMarks, 2),
                round((float)$passingMarks, 2),
                $negativeEnabled ? 1 : 0,
                0,
                0,
                $startValue,
                $endValue,
                $status
            ]);

            $examId =
                (int)$conn->lastInsertId();

            $link =
                $conn->prepare(
                    "INSERT INTO exam_questions
                    (
                        exam_id,
                        question_id,
                        position
                    )
                    VALUES (?,?,?)"
                );

            $position = 1;

            foreach (
                $validQuestions
                as $question
            ) {

                $link->execute([
                    $examId,
                    (int)$question['id'],
                    $position
                ]);

                $position++;
            }

            $conn->commit();

            $message =
                $action === 'publish'
                    ? 'Exam published successfully with exactly 50 questions.'
                    : 'Exam saved as Draft successfully.';

            $title = '';
            $description = '';
            $subjectId = null;
            $examType = 'Practice';
            $duration = 60;
            $totalMarks = null;
            $marksPerQuestion = null;
            $passingMarks = null;
            $negativeEnabled = false;
            $negativeMarks = 0;
            $startsAt = '';
            $endsAt = '';
            $questionIds = [];
            $requiredQuestions = 0;
            $selectedMarks = 0.0;
            $validQuestions = [];

        } catch (Throwable $exception) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            error_log(
                'ExamSphere teacher create exam failed: ' .
                $exception->getMessage()
            );

            $error =
                'Exam could not be created. Please verify the values and try again.';
        }
    }
}

$requiredPreview = 0;

if (
    is_numeric($totalMarks) &&
    is_numeric($marksPerQuestion) &&
    (float)$totalMarks > 0 &&
    (float)$marksPerQuestion > 0
) {

    $ratio =
        (float)$totalMarks /
        (float)$marksPerQuestion;

    if (
        abs($ratio - round($ratio)) < 0.000001
    ) {
        $requiredPreview =
            (int)round($ratio);
    }
}



$old_title =
    $title;

$old_description =
    $description;

$old_subject =
    (
        $subjectId === false ||
        $subjectId === null
    )
        ? 0
        : (int) $subjectId;

$old_type =
    $examType;

$old_duration =
    (int) $duration;

$old_passing =
    is_numeric($passingMarks)
        ? (string) $passingMarks
        : '';

$old_starts_at =
    $startsAt;

$old_selected_ids =
    $questionIds;
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Create Exam | ExamSphere</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/portal.css">
    <link rel="stylesheet" href="assets/css/create-exam-pro.css">
    <link rel="stylesheet" href="assets/css/create-exam-complete.css">
</head>
<body class="portal-body create-exam-page">
<div class="portal-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="portal-main">
        <header class="portal-topbar create-exam-hero">
            <div>
                <div class="create-exam-eyebrow"><i class="fa-solid fa-wand-magic-sparkles"></i> Exam Builder</div>
                <h1>Create a professional exam</h1>
                <p class="portal-subtitle">Set the exam rules, preview each question, and select exactly the questions you want students to receive.</p>
            </div>
            <div class="create-exam-hero-actions">
                <a class="btn btn-light" href="questions.php"><i class="fa-solid fa-circle-plus me-2"></i>Question Bank</a>
                <a class="btn btn-outline-light" href="import_questions.php"><i class="fa-solid fa-file-csv me-2"></i>Import CSV</a>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="alert alert-success create-alert" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger create-alert" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" id="examForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>">
            <input type="hidden" id="initialSelectedIds" value="<?= htmlspecialchars(json_encode($old_selected_ids), ENT_QUOTES, 'UTF-8') ?>">

            <div class="create-exam-grid">
                <section class="portal-panel exam-settings-panel">
                    <div class="panel-heading-row">
                        <div>
                            <span class="section-kicker">01</span>
                            <h2>Exam settings</h2>
                            <p>Define how this examination will run.</p>
                        </div>
                        <span class="heading-icon"><i class="fa-solid fa-sliders"></i></span>
                    </div>

                    <div class="form-section">
                        <label class="form-label" for="examTitle">Exam title</label>
                        <input class="form-control" id="examTitle" name="title" value="<?= htmlspecialchars($old_title) ?>" maxlength="180" required placeholder="e.g. Cyber Security — Unit 1 Assessment">
                    </div>

                    <div class="form-section">
                        <label class="form-label" for="subjectId">Subject</label>
                        <select class="form-select" name="subject_id" id="subjectId" required>
                            <option value="">Select subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= (int) $subject['id'] ?>" <?= $old_subject === (int) $subject['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($subject['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="field-help">Only your active questions from this subject will be selectable.</small>
                    </div>

                    <div class="row g-3 form-section">
                        <div class="col-6">
                            <label class="form-label" for="examType">Exam mode</label>
                            <select class="form-select" name="exam_type" id="examType">
                                <option value="Practice" <?= $old_type === 'Practice' ? 'selected' : '' ?>>Practice</option>
                                <option value="Live" <?= $old_type === 'Live' ? 'selected' : '' ?>>Live</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="duration">Duration</label>
                            <div class="input-with-suffix">
                                <input class="form-control" id="duration" type="number" min="1" max="1440" name="duration_minutes" value="<?= $old_duration ?>" required>
                                <span>min</span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 form-section">
                        <div class="col-6">
                            <label class="form-label" for="passingMarks">Passing marks</label>
                            <input class="form-control" id="passingMarks" type="number" min="0" step="0.01" name="passing_marks" value="<?= htmlspecialchars($old_passing) ?>">
                            <small class="field-help">Cannot exceed total selected marks.</small>
                        </div>
                        <div class="col-6" id="liveScheduleField">
                            <label class="form-label" for="startsAt">Live start</label>
                            <input class="form-control" id="startsAt" type="datetime-local" name="starts_at" value="<?= htmlspecialchars($old_starts_at) ?>">
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0" for="description">Description</label>
                            <small class="text-muted"><span id="descriptionCount">0</span>/1000</small>
                        </div>
                        <textarea class="form-control" id="description" name="description" rows="5" maxlength="1000" placeholder="Add instructions, scope, or guidance for students."><?= htmlspecialchars($old_description) ?></textarea>
                    </div>

                    <div class="exam-settings-summary">
                        <div class="summary-icon"><i class="fa-solid fa-shield-halved"></i></div>
                        <div>
                            <strong>Teacher-owned question security</strong>
                            <span>Only active questions created by your teacher account are accepted by the server.</span>
                        </div>
                    </div>
                </section>

                
<section class="portal-panel rules-panel">
    <div class="panel-heading-row">
        <div>
            <span class="section-kicker">02</span>
            <h2>Marks & rules</h2>
            <p>Required Questions = Total Marks ÷ Marks Per Question.</p>
        </div>
        <span class="heading-icon"><i class="fa-solid fa-calculator"></i></span>
    </div>

    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label" for="totalMarks">Total Marks</label>
            <input class="form-control" id="totalMarks" name="total_marks" type="number" min="0.01" step="0.01"
                   value="<?= htmlspecialchars((string)($totalMarks ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
        </div>

        <div class="col-md-3">
            <label class="form-label" for="marksPerQuestion">Marks Per Question</label>
            <input class="form-control" id="marksPerQuestion" name="marks_per_question" type="number" min="0.01" step="0.01"
                   value="<?= htmlspecialchars((string)($marksPerQuestion ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
        </div>

        <div class="col-md-3">
            <label class="form-label">Required Questions</label>
            <div class="derived-value" id="requiredQuestionsValue">
                <?= $requiredPreview > 0 ? $requiredPreview : '—' ?>
            </div>
        </div>

        <div class="col-md-3">
            <label class="form-label" for="passingMarks">Passing Marks</label>
            <input class="form-control" id="passingMarks" name="passing_marks" type="number" min="0" step="0.01"
                   value="<?= htmlspecialchars((string)($passingMarks ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <label class="form-label" for="negativeMarks">Negative Marks / Wrong Answer</label>
            <input class="form-control" id="negativeMarks" name="negative_marks" type="number" min="0" step="0.01"
                   value="<?= htmlspecialchars((string)$negativeMarks, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </div>

        <div class="col-md-4 d-flex align-items-end">
            <label class="form-check form-switch premium-switch">
                <input class="form-check-input" id="negativeMarking" name="negative_marking" type="checkbox" value="1" <?= $negativeEnabled ? 'checked' : '' ?>>
                <span class="form-check-label">Enable negative marking</span>
            </label>
        </div>

        <div class="col-md-4">
            <div class="validation-banner" id="markConfigBanner" data-state="neutral">
                <i class="fa-solid fa-circle-info" id="markConfigIcon"></i>
                <div>
                    <strong id="markConfigTitle">Enter the mark values.</strong>
                    <span id="markConfigMessage">The required question count will be calculated automatically.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1 live-fields">
        <div class="col-md-6">
            <label class="form-label" for="startsAt">Start Date & Time</label>
            <input class="form-control" id="startsAt" name="starts_at" type="datetime-local"
                   value="<?= htmlspecialchars($startsAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="endsAt">End Date & Time</label>
            <input class="form-control" id="endsAt" name="ends_at" type="datetime-local"
                   value="<?= htmlspecialchars($endsAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </div>
    </div>
</section>

<section class="portal-panel question-builder-panel">

                    <div class="panel-heading-row question-builder-heading">
                        <div>
                            <span class="section-kicker">02</span>
                            <h2>Build your question set</h2>
                            <p>Search, preview, and select questions without losing your place.</p>
                        </div>
                        <div class="selection-pill" id="selectionSummary">
                            <strong id="selectedCount">0</strong>
                            <span>selected</span>
                            <em>•</em>
                            <strong id="selectedMarks">0.00</strong>
                            <span>marks</span>
                        </div>
                    </div>

                    <div class="question-toolbar">
                        <div class="question-search-wrap">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" id="questionSearch" placeholder="Search question text, subject or difficulty..." autocomplete="off">
                            <kbd>Ctrl</kbd><kbd>K</kbd>
                        </div>
                        <div class="question-filter-group">
                            <select id="difficultyFilter" class="form-select" aria-label="Filter by difficulty">
                                <option value="">All difficulty</option>
                                <option value="Easy">Easy</option>
                                <option value="Medium">Medium</option>
                                <option value="Hard">Hard</option>
                            </select>
                            <button type="button" class="btn btn-outline-secondary" id="selectVisibleBtn"><i class="fa-solid fa-check-double me-1"></i>Select visible</button>
                            <button type="button" class="btn btn-outline-secondary" id="clearSelectionBtn"><i class="fa-solid fa-eraser me-1"></i>Clear</button>
                        </div>
                    </div>

                    <div class="question-results-meta">
                        <span id="questionResultsCount">Select a subject to view its questions.</span>
                        <span id="questionEmptyHint" class="d-none"><i class="fa-regular fa-face-frown me-1"></i>No questions match these filters.</span>
                    </div>

                    <div class="question-list" id="questionList">
                        <?php foreach ($questions as $q): ?>
                            <?php
                            $options = [
                                'A' => $q['option_a'],
                                'B' => $q['option_b'],
                                'C' => $q['option_c'],
                                'D' => $q['option_d'],
                            ];
                            $options_json = [];
                            foreach ($options as $letter => $option) {
                                if ($option !== null && trim((string) $option) !== '') {
                                    $options_json[$letter] = (string) $option;
                                }
                            }
                            ?>
                            <article
                                class="question-card"
                                data-subject="<?= (int) $q['subject_id'] ?>"
                                data-difficulty="<?= htmlspecialchars($q['difficulty']) ?>"
                                data-search="<?= htmlspecialchars(mb_strtolower($q['question_text'] . ' ' . $q['subject_name'] . ' ' . $q['difficulty'])) ?>"
                            >
                                <div class="question-card-top">
                                    <label class="question-select-wrap">
                                        <input
                                            class="form-check-input exam-question"
                                            type="checkbox"
                                            name="question_ids[]"
                                            value="<?= (int) $q['id'] ?>"
                                            data-marks="<?= htmlspecialchars((string) $q['marks']) ?>"
                                        >
                                        <span class="question-select-copy">
                                            <span class="question-number">Q<?= (int) $q['id'] ?></span>
                                            <span class="question-subject"><?= htmlspecialchars($q['subject_name']) ?></span>
                                        </span>
                                    </label>
                                    <div class="question-badges">
                                        <span class="difficulty-badge difficulty-<?= strtolower(htmlspecialchars($q['difficulty'])) ?>"><?= htmlspecialchars($q['difficulty']) ?></span>
                                        <span class="marks-badge"><?= number_format((float) $q['marks'], 2) ?> marks</span>
                                    </div>
                                </div>

                                <div class="question-text"><?= nl2br(htmlspecialchars($q['question_text'])) ?></div>

                                <div class="question-card-bottom">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-preview preview-question-btn"
                                        data-question-id="<?= (int) $q['id'] ?>"
                                        data-question-text="<?= htmlspecialchars($q['question_text'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-subject="<?= htmlspecialchars($q['subject_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-difficulty="<?= htmlspecialchars($q['difficulty'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-marks="<?= htmlspecialchars((string) $q['marks'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-negative-marks="<?= htmlspecialchars((string) $q['negative_marks'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-correct-answer="<?= htmlspecialchars($q['correct_answer'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-options='<?= htmlspecialchars(json_encode($options_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>'
                                    >
                                        <i class="fa-regular fa-eye me-1"></i> Preview question
                                    </button>
                                    <span class="selection-state"><i class="fa-regular fa-circle"></i><span>Select to add</span></span>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <?php if (!$questions): ?>
                            <div class="empty-question-bank">
                                <div class="empty-question-icon"><i class="fa-solid fa-circle-question"></i></div>
                                <h3>Your question bank is empty</h3>
                                <p>Create questions first, then return here to build the exam.</p>
                                <a class="btn btn-success" href="questions.php"><i class="fa-solid fa-plus me-1"></i>Create question</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="sticky-create-bar">
                        <button type="button" class="btn btn-outline-secondary" id="resetQuestionsBtn">
                            <i class="fa-solid fa-rotate-left me-1"></i>Reset Questions
                        </button>
                        <div class="sticky-create-info">
                            <div class="mini-progress"><span id="selectionProgress"></span></div>
                            <strong><span id="stickySelectedCount">0</span> questions ready</strong>
                            <small>Total marks: <b id="stickySelectedMarks">0.00</b></small>
                        </div>
                        <button class="btn btn-outline-success btn-lg" type="submit" name="action" value="draft" id="saveDraftBtn" <?= !$questions ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-file-pen me-2"></i>Save Draft
                        </button>
                        <button class="btn btn-success btn-lg" type="submit" name="action" value="publish" id="publishExamBtn" <?= !$questions ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-cloud-arrow-up me-2"></i>Publish Exam
                        </button>
                    </div>
                </section>
            </div>
        </form>
    </main>
</div>

<div class="modal fade" id="questionPreviewModal" tabindex="-1" aria-labelledby="questionPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content preview-modal">
            <div class="modal-header">
                <div>
                    <span class="preview-kicker">Question preview</span>
                    <h2 class="modal-title" id="questionPreviewTitle">Question</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="preview-meta" id="previewMeta"></div>
                <div class="preview-question" id="previewQuestion"></div>
                <div class="preview-options" id="previewOptions"></div>
                <div class="preview-answer" id="previewAnswer"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="previewSelectBtn"><i class="fa-solid fa-check me-1"></i>Select this question</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/create-exam-complete.js" defer></script>
</body>
</html>
