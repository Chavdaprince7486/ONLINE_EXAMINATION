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

$planId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$planId || $planId < 1) {
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare(
    "
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
    WHERE id = ?
    LIMIT 1
    "
);

$stmt->execute([$planId]);

$plan = $stmt->fetch();

if (!$plan) {
    header('Location: index.php');
    exit;
}

$usage = $conn->prepare(
    "
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Active') AS active,
        SUM(status = 'Expired') AS expired,
        SUM(status = 'Cancelled') AS cancelled
    FROM subscriptions
    WHERE plan_id = ?
    "
);

$usage->execute([$planId]);

$stats = $usage->fetch();

$benefitItems = [];

$benefitsText = trim(
    (string)($plan['benefits'] ?? '')
);

if ($benefitsText !== '') {

    $benefitsText = str_replace(
        ["\r\n", "\r"],
        "\n",
        $benefitsText
    );

    foreach (
        explode("\n", $benefitsText)
        as $line
    ) {

        $line = trim($line);

        if ($line === '') {
            continue;
        }

        $first = substr($line, 0, 1);

        if (
            $first === '-' ||
            $first === '*' ||
            $first === '•'
        ) {
            $line = trim(substr($line, 1));
        }

        if ($line !== '') {
            $benefitItems[] = $line;
        }
    }
}

function sp_view_escape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$page_title = 'Subscription Plan Details | ExamSphere';

require_once "../includes/header.php";
?>

<style>
.sp-view-page{
    min-height:calc(100vh - 70px);
    padding:28px;
    background:linear-gradient(135deg,#f5f5dc,#fbfaf3 55%,#f1ebdf);
}

.sp-view-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:20px;
    margin-bottom:22px;
}

.sp-view-kicker{
    color:#556b2f;
    font-size:10px;
    font-weight:900;
    letter-spacing:1.5px;
}

.sp-view-head h1{
    margin:6px 0 5px;
    color:#3e2723;
    font-size:34px;
    font-weight:900;
}

.sp-view-head p{
    margin:0;
    color:#766e69;
    font-size:12px;
}

.sp-view-actions{
    display:flex;
    gap:8px;
}

.sp-view-btn{
    min-height:43px;
    padding:0 15px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    border-radius:11px;
    text-decoration:none;
    font-size:11px;
    font-weight:800;
}

.sp-view-back{
    color:#5d4037;
    background:rgba(93,64,55,.08);
}

.sp-view-edit{
    color:#fff;
    background:#556b2f;
}

.sp-view-wrap{
    max-width:1080px;
    margin:auto;
}

.sp-view-top{
    display:grid;
    grid-template-columns:1.1fr .9fr;
    gap:18px;
}

.sp-view-card{
    padding:24px;
    background:rgba(255,255,255,.93);
    border:1px solid rgba(93,64,55,.12);
    border-radius:22px;
    box-shadow:0 18px 45px rgba(62,39,35,.08);
}

.sp-view-status{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:7px 11px;
    border-radius:999px;
    font-size:10px;
    font-weight:900;
}

.sp-view-status.active{
    color:#556b2f;
    background:rgba(85,107,47,.10);
}

.sp-view-status.inactive{
    color:#8c625a;
    background:rgba(140,98,90,.09);
}

.sp-view-title{
    margin:17px 0 4px;
    color:#3e2723;
    font-size:32px;
    font-weight:900;
}

.sp-view-duration{
    color:#766e69;
    font-size:12px;
}

.sp-view-price{
    margin-top:22px;
    color:#3e2723;
    font-size:43px;
    line-height:1;
    font-weight:900;
}

.sp-view-price span{
    color:#766e69;
    font-size:11px;
    font-weight:700;
}

.sp-info-title{
    margin:0 0 12px;
    color:#3e2723;
    font-size:15px;
    font-weight:900;
}

.sp-info-row{
    display:flex;
    justify-content:space-between;
    gap:15px;
    padding:11px 0;
    border-bottom:1px solid #eee9e1;
    font-size:11px;
}

.sp-info-row:last-child{
    border-bottom:0;
}

.sp-info-row span:first-child{
    color:#766e69;
}

.sp-info-row span:last-child{
    color:#3e2723;
    font-weight:800;
    text-align:right;
}

.sp-view-bottom{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
    margin-top:18px;
}

.sp-view-card h2{
    margin:0 0 14px;
    color:#3e2723;
    font-size:16px;
    font-weight:900;
}

.sp-description-text{
    color:#766e69;
    font-size:12px;
    line-height:1.8;
}

.sp-benefit-list{
    margin:0;
    padding:0;
    list-style:none;
}

.sp-benefit-list li{
    display:flex;
    align-items:flex-start;
    gap:8px;
    padding:8px 0;
    border-bottom:1px solid #f0ece6;
    color:#403a36;
    font-size:11px;
}

.sp-benefit-list li:last-child{
    border-bottom:0;
}

.sp-benefit-list i{
    margin-top:3px;
    color:#556b2f;
}

.sp-usage{
    margin-top:18px;
}

.sp-usage-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:10px;
}

.sp-usage-box{
    padding:14px 10px;
    text-align:center;
    background:#faf7f1;
    border-radius:13px;
}

.sp-usage-box strong{
    display:block;
    color:#3e2723;
    font-size:22px;
    font-weight:900;
}

