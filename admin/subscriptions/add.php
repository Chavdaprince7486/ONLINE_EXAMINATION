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

$page_title = 'Assign Subscription | ExamSphere';

$error = '';

$students = $conn->query(
    "
    SELECT
        id,
        full_name,
        email
    FROM students
    WHERE status = 'Active'
    ORDER BY full_name ASC
    "
)->fetchAll();

$plans = $conn->query(
    "
    SELECT
        id,
        name,
        duration_months,
        price
    FROM subscription_plans
    WHERE status = 'Active'
    ORDER BY duration_months ASC, id ASC
    "
)->fetchAll();

$studentId = '';
$planId = '';
$startDate = date('Y-m-d');
$status = 'Active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $error = 'Security verification failed. Please refresh the page and try again.';

    } else {

        $studentId = trim(
            (string)($_POST['student_id'] ?? '')
        );

        $planId = trim(
            (string)($_POST['plan_id'] ?? '')
        );

        $startDate = trim(
            (string)($_POST['start_date'] ?? '')
        );

        $status = trim(
            (string)($_POST['status'] ?? 'Active')
        );


        if (
            $studentId === '' ||
            !ctype_digit($studentId) ||
            (int)$studentId < 1
        ) {

            $error = 'Please select a valid student.';

        } elseif (
            $planId === '' ||
            !ctype_digit($planId) ||
            (int)$planId < 1
        ) {

            $error = 'Please select a valid subscription plan.';

        } elseif (
            $startDate === '' ||
            !preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $startDate
            )
        ) {

            $error = 'Please enter a valid start date.';

        } elseif (
            !in_array(
                $status,
                ['Active', 'Expired', 'Cancelled'],
                true
            )
        ) {

            $error = 'Invalid subscription status.';
        }


        if ($error === '') {

            try {

                $studentStmt = $conn->prepare(
                    "
                    SELECT id
                    FROM students
                    WHERE id = ?
                      AND status = 'Active'
                    LIMIT 1
                    "
                );

                $studentStmt->execute([
                    (int)$studentId
                ]);

                if (!$studentStmt->fetchColumn()) {

                    throw new RuntimeException(
                        'Selected student is not available.'
                    );
                }


                $planStmt = $conn->prepare(
                    "
                    SELECT
                        id,
                        duration_months
                    FROM subscription_plans
                    WHERE id = ?
                      AND status = 'Active'
                    LIMIT 1
                    "
                );

                $planStmt->execute([
                    (int)$planId
                ]);

                $selectedPlan = $planStmt->fetch();

                if (!$selectedPlan) {

                    throw new RuntimeException(
                        'Selected subscription plan is not available.'
                    );
                }


                $start = new DateTimeImmutable(
                    $startDate
                );


                [
                    $subscriptionStart,
                    $subscriptionEnd
                ] = subscription_period(
                    $start,
                    (int)$selectedPlan['duration_months']
                );


                if ($status === 'Active') {

                    $activeCheck = $conn->prepare(
                        "
                        SELECT id
                        FROM subscriptions
                        WHERE student_id = ?
                          AND status = 'Active'
                          AND start_date <= ?
                          AND end_date >= ?
                        LIMIT 1
                        "
                    );

                    $activeCheck->execute([
                        (int)$studentId,
                        $subscriptionStart->format('Y-m-d'),
                        $subscriptionStart->format('Y-m-d')
                    ]);

                    if ($activeCheck->fetchColumn()) {

                        throw new RuntimeException(
                            'This student already has an active subscription.'
                        );
                    }
                }


                $insert = $conn->prepare(
                    "
                    INSERT INTO subscriptions
                    (
                        student_id,
                        plan_id,
                        start_date,
                        end_date,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                    "
                );

                $insert->execute([
                    (int)$studentId,
                    (int)$planId,
                    $subscriptionStart->format('Y-m-d'),
                    $subscriptionEnd->format('Y-m-d'),
                    $status
                ]);


                header(
                    'Location: ../subscriptions.php?success=created'
                );

                exit;

            } catch (Throwable $exception) {

                error_log(
                    'Subscription assignment failed: ' .
                    $exception->getMessage()
                );

                $error = $exception->getMessage();
            }
        }
    }
}

function sa_add_escape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

require_once "../includes/header.php";
?>

<style>
.sa-form-page{
    min-height:calc(100vh - 70px);
    padding:28px;
    background:linear-gradient(135deg,#f5f5dc,#fbfaf3 55%,#f1ebdf);
}

.sa-form-card{
    max-width:900px;
    margin:0 auto;
    padding:25px;
    background:rgba(255,255,255,.93);
    border:1px solid rgba(93,64,55,.12);
    border-radius:22px;
    box-shadow:0 18px 45px rgba(62,39,35,.08);
}

.sa-kicker{
    color:#556b2f;
    font-size:10px;
    font-weight:900;
    letter-spacing:1.5px;
}

.sa-form-card h1{
    margin:6px 0 5px;
    color:#3e2723;
    font-size:32px;
    font-weight:900;
}

.sa-form-card p{
    color:#766e69;
    font-size:12px;
}

.sa-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:17px;
    margin-top:22px;
}

