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

$page_title = 'Edit Category';

$error = '';

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $id === false ||
    $id === null ||
    $id <= 0
) {
    $_SESSION['error'] = 'Invalid category.';
    header('Location: index.php');
    exit;
}

try {

    $statement = $conn->prepare("
        SELECT
            id,
            category_name,
            description,
            icon,
            status,
            created_at
        FROM categories
        WHERE id = ?
        LIMIT 1
    ");

    $statement->execute([$id]);

    $category = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        $_SESSION['error'] = 'Category not found.';
        header('Location: index.php');
        exit;
    }

} catch (Throwable $exception) {

    error_log(
        'Category edit load failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to load the category.';

    header('Location: index.php');
    exit;
}

$categoryName = (string)$category['category_name'];
$description = (string)($category['description'] ?? '');
$icon = (string)$category['icon'];
$status = (string)$category['status'];

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
            (string)($_POST['status'] ?? '')
        );

        if ($categoryName === '') {

            $error =
                'Category name is required.';

        } elseif (
            mb_strlen($categoryName) > 120
        ) {

            $error =
                'Category name cannot exceed 120 characters.';

        } elseif ($icon === '') {

            $error =
                'Font Awesome icon is required.';

        } elseif (
            mb_strlen($icon) > 100
        ) {

            $error =
                'Icon value cannot exceed 100 characters.';

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
         * Validate the stored icon class.
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
         * Check duplicate category name.
         */
        if ($error === '') {

            try {

                $duplicate = $conn->prepare("
                    SELECT
                        id
                    FROM categories
                    WHERE category_name = ?
                      AND id <> ?
                    LIMIT 1
                ");

                $duplicate->execute([
                    $categoryName,
                    $id
                ]);

                if ($duplicate->fetch(PDO::FETCH_ASSOC)) {

                    $error =
                        'This category name already exists.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Category duplicate check failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to validate the category name.';
            }
        }

        /*
         * Update category.
         *
         * IMPORTANT:
         * categories has no updated_at column in the real schema.
         */
        if ($error === '') {

            try {

                $conn->beginTransaction();

                /*
                 * Lock the current record before updating it.
                 */
                $lock = $conn->prepare("
                    SELECT
                        id,
                        category_name,
                        description,
                        icon,
                        status
                    FROM categories
                    WHERE id = ?
                    FOR UPDATE
                ");

                $lock->execute([$id]);

                $lockedCategory =
                    $lock->fetch(PDO::FETCH_ASSOC);

                if (!$lockedCategory) {

                    throw new RuntimeException(
                        'Category no longer exists.'
                    );
                }

                /*
                 * Re-check the unique category name inside
                 * the transaction.
                 */
                $finalDuplicate = $conn->prepare("
                    SELECT
                        id
                    FROM categories
                    WHERE category_name = ?
                      AND id <> ?
                    LIMIT 1
                ");

                $finalDuplicate->execute([
                    $categoryName,
                    $id
                ]);

                if (
                    $finalDuplicate->fetch(
                        PDO::FETCH_ASSOC
                    )
                ) {

                    throw new RuntimeException(
                        'This category name already exists.'
                    );
                }

                $update = $conn->prepare("
                    UPDATE categories
                    SET
                        category_name = ?,
                        description = ?,
                        icon = ?,
                        status = ?
                    WHERE id = ?
                ");

                $update->execute([
                    $categoryName,
                    $description !== ''
                        ? $description
                        : null,
                    $icon,
                    $status,
                    $id
                ]);

                $conn->commit();

                examsphere_event_admin_academic(
                    $conn,
                    'category',
                    (int)$id,
                    $categoryName,
                    'updated'
                );

                $_SESSION['success'] =
                    'Category "' .
                    $categoryName .
                    '" updated successfully.';

                header('Location: index.php');
                exit;

            } catch (PDOException $exception) {

                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }

                error_log(
                    'Category update failed: ' .
                    $exception->getMessage()
                );

                if (
                    isset($exception->errorInfo[1]) &&
                    (int)$exception->errorInfo[1] === 1062
                ) {

                    $error =
                        'This category name already exists.';

                } else {

                    $error =
                        'Unable to update the category.';
                }

            } catch (Throwable $exception) {

                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }

                error_log(
                    'Category update failed: ' .
                    $exception->getMessage()
                );

                $error =
                    $exception->getMessage();
            }
        }
    }
}

function edit_category_e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function edit_category_date(?string $value): string
{
    if (!$value) {
        return 'Not available';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d M Y, h:i A', $timestamp)
        : 'Not available';
}

include "../includes/header.php";
?>

<style>

    .category-edit-page {
        max-width: 1050px;
        margin: 0 auto;
    }

    .category-edit-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 23px;
    }

    .category-edit-heading .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        color: #556b2f;
        font-size: .76rem;
        font-weight: 800;
        letter-spacing: .12em;
    }

    .category-edit-heading h1 {
        margin: 0;
        color: #333;
    }

    .category-edit-heading p {
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

    .category-edit-card {
        overflow: hidden;
        border-radius: 24px;
        border: 1px solid rgba(93,64,55,.10);
        background: rgba(255,255,255,.90);
        box-shadow: 0 20px 55px rgba(51,51,51,.08);
        backdrop-filter: blur(15px);
    }

    .category-edit-head {
        padding: 21px 23px;
        border-bottom: 1px solid rgba(93,64,55,.08);
        background: rgba(250,247,240,.65);
    }

    .category-edit-head span {
        display: block;
        margin-bottom: 4px;
        color: #556b2f;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .10em;
    }

    .category-edit-head h2 {
        margin: 0;
        color: #333;
        font-size: 1.08rem;
    }

    .category-edit-body {
        padding: 25px;
    }

    .category-edit-grid {
        display: grid;
        grid-template-columns: repeat(2,minmax(0,1fr));
        gap: 18px;
    }

    .category-edit-field.full {
        grid-column: 1 / -1;
    }

    .category-edit-field label {
        display: block;
        margin-bottom: 7px;
        color: #504945;
        font-size: .82rem;
        font-weight: 800;
    }

    .category-edit-field input,
    .category-edit-field select,
    .category-edit-field textarea {
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

    .category-edit-field textarea {
        min-height: 130px;
        resize: vertical;
    }

    .category-edit-field input:focus,
    .category-edit-field select:focus,
    .category-edit-field textarea:focus {
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

    .readonly-box {
        min-height: 44px;
        display: flex;
        align-items: center;
        padding: 10px 13px;
        border-radius: 12px;
        border: 1px solid rgba(93,64,55,.09);
        background: #faf8f4;
        color: #645c57;
        font-weight: 700;
    }

    .icon-editor {
        display: grid;
        grid-template-columns: minmax(0,1fr) 72px;
        gap: 10px;
    }

    .icon-preview {
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        border: 1px solid rgba(93,64,55,.12);
        background: #faf8f4;
        color: #556b2f;
        font-size: 1.25rem;
    }

    .category-edit-footer {
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

        .category-edit-heading {
            align-items: flex-start;
            flex-direction: column;
        }

        .category-back {
            width: 100%;
        }

        .category-edit-grid {
            grid-template-columns: 1fr;
        }

        .category-edit-field.full {
            grid-column: auto;
        }

        .icon-editor {
            grid-template-columns: 1fr;
        }

        .category-edit-footer {
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

        <main class="dashboard-content category-edit-page">

            <section class="category-edit-heading">

                <div>

                    <span class="eyebrow">
                        <i class="fa-solid fa-pen-to-square"></i>
                        ACADEMIC STRUCTURE
                    </span>

                    <h1>Edit Category</h1>

                    <p>
                        Update the category information and display settings.
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

            <section class="category-edit-card">

                <header class="category-edit-head">

                    <span>CATEGORY CONFIGURATION</span>

                    <h2>
                        <?= edit_category_e(
                            $category['category_name']
                        ) ?>
                    </h2>

                </header>

                <div class="category-edit-body">

                    <?php if ($error !== ''): ?>

                        <div class="alert alert-danger border-0 rounded-4">

                            <i class="fa-solid fa-circle-exclamation me-2"></i>

                            <?= edit_category_e($error) ?>

                        </div>

                    <?php endif; ?>

                    <form
                        method="post"
                        autocomplete="off"
                        novalidate
                        id="categoryEditForm"
                    >

                        <?= csrf_field() ?>

                        <div class="category-edit-grid">

                            <div class="category-edit-field">

                                <label>
                                    Category ID
                                </label>

                                <div class="readonly-box">
                                    #<?= (int)$category['id'] ?>
                                </div>

                                <small class="category-help">
                                    Category ID cannot be changed.
                                </small>

                            </div>

                            <div class="category-edit-field">

                                <label>
                                    Created On
                                </label>

                                <div class="readonly-box">
                                    <?= edit_category_e(
                                        edit_category_date(
                                            $category['created_at']
                                        )
                                    ) ?>
                                </div>

                            </div>

                            <div class="category-edit-field">

                                <label for="category_name">

                                    Category Name

                                    <span class="text-danger">*</span>

                                </label>

                                <input
                                    id="category_name"
                                    type="text"
                                    name="category_name"
                                    value="<?= edit_category_e(
                                        $categoryName
                                    ) ?>"
                                    maxlength="120"
                                    required
                                >

                                <small class="category-help">
                                    Maximum 120 characters. Category names
                                    must be unique.
                                </small>

                            </div>

                            <div class="category-edit-field">

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

                            <div class="category-edit-field">

                                <label for="icon">

                                    Font Awesome Icon

                                    <span class="text-danger">*</span>

                                </label>

                                <div class="icon-editor">

                                    <input
                                        id="icon"
                                        type="text"
                                        name="icon"
                                        value="<?= edit_category_e(
                                            $icon
                                        ) ?>"
                                        maxlength="100"
                                        placeholder="fa-solid fa-book"
                                        required
                                    >

                                    <div
                                        class="icon-preview"
                                        id="iconPreview"
                                    >
                                        <i
                                            id="previewIcon"
                                            class="<?= edit_category_e(
                                                $icon
                                            ) ?>"
                                        ></i>
                                    </div>

                                </div>

                                <small class="category-help">
                                    Example:
                                    <code>fa-solid fa-book</code>
                                </small>

                            </div>

                            <div class="category-edit-field">

                                <label>
                                    Icon Preview
                                </label>

                                <div class="readonly-box">

                                    <i
                                        id="largePreviewIcon"
                                        class="<?= edit_category_e(
                                            $icon
                                        ) ?>"
                                        style="
                                            margin-right:9px;
                                            color:#556b2f;
                                            font-size:1.2rem;
                                        "
                                    ></i>

                                    <span>
                                        Live icon preview
                                    </span>

                                </div>

                            </div>

                            <div class="category-edit-field full">

                                <label for="description">
                                    Description
                                </label>

                                <textarea
                                    id="description"
                                    name="description"
                                    maxlength="65535"
                                    placeholder="Enter category description..."
                                ><?= edit_category_e(
                                    $description
                                ) ?></textarea>

                            </div>

                        </div>

                        <div class="category-edit-footer">

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
                                <i class="fa-solid fa-floppy-disk"></i>
                                Update Category
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
                    'categoryEditForm'
                );

            const button =
                document.getElementById(
                    'categorySaveButton'
                );

            function updatePreview() {

                const value =
                    iconInput
                        ? iconInput.value.trim()
                        : '';

                const fallback =
                    'fa-solid fa-book';

                const iconClass =
                    value !== ''
                        ? value
                        : fallback;

                if (previewIcon) {
                    previewIcon.className =
                        iconClass;
                }

                if (largePreviewIcon) {
                    largePreviewIcon.className =
                        iconClass;
                }
            }

            if (iconInput) {

                iconInput.addEventListener(
                    'input',
                    updatePreview
                );

                updatePreview();
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
                            'Updating Category...';
                    }
                );
            }

        }
    );

</script>

<?php include "../includes/footer.php"; ?>