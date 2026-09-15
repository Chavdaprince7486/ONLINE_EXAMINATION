<?php
declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";
require_once "../../config/notification_events.php";

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: ../../auth/login.php');
    exit;
}

$page_title = 'Add Category';

$error = '';

$categoryName = '';
$description = '';
$icon = 'fa-solid fa-book';
$status = 'Active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $error =
            'Security verification failed. Please refresh the page and try again.';

    } else {

        $categoryName = trim(
            (string)($_POST['category_name'] ?? '')
        );

        $description = trim(
            (string)($_POST['description'] ?? '')
        );

        $icon = trim(
            (string)($_POST['icon'] ?? '')
        );

        $status = trim(
            (string)($_POST['status'] ?? 'Active')
        );

        if ($categoryName === '') {

            $error =
                'Category name is required.';

        } elseif (
            mb_strlen($categoryName) > 100
        ) {

            $error =
                'Category name cannot exceed 100 characters.';

        } elseif ($icon === '') {

            $error =
                'Font Awesome icon is required.';

        } elseif (
            mb_strlen($icon) > 150
        ) {

            $error =
                'Icon value cannot exceed 150 characters.';

        } elseif (
            mb_strlen($description) > 65535
        ) {

            $error =
                'Category description is too long.';

        } elseif (
            !in_array(
                $status,
                ['Active', 'Inactive'],
                true
            )
        ) {

            $error =
                'Invalid category status.';
        }

        /*
         * Basic Font Awesome class validation.
         *
         * The application stores class names such as:
         * fa-solid fa-book
         */
        if ($error === '') {

            if (
                !preg_match(
                    '/^(?:fa-(?:solid|regular|brands|light|thin|duotone)(?:\s+|$))?[a-zA-Z0-9_-]+(?:\s+[a-zA-Z0-9_-]+)*$/',
                    $icon
                )
            ) {

                $error =
                    'Please enter a valid Font Awesome icon class.';
            }
        }

        /*
         * Duplicate category check.
         */
        if ($error === '') {

            try {

                $check = $conn->prepare("
                    SELECT
                        id
                    FROM categories
                    WHERE category_name = ?
                    LIMIT 1
                ");

                $check->execute([
                    $categoryName
                ]);

                if ($check->fetch(PDO::FETCH_ASSOC)) {

                    $error =
                        'This category already exists.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Category duplicate check failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to validate the category right now.';
            }
        }

        /*
         * Insert category.
         */
        if ($error === '') {

            try {

                $insert = $conn->prepare("
                    INSERT INTO categories
                    (
                        category_name,
                        description,
                        icon,
                        status
                    )
                    VALUES
                    (
                        ?, ?, ?, ?
                    )
                ");

                $insert->execute([
                    $categoryName,
                    $description !== ''
                        ? $description
                        : null,
                    $icon,
                    $status
                ]);

                examsphere_event_admin_academic(
                    $conn,
                    'category',
                    (int)$conn->lastInsertId(),
                    $categoryName,
                    'added'
                );

                $_SESSION['success'] =
                    'Category "' .
                    $categoryName .
                    '" created successfully.';

                header('Location: index.php');
                exit;

            } catch (PDOException $exception) {

                error_log(
                    'Category creation failed: ' .
                    $exception->getMessage()
                );

                if (
                    isset($exception->errorInfo[1]) &&
                    (int)$exception->errorInfo[1] === 1062
                ) {

                    $error =
                        'This category already exists.';

                } else {

                    $error =
                        'Unable to create the category.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Category creation failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to create the category.';
            }
        }
    }
}

function add_category_e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

include "../includes/header.php";
?>

<style>

    .category-add-page {
        max-width: 1050px;
        margin: 0 auto;
    }

    .category-add-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 23px;
    }

    .category-add-heading .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        color: #556b2f;
        font-size: .76rem;
        font-weight: 800;
        letter-spacing: .12em;
    }

    .category-add-heading h1 {
        margin: 0;
        color: #333;
    }

    .category-add-heading p {
        margin: 6px 0 0;
        color: #746d68;
    }

    .category-back {
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 15px;
        border-radius: 12px;
        border: 1px solid rgba(93,64,55,.12);
        background: rgba(255,255,255,.88);
        color: #5d4037;
        text-decoration: none;
        font-weight: 800;
        white-space: nowrap;
        transition: .2s ease;
    }

    .category-back:hover {
        background: #5d4037;
        border-color: #5d4037;
        color: #fff;
        transform: translateY(-2px);
    }

    .category-form-card {
        overflow: hidden;
        border-radius: 24px;
        border: 1px solid rgba(93,64,55,.10);
        background: rgba(255,255,255,.90);
        box-shadow: 0 20px 55px rgba(51,51,51,.08);
        backdrop-filter: blur(15px);
    }

    .category-form-head {
        padding: 21px 23px;
        border-bottom: 1px solid rgba(93,64,55,.08);
        background: rgba(250,247,240,.65);
    }

    .category-form-head span {
        display: block;
        margin-bottom: 4px;
        color: #556b2f;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .10em;
    }

    .category-form-head h2 {
        margin: 0;
        color: #333;
        font-size: 1.08rem;
    }

    .category-form-body {
        padding: 25px;
    }

    .category-form-grid {
        display: grid;
        grid-template-columns: repeat(2,minmax(0,1fr));
        gap: 18px;
    }

    .category-form-field.full {
        grid-column: 1 / -1;
    }

    .category-form-field label {
        display: block;
        margin-bottom: 7px;
        color: #504945;
        font-size: .82rem;
        font-weight: 800;
    }

    .category-form-field input,
    .category-form-field select,
    .category-form-field textarea {
        width: 100%;
        padding: 11px 13px;
        border: 1px solid rgba(93,64,55,.15);
        border-radius: 12px;
        background: #fff;
        color: #34302e;
        font: inherit;
        outline: none;
        transition: .2s ease;
    }

    .category-form-field textarea {
        min-height: 130px;
        resize: vertical;
    }

    .category-form-field input:focus,
    .category-form-field select:focus,
    .category-form-field textarea:focus {
        border-color: #556b2f;
        box-shadow: 0 0 0 3px rgba(85,107,47,.10);
    }

    .category-help {
        display: block;
        margin-top: 5px;
        color: #817973;
        font-size: .73rem;
        line-height: 1.45;
    }

    .icon-editor {
        display: grid;
        grid-template-columns: minmax(0,1fr) 120px;
        gap: 12px;
        align-items: stretch;
    }

    .icon-preview {
        min-height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        border: 1px solid rgba(93,64,55,.12);
        background: #faf8f4;
        color: #556b2f;
        font-size: 1.35rem;
    }

    .category-form-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 23px;
        padding-top: 22px;
        border-top: 1px solid rgba(93,64,55,.08);
    }

    .category-cancel-btn,
    .category-save-btn {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 18px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 800;
    }

    .category-cancel-btn {
        background: rgba(93,64,55,.08);
        color: #5d4037;
    }

    .category-cancel-btn:hover {
        background: rgba(93,64,55,.14);
        color: #5d4037;
    }

    .category-save-btn {
        border: 0;
        background: #556b2f;
        color: #fff;
        cursor: pointer;
        box-shadow: 0 10px 25px rgba(85,107,47,.18);
    }

    .category-save-btn:hover {
        background: #465b27;
        color: #fff;
    }

    .category-save-btn:disabled {
        opacity: .7;
        cursor: not-allowed;
        box-shadow: none;
    }

    @media (max-width: 760px) {

        .category-add-heading {
            align-items: flex-start;
            flex-direction: column;
        }

        .category-back {
            width: 100%;
        }

        .category-form-grid {
            grid-template-columns: 1fr;
        }

        .category-form-field.full {
            grid-column: auto;
        }

        .icon-editor {
            grid-template-columns: 1fr;
        }

        .category-form-footer {
            align-items: stretch;
            flex-direction: column-reverse;
        }

        .category-cancel-btn,
        .category-save-btn {
            width: 100%;
        }

    }

