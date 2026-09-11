<?php
declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$status = trim((string)($_GET['status'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));

$allowedStatuses = ['Active', 'Expired', 'Cancelled'];
$conditions = [];
$params = [];

if (in_array($status, $allowedStatuses, true)) {
    $conditions[] = 's.status = ?';
    $params[] = $status;
} else {
    $status = '';
}

if ($search !== '') {
    $conditions[] = '(st.full_name LIKE ? OR st.email LIKE ? OR p.name LIKE ? OR EXISTS (
        SELECT 1
        FROM subscription_payments sp_search
        WHERE sp_search.subscription_id = s.id
          AND (
              sp_search.reference_no LIKE ?
              OR sp_search.gateway_order_id LIKE ?
              OR sp_search.gateway_payment_id LIKE ?
          )
    ))';

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

$whereSql = $conditions
    ? 'WHERE ' . implode(' AND ', $conditions)
    : '';

$query = "
    SELECT
        s.id,
        s.student_id,
        s.plan_id,
        s.start_date,
        s.end_date,
        s.status,
        s.created_at,
        st.full_name,
        st.email,
        p.name AS plan_name,
        p.duration_months,
        p.price AS plan_price,

        (
            SELECT sp.amount
            FROM subscription_payments sp
            WHERE sp.subscription_id = s.id
            ORDER BY sp.created_at DESC, sp.id DESC
            LIMIT 1
        ) AS payment_amount,

        (
            SELECT sp.payment_method
            FROM subscription_payments sp
            WHERE sp.subscription_id = s.id
            ORDER BY sp.created_at DESC, sp.id DESC
            LIMIT 1
        ) AS payment_method,

        (
            SELECT sp.reference_no
            FROM subscription_payments sp
            WHERE sp.subscription_id = s.id
            ORDER BY sp.created_at DESC, sp.id DESC
            LIMIT 1
        ) AS payment_reference,

        (
            SELECT sp.payment_status
            FROM subscription_payments sp
            WHERE sp.subscription_id = s.id
            ORDER BY sp.created_at DESC, sp.id DESC
            LIMIT 1
        ) AS payment_status,

        (
            SELECT sp.gateway_order_id
            FROM subscription_payments sp
            WHERE sp.subscription_id = s.id
            ORDER BY sp.created_at DESC, sp.id DESC
            LIMIT 1
        ) AS razorpay_order_id,

        (
            SELECT sp.gateway_payment_id
            FROM subscription_payments sp
            WHERE sp.subscription_id = s.id
            ORDER BY sp.created_at DESC, sp.id DESC
            LIMIT 1
        ) AS razorpay_payment_id,

        (
            SELECT sp.paid_at
            FROM subscription_payments sp
            WHERE sp.subscription_id = s.id
            ORDER BY sp.created_at DESC, sp.id DESC
            LIMIT 1
        ) AS paid_at

    FROM subscriptions s

    INNER JOIN students st
        ON st.id = s.student_id

    INNER JOIN subscription_plans p
        ON p.id = s.plan_id

    $whereSql

    ORDER BY s.created_at DESC, s.id DESC
";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countsStmt = $conn->query(
        "SELECT status, COUNT(*) AS total
         FROM subscriptions
         GROUP BY status"
    );

    $counts = $countsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $totalSubscriptions = 0;

    foreach ($counts as $count) {
        $totalSubscriptions += (int)$count;
    }

} catch (Throwable $e) {
    error_log($e->getMessage());

    $items = [];
    $counts = [];
    $totalSubscriptions = 0;
}

function sub_e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sub_date(?string $value): string
{
    if (!$value) {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d M Y', $timestamp)
        : '—';
}

function sub_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d M Y, h:i A', $timestamp)
        : '—';
}

function subscription_status_class(string $value): string
{
    return match ($value) {
        'Active' => 'active',
        'Expired' => 'expired',
        'Cancelled' => 'cancelled',
        default => 'inactive',
    };
}

function payment_status_class(?string $value): string
{
    return match ($value) {
        'Paid' => 'paid',
        'Pending' => 'pending',
        'Failed' => 'failed',
        'Cancelled' => 'cancelled',
        default => 'unknown',
    };
}

