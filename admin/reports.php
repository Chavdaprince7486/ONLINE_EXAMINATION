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

function admin_results_escape(mixed $value): string
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

$status =
    trim(
        (string) (
            $_GET['status'] ?? ''
        )
    );

$dateFrom =
    trim(
        (string) (
            $_GET['date_from'] ?? ''
        )
    );

$dateTo =
    trim(
        (string) (
            $_GET['date_to'] ?? ''
        )
    );

$where = [];
$params = [];

if ($search !== '') {

    $where[] =
        "(
            s.full_name LIKE ?
            OR s.email LIKE ?
            OR e.title LIKE ?
        )";

    $searchValue =
        '%' .
        $search .
        '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if (
    in_array(
        $status,
        ['Pass', 'Fail'],
        true
    )
) {

    $where[] =
        'r.result_status = ?';

    $params[] =
        $status;
}

if (
    preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $dateFrom
    )
) {

    $where[] =
        'r.created_at >= ?';

    $params[] =
        $dateFrom .
        ' 00:00:00';

} else {

    $dateFrom = '';
}

if (
    preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $dateTo
    )
) {

    $where[] =
        'r.created_at <= ?';

    $params[] =
        $dateTo .
        ' 23:59:59';

} else {

    $dateTo = '';
}

$whereSql =
    empty($where)
        ? ''
        : 'WHERE ' .
          implode(
              ' AND ',
              $where
          );

$totalRows = 0;
$rows = [];

try {

    $countStatement =
        $conn->prepare(
            "
            SELECT COUNT(*)

            FROM results r

            INNER JOIN students s
                ON s.id = r.student_id

            INNER JOIN exams e
                ON e.id = r.exam_id

            $whereSql
            "
        );

    $countStatement->execute($params);

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

    $dataParams =
        $params;

    $dataParams[] =
        $perPage;

    $dataParams[] =
        $offset;

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

            INNER JOIN students s
                ON s.id = r.student_id

            INNER JOIN exams e
                ON e.id = r.exam_id

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

    $rows =
        $dataStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Admin results query failed: ' .
        $exception->getMessage()
    );

    $totalRows = 0;
    $totalPages = 1;
    $page = 1;
    $rows = [];
}

$baseQuery = [
    'search' =>
        $search,
    'status' =>
        $status,
    'date_from' =>
        $dateFrom,
    'date_to' =>
        $dateTo
];

$page_title =
    'Results | ExamSphere';

$page_css =
    'admin-subjects.css';

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
                        <i class="fa-solid fa-chart-column"></i>
                        EVALUATION CENTER
                    </span>

                    <h1>
                        Exam Results
                    </h1>

                    <p>
                        Search and review automatically evaluated
                        student examination attempts.
                    </p>
                </div>
            </div>

            <section class="subject-stat-row">

                <article>
                    <i class="fa-solid fa-file-circle-check"></i>
                    <span>
                        <small>Matching results</small>
                        <b><?= (int) $totalRows ?></b>
                    </span>
                </article>

                <article>
                    <i class="fa-solid fa-user-graduate"></i>
                    <span>
                        <small>Current page</small>
                        <b>
                            <?= (int) $page ?>
                            /
                            <?= (int) $totalPages ?>
                        </b>
                    </span>
                </article>

                <article>
                    <i class="fa-solid fa-filter"></i>
                    <span>
                        <small>Page size</small>
                        <b><?= (int) $perPage ?></b>
                    </span>
                </article>

            </section>

            <section class="subject-list-card">

                <div class="subject-card-heading list-heading">

                    <div>
                        <span class="mini-kicker">
                            HISTORY
                        </span>

                        <h2>
                            Result records
                        </h2>
                    </div>

                </div>

                <form
                    method="get"
                    class="row g-2 mb-4"
                >

                    <div class="col-lg-4">
                        <label
                            class="form-label"
                            for="resultSearch"
                        >
                            Search
                        </label>

                        <input
                            id="resultSearch"
                            type="search"
                            class="form-control"
                            name="search"
                            value="<?= admin_results_escape($search) ?>"
                            placeholder="Student, email or exam"
                        >
                    </div>

                    <div class="col-md-4 col-lg-2">
                        <label
                            class="form-label"
                            for="resultStatus"
                        >
                            Result
                        </label>

                        <select
                            id="resultStatus"
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

                    <div class="col-md-4 col-lg-2">
                        <label
                            class="form-label"
                            for="resultDateFrom"
                        >
                            From
                        </label>

                        <input
                            id="resultDateFrom"
                            type="date"
                            class="form-control"
                            name="date_from"
                            value="<?= admin_results_escape($dateFrom) ?>"
                        >
                    </div>

                    <div class="col-md-4 col-lg-2">
                        <label
                            class="form-label"
                            for="resultDateTo"
                        >
                            To
                        </label>

                        <input
                            id="resultDateTo"
                            type="date"
                            class="form-control"
                            name="date_to"
                            value="<?= admin_results_escape($dateTo) ?>"
                        >
                    </div>

                    <div class="col-md-4 col-lg-2 d-flex align-items-end gap-2">
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

                <div class="subject-table-wrap">

                    <table class="subject-table">

                        <thead>
                        <tr>
                            <th>Student</th>
                            <th>Exam</th>
                            <th>Marks</th>
                            <th>Percentage</th>
                            <th>Grade</th>
                            <th>Outcome</th>
                            <th>Date</th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($rows as $row): ?>

                            <tr>

                                <td data-label="Student">
                                    <b>
                                        <?= admin_results_escape(
                                            $row['full_name']
                                        ) ?>
                                    </b>

                                    <small class="d-block text-muted">
                                        <?= admin_results_escape(
                                            $row['email']
                                        ) ?>
                                    </small>
                                </td>

                                <td data-label="Exam">
                                    <b>
                                        <?= admin_results_escape(
                                            $row['title']
                                        ) ?>
                                    </b>
                                </td>

                                <td data-label="Marks">
                                    <span class="count-pill">
                                        <?= admin_results_escape(
                                            $row['obtained_marks']
                                        ) ?>

                                        /

                                        <?= admin_results_escape(
                                            $row['total_marks']
                                        ) ?>
                                    </span>
                                </td>

                                <td data-label="Percentage">
                                    <b>
                                        <?= admin_results_escape(
                                            $row['percentage']
                                        ) ?>%
                                    </b>
                                </td>

                                <td data-label="Grade">
                                    <?= admin_results_escape(
                                        $row['grade']
                                    ) ?>
                                </td>

                                <td data-label="Outcome">
                                    <span
                                        class="status-pill <?= (
                                            $row['result_status'] === 'Pass'
                                        )
                                            ? 'active'
                                            : 'inactive'
                                        ?>"
                                    >
                                        <?= admin_results_escape(
                                            $row['result_status']
                                        ) ?>
                                    </span>
                                </td>

                                <td data-label="Date">
                                    <?= admin_results_escape(
                                        date(
                                            'd M Y, h:i A',
                                            strtotime(
                                                (string) $row['created_at']
                                            )
                                        )
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (!$rows): ?>

                            <tr class="subject-empty">
                                <td colspan="7">
                                    <i class="fa-solid fa-chart-line"></i>
                                    No results match your filters.
                                </td>
                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

                <?php if ($totalPages > 1): ?>

                    <nav
                        class="mt-4"
                        aria-label="Result pages"
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
</div>

<script src="assets/js/admin-shell.js"></script>
</body>
</html>
