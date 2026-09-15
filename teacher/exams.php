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

$message = '';
$error = '';

function teacher_exams_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function teacher_exams_date(mixed $value): string
{
    if (empty($value)) {
        return '—';
    }

    $timestamp = strtotime((string)$value);

    return $timestamp === false
        ? '—'
        : date('d M Y, h:i A', $timestamp);
}

function teacher_exams_display_status(
    array $exam
): string {

    $status =
        (string)$exam['status'];

    if (
        $status === 'Active' &&
        !empty($exam['starts_at']) &&
        strtotime((string)$exam['starts_at']) > time()
    ) {
        return 'Upcoming';
    }

    if (
        in_array(
            $status,
            [
                'Active',
                'Upcoming',
                'Running',
                'Live',
                'Scheduled'
            ],
            true
        ) &&
        !empty($exam['ends_at']) &&
        strtotime((string)$exam['ends_at']) !== false &&
        strtotime((string)$exam['ends_at']) < time()
    ) {
        return 'Completed';
    }

    return $status;
}

function teacher_exams_status_class(
    string $status
): string {

    return match ($status) {
        'Active',
        'Running',
        'Live' =>
            'status-active',

        'Upcoming',
        'Scheduled' =>
            'status-upcoming',

        'Completed' =>
            'status-completed',

        'Cancelled' =>
            'status-cancelled',

        default =>
            'status-draft'
    };
}

function teacher_exams_attempt_count(
    PDO $conn,
    int $examId
): int {

    $stmt = $conn->prepare(
        "
        SELECT
            COUNT(*)
        FROM exam_attempts
        WHERE exam_id = ?
        "
    );

    $stmt->execute([
        $examId
    ]);

    return (int)(
        $stmt->fetchColumn() ?: 0
    );
}

