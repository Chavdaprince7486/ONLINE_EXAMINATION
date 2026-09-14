<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit;
}

$page_title = 'Subscription Management';

$search = trim(
    (string)($_GET['search'] ?? '')
);

$status = trim(
    (string)($_GET['status'] ?? '')
);

$allowedStatuses = [
    'Active',
    'Expired',
    'Cancelled'
];

if (
    $status !== '' &&
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {
    $status = '';
}

$conditions = [];
$params = [];


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if ($status !== '') {

    $conditions[] = 's.status = ?';

    $params[] = $status;
}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $conditions[] = '
        (
            st.full_name LIKE ?
            OR st.email LIKE ?
            OR p.name LIKE ?
        )
    ';

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


$whereSql = '';

if (!empty($conditions)) {

    $whereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $conditions
        );
}


/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/

$subscriptions = [];

$totalSubscriptions = 0;
$activeSubscriptions = 0;
$expiredSubscriptions = 0;
$cancelledSubscriptions = 0;
$totalPlans = 0;

try {

    /*
    |--------------------------------------------------------------------------
    | SUBSCRIPTIONS
    |--------------------------------------------------------------------------
    */

    $subscriptionStatement = $conn->prepare(
        "
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
            p.price AS plan_price

        FROM subscriptions s

        INNER JOIN students st
            ON st.id = s.student_id

        INNER JOIN subscription_plans p
            ON p.id = s.plan_id

        {$whereSql}

        ORDER BY
            s.created_at DESC,
            s.id DESC
        "
    );

    $subscriptionStatement->execute(
        $params
    );

    $subscriptions =
        $subscriptionStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | COUNTS
    |--------------------------------------------------------------------------
    */

    $totalSubscriptions = (int)$conn
        ->query(
            "
            SELECT COUNT(*)
            FROM subscriptions
            "
        )
        ->fetchColumn();


    $activeSubscriptions = (int)$conn
        ->query(
            "
            SELECT COUNT(*)
            FROM subscriptions
            WHERE status = 'Active'
            "
        )
        ->fetchColumn();


    $expiredSubscriptions = (int)$conn
        ->query(
            "
            SELECT COUNT(*)
            FROM subscriptions
            WHERE status = 'Expired'
            "
        )
        ->fetchColumn();


    $cancelledSubscriptions = (int)$conn
        ->query(
            "
            SELECT COUNT(*)
            FROM subscriptions
            WHERE status = 'Cancelled'
            "
        )
        ->fetchColumn();


    $totalPlans = (int)$conn
        ->query(
            "
            SELECT COUNT(*)
            FROM subscription_plans
            "
        )
        ->fetchColumn();

} catch (Throwable $exception) {

    error_log(
        'Subscription management page failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function subscription_management_escape(
    $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function subscription_management_date(
    $value
): string {

    if (
        $value === null ||
        $value === ''
    ) {
        return '—';
    }

    $timestamp = strtotime(
        (string)$value
    );

    if ($timestamp === false) {
        return '—';
    }

    return date(
        'd M Y',
        $timestamp
    );
}


function subscription_management_status_class(
    string $status
): string {

    switch ($status) {

        case 'Active':
            return 'active';

        case 'Expired':
            return 'expired';

        case 'Cancelled':
            return 'cancelled';

        default:
            return 'cancelled';
    }
}


/*
|--------------------------------------------------------------------------
| ADMIN SHELL
|--------------------------------------------------------------------------
*/

require_once 'includes/header.php';

?>

<style>

.subscription-management-page {

    --sm-brown: #5d4037;
    --sm-dark: #3e2723;
    --sm-olive: #556b2f;
    --sm-olive-dark: #465b27;
    --sm-muted: #766e69;
    --sm-border: rgba(93, 64, 55, .12);
    --sm-background: #f5f5dc;

    padding-bottom: 30px;

}


/*
|--------------------------------------------------------------------------
| PAGE HEADER
|--------------------------------------------------------------------------
*/

.subscription-management-page .sm-header {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 22px;

}


.subscription-management-page .sm-kicker {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    color: var(--sm-olive);

    font-size: 10px;

    font-weight: 900;

    letter-spacing: 1.5px;

    text-transform: uppercase;

}


.subscription-management-page .sm-header h1 {

    margin: 6px 0 5px;

    color: var(--sm-dark);

    font-size: 30px;

    font-weight: 900;

    line-height: 1.1;

}


.subscription-management-page .sm-header p {

    margin: 0;

    color: var(--sm-muted);

    font-size: 12px;

}


/*
|--------------------------------------------------------------------------
| ASSIGN BUTTON
|--------------------------------------------------------------------------
*/

.sm-assign-button {

    min-height: 42px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    padding: 0 15px;

    border-radius: 11px;

    background: var(--sm-olive);

    color: #fff;

    text-decoration: none;

    font-size: 10px;

    font-weight: 800;

    box-shadow:
        0 10px 23px
        rgba(85, 107, 47, .18);

    transition: .2s ease;

    white-space: nowrap;

}


.sm-assign-button:hover {

    background: var(--sm-olive-dark);

    color: #fff;

    transform: translateY(-1px);

}


/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

.sm-stat-grid {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 12px;

    margin-bottom: 17px;

}


.sm-stat-card {

    position: relative;

    overflow: hidden;

    padding: 16px;

    background:
        rgba(255, 255, 255, .9);

    border:
        1px solid
        var(--sm-border);

    border-radius: 16px;

    box-shadow:
        0 10px 28px
        rgba(62, 39, 35, .05);

}


.sm-stat-card::after {

    content: "";

    position: absolute;

    width: 85px;

    height: 85px;

    right: -42px;

    top: -42px;

    border-radius: 50%;

    background:
        rgba(85, 107, 47, .055);

}


.sm-stat-label {

    color: var(--sm-muted);

    font-size: 10px;

}


.sm-stat-value {

    margin-top: 5px;

    color: var(--sm-dark);

    font-size: 25px;

    font-weight: 900;

}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

.sm-filter {

    display: flex;

    align-items: center;

    gap: 8px;

    padding: 13px;

    margin-bottom: 16px;

    background: #fff;

    border:
        1px solid
        var(--sm-border);

    border-radius: 15px;

}


.sm-filter-input {

    flex: 1;

    min-width: 200px;

    height: 40px;

    border:
        1px solid
        rgba(93, 64, 55, .14);

    border-radius: 10px;

    padding: 0 12px;

    outline: none;

    color: var(--sm-dark);

    background: #fff;

    font-size: 11px;

}


.sm-filter-input:focus {

    border-color: var(--sm-olive);

    box-shadow:
        0 0 0 4px
        rgba(85, 107, 47, .07);

}


.sm-filter-select {

    height: 40px;

    min-width: 140px;

    border:
        1px solid
        rgba(93, 64, 55, .14);

    border-radius: 10px;

    padding: 0 11px;

    outline: none;

    background: #fff;

    color: var(--sm-dark);

    font-size: 11px;

}


.sm-filter-btn,
.sm-reset-btn {

    height: 40px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 6px;

    padding: 0 13px;

    border-radius: 10px;

    text-decoration: none;

    font-size: 10px;

    font-weight: 800;

}


.sm-filter-btn {

    border: 0;

    cursor: pointer;

    background: var(--sm-brown);

    color: #fff;

}


.sm-filter-btn:hover {

    background: var(--sm-dark);

}


.sm-reset-btn {

    background:
        rgba(93, 64, 55, .07);

    color: var(--sm-brown);

}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.sm-table-wrap {

    overflow-x: auto;

    background: #fff;

    border:
        1px solid
        var(--sm-border);

    border-radius: 16px;

    box-shadow:
        0 12px 30px
        rgba(62, 39, 35, .04);

}


.sm-table {

    width: 100%;

    min-width: 930px;

    border-collapse: collapse;

}


.sm-table th {

    padding: 12px 10px;

    background: #faf7f1;

    border-bottom:
        1px solid
        #ebe4da;

    color: #726860;

    font-size: 8px;

    font-weight: 900;

    letter-spacing: .6px;

    text-align: left;

    text-transform: uppercase;

}


.sm-table td {

    padding: 11px 10px;

    border-bottom:
        1px solid
        #f0ebe5;

    color: #403a36;

    font-size: 9px;

    vertical-align: middle;

}


.sm-table tbody tr {

    transition: background .18s ease;

}


.sm-table tbody tr:hover {

    background: #fdfbf7;

}


.sm-table tbody tr:last-child td {

    border-bottom: 0;

}


.sm-student-name {

    color: var(--sm-dark);

    font-weight: 800;

}


.sm-student-email {

    margin-top: 2px;

    color: var(--sm-muted);

    font-size: 8px;

}


.sm-plan-name {

    color: var(--sm-dark);

    font-weight: 800;

}


.sm-plan-duration {

    margin-top: 2px;

    color: var(--sm-muted);

    font-size: 8px;

}


.sm-validity {

    color: #5e5751;

    font-size: 8px;

}


/*
|--------------------------------------------------------------------------
| STATUS BADGE
|--------------------------------------------------------------------------
*/

.sm-status-badge {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 5px 8px;

    border-radius: 999px;

    font-size: 8px;

    font-weight: 900;

}


.sm-status-badge.active {

    color: #4e662c;

    background: #edf3e6;

}


.sm-status-badge.expired {

    color: #8a691e;

    background: #fff3d9;

}


.sm-status-badge.cancelled {

    color: #9a514a;

    background: #f8eae8;

}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.sm-actions {

    display: flex;

    align-items: center;

    gap: 5px;

}


.sm-action-button {

    width: 30px;

    height: 30px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border: 0;

    border-radius: 8px;

    background: #f6f2eb;

    color: var(--sm-brown);

    text-decoration: none;

    cursor: pointer;

    transition:
        background .2s ease,
        color .2s ease,
        transform .2s ease;

}


.sm-action-button:hover {

    background: var(--sm-brown);

    color: #fff;

    transform: translateY(-1px);

}


/*
|--------------------------------------------------------------------------
| TOGGLE
|--------------------------------------------------------------------------
*/

.sm-toggle-form {

    margin: 0;

    padding: 0;

}


.sm-toggle-wrapper {

    display: inline-flex;

    align-items: center;

    gap: 5px;

}


.sm-toggle {

    position: relative;

    display: inline-block;

    width: 42px;

    height: 24px;

}


.sm-toggle input {

    position: absolute;

    opacity: 0;

    width: 0;

    height: 0;

}


.sm-toggle-slider {

    position: absolute;

    inset: 0;

    border-radius: 999px;

    cursor: pointer;

    background: #d5d0c8;

    border:
        1px solid
        rgba(93, 64, 55, .14);

    transition:
        background .25s ease,
        box-shadow .25s ease;

}


.sm-toggle-slider::before {

    content: "";

    position: absolute;

    width: 18px;

    height: 18px;

    top: 2px;

    left: 2px;

    border-radius: 50%;

    background: #fff;

    box-shadow:
        0 2px 5px
        rgba(0, 0, 0, .17);

    transition:
        transform .25s ease;

}


.sm-toggle input:checked
+ .sm-toggle-slider {

    background:
        var(--sm-olive);

    border-color:
        var(--sm-olive);

    box-shadow:
        0 5px 14px
        rgba(85, 107, 47, .2);

}


.sm-toggle input:checked
+ .sm-toggle-slider::before {

    transform:
        translateX(18px);

}


.sm-toggle input:focus-visible
+ .sm-toggle-slider {

    box-shadow:
        0 0 0 4px
        rgba(85, 107, 47, .14);

}


.sm-toggle-text {

    min-width: 21px;

    font-size: 7px;

    font-weight: 900;

}


.sm-toggle-on {

    color:
        var(--sm-olive);

}


.sm-toggle-off {

    color:
        #9a514a;

}


/*
|--------------------------------------------------------------------------
| EXPIRED TOGGLE
|--------------------------------------------------------------------------
*/

.sm-toggle-expired {

    opacity: .55;

}


.sm-toggle-expired
.sm-toggle-slider {

    cursor: not-allowed;

}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.sm-empty {

    padding: 40px 20px;

    text-align: center;

    color: var(--sm-muted);

}


.sm-empty i {

    margin-bottom: 10px;

    color: #a39990;

}


.sm-empty-title {

    color: var(--sm-dark);

    font-size: 14px;

    font-weight: 900;

}


.sm-empty-text {

    margin-top: 4px;

    font-size: 10px;

}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 1050px) {

    .sm-stat-grid {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

    }

}


@media (max-width: 760px) {

    .subscription-management-page
    .sm-header {

        align-items: stretch;

        flex-direction: column;

    }


    .sm-assign-button {

        width: 100%;

    }


    .sm-filter {

        align-items: stretch;

        flex-direction: column;

    }


    .sm-filter-input,
    .sm-filter-select,
    .sm-filter-btn,
    .sm-reset-btn {

        width: 100%;

        min-width: 100%;

    }

}


@media (max-width: 520px) {

    .sm-stat-grid {

        grid-template-columns: 1fr;

    }

}

</style>


<div class="dashboard-wrapper">


    <!-- =========================================================
         SIDEBAR
         ========================================================= -->

    <?php include "includes/sidebar.php"; ?>


    <div class="main-content">


        <!-- =====================================================
             NAVBAR
             ===================================================== -->

        <?php include "includes/navbar.php"; ?>


        <main class="dashboard-content subscription-management-page">


            <!-- =================================================
                 HEADER
                 ================================================= -->

            <section class="sm-header">

                <div>

                    <div class="sm-kicker">

                        <i class="fa-solid fa-gem"></i>

                        ACCESS CONTROL

                    </div>


                    <h1>
                        Subscription Management
                    </h1>


                    <p>
                        Manage student memberships without any online payment gateway.
                    </p>

                </div>


                <a
                    href="subscriptions/add.php"
                    class="sm-assign-button"
                >

                    <i class="fa-solid fa-plus"></i>

                    Assign Subscription

                </a>

            </section>


            <!-- =================================================
                 STATS
                 ================================================= -->

            <section class="sm-stat-grid">


                <div class="sm-stat-card">

                    <div class="sm-stat-label">
                        Total subscriptions
                    </div>

                    <div class="sm-stat-value">
                        <?= $totalSubscriptions ?>
                    </div>

                </div>


                <div class="sm-stat-card">

                    <div class="sm-stat-label">
                        Active
                    </div>

                    <div class="sm-stat-value">
                        <?= $activeSubscriptions ?>
                    </div>

                </div>


                <div class="sm-stat-card">

                    <div class="sm-stat-label">
                        Expired
                    </div>

                    <div class="sm-stat-value">
                        <?= $expiredSubscriptions ?>
                    </div>

                </div>


                <div class="sm-stat-card">

                    <div class="sm-stat-label">
                        Subscription plans
                    </div>

                    <div class="sm-stat-value">
                        <?= $totalPlans ?>
                    </div>

                </div>


            </section>


            <!-- =================================================
                 FILTER
                 ================================================= -->

            <form
                method="get"
                class="sm-filter"
            >

                <input
                    type="search"
                    name="search"
                    class="sm-filter-input"
                    value="<?= subscription_management_escape($search) ?>"
                    placeholder="Search student, email or plan..."
                >


                <select
                    name="status"
                    class="sm-filter-select"
                >

                    <option value="">
                        All statuses
                    </option>

                    <?php foreach ($allowedStatuses as $item): ?>

                        <option
                            value="<?= subscription_management_escape($item) ?>"
                            <?= $status === $item
                                ? 'selected'
                                : '' ?>
                        >

                            <?= subscription_management_escape($item) ?>

                        </option>

                    <?php endforeach; ?>

                </select>


                <button
                    type="submit"
                    class="sm-filter-btn sm-filter-btn"
                >

                    <i class="fa-solid fa-magnifying-glass"></i>

                    Search

                </button>


                <a
                    href="subscriptions.php"
                    class="sm-reset-btn"
                >

                    Reset

                </a>

            </form>


            <!-- =================================================
                 TABLE
                 ================================================= -->

            <section class="sm-table-wrap">

                <table class="sm-table">

                    <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>
                                Plan
                            </th>

                            <th>
                                Validity
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


                        <?php if (empty($subscriptions)): ?>


                            <tr>

                                <td colspan="6">

                                    <div class="sm-empty">

                                        <i
                                            class="
                                                fa-regular
                                                fa-folder-open
                                                fa-2x
                                            "
                                        ></i>


                                        <div class="sm-empty-title">
                                            No subscription records found
                                        </div>


                                        <div class="sm-empty-text">
                                            Try changing your search or filter.
                                        </div>

                                    </div>

                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach ($subscriptions as $item): ?>

                                <?php

                                $itemStatus =
                                    (string)$item['status'];

                                $isActive =
                                    $itemStatus === 'Active';

                                $isExpired =
                                    $itemStatus === 'Expired';

                                ?>

                                <tr>


                                    <!-- STUDENT -->

                                    <td>

                                        <div
                                            class="sm-student-name"
                                        >

                                            <?= subscription_management_escape(
                                                $item['full_name']
                                            ) ?>

                                        </div>


                                        <div
                                            class="sm-student-email"
                                        >

                                            <?= subscription_management_escape(
                                                $item['email']
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- PLAN -->

                                    <td>

                                        <div
                                            class="sm-plan-name"
                                        >

                                            <?= subscription_management_escape(
                                                $item['plan_name']
                                            ) ?>

                                        </div>


                                        <div
                                            class="sm-plan-duration"
                                        >

                                            <?= (int)$item['duration_months'] ?>

                                            month(s)

                                        </div>

                                    </td>


                                    <!-- VALIDITY -->

                                    <td>

                                        <div class="sm-validity">

                                            <?= subscription_management_date(
                                                $item['start_date']
                                            ) ?>

                                            →

                                            <?= subscription_management_date(
                                                $item['end_date']
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span
                                            class="
                                                sm-status-badge
                                                <?= subscription_management_status_class(
                                                    $itemStatus
                                                ) ?>
                                            "
                                        >

                                            <i
                                                class="
                                                    fa-solid
                                                    <?= $isActive
                                                        ? 'fa-circle-check'
                                                        : ($isExpired
                                                            ? 'fa-clock'
                                                            : 'fa-ban') ?>
                                                "
                                            ></i>


                                            <?= subscription_management_escape(
                                                $itemStatus
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- CREATED -->

                                    <td>

                                        <?= subscription_management_date(
                                            $item['created_at']
                                        ) ?>

                                    </td>


                                    <!-- ACTIONS -->

                                    <td>

                                        <div class="sm-actions">


                                            <!-- VIEW -->

                                            <a
                                                href="subscriptions/view.php?id=<?= (int)$item['id'] ?>"
                                                class="sm-action-button"
                                                title="View Subscription"
                                            >

                                                <i
                                                    class="
                                                        fa-regular
                                                        fa-eye
                                                    "
                                                ></i>

                                            </a>


                                            <!-- EDIT -->

                                            <a
                                                href="subscriptions/edit.php?id=<?= (int)$item['id'] ?>"
                                                class="sm-action-button"
                                                title="Edit Subscription"
                                            >

                                                <i
                                                    class="
                                                        fa-solid
                                                        fa-pen
                                                    "
                                                ></i>

                                            </a>


                                            <!-- TOGGLE -->

                                            <?php if ($isExpired): ?>


                                                <div
                                                    class="
                                                        sm-toggle-wrapper
                                                        sm-toggle-expired
                                                    "
                                                    title="Expired subscriptions cannot be toggled."
                                                >

                                                    <label
                                                        class="sm-toggle"
                                                    >

                                                        <input
                                                            type="checkbox"
                                                            disabled
                                                        >

                                                        <span
                                                            class="sm-toggle-slider"
                                                        ></span>

                                                    </label>


                                                    <span
                                                        class="
                                                            sm-toggle-text
                                                            sm-toggle-off
                                                        "
                                                    >
                                                        OFF
                                                    </span>

                                                </div>


                                            <?php else: ?>


                                                <form
                                                    method="post"
                                                    action="subscriptions/status.php"
                                                    class="sm-toggle-form"
                                                    onsubmit="
                                                        return confirm(
                                                            <?= json_encode(
                                                                $isActive
                                                                    ? 'Deactivate this subscription?'
                                                                    : 'Activate this subscription?'
                                                            ) ?>
                                                        );
                                                    "
                                                >

                                                    <?= csrf_field() ?>


                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)$item['id'] ?>"
                                                    >


                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="toggle"
                                                    >


                                                    <div
                                                        class="
                                                            sm-toggle-wrapper
                                                        "
                                                    >


                                                        <label
                                                            class="sm-toggle"
                                                            title="
                                                                <?= $isActive
                                                                    ? 'Click to deactivate'
                                                                    : 'Click to activate' ?>
                                                            "
                                                        >

                                                            <input
                                                                type="checkbox"
                                                                <?= $isActive
                                                                    ? 'checked'
                                                                    : '' ?>
                                                                onchange="
                                                                    this.form.submit();
                                                                "
                                                                aria-label="
                                                                    <?= $isActive
                                                                        ? 'Deactivate subscription'
                                                                        : 'Activate subscription' ?>
                                                                "
                                                            >


                                                            <span
                                                                class="sm-toggle-slider"
                                                            ></span>

                                                        </label>


                                                        <span
                                                            class="
                                                                sm-toggle-text
                                                                <?= $isActive
                                                                    ? 'sm-toggle-on'
                                                                    : 'sm-toggle-off' ?>
                                                            "
                                                        >

                                                            <?= $isActive
                                                                ? 'ON'
                                                                : 'OFF' ?>

                                                        </span>

                                                    </div>

                                                </form>


                                            <?php endif; ?>


                                            <!-- DELETE -->

                                            <a
                                                href="subscriptions/delete.php?id=<?= (int)$item['id'] ?>"
                                                class="sm-action-button"
                                                title="Delete Subscription"
                                                onclick="
                                                    return confirm(
                                                        'Are you sure you want to delete this subscription?'
                                                    );
                                                "
                                            >

                                                <i
                                                    class="
                                                        fa-solid
                                                        fa-trash
                                                    "
                                                ></i>

                                            </a>


                                        </div>

                                    </td>


                                </tr>

                            <?php endforeach; ?>


                        <?php endif; ?>


                    </tbody>

                </table>

            </section>


        </main>

    </div>

</div>


<?php include 'includes/footer.php'; ?>