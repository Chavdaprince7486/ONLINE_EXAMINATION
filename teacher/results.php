<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

require_login('teacher');

$teacherId = current_user_id();

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

$perPage = 10;

$search = trim(
    (string)($_GET['search'] ?? '')
);

$examId = filter_input(
    INPUT_GET,
    'exam_id',
    FILTER_VALIDATE_INT
);

if (
    $examId === false ||
    $examId === null ||
    $examId <= 0
) {
    $examId = null;
}

$status = trim(
    (string)($_GET['status'] ?? '')
);

if (
    !in_array(
        $status,
        ['', 'Pass', 'Fail'],
        true
    )
) {
    $status = '';
}

function teacher_history_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function teacher_history_number(mixed $value): string
{
    $number = (float)$value;

    return rtrim(
        rtrim(
            number_format(
                $number,
                2,
                '.',
                ''
            ),
            '0'
        ),
        '.'
    );
}

function teacher_history_date(mixed $value): string
{
    if (empty($value)) {
        return '—';
    }

    try {
        return (
            new DateTimeImmutable(
                (string)$value
            )
        )->format(
            'd M Y'
        );
    } catch (Throwable) {
        return '—';
    }
}

function teacher_history_url(
    int $page,
    string $search,
    ?int $examId,
    string $status
): string {
    $params = [
        'page' => $page
    ];

    if ($search !== '') {
        $params['search'] = $search;
    }

    if ($examId !== null) {
        $params['exam_id'] = $examId;
    }

    if ($status !== '') {
        $params['status'] = $status;
    }

    return '?' . http_build_query($params);
}

$where = [
    'e.teacher_id = ?'
];

$params = [
    $teacherId
];

if ($search !== '') {

    $where[] = '
        (
            s.full_name LIKE ?
            OR s.email LIKE ?
            OR e.title LIKE ?
        )
    ';

    $searchValue =
        '%' .
        $search .
        '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($examId !== null) {

    $where[] =
        'r.exam_id = ?';

    $params[] =
        $examId;
}

if ($status !== '') {

    $where[] =
        'r.result_status = ?';

    $params[] =
        $status;
}

$whereSql =
    'WHERE ' .
    implode(
        ' AND ',
        $where
    );

$rows = [];
$totalRows = 0;
$totalPages = 1;
$pageError = '';

try {

    $countStmt = $conn->prepare(
        "
        SELECT
            COUNT(*)
        FROM results r

        INNER JOIN exams e
            ON e.id = r.exam_id

        INNER JOIN students s
            ON s.id = r.student_id

        $whereSql
        "
    );

    $countStmt->execute(
        $params
    );

    $totalRows =
        (int)(
            $countStmt->fetchColumn()
            ?: 0
        );

    $totalPages =
        max(
            1,
            (int)ceil(
                $totalRows /
                $perPage
            )
        );

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset =
        (
            $page - 1
        ) *
        $perPage;

    $dataStmt = $conn->prepare(
        "
        SELECT
            r.id AS result_id,
            r.attempt_id,

            r.total_questions,
            r.attempted_questions,
            r.correct_answers,
            r.wrong_answers,
            r.unanswered_questions,

            r.total_marks,
            r.obtained_marks,
            r.percentage,
            r.grade,
            r.result_status,
            r.created_at,

            s.student_code,
            s.full_name,
            s.email,

            e.title AS exam_title,
            e.exam_type,

            sub.name AS subject_name

        FROM results r

        INNER JOIN exams e
            ON e.id = r.exam_id

        INNER JOIN students s
            ON s.id = r.student_id

        LEFT JOIN subjects sub
            ON sub.id = e.subject_id

        $whereSql

        ORDER BY
            r.created_at DESC,
            r.id DESC

        LIMIT $perPage
        OFFSET $offset
        "
    );

    $dataStmt->execute(
        $params
    );

    $rows =
        $dataStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'ExamSphere teacher result history failed: ' .
        $exception->getMessage()
    );

    $pageError =
        'Result history could not be loaded right now.';
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

<meta
    name="theme-color"
    content="#f5f5dc"
>

<title>
    Your Result History | ExamSphere
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
    --olive:#556b2f;
    --cream:#f5f5dc;
    --page:#f5f2ea;
    --text:#382e29;
    --muted:#8b8077;
    --line:#e8e0d5;
    --soft:#f5f0e7;
    --white:#ffffff;
    --pass:#4e713f;
    --fail:#9a5047;
}