/*
|--------------------------------------------------------------------------
| Mutation actions
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

        $action =
            trim(
                (string)(
                    $_POST['action'] ?? ''
                )
            );

        $examId =
            filter_var(
                $_POST['exam_id'] ?? '',
                FILTER_VALIDATE_INT
            );

        if (
            $examId === false ||
            $examId <= 0
        ) {
            throw new RuntimeException(
                'Invalid examination.'
            );
        }

        $examStmt =
            $conn->prepare(
                "
                SELECT *
                FROM exams
                WHERE
                    id = ?
                    AND teacher_id = ?
                LIMIT 1
                "
            );

        $examStmt->execute([
            (int)$examId,
            $teacherId
        ]);

        $exam =
            $examStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$exam) {
            throw new RuntimeException(
                'Examination not found or access denied.'
            );
        }

        $attemptCount =
            teacher_exams_attempt_count(
                $conn,
                (int)$examId
            );

        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete') {

            if ($attemptCount > 0) {
                throw new RuntimeException(
                    'This examination cannot be deleted because student attempts already exist.'
                );
            }

            $delete =
                $conn->prepare(
                    "
                    DELETE FROM exams
                    WHERE
                        id = ?
                        AND teacher_id = ?
                    "
                );

            $delete->execute([
                (int)$examId,
                $teacherId
            ]);

            if (
                $delete->rowCount() !== 1
            ) {
                throw new RuntimeException(
                    'The examination could not be deleted.'
                );
            }

            $message =
                'Examination deleted successfully.';

        /*
        |--------------------------------------------------------------------------
        | PUBLISH / ACTIVATE
        |--------------------------------------------------------------------------
        */

        } elseif (
            $action === 'publish' ||
            $action === 'activate'
        ) {

            if ($attemptCount > 0) {
                throw new RuntimeException(
                    'This examination cannot be reconfigured because student attempts already exist.'
                );
            }

            $requiredCount =
                (int)$exam[
                    'required_question_count'
                ];

            $questionStmt =
                $conn->prepare(
                    "
                    SELECT
                        q.id,
                        q.marks,
                        q.status
                    FROM exam_questions eq
                    INNER JOIN questions q
                        ON q.id = eq.question_id
                    WHERE
                        eq.exam_id = ?
                    ORDER BY
                        eq.position ASC
                    "
                );

            $questionStmt->execute([
                (int)$examId
            ]);

            $questions =
                $questionStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );

            $validation =
                validate_exam_question_configuration(
                    (float)$exam['total_marks'],
                    $questions,
                    $requiredCount
                );

            if (!$validation['valid']) {
                throw new RuntimeException(
                    $validation['message']
                );
            }

            foreach (
                $questions as $question
            ) {
                if (
                    (string)$question['status'] !==
                    'Active'
                ) {
                    throw new RuntimeException(
                        'All assigned questions must be active before publishing.'
                    );
                }
            }

            $start =
                !empty($exam['starts_at'])
                    ? (string)$exam['starts_at']
                    : null;

            $end =
                !empty($exam['ends_at'])
                    ? (string)$exam['ends_at']
                    : null;

            $status =
                'Active';

            if (
                $exam['exam_type'] === 'Live'
            ) {

                $now = time();

                $startTimestamp =
                    $start
                        ? strtotime($start)
                        : false;

                $endTimestamp =
                    $end
                        ? strtotime($end)
                        : false;

                if (
                    $endTimestamp !== false &&
                    $endTimestamp <= $now
                ) {
                    $status =
                        'Completed';

                } elseif (
                    $startTimestamp !== false &&
                    $startTimestamp > $now
                ) {
                    $status =
                        'Upcoming';

                } else {
                    $status =
                        'Running';
                }

            } else {

                $status =
                    'Active';
            }

            $update =
                $conn->prepare(
                    "
                    UPDATE exams
                    SET status = ?
                    WHERE
                        id = ?
                        AND teacher_id = ?
                    "
                );

            $update->execute([
                $status,
                (int)$examId,
                $teacherId
            ]);

            examsphere_event_exam_updated(
                $conn,
                (int)$examId,
                (string)$exam['title'],
                $status
            );

            $message =
                'Examination status updated to ' .
                $status .
                '.';

        /*
        |--------------------------------------------------------------------------
        | CANCEL
        |--------------------------------------------------------------------------
        */

        } elseif ($action === 'cancel') {

            if ($attemptCount > 0) {
                throw new RuntimeException(
                    'This examination cannot be cancelled because student attempts already exist.'
                );
            }

            $update =
                $conn->prepare(
                    "
                    UPDATE exams
                    SET status = 'Cancelled'
                    WHERE
                        id = ?
                        AND teacher_id = ?
                    "
                );

            $update->execute([
                (int)$examId,
                $teacherId
            ]);

            $message =
                'Examination cancelled successfully.';

        /*
        |--------------------------------------------------------------------------
        | RESTORE DRAFT
        |--------------------------------------------------------------------------
        */

        } elseif ($action === 'restore') {

            if ($attemptCount > 0) {
                throw new RuntimeException(
                    'This examination cannot be restored because student attempts already exist.'
                );
            }

            $update =
                $conn->prepare(
                    "
                    UPDATE exams
                    SET status = 'Draft'
                    WHERE
                        id = ?
                        AND teacher_id = ?
                    "
                );

            $update->execute([
                (int)$examId,
                $teacherId
            ]);

            $message =
                'Examination restored to Draft.';

        } else {

            throw new RuntimeException(
                'Invalid examination action.'
            );
        }

    } catch (Throwable $exception) {

        error_log(
            'ExamSphere teacher exam action failed: ' .
            $exception->getMessage()
        );

        $error =
            $exception->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search =
    trim(
        (string)(
            $_GET['search'] ?? ''
        )
    );

$typeFilter =
    trim(
        (string)(
            $_GET['exam_type'] ?? ''
        )
    );

$statusFilter =
    trim(
        (string)(
            $_GET['status'] ?? ''
        )
    );

if (
    !in_array(
        $typeFilter,
        [
            '',
            'Practice',
            'Live'
        ],
        true
    )
) {
    $typeFilter = '';
}

$allowedStatuses = [
    '',
    'Draft',
    'Active',
    'Upcoming',
    'Running',
    'Live',
    'Scheduled',
    'Completed',
    'Cancelled'
];

if (
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {
    $statusFilter = '';
}

/*
|--------------------------------------------------------------------------
| Exam query
|--------------------------------------------------------------------------
*/

$where = [
    'e.teacher_id = ?'
];

$params = [
    $teacherId
];

if ($search !== '') {

    $where[] =
        "
        (
            e.title LIKE ?
            OR e.description LIKE ?
            OR s.name LIKE ?
        )
        ";

    $searchValue =
        '%' .
        $search .
        '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($typeFilter !== '') {

    $where[] =
        'e.exam_type = ?';

    $params[] =
        $typeFilter;
}

if ($statusFilter !== '') {

    $where[] =
        'e.status = ?';

    $params[] =
        $statusFilter;
}

$whereSql =
    'WHERE ' .
    implode(
        ' AND ',
        $where
    );

$items = [];
$pageError = '';

try {

    $stmt =
        $conn->prepare(
            "
            SELECT

                e.id,
                e.subject_id,
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
                e.created_at,

                s.name AS subject_name,
                s.code AS subject_code,

                COUNT(eq.question_id) AS question_count,
                COALESCE(
                    SUM(q.marks),
                    0
                ) AS actual_marks,

                (
                    SELECT COUNT(*)
                    FROM exam_attempts ea
                    WHERE ea.exam_id = e.id
                ) AS attempt_count

            FROM exams e

            LEFT JOIN subjects s
                ON s.id = e.subject_id

            LEFT JOIN exam_questions eq
                ON eq.exam_id = e.id

            LEFT JOIN questions q
                ON q.id = eq.question_id

            $whereSql

            GROUP BY
                e.id,
                e.subject_id,
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
                e.created_at,
                s.name,
                s.code

            ORDER BY
                e.created_at DESC,
                e.id DESC
            "
        );

    $stmt->execute(
        $params
    );

    $items =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'ExamSphere teacher exams load failed: ' .
        $exception->getMessage()
    );

    $pageError =
        'Examination library could not be loaded.';
}

/*
|--------------------------------------------------------------------------
| Metrics
|--------------------------------------------------------------------------
*/

$totalExams = count($items);
$practice = 0;
$live = 0;
$ready = 0;
$draft = 0;

foreach (
    $items as &$item
) {

    $actualCount =
        (int)$item['question_count'];

    $requiredCount =
        (int)$item['required_question_count'];

    $actualMarks =
        round(
            (float)$item['actual_marks'],
            2
        );

    $configuredMarks =
        round(
            (float)$item['total_marks'],
            2
        );

    $item['is_ready'] =
        $requiredCount > 0 &&
        $actualCount ===
        $requiredCount &&
        abs(
            $actualMarks -
            $configuredMarks
        ) < 0.000001;

    $item['display_status'] =
        teacher_exams_display_status(
            $item
        );

    if (
        $item['exam_type'] ===
        'Practice'
    ) {
        $practice++;
    } else {
        $live++;
    }

    if ($item['is_ready']) {
        $ready++;
    }

    if (
        $item['status'] ===
        'Draft'
    ) {
        $draft++;
    }
}

unset($item);

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
    My Exams | ExamSphere
</title>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="../assets/css/portal.css"
>

<style>

:root{
    --earth:#5d4037;
    --earth-dark:#422d26;
    --olive:#556b2f;
    --page:#f6f3eb;
    --text:#352c27;
    --muted:#867b72;
    --line:#e7dfd5;
    --soft:#fbfaf6;
    --white:#fff;
    --green:#4f7040;
    --red:#995048;
    --amber:#99743a;
}

*{
    box-sizing:border-box;
}

body.portal-body{
    margin:0;
    font-family:'Poppins',sans-serif;
    color:var(--text);
    background:
        radial-gradient(
            circle at 8% 0%,
            rgba(85,107,47,.08),
            transparent 26%
        ),
        linear-gradient(
            135deg,
            #faf8f2,
            #efebe4
        );
}

.exam-page{
    width:min(
        1420px,
        calc(100vw - 24px)
    );
    margin:0 auto;
    padding:20px 0 60px;
}

.page-head{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:20px;
    margin-bottom:19px;
}

.kicker{
    color:var(--olive);
    font-size:.60rem;
    font-weight:900;
    letter-spacing:.14em;
}

.page-head h1{
    margin:7px 0 4px;
    color:var(--earth);
    font-size:1.85rem;
    font-weight:900;
    letter-spacing:-.035em;
}

.page-head p{
    margin:0;
    color:var(--muted);
    font-size:.63rem;
}

.btn-create{
    min-height:42px;
    padding:0 16px;
    border:0;
    border-radius:11px;
    background:var(--earth);
    color:#fff;
    font-size:.62rem;
    font-weight:850;
}

.btn-create:hover{
    background:var(--earth-dark);
    color:#fff;
}

.alert{
    border-radius:12px;
    font-size:.60rem;
}

.metrics{
    display:grid;
    grid-template-columns:
        repeat(5,minmax(0,1fr));
    gap:11px;
    margin-bottom:18px;
}

.metric{
    min-height:90px;
    padding:14px;
    border:1px solid var(--line);
    border-radius:15px;
    background:rgba(255,255,255,.88);
    box-shadow:
        0 10px 24px rgba(62,45,37,.045);
}

.metric small{
    display:block;
    color:var(--muted);
    font-size:.49rem;
    font-weight:750;
}

.metric strong{
    display:block;
    margin-top:4px;
    color:var(--earth);
    font-size:1.05rem;
    font-weight:900;
}

.metric.ready strong{
    color:var(--green);
}

.metric.draft strong{
    color:var(--amber);
}

.filter-card{
    margin-bottom:18px;
    padding:15px;
    border:1px solid var(--line);
    border-radius:17px;
    background:rgba(255,255,255,.88);
    box-shadow:
        0 12px 28px rgba(62,45,37,.05);
}

.filter-grid{
    display:grid;
    grid-template-columns:
        minmax(250px,1.6fr)
        minmax(170px,1fr)
        minmax(170px,1fr)
        100px;
    gap:9px;
    align-items:end;
}

.filter-label{
    display:block;
    margin-bottom:5px;
    color:var(--earth);
    font-size:.49rem;
    font-weight:850;
}

.control{
    min-height:41px;
    border:1px solid #ddd5cc;
    border-radius:10px;
    font-size:.59rem;
}

.filter-btn,
.clear-btn{
    min-height:41px;
    border-radius:10px;
    font-size:.55rem;
    font-weight:850;
}

.filter-btn{
    width:100%;
    border:0;
    background:var(--earth);
    color:#fff;
}

.clear-btn{
    width:100%;
    display:flex;
    align-items:center;
    justify-content:center;
    border:1px solid var(--line);
    background:#fff;
    color:var(--earth);
    text-decoration:none;
}

.library{
    overflow:hidden;
    border:1px solid rgba(93,64,55,.08);
    border-radius:20px;
    background:rgba(255,255,255,.9);
    box-shadow:
        0 18px 44px rgba(62,45,37,.07);
}

.library-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    padding:18px 20px;
    border-bottom:1px solid var(--line);
}