$page_title = 'Subscriptions | ExamSphere';
$page_css = 'admin-subjects.css';

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
                        <i class="fa-solid fa-gem"></i>
                        MEMBERSHIP MONITOR
                    </span>

                    <h1>Student Subscriptions</h1>

                    <p>
                        Monitor subscription purchases, access periods and
                        recorded payment details.
                    </p>
                </div>
            </div>

            <section class="subject-stat-row">

                <article>
                    <i class="fa-solid fa-layer-group"></i>

                    <span>
                        <small>All subscriptions</small>
                        <b><?= $totalSubscriptions ?></b>
                    </span>
                </article>

                <article>
                    <i class="fa-solid fa-circle-check"></i>

                    <span>
                        <small>Active access</small>
                        <b><?= (int)($counts['Active'] ?? 0) ?></b>
                    </span>
                </article>

                <article>
                    <i class="fa-solid fa-clock-rotate-left"></i>

                    <span>
                        <small>Expired access</small>
                        <b><?= (int)($counts['Expired'] ?? 0) ?></b>
                    </span>
                </article>

                <article>
                    <i class="fa-solid fa-ban"></i>

                    <span>
                        <small>Cancelled</small>
                        <b><?= (int)($counts['Cancelled'] ?? 0) ?></b>
                    </span>
                </article>

            </section>

            <section class="subject-list-card">

                <div class="subject-card-heading list-heading">

                    <div>
                        <span class="mini-kicker">
                            MEMBERSHIP PURCHASES
                        </span>

                        <h2>Subscription history</h2>
                    </div>

                    <form
                        method="get"
                        class="list-tools"
                        autocomplete="off"
                    >

                        <div class="input-group input-group-sm">

                            <input
                                type="search"
                                name="search"
                                value="<?= sub_e($search) ?>"
                                class="form-control"
                                placeholder="Student, plan or reference..."
                            >

                            <?php if ($status !== ''): ?>
                                <input
                                    type="hidden"
                                    name="status"
                                    value="<?= sub_e($status) ?>"
                                >
                            <?php endif; ?>

                            <button
                                class="btn btn-dark"
                                type="submit"
                                title="Search"
                            >
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </button>

                        </div>

                        <a
                            class="table-filter <?= $status === '' ? 'active' : '' ?>"
                            href="subscriptions.php<?= $search !== '' ? '?search=' . urlencode($search) : '' ?>"
                        >
                            All
                        </a>

                        <a
                            class="table-filter <?= $status === 'Active' ? 'active' : '' ?>"
                            href="subscriptions.php?status=Active<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
                        >
                            Active
                        </a>

                        <a
                            class="table-filter <?= $status === 'Expired' ? 'active' : '' ?>"
                            href="subscriptions.php?status=Expired<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
                        >
                            Expired
                        </a>

                        <a
                            class="table-filter <?= $status === 'Cancelled' ? 'active' : '' ?>"
                            href="subscriptions.php?status=Cancelled<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
                        >
                            Cancelled
                        </a>

                    </form>

                </div>

                <div class="subject-table-wrap">

                    <table class="subject-table">

                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Plan</th>
                                <th>Access period</th>
                                <th>Payment</th>
                                <th>Reference</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($items as $item): ?>

                            <tr>

                                <td data-label="Student">
                                    <b>
                                        <?= sub_e($item['full_name']) ?>
                                    </b>

                                    <small>
                                        <?= sub_e($item['email']) ?>
                                    </small>
                                </td>

                                <td data-label="Plan">

                                    <span class="code-pill">
                                        <?= sub_e($item['plan_name']) ?>
                                    </span>

                                    <small>
                                        <?= (int)$item['duration_months'] ?>
                                        months
                                        · ₹<?= number_format((float)$item['plan_price'], 2) ?>
                                        plan price
                                    </small>

                                </td>

                                <td data-label="Access period">

                                    <b>
                                        <?= sub_date($item['start_date']) ?>
                                    </b>

                                    <small>
                                        to
                                        <?= sub_date($item['end_date']) ?>
                                    </small>

                                </td>

                                <td data-label="Payment">

                                    <b>
                                        ₹<?= number_format(
                                            (float)($item['payment_amount'] ?? 0),
                                            2
                                        ) ?>
                                    </b>

                                    <small>

                                        <?= sub_e(
                                            $item['payment_method'] ?? '—'
                                        ) ?>

                                        ·

                                        <span
                                            class="payment-state <?= payment_status_class(
                                                $item['payment_status'] ?? null
                                            ) ?>"
                                        >
                                            <?= sub_e(
                                                $item['payment_status']
                                                ?? 'No payment'
                                            ) ?>
                                        </span>

                                    </small>

                                </td>

                                <td data-label="Reference">

                                    <small class="reference-text">
                                        <?= sub_e(
                                            $item['payment_reference']
                                            ?? '—'
                                        ) ?>
                                    </small>

                                    <?php if (!empty($item['razorpay_order_id'])): ?>
                                        <small>
                                            Razorpay order
                                            <?= sub_e($item['razorpay_order_id']) ?>
                                        </small>
                                    <?php endif; ?>

                                    <?php if (!empty($item['razorpay_payment_id'])): ?>
                                        <small>
                                            Razorpay payment
                                            <?= sub_e($item['razorpay_payment_id']) ?>
                                        </small>
                                    <?php endif; ?>

                                    <?php if (!empty($item['paid_at'])): ?>

                                        <small>
                                            Paid
                                            <?= sub_e(
                                                sub_datetime($item['paid_at'])
                                            ) ?>
                                        </small>

                                    <?php endif; ?>

                                </td>

                                <td data-label="Status">

                                    <span
                                        class="status-pill <?= sub_e(
                                            subscription_status_class(
                                                (string)$item['status']
                                            )
                                        ) ?>"
                                    >
                                        <?= sub_e($item['status']) ?>
                                    </span>

                                    <small>
                                        Created
                                        <?= sub_e(
                                            sub_datetime($item['created_at'])
                                        ) ?>
                                    </small>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (!$items): ?>

                            <tr class="subject-empty">

                                <td colspan="6">

                                    <i class="fa-solid fa-gem"></i>

                                    No subscription records found.

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

<style>

    .list-tools {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .list-tools .input-group {
        width: min(300px, 100%);
    }

    .list-tools .form-control {
        min-width: 0;
    }

    .table-filter.active {
        font-weight: 700;
    }

    .subject-table td small {
        display: block;
        margin-top: 4px;
    }

    .reference-text {
        max-width: 180px;
        word-break: break-word;
    }

    .payment-state {
        font-weight: 700;
    }

    .payment-state.paid {
        color: #556b2f;
    }

    .payment-state.pending {
        color: #9a6b16;
    }

    .payment-state.failed,
    .payment-state.cancelled {
        color: #a33a32;
    }

    .status-pill.expired {
        opacity: .82;
    }

    .status-pill.cancelled {
        background: rgba(163, 58, 50, .10);
        color: #8f302a;
    }

    @media (max-width: 900px) {

        .list-tools {
            width: 100%;
        }

        .list-tools .input-group {
            width: 100%;
        }

    }

</style>

<script src="assets/js/admin-shell.js"></script>

</body>
</html>