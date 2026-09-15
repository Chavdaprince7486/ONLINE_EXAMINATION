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

$page_title = 'Topic Management';

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$subjectId = filter_input(
    INPUT_GET,
    'subject_id',
    FILTER_VALIDATE_INT
);

if (
    $subjectId === false ||
    $subjectId === null ||
    $subjectId <= 0
) {
    $subjectId = 0;
}

if (
    !in_array(
        $status,
        ['Active', 'Inactive'],
        true
    )
) {
    $status = '';
}

$subjects = [];

try {

    $subjectStatement = $conn->query("
        SELECT
            id,
            name,
            status
        FROM subjects
        ORDER BY
            name ASC,
            id ASC
    ");

    $subjects = $subjectStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $exception) {

    error_log(
        'Topic subject list failed: ' .
        $exception->getMessage()
    );
}

$conditions = [];
$params = [];

if ($search !== '') {

    $conditions[] = "(
        t.name LIKE ?
        OR t.description LIKE ?
        OR s.name LIKE ?
    )";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($status !== '') {

    $conditions[] = "t.status = ?";

    $params[] = $status;
}

if ($subjectId > 0) {

    $conditions[] = "t.subject_id = ?";

    $params[] = $subjectId;
}

$whereSql = '';

if ($conditions) {

    $whereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $conditions
        );
}

$topics = [];