.library-head h2{
    margin:0;
    color:var(--earth);
    font-size:.92rem;
    font-weight:900;
}

.library-head p{
    margin:4px 0 0;
    color:var(--muted);
    font-size:.55rem;
}

.count{
    padding:7px 9px;
    border-radius:999px;
    background:#f3eee6;
    color:var(--earth);
    font-size:.50rem;
    font-weight:850;
}

.exam-grid{
    display:grid;
    grid-template-columns:
        repeat(2,minmax(0,1fr));
    gap:14px;
    padding:16px;
}

.exam-card{
    padding:17px;
    border:1px solid var(--line);
    border-radius:16px;
    background:#fcfbf8;
}

.exam-top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
}

.exam-title{
    margin:0;
    color:var(--earth);
    font-size:.74rem;
    font-weight:900;
    line-height:1.45;
}

.exam-sub{
    margin-top:4px;
    color:var(--muted);
    font-size:.52rem;
}

.status{
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:6px 8px;
    border-radius:999px;
    font-size:.48rem;
    font-weight:900;
    white-space:nowrap;
}

.status-active{
    background:#eaf3e6;
    color:var(--green);
}

.status-upcoming{
    background:#fff2d9;
    color:var(--amber);
}

.status-completed{
    background:#eeeae4;
    color:#71675f;
}

