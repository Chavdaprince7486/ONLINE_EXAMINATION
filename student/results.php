<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {
    header('Location: ../auth/login.php');
    exit;
}

$studentId = (int) $_SESSION['user_id'];
$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = ($page && $page > 0) ? $page : 1;

$perPage = 10;
$offset = ($page - 1) * $perPage;

$results = [];
$totalResults = 0;
$totalPages = 1;
$pageError = '';

$summary = [
    'completed' => 0,
    'passed' => 0,
    'failed' => 0,
    'average_score' => 0.0,
];

function results_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function results_number(float $value): string
{
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
}

function results_format_date(?string $value): string
{
    if (!$value) {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('d M Y');
    } catch (Throwable) {
        return '—';
    }
}

try {
    $where = [
        'r.student_id = ?',
        "ea.status IN ('Submitted', 'Auto Submitted')"
    ];
    $params = [$studentId];

    if ($search !== '') {
        $where[] = "(
            e.title LIKE ?
            OR COALESCE(s.name, '') LIKE ?
            OR COALESCE(s.code, '') LIKE ?
        )";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if ($statusFilter === 'Pass' || $statusFilter === 'Fail') {
        $where[] = 'r.result_status = ?';
        $params[] = $statusFilter;
    }

    $whereSql = implode(' AND ', $where);

    $summaryStatement = $conn->prepare(
        "SELECT
            COUNT(*) AS completed,
            SUM(CASE WHEN r.result_status = 'Pass' THEN 1 ELSE 0 END) AS passed,
            SUM(CASE WHEN r.result_status = 'Fail' THEN 1 ELSE 0 END) AS failed,
            COALESCE(AVG(r.percentage), 0) AS average_score
         FROM results r
         INNER JOIN exams e ON e.id = r.exam_id
         INNER JOIN exam_attempts ea
             ON ea.id = r.attempt_id
            AND ea.student_id = r.student_id
            AND ea.exam_id = r.exam_id
         LEFT JOIN subjects s ON s.id = e.subject_id
         WHERE {$whereSql}"
    );
    $summaryStatement->execute($params);
    $summaryRow = $summaryStatement->fetch(PDO::FETCH_ASSOC) ?: [];

    $summary['completed'] = (int) ($summaryRow['completed'] ?? 0);
    $summary['passed'] = (int) ($summaryRow['passed'] ?? 0);
    $summary['failed'] = (int) ($summaryRow['failed'] ?? 0);
    $summary['average_score'] = (float) ($summaryRow['average_score'] ?? 0);

    $countStatement = $conn->prepare(
        "SELECT COUNT(*)
         FROM results r
         INNER JOIN exams e ON e.id = r.exam_id
         INNER JOIN exam_attempts ea
             ON ea.id = r.attempt_id
            AND ea.student_id = r.student_id
            AND ea.exam_id = r.exam_id
         LEFT JOIN subjects s ON s.id = e.subject_id
         WHERE {$whereSql}"
    );
    $countStatement->execute($params);
    $totalResults = (int) $countStatement->fetchColumn();

    $totalPages = max(1, (int) ceil($totalResults / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $dataStatement = $conn->prepare(
        "SELECT
            r.id AS result_id,
            r.attempt_id,
            r.exam_id,
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
            e.title AS exam_title,
            e.exam_type,
            e.duration_minutes,
            e.passing_marks,
            s.name AS subject_name,
            s.code AS subject_code,
            ea.started_at,
            ea.submitted_at,
            ea.status AS attempt_status
         FROM results r
         INNER JOIN exams e ON e.id = r.exam_id
         INNER JOIN exam_attempts ea
             ON ea.id = r.attempt_id
            AND ea.student_id = r.student_id
            AND ea.exam_id = r.exam_id
         LEFT JOIN subjects s ON s.id = e.subject_id
         WHERE {$whereSql}
         ORDER BY r.created_at DESC, r.id DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $dataStatement->execute($params);
    $results = $dataStatement->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('Student results page failed: ' . $exception->getMessage());
    $pageError = 'Results are temporarily unavailable. Please try again.';
}

$filterActive = $search !== '' || $statusFilter !== '';
$queryBase = [];
if ($search !== '') {
    $queryBase['search'] = $search;
}
if ($statusFilter !== '') {
    $queryBase['status'] = $statusFilter;
}

function results_page_url(int $page, array $queryBase): string
{
    $query = $queryBase;
    $query['page'] = $page;
    return '?' . http_build_query($query);
}

$todayLabel = (new DateTimeImmutable('now'))->format('D, d M Y');

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#F5F5DC">
    <title>Results | ExamSphere</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">

    <style>
        :root{--bg:#F5F5DC;--brown:#5D4037;--brown2:#3E2723;--olive:#556B2F;--text:#302A26;--muted:#7D736B;--line:#E5DED2;--white:#fff;--soft:#F2EEE4;--pass:#EDF5E5;--fail:#F8EDEA}
        *{box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{margin:0;background:var(--bg);color:var(--text);font-family:Poppins,Arial,sans-serif}
        .results-wrap{width:min(1480px,calc(100% - 36px));margin:34px auto 70px}
        .results-hero{display:flex;justify-content:space-between;align-items:flex-end;gap:25px;margin-bottom:24px}
        .kicker{display:inline-flex;align-items:center;gap:8px;color:var(--olive);font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
        .results-hero h1{margin:9px 0 10px;color:var(--brown);font-size:clamp(34px,4.6vw,56px);line-height:1.04;font-weight:800;letter-spacing:-.03em}
        .results-hero h1 em{font-weight:600}
        .results-hero p{max-width:850px;margin:0;color:var(--muted);font-size:13px;line-height:1.8}
        .hero-badge{display:flex;align-items:center;gap:12px;padding:15px 17px;border:1px solid var(--line);border-radius:18px;background:rgba(255,255,255,.8);box-shadow:0 14px 35px rgba(62,39,35,.06);min-width:260px}
        .hero-badge-icon{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;background:var(--brown);color:#fff}
        .hero-badge strong,.hero-badge span{display:block}
        .hero-badge strong{font-size:11px}
        .hero-badge span{font-size:9px;color:var(--muted);margin-top:2px}

        .error-box{margin-bottom:18px;padding:14px 17px;border:1px solid #E8B7B7;border-radius:15px;background:#FFF5F5;color:#8C3737;font-size:11px;font-weight:700}
        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px}
        .stat{display:flex;align-items:center;gap:12px;padding:16px;border:1px solid var(--line);border-radius:18px;background:rgba(255,255,255,.82)}
        .stat i{width:40px;height:40px;display:grid;place-items:center;border-radius:12px;background:var(--soft);color:var(--brown)}
        .stat span,.stat strong{display:block}.stat span{font-size:9px;color:var(--muted)}.stat strong{margin-top:2px;font-size:18px;color:var(--brown2)}

        .filter{padding:11px;border:1px solid var(--line);border-radius:18px;background:#fff;box-shadow:0 14px 38px rgba(62,39,35,.06);margin-bottom:28px}
        .filter form{display:grid;grid-template-columns:1fr 250px auto auto;gap:9px}
        .field{height:48px;display:flex;align-items:center;gap:8px;padding:0 14px;border:1px solid #DDD4C7;border-radius:13px;background:#FCFBF8}
        .field i{font-size:12px;color:#887B70}.field input,.field select{width:100%;border:0;outline:0;background:transparent;color:var(--text);font:500 11px Poppins}
        .btn{min-height:48px;padding:0 16px;border:0;border-radius:13px;display:inline-flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;background:var(--brown);color:#fff;font-size:11px;font-weight:800;cursor:pointer}
        .btn:hover{background:var(--brown2);color:#fff}.btn.olive{background:var(--olive)}.btn.light{background:#EEE9DF;color:var(--brown)}

        .section-head{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:15px}
        .section-head small{font-size:9px;font-weight:800;letter-spacing:.13em;color:#74695F}.section-head h2{margin:4px 0 0;font-size:24px;color:var(--brown2)}
        .count strong{font-size:22px;color:var(--brown)}.count span{margin-left:4px;font-size:10px;color:var(--muted)}

        .results-panel{border:1px solid var(--line);border-radius:22px;background:rgba(255,255,255,.96);box-shadow:0 18px 48px rgba(62,39,35,.07);overflow:hidden}
        .table-wrap{overflow-x:auto}
        table{width:100%;min-width:1050px;border-collapse:collapse}
        thead th{padding:14px 16px;background:#F4F0E5;border-bottom:1px solid var(--line);text-align:left;color:#74695F;font-size:8.5px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
        tbody td{padding:16px;border-bottom:1px solid #EEE8DE;vertical-align:middle;font-size:10px}
        tbody tr:last-child td{border-bottom:0}
        tbody tr{transition:.2s ease}tbody tr:hover{background:#FCFAF6}
        .exam-title{color:var(--brown2);font-size:11px;font-weight:800;line-height:1.45}.exam-meta{margin-top:4px;color:var(--muted);font-size:8.5px}
        .score-main{color:var(--brown2);font-size:12px;font-weight:800}.score-sub{display:block;margin-top:3px;color:var(--muted);font-size:8.5px}
        .grade-chip{min-width:42px;padding:7px 9px;display:inline-flex;align-items:center;justify-content:center;border-radius:10px;background:#F2ECE0;color:var(--brown2);font-size:10px;font-weight:800}
        .status-pill{display:inline-flex;align-items:center;gap:5px;padding:6px 9px;border-radius:999px;font-size:8.5px;font-weight:800}.status-pass{background:var(--pass);color:#4D662C}.status-fail{background:var(--fail);color:#8D5146}
        .action-group{display:flex;gap:7px}.action{min-height:37px;padding:0 11px;display:inline-flex;align-items:center;justify-content:center;gap:6px;border-radius:10px;text-decoration:none;font-size:8.5px;font-weight:800;white-space:nowrap}.action-view{background:var(--brown);color:#fff}.action-pdf{background:#ECF2E2;color:var(--olive)}
        .empty{padding:70px 25px;border-top:1px solid var(--line);text-align:center;background:rgba(255,255,255,.8)}
        .empty-icon,.error-icon{width:65px;height:65px;margin:0 auto 14px;border-radius:19px;display:grid;place-items:center;font-size:22px}.empty-icon{background:#F0EBE2;color:var(--brown)}.error-icon{background:#FBEFEC;color:#964C41}
        .empty small,.error-box small{font-size:9px;font-weight:800;letter-spacing:.14em;color:#766A60}.empty h2{margin:8px 0;font-size:22px;color:var(--brown2)}.empty p{max-width:640px;margin:0 auto 18px;color:#7E736A;font-size:10px;line-height:1.7}
        .table-footer{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:15px 17px;border-top:1px solid var(--line);background:#FCFAF6}
        .page-info{color:var(--muted);font-size:9px}.pages{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.page-link{width:34px;height:34px;display:grid;place-items:center;border:1px solid #DED6CA;border-radius:10px;background:#fff;color:var(--brown);text-decoration:none;font-size:9px;font-weight:800}.page-link.active{background:var(--olive);border-color:var(--olive);color:#fff}.page-link.disabled{opacity:.45;pointer-events:none}

        @media(max-width:1080px){.stats{grid-template-columns:repeat(2,1fr)}.filter form{grid-template-columns:1fr 1fr}.filter .btn{width:100%}.results-hero{align-items:flex-start;flex-direction:column}.hero-badge{width:100%}.results-panel{border-radius:18px}}
        @media(max-width:650px){.results-wrap{width:calc(100% - 18px);margin-top:20px}.stats{grid-template-columns:1fr}.filter form{grid-template-columns:1fr}.section-head{align-items:flex-start;gap:12px}.table-footer{align-items:flex-start;flex-direction:column}.pages{justify-content:flex-start}.hero-badge{min-width:0}}
        @media print{body{background:#fff}.filter,.hero-badge,.table-footer,.results-back{display:none!important}.results-panel{box-shadow:none}}
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<main class="results-wrap">

    <section class="results-hero">
        <div>
            <span class="kicker"><i class="fa-solid fa-chart-column"></i> Exam History</span>
            <h1>Review your. <em>Results.</em></h1>
            <p>Track every completed examination, open detailed analysis, and see how your preparation is progressing over time.</p>
        </div>
        <div class="hero-badge">
            <div class="hero-badge-icon"><i class="fa-solid fa-calendar-check"></i></div>
            <div><strong>Result history</strong><span><?= results_escape($todayLabel) ?> · Verified attempts only</span></div>
        </div>
    </section>

    <?php if ($pageError !== ''): ?>
        <div class="error-box">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <?= results_escape($pageError) ?>
        </div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat"><i class="fa-solid fa-circle-check"></i><div><span>Completed results</span><strong><?= $summary['completed'] ?></strong></div></div>
        <div class="stat"><i class="fa-solid fa-trophy"></i><div><span>Passed</span><strong><?= $summary['passed'] ?></strong></div></div>
        <div class="stat"><i class="fa-solid fa-circle-xmark"></i><div><span>Failed</span><strong><?= $summary['failed'] ?></strong></div></div>
        <div class="stat"><i class="fa-solid fa-chart-line"></i><div><span>Average score</span><strong><?= results_number((float) $summary['average_score']) ?>%</strong></div></div>
    </section>

    <section class="filter">
        <form method="GET" action="results.php">
            <label class="field">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" name="search" value="<?= results_escape($search) ?>" placeholder="Search exam or subject..." autocomplete="off">
            </label>

            <label class="field">
                <i class="fa-solid fa-filter"></i>
                <select name="status">
                    <option value="">All results</option>
                    <option value="Pass" <?= $statusFilter === 'Pass' ? 'selected' : '' ?>>Passed</option>
                    <option value="Fail" <?= $statusFilter === 'Fail' ? 'selected' : '' ?>>Failed</option>
                </select>
            </label>

            <button type="submit" class="btn"><i class="fa-solid fa-filter"></i> Filter</button>
            <?php if ($filterActive): ?>
                <a href="results.php" class="btn light">Clear</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="section-head">
        <div><small>COMPLETED EXAMINATIONS</small><h2>Your result history</h2></div>
        <div class="count"><strong><?= $totalResults ?></strong><span><?= $totalResults === 1 ? 'result' : 'results' ?></span></div>
    </section>

    <section class="results-panel">
        <?php if (empty($results) && $pageError === ''): ?>
            <div class="empty">
                <div class="empty-icon"><i class="fa-solid fa-chart-column"></i></div>
                <small><?= $filterActive ? 'NO MATCHING RESULTS' : 'NO COMPLETED RESULTS' ?></small>
                <h2><?= $filterActive ? 'No matching results were found.' : 'Your result history starts here.' ?></h2>
                <p><?= $filterActive ? 'Try another search or clear the active filter to view your complete result history.' : 'Complete a practice or live examination and your verified result will appear automatically on this page.' ?></p>
                <?php if ($filterActive): ?><a href="results.php" class="btn">Show all results</a><?php else: ?><a href="practice_exams.php" class="btn"><i class="fa-solid fa-book-open"></i> Start practice</a><?php endif; ?>
            </div>
        <?php elseif ($pageError === ''): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>Score</th>
                            <th>Correct / Wrong</th>
                            <th>Grade</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results as $row): ?>
                        <?php
                        $isPass = (string) $row['result_status'] === 'Pass';
                        $percentage = (float) $row['percentage'];
                        $attempted = (int) $row['attempted_questions'];
                        $totalQuestions = (int) $row['total_questions'];
                        ?>
                        <tr>
                            <td>
                                <div class="exam-title"><?= results_escape((string) $row['exam_title']) ?></div>
                                <div class="exam-meta"><?= results_escape((string) ($row['subject_name'] ?? 'General')) ?> · <?= results_escape((string) $row['exam_type']) ?></div>
                            </td>
                            <td>
                                <span class="score-main"><?= results_number($percentage) ?>%</span>
                                <span class="score-sub"><?= results_number((float) $row['obtained_marks']) ?> / <?= results_number((float) $row['total_marks']) ?> marks</span>
                            </td>
                            <td>
                                <span class="score-main"><?= (int) $row['correct_answers'] ?> / <?= (int) $row['wrong_answers'] ?></span>
                                <span class="score-sub"><?= $attempted ?> of <?= $totalQuestions ?> attempted</span>
                            </td>
                            <td><span class="grade-chip"><?= results_escape((string) $row['grade']) ?></span></td>
                            <td><span class="status-pill <?= $isPass ? 'status-pass' : 'status-fail' ?>"><i class="fa-solid <?= $isPass ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i><?= $isPass ? 'Passed' : 'Failed' ?></span></td>
                            <td><?= results_escape(results_format_date((string) $row['created_at'])) ?></td>
                            <td>
                                <div class="action-group">
                                    <a class="action action-view" href="result.php?id=<?= (int) $row['result_id'] ?>"><i class="fa-solid fa-eye"></i> View</a>
                                    <a class="action action-pdf" href="ajax/download_result_pdf.php?attempt_id=<?= (int) $row['attempt_id'] ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf"></i> PDF</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="table-footer">
                    <div class="page-info">Page <?= $page ?> of <?= $totalPages ?></div>
                    <div class="pages">
                        <a class="page-link <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= results_page_url(max(1, $page - 1), $queryBase) ?>" aria-label="Previous page"><i class="fa-solid fa-angle-left"></i></a>
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        ?>
                        <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
                            <a class="page-link <?= $pageNumber === $page ? 'active' : '' ?>" href="<?= results_page_url($pageNumber, $queryBase) ?>"><?= $pageNumber ?></a>
                        <?php endfor; ?>
                        <a class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= results_page_url(min($totalPages, $page + 1), $queryBase) ?>" aria-label="Next page"><i class="fa-solid fa-angle-right"></i></a>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty">
                <div class="error-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <small>RESULT HISTORY UNAVAILABLE</small>
                <h2>We could not load your results.</h2>
                <p>Please refresh the page and try again.</p>
                <a href="results.php" class="btn"><i class="fa-solid fa-rotate-right"></i> Try again</a>
            </div>
        <?php endif; ?>
    </section>

</main>

</body>
</html>
