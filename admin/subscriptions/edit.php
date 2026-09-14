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

$fetch = $conn->prepare(
    "
    SELECT
        id,
        student_id,
        plan_id,
        start_date,
        end_date,
        status
    FROM subscriptions
    WHERE id = ?
    LIMIT 1
    "
);

$fetch->execute([$id]);

$subscription = $fetch->fetch();

if (!$subscription) {
    header('Location: ../subscriptions.php');
    exit;
}

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
    ORDER BY duration_months ASC, id ASC
    "
)->fetchAll();

$page_title = 'Edit Subscription | ExamSphere';

$error = '';

$studentId = (string)$subscription['student_id'];
$planId = (string)$subscription['plan_id'];
$startDate = (string)$subscription['start_date'];
$status = (string)$subscription['status'];

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
            (string)($_POST['status'] ?? '')
        );


        if (
            $studentId === '' ||
            !ctype_digit($studentId)
        ) {

            $error = 'Please select a valid student.';

        } elseif (
            $planId === '' ||
            !ctype_digit($planId)
        ) {

            $error = 'Please select a valid plan.';

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

                $studentCheck = $conn->prepare(
                    "
                    SELECT id
                    FROM students
                    WHERE id = ?
                      AND status = 'Active'
                    LIMIT 1
                    "
                );

                $studentCheck->execute([
                    (int)$studentId
                ]);

                if (!$studentCheck->fetchColumn()) {

                    throw new RuntimeException(
                        'Selected student is not available.'
                    );
                }


                $planCheck = $conn->prepare(
                    "
                    SELECT
                        id,
                        duration_months
                    FROM subscription_plans
                    WHERE id = ?
                    LIMIT 1
                    "
                );

                $planCheck->execute([
                    (int)$planId
                ]);

                $selectedPlan = $planCheck->fetch();

                if (!$selectedPlan) {

                    throw new RuntimeException(
                        'Selected subscription plan does not exist.'
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

                    $duplicate = $conn->prepare(
                        "
                        SELECT id
                        FROM subscriptions
                        WHERE student_id = ?
                          AND id <> ?
                          AND status = 'Active'
                          AND start_date <= ?
                          AND end_date >= ?
                        LIMIT 1
                        "
                    );

                    $duplicate->execute([
                        (int)$studentId,
                        $id,
                        $subscriptionStart->format('Y-m-d'),
                        $subscriptionStart->format('Y-m-d')
                    ]);

                    if ($duplicate->fetchColumn()) {

                        throw new RuntimeException(
                            'This student already has another active subscription.'
                        );
                    }
                }


                $update = $conn->prepare(
                    "
                    UPDATE subscriptions
                    SET
                        student_id = ?,
                        plan_id = ?,
                        start_date = ?,
                        end_date = ?,
                        status = ?
                    WHERE id = ?
                    "
                );

                $update->execute([
                    (int)$studentId,
                    (int)$planId,
                    $subscriptionStart->format('Y-m-d'),
                    $subscriptionEnd->format('Y-m-d'),
                    $status,
                    $id
                ]);


                header(
                    'Location: ../subscriptions.php?success=updated'
                );

                exit;

            } catch (Throwable $exception) {

                error_log(
                    'Subscription update failed: ' .
                    $exception->getMessage()
                );

                $error = $exception->getMessage();
            }
        }
    }
}

function sa_edit_escape($value): string
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
.sa-edit-page{
    min-height:calc(100vh - 70px);
    padding:28px;
    background:linear-gradient(135deg,#f5f5dc,#fbfaf3 55%,#f1ebdf);
}

.sa-edit-card{
    max-width:900px;
    margin:auto;
    padding:25px;
    background:rgba(255,255,255,.93);
    border:1px solid rgba(93,64,55,.12);
    border-radius:22px;
    box-shadow:0 18px 45px rgba(62,39,35,.08);
}

.sa-edit-kicker{
    color:#556b2f;
    font-size:10px;
    font-weight:900;
    letter-spacing:1.5px;
}

.sa-edit-card h1{
    margin:6px 0;
    color:#3e2723;
    font-size:32px;
    font-weight:900;
}

.sa-edit-card p{
    color:#766e69;
    font-size:12px;
}

.sa-edit-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:17px;
    margin-top:22px;
}

