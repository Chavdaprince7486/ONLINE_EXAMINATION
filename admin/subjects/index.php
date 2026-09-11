<?php
declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: ../../auth/login.php');
    exit;
}

$page_title = 'Subject Management';

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$allowedStatuses = [
    'Active',
    'Inactive'
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$conditions = [];
$params = [];

/*
 * Check whether the Subject -> Category migration exists.
 */
$hasCategoryRelation = false;

try {

    $columnCheck = $conn->query("
        SHOW COLUMNS
        FROM subjects
        LIKE 'category_id'
    ");

    $hasCategoryRelation =
        (bool)$columnCheck->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $exception) {

    error_log(
        'Subject category column check failed: ' .
        $exception->getMessage()
    );
}

/*
 * Search.
 */
if ($search !== '') {

    if ($hasCategoryRelation) {

        $conditions[] = "(
            s.name LIKE ?
            OR s.code LIKE ?
            OR s.description LIKE ?
            OR c.category_name LIKE ?
        )";

        $searchValue = '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;

    } else {

        $conditions[] = "(
            s.name LIKE ?
            OR s.code LIKE ?
            OR s.description LIKE ?
        )";

        $searchValue = '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }
}

/*
 * Status filter.
 */
if ($status !== '') {

    $conditions[] = 's.status = ?';

    $params[] = $status;
}

$whereSql = '';

if ($conditions) {
    $whereSql =
        'WHERE ' .
        implode(' AND ', $conditions);
}

$subjects = [];

try {

    if ($hasCategoryRelation) {

        $statement = $conn->prepare("
            SELECT
                s.id,
                s.category_id,
                s.name,
                s.code,
                s.description,
                s.status,
                s.created_at,

                c.category_name,

                (
                    SELECT COUNT(*)
                    FROM questions q
                    WHERE q.subject_id = s.id
                ) AS question_count,

                (
                    SELECT COUNT(*)
                    FROM exams e
                    WHERE e.subject_id = s.id
                ) AS exam_count

            FROM subjects s

            LEFT JOIN categories c
                ON c.id = s.category_id

            $whereSql

            ORDER BY
                s.created_at DESC,
                s.id DESC
        ");

    } else {

        $statement = $conn->prepare("
            SELECT
                s.id,
                NULL AS category_id,
                s.name,
                s.code,
                s.description,
                s.status,
                s.created_at,
                NULL AS category_name,

                (
                    SELECT COUNT(*)
                    FROM questions q
                    WHERE q.subject_id = s.id
                ) AS question_count,

                (
                    SELECT COUNT(*)
                    FROM exams e
                    WHERE e.subject_id = s.id
                ) AS exam_count

            FROM subjects s

            $whereSql

            ORDER BY
                s.created_at DESC,
                s.id DESC
        ");
    }

    $statement->execute($params);

    $subjects = $statement->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $exception) {

    error_log(
        'Subject list query failed: ' .
        $exception->getMessage()
    );
}

/*
 * Statistics.
 */
$totalSubjects = count($subjects);

$activeSubjects = 0;
$inactiveSubjects = 0;
$totalQuestions = 0;
$totalExams = 0;

foreach ($subjects as $subject) {

    if ($subject['status'] === 'Active') {
        $activeSubjects++;
    } else {
        $inactiveSubjects++;
    }

    $totalQuestions +=
        (int)$subject['question_count'];

    $totalExams +=
        (int)$subject['exam_count'];
}

function subject_index_e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function subject_index_status_class(
    string $status
): string {

    return strtolower(
        preg_replace(
            '/[^a-zA-Z0-9_-]/',
            '',
            $status
        )
    );
}

include "../includes/header.php";
?>

<style>

    .subject-page {
        position: relative;
    }

    .subject-page-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 22px;
        margin-bottom: 24px;
    }

    .subject-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #556b2f;
        font-size: .76rem;
        font-weight: 800;
        letter-spacing: .12em;
        margin-bottom: 8px;
    }

    .subject-page-header h1 {
        margin: 0;
    }

    .subject-page-header p {
        margin: 7px 0 0;
        color: #756d68;
    }

    .subject-add-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 43px;
        padding: 0 17px;
        border-radius: 12px;
        background: #556b2f;
        color: #fff;
        text-decoration: none;
        font-weight: 800;
        box-shadow: 0 10px 25px rgba(85,107,47,.18);
        transition: .2s ease;
        white-space: nowrap;
    }

    .subject-add-btn:hover {
        background: #465b27;
        color: #fff;
        transform: translateY(-2px);
    }

    .subject-migration-alert {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 22px;
        padding: 14px 16px;
        border-radius: 15px;
        background: rgba(154,107,22,.09);
        border: 1px solid rgba(154,107,22,.15);
        color: #715317;
    }

    .subject-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0,1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .subject-stat-card {
        min-height: 105px;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 19px;
        border-radius: 20px;
        background: rgba(255,255,255,.87);
        border: 1px solid rgba(93,64,55,.10);
        box-shadow: 0 14px 40px rgba(51,51,51,.07);
        backdrop-filter: blur(14px);
    }

    .subject-stat-icon {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: rgba(85,107,47,.10);
        color: #556b2f;
    }

    .subject-stat-card small {
        display: block;
        color: #786f69;
        margin-bottom: 4px;
    }

    .subject-stat-card strong {
        display: block;
        color: #333;
        font-size: 1.5rem;
        line-height: 1;
    }

    .subject-list-card {
        overflow: hidden;
        border-radius: 24px;
        background: rgba(255,255,255,.89);
        border: 1px solid rgba(93,64,55,.10);
        box-shadow: 0 20px 55px rgba(51,51,51,.08);
        backdrop-filter: blur(15px);
    }

    .subject-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        flex-wrap: wrap;
        padding: 21px 22px;
        border-bottom: 1px solid rgba(93,64,55,.08);
    }

    .subject-card-header h2 {
        margin: 0;
        color: #333;
        font-size: 1.08rem;
    }

    .subject-card-header p {
        margin: 5px 0 0;
        color: #77706b;
        font-size: .84rem;
    }

    .subject-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .subject-search {
        width: min(310px,100%);
    }

    .subject-search .form-control,
    .subject-search .btn {
        min-height: 40px;
    }

    .subject-filter {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 0 13px;
        border-radius: 11px;
        border: 1px solid rgba(93,64,55,.12);
        background: #fff;
        color: #655c57;
        font-size: .82rem;
        font-weight: 800;
        text-decoration: none;
        transition: .2s ease;
    }

    .subject-filter:hover,
    .subject-filter.active {
        background: #556b2f;
        border-color: #556b2f;
        color: #fff;
    }

    .subject-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .subject-table {
        width: 100%;
        min-width: 950px;
        border-collapse: collapse;
    }

    .subject-table th {
        padding: 15px 18px;
        background: #faf7f0;
        border-bottom: 1px solid rgba(93,64,55,.08);
        color: #625a55;
        text-align: left;
        font-size: .74rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .06em;
        white-space: nowrap;
    }

    .subject-table td {
        padding: 17px 18px;
        vertical-align: middle;
        color: #373330;
        border-bottom: 1px solid rgba(93,64,55,.07);
    }

    .subject-table tbody tr {
        transition: .2s ease;
    }

    .subject-table tbody tr:hover {
        background: rgba(245,245,220,.45);
    }

    .subject-code,
    .category-pill,
    .count-pill,
    .status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        border-radius: 999px;
        font-weight: 800;
    }

    .subject-code,
    .category-pill {
        padding: 6px 10px;
        font-size: .75rem;
    }

    .subject-code {
        background: rgba(93,64,55,.07);
        color: #5d4037;
    }

    .category-pill {
        background: rgba(85,107,47,.09);
        color: #556b2f;
    }

    .subject-table td strong {
        display: block;
        color: #34312f;
    }

    .subject-table td small {
        display: block;
        max-width: 320px;
        margin-top: 4px;
        color: #77706b;
        line-height: 1.4;
    }

    .count-pill {
        min-width: 34px;
        padding: 6px 10px;
        background: #faf7f0;
        color: #5d4037;
        font-size: .78rem;
    }

    .status-pill {
        padding: 7px 11px;
        font-size: .75rem;
    }

    .status-pill.active {
        background: rgba(85,107,47,.11);
        color: #556b2f;
    }

    .status-pill.inactive {
        background: rgba(163,58,50,.10);
        color: #96352f;
    }

    .subject-actions {
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .subject-action {
        width: 35px;
        height: 35px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        border: 1px solid rgba(93,64,55,.10);
        background: #fff;
        text-decoration: none;
        transition: .2s ease;
    }

    .subject-action:hover {
        transform: translateY(-2px);
    }

    .subject-action.view {
        color: #556b2f;
    }

    .subject-action.edit {
        color: #5d4037;
    }

    .subject-action.toggle {
        color: #806d27;
    }

    .subject-action.delete {
        color: #a33a32;
    }

    .subject-action.view:hover {
        background: rgba(85,107,47,.08);
    }

    .subject-action.edit:hover {
        background: rgba(93,64,55,.08);
    }

    .subject-action.toggle:hover {
        background: rgba(128,109,39,.08);
    }

    .subject-action.delete:hover {
        background: rgba(163,58,50,.08);
    }

    .subject-empty {
        padding: 55px 20px !important;
        text-align: center;
        color: #766e69 !important;
    }

    .subject-empty i {
        display: block;
        margin-bottom: 12px;
        color: #556b2f;
        font-size: 2rem;
    }

    @media (max-width: 1100px) {

        .subject-stat-grid {
            grid-template-columns: repeat(2,minmax(0,1fr));
        }
    }

    @media (max-width: 700px) {

        .subject-page-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .subject-add-btn {
            width: 100%;
        }

        .subject-stat-grid {
            grid-template-columns: 1fr;
        }

        .subject-toolbar {
            width: 100%;
        }

        .subject-search {
            width: 100%;
        }
    }

</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content subject-page">

            <section class="subject-page-header">

                <div>

                    <span class="subject-eyebrow">
                        <i class="fa-solid fa-book-open"></i>
                        ACADEMIC STRUCTURE
                    </span>

                    <h1>Subject Management</h1>

                    <p>
                        Manage examination subjects, categories and
                        linked academic content.
                    </p>

                </div>

                <a
                    href="add.php"
                    class="subject-add-btn"
                >
                    <i class="fa-solid fa-plus"></i>
                    Add Subject
                </a>

            </section>

            <?php if (!$hasCategoryRelation): ?>

                <div class="subject-migration-alert">

                    <i class="fa-solid fa-triangle-exclamation mt-1"></i>

                    <div>
                        <strong>
                            Category relationship is not installed.
                        </strong>

                        <div class="small mt-1">
                            Run
                            <code>
                                database/examsphere_phase_0_3_schema_migration.sql
                            </code>
                            to enable category-linked subjects.
                        </div>
                    </div>

                </div>

            <?php endif; ?>

            <section class="subject-stat-grid">

                <article class="subject-stat-card">

                    <div class="subject-stat-icon">
                        <i class="fa-solid fa-book"></i>
                    </div>

                    <div>
                        <small>Total Subjects</small>
                        <strong>
                            <?= $totalSubjects ?>
                        </strong>
                    </div>

                </article>

                <article class="subject-stat-card">

                    <div class="subject-stat-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div>
                        <small>Active Subjects</small>
                        <strong>
                            <?= $activeSubjects ?>
                        </strong>
                    </div>

                </article>

                <article class="subject-stat-card">

                    <div class="subject-stat-icon">
                        <i class="fa-solid fa-circle-question"></i>
                    </div>

                    <div>
                        <small>Linked Questions</small>
                        <strong>
                            <?= $totalQuestions ?>
                        </strong>
                    </div>

                </article>

                <article class="subject-stat-card">

                    <div class="subject-stat-icon">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>

                    <div>
                        <small>Linked Exams</small>
                        <strong>
                            <?= $totalExams ?>
                        </strong>
                    </div>

                </article>

            </section>

            <section class="subject-list-card">

                <header class="subject-card-header">

                    <div>

                        <h2>All Subjects</h2>

                        <p>
                            <?= count($subjects) ?>
                            record<?= count($subjects) === 1 ? '' : 's' ?>
                            shown
                        </p>

                    </div>

                    <form
                        method="get"
                        class="subject-toolbar"
                        autocomplete="off"
                    >

                        <div class="input-group subject-search">

                            <input
                                type="search"
                                name="search"
                                value="<?= subject_index_e($search) ?>"
                                class="form-control"
                                placeholder="Search subject, code, category..."
                            >

                            <?php if ($status !== ''): ?>

                                <input
                                    type="hidden"
                                    name="status"
                                    value="<?= subject_index_e($status) ?>"
                                >

                            <?php endif; ?>

                            <button
                                type="submit"
                                class="btn btn-dark"
                            >
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </button>

                        </div>

                        <a
                            href="index.php<?= $search !== ''
                                ? '?search=' . urlencode($search)
                                : '' ?>"
                            class="subject-filter <?= $status === ''
                                ? 'active'
                                : '' ?>"
                        >
                            All
                        </a>

                        <a
                            href="index.php?status=Active<?= $search !== ''
                                ? '&search=' . urlencode($search)
                                : '' ?>"
                            class="subject-filter <?= $status === 'Active'
                                ? 'active'
                                : '' ?>"
                        >
                            Active
                        </a>

                        <a
                            href="index.php?status=Inactive<?= $search !== ''
                                ? '&search=' . urlencode($search)
                                : '' ?>"
                            class="subject-filter <?= $status === 'Inactive'
                                ? 'active'
                                : '' ?>"
                        >
                            Inactive
                        </a>

                    </form>

                </header>

                <div class="subject-table-wrap">

                    <table class="subject-table">

                        <thead>

                            <tr>

                                <th>Code</th>

                                <th>Category</th>

                                <th>Subject</th>

                                <th>Questions</th>

                                <th>Exams</th>

                                <th>Status</th>

                                <th>Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($subjects as $subject): ?>

                            <tr>

                                <td data-label="Code">

                                    <span class="subject-code">
                                        <?= subject_index_e(
                                            $subject['code'] ?: '—'
                                        ) ?>
                                    </span>

                                </td>

                                <td data-label="Category">

                                    <?php if (
                                        !empty(
                                            $subject['category_name']
                                        )
                                    ): ?>

                                        <span class="category-pill">
                                            <i class="fa-solid fa-layer-group me-1"></i>
                                            <?= subject_index_e(
                                                $subject['category_name']
                                            ) ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td data-label="Subject">

                                    <strong>
                                        <?= subject_index_e(
                                            $subject['name']
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= subject_index_e(
                                            $subject['description']
                                            ?: 'No description added yet.'
                                        ) ?>
                                    </small>

                                </td>

                                <td data-label="Questions">

                                    <span class="count-pill">
                                        <?= (int)$subject['question_count'] ?>
                                    </span>

                                </td>

                                <td data-label="Exams">

                                    <span class="count-pill">
                                        <?= (int)$subject['exam_count'] ?>
                                    </span>

                                </td>

                                <td data-label="Status">

                                    <span
                                        class="status-pill <?= subject_index_status_class(
                                            (string)$subject['status']
                                        ) ?>"
                                    >
                                        <?= subject_index_e(
                                            $subject['status']
                                        ) ?>
                                    </span>

                                </td>

                                <td data-label="Actions">

                                    <div class="subject-actions">

                                        <a
                                            href="view.php?id=<?= (int)$subject['id'] ?>"
                                            class="subject-action view"
                                            title="View Subject"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </a>

                                        <a
                                            href="edit.php?id=<?= (int)$subject['id'] ?>"
                                            class="subject-action edit"
                                            title="Edit Subject"
                                        >
                                            <i class="fa-solid fa-pen"></i>
                                        </a>

                                        <a
                                            href="toggle-status.php?id=<?= (int)$subject['id'] ?>"
                                            class="subject-action toggle"
                                            title="Toggle Status"
                                        >
                                            <i class="fa-solid <?= $subject['status'] === 'Active'
                                                ? 'fa-toggle-on'
                                                : 'fa-toggle-off' ?>"
                                            ></i>
                                        </a>

                                        <a
                                            href="delete.php?id=<?= (int)$subject['id'] ?>"
                                            class="subject-action delete"
                                            title="Delete Subject"
                                            onclick="return confirm('Delete this subject? Only subjects without linked exams or questions can be deleted.');"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (!$subjects): ?>

                            <tr>

                                <td
                                    colspan="7"
                                    class="subject-empty"
                                >

                                    <i class="fa-solid fa-book-open"></i>

                                    <strong>
                                        No subjects found.
                                    </strong>

                                    <div class="small mt-1">
                                        Try changing your search or status
                                        filter.
                                    </div>

                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </main>

    </div>

</div>

<?php include "../includes/footer.php"; ?>