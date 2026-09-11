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

$page_title = 'Category Management';

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

if ($search !== '') {

    $conditions[] = "(
        c.category_name LIKE ?
        OR c.description LIKE ?
        OR c.icon LIKE ?
    )";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($status !== '') {

    $conditions[] = "c.status = ?";
    $params[] = $status;
}

$whereSql = '';

if ($conditions) {
    $whereSql =
        'WHERE ' .
        implode(' AND ', $conditions);
}

$categories = [];

$totalCategories = 0;
$activeCategories = 0;
$inactiveCategories = 0;
$totalSubjects = 0;

try {

    $statement = $conn->prepare("
        SELECT
            c.id,
            c.category_name,
            c.description,
            c.icon,
            c.status,
            c.created_at,

            (
                SELECT COUNT(*)
                FROM subjects s
                WHERE s.category_id = c.id
            ) AS subject_count

        FROM categories c

        $whereSql

        ORDER BY
            c.created_at DESC,
            c.id DESC
    ");

    $statement->execute($params);

    $categories =
        $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

    $statsStatement = $conn->query("
        SELECT
            COUNT(*) AS total_categories,

            COALESCE(
                SUM(status = 'Active'),
                0
            ) AS active_categories,

            COALESCE(
                SUM(status = 'Inactive'),
                0
            ) AS inactive_categories

        FROM categories
    ");

    $stats =
        $statsStatement->fetch(
            PDO::FETCH_ASSOC
        ) ?: [
            'total_categories' => 0,
            'active_categories' => 0,
            'inactive_categories' => 0
        ];

    $totalCategories =
        (int)$stats['total_categories'];

    $activeCategories =
        (int)$stats['active_categories'];

    $inactiveCategories =
        (int)$stats['inactive_categories'];

    foreach ($categories as $category) {
        $totalSubjects +=
            (int)$category['subject_count'];
    }

} catch (Throwable $exception) {

    error_log(
        'Category listing failed: ' .
        $exception->getMessage()
    );
}

function category_index_e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function category_index_date(?string $value): string
{
    if (!$value) {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d M Y', $timestamp)
        : '—';
}

function category_status_class(string $status): string
{
    return match ($status) {
        'Active' => 'active',
        'Inactive' => 'inactive',
        default => 'unknown'
    };
}

include "../includes/header.php";
?>

<style>

    .category-page {
        position: relative;
    }

    .category-page-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 22px;
        margin-bottom: 24px;
    }

    .category-page-heading .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        color: #556b2f;
        font-size: .76rem;
        font-weight: 800;
        letter-spacing: .12em;
    }

    .category-page-heading h1 {
        margin: 0;
        color: #333;
    }

    .category-page-heading p {
        margin: 7px 0 0;
        color: #756d68;
    }

    .category-add-btn {
        min-height: 43px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
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

    .category-add-btn:hover {
        background: #465b27;
        color: #fff;
        transform: translateY(-2px);
    }

    .category-stat-grid {
        display: grid;
        grid-template-columns: repeat(4,minmax(0,1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .category-stat {
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

    .category-stat-icon {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: rgba(85,107,47,.10);
        color: #556b2f;
        font-size: 1.1rem;
    }

    .category-stat small {
        display: block;
        margin-bottom: 4px;
        color: #786f69;
    }

    .category-stat strong {
        display: block;
        color: #333;
        font-size: 1.5rem;
        line-height: 1;
    }

    .category-list-card {
        overflow: hidden;
        border-radius: 24px;
        background: rgba(255,255,255,.89);
        border: 1px solid rgba(93,64,55,.10);
        box-shadow: 0 20px 55px rgba(51,51,51,.08);
        backdrop-filter: blur(15px);
    }

    .category-list-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        flex-wrap: wrap;
        padding: 21px 22px;
        border-bottom: 1px solid rgba(93,64,55,.08);
    }

    .category-list-header h2 {
        margin: 0;
        color: #333;
        font-size: 1.08rem;
    }

    .category-list-header p {
        margin: 5px 0 0;
        color: #77706b;
        font-size: .84rem;
    }

    .category-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .category-search {
        width: min(315px,100%);
    }

    .category-search .form-control,
    .category-search .btn {
        min-height: 40px;
    }

    .category-filter {
        min-height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 13px;
        border-radius: 11px;
        border: 1px solid rgba(93,64,55,.12);
        background: #fff;
        color: #655d58;
        font-size: .82rem;
        font-weight: 800;
        text-decoration: none;
        transition: .2s ease;
    }

    .category-filter:hover,
    .category-filter.active {
        background: #556b2f;
        border-color: #556b2f;
        color: #fff;
    }

    .category-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .category-table {
        width: 100%;
        min-width: 1000px;
        border-collapse: collapse;
    }

    .category-table th {
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

    .category-table td {
        padding: 16px 18px;
        vertical-align: middle;
        color: #373330;
        border-bottom: 1px solid rgba(93,64,55,.07);
    }

    .category-table tbody tr {
        transition: .2s ease;
    }

    .category-table tbody tr:hover {
        background: rgba(245,245,220,.45);
    }

    .category-icon {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 13px;
        background: rgba(93,64,55,.08);
        color: #5d4037;
        font-size: 1.05rem;
    }

    .category-name strong {
        display: block;
        color: #34312f;
    }

    .category-name small,
    .category-description small {
        display: block;
        max-width: 350px;
        margin-top: 4px;
        color: #77706b;
        line-height: 1.42;
    }

    .category-id {
        display: inline-flex;
        padding: 5px 9px;
        border-radius: 8px;
        background: rgba(93,64,55,.07);
        color: #5d4037;
        font-size: .77rem;
        font-weight: 800;
    }

    .category-count {
        display: inline-flex;
        min-width: 38px;
        justify-content: center;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(85,107,47,.09);
        color: #556b2f;
        font-size: .77rem;
        font-weight: 800;
    }

    .category-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 11px;
        border-radius: 999px;
        font-size: .75rem;
        font-weight: 800;
    }

    .category-status.active {
        background: rgba(85,107,47,.11);
        color: #556b2f;
    }

    .category-status.inactive {
        background: rgba(163,58,50,.10);
        color: #96352f;
    }

    .category-status.unknown {
        background: rgba(93,64,55,.08);
        color: #675f5a;
    }

    .category-actions {
        display: flex;
        gap: 7px;
    }

    .category-action {
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

    .category-action:hover {
        transform: translateY(-2px);
    }

    .category-action.edit {
        color: #5d4037;
    }

    .category-action.toggle {
        color: #806d27;
    }

    .category-action.delete {
        color: #a33a32;
    }

    .category-action.edit:hover {
        background: rgba(93,64,55,.08);
    }

    .category-action.toggle:hover {
        background: rgba(128,109,39,.08);
    }

    .category-action.delete:hover {
        background: rgba(163,58,50,.08);
    }

    .category-empty {
        padding: 58px 20px !important;
        text-align: center;
        color: #766e69 !important;
    }

    .category-empty i {
        display: block;
        margin-bottom: 12px;
        color: #556b2f;
        font-size: 2rem;
    }

    @media (max-width: 1100px) {

        .category-stat-grid {
            grid-template-columns: repeat(2,minmax(0,1fr));
        }

    }

    @media (max-width: 700px) {

        .category-page-heading {
            align-items: flex-start;
            flex-direction: column;
        }

        .category-add-btn {
            width: 100%;
        }

        .category-stat-grid {
            grid-template-columns: 1fr;
        }

        .category-toolbar {
            width: 100%;
        }

        .category-search {
            width: 100%;
        }

    }

</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content category-page">

            <section class="category-page-heading">

                <div>

                    <span class="eyebrow">
                        <i class="fa-solid fa-layer-group"></i>
                        ACADEMIC STRUCTURE
                    </span>

                    <h1>Category Management</h1>

                    <p>
                        Manage exam categories and their linked subjects.
                    </p>

                </div>

                <a
                    href="add.php"
                    class="category-add-btn"
                >
                    <i class="fa-solid fa-plus"></i>
                    Add Category
                </a>

            </section>

            <section class="category-stat-grid">

                <article class="category-stat">

                    <div class="category-stat-icon">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>

                    <div>
                        <small>Total Categories</small>
                        <strong>
                            <?= $totalCategories ?>
                        </strong>
                    </div>

                </article>

                <article class="category-stat">

                    <div class="category-stat-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div>
                        <small>Active Categories</small>
                        <strong>
                            <?= $activeCategories ?>
                        </strong>
                    </div>

                </article>

                <article class="category-stat">

                    <div class="category-stat-icon">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>

                    <div>
                        <small>Inactive Categories</small>
                        <strong>
                            <?= $inactiveCategories ?>
                        </strong>
                    </div>

                </article>

                <article class="category-stat">

                    <div class="category-stat-icon">
                        <i class="fa-solid fa-book-open"></i>
                    </div>

                    <div>
                        <small>Linked Subjects</small>
                        <strong>
                            <?= $totalSubjects ?>
                        </strong>
                    </div>

                </article>

            </section>

            <section class="category-list-card">

                <header class="category-list-header">

                    <div>

                        <h2>All Categories</h2>

                        <p>
                            <?= count($categories) ?>
                            record<?= count($categories) === 1 ? '' : 's' ?>
                            shown
                        </p>

                    </div>

                    <form
                        method="get"
                        class="category-toolbar"
                        autocomplete="off"
                    >

                        <div class="input-group category-search">

                            <input
                                type="search"
                                name="search"
                                value="<?= category_index_e($search) ?>"
                                class="form-control"
                                placeholder="Search category..."
                            >

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
                            class="category-filter <?= $status === ''
                                ? 'active'
                                : '' ?>"
                        >
                            All
                        </a>

                        <a
                            href="index.php?status=Active<?= $search !== ''
                                ? '&search=' . urlencode($search)
                                : '' ?>"
                            class="category-filter <?= $status === 'Active'
                                ? 'active'
                                : '' ?>"
                        >
                            Active
                        </a>

                        <a
                            href="index.php?status=Inactive<?= $search !== ''
                                ? '&search=' . urlencode($search)
                                : '' ?>"
                            class="category-filter <?= $status === 'Inactive'
                                ? 'active'
                                : '' ?>"
                        >
                            Inactive
                        </a>

                    </form>

                </header>

                <div class="category-table-wrap">

                    <table class="category-table">

                        <thead>

                            <tr>

                                <th>ID</th>

                                <th>Icon</th>

                                <th>Category</th>

                                <th>Description</th>

                                <th>Subjects</th>

                                <th>Status</th>

                                <th>Created</th>

                                <th>Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach (
                            $categories as $category
                        ): ?>

                            <tr>

                                <td data-label="ID">

                                    <span class="category-id">
                                        #<?= (int)$category['id'] ?>
                                    </span>

                                </td>

                                <td data-label="Icon">

                                    <?php
                                    $iconClass = trim(
                                        (string)($category['icon'] ?? '')
                                    );

                                    if ($iconClass === '') {
                                        $iconClass =
                                            'fa-solid fa-book';
                                    }
                                    ?>

                                    <span
                                        class="category-icon"
                                        title="<?= category_index_e(
                                            $iconClass
                                        ) ?>"
                                    >
                                        <i
                                            class="<?= category_index_e(
                                                $iconClass
                                            ) ?>"
                                        ></i>
                                    </span>

                                </td>

                                <td data-label="Category">

                                    <div class="category-name">

                                        <strong>
                                            <?= category_index_e(
                                                $category['category_name']
                                            ) ?>
                                        </strong>

                                        <small>
                                            Category ID:
                                            <?= (int)$category['id'] ?>
                                        </small>

                                    </div>

                                </td>

                                <td data-label="Description">

                                    <div class="category-description">

                                        <small>
                                            <?= category_index_e(
                                                $category['description']
                                                ?: 'No description added.'
                                            ) ?>
                                        </small>

                                    </div>

                                </td>

                                <td data-label="Subjects">

                                    <span class="category-count">
                                        <?= (int)$category['subject_count'] ?>
                                    </span>

                                </td>

                                <td data-label="Status">

                                    <span
                                        class="category-status <?= category_status_class(
                                            (string)$category['status']
                                        ) ?>"
                                    >

                                        <i class="fa-solid fa-circle"></i>

                                        <?= category_index_e(
                                            $category['status']
                                        ) ?>

                                    </span>

                                </td>

                                <td data-label="Created">

                                    <?= category_index_e(
                                        category_index_date(
                                            $category['created_at']
                                        )
                                    ) ?>

                                </td>

                                <td data-label="Actions">

                                    <div class="category-actions">

                                        <a
                                            href="edit.php?id=<?= (int)$category['id'] ?>"
                                            class="category-action edit"
                                            title="Edit Category"
                                        >
                                            <i class="fa-solid fa-pen"></i>
                                        </a>

                                        <a
                                            href="toggle-status.php?id=<?= (int)$category['id'] ?>"
                                            class="category-action toggle"
                                            title="Toggle Status"
                                        >
                                            <i
                                                class="fa-solid <?= $category['status'] === 'Active'
                                                    ? 'fa-toggle-on'
                                                    : 'fa-toggle-off' ?>"
                                            ></i>
                                        </a>

                                        <a
                                            href="delete.php?id=<?= (int)$category['id'] ?>"
                                            class="category-action delete"
                                            title="Delete Category"
                                            onclick="return confirm('Delete this category? Categories with linked subjects cannot be deleted.');"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (!$categories): ?>

                            <tr>

                                <td
                                    colspan="8"
                                    class="category-empty"
                                >

                                    <i class="fa-solid fa-layer-group"></i>

                                    <strong>
                                        No categories found.
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