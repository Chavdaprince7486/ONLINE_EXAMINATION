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

$teacherId =
    (int) $_SESSION['user_id'];

function teacher_results_escape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$page =
    max(
        1,
        (int) (
            $_GET['page'] ?? 1
        )
    );

$perPage = 25;

$search =
    trim(
        (string) (
            $_GET['search'] ?? ''
        )
    );

$examId =
    filter_input(
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

$status =
    trim(
        (string) (
            $_GET['status'] ?? ''
        )
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

$exams = [];

try {

    $examStatement =
        $conn->prepare(
            "
            SELECT
                id,
                title

            FROM exams

            WHERE
                teacher_id = ?

            ORDER BY
                title ASC
            "
        );

    $examStatement->execute([
        $teacherId
    ]);

    $exams =
        $examStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Teacher result exam filter query failed: ' .
        $exception->getMessage()
    );
}

$where = [
    'e.teacher_id = ?'
];

$params = [
    $teacherId
];

if ($search !== '') {

    $where[] =
        "(
            s.full_name LIKE ?
            OR s.email LIKE ?
            OR e.title LIKE ?
        )";

    $value =
        '%' .
        $search .
        '%';

    $params[] = $value;
    $params[] = $value;
    $params[] = $value;
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

$totalRows = 0;
$items = [];

try {

    $countStatement =
        $conn->prepare(
            "
            SELECT COUNT(*)

            FROM results r

            INNER JOIN exams e
                ON e.id = r.exam_id

            INNER JOIN students s
                ON s.id = r.student_id

            $whereSql
            "
        );

    $countStatement->execute(
        $params
    );

    $totalRows =
        (int) $countStatement->fetchColumn();

    $totalPages =
        max(
            1,
            (int) ceil(
                $totalRows /
                $perPage
            )
        );

    if (
        $page > $totalPages
    ) {
        $page = $totalPages;
    }

    $offset =
        (
            $page - 1
        ) *
        $perPage;

    $dataStatement =
        $conn->prepare(
            "
            SELECT

                r.id,
                r.student_id,
                r.exam_id,

                r.obtained_marks,
                r.total_marks,

                r.percentage,
                r.grade,

                r.result_status,
                r.created_at,

                s.full_name,
                s.email,

                e.title

            FROM results r

            INNER JOIN exams e
                ON e.id = r.exam_id

            INNER JOIN students s
                ON s.id = r.student_id

            $whereSql

            ORDER BY
                r.created_at DESC,
                r.id DESC

            LIMIT ?
            OFFSET ?
            "
        );

    $dataStatement->execute(
        array_merge(
            $params,
            [
                $perPage,
                $offset
            ]
        )
    );

    $items =
        $dataStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Teacher results query failed: ' .
        $exception->getMessage()
    );

    $totalRows = 0;
    $totalPages = 1;
    $page = 1;
    $items = [];
}

$evaluated =
    $totalRows;

$passed = 0;
$average = 0.0;

foreach ($items as $item) {

    if (
        $item['result_status'] === 'Pass'
    ) {
        $passed++;
    }

    $average +=
        (float) $item['percentage'];
}

if (!empty($items)) {

    $average =
        $average /
        count($items);
}

