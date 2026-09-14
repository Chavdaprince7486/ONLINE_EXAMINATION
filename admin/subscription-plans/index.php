<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";
require_once "../../config/functions.php";

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: ../../auth/login.php');
    exit;
}

$page_title = 'Subscription Plans';

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$allowedStatuses = [
    'Active',
    'Inactive'
];

$where = [];
$params = [];

if ($search !== '') {

    $where[] = '(name LIKE ? OR description LIKE ? OR benefits LIKE ?)';

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if (
    $status !== '' &&
    in_array($status, $allowedStatuses, true)
) {
    $where[] = 'status = ?';
    $params[] = $status;
}

$sql = "
    SELECT
        id,
        name,
        duration_months,
        price,
        description,
        benefits,
        status,
        created_at
    FROM subscription_plans
";

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= "
    ORDER BY
        status = 'Active' DESC,
        duration_months ASC,
        id DESC
";

try {

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $plans = $stmt->fetchAll();

    $totalPlansStmt = $conn->query(
        "SELECT COUNT(*) FROM subscription_plans"
    );

    $totalPlans = (int)$totalPlansStmt->fetchColumn();

    $activePlansStmt = $conn->query(
        "SELECT COUNT(*) FROM subscription_plans WHERE status = 'Active'"
    );

    $activePlans = (int)$activePlansStmt->fetchColumn();

    $inactivePlansStmt = $conn->query(
        "SELECT COUNT(*) FROM subscription_plans WHERE status = 'Inactive'"
    );

    $inactivePlans = (int)$inactivePlansStmt->fetchColumn();

} catch (Throwable $exception) {

    error_log(
        'Subscription plans listing failed: ' .
        $exception->getMessage()
    );

    $plans = [];
    $totalPlans = 0;
    $activePlans = 0;
    $inactivePlans = 0;
}

function subscription_plan_escape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function subscription_plan_benefits(string $benefits): array
{
    $benefits = str_replace(
        ["\r\n", "\r"],
        "\n",
        trim($benefits)
    );

    if ($benefits === '') {
        return [];
    }

    $lines = explode("\n", $benefits);

    $result = [];

    foreach ($lines as $line) {

        $line = trim($line);

        if ($line === '') {
            continue;
        }

        $firstCharacter = substr($line, 0, 1);

        if (
            $firstCharacter === '-' ||
            $firstCharacter === '*' ||
            $firstCharacter === '•'
        ) {
            $line = trim(substr($line, 1));
        }

        if ($line !== '') {
            $result[] = $line;
        }
    }

    return $result;
}

require_once "../includes/header.php";
?>

<style>

:root {
    --sp-bg: #f5f5dc;
    --sp-brown: #5d4037;
    --sp-dark: #3e2723;
    --sp-olive: #556b2f;
    --sp-muted: #766e69;
    --sp-white: #ffffff;
    --sp-border: rgba(93, 64, 55, 0.12);
}

.subscription-plans-page {
    min-height: calc(100vh - 70px);
    padding: 28px;
    background:
        radial-gradient(
            circle at top right,
            rgba(85, 107, 47, 0.08),
            transparent 28%
        ),
        linear-gradient(
            135deg,
            #f5f5dc 0%,
            #fbfaf3 55%,
            #f1ebdf 100%
        );
}

.sp-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 25px;
}

.sp-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--sp-olive);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 1.6px;
    text-transform: uppercase;
}

.sp-heading h1 {
    margin: 7px 0 5px;
    color: var(--sp-dark);
    font-size: 36px;
    line-height: 1.1;
    font-weight: 900;
}

.sp-heading p {
    margin: 0;
    color: var(--sp-muted);
    font-size: 13px;
}

.sp-add-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 45px;
    padding: 0 17px;
    border-radius: 12px;
    background: var(--sp-olive);
    color: #ffffff;
    text-decoration: none;
    font-size: 12px;
    font-weight: 800;
    box-shadow: 0 12px 25px rgba(85, 107, 47, 0.18);
    transition: 0.25s ease;
    white-space: nowrap;
}

.sp-add-btn:hover {
    color: #ffffff;
    background: #465b27;
    transform: translateY(-2px);
}

.sp-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.sp-stat-card {
    padding: 18px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid var(--sp-border);
    border-radius: 18px;
    box-shadow: 0 14px 35px rgba(62, 39, 35, 0.06);
}

