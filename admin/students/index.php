<?php
declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header("Location: ../../auth/login.php");
    exit;
}

$page_title = "Student Management";
$page_css = "admin-dashboard.css";

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$allowedStatuses = ['Active', 'Inactive'];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = "(
        s.student_code LIKE ?
        OR s.full_name LIKE ?
        OR s.email LIKE ?
        OR s.mobile LIKE ?
        OR s.city LIKE ?
        OR s.state LIKE ?
    )";

    $searchValue = '%' . $search . '%';

    for ($i = 0; $i < 6; $i++) {
        $params[] = $searchValue;
    }
}

if ($status !== '') {
    $conditions[] = "s.status = ?";
    $params[] = $status;
}

$whereSql = '';

if ($conditions) {
    $whereSql = 'WHERE ' . implode(' AND ', $conditions);
}

try {

    $statsStmt = $conn->query("
        SELECT
            COUNT(*) AS total_students,
            COALESCE(SUM(status = 'Active'), 0) AS active_students,
            COALESCE(SUM(status = 'Inactive'), 0) AS inactive_students,
            COALESCE(SUM(email_verified = 'Yes'), 0) AS verified_students
        FROM students
    ");

    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_students' => 0,
        'active_students' => 0,
        'inactive_students' => 0,
        'verified_students' => 0
    ];

    $studentStmt = $conn->prepare("
        SELECT
            s.id,
            s.student_code,
            s.full_name,
            s.email,
            s.mobile,
            s.gender,
            s.dob,
            s.city,
            s.state,
            s.email_verified,
            s.status,
            s.last_login,
            s.created_at,

            (
                SELECT p.name
                FROM subscriptions sub
                INNER JOIN subscription_plans p
                    ON p.id = sub.plan_id
                WHERE sub.student_id = s.id
                  AND sub.status = 'Active'
                  AND sub.start_date <= CURDATE()
                  AND sub.end_date >= CURDATE()
                ORDER BY sub.end_date DESC, sub.id DESC
                LIMIT 1
            ) AS active_plan,

            (
                SELECT sub.end_date
                FROM subscriptions sub
                WHERE sub.student_id = s.id
                  AND sub.status = 'Active'
                  AND sub.start_date <= CURDATE()
                  AND sub.end_date >= CURDATE()
                ORDER BY sub.end_date DESC, sub.id DESC
                LIMIT 1
            ) AS active_plan_end

        FROM students s

        $whereSql

        ORDER BY s.created_at DESC, s.id DESC
    ");

    $studentStmt->execute($params);

    $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log($e->getMessage());

    $stats = [
        'total_students' => 0,
        'active_students' => 0,
        'inactive_students' => 0,
        'verified_students' => 0
    ];

    $students = [];
}

function student_e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function student_date(?string $value): string
{
    if (!$value) {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d M Y', $timestamp)
        : '—';
}

function student_datetime(?string $value): string
{
    if (!$value) {
        return 'Never';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d M Y, h:i A', $timestamp)
        : '—';
}

include "../includes/header.php";
?>

<style>

    .student-management-page {
        position: relative;
    }

    .student-management-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 25px;
        margin-bottom: 25px;
    }

    .student-management-heading .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #556b2f;
        font-size: .78rem;
        font-weight: 800;
        letter-spacing: .12em;
        margin-bottom: 8px;
    }

    .student-management-heading h1 {
        margin: 0;
    }

    .student-management-heading p {
        margin: 7px 0 0;
        color: #6d6762;
    }

    .student-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .student-stat-card {
        display: flex;
        align-items: center;
        gap: 15px;
        min-height: 105px;
        padding: 20px;
        border-radius: 20px;
        background: rgba(255,255,255,.82);
        border: 1px solid rgba(93,64,55,.10);
        box-shadow: 0 12px 35px rgba(51,51,51,.07);
        backdrop-filter: blur(14px);
    }

    .student-stat-card .icon {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: rgba(85,107,47,.10);
        color: #556b2f;
        font-size: 1.15rem;
    }

    .student-stat-card small {
        display: block;
        color: #756e69;
        margin-bottom: 4px;
    }

    .student-stat-card strong {
        display: block;
        color: #333;
        font-size: 1.55rem;
        line-height: 1;
    }

    .student-list-card {
        border-radius: 24px;
        background: rgba(255,255,255,.88);
        border: 1px solid rgba(93,64,55,.10);
        box-shadow: 0 20px 50px rgba(51,51,51,.08);
        overflow: hidden;
        backdrop-filter: blur(16px);
    }

    .student-list-header {
        padding: 22px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        flex-wrap: wrap;
        border-bottom: 1px solid rgba(93,64,55,.08);
    }

    .student-list-header h2 {
        margin: 0;
        font-size: 1.12rem;
        color: #333;
    }

    .student-list-header p {
        margin: 5px 0 0;
        color: #77706b;
        font-size: .88rem;
    }

    .student-toolbar {
        display: flex;
        align-items: center;
        gap: 9px;
        flex-wrap: wrap;
    }

    .student-search {
        width: min(310px, 100%);
    }

    .student-search .form-control,
    .student-search .btn {
        min-height: 40px;
    }

    .student-filter {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 0 13px;
        border-radius: 11px;
        text-decoration: none;
        border: 1px solid rgba(93,64,55,.12);
        background: #fff;
        color: #655c57;
        font-size: .84rem;
        font-weight: 700;
        transition: .2s ease;
    }

    .student-filter:hover,
    .student-filter.active {
        background: #556b2f;
        border-color: #556b2f;
        color: #fff;
    }

    .student-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .student-management-table {
        width: 100%;
        min-width: 1100px;
        border-collapse: collapse;
    }

    .student-management-table th {
        padding: 15px 18px;
        background: #faf7f0;
        color: #655c57;
        text-align: left;
        font-size: .76rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        white-space: nowrap;
        border-bottom: 1px solid rgba(93,64,55,.08);
    }

    .student-management-table td {
        padding: 17px 18px;
        vertical-align: middle;
        color: #383330;
        border-bottom: 1px solid rgba(93,64,55,.07);
    }

    .student-management-table tbody tr {
        transition: background .2s ease;
    }

    .student-management-table tbody tr:hover {
        background: rgba(245,245,220,.48);
    }

    .student-name {
        display: flex;
        align-items: center;
        gap: 11px;
        min-width: 205px;
    }

    .student-avatar {
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #5d4037;
        color: #fff;
        font-weight: 800;
        text-transform: uppercase;
    }

    .student-name strong {
        display: block;
    }

    .student-name small,
    .student-cell small {
        display: block;
        color: #77706b;
        margin-top: 3px;
        font-size: .79rem;
    }

    .student-code {
        display: inline-flex;
        padding: 5px 9px;
        border-radius: 8px;
        background: rgba(93,64,55,.07);
        color: #5d4037;
        font-size: .79rem;
        font-weight: 800;
    }

    .student-status,
    .student-verify,
    .student-plan {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: .76rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .student-status.active,
    .student-verify.verified,
    .student-plan.active {
        background: rgba(85,107,47,.11);
        color: #556b2f;
    }

    .student-status.inactive,
    .student-verify.pending {
        background: rgba(154,107,22,.11);
        color: #8a641a;
    }

    .student-actions {
        display: flex;
        gap: 7px;
    }

    .student-action {
        width: 35px;
        height: 35px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        border: 1px solid rgba(93,64,55,.10);
        background: #fff;
        transition: .2s ease;
    }

    .student-action:hover {
        transform: translateY(-2px);
    }

    .student-action.view {
        color: #556b2f;
    }

    .student-action.toggle {
        color: #5d4037;
    }

    .student-action.delete {
        color: #a33a32;
    }

    .student-action.view:hover {
        background: rgba(85,107,47,.09);
    }

    .student-action.toggle:hover {
        background: rgba(93,64,55,.08);
    }

    .student-action.delete:hover {
        background: rgba(163,58,50,.08);
    }

    .student-empty {
        text-align: center;
        padding: 60px 20px !important;
        color: #766e69 !important;
    }

    .student-empty i {
        display: block;
        margin-bottom: 12px;
        font-size: 2rem;
        color: #556b2f;
    }

    @media (max-width: 1100px) {
        .student-stat-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .student-stat-grid {
            grid-template-columns: 1fr;
        }

        .student-search {
            width: 100%;
        }

        .student-toolbar {
            width: 100%;
        }
    }

</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content student-management-page">

            <section class="student-management-heading">

                <div>

                    <span class="eyebrow">
                        <i class="fa-solid fa-users"></i>
                        USER MANAGEMENT
                    </span>

                    <h1>Student Management</h1>

                    <p>
                        Manage registered students, account status and
                        membership access.
                    </p>

                </div>

            </section>

            <section class="student-stat-grid">

                <article class="student-stat-card">

                    <div class="icon">
                        <i class="fa-solid fa-users"></i>
                    </div>

                    <div>
                        <small>Total Students</small>
                        <strong>
                            <?= (int)$stats['total_students'] ?>
                        </strong>
                    </div>

                </article>

                <article class="student-stat-card">

                    <div class="icon">
                        <i class="fa-solid fa-user-check"></i>
                    </div>

                    <div>
                        <small>Active Students</small>
                        <strong>
                            <?= (int)$stats['active_students'] ?>
                        </strong>
                    </div>

                </article>

                <article class="student-stat-card">

                    <div class="icon">
                        <i class="fa-solid fa-user-slash"></i>
                    </div>

                    <div>
                        <small>Inactive Students</small>
                        <strong>
                            <?= (int)$stats['inactive_students'] ?>
                        </strong>
                    </div>

                </article>

                <article class="student-stat-card">

                    <div class="icon">
                        <i class="fa-solid fa-envelope-circle-check"></i>
                    </div>

                    <div>
                        <small>Email Verified</small>
                        <strong>
                            <?= (int)$stats['verified_students'] ?>
                        </strong>
                    </div>

                </article>

            </section>

            <section class="student-list-card">

                <header class="student-list-header">

                    <div>
                        <h2>Registered Students</h2>

                        <p>
                            <?= count($students) ?>
                            record<?= count($students) === 1 ? '' : 's' ?>
                            shown
                        </p>
                    </div>

                    <form
                        method="get"
                        class="student-toolbar"
                        autocomplete="off"
                    >

                        <div class="input-group student-search">

                            <input
                                type="search"
                                name="search"
                                class="form-control"
                                value="<?= student_e($search) ?>"
                                placeholder="Search name, email, code..."
                            >

                            <button
                                type="submit"
                                class="btn btn-dark"
                            >
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </button>

                        </div>

                        <a
                            href="index.php<?= $search !== '' ? '?search=' . urlencode($search) : '' ?>"
                            class="student-filter <?= $status === '' ? 'active' : '' ?>"
                        >
                            All
                        </a>

                        <a
                            href="index.php?status=Active<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
                            class="student-filter <?= $status === 'Active' ? 'active' : '' ?>"
                        >
                            Active
                        </a>

                        <a
                            href="index.php?status=Inactive<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
                            class="student-filter <?= $status === 'Inactive' ? 'active' : '' ?>"
                        >
                            Inactive
                        </a>

                    </form>

                </header>

                <div class="student-table-wrap">

                    <table class="student-management-table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student</th>
                                <th>Contact</th>
                                <th>Membership</th>
                                <th>Verification</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($students as $student): ?>

                            <?php
                            $name = trim((string)$student['full_name']);
                            $initial = function_exists('mb_substr')
                                ? mb_substr($name, 0, 1, 'UTF-8')
                                : substr($name, 0, 1);
                            ?>

                            <tr>

                                <td data-label="ID">
                                    #<?= (int)$student['id'] ?>
                                </td>

                                <td data-label="Student">

                                    <div class="student-name">

                                        <div class="student-avatar">
                                            <?= student_e($initial) ?>
                                        </div>

                                        <div>
                                            <strong>
                                                <?= student_e($student['full_name']) ?>
                                            </strong>

                                            <small>
                                                <?= student_e(
                                                    $student['student_code'] ?? 'No student code'
                                                ) ?>
                                            </small>
                                        </div>

                                    </div>

                                </td>

                                <td data-label="Contact">

                                    <div class="student-cell">

                                        <strong>
                                            <?= student_e($student['email']) ?>
                                        </strong>

                                        <small>
                                            <?= student_e($student['mobile']) ?>
                                        </small>

                                        <?php if (!empty($student['city']) || !empty($student['state'])): ?>

                                            <small>
                                                <?= student_e(
                                                    trim(
                                                        implode(
                                                            ', ',
                                                            array_filter([
                                                                $student['city'] ?? '',
                                                                $student['state'] ?? ''
                                                            ])
                                                        )
                                                    )
                                                ) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>

                                </td>

                                <td data-label="Membership">

                                    <?php if (!empty($student['active_plan'])): ?>

                                        <span class="student-plan active">
                                            <i class="fa-solid fa-gem"></i>
                                            <?= student_e($student['active_plan']) ?>
                                        </span>

                                        <small>
                                            Until
                                            <?= student_e(
                                                student_date(
                                                    $student['active_plan_end'] ?? null
                                                )
                                            ) ?>
                                        </small>

                                    <?php else: ?>

                                        <span class="student-plan">
                                            <i class="fa-regular fa-circle"></i>
                                            No active plan
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td data-label="Verification">

                                    <?php if ($student['email_verified'] === 'Yes'): ?>

                                        <span class="student-verify verified">
                                            <i class="fa-solid fa-check"></i>
                                            Verified
                                        </span>

                                    <?php else: ?>

                                        <span class="student-verify pending">
                                            <i class="fa-regular fa-clock"></i>
                                            Pending
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td data-label="Status">

                                    <?php if ($student['status'] === 'Active'): ?>

                                        <span class="student-status active">
                                            <i class="fa-solid fa-circle"></i>
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="student-status inactive">
                                            <i class="fa-solid fa-circle"></i>
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                    <small>
                                        Last login:
                                        <?= student_datetime(
                                            $student['last_login'] ?? null
                                        ) ?>
                                    </small>

                                </td>

                                <td data-label="Registered">

                                    <?= student_e(
                                        student_date(
                                            $student['created_at'] ?? null
                                        )
                                    ) ?>

                                </td>

                                <td data-label="Actions">

                                    <div class="student-actions">

                                        <a
                                            href="view.php?id=<?= (int)$student['id'] ?>"
                                            class="student-action view"
                                            title="View student"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </a>

                                        <a
                                            href="toggle-status.php?id=<?= (int)$student['id'] ?>"
                                            class="student-action toggle"
                                            title="<?= $student['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>"
                                        >
                                            <i class="fa-solid <?= $student['status'] === 'Active'
                                                ? 'fa-toggle-on'
                                                : 'fa-toggle-off' ?>"></i>
                                        </a>

                                        <a
                                            href="delete.php?id=<?= (int)$student['id'] ?>"
                                            class="student-action delete"
                                            title="Delete student"
                                            onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.');"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (!$students): ?>

                            <tr>

                                <td
                                    colspan="8"
                                    class="student-empty"
                                >

                                    <i class="fa-solid fa-user-slash"></i>

                                    <strong>No students found.</strong>

                                    <div>
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