.sa-edit-field label{
    display:block;
    margin-bottom:7px;
    color:#3e2723;
    font-size:11px;
    font-weight:800;
}

.sa-edit-field input,
.sa-edit-field select{
    width:100%;
    min-height:44px;
    border:1px solid rgba(93,64,55,.15);
    border-radius:11px;
    background:#fff;
    padding:0 12px;
    outline:none;
    font-size:12px;
}

.sa-edit-field input:focus,
.sa-edit-field select:focus{
    border-color:#556b2f;
    box-shadow:0 0 0 4px rgba(85,107,47,.08);
}

.sa-edit-actions{
    display:flex;
    justify-content:flex-end;
    gap:9px;
    margin-top:22px;
}

.sa-edit-cancel,
.sa-edit-save{
    min-height:43px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:0 16px;
    border-radius:11px;
    text-decoration:none;
    font-size:11px;
    font-weight:800;
}

.sa-edit-cancel{
    color:#5d4037;
    background:rgba(93,64,55,.07);
}

.sa-edit-save{
    border:0;
    background:#556b2f;
    color:#fff;
}

@media(max-width:700px){
    .sa-edit-page{
        padding:18px;
    }

    .sa-edit-grid{
        grid-template-columns:1fr;
    }

    .sa-edit-actions{
        flex-direction:column-reverse;
    }

    .sa-edit-cancel,
    .sa-edit-save{
        width:100%;
    }
}
</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content sa-edit-page">

            <section class="sa-edit-card">

                <div class="sa-edit-kicker">
                    <i class="fa-solid fa-pen-to-square"></i>
                    ACCESS CONTROL
                </div>

                <h1>
                    Edit Subscription
                </h1>

                <p>
                    Update student, plan, dates and status.
                </p>


                <?php if ($error !== ''): ?>

                    <div class="alert alert-danger border-0 rounded-4">
                        <i class="fa-solid fa-circle-exclamation me-2"></i>
                        <?= sa_edit_escape($error) ?>
                    </div>

                <?php endif; ?>


                <form method="post">

                    <?= csrf_field() ?>


                    <div class="sa-edit-grid">

                        <div class="sa-edit-field">

                            <label for="student_id">
                                Student
                            </label>

                            <select
                                id="student_id"
                                name="student_id"
                                required
                            >

                                <?php foreach ($students as $student): ?>

                                    <option
                                        value="<?= (int)$student['id'] ?>"
                                        <?= (int)$studentId ===
                                            (int)$student['id']
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= sa_edit_escape(
                                            $student['full_name']
                                        ) ?>

                                        —
                                        <?= sa_edit_escape(
                                            $student['email']
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="sa-edit-field">

                            <label for="plan_id">
                                Subscription Plan
                            </label>

                            <select
                                id="plan_id"
                                name="plan_id"
                                required
                            >

                                <?php foreach ($plans as $plan): ?>

                                    <option
                                        value="<?= (int)$plan['id'] ?>"
                                        <?= (int)$planId ===
                                            (int)$plan['id']
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= sa_edit_escape(
                                            $plan['name']
                                        ) ?>

                                        —
                                        <?= (int)$plan['duration_months'] ?>
                                        month(s)

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="sa-edit-field">

                            <label for="start_date">
                                Start Date
                            </label>

                            <input
                                type="date"
                                id="start_date"
                                name="start_date"
                                value="<?= sa_edit_escape($startDate) ?>"
                                required
                            >

                        </div>


                        <div class="sa-edit-field">

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


                    <div class="sa-edit-actions">

                        <a
                            href="../subscriptions.php"
                            class="sa-edit-cancel"
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="sa-edit-save"
                        >
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save Changes
                        </button>

                    </div>

                </form>

            </section>

        </main>

    </div>

</div>

<?php include "../includes/footer.php"; ?>