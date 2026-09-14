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

$page_title = 'Add Subscription Plan | ExamSphere';

$error = '';

$name = '';
$duration_months = '';
$price = '';
$description = '';
$benefits = '';
$status = 'Active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $error = 'Security verification failed. Please refresh the page and try again.';

    } else {

        $name = trim((string)($_POST['name'] ?? ''));
        $duration_months = trim((string)($_POST['duration_months'] ?? ''));
        $price = trim((string)($_POST['price'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $benefits = trim((string)($_POST['benefits'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'Active'));

        if ($name === '') {

            $error = 'Plan name is required.';

        } elseif (mb_strlen($name) > 50) {

            $error = 'Plan name cannot exceed 50 characters.';

        } elseif (
            $duration_months === '' ||
            !ctype_digit($duration_months)
        ) {

            $error = 'Please enter a valid duration in months.';

        } elseif (
            (int)$duration_months < 1 ||
            (int)$duration_months > 255
        ) {

            $error = 'Duration must be between 1 and 255 months.';

        } elseif ($price === '') {

            $error = 'Price is required.';

        } elseif (!is_numeric($price)) {

            $error = 'Please enter a valid price.';

        } elseif ((float)$price < 0) {

            $error = 'Price cannot be negative.';

        } elseif ((float)$price > 99999999.99) {

            $error = 'Price is too large.';

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

                $check = $conn->prepare(
                    "
                    SELECT id
                    FROM subscription_plans
                    WHERE name = ?
                    LIMIT 1
                    "
                );

                $check->execute([$name]);

                if ($check->fetchColumn()) {

                    $error =
                        'A subscription plan with this name already exists.';

                } else {

                    $insert = $conn->prepare(
                        "
                        INSERT INTO subscription_plans
                        (
                            name,
                            duration_months,
                            price,
                            description,
                            benefits,
                            status
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?
                        )
                        "
                    );

                    $insert->execute([
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
                        $status
                    ]);

                    header(
                        'Location: index.php?success=created'
                    );

                    exit;
                }

            } catch (Throwable $exception) {

                error_log(
                    'Subscription plan creation failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to create the subscription plan.';
            }
        }
    }
}

function sp_add_escape($value): string
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
.sp-form-page{
    min-height:calc(100vh - 70px);
    padding:28px;
    background:linear-gradient(135deg,#f5f5dc,#fbfaf3 55%,#f1ebdf);
}

.sp-form-head{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:20px;
    margin-bottom:22px;
}

.sp-form-kicker{
    color:#556b2f;
    font-size:10px;
    font-weight:900;
    letter-spacing:1.5px;
}

.sp-form-head h1{
    margin:6px 0 5px;
    color:#3e2723;
    font-size:34px;
    font-weight:900;
}

.sp-form-head p{
    margin:0;
    color:#766e69;
    font-size:12px;
}

.sp-back{
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

.sp-form-card{
    max-width:980px;
    margin:0 auto;
    overflow:hidden;
    background:rgba(255,255,255,.93);
    border:1px solid rgba(93,64,55,.12);
    border-radius:22px;
    box-shadow:0 18px 45px rgba(62,39,35,.08);
}

.sp-form-body{
    padding:25px;
}

.sp-form-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:18px;
}

.sp-form-field.full{
    grid-column:1/-1;
}

.sp-form-field label{
    display:block;
    margin-bottom:7px;
    color:#3e2723;
    font-size:11px;
    font-weight:800;
}

.sp-form-field label span{
    color:#a63c37;
}

.sp-form-field input,
.sp-form-field select,
.sp-form-field textarea{
    width:100%;
    border:1px solid rgba(93,64,55,.15);
    border-radius:11px;
    background:#fff;
    color:#333;
    outline:none;
    font-size:12px;
}

.sp-form-field input,
.sp-form-field select{
    min-height:44px;
    padding:0 12px;
}

.sp-form-field textarea{
    min-height:125px;
    padding:11px 12px;
    resize:vertical;
}

.sp-form-field input:focus,
.sp-form-field select:focus,
.sp-form-field textarea:focus{
    border-color:#556b2f;
    box-shadow:0 0 0 4px rgba(85,107,47,.08);
}

.sp-form-help{
    margin-top:6px;
    color:#8c827b;
    font-size:10px;
}

.sp-form-footer{
    display:flex;
    justify-content:flex-end;
    gap:10px;
    padding:18px 25px;
    background:#fcfaf6;
    border-top:1px solid #eee9e1;
}

.sp-cancel,
.sp-save{
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

.sp-cancel{
    background:rgba(93,64,55,.07);
    color:#5d4037;
}

.sp-save{
    border:0;
    background:#556b2f;
    color:#fff;
    cursor:pointer;
}

@media(max-width:760px){
    .sp-form-page{
        padding:18px;
    }

    .sp-form-head{
        align-items:flex-start;
        flex-direction:column;
    }

    .sp-back{
        width:100%;
        justify-content:center;
    }

    .sp-form-grid{
        grid-template-columns:1fr;
    }

    .sp-form-field.full{
        grid-column:auto;
    }

    .sp-form-footer{
        flex-direction:column-reverse;
    }

    .sp-cancel,
    .sp-save{
        width:100%;
    }
}
</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content sp-form-page">

            <section class="sp-form-head">

                <div>

                    <div class="sp-form-kicker">
                        <i class="fa-solid fa-gem"></i>
                        SUBSCRIPTION PLANS
                    </div>

                    <h1>
                        Add Subscription Plan
                    </h1>

                    <p>
                        Create a new plan for student subscriptions.
                    </p>

                </div>

                <a
                    href="index.php"
                    class="sp-back"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Plans
                </a>

            </section>


            <section class="sp-form-card">

                <div class="sp-form-body">

                    <?php if ($error !== ''): ?>

                        <div class="alert alert-danger border-0 rounded-4 mb-4">
                            <i class="fa-solid fa-circle-exclamation me-2"></i>
                            <?= sp_add_escape($error) ?>
                        </div>

                    <?php endif; ?>


                    <form
                        method="post"
                        id="subscriptionPlanAddForm"
                        novalidate
                    >

                        <?= csrf_field() ?>


                        <div class="sp-form-grid">

                            <div class="sp-form-field">

                                <label for="name">
                                    Plan Name
                                    <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    maxlength="50"
                                    value="<?= sp_add_escape($name) ?>"
                                    placeholder="Example: Premium"
                                    required
                                >

                            </div>


                            <div class="sp-form-field">

                                <label for="duration_months">
                                    Duration in Months
                                    <span>*</span>
                                </label>

                                <input
                                    type="number"
                                    id="duration_months"
                                    name="duration_months"
                                    min="1"
                                    max="255"
                                    step="1"
                                    value="<?= sp_add_escape($duration_months) ?>"
                                    placeholder="Example: 3"
                                    required
                                >

                            </div>


                            <div class="sp-form-field">

                                <label for="price">
                                    Price
                                    <span>*</span>
                                </label>

                                <input
                                    type="number"
                                    id="price"
                                    name="price"
                                    min="0"
                                    max="99999999.99"
                                    step="0.01"
                                    value="<?= sp_add_escape($price) ?>"
                                    placeholder="Example: 999.00"
                                    required
                                >

                            </div>


                            <div class="sp-form-field">

                                <label for="status">
                                    Status
                                    <span>*</span>
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


                            <div class="sp-form-field full">

                                <label for="description">
                                    Description
                                </label>

                                <textarea
                                    id="description"
                                    name="description"
                                    maxlength="5000"
                                    placeholder="Write plan description..."
                                ><?= sp_add_escape($description) ?></textarea>

                            </div>


                            <div class="sp-form-field full">

                                <label for="benefits">
                                    Benefits
                                </label>

                                <textarea
                                    id="benefits"
                                    name="benefits"
                                    maxlength="5000"
                                    placeholder="Unlimited practice exams&#10;Premium study materials&#10;Performance analytics&#10;Live exam access"
                                ><?= sp_add_escape($benefits) ?></textarea>

                                <div class="sp-form-help">
                                    Enter one benefit per line.
                                </div>

                            </div>

                        </div>


                        <div class="sp-form-footer">

                            <a
                                href="index.php"
                                class="sp-cancel"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="sp-save"
                                id="createPlanButton"
                            >
                                <i class="fa-solid fa-plus"></i>
                                Create Plan
                            </button>

                        </div>

                    </form>

                </div>

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
                'subscriptionPlanAddForm'
            );

        const button =
            document.getElementById(
                'createPlanButton'
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
                    '<i class="fa-solid fa-spinner fa-spin"></i> Creating Plan...';
            }
        );
    }
);
</script>

<?php include "../includes/footer.php"; ?>