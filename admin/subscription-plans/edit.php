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

$fetch = $conn->prepare(
    "
    SELECT
        id,
        name,
        duration_months,
        price,
        description,
        benefits,
        status
    FROM subscription_plans
    WHERE id = ?
    LIMIT 1
    "
);

$fetch->execute([$planId]);

$plan = $fetch->fetch();

if (!$plan) {
    header('Location: index.php');
    exit;
}

$page_title = 'Edit Subscription Plan | ExamSphere';

$error = '';

$name = (string)$plan['name'];
$duration_months = (string)$plan['duration_months'];
$price = (string)$plan['price'];
$description = (string)($plan['description'] ?? '');
$benefits = (string)($plan['benefits'] ?? '');
$status = (string)$plan['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $error = 'Security verification failed. Please refresh the page and try again.';

    } else {

        $name = trim((string)($_POST['name'] ?? ''));
        $duration_months = trim((string)($_POST['duration_months'] ?? ''));
        $price = trim((string)($_POST['price'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $benefits = trim((string)($_POST['benefits'] ?? ''));
        $status = trim((string)($_POST['status'] ?? ''));

        if ($name === '') {

            $error = 'Plan name is required.';

        } elseif (mb_strlen($name) > 50) {

            $error = 'Plan name cannot exceed 50 characters.';

        } elseif (
            $duration_months === '' ||
            !ctype_digit($duration_months)
        ) {

            $error = 'Please enter a valid duration.';

        } elseif (
            (int)$duration_months < 1 ||
            (int)$duration_months > 255
        ) {

            $error = 'Duration must be between 1 and 255 months.';

        } elseif ($price === '' || !is_numeric($price)) {

            $error = 'Please enter a valid price.';

        } elseif ((float)$price < 0) {

            $error = 'Price cannot be negative.';

        } elseif (
            !in_array(
                $status,
                ['Active', 'Inactive'],
                true
            )
        ) {

            $error = 'Invalid status selected.';
        }


        if ($error === '') {

            try {

                $duplicate = $conn->prepare(
                    "
                    SELECT id
                    FROM subscription_plans
                    WHERE name = ?
                      AND id <> ?
                    LIMIT 1
                    "
                );

                $duplicate->execute([
                    $name,
                    $planId
                ]);

                if ($duplicate->fetchColumn()) {

                    $error =
                        'Another plan already uses this name.';

                } else {

                    $update = $conn->prepare(
                        "
                        UPDATE subscription_plans
                        SET
                            name = ?,
                            duration_months = ?,
                            price = ?,
                            description = ?,
                            benefits = ?,
                            status = ?
                        WHERE id = ?
                        "
                    );

                    $update->execute([
                        $name,
                        (int)$duration_months,
                        number_format(
                            (float)$price,
                            2,
                            '.',
                            ''
                        ),
                        $description !== ''
                            ? $description
                            : null,
                        $benefits !== ''
                            ? $benefits
                            : null,
                        $status,
                        $planId
                    ]);

                    header(
                        'Location: index.php?success=updated'
                    );

                    exit;
                }

            } catch (Throwable $exception) {

                error_log(
                    'Subscription plan update failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to update the subscription plan.';
            }
        }
    }
}

function sp_edit_escape($value): string
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
.sp-edit-page{
    min-height:calc(100vh - 70px);
    padding:28px;
    background:linear-gradient(135deg,#f5f5dc,#fbfaf3 55%,#f1ebdf);
}

.sp-edit-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:20px;
    margin-bottom:22px;
}

.sp-edit-kicker{
    color:#556b2f;
    font-size:10px;
    font-weight:900;
    letter-spacing:1.5px;
}

.sp-edit-head h1{
    margin:6px 0 5px;
    color:#3e2723;
    font-size:34px;
    font-weight:900;
}

.sp-edit-head p{
    margin:0;
    color:#766e69;
    font-size:12px;
}

.sp-edit-back{
    display:inline-flex;
    align-items:center;
    gap:8px;
    min-height:43px;
    padding:0 15px;
    border-radius:11px;
    background:rgba(93,64,55,.08);
    color:#5d4037;
    text-decoration:none;
    font-size:11px;
    font-weight:800;
}

.sp-edit-card{
    max-width:980px;
    margin:0 auto;
    overflow:hidden;
    background:rgba(255,255,255,.93);
    border:1px solid rgba(93,64,55,.12);
    border-radius:22px;
    box-shadow:0 18px 45px rgba(62,39,35,.08);
}

.sp-edit-body{
    padding:25px;
}

.sp-edit-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:18px;
}

.sp-edit-field.full{
    grid-column:1/-1;
}

.sp-edit-field label{
    display:block;
    margin-bottom:7px;
    color:#3e2723;
    font-size:11px;
    font-weight:800;
}

.sp-edit-field input,
.sp-edit-field select,
.sp-edit-field textarea{
    width:100%;
    border:1px solid rgba(93,64,55,.15);
    border-radius:11px;
    outline:none;
    background:#fff;
    color:#333;
    font-size:12px;
}

.sp-edit-field input,
.sp-edit-field select{
    min-height:44px;
    padding:0 12px;
}

.sp-edit-field textarea{
    min-height:125px;
    padding:11px 12px;
    resize:vertical;
}

.sp-edit-field input:focus,
.sp-edit-field select:focus,
.sp-edit-field textarea:focus{
    border-color:#556b2f;
    box-shadow:0 0 0 4px rgba(85,107,47,.08);
}

.sp-edit-footer{
    display:flex;
    justify-content:flex-end;
    gap:10px;
    padding:18px 25px;
    background:#fcfaf6;
    border-top:1px solid #eee9e1;
}

.sp-edit-cancel,
.sp-edit-save{
    min-height:43px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:0 16px;
    border-radius:11px;
    font-size:11px;
    font-weight:800;
    text-decoration:none;
}

.sp-edit-cancel{
    background:rgba(93,64,55,.07);
    color:#5d4037;
}

.sp-edit-save{
    border:0;
    background:#556b2f;
    color:#fff;
    cursor:pointer;
}

@media(max-width:760px){
    .sp-edit-page{
        padding:18px;
    }

    .sp-edit-head{
        flex-direction:column;
        align-items:flex-start;
    }

    .sp-edit-back{
        width:100%;
        justify-content:center;
    }

    .sp-edit-grid{
        grid-template-columns:1fr;
    }

    .sp-edit-field.full{
        grid-column:auto;
    }

    .sp-edit-footer{
        flex-direction:column-reverse;
    }

    .sp-edit-cancel,
    .sp-edit-save{
        width:100%;
    }
}
</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content sp-edit-page">

            <section class="sp-edit-head">

                <div>

                    <div class="sp-edit-kicker">
                        <i class="fa-solid fa-pen-to-square"></i>
                        SUBSCRIPTION PLANS
                    </div>

                    <h1>
                        Edit Subscription Plan
                    </h1>

                    <p>
                        Update the selected subscription plan.
                    </p>

                </div>

                <a
                    href="index.php"
                    class="sp-edit-back"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Plans
                </a>

            </section>


            <section class="sp-edit-card">

                <div class="sp-edit-body">

                    <?php if ($error !== ''): ?>

                        <div class="alert alert-danger border-0 rounded-4 mb-4">
                            <i class="fa-solid fa-circle-exclamation me-2"></i>
                            <?= sp_edit_escape($error) ?>
                        </div>

                    <?php endif; ?>


                    <form
                        method="post"
                        id="subscriptionPlanEditForm"
                    >

                        <?= csrf_field() ?>


                        <div class="sp-edit-grid">

                            <div class="sp-edit-field">

                                <label for="name">
                                    Plan Name
                                </label>

                                <input
                                    id="name"
                                    name="name"
                                    type="text"
                                    maxlength="50"
                                    value="<?= sp_edit_escape($name) ?>"
                                    required
                                >

                            </div>


                            <div class="sp-edit-field">

                                <label for="duration_months">
                                    Duration in Months
                                </label>

                                <input
                                    id="duration_months"
                                    name="duration_months"
                                    type="number"
                                    min="1"
                                    max="255"
                                    value="<?= sp_edit_escape($duration_months) ?>"
                                    required
                                >

                            </div>


                            <div class="sp-edit-field">

                                <label for="price">
                                    Price
                                </label>

                                <input
                                    id="price"
                                    name="price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value="<?= sp_edit_escape($price) ?>"
                                    required
                                >

                            </div>


                            <div class="sp-edit-field">

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
                                        value="Inactive"
                                        <?= $status === 'Inactive'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Inactive
                                    </option>

                                </select>

                            </div>


                            <div class="sp-edit-field full">

                                <label for="description">
                                    Description
                                </label>

                                <textarea
                                    id="description"
                                    name="description"
                                    maxlength="5000"
                                ><?= sp_edit_escape($description) ?></textarea>

                            </div>


                            <div class="sp-edit-field full">

                                <label for="benefits">
                                    Benefits
                                </label>

                                <textarea
                                    id="benefits"
                                    name="benefits"
                                    maxlength="5000"
                                ><?= sp_edit_escape($benefits) ?></textarea>

                            </div>

                        </div>


                        <div class="sp-edit-footer">

                            <a
                                href="index.php"
                                class="sp-edit-cancel"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="sp-edit-save"
                            >
                                <i class="fa-solid fa-floppy-disk"></i>
                                Save Changes
                            </button>

                        </div>

                    </form>

                </div>

            </section>

        </main>

    </div>

</div>

<?php include "../includes/footer.php"; ?>