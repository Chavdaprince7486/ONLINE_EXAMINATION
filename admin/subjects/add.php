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

$page_title = 'Add Subject';

$error = '';

$categoryId = '';
$code = '';
$name = '';
$description = '';
$status = 'Active';

$hasCategoryRelation = false;
$categories = [];

/*
 * Confirm that the installed subjects table contains category_id.
 */
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
        'Subject category relationship check failed: ' .
        $exception->getMessage()
    );

    $error =
        'Unable to verify the subject category structure.';
}

/*
 * Process form.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $error =
            'Security verification failed. Please refresh the page and try again.';

    } else {

        $rawCategoryId =
            $_POST['category_id'] ?? '';

        $categoryId = filter_var(
            $rawCategoryId,
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
            (string)($_POST['status'] ?? 'Active')
        );

        /*
         * Validate migration/category requirement.
         */
        if (!$hasCategoryRelation) {

            $error =
                'The Subject → Category relationship is not available. ' .
                'Apply the project category migration first.';

        } elseif (
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
         * Verify selected category.
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
         * Check globally unique subject code.
         *
         * The database allows NULL for code, but this form requires
         * a code because ExamSphere uses it as the subject identifier.
         */
        if ($error === '') {

            try {

                $codeCheck = $conn->prepare("
                    SELECT id
                    FROM subjects
                    WHERE code = ?
                    LIMIT 1
                ");

                $codeCheck->execute([
                    $code
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
         * The database defines subject name as UNIQUE.
         */
        if ($error === '') {

            try {

                $nameCheck = $conn->prepare("
                    SELECT id
                    FROM subjects
                    WHERE name = ?
                    LIMIT 1
                ");

                $nameCheck->execute([
                    $name
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
         * Insert.
         */
        if ($error === '') {

            try {

                $insert = $conn->prepare("
                    INSERT INTO subjects
                    (
                        category_id,
                        name,
                        code,
                        description,
                        status
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?
                    )
                ");

                $insert->execute([
                    $categoryId,
                    $name,
                    $code !== '' ? $code : null,
                    $description !== ''
                        ? $description
                        : null,
                    $status
                ]);

                examsphere_event_admin_academic(
                    $conn,
                    'subject',
                    (int)$conn->lastInsertId(),
                    $name,
                    'added'
                );

                $_SESSION['success'] =
                    'Subject "' .
                    $name .
                    '" created successfully.';

                header('Location: index.php');
                exit;

            } catch (PDOException $exception) {

                error_log(
                    'Subject creation failed: ' .
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
                        'Unable to create the subject.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Subject creation failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to create the subject.';
            }
        }
    }
}

/*
 * Load active categories for the dropdown.
 */
if ($hasCategoryRelation) {

    try {

        $categoryStatement = $conn->query("
            SELECT
                id,
                category_name
            FROM categories
            WHERE status = 'Active'
            ORDER BY category_name ASC, id ASC
        ");

        $categories =
            $categoryStatement->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (Throwable $exception) {

        error_log(
            'Active category loading failed: ' .
            $exception->getMessage()
        );

        if ($error === '') {
            $error =
                'Unable to load active categories.';
        }
    }
}

function add_subject_e(mixed $value): string
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

    .subject-add-page {
        max-width: 1050px;
        margin: 0 auto;
    }

    .subject-add-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 23px;
    }

    .subject-add-heading .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #556b2f;
        font-size: .76rem;
        font-weight: 800;
        letter-spacing: .12em;
        margin-bottom: 8px;
    }

    .subject-add-heading h1 {
        margin: 0;
    }

    .subject-add-heading p {
        margin: 6px 0 0;
        color: #746d68;
    }

    .subject-back {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 42px;
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

    .subject-back:hover {
        background: #5d4037;
        border-color: #5d4037;
        color: #fff;
        transform: translateY(-2px);
    }

    .subject-form-card {
        overflow: hidden;
        border-radius: 24px;
        background: rgba(255,255,255,.90);
        border: 1px solid rgba(93,64,55,.10);
        box-shadow: 0 20px 55px rgba(51,51,51,.08);
        backdrop-filter: blur(15px);
    }

    .subject-form-head {
        padding: 21px 23px;
        border-bottom: 1px solid rgba(93,64,55,.08);
        background: rgba(250,247,240,.65);
    }

    .subject-form-head span {
        display: block;
        color: #556b2f;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .10em;
        margin-bottom: 4px;
    }

    .subject-form-head h2 {
        margin: 0;
        color: #333;
        font-size: 1.08rem;
    }

    .subject-form-body {
        padding: 25px;
    }

    .subject-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .subject-form-field.full {
        grid-column: 1 / -1;
    }

    .subject-form-field label {
        display: block;
        margin-bottom: 7px;
        color: #504945;
        font-size: .82rem;
        font-weight: 800;
    }

    .subject-form-field input,
    .subject-form-field select,
    .subject-form-field textarea {
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

    .subject-form-field textarea {
        min-height: 130px;
        resize: vertical;
    }

    .subject-form-field input:focus,
    .subject-form-field select:focus,
    .subject-form-field textarea:focus {
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

    .migration-warning {
        display: flex;
        align-items: flex-start;
        gap: 11px;
        margin-bottom: 20px;
        padding: 15px;
        border-radius: 15px;
        background: rgba(154,107,22,.09);
        border: 1px solid rgba(154,107,22,.14);
        color: #725315;
    }

    .migration-warning code {
        color: #5d4037;
    }

    .subject-form-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding-top: 22px;
        margin-top: 23px;
        border-top: 1px solid rgba(93,64,55,.08);
    }

    .subject-save-btn,
    .subject-cancel-btn {
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
        transform: none;
    }

    .subject-cancel-btn {
        background: rgba(93,64,55,.08);
        color: #5d4037;
    }

    .subject-cancel-btn:hover {
        background: rgba(93,64,55,.14);
        color: #5d4037;
    }

    @media (max-width: 760px) {

        .subject-add-heading {
            align-items: flex-start;
            flex-direction: column;
        }

        .subject-back {
            width: 100%;
        }

        .subject-form-grid {
            grid-template-columns: 1fr;
        }

        .subject-form-field.full {
            grid-column: auto;
        }

        .subject-form-footer {
            align-items: stretch;
            flex-direction: column-reverse;
        }

        .subject-save-btn,
        .subject-cancel-btn {
            width: 100%;
        }

    }

</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content subject-add-page">

            <section class="subject-add-heading">

                <div>

                    <span class="eyebrow">
                        <i class="fa-solid fa-book-medical"></i>
                        ACADEMIC STRUCTURE
                    </span>

                    <h1>Add Subject</h1>

                    <p>
                        Create a new subject and connect it to an active
                        category.
                    </p>

                </div>

                <a
                    href="index.php"
                    class="subject-back"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Subjects
                </a>

            </section>

            <section class="subject-form-card">

                <header class="subject-form-head">

                    <span>SUBJECT CONFIGURATION</span>

                    <h2>Create Subject</h2>

                </header>

                <div class="subject-form-body">

                    <?php if (!$hasCategoryRelation): ?>

                        <div class="migration-warning">

                            <i class="fa-solid fa-triangle-exclamation mt-1"></i>

                            <div>

                                <strong>
                                    Category relationship unavailable.
                                </strong>

                                <div class="small mt-1">
                                    Apply the project category migration
                                    before creating category-linked subjects.
                                </div>

                            </div>

                        </div>

                    <?php endif; ?>

                    <?php if (
                        $error !== '' &&
                        !$hasCategoryRelation
                    ): ?>

                        <div class="alert alert-danger border-0 rounded-4">

                            <i class="fa-solid fa-circle-exclamation me-2"></i>

                            <?= add_subject_e($error) ?>

                        </div>

                    <?php elseif ($error !== ''): ?>

                        <div class="alert alert-danger border-0 rounded-4">

                            <i class="fa-solid fa-circle-exclamation me-2"></i>

                            <?= add_subject_e($error) ?>

                        </div>

                    <?php endif; ?>

                    <form
                        method="post"
                        autocomplete="off"
                        novalidate
                        id="subjectAddForm"
                    >

                        <?= csrf_field() ?>

                        <div class="subject-form-grid">

                            <div class="subject-form-field">

                                <label for="category_id">
                                    Category
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    id="category_id"
                                    name="category_id"
                                    required
                                    <?= !$hasCategoryRelation
                                        ? 'disabled'
                                        : '' ?>
                                >

                                    <option value="">
                                        Select Category
                                    </option>

                                    <?php foreach (
                                        $categories as $category
                                    ): ?>

                                        <option
                                            value="<?= (int)$category['id'] ?>"
                                            <?= (string)$categoryId ===
                                                (string)$category['id']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= add_subject_e(
                                                $category['category_name']
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <small class="subject-help">
                                    Only active categories can receive new
                                    subjects.
                                </small>

                            </div>

                            <div class="subject-form-field">

                                <label for="code">
                                    Subject Code
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    id="code"
                                    type="text"
                                    name="code"
                                    maxlength="30"
                                    value="<?= add_subject_e($code) ?>"
                                    placeholder="Example: CS101"
                                    required
                                >

                                <small class="subject-help">
                                    Maximum 30 characters. Subject codes are
                                    unique.
                                </small>

                            </div>

                            <div class="subject-form-field">

                                <label for="name">
                                    Subject Name
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    id="name"
                                    type="text"
                                    name="name"
                                    maxlength="120"
                                    value="<?= add_subject_e($name) ?>"
                                    placeholder="Example: Database Management"
                                    required
                                >

                                <small class="subject-help">
                                    Subject names are unique in the database.
                                </small>

                            </div>

                            <div class="subject-form-field">

                                <label for="status">
                                    Status
                                </label>

                                <select
                                    id="status"
                                    name="status"
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

                                <small class="subject-help">
                                    Inactive subjects can remain in existing
                                    records without being presented as active.
                                </small>

                            </div>

                            <div class="subject-form-field full">

                                <label for="description">
                                    Description
                                </label>

                                <textarea
                                    id="description"
                                    name="description"
                                    maxlength="65535"
                                    placeholder="Enter a short description for this subject..."
                                ><?= add_subject_e(
                                    $description
                                ) ?></textarea>

                            </div>

                        </div>

                        <div class="subject-form-footer">

                            <a
                                href="index.php"
                                class="subject-cancel-btn"
                            >
                                <i class="fa-solid fa-xmark"></i>
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="subject-save-btn"
                                id="subjectSaveButton"
                                <?= !$hasCategoryRelation
                                    ? 'disabled'
                                    : '' ?>
                            >
                                <i class="fa-solid fa-plus"></i>
                                Create Subject
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
                document.getElementById('subjectAddForm');

            const code =
                document.getElementById('code');

            const button =
                document.getElementById('subjectSaveButton');

            if (code) {

                code.addEventListener(
                    'input',
                    function () {
                        code.value =
                            code.value
                                .toUpperCase();
                    }
                );
            }

            if (form && button) {

                form.addEventListener(
                    'submit',
                    function (event) {

                        if (!form.checkValidity()) {
                            return;
                        }

                        button.disabled = true;

                        button.innerHTML =
                            '<i class="fa-solid fa-spinner fa-spin"></i> ' +
                            'Creating Subject...';
                    }
                );
            }

        }
    );

</script>

<?php include "../includes/footer.php"; ?>