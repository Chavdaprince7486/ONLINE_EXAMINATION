<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../config/exam_validation.php';
require_once '../config/notification_events.php';

require_login('teacher');

$teacherId = current_user_id();
$examId = filter_var(
    $_GET['exam_id'] ?? $_POST['exam_id'] ?? '',
    FILTER_VALIDATE_INT
);

$error = '';
$success = '';

if ($examId === false || $examId <= 0) {
    http_response_code(400);
    exit('Invalid examination.');
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function exam_questions_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function exam_questions_clean_ids(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    $ids = [];

    foreach ($value as $id) {
        $id = filter_var(
            $id,
            FILTER_VALIDATE_INT
        );

        if ($id !== false && $id > 0) {
            $ids[] = (int)$id;
        }
    }

    $ids = array_values(
        array_unique($ids)
    );

    return $ids;
}

function exam_questions_assignment_locked(
    PDO $conn,
    int $examId
): bool {
    $stmt = $conn->prepare(
        "
        SELECT
            COUNT(*) AS attempt_count
        FROM exam_attempts
        WHERE exam_id = ?
        "
    );

    $stmt->execute([
        $examId
    ]);

    return (int)(
        $stmt->fetchColumn() ?: 0
    ) > 0;
}

/*
|--------------------------------------------------------------------------
| Load teacher-owned exam
|--------------------------------------------------------------------------
*/

try {

    $examStatement = $conn->prepare(
        "
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
            s.name AS subject_name,
            s.code AS subject_code
        FROM exams e
        INNER JOIN subjects s
            ON s.id = e.subject_id
        WHERE
            e.id = ?
            AND e.teacher_id = ?
        LIMIT 1
        "
    );

    $examStatement->execute([
        (int)$examId,
        $teacherId
    ]);

    $exam = $examStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$exam) {
        http_response_code(404);
        exit('Examination not found or access denied.');
    }

} catch (Throwable $exception) {

    error_log(
        'ExamSphere teacher exam question load failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);
    exit('Unable to load this examination.');
}

/*
|--------------------------------------------------------------------------
| Modification lock
|--------------------------------------------------------------------------
*/

$assignmentLocked =
    exam_questions_assignment_locked(
        $conn,
        (int)$examId
    );

/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

        $action = trim(
            (string)(
                $_POST['action'] ?? ''
            )
        );

        if ($assignmentLocked) {
            throw new RuntimeException(
                'Question assignment is locked because this examination already has student attempts.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | ADD QUESTIONS
        |--------------------------------------------------------------------------
        */

        if ($action === 'add') {

            $questionIds =
                exam_questions_clean_ids(
                    $_POST['question_ids'] ?? []
                );

            if (!$questionIds) {
                throw new RuntimeException(
                    'Select at least one question to add.'
                );
            }

            $currentStmt = $conn->prepare(
                "
                SELECT
                    question_id
                FROM exam_questions
                WHERE exam_id = ?
                "
            );

            $currentStmt->execute([
                (int)$examId
            ]);

            $existingIds = array_map(
                'intval',
                $currentStmt->fetchAll(
                    PDO::FETCH_COLUMN
                )
            );

            $existingMap = array_flip(
                $existingIds
            );

            $newIds = [];

            foreach ($questionIds as $questionId) {
                if (!isset($existingMap[$questionId])) {
                    $newIds[] = $questionId;
                }
            }

            if (!$newIds) {
                throw new RuntimeException(
                    'All selected questions are already assigned to this examination.'
                );
            }

            $requiredCount =
                (int)$exam[
                    'required_question_count'
                ];

            $currentCount =
                count($existingIds);

            if (
                $currentCount +
                count($newIds) >
                $requiredCount
            ) {
                throw new RuntimeException(
                    'You can assign only ' .
                    $requiredCount .
                    ' question(s) to this examination. Current assignment: ' .
                    $currentCount .
                    '.'
                );
            }

            $placeholders = implode(
                ',',
                array_fill(
                    0,
                    count($newIds),
                    '?'
                )
            );

            $params =
                array_merge(
                    [$teacherId, (int)$exam['subject_id']],
                    $newIds
                );

            $questionStmt = $conn->prepare(
                "
                SELECT
                    id,
                    question_text,
                    marks,
                    negative_marks,
                    difficulty,
                    status
                FROM questions
                WHERE
                    created_by_teacher_id = ?
                    AND subject_id = ?
                    AND status = 'Active'
                    AND id IN ($placeholders)
                "
            );

            $questionStmt->execute(
                $params
            );

            $availableRows =
                $questionStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );

            $foundIds = array_map(
                'intval',
                array_column(
                    $availableRows,
                    'id'
                )
            );

            sort($foundIds);

            $requestedIds = $newIds;
            sort($requestedIds);

            if (
                $foundIds !==
                $requestedIds
            ) {
                throw new RuntimeException(
                    'One or more selected questions are invalid, inactive, from another subject, or outside your question bank.'
                );
            }

            $newMarks = 0.0;

            foreach (
                $availableRows as $question
            ) {
                $newMarks +=
                    (float)$question['marks'];
            }

            $currentMarksStmt =
                $conn->prepare(
                    "
                    SELECT
                        COALESCE(
                            SUM(q.marks),
                            0
                        )
                    FROM exam_questions eq
                    INNER JOIN questions q
                        ON q.id = eq.question_id
                    WHERE eq.exam_id = ?
                    "
                );

            $currentMarksStmt->execute([
                (int)$examId
            ]);

            $currentMarks =
                (float)(
                    $currentMarksStmt->fetchColumn()
                    ?: 0
                );

            $projectedMarks =
                round(
                    $currentMarks +
                    $newMarks,
                    2
                );

            $configuredMarks =
                round(
                    (float)$exam[
                        'total_marks'
                    ],
                    2
                );

            if (
                $projectedMarks >
                $configuredMarks +
                0.000001
            ) {
                throw new RuntimeException(
                    'Adding these questions would exceed the configured total exam marks of ' .
                    number_format(
                        $configuredMarks,
                        2
                    ) .
                    '.'
                );
            }

            $maxPositionStmt =
                $conn->prepare(
                    "
                    SELECT
                        COALESCE(
                            MAX(position),
                            0
                        )
                    FROM exam_questions
                    WHERE exam_id = ?
                    "
                );

            $maxPositionStmt->execute([
                (int)$examId
            ]);

            $position =
                (int)(
                    $maxPositionStmt->fetchColumn()
                    ?: 0
                );

            $conn->beginTransaction();

            $insertStmt =
                $conn->prepare(
                    "
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
                    "
                );

            foreach ($newIds as $questionId) {

                $position++;

                $insertStmt->execute([
                    (int)$examId,
                    $questionId,
                    $position
                ]);
            }

            $conn->commit();

            examsphere_event_exam_content_changed(
                $conn,
                (int)$examId,
                (string)$exam['title'],
                (string)$exam['status']
            );

            $success =
                count($newIds) .
                ' question(s) added successfully.';

        /*
        |--------------------------------------------------------------------------
        | REMOVE QUESTION
        |--------------------------------------------------------------------------
        */

        } elseif ($action === 'remove') {

            $questionId = filter_var(
                $_POST['question_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (
                $questionId === false ||
                $questionId <= 0
            ) {
                throw new RuntimeException(
                    'Invalid question selected.'
                );
            }

            $checkStmt = $conn->prepare(
                "
                SELECT
                    eq.question_id
                FROM exam_questions eq
                INNER JOIN exams e
                    ON e.id = eq.exam_id
                INNER JOIN questions q
                    ON q.id = eq.question_id
                WHERE
                    eq.exam_id = ?
                    AND eq.question_id = ?
                    AND e.teacher_id = ?
                    AND q.created_by_teacher_id = ?
                LIMIT 1
                "
            );

            $checkStmt->execute([
                (int)$examId,
                (int)$questionId,
                $teacherId,
                $teacherId
            ]);

            if (!$checkStmt->fetchColumn()) {
                throw new RuntimeException(
                    'That question is not assigned to this examination.'
                );
            }

            $deleteStmt = $conn->prepare(
                "
                DELETE FROM exam_questions
                WHERE
                    exam_id = ?
                    AND question_id = ?
                "
            );

            $deleteStmt->execute([
                (int)$examId,
                (int)$questionId
            ]);

            $renumberStmt = $conn->prepare(
                "
                SELECT
                    question_id
                FROM exam_questions
                WHERE exam_id = ?
                ORDER BY
                    position ASC,
                    question_id ASC
                "
            );

            $renumberStmt->execute([
                (int)$examId
            ]);

            $remainingIds =
                array_map(
                    'intval',
                    $renumberStmt->fetchAll(
                        PDO::FETCH_COLUMN
                    )
                );

            $updatePosition =
                $conn->prepare(
                    "
                    UPDATE exam_questions
                    SET position = ?
                    WHERE
                        exam_id = ?
                        AND question_id = ?
                    "
                );

            $conn->beginTransaction();

            foreach (
                $remainingIds as $index => $remainingId
            ) {
                $updatePosition->execute([
                    $index + 1,
                    (int)$examId,
                    $remainingId
                ]);
            }

            $conn->commit();

            examsphere_event_exam_content_changed(
                $conn,
                (int)$examId,
                (string)$exam['title'],
                (string)$exam['status']
            );

            $success =
                'Question removed and positions normalized.';

        /*
        |--------------------------------------------------------------------------
        | REORDER QUESTIONS
        |--------------------------------------------------------------------------
        */

        } elseif ($action === 'reorder') {

            $orderedIds =
                exam_questions_clean_ids(
                    $_POST['ordered_ids'] ?? []
                );

            if (!$orderedIds) {
                throw new RuntimeException(
                    'No question order was submitted.'
                );
            }

            $assignedStmt =
                $conn->prepare(
                    "
                    SELECT
                        question_id
                    FROM exam_questions
                    WHERE exam_id = ?
                    "
                );

            $assignedStmt->execute([
                (int)$examId
            ]);

            $assignedIds =
                array_map(
                    'intval',
                    $assignedStmt->fetchAll(
                        PDO::FETCH_COLUMN
                    )
                );

            sort($assignedIds);

            $requestedIds = $orderedIds;
            sort($requestedIds);

            if (
                $assignedIds !==
                $requestedIds
            ) {
                throw new RuntimeException(
                    'The submitted question order does not match the current assignment.'
                );
            }

            $updatePosition =
                $conn->prepare(
                    "
                    UPDATE exam_questions
                    SET position = ?
                    WHERE
                        exam_id = ?
                        AND question_id = ?
                    "
                );

            $conn->beginTransaction();

            foreach (
                $orderedIds as $index => $questionId
            ) {
                $updatePosition->execute([
                    $index + 1,
                    (int)$examId,
                    (int)$questionId
                ]);
            }

            $conn->commit();

            examsphere_event_exam_content_changed(
                $conn,
                (int)$examId,
                (string)$exam['title'],
                (string)$exam['status']
            );

            $success =
                'Question order saved successfully.';

        /*
        |--------------------------------------------------------------------------
        | REMOVE ALL ASSIGNMENTS
        |--------------------------------------------------------------------------
        */

        } elseif ($action === 'clear_all') {

            $deleteStmt = $conn->prepare(
                "
                DELETE FROM exam_questions
                WHERE exam_id = ?
                "
            );

            $deleteStmt->execute([
                (int)$examId
            ]);

            examsphere_event_exam_content_changed(
                $conn,
                (int)$examId,
                (string)$exam['title'],
                (string)$exam['status']
            );

            $success =
                'All questions were removed from this examination.';

        } else {

            throw new RuntimeException(
                'Invalid question-management action.'
            );
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
            'ExamSphere teacher exam-question management failed: ' .
            $exception->getMessage()
        );

        $error =
            $exception->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Reload assignment + available question bank
|--------------------------------------------------------------------------
*/

$assignedQuestions = [];
$availableQuestions = [];

try {

    $assignedStmt = $conn->prepare(
        "
        SELECT
            eq.question_id,
            eq.position,

            q.question_text,
            q.question_type,
            q.option_a,
            q.option_b,
            q.option_c,
            q.option_d,
            q.marks,
            q.negative_marks,
            q.difficulty,
            q.status,

            t.name AS topic_name

        FROM exam_questions eq

        INNER JOIN questions q
            ON q.id = eq.question_id

        LEFT JOIN topics t
            ON t.id = q.topic_id

        WHERE
            eq.exam_id = ?
            AND q.created_by_teacher_id = ?

        ORDER BY
            eq.position ASC,
            eq.question_id ASC
        "
    );

    $assignedStmt->execute([
        (int)$examId,
        $teacherId
    ]);

    $assignedQuestions =
        $assignedStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    $assignedIds =
        array_map(
            'intval',
            array_column(
                $assignedQuestions,
                'question_id'
            )
        );

    if ($assignedIds) {

        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($assignedIds),
                '?'
            )
        );

        $availableStmt =
            $conn->prepare(
                "
                SELECT
                    q.id,
                    q.question_text,
                    q.question_type,
                    q.marks,
                    q.negative_marks,
                    q.difficulty,

                    t.name AS topic_name

                FROM questions q

                LEFT JOIN topics t
                    ON t.id = q.topic_id

                WHERE
                    q.created_by_teacher_id = ?
                    AND q.subject_id = ?
                    AND q.status = 'Active'
                    AND q.id NOT IN ($placeholders)

                ORDER BY
                    q.id DESC
                "
            );

        $availableStmt->execute(
            array_merge(
                [
                    $teacherId,
                    (int)$exam['subject_id']
                ],
                $assignedIds
            )
        );

    } else {

        $availableStmt =
            $conn->prepare(
                "
                SELECT
                    q.id,
                    q.question_text,
                    q.question_type,
                    q.marks,
                    q.negative_marks,
                    q.difficulty,

                    t.name AS topic_name

                FROM questions q

                LEFT JOIN topics t
                    ON t.id = q.topic_id

                WHERE
                    q.created_by_teacher_id = ?
                    AND q.subject_id = ?
                    AND q.status = 'Active'

                ORDER BY
                    q.id DESC
                "
            );

        $availableStmt->execute([
            $teacherId,
            (int)$exam['subject_id']
        ]);
    }

    $availableQuestions =
        $availableStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'ExamSphere exam question builder reload failed: ' .
        $exception->getMessage()
    );

    $error =
        $error !== ''
            ? $error
            : 'Unable to load question assignment data.';
}