.status-cancelled{
    background:#fae8e5;
    color:var(--red);
}

.status-draft{
    background:#f5efe4;
    color:#846a45;
}

.details{
    display:grid;
    grid-template-columns:
        repeat(4,minmax(0,1fr));
    gap:7px;
    margin-top:14px;
}

.detail{
    padding:9px;
    border-radius:10px;
    background:#f6f1e9;
}

.detail small{
    display:block;
    color:var(--muted);
    font-size:.45rem;
}

.detail strong{
    display:block;
    margin-top:3px;
    color:var(--earth);
    font-size:.56rem;
    font-weight:850;
}

.readiness{
    margin-top:11px;
    padding:9px 10px;
    border-radius:9px;
    font-size:.50rem;
    line-height:1.5;
}

.readiness.ready{
    background:#edf5e9;
    color:var(--green);
}

.readiness.not-ready{
    background:#fff1df;
    color:var(--amber);
}

.actions{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-top:13px;
    padding-top:12px;
    border-top:1px solid var(--line);
}

.action,
.action-btn{
    min-height:32px;
    padding:0 9px;
    border-radius:8px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:5px;
    font-size:.48rem;
    font-weight:850;
    text-decoration:none;
}

.action-view{
    background:var(--earth);
    color:#fff;
}

.action-view:hover{
    background:var(--earth-dark);
    color:#fff;
}