.sp-stat-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    border-radius: 12px;
    background: rgba(85, 107, 47, 0.10);
    color: var(--sp-olive);
}

.sp-stat-label {
    color: var(--sp-muted);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.7px;
    text-transform: uppercase;
}

.sp-stat-value {
    margin-top: 3px;
    color: var(--sp-dark);
    font-size: 27px;
    font-weight: 900;
}

.sp-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 10px;
    padding: 15px;
    margin-bottom: 20px;
    background: rgba(255, 255, 255, 0.90);
    border: 1px solid var(--sp-border);
    border-radius: 17px;
    box-shadow: 0 12px 30px rgba(62, 39, 35, 0.04);
}

.sp-field {
    flex: 1;
    min-width: 220px;
}

.sp-field.status-field {
    flex: 0 0 170px;
    min-width: 170px;
}

.sp-field label {
    display: block;
    margin-bottom: 6px;
    color: var(--sp-dark);
    font-size: 10px;
    font-weight: 800;
}

.sp-field input,
.sp-field select {
    width: 100%;
    height: 42px;
    border: 1px solid rgba(93, 64, 55, 0.15);
    border-radius: 10px;
    background: #ffffff;
    padding: 0 11px;
    outline: none;
    color: #333333;
    font-size: 12px;
}

.sp-field input:focus,
.sp-field select:focus {
    border-color: var(--sp-olive);
    box-shadow: 0 0 0 4px rgba(85, 107, 47, 0.08);
}

.sp-filter-btn,
.sp-reset-btn {
    height: 42px;
    padding: 0 14px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    font-size: 11px;
    font-weight: 800;
    text-decoration: none;
}

.sp-filter-btn {
    border: 0;
    background: var(--sp-brown);
    color: #ffffff;
    cursor: pointer;
}

.sp-filter-btn:hover {
    background: var(--sp-dark);
}

.sp-reset-btn {
    background: rgba(93, 64, 55, 0.07);
    color: var(--sp-brown);
}

.sp-reset-btn:hover {
    background: rgba(93, 64, 55, 0.13);
    color: var(--sp-dark);
}

.sp-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.sp-card {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.93);
    border: 1px solid var(--sp-border);
    border-radius: 22px;
    box-shadow: 0 16px 38px rgba(62, 39, 35, 0.07);
    transition: transform 0.28s ease, box-shadow 0.28s ease;
}

.sp-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 24px 48px rgba(62, 39, 35, 0.11);
}

.sp-card-top {
    padding: 20px 20px 9px;
}

.sp-top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 11px;
}

.sp-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 9px;
    border-radius: 999px;
    font-size: 9px;
    font-weight: 900;
}

.sp-status.active {
    color: var(--sp-olive);
    background: rgba(85, 107, 47, 0.10);
}

.sp-status.inactive {
    color: #8d6259;
    background: rgba(141, 98, 89, 0.08);
}

.sp-plan-id {
    color: #a0958f;
    font-size: 9px;
    font-weight: 800;
}

.sp-card-title {
    margin: 0;
    color: var(--sp-dark);
    font-size: 22px;
    font-weight: 900;
}

.sp-duration {
    margin-top: 4px;
    color: var(--sp-muted);
    font-size: 11px;
}

.sp-price-box {
    padding: 7px 20px 17px;
}

.sp-price {
    color: var(--sp-dark);
    font-size: 32px;
    line-height: 1;
    font-weight: 900;
}

.sp-price-label {
    color: var(--sp-muted);
    font-size: 11px;
    font-weight: 700;
}

.sp-description {
    min-height: 82px;
    padding: 16px 20px;
    border-top: 1px solid #eee9e1;
    border-bottom: 1px solid #eee9e1;
    color: var(--sp-muted);
    font-size: 12px;
    line-height: 1.65;
}

.sp-benefits {
    flex: 1;
    padding: 17px 20px 8px;
}

.sp-benefits-title {
    margin-bottom: 10px;
    color: var(--sp-dark);
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.7px;
    text-transform: uppercase;
}

.sp-benefits ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sp-benefits li {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 8px;
    color: #403a36;
    font-size: 11px;
    line-height: 1.5;
}

.sp-benefits li i {
    margin-top: 3px;
    color: var(--sp-olive);
}

.sp-no-benefits {
    color: #8b817b;
    font-size: 11px;
}

.sp-actions {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 7px;
    padding: 17px 20px 20px;
}

