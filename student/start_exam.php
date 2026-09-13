<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/exam_validation.php';
require_once '../config/auth.php';

require_login('student');

$studentId = (int)($_SESSION['user_id'] ?? 0);

$examId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if ($examId === false || $examId === null || $examId <= 0) {
    http_response_code(400);
    exit('Invalid exam ID.');
}

$examId = (int)$examId;

function start_exam_escape(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function start_exam_error(string $message, int $status = 422): never
{
    http_response_code($status);
    echo '<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ExamSphere</title><style>:root{--brown:#5d4037;--dark:#3e2723;--cream:#f5f5dc;--muted:#766b63;--line:#e4d9cf}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:var(--cream);font-family:Arial,sans-serif;color:var(--dark)}.card{width:min(560px,100%);padding:36px;border:1px solid var(--line);border-radius:24px;background:#fff;box-shadow:0 20px 60px rgba(62,39,35,.12);text-align:center}.icon{width:64px;height:64px;margin:0 auto 18px;display:grid;place-items:center;border-radius:18px;background:#fff0ed;color:#a0453d;font-size:24px}h1{margin:0 0 10px;font-size:24px}p{margin:0;color:var(--muted);line-height:1.7}a{display:inline-block;margin-top:22px;padding:13px 18px;border-radius:12px;background:var(--brown);color:#fff;text-decoration:none;font-weight:700}</style></head><body><div class="card"><div class="icon">!</div><h1>Unable to continue</h1><p>' . start_exam_escape($message) . '</p><a href="practice_exams.php">Back to Practice Exams</a></div></body></html>';
    exit;
}

try {
    $examStmt = $conn->prepare(
        "SELECT
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
            s.name AS subject_name,
            s.code AS subject_code
         FROM exams e
         LEFT JOIN subjects s ON s.id=e.subject_id
         WHERE e.id=?
         LIMIT 1"
    );
    $examStmt->execute([$examId]);
    $exam = $examStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('Student start exam load failed: ' . $exception->getMessage());
    start_exam_error('Unable to load this examination right now.', 500);
}

if (!$exam) {
    start_exam_error('The examination could not be found.', 404);
}

$examType = (string)($exam['exam_type'] ?? '');
$examStatus = (string)($exam['status'] ?? '');
$requiredQuestionCount = (int)($exam['required_question_count'] ?? 0);
$durationMinutes = (int)($exam['duration_minutes'] ?? 0);
$totalMarks = round((float)($exam['total_marks'] ?? 0), 2);
$passingMarks = round((float)($exam['passing_marks'] ?? 0), 2);
$negativeMarking = (int)($exam['negative_marking'] ?? 0) === 1;
$subscriptionRequired = (int)($exam['subscription_required'] ?? 0) === 1;

if ($examType !== 'Practice') {
    start_exam_error('This page is for Practice Exams.');
}

if ($examStatus !== 'Active') {
    start_exam_error('This practice examination is not currently available.');
}

if ($requiredQuestionCount < 1) {
    start_exam_error('This examination has an invalid question count.');
}

if ($durationMinutes < 1) {
    start_exam_error('This examination has an invalid duration.');
}

if ($totalMarks <= 0 || $passingMarks < 0 || $passingMarks > $totalMarks) {
    start_exam_error('This examination has an invalid marks configuration.');
}

$now = new DateTimeImmutable();
$startsAt = null;
$endsAt = null;

try {
    if (!empty($exam['starts_at'])) {
        $startsAt = new DateTimeImmutable((string)$exam['starts_at']);
    }
    if (!empty($exam['ends_at'])) {
        $endsAt = new DateTimeImmutable((string)$exam['ends_at']);
    }
} catch (Throwable $exception) {
    start_exam_error('This examination has an invalid schedule. Please contact the administrator.');
}

if ($startsAt !== null && $now < $startsAt) {
    start_exam_error('This practice examination has not started yet.');
}

if ($endsAt !== null && $now > $endsAt) {
    start_exam_error('This practice examination has ended.');
}

try {
    if ($subscriptionRequired && !has_active_subscription($conn, $studentId)) {
        header('Location: subscriptions.php');
        exit;
    }

    $validation = validate_exam_from_database($conn, $examId);

    if (!$validation['valid']) {
        $validationMessage = (string)($validation['validation']['message'] ?? 'This examination is not ready.');
        start_exam_error($validationMessage);
    }

    $validationData = $validation['validation'];

    if ((int)($validationData['required_question_count'] ?? 0) !== $requiredQuestionCount) {
        start_exam_error('This examination is not ready because its question count configuration is inconsistent.');
    }

    if (abs((float)($validationData['actual_marks'] ?? 0) - $totalMarks) > 0.000001) {
        start_exam_error('This examination is not ready because its total marks configuration is inconsistent.');
    }
} catch (Throwable $exception) {
    error_log('Student start exam validation failed: ' . $exception->getMessage());
    start_exam_error('Unable to verify examination eligibility right now.', 500);
}

try {
    $questionCheck = $conn->prepare(
        "SELECT
            COUNT(DISTINCT eq.question_id) AS question_count,
            COUNT(DISTINCT CASE WHEN q.status='Active' THEN eq.question_id END) AS active_question_count,
            COALESCE(SUM(CASE WHEN q.status='Active' THEN q.marks ELSE 0 END),0) AS active_marks
         FROM exam_questions eq
         INNER JOIN questions q ON q.id=eq.question_id
         WHERE eq.exam_id=?"
    );
    $questionCheck->execute([$examId]);
    $questionData = $questionCheck->fetch(PDO::FETCH_ASSOC) ?: [];

    $questionCount = (int)($questionData['question_count'] ?? 0);
    $activeQuestionCount = (int)($questionData['active_question_count'] ?? 0);
    $activeMarks = round((float)($questionData['active_marks'] ?? 0), 2);

    if ($questionCount !== $requiredQuestionCount || $activeQuestionCount !== $requiredQuestionCount) {
        start_exam_error('This practice examination is not ready because its active question set is incomplete.');
    }

    if (abs($activeMarks - $totalMarks) > 0.000001) {
        start_exam_error('This practice examination is not ready because the question marks do not match the exam total.');
    }
} catch (Throwable $exception) {
    error_log('Student start exam question validation failed: ' . $exception->getMessage());
    start_exam_error('Unable to verify the questions for this examination right now.', 500);
}

if (empty($_SESSION['exam_csrf_token'])) {
    try {
        $_SESSION['exam_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Throwable $exception) {
        $_SESSION['exam_csrf_token'] = hash('sha256', uniqid('', true));
    }
}

$examCsrfToken = (string)$_SESSION['exam_csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $requestToken = trim((string)($_POST['csrf_token'] ?? ''));

        if ($requestToken === '' || $examCsrfToken === '' || !hash_equals($examCsrfToken, $requestToken)) {
            throw new RuntimeException('Security verification failed. Please refresh the page and try again.');
        }

        $postedExamId = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT);

        if ($postedExamId === false || $postedExamId === null || (int)$postedExamId !== $examId) {
            throw new RuntimeException('Invalid examination request.');
        }

        $conn->beginTransaction();

        $lockedExamStmt = $conn->prepare(
            "SELECT
                id,
                subject_id,
                title,
                exam_type,
                duration_minutes,
                required_question_count,
                total_marks,
                passing_marks,
                negative_marking,
                subscription_required,
                starts_at,
                ends_at,
                status
             FROM exams
             WHERE id=?
             LIMIT 1
             FOR UPDATE"
        );
        $lockedExamStmt->execute([$examId]);
        $lockedExam = $lockedExamStmt->fetch(PDO::FETCH_ASSOC);

        if (!$lockedExam) {
            throw new RuntimeException('The examination no longer exists.');
        }

        if ((string)$lockedExam['exam_type'] !== 'Practice' || (string)$lockedExam['status'] !== 'Active') {
            throw new RuntimeException('This practice examination is no longer available.');
        }

        $lockedRequired = (int)$lockedExam['required_question_count'];
        $lockedMarks = round((float)$lockedExam['total_marks'], 2);
        $lockedDuration = (int)$lockedExam['duration_minutes'];

        if ($lockedRequired !== $requiredQuestionCount || $lockedRequired < 1) {
            throw new RuntimeException('This examination configuration has changed. Please return to the Practice Exams page.');
        }

        $recheckStmt = $conn->prepare(
            "SELECT
                COUNT(DISTINCT eq.question_id) AS total_questions,
                COUNT(DISTINCT CASE WHEN q.status='Active' THEN eq.question_id END) AS active_questions,
                COALESCE(SUM(CASE WHEN q.status='Active' THEN q.marks ELSE 0 END),0) AS active_marks
             FROM exam_questions eq
             INNER JOIN questions q ON q.id=eq.question_id
             WHERE eq.exam_id=?"
        );
        $recheckStmt->execute([$examId]);
        $lockedQuestions = $recheckStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if ((int)($lockedQuestions['total_questions'] ?? 0) !== $lockedRequired || (int)($lockedQuestions['active_questions'] ?? 0) !== $lockedRequired) {
            throw new RuntimeException('This examination is not ready because its question set has changed.');
        }

        if (abs(round((float)($lockedQuestions['active_marks'] ?? 0), 2) - $lockedMarks) > 0.000001) {
            throw new RuntimeException('This examination is not ready because its question marks have changed.');
        }

        if ((int)$lockedExam['subscription_required'] === 1 && !has_active_subscription($conn, $studentId)) {
            throw new RuntimeException('An active subscription is required for this practice exam.');
        }

        $lockedStartsAt = null;
        $lockedEndsAt = null;
        $transactionNow = new DateTimeImmutable();

        if (!empty($lockedExam['starts_at'])) {
            $lockedStartsAt = new DateTimeImmutable((string)$lockedExam['starts_at']);
            if ($transactionNow < $lockedStartsAt) {
                throw new RuntimeException('This practice examination has not started yet.');
            }
        }

        if (!empty($lockedExam['ends_at'])) {
            $lockedEndsAt = new DateTimeImmutable((string)$lockedExam['ends_at']);
            if ($transactionNow > $lockedEndsAt) {
                throw new RuntimeException('This practice examination has ended.');
            }
        }

        $existingStmt = $conn->prepare(
            "SELECT
                id,
                started_at,
                server_deadline,
                status
             FROM exam_attempts
             WHERE student_id=?
               AND exam_id=?
               AND status='Started'
             ORDER BY id DESC
             LIMIT 1
             FOR UPDATE"
        );
        $existingStmt->execute([$studentId, $examId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $deadline = null;

            if (!empty($existing['server_deadline'])) {
                try {
                    $deadline = new DateTimeImmutable((string)$existing['server_deadline']);
                } catch (Throwable $exception) {
                    $deadline = null;
                }
            }

            if ($deadline === null && !empty($existing['started_at'])) {
                $startedAt = new DateTimeImmutable((string)$existing['started_at']);
                $deadline = $startedAt->modify('+' . $lockedDuration . ' minutes');
            }

            if ($deadline === null) {
                throw new RuntimeException('The existing examination attempt has invalid timing information.');
            }

            if ($lockedEndsAt !== null && $lockedEndsAt < $deadline) {
                $deadline = $lockedEndsAt;
            }

            if ($transactionNow >= $deadline) {
                $conn->commit();
                header('Location: take_exam.php?attempt_id=' . (int)$existing['id'] . '&auto_submit=1');
                exit;
            }

            $updateAttempt = $conn->prepare(
                "UPDATE exam_attempts
                 SET server_deadline=?, last_activity_at=NOW()
                 WHERE id=? AND student_id=? AND exam_id=? AND status='Started'"
            );
            $updateAttempt->execute([
                $deadline->format('Y-m-d H:i:s'),
                (int)$existing['id'],
                $studentId,
                $examId
            ]);

            $conn->commit();
            header('Location: take_exam.php?attempt_id=' . (int)$existing['id']);
            exit;
        }

        $newDeadline = $transactionNow->modify('+' . $lockedDuration . ' minutes');

        if ($lockedEndsAt !== null && $lockedEndsAt < $newDeadline) {
            $newDeadline = $lockedEndsAt;
        }

        if ($newDeadline <= $transactionNow) {
            throw new RuntimeException('This examination does not have enough remaining time to start.');
        }

        $insertAttempt = $conn->prepare(
            "INSERT INTO exam_attempts
             (
                student_id,
                exam_id,
                started_at,
                server_deadline,
                submitted_at,
                last_activity_at,
                status,
                obtained_marks,
                percentage
             )
             VALUES
             (?, ?, NOW(), ?, NULL, NOW(), 'Started', 0.00, 0.00)"
        );

        $insertAttempt->execute([
            $studentId,
            $examId,
            $newDeadline->format('Y-m-d H:i:s')
        ]);

        $attemptId = (int)$conn->lastInsertId();

        if ($attemptId <= 0) {
            throw new RuntimeException('Unable to start the examination.');
        }

        $verifyAttempt = $conn->prepare(
            "SELECT id, student_id, exam_id, status, server_deadline
             FROM exam_attempts
             WHERE id=?
             LIMIT 1"
        );
        $verifyAttempt->execute([$attemptId]);
        $createdAttempt = $verifyAttempt->fetch(PDO::FETCH_ASSOC);

        if (!$createdAttempt || (int)$createdAttempt['student_id'] !== $studentId || (int)$createdAttempt['exam_id'] !== $examId || (string)$createdAttempt['status'] !== 'Started') {
            throw new RuntimeException('The examination attempt could not be verified.');
        }

        $conn->commit();

        header('Location: take_exam.php?attempt_id=' . $attemptId);
        exit;
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        error_log('Student practice exam start failed: ' . $exception->getMessage());
        start_exam_error($exception->getMessage());
    }
}

$questionsLabel = $requiredQuestionCount . ' ' . ($requiredQuestionCount === 1 ? 'Question' : 'Questions');
$negativeLabel = $negativeMarking ? 'Enabled' : 'None';
$accessLabel = $subscriptionRequired ? 'Subscription Required' : 'Free Practice';
$startDateLabel = $startsAt ? $startsAt->format('d M Y, h:i A') : 'Available now';
$endDateLabel = $endsAt ? $endsAt->format('d M Y, h:i A') : 'No end time';

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#5D4037">
    <title><?= start_exam_escape($exam['title']) ?> | Start Practice | ExamSphere</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <style>
        :root{--brown:#5D4037;--brown-dark:#3E2723;--olive:#556B2F;--cream:#F5F5DC;--paper:#FFFDF9;--text:#332923;--muted:#7A6E66;--line:#E5DCD3;--soft:#F5EFE8;--green:#EEF5E8;--warning:#FFF1DD;--shadow:0 24px 70px rgba(62,39,35,.10)}
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;background:radial-gradient(circle at 8% 5%,rgba(85,107,47,.09),transparent 25%),radial-gradient(circle at 92% 10%,rgba(93,64,55,.08),transparent 25%),linear-gradient(135deg,#FCFAF7,#F1ECE5);color:var(--text);font-family:Inter,Arial,sans-serif}
        .page{width:min(1120px,calc(100% - 34px));margin:0 auto;padding:40px 0 70px}
        .top{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:25px}
        .brand{display:flex;align-items:center;gap:12px;color:var(--brown-dark);font-weight:800}
        .brand-icon{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(145deg,var(--brown),var(--brown-dark));color:#fff}
        .brand small{display:block;color:var(--muted);font-size:10px;font-weight:600;margin-top:2px}
        .back{display:inline-flex;align-items:center;gap:8px;padding:11px 15px;border:1px solid var(--line);border-radius:12px;background:#fff;color:var(--brown);text-decoration:none;font-size:13px;font-weight:800}
        .hero{padding:31px;border:1px solid var(--line);border-radius:25px;background:rgba(255,253,249,.94);box-shadow:var(--shadow);text-align:center}
        .eyebrow{display:inline-flex;align-items:center;gap:8px;color:var(--olive);font-size:11px;font-weight:800;letter-spacing:.15em;text-transform:uppercase}
        .eyebrow:before{content:"";width:28px;height:2px;border-radius:999px;background:var(--olive)}
        h1{margin:11px auto 8px;max-width:800px;color:var(--brown-dark);font-family:"Playfair Display",serif;font-size:clamp(38px,5vw,58px);line-height:1.08}
        .subtitle{max-width:780px;margin:0 auto;color:var(--muted);font-size:15px;line-height:1.75}
        .tags{display:flex;justify-content:center;flex-wrap:wrap;gap:8px;margin-top:18px}
        .tag{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;border-radius:999px;background:#F4EEE7;color:var(--brown);font-size:11px;font-weight:800}
        .tag.green{background:var(--green);color:var(--olive)}
        .tag.warning{background:var(--warning);color:#96651F}
        .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:13px;margin-top:18px}
        .stat{padding:17px;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:var(--shadow)}
        .stat span{display:block;color:#95887F;font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:5px}
        .stat strong{display:block;color:var(--brown-dark);font-size:20px;font-weight:800}
        .details{display:grid;grid-template-columns:1.25fr .75fr;gap:18px;margin-top:18px}
        .card{border:1px solid var(--line);border-radius:20px;background:rgba(255,253,249,.96);box-shadow:var(--shadow);overflow:hidden}
        .card-head{padding:20px 22px;border-bottom:1px solid #EEE6DE}
        .card-head h2{margin:0;color:var(--brown-dark);font-size:20px;font-weight:800}
        .card-head p{margin:5px 0 0;color:var(--muted);font-size:12px;line-height:1.5}
        .body{padding:20px 22px}
        .row{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;padding:13px 0;border-bottom:1px solid #F0E9E2}
        .row:last-child{border-bottom:0;padding-bottom:0}
        .row:first-child{padding-top:0}
        .row span{color:#83776F;font-size:12px;font-weight:600}
        .row strong{color:var(--brown-dark);font-size:13px;font-weight:800;text-align:right}
        .rules{display:grid;gap:10px}
        .rule{display:flex;align-items:flex-start;gap:11px;padding:13px;border-radius:13px;background:#FBF8F4}
        .rule i{margin-top:2px;color:var(--olive)}
        .rule strong{display:block;color:var(--brown-dark);font-size:12px}
        .rule span{display:block;margin-top:2px;color:var(--muted);font-size:11px;line-height:1.5}
        .start-card{margin-top:18px;padding:22px;border:1px solid var(--line);border-radius:20px;background:linear-gradient(145deg,#FFFDF9,#F8F3EC);box-shadow:var(--shadow)}
        .start-top{display:flex;align-items:center;justify-content:space-between;gap:18px}
        .ready{display:flex;align-items:center;gap:12px}
        .ready-icon{width:44px;height:44px;display:grid;place-items:center;border-radius:13px;background:var(--green);color:var(--olive)}
        .ready strong{display:block;color:var(--brown-dark);font-size:14px}
        .ready span{display:block;color:var(--muted);font-size:11px;margin-top:3px}
        .start-btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:52px;padding:0 22px;border:0;border-radius:14px;background:linear-gradient(135deg,var(--brown),#785646);color:#fff;font-size:14px;font-weight:800;cursor:pointer;box-shadow:0 16px 28px rgba(93,64,55,.18);transition:.2s ease}
        .start-btn:hover{transform:translateY(-2px);box-shadow:0 22px 35px rgba(93,64,55,.23)}
        .note{margin-top:11px;color:#8A7E75;font-size:11px;line-height:1.6}
        @media(max-width:900px){.grid{grid-template-columns:repeat(2,1fr)}.details{grid-template-columns:1fr}}
        @media(max-width:600px){.page{padding-top:22px}.top{align-items:flex-start;flex-direction:column}.back{width:100%;justify-content:center}.hero{padding:24px 18px}h1{font-size:37px}.grid{grid-template-columns:1fr 1fr}.start-top{align-items:stretch;flex-direction:column}.start-btn{width:100%}}
    </style>
</head>
<body>
    <main class="page">
        <div class="top">
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                <div>
                    ExamSphere
                    <small>Student Practice Centre</small>
                </div>
            </div>
            <a class="back" href="practice_exams.php"><i class="fa-solid fa-arrow-left"></i> Back to Practice Exams</a>
        </div>

        <section class="hero">
            <span class="eyebrow"><i class="fa-solid fa-pen-to-square"></i> Practice Examination</span>
            <h1><?= start_exam_escape($exam['title']) ?></h1>
            <p class="subtitle"><?= start_exam_escape($exam['description'] ?: 'Build your confidence with this carefully configured practice examination.') ?></p>
            <div class="tags">
                <span class="tag green"><i class="fa-solid fa-circle-check"></i> Ready to Start</span>
                <span class="tag"><i class="fa-solid fa-book-open"></i> <?= start_exam_escape($exam['subject_name'] ?? 'Subject') ?></span>
                <span class="tag <?= $subscriptionRequired ? 'warning' : 'green' ?>"><i class="fa-solid <?= $subscriptionRequired ? 'fa-crown' : 'fa-unlock' ?>"></i> <?= start_exam_escape($accessLabel) ?></span>
            </div>
        </section>

        <section class="grid">
            <div class="stat"><span>Questions</span><strong><?= start_exam_escape($requiredQuestionCount) ?></strong></div>
            <div class="stat"><span>Total Marks</span><strong><?= start_exam_escape(rtrim(rtrim(number_format($totalMarks,2,'.',''),'0'),'.')) ?></strong></div>
            <div class="stat"><span>Passing Marks</span><strong><?= start_exam_escape(rtrim(rtrim(number_format($passingMarks,2,'.',''),'0'),'.')) ?></strong></div>
            <div class="stat"><span>Duration</span><strong><?= start_exam_escape($durationMinutes) ?> min</strong></div>
        </section>

        <section class="details">
            <article class="card">
                <div class="card-head">
                    <h2>Examination Details</h2>
                    <p>Everything you should know before starting.</p>
                </div>
                <div class="body">
                    <div class="row"><span>Subject</span><strong><?= start_exam_escape($exam['subject_name'] ?? '—') ?></strong></div>
                    <div class="row"><span>Question Set</span><strong><?= start_exam_escape($questionsLabel) ?></strong></div>
                    <div class="row"><span>Total Marks</span><strong><?= start_exam_escape(rtrim(rtrim(number_format($totalMarks,2,'.',''),'0'),'.')) ?></strong></div>
                    <div class="row"><span>Passing Marks</span><strong><?= start_exam_escape(rtrim(rtrim(number_format($passingMarks,2,'.',''),'0'),'.')) ?></strong></div>
                    <div class="row"><span>Negative Marking</span><strong><?= start_exam_escape($negativeLabel) ?></strong></div>
                    <div class="row"><span>Available From</span><strong><?= start_exam_escape($startDateLabel) ?></strong></div>
                    <div class="row"><span>Available Until</span><strong><?= start_exam_escape($endDateLabel) ?></strong></div>
                </div>
            </article>

            <article class="card">
                <div class="card-head">
                    <h2>Before You Start</h2>
                    <p>Keep these rules in mind.</p>
                </div>
                <div class="body rules">
                    <div class="rule"><i class="fa-solid fa-clock"></i><div><strong>Timer starts immediately</strong><span>Your server-controlled exam time begins when the attempt is created.</span></div></div>
                    <div class="rule"><i class="fa-solid fa-floppy-disk"></i><div><strong>Answers are saved</strong><span>Your answers and question status are persisted during the attempt.</span></div></div>
                    <div class="rule"><i class="fa-solid fa-list-check"></i><div><strong>Use the question palette</strong><span>Move between questions and review your progress without losing your place.</span></div></div>
                    <div class="rule"><i class="fa-solid fa-circle-check"></i><div><strong>Submit when finished</strong><span>Your result is calculated securely on the server after submission.</span></div></div>
                </div>
            </article>
        </section>

        <section class="start-card">
            <div class="start-top">
                <div class="ready">
                    <div class="ready-icon"><i class="fa-solid fa-rocket"></i></div>
                    <div>
                        <strong>Ready to begin your practice?</strong>
                        <span><?= start_exam_escape($requiredQuestionCount) ?> questions · <?= start_exam_escape($durationMinutes) ?> minutes · <?= start_exam_escape($negativeLabel) ?> negative marking</span>
                    </div>
                </div>

                <form method="post" onsubmit="return confirmStart(event);">
                    <input type="hidden" name="exam_id" value="<?= $examId ?>">
                    <input type="hidden" name="csrf_token" value="<?= start_exam_escape($examCsrfToken) ?>">
                    <button type="submit" class="start-btn">
                        <i class="fa-solid fa-play"></i>
                        Start Practice
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>
            </div>
            <div class="note"><i class="fa-solid fa-shield-halved"></i> Starting the exam creates one secure attempt for your student account. Opening this page alone does not start the exam.</div>
        </section>
    </main>

    <script>
        function confirmStart(event) {
            const ok = window.confirm('Start this practice examination now? The timer will begin immediately.');
            if (!ok) {
                event.preventDefault();
                return false;
            }
            const button = event.target.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Starting...';
            }
            return true;
        }
    </script>
</body>
</html>