.action-question{
    background:#f1f4eb;
    color:var(--olive);
}

.action-edit{
    background:#f5efe6;
    color:#765744;
}

.action-cancel{
    border:0;
    background:#fae9e6;
    color:var(--red);
}

.action-publish{
    border:0;
    background:#eaf3e6;
    color:var(--green);
}

.action-delete{
    border:0;
    background:#fbe8e5;
    color:var(--red);
}

.action-restore{
    border:0;
    background:#f3efe7;
    color:var(--earth);
}

.empty{
    padding:55px 20px;
    text-align:center;
}

.empty i{
    color:#a49a8f;
    font-size:1.7rem;
}

.empty strong{
    display:block;
    margin-top:9px;
    color:var(--earth);
    font-size:.70rem;
}

.empty span{
    display:block;
    margin-top:4px;
    color:var(--muted);
    font-size:.53rem;
}

@media(max-width:1100px){

    .metrics{
        grid-template-columns:
            repeat(3,minmax(0,1fr));
    }

    .filter-grid{
        grid-template-columns:
            1fr 1fr;
    }

}

@media(max-width:800px){

    .exam-grid{
        grid-template-columns:1fr;
    }

}

@media(max-width:650px){

    .exam-page{
        width:calc(100vw - 12px);
    }

    .page-head{
        align-items:flex-start;
        flex-direction:column;
    }

    .page-head h1{
        font-size:1.52rem;
    }

    .metrics{
        grid-template-columns:
            1fr 1fr;
    }

    .filter-grid{
        grid-template-columns:1fr;
    }

    .details{
        grid-template-columns:
            repeat(2,minmax(0,1fr));
    }

}

@media(max-width:420px){

    .metrics{
        grid-template-columns:1fr;
    }

}

/* Readability enhancement: larger + bolder typography */
.page-head .kicker{font-size:.78rem;font-weight:900;letter-spacing:.14em}
.page-head h1{font-size:2.35rem;font-weight:900;line-height:1.2}
.page-head p{font-size:.92rem;font-weight:600}
.btn-create{font-size:.82rem;font-weight:900;min-height:48px;padding:0 18px}
.alert{font-size:.82rem;font-weight:700}
.metric{min-height:108px;padding:17px}
.metric small{font-size:.72rem;font-weight:800}
.metric strong{font-size:1.55rem;font-weight:900}
.filter-card{padding:18px}
.filter-label{font-size:.72rem;font-weight:900}
.control{min-height:46px;font-size:.82rem;font-weight:600}
.filter-btn,.clear-btn{min-height:46px;font-size:.75rem;font-weight:900}
.library-head h2{font-size:1.2rem;font-weight:900}
.library-head p{font-size:.72rem;font-weight:600}
.count{font-size:.68rem;font-weight:900}
.exam-card{padding:20px}
.exam-title{font-size:1rem;font-weight:900;line-height:1.45}
.exam-sub{font-size:.72rem;font-weight:600}
.status{font-size:.68rem;font-weight:900;padding:7px 10px}
.details{gap:9px;margin-top:16px}
.detail{padding:11px}
.detail small{font-size:.62rem;font-weight:700}
.detail strong{font-size:.74rem;font-weight:900}
.readiness{padding:11px 12px;font-size:.70rem;font-weight:700}
.action,.action-btn{min-height:36px;padding:0 11px;font-size:.67rem;font-weight:900}
.empty strong{font-size:.88rem;font-weight:900}
.empty span{font-size:.70rem;font-weight:600}
</style>

</head>

<body class="portal-body">

<div class="portal-layout">

<?php include 'includes/sidebar.php'; ?>

<main class="portal-main">

<div class="exam-page">

<header class="page-head">

<div>

<div class="kicker">

<i class="fa-solid fa-file-circle-check me-1"></i>

EXAM MANAGEMENT

</div>

<h1>
    My Exams
</h1>

<p>
    Manage your dynamic practice and live examinations.
</p>

</div>

<a
    href="create_exam.php"
    class="btn btn-create"