.sa-field.full{
    grid-column:1/-1;
}

.sa-field label{
    display:block;
    margin-bottom:7px;
    color:#3e2723;
    font-size:11px;
    font-weight:800;
}

.sa-field select,
.sa-field input{
    width:100%;
    min-height:44px;
    border:1px solid rgba(93,64,55,.15);
    border-radius:11px;
    background:#fff;
    padding:0 12px;
    outline:none;
    font-size:12px;
}

.sa-field select:focus,
.sa-field input:focus{
    border-color:#556b2f;
    box-shadow:0 0 0 4px rgba(85,107,47,.08);
}

.sa-actions{
    display:flex;
    justify-content:flex-end;
    gap:9px;
    margin-top:22px;
}

.sa-cancel,
.sa-save{
    min-height:43px;
    padding:0 16px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    border-radius:11px;
    text-decoration:none;
    font-size:11px;
    font-weight:800;
}

.sa-cancel{
    background:rgba(93,64,55,.07);
    color:#5d4037;
}

.sa-save{
    border:0;
    cursor:pointer;
    background:#556b2f;
    color:#fff;
}

@media(max-width:700px){
    .sa-form-page{
        padding:18px;
    }

    .sa-grid{
        grid-template-columns:1fr;
    }

    .sa-field.full{
        grid-column:auto;
    }

    .sa-actions{
        flex-direction:column-reverse;
    }

    .sa-cancel,
    .sa-save{
        width:100%;
    }
}
</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content sa-form-page">

            <section class="sa-form-card">

                <div class="sa-kicker">
                    <i class="fa-solid fa-user-check"></i>
                    ACCESS CONTROL
                </div>

                <h1>
                    Assign Subscription
                </h1>

                <p>
                    Assign an admin-managed subscription to a student.
                </p>


                <?php if ($error !== ''): ?>

                    <div class="alert alert-danger border-0 rounded-4">

                        <i class="fa-solid fa-circle-exclamation me-2"></i>

                        <?= sa_add_escape($error) ?>

                    </div>

                <?php endif; ?>


                <form
                    method="post"
                    id="subscriptionAssignForm"
                >

                    <?= csrf_field() ?>


                    <div class="sa-grid">


                        <div class="sa-field full">

                            <label for="student_id">
                                Student
                            </label>

                            <select
                                id="student_id"
                                name="student_id"
                                required
                            >

                                <option value="">
                                    Select student
                                </option>

                                <?php foreach ($students as $student): ?>

                                    <option
                                        value="<?= (int)$student['id'] ?>"
                                        <?= (string)$studentId ===
                                            (string)$student['id']
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= sa_add_escape(
                                            $student['full_name']
                                        ) ?>

                                        —
                                        <?= sa_add_escape(
                                            $student['email']
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="sa-field">

                            <label for="plan_id">
                                Subscription Plan
                            </label>

                            <select
                                id="plan_id"
                                name="plan_id"
                                required
                            >

                                <option value="">
                                    Select plan
                                </option>

                                <?php foreach ($plans as $plan): ?>

                                    <option
                                        value="<?= (int)$plan['id'] ?>"
                                        <?= (string)$planId ===
                                            (string)$plan['id']
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= sa_add_escape(
                                            $plan['name']
                                        ) ?>

                                        —
                                        <?= (int)$plan['duration_months'] ?>
                                        month(s)

                                        —
                                        ₹<?= number_format(
                                            (float)$plan['price'],
                                            2
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="sa-field">

                            <label for="start_date">
                                Start Date
                            </label>

                            <input
                                type="date"
                                id="start_date"
                                name="start_date"
                                value="<?= sa_add_escape($startDate) ?>"
                                required
                            >

                        </div>


                        <div class="sa-field">

                            <label for="status">
                                Status
                            </label>

                            <select
                                id="status"
                                name="status"
                                required
                            >

                                <option
                                    value="Active"
                                    <?= $status === 'Active'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Active
                                </option>

                                <option
                                    value="Expired"
                                    <?= $status === 'Expired'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Expired
                                </option>

                                <option
                                    value="Cancelled"
                                    <?= $status === 'Cancelled'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Cancelled
                                </option>

                            </select>

                        </div>

                    </div>


                    <div class="sa-actions">

                        <a
                            href="../subscriptions.php"
                            class="sa-cancel"
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="sa-save"
                            id="assignSubscriptionButton"
                        >
                            <i class="fa-solid fa-plus"></i>
                            Assign Subscription
                        </button>

                    </div>

                </form>

            </section>

        </main>

    </div>

</div>

<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {

        const form =
            document.getElementById(
                'subscriptionAssignForm'
            );

        const button =
            document.getElementById(
                'assignSubscriptionButton'
            );

        if (!form || !button) {
            return;
        }

        form.addEventListener(
            'submit',
            function () {

                if (!form.checkValidity()) {
                    return;
                }

                button.disabled = true;

                button.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> Assigning...';
            }
        );

    }
);
</script>

<?php include "../includes/footer.php"; ?>