.sp-action {
    min-height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    border-radius: 9px;
    text-decoration: none;
    font-size: 9px;
    font-weight: 800;
    transition: 0.2s ease;
}

.sp-view {
    background: rgba(93, 64, 55, 0.08);
    color: var(--sp-brown);
}

.sp-edit {
    background: rgba(85, 107, 47, 0.10);
    color: var(--sp-olive);
}

.sp-status-action {
    background: #f3f0ea;
    color: var(--sp-brown);
}

.sp-delete {
    background: rgba(171, 56, 48, 0.08);
    color: #9f4039;
}

.sp-view:hover,
.sp-edit:hover,
.sp-status-action:hover {
    background: var(--sp-brown);
    color: #ffffff;
}

.sp-delete:hover {
    background: #9f4039;
    color: #ffffff;
}

.sp-empty {
    grid-column: 1 / -1;
    padding: 55px 20px;
    text-align: center;
    background: rgba(255, 255, 255, 0.75);
    border: 1px dashed rgba(93, 64, 55, 0.20);
    border-radius: 20px;
    color: var(--sp-muted);
}

.sp-empty-icon {
    margin-bottom: 14px;
    color: var(--sp-olive);
    font-size: 35px;
}

.sp-empty h3 {
    margin: 0 0 7px;
    color: var(--sp-dark);
    font-size: 19px;
    font-weight: 900;
}

.sp-empty p {
    margin: 0 0 17px;
    font-size: 12px;
}

@media (max-width: 1100px) {

    .sp-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

}

@media (max-width: 760px) {

    .subscription-plans-page {
        padding: 18px;
    }

    .sp-heading {
        align-items: stretch;
        flex-direction: column;
    }

    .sp-add-btn {
        width: 100%;
    }

    .sp-stats {
        grid-template-columns: 1fr;
    }

    .sp-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .sp-field,
    .sp-field.status-field {
        min-width: 100%;
        flex: 1 1 auto;
    }

    .sp-filter-btn,
    .sp-reset-btn {
        width: 100%;
    }

    .sp-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 450px) {

    .sp-actions {
        grid-template-columns: repeat(2, 1fr);
    }

}