>

<i class="fa-solid fa-plus me-1"></i>

Create Exam

</a>

</header>


<?php if ($message !== ''): ?>

<div class="alert alert-success mb-3">

<i class="fa-solid fa-circle-check me-1"></i>

<?= teacher_exams_e(
    $message
) ?>

</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div class="alert alert-danger mb-3">

<i class="fa-solid fa-circle-exclamation me-1"></i>

<?= teacher_exams_e(
    $error
) ?>

</div>

<?php endif; ?>


<?php if ($pageError !== ''): ?>

<div class="alert alert-danger mb-3">

<i class="fa-solid fa-circle-exclamation me-1"></i>

<?= teacher_exams_e(
    $pageError
) ?>

</div>

<?php endif; ?>


<section class="metrics">

<div class="metric">

<small>
    Total Exams
</small>

<strong>
    <?= (int)$totalExams ?>
</strong>

</div>


<div class="metric">

<small>
    Practice
</small>

<strong>
    <?= (int)$practice ?>
</strong>

</div>


<div class="metric">

<small>
    Live
</small>

<strong>
    <?= (int)$live ?>
</strong>

</div>


<div class="metric ready">

<small>
    Question Ready
</small>

<strong>
    <?= (int)$ready ?>
</strong>

</div>


<div class="metric draft">

<small>
    Draft
</small>

<strong>
    <?= (int)$draft ?>
</strong>

</div>

</section>


<section class="filter-card">

<form
    method="get"
    class="filter-grid"
>

<div>

<label
    class="filter-label"
    for="search"
>
    Search
</label>

<input
    id="search"
    class="form-control control"
    type="search"
    name="search"
    value="<?= teacher_exams_e(
        $search
    ) ?>"
    placeholder="Exam title, description or subject..."
>

</div>


<div>

<label
    class="filter-label"
    for="exam_type"
>
    Exam Type
</label>

<select
    id="exam_type"
    class="form-select control"
    name="exam_type"
>

<option value="">
    All Types
</option>

<option
    value="Practice"
    <?= $typeFilter === 'Practice'
        ? 'selected'
        : ''
    ?>
>
    Practice
</option>

<option
    value="Live"
    <?= $typeFilter === 'Live'
        ? 'selected'
        : ''
    ?>
>
    Live
</option>

</select>

</div>


<div>

<label
    class="filter-label"
    for="status"
>
    Status
</label>

<select
    id="status"
    class="form-select control"
    name="status"
>

<option value="">
    All Status
</option>

<?php foreach (
    array_slice(
        $allowedStatuses,
        1
    ) as $status
): ?>

<option
    value="<?= teacher_exams_e(
        $status
    ) ?>"
    <?= $statusFilter === $status
        ? 'selected'
        : ''
    ?>
>

<?= teacher_exams_e(
    $status
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div>

<label class="filter-label">
    &nbsp;
</label>

<div class="d-flex gap-1">

<button
    type="submit"
    class="filter-btn"
>

<i class="fa-solid fa-filter"></i>

</button>

<a
    href="exams.php"
    class="clear-btn"
    title="Clear filters"
>

<i class="fa-solid fa-xmark"></i>

</a>

</div>

</div>

</form>

</section>


<section class="library">

<header class="library-head">

<div>

<h2>
    Exam Library
</h2>

<p>
    Assigned count and question marks must exactly match the exam configuration before publishing.
</p>

</div>

<span class="count">

<?= (int)$totalExams ?>

exam<?= $totalExams === 1 ? '' : 's' ?>

</span>

</header>


<?php if ($items): ?>

<div class="exam-grid">

<?php foreach (
    $items as $item
): ?>

<article class="exam-card">

<div class="exam-top">

<div>

<h3 class="exam-title">

<?= teacher_exams_e(
    $item['title']
) ?>

</h3>

<div class="exam-sub">

<?= teacher_exams_e(
    $item['subject_name']
        ?? '—'
) ?>

<?php if (
    !empty(
        $item['subject_code']
    )
): ?>

&nbsp; · &nbsp;

<?= teacher_exams_e(
    $item['subject_code']
) ?>

<?php endif; ?>

&nbsp; · &nbsp;

<?= teacher_exams_e(
    $item['exam_type']
) ?>

</div>

</div>


<span
    class="status <?= teacher_exams_status_class(
        $item['display_status']
    ) ?>"
>

<i class="fa-solid fa-circle"></i>