</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content category-add-page">

            <section class="category-add-heading">

                <div>

                    <span class="eyebrow">
                        <i class="fa-solid fa-folder-plus"></i>
                        ACADEMIC STRUCTURE
                    </span>

                    <h1>Add Category</h1>

                    <p>
                        Create a new examination category for ExamSphere.
                    </p>

                </div>

                <a
                    href="index.php"
                    class="category-back"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Categories
                </a>

            </section>

            <section class="category-form-card">

                <header class="category-form-head">

                    <span>CATEGORY CONFIGURATION</span>

                    <h2>Create Category</h2>

                </header>

                <div class="category-form-body">

                    <?php if ($error !== ''): ?>

                        <div class="alert alert-danger border-0 rounded-4">

                            <i class="fa-solid fa-circle-exclamation me-2"></i>

                            <?= add_category_e($error) ?>

                        </div>

                    <?php endif; ?>

                    <form
                        method="post"
                        autocomplete="off"
                        novalidate
                        id="categoryAddForm"
                    >

                        <?= csrf_field() ?>

                        <div class="category-form-grid">

                            <div class="category-form-field">

                                <label for="category_name">
                                    Category Name
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    id="category_name"
                                    type="text"
                                    name="category_name"
                                    maxlength="100"
                                    value="<?= add_category_e(
                                        $categoryName
                                    ) ?>"
                                    placeholder="Example: Competitive Exams"
                                    required
                                >

                                <small class="category-help">
                                    Maximum 100 characters.
                                </small>

                            </div>

                            <div class="category-form-field">

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

                            <div class="category-form-field">

                                <label for="icon">
                                    Font Awesome Icon
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="icon-editor">

                                    <input
                                        id="icon"
                                        type="text"
                                        name="icon"
                                        maxlength="150"
                                        value="<?= add_category_e(
                                            $icon
                                        ) ?>"
                                        placeholder="fa-solid fa-book"
                                        required
                                    >

                                    <div
                                        class="icon-preview"
                                        aria-label="Icon preview"
                                    >
                                        <i id="previewIcon"></i>
                                    </div>

                                </div>

                                <small class="category-help">
                                    Example:
                                    <code>fa-solid fa-book</code>
                                </small>

                            </div>

                            <div class="category-form-field">

                                <label>
                                    Preview
                                </label>

                                <div
                                    class="readonly-box"
                                    style="
                                        min-height:44px;
                                        display:flex;
                                        align-items:center;
                                        padding:10px 13px;
                                        border:1px solid rgba(93,64,55,.09);
                                        border-radius:12px;
                                        background:#faf8f4;
                                        color:#655d58;
                                    "
                                >
                                    <i
                                        id="largePreviewIcon"
                                        class="<?= add_category_e(
                                            $icon
                                        ) ?>"
                                        style="
                                            margin-right:9px;
                                            font-size:1.2rem;
                                            color:#556b2f;
                                        "
                                    ></i>

                                    <span>
                                        Live icon preview
                                    </span>
                                </div>

                            </div>

                            <div class="category-form-field full">

                                <label for="description">
                                    Description
                                </label>

                                <textarea
                                    id="description"
                                    name="description"
                                    maxlength="65535"
                                    placeholder="Enter category description..."
                                ><?= add_category_e(
                                    $description
                                ) ?></textarea>

                                <small class="category-help">
                                    Add a short explanation of the type of
                                    exams contained in this category.
                                </small>

                            </div>

                        </div>

                        <div class="category-form-footer">

                            <a
                                href="index.php"
                                class="category-cancel-btn"
                            >
                                <i class="fa-solid fa-xmark"></i>
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="category-save-btn"
                                id="categorySaveButton"
                            >
                                <i class="fa-solid fa-folder-plus"></i>
                                Create Category
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

            const iconInput =
                document.getElementById('icon');

            const previewIcon =
                document.getElementById('previewIcon');

            const largePreviewIcon =
                document.getElementById(
                    'largePreviewIcon'
                );

            const form =
                document.getElementById(
                    'categoryAddForm'
                );

            const button =
                document.getElementById(
                    'categorySaveButton'
                );

            function updateIconPreview() {

                if (!iconInput) {
                    return;
                }

                const iconValue =
                    iconInput.value.trim();

                if (previewIcon) {

                    previewIcon.className =
                        iconValue !== ''
                            ? iconValue
                            : 'fa-solid fa-book';
                }

                if (largePreviewIcon) {

                    largePreviewIcon.className =
                        iconValue !== ''
                            ? iconValue
                            : 'fa-solid fa-book';
                }
            }

            if (iconInput) {

                iconInput.addEventListener(
                    'input',
                    updateIconPreview
                );

                updateIconPreview();
            }

            if (form && button) {

                form.addEventListener(
                    'submit',
                    function () {

                        if (!form.checkValidity()) {
                            return;
                        }

                        button.disabled = true;

                        button.innerHTML =
                            '<i class="fa-solid fa-spinner fa-spin"></i> ' +
                            'Creating Category...';
                    }
                );
            }

        }
    );

</script>

<?php include "../includes/footer.php"; ?>