try {

    $statement = $conn->prepare("
        SELECT
            t.id,
            t.subject_id,
            t.name,
            t.description,
            t.status,
            t.created_at,
            t.updated_at,

            s.name AS subject_name,
            s.status AS subject_status,

            (
                SELECT COUNT(*)
                FROM questions q
                WHERE q.topic_id = t.id
            ) AS question_count

        FROM topics t

        INNER JOIN subjects s
            ON s.id = t.subject_id

        $whereSql

        ORDER BY
            t.created_at DESC,
            t.id DESC
    ");

    $statement->execute($params);

    $topics = $statement->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $exception) {

    error_log(
        'Topic list failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to load topics.';
}

$totalTopics = count($topics);
$activeTopics = 0;
$inactiveTopics = 0;
$totalQuestions = 0;

foreach ($topics as $topic) {

    if (
        (string)$topic['status'] === 'Active'
    ) {

        $activeTopics++;

    } else {

        $inactiveTopics++;
    }

    $totalQuestions +=
        (int)$topic['question_count'];
}

function topic_index_e(
    mixed $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

include "../includes/header.php";
?>

<style>

    .topic-page {
        position: relative;
    }

    .topic-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .topic-heading small {
        color: #556b2f;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .topic-heading h1 {
        margin: 7px 0 0;
        color: #5d4037;
        font-weight: 900;
    }

    .topic-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 46px;
        padding: 0 18px;
        border-radius: 13px;
        background: #5d4037;
        color: #fff;
        text-decoration: none;
        font-weight: 800;
        box-shadow: 0 12px 26px rgba(93,64,55,.18);
    }

    .topic-btn:hover {
        color: #fff;
        transform: translateY(-1px);
    }

    .topic-stat-grid {
        display: grid;
        grid-template-columns:
            repeat(
                4,
                minmax(0, 1fr)
            );
        gap: 16px;
        margin-bottom: 22px;
    }

    .topic-stat {
        padding: 20px;
        border-radius: 18px;
        background: rgba(255,255,255,.78);
        border: 1px solid rgba(93,64,55,.08);
        box-shadow: 0 14px 34px rgba(62,45,37,.08);
    }

    .topic-stat-label {
        color: #746d68;
        font-size: .82rem;
        font-weight: 700;
    }

    .topic-stat-value {
        margin-top: 7px;
        color: #5d4037;
        font-size: 1.7rem;
        font-weight: 900;
    }

    .topic-filter {
        padding: 18px;
        margin-bottom: 22px;
        border-radius: 18px;
        background: rgba(255,255,255,.78);
        border: 1px solid rgba(93,64,55,.08);
    }

    .topic-filter-grid {
        display: grid;
        grid-template-columns:
            2fr 1fr 1fr auto;
        gap: 12px;
        align-items: end;
    }

    .topic-filter label {
        display: block;
        margin-bottom: 6px;
        color: #5d4037;
        font-size: .82rem;
        font-weight: 800;
    }

    .topic-filter input,
    .topic-filter select {
        width: 100%;
        min-height: 44px;
        border: 1px solid #ddd3ca;
        border-radius: 11px;
        padding: 0 12px;
        background: #fff;
        outline: none;
    }

    .topic-filter button {
        min-height: 44px;
        padding: 0 16px;
        border: 0;
        border-radius: 11px;
        background: #556b2f;
        color: #fff;
        font-weight: 800;
    }

    .topic-table-wrap {
        overflow-x: auto;
        border-radius: 20px;
        background: rgba(255,255,255,.82);
        border: 1px solid rgba(93,64,55,.08);
        box-shadow: 0 18px 42px rgba(62,45,37,.08);
    }

    .topic-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 900px;
    }

    .topic-table th,
    .topic-table td {
        padding: 15px 16px;
        border-bottom: 1px solid #eee7df;
        text-align: left;
        vertical-align: middle;
    }

    .topic-table th {
        color: #5d4037;
        background: #faf7f0;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .topic-name {
        color: #5d4037;
        font-weight: 850;
    }

    .topic-description {
        max-width: 280px;
        color: #746d68;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .topic-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: .76rem;
        font-weight: 800;
    }

    .topic-badge.active {
        background: rgba(85,107,47,.12);
        color: #556b2f;
    }

    .topic-badge.inactive {
        background: rgba(168,50,50,.10);
        color: #a83232;
    }

    .topic-actions {
        display: flex;
        gap: 7px;
        flex-wrap: wrap;
    }

    .topic-action {
        width: 36px;
        height: 36px;
        display: inline-grid;
        place-items: center;
        border-radius: 10px;
        text-decoration: none;
        border: 1px solid #e6ddd5;
        background: #fff;
        color: #5d4037;
    }

    .topic-action:hover {
        color: #556b2f;
        border-color: #cdbfb4;
    }

    .topic-empty {
        padding: 55px 20px;
        text-align: center;
        color: #746d68;
    }

    @media (max-width: 1000px) {

        .topic-stat-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .topic-filter-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

    }

    @media (max-width: 600px) {

        .topic-stat-grid,
        .topic-filter-grid {
            grid-template-columns: 1fr;
        }

    }

</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content topic-page">

    <div class="topic-header">

        <div class="topic-heading">

            <small>
                Academic Structure
            </small>

            <h1>
                Topics
            </h1>

        </div>

        <a
            href="add.php"
            class="topic-btn"
        >
            <i class="fa-solid fa-plus"></i>
            Add Topic
        </a>

    </div>

    <?php if (!empty($_SESSION['success'])): ?>

        <div class="alert alert-success">
            <?= topic_index_e($_SESSION['success']) ?>
        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>

        <div class="alert alert-danger">
            <?= topic_index_e($_SESSION['error']) ?>
        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>

    <div class="topic-stat-grid">

        <div class="topic-stat">
            <div class="topic-stat-label">
                Total Topics
            </div>
            <div class="topic-stat-value">
                <?= $totalTopics ?>
            </div>
        </div>

        <div class="topic-stat">
            <div class="topic-stat-label">
                Active
            </div>
            <div class="topic-stat-value">
                <?= $activeTopics ?>
            </div>
        </div>

        <div class="topic-stat">
            <div class="topic-stat-label">
                Inactive
            </div>
            <div class="topic-stat-value">
                <?= $inactiveTopics ?>
            </div>
        </div>

        <div class="topic-stat">
            <div class="topic-stat-label">
                Linked Questions
            </div>
            <div class="topic-stat-value">
                <?= $totalQuestions ?>
            </div>
        </div>

    </div>

    <form
        method="get"
        class="topic-filter"
    >

        <div class="topic-filter-grid">

            <div>

                <label>
                    Search
                </label>

                <input
                    type="search"
                    name="search"
                    value="<?= topic_index_e($search) ?>"
                    placeholder="Search topic, description or subject..."
                >

            </div>

            <div>

                <label>
                    Subject
                </label>

                <select name="subject_id">

                    <option value="">
                        All Subjects
                    </option>

                    <?php foreach ($subjects as $subject): ?>

                        <option
                            value="<?= (int)$subject['id'] ?>"
                            <?= $subjectId === (int)$subject['id']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= topic_index_e($subject['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div>

                <label>
                    Status
                </label>

                <select name="status">

                    <option value="">
                        All Status
                    </option>

                    <option
                        value="Active"
                        <?= $status === 'Active'
                            ? 'selected'
                            : '' ?>
                    >
                        Active
                    </option>

                    <option
                        value="Inactive"
                        <?= $status === 'Inactive'
                            ? 'selected'
                            : '' ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>

            <div>

                <button type="submit">
                    <i class="fa-solid fa-filter"></i>
                    Filter
                </button>

            </div>

        </div>

    </form>

    <div class="topic-table-wrap">

        <?php if (!$topics): ?>

            <div class="topic-empty">

                <i
                    class="fa-solid fa-folder-open"
                    style="font-size:40px; margin-bottom:15px;"
                ></i>

                <h3>
                    No Topics Found
                </h3>

                <p>
                    Create your first topic to organize questions.
                </p>

            </div>

        <?php else: ?>

            <table class="topic-table">

                <thead>

                    <tr>

                        <th>
                            Topic
                        </th>

                        <th>
                            Subject
                        </th>

                        <th>
                            Questions
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Created
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($topics as $topic): ?>

                        <tr>

                            <td>

                                <div class="topic-name">
                                    <?= topic_index_e($topic['name']) ?>
                                </div>

                                <?php if (
                                    trim(
                                        (string)$topic['description']
                                    ) !== ''
                                ): ?>

                                    <div class="topic-description">

                                        <?= topic_index_e(
                                            $topic['description']
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= topic_index_e(
                                    $topic['subject_name']
                                ) ?>
                            </td>

                            <td>
                                <?= (int)$topic['question_count'] ?>
                            </td>

                            <td>

                                <span
                                    class="topic-badge <?= strtolower(
                                        (string)$topic['status']
                                    ) ?>"
                                >
                                    <?= topic_index_e(
                                        $topic['status']
                                    ) ?>
                                </span>

                            </td>

                            <td>
                                <?= topic_index_e(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            (string)$topic['created_at']
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>

                                <div class="topic-actions">

                                    <a
                                        class="topic-action"
                                        href="view.php?id=<?= (int)$topic['id'] ?>"
                                        title="View"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </a>

                                    <a
                                        class="topic-action"
                                        href="edit.php?id=<?= (int)$topic['id'] ?>"
                                        title="Edit"
                                    >
                                        <i class="fa-solid fa-pen"></i>
                                    </a>

                                    <a
                                        class="topic-action"
                                        href="toggle-status.php?id=<?= (int)$topic['id'] ?>"
                                        title="Toggle Status"
                                    >
                                        <i class="fa-solid fa-power-off"></i>
                                    </a>

                                    <a
                                        class="topic-action"
                                        href="delete.php?id=<?= (int)$topic['id'] ?>"
                                        title="Delete"
                                    >
                                        <i class="fa-solid fa-trash"></i>
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

        </main>

    </div>

</div>

<?php include "../includes/footer.php"; ?>