<?= teacher_exams_e(
    $item['display_status']
) ?>

</span>

</div>


<div class="details">


<div class="detail">

<small>
    Questions
</small>

<strong>

<?= (int)$item['question_count'] ?>

/
<?= (int)$item['required_question_count'] ?>

</strong>

</div>


<div class="detail">

<small>
    Marks
</small>

<strong>

<?= number_format(
    (float)$item['actual_marks'],
    2
) ?>

/
<?= number_format(
    (float)$item['total_marks'],
    2
) ?>

</strong>

</div>


<div class="detail">

<small>
    Duration
</small>

<strong>

<?= (int)$item['duration_minutes'] ?>

min

</strong>

</div>


<div class="detail">

<small>
    Attempts
</small>

<strong>

<?= (int)$item['attempt_count'] ?>

</strong>

</div>

</div>


<div
    class="readiness <?= $item['is_ready']
        ? 'ready'
        : 'not-ready'
    ?>"
>

<?php if (
    $item['is_ready']
): ?>

<i class="fa-solid fa-circle-check me-1"></i>

Question configuration is ready.

<?php else: ?>

<i class="fa-solid fa-triangle-exclamation me-1"></i>

Not ready:
configured count/marks do not exactly match the current assignment.

<?php endif; ?>

</div>


<div class="actions">


<a
    href="exam_view.php?id=<?= (int)$item['id'] ?>"
    class="action action-view"
>

<i class="fa-solid fa-eye"></i>

View

</a>


<a
    href="exam_questions.php?exam_id=<?= (int)$item['id'] ?>"
    class="action action-question"
>

<i class="fa-solid fa-list-check"></i>

Questions

</a>


<?php if (
    (int)$item['attempt_count'] === 0
): ?>

<a
    href="edit_exam.php?id=<?= (int)$item['id'] ?>"
    class="action action-edit"
>

<i class="fa-solid fa-pen"></i>

Edit

</a>

<?php endif; ?>


<?php if (
    $item['status'] === 'Draft' &&
    $item['is_ready']
): ?>

<form
    method="post"
    class="d-inline"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="action"
    value="publish"
>

<input
    type="hidden"
    name="exam_id"
    value="<?= (int)$item['id'] ?>"
>

<button
    type="submit"
    class="action-btn action-publish"
>

<i class="fa-solid fa-paper-plane"></i>

Publish

</button>

</form>

<?php endif; ?>


<?php if (
    in_array(
        $item['status'],
        [
            'Active',
            'Upcoming',
            'Running',
            'Live',
            'Scheduled'
        ],
        true
    ) &&
    (int)$item['attempt_count'] === 0
): ?>

<form
    method="post"
    class="d-inline"
    onsubmit="return confirm('Cancel this examination?');"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="action"
    value="cancel"
>

<input
    type="hidden"
    name="exam_id"
    value="<?= (int)$item['id'] ?>"
>

<button
    type="submit"
    class="action-btn action-cancel"
>

<i class="fa-solid fa-ban"></i>

Cancel

</button>

</form>

<?php endif; ?>


<?php if (
    $item['status'] === 'Cancelled' &&
    (int)$item['attempt_count'] === 0
): ?>

<form
    method="post"
    class="d-inline"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="action"
    value="restore"
>

<input
    type="hidden"
    name="exam_id"
    value="<?= (int)$item['id'] ?>"
>

<button
    type="submit"
    class="action-btn action-restore"
>

<i class="fa-solid fa-rotate-left"></i>

Draft

</button>

</form>

<?php endif; ?>


<?php if (
    (int)$item['attempt_count'] === 0
): ?>

<form
    method="post"
    class="d-inline"
    onsubmit="return confirm('Delete this examination permanently?');"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="action"
    value="delete"
>

<input
    type="hidden"
    name="exam_id"
    value="<?= (int)$item['id'] ?>"
>

<button
    type="submit"
    class="action-btn action-delete"
>

<i class="fa-solid fa-trash"></i>

Delete

</button>

</form>

<?php endif; ?>


</div>

</article>

<?php endforeach; ?>

</div>

<?php else: ?>

<div class="empty">

<i class="fa-solid fa-file-circle-xmark"></i>

<strong>
    No examinations found.
</strong>

<span>
    Create an examination or clear the current filters.
</span>

</div>

<?php endif; ?>

</section>


</div>

</main>

</div>

</body>

</html>