*{
    box-sizing:border-box;
}

body.portal-body{
    margin:0;
    background:
        linear-gradient(
            180deg,
            #f7f4eb 0%,
            #f3f0e8 100%
        );
    color:var(--text);
    font-family:'Poppins',sans-serif;
}

.teacher-history-page{
    width:min(
        1480px,
        calc(100vw - 24px)
    );
    margin:0 auto;
    padding:20px 0 55px;
}

.history-heading{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:20px;
    margin-bottom:18px;
}

.history-heading h1{
    margin:0;
    color:#392c26;
    font-size:1.45rem;
    font-weight:900;
    letter-spacing:-.035em;
}

.history-heading p{
    margin:5px 0 0;
    color:var(--muted);
    font-size:.61rem;
}

.result-total{
    display:flex;
    align-items:baseline;
    gap:7px;
    white-space:nowrap;
}

.result-total strong{
    color:var(--earth);
    font-size:1.45rem;
    font-weight:900;
    line-height:1;
}

.result-total span{
    color:var(--muted);
    font-size:.57rem;
}

.history-card{
    overflow:hidden;
    border:1px solid rgba(93,64,55,.09);
    border-radius:20px;
    background:rgba(255,255,255,.89);
    box-shadow:
        0 17px 40px rgba(66,48,40,.07);
}

.table-wrap{
    width:100%;
    overflow-x:auto;
}

.history-table{
    width:100%;
    min-width:1080px;
    border-collapse:collapse;
}

.history-table thead th{
    padding:12px 16px;
    border-bottom:1px solid #ded6ca;
    background:#f6f1e8;
    color:#75695f;
    text-align:left;
    font-size:.50rem;
    font-weight:850;
    letter-spacing:.08em;
    text-transform:uppercase;
    white-space:nowrap;
}

.history-table tbody td{
    padding:15px 16px;
    border-bottom:1px solid #ebe4da;
    background:#fff;
    vertical-align:middle;
}

.history-table tbody tr:last-child td{
    border-bottom:0;
}

.history-table tbody tr:hover td{
    background:#fdfbf7;
}