.sp-usage-box span{
    color:#766e69;
    font-size:9px;
    font-weight:700;
}

@media(max-width:850px){
    .sp-view-top,
    .sp-view-bottom{
        grid-template-columns:1fr;
    }

    .sp-view-head{
        align-items:flex-start;
        flex-direction:column;
    }

    .sp-view-actions{
        width:100%;
    }

    .sp-view-btn{
        flex:1;
    }
}

@media(max-width:600px){
    .sp-view-page{
        padding:18px;
    }

    .sp-usage-grid{
        grid-template-columns:repeat(2,1fr);
    }
}
</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content sp-view-page">

            <section class="sp-view-head">

                <div>

                    <div class="sp-view-kicker">
                        <i class="fa-solid fa-eye"></i>
                        SUBSCRIPTION PLANS
                    </div>

                    <h1>
                        Plan Details
                    </h1>

                    <p>
                        Complete overview of the selected plan.
                    </p>

                </div>

                <div class="sp-view-actions">

                    <a
                        href="index.php"
                        class="sp-view-btn sp-view-back"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                        Back
                    </a>

                    <a
                        href="edit.php?id=<?= $planId ?>"
                        class="sp-view-btn sp-view-edit"
                    >
                        <i class="fa-solid fa-pen"></i>
                        Edit
                    </a>

                </div>

            </section>


            <div class="sp-view-wrap">

                <section class="sp-view-top">

                    <div class="sp-view-card">

                        <span
                            class="
                                sp-view-status
                                <?= $plan['status'] === 'Active'
                                    ? 'active'
                                    : 'inactive' ?>
                            "
                        >
                            <i class="fa-solid fa-circle"></i>
                            <?= sp_view_escape($plan['status']) ?>
                        </span>


                        <h2 class="sp-view-title">
                            <?= sp_view_escape($plan['name']) ?>
                        </h2>


                        <div class="sp-view-duration">

                            <i class="fa-regular fa-clock"></i>

                            <?= (int)$plan['duration_months'] ?>

                            month<?= (int)$plan['duration_months'] === 1
                                ? ''
                                : 's' ?>

                        </div>


                        <div class="sp-view-price">

                            ₹<?= number_format(
                                (float)$plan['price'],
                                2
                            ) ?>

                            <span>
                                / plan
                            </span>

                        </div>

                    </div>


                    <div class="sp-view-card">

                        <h2 class="sp-info-title">
                            Plan Information
                        </h2>


                        <div class="sp-info-row">

                            <span>
                                Plan ID
                            </span>

                            <span>
                                #<?= (int)$plan['id'] ?>
                            </span>

                        </div>


                        <div class="sp-info-row">

                            <span>
                                Duration
                            </span>

                            <span>
                                <?= (int)$plan['duration_months'] ?>
                                month(s)
                            </span>

                        </div>


                        <div class="sp-info-row">

                            <span>
                                Status
                            </span>

                            <span>
                                <?= sp_view_escape($plan['status']) ?>
                            </span>

                        </div>


                        <div class="sp-info-row">

                            <span>
                                Created
                            </span>

                            <span>
                                <?= sp_view_escape(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            (string)$plan['created_at']
                                        )
                                    )
                                ) ?>
                            </span>

                        </div>

                    </div>

                </section>


                <section class="sp-view-bottom">

                    <div class="sp-view-card">

                        <h2>
                            Description
                        </h2>

                        <div class="sp-description-text">

                            <?= nl2br(
                                sp_view_escape(
                                    $plan['description']
                                    ?: 'No description available.'
                                )
                            ) ?>

                        </div>

                    </div>


                    <div class="sp-view-card">

                        <h2>
                            Benefits
                        </h2>

                        <?php if (!empty($benefitItems)): ?>

                            <ul class="sp-benefit-list">

                                <?php foreach ($benefitItems as $benefit): ?>

                                    <li>

                                        <i class="fa-solid fa-check"></i>

                                        <span>
                                            <?= sp_view_escape($benefit) ?>
                                        </span>

                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        <?php else: ?>

                            <div class="sp-description-text">
                                No benefits have been added.
                            </div>

                        <?php endif; ?>

                    </div>

                </section>


                <section class="sp-view-card sp-usage">

                    <h2>
                        Subscription Usage
                    </h2>


                    <div class="sp-usage-grid">

                        <div class="sp-usage-box">

                            <strong>
                                <?= (int)($stats['total'] ?? 0) ?>
                            </strong>

                            <span>
                                Total
                            </span>

                        </div>


                        <div class="sp-usage-box">

                            <strong>
                                <?= (int)($stats['active'] ?? 0) ?>
                            </strong>

                            <span>
                                Active
                            </span>

                        </div>


                        <div class="sp-usage-box">

                            <strong>
                                <?= (int)($stats['expired'] ?? 0) ?>
                            </strong>

                            <span>
                                Expired
                            </span>

                        </div>


                        <div class="sp-usage-box">

                            <strong>
                                <?= (int)($stats['cancelled'] ?? 0) ?>
                            </strong>

                            <span>
                                Cancelled
                            </span>

                        </div>

                    </div>

                </section>

            </div>

        </main>

    </div>

</div>

<?php include "../includes/footer.php"; ?>