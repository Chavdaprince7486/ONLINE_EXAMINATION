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

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    header('Location: ../subscriptions.php');
    exit;
}

$stmt = $conn->prepare(
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
        st.mobile,

        p.name AS plan_name,
        p.duration_months,
        p.price,
        p.description,
        p.benefits

    FROM subscriptions s

    INNER JOIN students st
        ON st.id = s.student_id

    INNER JOIN subscription_plans p
        ON p.id = s.plan_id

    WHERE s.id = ?

    LIMIT 1
    "
);

$stmt->execute([$id]);

$subscription = $stmt->fetch();

if (!$subscription) {
    header('Location: ../subscriptions.php');
    exit;
}

function sa_view_escape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$page_title = 'Subscription Details | ExamSphere';

require_once "../includes/header.php";
?>

<style>
.sa-view-page{
    min-height:calc(100vh - 70px);
    padding:28px;
    background:linear-gradient(135deg,#f5f5dc,#fbfaf3 55%,#f1ebdf);
}

.sa-view-card{
    max-width:1050px;
    margin:auto;
    background:rgba(255,255,255,.93);
    border:1px solid rgba(93,64,55,.12);
    border-radius:22px;
    box-shadow:0 18px 45px rgba(62,39,35,.08);
    overflow:hidden;
}

.sa-view-head{
    padding:25px;
    border-bottom:1px solid #eee9e1;
}

.sa-view-kicker{
    color:#556b2f;
    font-size:10px;
    font-weight:900;
    letter-spacing:1.5px;
}

.sa-view-head h1{
    margin:6px 0;
    color:#3e2723;
    font-size:32px;
    font-weight:900;
}

.sa-view-head p{
    margin:0;
    color:#766e69;
    font-size:12px;
}

.sa-view-body{
    padding:25px;
}

.sa-view-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:15px;
}

.sa-info{
    padding:17px;
    border-radius:15px;
    background:#faf7f1;
}

.sa-info-label{
    color:#8b817b;
    font-size:9px;
    font-weight:800;
    letter-spacing:.7px;
    text-transform:uppercase;
}

.sa-info-value{
    margin-top:5px;
    color:#3e2723;
    font-size:14px;
    font-weight:900;
}

.sa-info-sub{
    margin-top:3px;
    color:#766e69;
    font-size:10px;
}

.sa-status{
    display:inline-flex;
    padding:6px 9px;
    border-radius:999px;
    background:rgba(85,107,47,.10);
    color:#556b2f;
    font-size:9px;
    font-weight:900;
}

.sa-section{
    margin-top:18px;
}

.sa-section h2{
    margin:0 0 10px;
    color:#3e2723;
    font-size:15px;
    font-weight:900;
}

.sa-section p{
    color:#766e69;
    font-size:12px;
    line-height:1.8;
}

.sa-view-actions{
    display:flex;
    justify-content:flex-end;
    gap:9px;
    padding:18px 25px;
    background:#fcfaf6;
    border-top:1px solid #eee9e1;
}

.sa-view-btn{
    min-height:43px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:0 15px;
    border-radius:11px;
    text-decoration:none;
    font-size:11px;
    font-weight:800;
}

.sa-view-back{
    background:rgba(93,64,55,.07);
    color:#5d4037;
}

.sa-view-edit{
    background:#556b2f;
    color:#fff;
}

@media(max-width:700px){
    .sa-view-page{
        padding:18px;
    }

    .sa-view-grid{
        grid-template-columns:1fr;
    }

    .sa-view-actions{
        flex-direction:column;
    }

    .sa-view-btn{
        width:100%;
    }
}
</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content sa-view-page">

            <section class="sa-view-card">

                <header class="sa-view-head">

                    <div class="sa-view-kicker">
                        <i class="fa-solid fa-user-check"></i>
                        SUBSCRIPTION RECORD
                    </div>

                    <h1>
                        Subscription Details
                    </h1>

                    <p>
                        Complete details of the selected student subscription.
                    </p>

                </header>


                <div class="sa-view-body">

                    <div class="sa-view-grid">


                        <div class="sa-info">

                            <div class="sa-info-label">
                                Student
                            </div>

                            <div class="sa-info-value">
                                <?= sa_view_escape(
                                    $subscription['full_name']
                                ) ?>
                            </div>

                            <div class="sa-info-sub">
                                <?= sa_view_escape(
                                    $subscription['email']
                                ) ?>
                            </div>

                        </div>


                        <div class="sa-info">

                            <div class="sa-info-label">
                                Plan
                            </div>

                            <div class="sa-info-value">
                                <?= sa_view_escape(
                                    $subscription['plan_name']
                                ) ?>
                            </div>

                            <div class="sa-info-sub">

                                <?= (int)$subscription['duration_months'] ?>
                                month(s)

                                —
                                ₹<?= number_format(
                                    (float)$subscription['price'],
                                    2
                                ) ?>

                            </div>

                        </div>


                        <div class="sa-info">

                            <div class="sa-info-label">
                                Start Date
                            </div>

                            <div class="sa-info-value">
                                <?= sa_view_escape(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $subscription['start_date']
                                        )
                                    )
                                ) ?>
                            </div>

                        </div>


                        <div class="sa-info">

                            <div class="sa-info-label">
                                End Date
                            </div>

                            <div class="sa-info-value">
                                <?= sa_view_escape(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $subscription['end_date']
                                        )
                                    )
                                ) ?>
                            </div>

                        </div>


                        <div class="sa-info">

                            <div class="sa-info-label">
                                Status
                            </div>

                            <div class="sa-info-value">

                                <span class="sa-status">
                                    <?= sa_view_escape(
                                        $subscription['status']
                                    ) ?>
                                </span>

                            </div>

                        </div>


                        <div class="sa-info">

                            <div class="sa-info-label">
                                Subscription ID
                            </div>

                            <div class="sa-info-value">
                                #<?= (int)$subscription['id'] ?>
                            </div>

                        </div>

                    </div>


                    <section class="sa-section">

                        <h2>
                            Plan Description
                        </h2>

                        <p>
                            <?= nl2br(
                                sa_view_escape(
                                    $subscription['description']
                                    ?: 'No description available.'
                                )
                            ) ?>
                        </p>

                    </section>


                    <section class="sa-section">

                        <h2>
                            Plan Benefits
                        </h2>

                        <p>
                            <?= nl2br(
                                sa_view_escape(
                                    $subscription['benefits']
                                    ?: 'No benefits available.'
                                )
                            ) ?>
                        </p>

                    </section>

                </div>


                <footer class="sa-view-actions">

                    <a
                        href="../subscriptions.php"
                        class="sa-view-btn sa-view-back"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                        Back
                    </a>

                    <a
                        href="edit.php?id=<?= (int)$subscription['id'] ?>"
                        class="sa-view-btn sa-view-edit"
                    >
                        <i class="fa-solid fa-pen"></i>
                        Edit Subscription
                    </a>

                </footer>

            </section>

        </main>

    </div>

</div>

<?php include "../includes/footer.php"; ?>