.exam-title{
    display:block;
    max-width:280px;
    overflow:hidden;
    color:#372a24;
    font-size:.61rem;
    font-weight:850;
    line-height:1.45;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.exam-meta{
    display:block;
    margin-top:3px;
    color:#9a8e84;
    font-size:.48rem;
    line-height:1.4;
}

.score-main{
    display:block;
    color:#291f1b;
    font-size:.67rem;
    font-weight:900;
    line-height:1.35;
}

.score-sub{
    display:block;
    margin-top:3px;
    color:#9a8e84;
    font-size:.48rem;
}

.correct-wrong{
    display:block;
    color:#2f2621;
    font-size:.65rem;
    font-weight:900;
}

.correct-wrong-sub{
    display:block;
    margin-top:3px;
    color:#9a8e84;
    font-size:.47rem;
}

.grade-chip{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:42px;
    height:31px;
    padding:0 9px;
    border-radius:10px;
    background:#f2ece1;
    color:#5c4a3f;
    font-size:.59rem;
    font-weight:900;
}

.status-chip{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 9px;
    border-radius:999px;
    font-size:.49rem;
    font-weight:900;
    white-space:nowrap;
}

.status-chip.pass{
    background:#edf5e9;
    color:var(--pass);
}

.status-chip.fail{
    background:#faece9;
    color:var(--fail);
}

.date-text{
    color:#5d5048;
    font-size:.52rem;
    white-space:nowrap;
}

.action-group{
    display:flex;
    align-items:center;
    gap:7px;
}

.action{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    min-height:35px;
    padding:0 10px;
    border-radius:10px;
    text-decoration:none;
    font-size:.50rem;
    font-weight:850;
    white-space:nowrap;
}

.action-view{
    background:var(--earth);
    color:#fff;
    min-width:92px;
}

.action-view:hover{
    background:#402b25;
    color:#fff;
}

.action-pdf{
    background:#edf4e7;
    color:var(--olive);
}

.action-pdf:hover{
    background:#e3eddc;
    color:var(--olive);
}

.empty{
    padding:65px 20px;
    text-align:center;
}

.empty-icon{
    width:55px;
    height:55px;
    margin:0 auto 12px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:16px;
    background:#f3eee5;
    color:#9b8f84;
}

.empty small{
    color:var(--olive);
    font-size:.52rem;
    font-weight:900;
    letter-spacing:.11em;
}

.empty h2{
    margin:7px 0 5px;
    color:var(--earth);
    font-size:.85rem;
    font-weight:900;
}

.empty p{
    margin:0 auto;
    max-width:480px;
    color:var(--muted);
    font-size:.58rem;
    line-height:1.7;
}

.error-box{
    margin-bottom:16px;
    padding:12px 14px;
    border-radius:12px;
    background:#fbebe8;
    color:var(--fail);
    font-size:.59rem;
}

.pagination{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    padding:14px 16px;
    border-top:1px solid var(--line);
    background:#fcfaf5;
}

.pagination-info{
    color:var(--muted);
    font-size:.51rem;
    font-weight:700;
}

.pagination-links{
    display:flex;
    gap:5px;
}

.page-btn{
    width:30px;
    height:30px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:1px solid var(--line);
    border-radius:8px;
    background:#fff;
    color:var(--earth);
    text-decoration:none;
    font-size:.51rem;
    font-weight:850;
}

.page-btn:hover,
.page-btn.active{
    border-color:var(--earth);
    background:var(--earth);
    color:#fff;
}

.page-btn.disabled{
    pointer-events:none;
    opacity:.4;
}

@media(max-width:800px){

    .teacher-history-page{
        width:calc(100vw - 16px);
        padding-top:14px;
    }

    .history-heading{
        align-items:flex-start;
        flex-direction:column;
        gap:9px;
    }

    .history-heading h1{
        font-size:1.28rem;
    }

    .history-heading p{
        font-size:.57rem;
    }

    .result-total{
        margin-left:1px;
    }

}

@media(max-width:480px){

    .teacher-history-page{
        width:calc(100vw - 12px);
    }

    .pagination{
        align-items:flex-start;
        flex-direction:column;
    }

}


/* Readability upgrade — same layout, bigger and bolder typography */
body.portal-body{font-size:16px;}
.teacher-history-page{font-size:16px;}
.history-heading h1{font-size:2.05rem !important;font-weight:900 !important;line-height:1.2;}
.history-heading p{font-size:1rem !important;font-weight:600 !important;line-height:1.6;}
.result-total strong{font-size:1.35rem !important;font-weight:900 !important;}
.result-total span{font-size:.85rem !important;font-weight:700 !important;}
.history-table th{font-size:.82rem !important;font-weight:900 !important;}
.history-table td{font-size:.9rem !important;font-weight:600 !important;line-height:1.5;}
.exam-title{font-size:1rem !important;font-weight:900 !important;}
.exam-meta{font-size:.78rem !important;font-weight:600 !important;}
.score-main{font-size:1.2rem !important;font-weight:900 !important;}
.score-sub{font-size:.8rem !important;font-weight:700 !important;}
.correct-wrong{font-size:1.05rem !important;font-weight:900 !important;}
.correct-wrong-sub{font-size:.78rem !important;font-weight:700 !important;}
.grade-chip,.status-chip{font-size:.78rem !important;font-weight:900 !important;}
.date-text{font-size:.82rem !important;font-weight:700 !important;}
.action{font-size:.78rem !important;font-weight:900 !important;}
.error-box{font-size:.85rem !important;font-weight:700 !important;}
.pagination-info{font-size:.78rem !important;font-weight:800 !important;}
.page-btn{font-size:.76rem !important;font-weight:900 !important;}
.empty small{font-size:.72rem !important;font-weight:900 !important;}
.empty h2{font-size:1.1rem !important;font-weight:900 !important;}
.empty p{font-size:.82rem !important;font-weight:600 !important;line-height:1.7;}
@media(max-width:800px){
.history-heading h1{font-size:1.65rem !important;}
.history-heading p{font-size:.9rem !important;}
.history-table td{font-size:.86rem !important;}
}
</style>

</head>

<body class="portal-body">

<div class="portal-layout">

<?php include 'includes/sidebar.php'; ?>

<main class="portal-main">

<div class="teacher-history-page">

<header class="history-heading">

<div>

<h1>
    Your result history
</h1>

<p>
    Review results submitted by students in your examinations.
</p>

</div>

<div class="result-total">

<strong>
    <?= (int)$totalRows ?>
</strong>

<span>
    results
</span>

</div>

</header>


<?php if ($pageError !== ''): ?>

<div class="error-box">

<i class="fa-solid fa-circle-exclamation me-1"></i>

<?= teacher_history_e(
    $pageError
) ?>

</div>

<?php endif; ?>


<section class="history-card">

<?php if ($rows): ?>

<div class="table-wrap">

<table class="history-table">

<thead>

<tr>

<th>
    Exam
</th>

<th>
    Score
</th>

<th>
    Correct / Wrong
</th>

<th>
    Grade
</th>

<th>
    Status
</th>

<th>
    Date
</th>

<th>
    Action
</th>

</tr>

</thead>


<tbody>

<?php foreach (
    $rows as $row
): ?>

<?php

$isPass =
    (string)(
        $row['result_status']
        ?? ''
    ) === 'Pass';

?>

<tr>

<td>

<span class="exam-title">

<?= teacher_history_e(
    $row['exam_title']
) ?>

</span>

<span class="exam-meta">

<?= teacher_history_e(
    $row['subject_name']
        ?? 'General'
) ?>

&nbsp; · &nbsp;

<?= teacher_history_e(
    $row['exam_type']
) ?>

&nbsp; · &nbsp;

<?= teacher_history_e(
    $row['full_name']
) ?>

</span>

</td>


<td>

<span class="score-main">

<?= teacher_history_number(
    $row['percentage']
) ?>%

</span>

<span class="score-sub">

<?= teacher_history_number(
    $row['obtained_marks']
) ?>

/

<?= teacher_history_number(
    $row['total_marks']
) ?>

marks

</span>

</td>


<td>

<span class="correct-wrong">

<?= (int)(
    $row['correct_answers']
    ?? 0
) ?>

/

<?= (int)(
    $row['wrong_answers']
    ?? 0
) ?>

</span>

<span class="correct-wrong-sub">

<?= (int)(
    $row['attempted_questions']
    ?? 0
) ?>

of

<?= (int)(
    $row['total_questions']
    ?? 0
) ?>

attempted

</span>

</td>


<td>

<span class="grade-chip">

<?= teacher_history_e(
    $row['grade']
) ?>

</span>

</td>


<td>

<span
    class="status-chip <?= $isPass
        ? 'pass'
        : 'fail'
    ?>"
>

<i class="fa-solid <?= $isPass
    ? 'fa-circle-check'
    : 'fa-circle-xmark'
?>"></i>

<?= $isPass
    ? 'Passed'
    : 'Failed'
?>

</span>

</td>


<td>

<span class="date-text">

<?= teacher_history_e(
    teacher_history_date(
        $row['created_at']
    )
) ?>

</span>

</td>


<td>

<div class="action-group">

<a
    class="action action-view"
    href="result_view.php?id=<?= (int)$row['result_id'] ?>"
>

<i class="fa-solid fa-eye"></i>

View Result

</a>

</div>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<?php if ($totalPages > 1): ?>

<div class="pagination">

<div class="pagination-info">

Page
<?= (int)$page ?>
of
<?= (int)$totalPages ?>

</div>


<div class="pagination-links">

<a
    class="page-btn <?= $page <= 1
        ? 'disabled'
        : ''
    ?>"
    href="<?= teacher_history_url(
        max(
            1,
            $page - 1
        ),
        $search,
        $examId,
        $status
    ) ?>"