</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content subscription-plans-page">

            <section class="sp-heading">

                <div>

                    <div class="sp-kicker">
                        <i class="fa-solid fa-gem"></i>
                        SUBSCRIPTION MANAGEMENT
                    </div>

                    <h1>
                        Subscription Plans
                    </h1>

                    <p>
                        Create and manage the subscription plans
                        available for ExamSphere students.
                    </p>

                </div>

                <a
                    href="add.php"
                    class="sp-add-btn"
                >
                    <i class="fa-solid fa-plus"></i>
                    Add New Plan
                </a>

            </section>


            <section class="sp-stats">

                <div class="sp-stat-card">

                    <div class="sp-stat-icon">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>

                    <div class="sp-stat-label">
                        Total Plans
                    </div>

                    <div class="sp-stat-value">
                        <?= $totalPlans ?>
                    </div>

                </div>


                <div class="sp-stat-card">

                    <div class="sp-stat-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div class="sp-stat-label">
                        Active Plans
                    </div>

                    <div class="sp-stat-value">
                        <?= $activePlans ?>
                    </div>

                </div>


                <div class="sp-stat-card">

                    <div class="sp-stat-icon">
                        <i class="fa-solid fa-circle-pause"></i>
                    </div>

                    <div class="sp-stat-label">
                        Inactive Plans
                    </div>

                    <div class="sp-stat-value">
                        <?= $inactivePlans ?>
                    </div>

                </div>

            </section>


            <form
                method="get"
                class="sp-toolbar"
            >

                <div class="sp-field">

                    <label for="search">
                        Search Plans
                    </label>

                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="<?= subscription_plan_escape($search) ?>"
                        placeholder="Search plan name, description..."
                    >

                </div>


                <div class="sp-field status-field">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option value="">
                            All Status
                        </option>

                        <option
                            value="Active"
                            <?= $status === 'Active' ? 'selected' : '' ?>
                        >
                            Active
                        </option>

                        <option
                            value="Inactive"
                            <?= $status === 'Inactive' ? 'selected' : '' ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    class="sp-filter-btn"
                >
                    <i class="fa-solid fa-filter"></i>
                    Filter
                </button>


                <a
                    href="index.php"
                    class="sp-reset-btn"
                >
                    <i class="fa-solid fa-rotate-left"></i>
                    Reset
                </a>

            </form>


            <section class="sp-grid">

                <?php if (empty($plans)): ?>

                    <div class="sp-empty">

                        <div class="sp-empty-icon">
                            <i class="fa-solid fa-gem"></i>
                        </div>

                        <h3>
                            No Subscription Plans Found
                        </h3>

                        <p>
                            Create your first subscription plan
                            to start managing student subscriptions.
                        </p>

                        <a
                            href="add.php"
                            class="sp-add-btn"
                        >
                            <i class="fa-solid fa-plus"></i>
                            Create First Plan
                        </a>

                    </div>

                <?php else: ?>

                    <?php foreach ($plans as $plan): ?>

                        <?php

                        $benefits = subscription_plan_benefits(
                            (string)($plan['benefits'] ?? '')
                        );

                        ?>

                        <article class="sp-card">


                            <div class="sp-card-top">

                                <div class="sp-top-row">

                                    <span
                                        class="
                                            sp-status
                                            <?= $plan['status'] === 'Active'
                                                ? 'active'
                                                : 'inactive' ?>
                                        "
                                    >

                                        <i
                                            class="
                                                fa-solid
                                                <?= $plan['status'] === 'Active'
                                                    ? 'fa-circle-check'
                                                    : 'fa-circle-pause' ?>
                                            "
                                        ></i>

                                        <?= subscription_plan_escape(
                                            $plan['status']
                                        ) ?>

                                    </span>


                                    <span class="sp-plan-id">

                                        PLAN #<?= (int)$plan['id'] ?>

                                    </span>

                                </div>


                                <h2 class="sp-card-title">

                                    <?= subscription_plan_escape(
                                        $plan['name']
                                    ) ?>

                                </h2>


                                <div class="sp-duration">

                                    <i class="fa-regular fa-clock"></i>

                                    <?= (int)$plan['duration_months'] ?>

                                    month<?= (int)$plan['duration_months'] === 1
                                        ? ''
                                        : 's' ?>

                                </div>

                            </div>


                            <div class="sp-price-box">

                                <span class="sp-price">

                                    ₹<?= number_format(
                                        (float)$plan['price'],
                                        2
                                    ) ?>

                                </span>

                                <span class="sp-price-label">
                                    / plan
                                </span>

                            </div>


                            <div class="sp-description">

                                <?= nl2br(
                                    subscription_plan_escape(
                                        $plan['description']
                                        ?: 'No description added.'
                                    )
                                ) ?>

                            </div>


                            <div class="sp-benefits">

                                <div class="sp-benefits-title">
                                    Plan Benefits
                                </div>


                                <?php if (!empty($benefits)): ?>

                                    <ul>

                                        <?php foreach ($benefits as $benefit): ?>

                                            <li>

                                                <i class="fa-solid fa-check"></i>

                                                <span>
                                                    <?= subscription_plan_escape(
                                                        $benefit
                                                    ) ?>
                                                </span>

                                            </li>

                                        <?php endforeach; ?>

                                    </ul>

                                <?php else: ?>

                                    <div class="sp-no-benefits">
                                        No benefits added.
                                    </div>

                                <?php endif; ?>

                            </div>


                            <div class="sp-actions">

                                <a
                                    href="view.php?id=<?= (int)$plan['id'] ?>"
                                    class="sp-action sp-view"
                                    title="View Plan"
                                >
                                    <i class="fa-regular fa-eye"></i>
                                    View
                                </a>


                                <a
                                    href="edit.php?id=<?= (int)$plan['id'] ?>"
                                    class="sp-action sp-edit"
                                    title="Edit Plan"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                    Edit
                                </a>


                                <a
                                    href="toggle-status.php?id=<?= (int)$plan['id'] ?>"
                                    class="sp-action sp-status-action"
                                    title="Change Status"
                                    onclick="return confirm('Are you sure you want to change this plan status?');"
                                >
                                    <i class="fa-solid fa-toggle-on"></i>
                                    Status
                                </a>


                                <a
                                    href="delete.php?id=<?= (int)$plan['id'] ?>"
                                    class="sp-action sp-delete"
                                    title="Delete Plan"
                                    onclick="return confirm('Are you sure you want to delete this subscription plan?');"
                                >
                                    <i class="fa-solid fa-trash"></i>
                                    Delete
                                </a>

                            </div>

                        </article>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>

        </main>

    </div>

</div>

<?php include "../includes/footer.php"; ?>