$baseQuery = [
    'search' =>
        $search,
    'exam_id' =>
        $examId,
    'status' =>
        $status
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <title>
        Student Results | ExamSphere
    </title>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

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
</head>

<body class="portal-body">

<div class="portal-layout">

    <?php include 'includes/sidebar.php'; ?>

    <main class="portal-main">

        <header class="portal-topbar">

            <div>

                <h1>
                    Student results
                </h1>

                <p class="portal-subtitle">
                    Review performance from the examinations you own.
                </p>

            </div>

        </header>

        <section class="row g-3">

            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <h2><?= (int) $evaluated ?></h2>
                    <p>Matching attempts</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h2><?= (int) $passed ?></h2>
                    <p>Passed on current page</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <h2><?= number_format($average, 2) ?>%</h2>
                    <p>Current page average</p>
                </div>
            </div>

        </section>

        <section class="portal-panel">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">

                <div>
                    <h2 class="h5 mb-1">
                        Result records
                    </h2>

                    <small class="text-muted">
                        Search only within examinations owned by you.
                    </small>
                </div>

            </div>

            <form method="get" class="row g-2 mb-4">

                <div class="col-lg-4">
                    <label class="form-label" for="teacherResultSearch">
                        Search
                    </label>

                    <input
                        id="teacherResultSearch"
                        type="search"
                        class="form-control"
                        name="search"
                        value="<?= teacher_results_escape($search) ?>"
                        placeholder="Student, email or exam"
                    >
                </div>

                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="teacherExam">
                        Exam
                    </label>

                    <select
                        id="teacherExam"
                        name="exam_id"
                        class="form-select"
                    >
                        <option value="">
                            All my exams
                        </option>

                        <?php foreach ($exams as $exam): ?>

                            <option
                                value="<?= (int) $exam['id'] ?>"
                                <?= (
                                    $examId !== null &&
                                    $examId ===
                                    (int) $exam['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= teacher_results_escape(
                                    $exam['title']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="col-md-4 col-lg-2">

                    <label class="form-label" for="teacherStatus">
                        Result
                    </label>

                    <select
                        id="teacherStatus"
                        name="status"
                        class="form-select"
                    >
                        <option value="">
                            All
                        </option>

                        <option
                            value="Pass"
                            <?= $status === 'Pass' ? 'selected' : '' ?>
                        >
                            Pass
                        </option>

                        <option
                            value="Fail"
                            <?= $status === 'Fail' ? 'selected' : '' ?>
                        >
                            Fail
                        </option>
                    </select>

                </div>

                <div class="col-md-4 col-lg-3 d-flex align-items-end gap-2">

                    <button
                        type="submit"
                        class="btn btn-success flex-grow-1"
                    >
                        <i class="fa-solid fa-filter me-1"></i>
                        Filter
                    </button>

                    <a
                        href="results.php"
                        class="btn btn-light"
                        title="Clear filters"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </a>

                </div>

            </form>

            <div class="table-responsive">

                <table class="table align-middle">

                    <thead>
                    <tr>
                        <th>Student</th>
                        <th>Exam</th>
                        <th>Marks</th>
                        <th>Percentage</th>
                        <th>Grade</th>
                        <th>Result</th>
                        <th>Date</th>
                    </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($items as $item): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= teacher_results_escape(
                                        $item['full_name']
                                    ) ?>
                                </strong>

                                <small class="d-block text-muted">
                                    <?= teacher_results_escape(
                                        $item['email']
                                    ) ?>
                                </small>
                            </td>

                            <td>
                                <?= teacher_results_escape(
                                    $item['title']
                                ) ?>
                            </td>

                            <td>
                                <?= teacher_results_escape(
                                    $item['obtained_marks']
                                ) ?>

                                /

                                <?= teacher_results_escape(
                                    $item['total_marks']
                                ) ?>
                            </td>

                            <td>
                                <?= teacher_results_escape(
                                    $item['percentage']
                                ) ?>%
                            </td>

                            <td>
                                <?= teacher_results_escape(
                                    $item['grade']
                                ) ?>
                            </td>

                            <td>
                                <span class="badge text-bg-<?= (
                                    $item['result_status'] === 'Pass'
                                )
                                    ? 'success'
                                    : 'danger'
                                ?>">
                                    <?= teacher_results_escape(
                                        $item['result_status']
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= teacher_results_escape(
                                    date(
                                        'd M Y, h:i A',
                                        strtotime(
                                            (string) $item['created_at']
                                        )
                                    )
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if (!$items): ?>

                        <tr>
                            <td
                                colspan="7"
                                class="text-center text-muted py-5"
                            >
                                No evaluated attempts match your filters.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <?php if ($totalPages > 1): ?>

                <nav
                    class="mt-4"
                    aria-label="Teacher result pages"
                >

                    <ul class="pagination justify-content-center flex-wrap">

                        <?php
                        $previousQuery =
                            $baseQuery +
                            [
                                'page' =>
                                    max(
                                        1,
                                        $page - 1
                                    )
                            ];

                        $nextQuery =
                            $baseQuery +
                            [
                                'page' =>
                                    min(
                                        $totalPages,
                                        $page + 1
                                    )
                            ];
                        ?>

                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a
                                class="page-link"
                                href="?<?= http_build_query($previousQuery) ?>"
                            >
                                Previous
                            </a>
                        </li>

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

                        for (
                            $pageNumber = $startPage;
                            $pageNumber <= $endPage;
                            $pageNumber++
                        ):
                        ?>

                            <?php
                            $pageQuery =
                                $baseQuery +
                                [
                                    'page' =>
                                        $pageNumber
                                ];
                            ?>

                            <li
                                class="page-item <?= (
                                    $pageNumber === $page
                                )
                                    ? 'active'
                                    : ''
                                ?>"
                            >
                                <a
                                    class="page-link"
                                    href="?<?= http_build_query($pageQuery) ?>"
                                >
                                    <?= (int) $pageNumber ?>
                                </a>
                            </li>

                        <?php endfor; ?>

                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a
                                class="page-link"
                                href="?<?= http_build_query($nextQuery) ?>"
                            >
                                Next
                            </a>
                        </li>

                    </ul>

                </nav>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>