>

<i class="fa-solid fa-angle-left"></i>

</a>


<?php

$startPage =
    max(
        1,
        $page - 2
    );

$endPage =
    min(
        $totalPages,
        $page + 2
    );

?>

<?php for (
    $number = $startPage;
    $number <= $endPage;
    $number++
): ?>

<a
    class="page-btn <?= $number === $page
        ? 'active'
        : ''
    ?>"
    href="<?= teacher_history_url(
        $number,
        $search,
        $examId,
        $status
    ) ?>"
>

<?= (int)$number ?>

</a>

<?php endfor; ?>


<a
    class="page-btn <?= $page >= $totalPages
        ? 'disabled'
        : ''
    ?>"
    href="<?= teacher_history_url(
        min(
            $totalPages,
            $page + 1
        ),
        $search,
        $examId,
        $status
    ) ?>"
>

<i class="fa-solid fa-angle-right"></i>

</a>

</div>

</div>

<?php endif; ?>


<?php else: ?>

<div class="empty">

<div class="empty-icon">

<i class="fa-solid fa-chart-column"></i>

</div>

<small>
    RESULT HISTORY
</small>

<h2>
    No student results found.
</h2>

<p>
    Once students complete one of your examinations,
    their result history will appear here.
</p>

</div>

<?php endif; ?>

</section>

</div>

</main>

</div>

</body>

</html>