/*
|--------------------------------------------------------------------------
| Dynamic readiness metrics
|--------------------------------------------------------------------------
*/

$requiredCount =
    (int)(
        $exam[
            'required_question_count'
        ] ?? 0
    );

$assignedCount =
    count($assignedQuestions);

$actualMarks = 0.0;

foreach (
    $assignedQuestions as $question
) {
    $actualMarks +=
        (float)$question['marks'];
}

$actualMarks =
    round(
        $actualMarks,
        2
    );

$configuredMarks =
    round(
        (float)(
            $exam['total_marks']
            ?? 0
        ),
        2
    );

$countDifference =
    $assignedCount -
    $requiredCount;

$marksDifference =
    round(
        $actualMarks -
        $configuredMarks,
        2
    );

$isCountReady =
    $requiredCount > 0 &&
    $assignedCount === $requiredCount;

$isMarksReady =
    $configuredMarks > 0 &&
    abs(
        $actualMarks -
        $configuredMarks
    ) < 0.000001;

$isReady =
    $isCountReady &&
    $isMarksReady;

/*
|--------------------------------------------------------------------------
| Same-marks information
|--------------------------------------------------------------------------
*/

$marksPerQuestion = null;
$sameMarks = true;

if ($assignedQuestions) {

    $firstMarks =
        (float)$assignedQuestions[0]['marks'];

    foreach (
        $assignedQuestions as $question
    ) {

        if (
            abs(
                (float)$question['marks'] -
                $firstMarks
            ) > 0.000001
        ) {
            $sameMarks = false;
            break;
        }
    }

    if ($sameMarks) {
        $marksPerQuestion =
            round(
                $firstMarks,
                2
            );
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
    Exam Questions | <?= exam_questions_e($exam['title']) ?>
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
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    rel="stylesheet"
>

<style>

:root{
    --earth:#5d4037;
    --earth-dark:#402b25;
    --olive:#556b2f;
    --cream:#f5f5dc;
    --page:#f6f4ed;
    --white:#ffffff;
    --text:#352c27;
    --muted:#847970;
    --line:#e9e1d7;
    --soft:#fbfaf6;
    --success:#4f7040;
    --danger:#a85146;
    --warning:#9a773c;
}

*{
    box-sizing:border-box;
}

body{
    margin:0;
    font-family:'Poppins',sans-serif;
    color:var(--text);
    background:
        radial-gradient(
            circle at 5% 0%,
            rgba(85,107,47,.08),
            transparent 25%
        ),
        linear-gradient(
            135deg,
            #faf8f2,
            #efebe3
        );
}

.page{
    max-width:1220px;
    margin:0 auto;
    padding:20px 18px 60px;
}

.header{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:20px;
    margin-bottom:20px;
}

.kicker{
    font-size:.60rem;
    letter-spacing:.14em;
    font-weight:900;
    color:var(--olive);
}

.header h1{
    margin:6px 0 4px;
    color:var(--earth);
    font-size:1.85rem;
    font-weight:900;
    letter-spacing:-.035em;
}

.header p{
    margin:0;
    color:var(--muted);
    font-size:.68rem;
}

.back-btn{
    border-radius:11px;
    font-size:.67rem;
    font-weight:800;
}

.panel{
    border:1px solid rgba(93,64,55,.08);
    border-radius:20px;
    background:rgba(255,255,255,.88);
    box-shadow:
        0 18px 45px rgba(62,45,37,.08);
    overflow:hidden;
}

.panel + .panel{
    margin-top:20px;
}

.panel-head{
    padding:18px 20px;
    border-bottom:1px solid var(--line);
}

.panel-head h2{
    margin:0;
    color:var(--earth);
    font-size:.95rem;
    font-weight:900;
}

.panel-head p{
    margin:4px 0 0;
    color:var(--muted);
    font-size:.62rem;
}

.metrics{
    display:grid;
    grid-template-columns:
        repeat(5,minmax(0,1fr));
    gap:12px;
    padding:18px;
}

.metric{
    padding:15px;
    border:1px solid var(--line);
    border-radius:15px;
    background:var(--soft);
}

.metric small{
    display:block;
    color:var(--muted);
    font-size:.57rem;
    font-weight:700;
}

.metric strong{
    display:block;
    margin-top:5px;
    color:var(--earth);
    font-size:1.05rem;
    font-weight:900;
}

.metric span{
    display:block;
    margin-top:3px;
    color:var(--muted);
    font-size:.54rem;
}

.metric.ready strong{
    color:var(--olive);
}

.metric.bad strong{
    color:var(--danger);
}

.builder-body{
    padding:18px;
}

.question-row{
    display:flex;
    gap:13px;
    align-items:center;
    padding:13px 0;
    border-bottom:1px solid #eee8df;
}

.question-row:last-child{
    border-bottom:0;
}

.drag{
    width:30px;
    color:#b6aa9f;
    text-align:center;
}

.position{
    width:38px;
    height:38px;
    flex:0 0 38px;
    border-radius:11px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#f0eadf;
    color:var(--earth);
    font-size:.68rem;
    font-weight:900;
}

.question-content{
    min-width:0;
    flex:1;
}

.question-title{
    color:var(--earth);
    font-size:.70rem;
    line-height:1.55;
    font-weight:750;
}

.meta{
    display:flex;
    flex-wrap:wrap;
    gap:7px;
    margin-top:6px;
}

.meta span{
    padding:4px 7px;
    border-radius:7px;
    background:#f4f0e8;
    color:var(--muted);
    font-size:.51rem;
    font-weight:700;
}

.remove-btn{
    min-width:36px;
    height:36px;
    border:0;
    border-radius:10px;
    background:#f7e9e6;
    color:var(--danger);
}

.bank{
    max-height:650px;
    overflow:auto;
}

.bank-item{
    display:flex;
    gap:12px;
    align-items:flex-start;
    padding:12px 0;
    border-bottom:1px solid #eee8df;
}

.bank-item:last-child{
    border-bottom:0;
}

.bank-item input{
    margin-top:4px;
}

.bank-question{
    flex:1;
    min-width:0;
}

.bank-question strong{
    display:block;
    color:var(--earth);
    font-size:.65rem;
    line-height:1.5;
}

.bank-question small{
    display:block;
    margin-top:5px;
    color:var(--muted);
    font-size:.53rem;
}

.add-bar{
    padding:16px 18px;
    border-top:1px solid var(--line);
    background:#fcfaf6;
}

.primary-btn,
.success-btn{
    border:0;
    border-radius:11px;
    min-height:42px;
    padding:0 17px;
    color:#fff;
    font-size:.65rem;
    font-weight:850;
}

.primary-btn{
    background:var(--earth);
}

.primary-btn:hover{
    background:var(--earth-dark);
    color:#fff;
}

.success-btn{
    background:var(--olive);
}

.success-btn:hover{
    color:#fff;
    filter:brightness(.94);
}

.clear-btn{
    border-radius:11px;
    font-size:.62rem;
    font-weight:800;
}

.add-question-btn{
    border:0;
    border-radius:10px;
    background:var(--olive);
    color:#fff;
    padding:8px 11px;
    white-space:nowrap;
    font-size:.58rem;
    font-weight:850;
}

.add-question-btn:hover{
    background:#465a25;
    color:#fff;
}

.notice{
    margin:0 18px 18px;
    padding:13px 15px;
    border-radius:12px;
    font-size:.62rem;
    line-height:1.6;
}

.notice.success{
    background:#eef5ea;
    color:#46643b;
}

.notice.warning{
    background:#fff7e8;
    color:#896a36;
}

.notice.danger{
    background:#fbeeed;
    color:#914b43;
}

.notice.info{
    background:#f1f4ee;
    color:#58664d;
}

.empty{
    padding:40px 20px;
    text-align:center;
    color:var(--muted);
}

.empty i{
    font-size:1.7rem;
    color:#a69b90;
    margin-bottom:10px;
}

.empty strong{
    display:block;
    color:var(--earth);
    font-size:.72rem;
}

.empty span{
    display:block;
    margin-top:5px;
    font-size:.59rem;
}

.exam-info{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:10px;
}

.exam-info span{
    padding:5px 8px;
    border-radius:8px;
    background:#f4efe7;
    color:var(--muted);
    font-size:.52rem;
    font-weight:750;
}

.status-pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 9px;
    border-radius:999px;
    font-size:.55rem;
    font-weight:850;
}

.status-ready{
    background:#eaf2e5;
    color:#4d713f;
}

.status-not{
    background:#fbebe8;
    color:#965148;
}

@media(max-width:1050px){

    .metrics{
        grid-template-columns:
            repeat(3,minmax(0,1fr));
    }

}

@media(max-width:700px){

    .page{
        padding-left:9px;
        padding-right:9px;
    }

    .header{
        align-items:flex-start;
        flex-direction:column;
    }

    .header h1{
        font-size:1.55rem;
    }

    .metrics{
        grid-template-columns:
            repeat(2,minmax(0,1fr));
    }

    .question-row{
        align-items:flex-start;
    }

}

@media(max-width:460px){

    .metrics{
        grid-template-columns:1fr;
    }

    .question-row{
        gap:8px;
    }

    .position{
        width:33px;
        height:33px;
        flex-basis:33px;
    }

}

</style>

</head>

<body>

<?php include 'includes/sidebar.php'; ?>

<main class="portal-main">

<div class="page">

<header class="header">

<div>

<div class="kicker">
    <i class="fa-solid fa-list-check me-1"></i>
    EXAM QUESTION BUILDER
</div>

<h1>
    <?= exam_questions_e(
        $exam['title']
    ) ?>
</h1>

<p>
    Manage the exact question set for this examination.
</p>

<div class="exam-info">

<span>
    <?= exam_questions_e(
        $exam['subject_name']
    ) ?>
</span>

<span>
    <?= exam_questions_e(
        $exam['exam_type']
    ) ?>
</span>

<span>
    <?= (int)$requiredCount ?>
    required
</span>

<span>
    <?= number_format(
        $configuredMarks,
        2
    ) ?>
    total marks
</span>

</div>

</div>


<a
    href="exams.php"
    class="btn btn-outline-secondary back-btn"
>

<i class="fa-solid fa-arrow-left me-1"></i>

My Exams

</a>

</header>


<?php if ($success !== ''): ?>

<div class="notice success">

<i class="fa-solid fa-circle-check me-1"></i>

<?= exam_questions_e(
    $success
) ?>

</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div class="notice danger">

<i class="fa-solid fa-circle-exclamation me-1"></i>

<?= exam_questions_e(
    $error
) ?>

</div>

<?php endif; ?>


<?php if ($assignmentLocked): ?>

<div class="notice warning">

<i class="fa-solid fa-lock me-1"></i>

Question assignment is locked because this examination already has student attempts.
This protects existing attempt integrity.

</div>

<?php endif; ?>


<section class="panel">

<div class="panel-head">

<h2>
    Assignment Status
</h2>

<p>
    The examination becomes question-ready only when both question count and total question marks exactly match the configuration.
</p>

</div>


<div class="metrics">


<div class="metric <?= $isCountReady ? 'ready' : 'bad' ?>">

<small>
    Required Questions
</small>

<strong>
    <?= (int)$requiredCount ?>
</strong>

<span>
    Configured for this exam
</span>

</div>


<div class="metric <?= $isCountReady ? 'ready' : 'bad' ?>">

<small>
    Assigned Questions
</small>

<strong>
    <?= (int)$assignedCount ?>
</strong>

<span>
    Difference: <?= $countDifference >= 0 ? '+' : '' ?><?= (int)$countDifference ?>
</span>

</div>


<div class="metric <?= $isMarksReady ? 'ready' : 'bad' ?>">

<small>
    Configured Marks
</small>

<strong>
    <?= number_format(
        $configuredMarks,
        2
    ) ?>
</strong>

<span>
    Exam total marks
</span>

</div>


<div class="metric <?= $isMarksReady ? 'ready' : 'bad' ?>">

<small>
    Actual Marks
</small>

<strong>
    <?= number_format(
        $actualMarks,
        2
    ) ?>
</strong>

<span>
    Difference: <?= $marksDifference >= 0 ? '+' : '' ?><?= number_format($marksDifference, 2) ?>
</span>

</div>


<div class="metric <?= $isReady ? 'ready' : 'bad' ?>">

<small>
    Readiness
</small>

<strong>
    <?= $isReady ? 'READY' : 'NOT READY' ?>
</strong>

<span>
    <?= $sameMarks && $marksPerQuestion !== null
        ? number_format($marksPerQuestion, 2) . ' marks/question'
        : 'Mixed question marks'
    ?>
</span>

</div>


</div>


<?php if ($isReady): ?>

<div class="notice success">

<i class="fa-solid fa-circle-check me-1"></i>

This exam has exactly
<strong>
    <?= (int)$requiredCount ?>
</strong>
assigned questions and their total marks exactly match
<strong>
    <?= number_format($configuredMarks, 2) ?>
</strong>.
The question configuration is valid.

</div>

<?php else: ?>

<div class="notice info">

<i class="fa-solid fa-circle-info me-1"></i>

Add or remove questions until the assigned count is exactly
<strong>
    <?= (int)$requiredCount ?>
</strong>
and the assigned question marks total exactly
<strong>
    <?= number_format($configuredMarks, 2) ?>
</strong>.

</div>

<?php endif; ?>

</section>


<div class="row g-4 mt-1">


<div class="col-lg-7">

<section class="panel">

<div class="panel-head d-flex justify-content-between align-items-center gap-3">

<div>

<h2>
    Assigned Questions
</h2>

<p>
    Dragging is optional; use the order controls to save a precise sequence.
</p>

</div>

<form
    method="post"
    onsubmit="return confirm('Remove every question from this examination?');"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="exam_id"
    value="<?= (int)$examId ?>"
>

<input
    type="hidden"
    name="action"
    value="clear_all"
>

<button
    type="submit"
    class="btn btn-outline-danger clear-btn"
    <?= $assignmentLocked || !$assignedQuestions ? 'disabled' : '' ?>
>

<i class="fa-solid fa-trash-can me-1"></i>

Clear All

</button>

</form>

</div>


<div class="builder-body">

<?php if ($assignedQuestions): ?>

<form
    method="post"
    id="reorderForm"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="exam_id"
    value="<?= (int)$examId ?>"
>

<input
    type="hidden"
    name="action"
    value="reorder"
>

<div id="assignedList">

<?php foreach (
    $assignedQuestions as $question
): ?>

<div
    class="question-row"
    draggable="<?= $assignmentLocked ? 'false' : 'true' ?>"
    data-id="<?= (int)$question['question_id'] ?>"
>

<div class="drag">

<i class="fa-solid fa-grip-vertical"></i>

</div>

<div class="position">

<?= (int)$question['position'] ?>

</div>

<div class="question-content">

<div class="question-title">

<?= exam_questions_e(
    $question['question_text']
) ?>

</div>

<div class="meta">

<span>
    <?= exam_questions_e(
        $question['question_type']
    ) ?>
</span>

<span>
    <?= number_format(
        (float)$question['marks'],
        2
    ) ?>
    marks
</span>

<span>
    -<?= number_format(
        (float)$question['negative_marks'],
        2
    ) ?>
    negative
</span>

<span>
    <?= exam_questions_e(
        $question['difficulty']
    ) ?>
</span>

<?php if (
    !empty(
        $question['topic_name']
    )
): ?>

<span>
    <?= exam_questions_e(
        $question['topic_name']
    ) ?>
</span>

<?php endif; ?>

</div>

</div>


<form
    method="post"
    onsubmit="return confirm('Remove this question from the exam?');"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="exam_id"
    value="<?= (int)$examId ?>"
>

<input
    type="hidden"
    name="action"
    value="remove"
>

<input
    type="hidden"
    name="question_id"
    value="<?= (int)$question['question_id'] ?>"
>

<button
    type="submit"
    class="remove-btn"
    title="Remove"
    <?= $assignmentLocked ? 'disabled' : '' ?>
>

<i class="fa-solid fa-xmark"></i>

</button>

</form>

</div>

<?php endforeach; ?>

</div>


<div class="mt-3 d-flex justify-content-end">

<button
    type="submit"
    class="btn success-btn"
    <?= $assignmentLocked ? 'disabled' : '' ?>
>

<i class="fa-solid fa-arrow-down-up-across-line me-1"></i>

Save Question Order

</button>

</div>

</form>

<?php else: ?>

<div class="empty">

<i class="fa-solid fa-inbox"></i>

<strong>
    No questions assigned yet.
</strong>

<span>
    Select questions from your Question Bank below.
</span>

</div>

<?php endif; ?>

</div>

</section>

</div>


<div class="col-lg-5">

<section class="panel">

<div class="panel-head d-flex justify-content-between align-items-start gap-3">

<div>

<h2>
    Question Bank
</h2>

<p>
    Only your active questions from this exam's subject are available.
</p>

</div>

<a
    href="questions.php"
    class="btn add-question-btn"
>

<i class="fa-solid fa-circle-plus me-1"></i>

Add Question

</a>

</div>


<form
    method="post"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="exam_id"
    value="<?= (int)$examId ?>"
>

<input
    type="hidden"
    name="action"
    value="add"
>


<div class="builder-body bank">

<?php if ($availableQuestions): ?>

<?php foreach (
    $availableQuestions as $question
): ?>

<label
    class="bank-item"
>

<input
    type="checkbox"
    name="question_ids[]"
    value="<?= (int)$question['id'] ?>"
    <?= $assignmentLocked ? 'disabled' : '' ?>
>

<div class="bank-question">

<strong>

<?= exam_questions_e(
    $question['question_text']
) ?>

</strong>

<small>

<?= exam_questions_e(
    $question['question_type']
) ?>

&nbsp; • &nbsp;

<?= number_format(
    (float)$question['marks'],
    2
) ?>
marks

&nbsp; • &nbsp;

<?= exam_questions_e(
    $question['difficulty']
) ?>

<?php if (
    !empty(
        $question['topic_name']
    )
): ?>

&nbsp; • &nbsp;

<?= exam_questions_e(
    $question['topic_name']
) ?>

<?php endif; ?>

</small>

</div>

</label>

<?php endforeach; ?>

<?php else: ?>

<div class="empty">

<i class="fa-solid fa-folder-open"></i>

<strong>
    No additional active questions found.
</strong>

<span>
    Create or import questions for this subject first.
</span>

</div>

<?php endif; ?>

</div>


<div class="add-bar">

<button
    type="submit"
    class="btn primary-btn w-100"
    <?= $assignmentLocked || !$availableQuestions ? 'disabled' : '' ?>
>

<i class="fa-solid fa-plus me-1"></i>

Add Selected Questions

</button>

</div>

</form>

</section>

</div>

</div>

</div>

</main>


<script>

(() => {

    const list =
        document.getElementById(
            'assignedList'
        );

    const form =
        document.getElementById(
            'reorderForm'
        );

    if (
        !list ||
        !form
    ) {
        return;
    }

    let dragged = null;

    list.querySelectorAll(
        '.question-row[draggable="true"]'
    ).forEach(
        row => {

            row.addEventListener(
                'dragstart',
                () => {
                    dragged = row;
                    row.style.opacity = '0.45';
                }
            );

            row.addEventListener(
                'dragend',
                () => {
                    if (dragged) {
                        dragged.style.opacity = '';
                    }
                    dragged = null;
                    syncPositions();
                }
            );

            row.addEventListener(
                'dragover',
                event => {
                    event.preventDefault();

                    if (
                        !dragged ||
                        dragged === row
                    ) {
                        return;
                    }

                    const rect =
                        row.getBoundingClientRect();

                    const after =
                        (
                            event.clientY -
                            rect.top
                        ) >
                        rect.height / 2;

                    if (after) {
                        row.after(dragged);
                    } else {
                        row.before(dragged);
                    }

                    syncPositions();
                }
            );
        }
    );

    function syncPositions() {

        list.querySelectorAll(
            '.question-row'
        ).forEach(
            (row, index) => {

                const position =
                    row.querySelector(
                        '.position'
                    );

                if (position) {
                    position.textContent =
                        String(index + 1);
                }
            }
        );
    }

    form.addEventListener(
        'submit',
        () => {

            list.querySelectorAll(
                '.order-hidden'
            ).forEach(
                node => node.remove()
            );

            list.querySelectorAll(
                '.question-row'
            ).forEach(
                row => {

                    const input =
                        document.createElement(
                            'input'
                        );

                    input.type = 'hidden';
                    input.name =
                        'ordered_ids[]';

                    input.value =
                        row.dataset.id;

                    input.className =
                        'order-hidden';

                    form.appendChild(
                        input
                    );
                }
            );
        }
    );

})();

</script>

</body>

</html>
