<?php
declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: ../../auth/login.php');
    exit;
}

$page_title = 'Edit Subject';

$error = '';
$categories = [];

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null || $id <= 0) {
    $_SESSION['error'] = 'Invalid subject.';
    header('Location: index.php');
    exit;
}

/*
 * Confirm that category_id exists in the installed database.
 */
$hasCategoryRelation = false;

try {

    $columnCheck = $conn->query("
        SHOW COLUMNS
        FROM subjects
        LIKE 'category_id'
    ");

    $hasCategoryRelation =
        (bool)$columnCheck->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $exception) {

    error_log(
        'Subject category relation check failed: ' .
        $exception->getMessage()
    );
}

if (!$hasCategoryRelation) {

    $_SESSION['error'] =
        'The Subject → Category relationship is not available.';

    header('Location: index.php');
    exit;
}

/*
 * Load current subject.
 */
try {

    $statement = $conn->prepare("
        SELECT
            id,
            category_id,
            name,
            code,
            description,
            status
        FROM subjects
        WHERE id = ?
        LIMIT 1
    ");

    $statement->execute([$id]);

    $subject = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$subject) {
        $_SESSION['error'] = 'Subject not found.';
        header('Location: index.php');
        exit;
    }

} catch (Throwable $exception) {

    error_log(
        'Subject edit load failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to load the subject.';

    header('Location: index.php');
    exit;
}

/*
 * Initial form values.
 */
$categoryId = (int)$subject['category_id'];
$code = (string)($subject['code'] ?? '');
$name = (string)$subject['name'];
$description = (string)($subject['description'] ?? '');
$status = (string)$subject['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $error =
            'Security verification failed. Please refresh the page and try again.';

    } else {

        $categoryId = filter_var(
            $_POST['category_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $code = strtoupper(
            trim(
                (string)($_POST['code'] ?? '')
            )
        );

        $name = trim(
            (string)($_POST['name'] ?? '')
        );

        $description = trim(
            (string)($_POST['description'] ?? '')
        );

        $status = trim(
            (string)($_POST['status'] ?? '')
        );

        if (
            $categoryId === false ||
            $categoryId === null ||
            $categoryId <= 0
        ) {

            $error =
                'Please select a valid category.';

        } elseif ($code === '') {

            $error =
                'Subject code is required.';

        } elseif ($name === '') {

            $error =
                'Subject name is required.';

        } elseif (
            mb_strlen($code) > 30
        ) {

            $error =
                'Subject code cannot exceed 30 characters.';

        } elseif (
            mb_strlen($name) > 120
        ) {

            $error =
                'Subject name cannot exceed 120 characters.';

        } elseif (
            mb_strlen($description) > 65535
        ) {

            $error =
                'Subject description is too long.';

        } elseif (
            !in_array(
                $status,
                ['Active', 'Inactive'],
                true
            )
        ) {

            $error =
                'Invalid subject status.';
        }

        /*
         * Verify the selected category is active.
         */
        if ($error === '') {

            try {

                $categoryCheck = $conn->prepare("
                    SELECT
                        id,
                        category_name
                    FROM categories
                    WHERE id = ?
                      AND status = 'Active'
                    LIMIT 1
                ");

                $categoryCheck->execute([
                    $categoryId
                ]);

                $category =
                    $categoryCheck->fetch(
                        PDO::FETCH_ASSOC
                    );

                if (!$category) {

                    $error =
                        'The selected category does not exist or is inactive.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Subject category validation failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to validate the selected category.';
            }
        }

        /*
         * Verify unique subject code.
         */
        if ($error === '') {

            try {

                $codeCheck = $conn->prepare("
                    SELECT
                        id
                    FROM subjects
                    WHERE code = ?
                      AND id <> ?
                    LIMIT 1
                ");

                $codeCheck->execute([
                    $code,
                    $id
                ]);

                if ($codeCheck->fetch(PDO::FETCH_ASSOC)) {

                    $error =
                        'This subject code already exists.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Subject code check failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to validate the subject code.';
            }
        }

        /*
         * Verify unique subject name.
         */
        if ($error === '') {

            try {

                $nameCheck = $conn->prepare("
                    SELECT
                        id
                    FROM subjects
                    WHERE name = ?
                      AND id <> ?
                    LIMIT 1
                ");

                $nameCheck->execute([
                    $name,
                    $id
                ]);

                if ($nameCheck->fetch(PDO::FETCH_ASSOC)) {

                    $error =
                        'This subject name already exists.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Subject name check failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to validate the subject name.';
            }
        }

        /*
         * Perform update.
         */
        if ($error === '') {

            try {

                $conn->beginTransaction();

                /*
                 * Lock the subject while checking and updating it.
                 */
                $lock = $conn->prepare("
                    SELECT
                        id,
                        category_id,
                        name,
                        code,
                        status
                    FROM subjects
                    WHERE id = ?
                    FOR UPDATE
                ");

                $lock->execute([$id]);

                $lockedSubject =
                    $lock->fetch(PDO::FETCH_ASSOC);

                if (!$lockedSubject) {

                    throw new RuntimeException(
                        'Subject no longer exists.'
                    );
                }

                /*
                 * Re-check unique code inside the transaction.
                 */
                $finalCodeCheck = $conn->prepare("
                    SELECT
                        id
                    FROM subjects
                    WHERE code = ?
                      AND id <> ?
                    LIMIT 1
                ");

                $finalCodeCheck->execute([
                    $code,
                    $id
                ]);

                if ($finalCodeCheck->fetch(PDO::FETCH_ASSOC)) {

                    throw new RuntimeException(
                        'Subject code already exists.'
                    );
                }

                /*
                 * Re-check unique name inside the transaction.
                 */
                $finalNameCheck = $conn->prepare("
                    SELECT
                        id
                    FROM subjects
                    WHERE name = ?
                      AND id <> ?
                    LIMIT 1
                ");

                $finalNameCheck->execute([
                    $name,
                    $id
                ]);

                if ($finalNameCheck->fetch(PDO::FETCH_ASSOC)) {

                    throw new RuntimeException(
                        'Subject name already exists.'
                    );
                }

                $update = $conn->prepare("
                    UPDATE subjects
                    SET
                        category_id = ?,
                        name = ?,
                        code = ?,
                        description = ?,
                        status = ?
                    WHERE id = ?
                ");

                $update->execute([
                    $categoryId,
                    $name,
                    $code !== ''
                        ? $code
                        : null,
                    $description !== ''
                        ? $description
                        : null,
                    $status,
                    $id
                ]);

                if ($update->rowCount() < 0) {
                    throw new RuntimeException(
                        'Subject update failed.'
                    );
                }

                $conn->commit();

                $_SESSION['success'] =
                    'Subject "' .
                    $name .
                    '" updated successfully.';

                header('Location: view.php?id=' . $id);
                exit;

            } catch (PDOException $exception) {

                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }

                error_log(
                    'Subject update failed: ' .
                    $exception->getMessage()
                );

                if (
                    isset($exception->errorInfo[1]) &&
                    (int)$exception->errorInfo[1] === 1062
                ) {

                    $error =
                        'Subject name or subject code already exists.';

                } else {

                    $error =
                        'Unable to update the subject.';
                }

            } catch (Throwable $exception) {

                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }

                error_log(
                    'Subject update failed: ' .
                    $exception->getMessage()
                );

                $error =
                    $exception->getMessage();
            }
        }
    }
}

/*
 * Load active categories for dropdown.
 *
 * Keep the currently selected category available even if it has
 * become inactive, so the existing record is not silently changed.
 */
try {

    $categoryStatement = $conn->prepare("
        SELECT
            id,
            category_name,
            status
        FROM categories
        WHERE status = 'Active'
           OR id = ?
        ORDER BY
            CASE
                WHEN id = ? THEN 0
                ELSE 1
            END,
            category_name ASC,
            id ASC
    ");

    $categoryStatement->execute([
        $categoryId,
        $categoryId
    ]);

    $categories =
        $categoryStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Subject categories loading failed: ' .
        $exception->getMessage()
    );

    $categories = [];

    if ($error === '') {
        $error =
            'Unable to load categories.';
    }
}

function edit_subject_e(mixed $value): string
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

    .subject-edit-page {
        max-width: 1050px;
        margin: 0 auto;
    }

    .subject-edit-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 20px;
        margin-bottom: 23px;
    }

    .subject-edit-heading .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        color: #556b2f;
        font-size: .76rem;
        font-weight: 800;
        letter-spacing: .12em;
    }

    .subject-edit-heading h1 {
        margin: 0;
        color: #333;
    }

    .subject-edit-heading p {
        margin: 6px 0 0;
        color: #746d68;
    }

    .subject-back {
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
        transition: .2s ease;
        white-space: nowrap;
    }

    .subject-back:hover {
        background: #5d4037;
        border-color: #5d4037;
        color: #fff;
        transform: translateY(-2px);
    }

    .subject-edit-card {
        overflow: hidden;
        border-radius: 24px;
        border: 1px solid rgba(93,64,55,.10);
        background: rgba(255,255,255,.90);
        box-shadow: 0 20px 55px rgba(51,51,51,.08);
        backdrop-filter: blur(15px);
    }

    .subject-edit-head {
        padding: 21px 23px;
        border-bottom: 1px solid rgba(93,64,55,.08);
        background: rgba(250,247,240,.65);
    }

    .subject-edit-head span {
        display: block;
        margin-bottom: 4px;
        color: #556b2f;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .10em;
    }

    .subject-edit-head h2 {
        margin: 0;
        color: #333;
        font-size: 1.08rem;
    }

    .subject-edit-body {
        padding: 25px;
    }

    .subject-edit-grid {
        display: grid;
        grid-template-columns: repeat(2,minmax(0,1fr));
        gap: 18px;
    }

    .subject-edit-field.full {
        grid-column: 1 / -1;
    }

    .subject-edit-field label {
        display: block;
        margin-bottom: 7px;
        color: #504945;
        font-size: .82rem;
        font-weight: 800;
    }

    .subject-edit-field input,
    .subject-edit-field select,
    .subject-edit-field textarea {
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

    .subject-edit-field textarea {
        min-height: 130px;
        resize: vertical;
    }

    .subject-edit-field input:focus,
    .subject-edit-field select:focus,
    .subject-edit-field textarea:focus {
        border-color: #556b2f;
        box-shadow: 0 0 0 3px rgba(85,107,47,.10);
    }

    .subject-help {
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

    .inactive-category-warning {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-top: 8px;
        padding: 9px 11px;
        border-radius: 10px;
        background: rgba(154,107,22,.08);
        color: #785719;
        font-size: .75rem;
    }

    .subject-edit-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 23px;
        padding-top: 22px;
        border-top: 1px solid rgba(93,64,55,.08);
    }

    .subject-cancel-btn,
    .subject-save-btn {
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

    .subject-cancel-btn {
        background: rgba(93,64,55,.08);
        color: #5d4037;
    }

    .subject-cancel-btn:hover {
        background: rgba(93,64,55,.14);
        color: #5d4037;
    }

    .subject-save-btn {
        border: 0;
        background: #556b2f;
        color: #fff;
        cursor: pointer;
        box-shadow: 0 10px 25px rgba(85,107,47,.18);
    }

    .subject-save-btn:hover {
        background: #465b27;
        color: #fff;
    }

    .subject-save-btn:disabled {
        opacity: .7;
        cursor: not-allowed;
        box-shadow: none;
    }

    @media (max-width: 760px) {

        .subject-edit-heading {
            align-items: flex-start;
            flex-direction: column;
        }

        .subject-back {
            width: 100%;
        }

        .subject-edit-grid {
            grid-template-columns: 1fr;
        }

        .subject-edit-field.full {
            grid-column: auto;
        }

        .subject-edit-footer {
            align-items: stretch;
            flex-direction: column-reverse;
        }

        .subject-cancel-btn,
        .subject-save-btn {
            width: 100%;
        }

    }

</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content subject-edit-page">

            <section class="subject-edit-heading">

                <div>

                    <span class="eyebrow">
                        <i class="fa-solid fa-book-pen"></i>
                        ACADEMIC STRUCTURE
                    </span>

                    <h1>Edit Subject</h1>

                    <p>
                        Update the subject category, identity, description
                        and status.
                    </p>

                </div>

                <a
                    href="view.php?id=<?= (int)$subject['id'] ?>"
                    class="subject-back"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Subject
                </a>

            </section>

            <section class="subject-edit-card">

                <header class="subject-edit-head">

                    <span>SUBJECT CONFIGURATION</span>

                    <h2>
                        <?= edit_subject_e(
                            $subject['name']
                        ) ?>
                    </h2>

                </header>

                <div class="subject-edit-body">

                    <?php if ($error !== ''): ?>

                        <div class="alert alert-danger border-0 rounded-4">

                            <i class="fa-solid fa-circle-exclamation me-2"></i>

                            <?= edit_subject_e($error) ?>

                        </div>

                    <?php endif; ?>

                    <form
                        method="post"
                        autocomplete="off"
                        novalidate
                        id="subjectEditForm"
                    >

                        <?= csrf_field() ?>

                        <div class="subject-edit-grid">

                            <div class="subject-edit-field">

                                <label for="category_id">
                                    Category
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    id="category_id"
                                    name="category_id"
                                    required
                                >

                                    <option value="">
                                        Select Category
                                    </option>

                                    <?php foreach (
                                        $categories
                                        as $category
                                    ): ?>

                                        <option
                                            value="<?= (int)$category['id'] ?>"
                                            <?= (string)$categoryId ===
                                                (string)$category['id']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= edit_subject_e(
                                                $category['category_name']
                                            ) ?>

                                            <?php if (
                                                $category['status']
                                                !== 'Active'
                                            ): ?>

                                                —
                                                Inactive

                                            <?php endif; ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <?php
                                $selectedCategoryStatus = null;

                                foreach (
                                    $categories
                                    as $category
                                ) {
                                    if (
                                        (int)$category['id']
                                        === (int)$categoryId
                                    ) {
                                        $selectedCategoryStatus =
                                            (string)$category['status'];
                                        break;
                                    }
                                }
                                ?>

                                <?php if (
                                    $selectedCategoryStatus ===
                                    'Inactive'
                                ): ?>

                                    <div class="inactive-category-warning">

                                        <i class="fa-solid fa-triangle-exclamation mt-1"></i>

                                        <span>
                                            This subject is currently linked
                                            to an inactive category. Select an
                                            active category before saving.
                                        </span>

                                    </div>

                                <?php endif; ?>

                            </div>

                            <div class="subject-edit-field">

                                <label>
                                    Subject ID
                                </label>

                                <div class="readonly-box">
                                    #<?= (int)$subject['id'] ?>
                                </div>

                                <small class="subject-help">
                                    The subject ID cannot be changed.
                                </small>

                            </div>

                            <div class="subject-edit-field">

                                <label for="code">
                                    Subject Code
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    id="code"
                                    type="text"
                                    name="code"
                                    value="<?= edit_subject_e($code) ?>"
                                    maxlength="30"
                                    required
                                >

                                <small class="subject-help">
                                    Maximum 30 characters and must be unique.
                                </small>

                            </div>

                            <div class="subject-edit-field">

                                <label for="name">
                                    Subject Name
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    id="name"
                                    type="text"
                                    name="name"
                                    value="<?= edit_subject_e($name) ?>"
                                    maxlength="120"
                                    required
                                >

                                <small class="subject-help">
                                    Subject names are unique.
                                </small>

                            </div>

                            <div class="subject-edit-field">

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

                            <div class="subject-edit-field full">

                                <label for="description">
                                    Description
                                </label>

                                <textarea
                                    id="description"
                                    name="description"
                                    maxlength="65535"
                                    placeholder="Enter subject description..."
                                ><?= edit_subject_e(
                                    $description
                                ) ?></textarea>

                            </div>

                        </div>

                        <div class="subject-edit-footer">

                            <a
                                href="view.php?id=<?= (int)$subject['id'] ?>"
                                class="subject-cancel-btn"
                            >
                                <i class="fa-solid fa-xmark"></i>
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="subject-save-btn"
                                id="subjectSaveButton"
                            >
                                <i class="fa-solid fa-floppy-disk"></i>
                                Update Subject
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
                    'subjectEditForm'
                );

            const code =
                document.getElementById(
                    'code'
                );

            const button =
                document.getElementById(
                    'subjectSaveButton'
                );

            if (code) {

                code.addEventListener(
                    'input',
                    function () {

                        code.value =
                            code.value.toUpperCase();

                    }
                );
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
                            'Updating Subject...';

                    }
                );
            }

        }
    );

</script>

<?php include "../includes/footer.php"; ?>