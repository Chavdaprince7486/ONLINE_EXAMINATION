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

$page_title = 'Add Topic';

$error = '';

$subjectId = '';
$name = '';
$description = '';
$status = 'Active';

$subjects = [];

try {

    $statement = $conn->query("
        SELECT
            id,
            name,
            status
        FROM subjects
        ORDER BY
            name ASC,
            id ASC
    ");

    $subjects = $statement->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $exception) {

    error_log(
        'Topic subject loading failed: ' .
        $exception->getMessage()
    );

    $error =
        'Unable to load subjects.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? null
        )
    ) {

        $error =
            'Security verification failed. Please refresh the page and try again.';

    } else {

        $subjectId = filter_var(
            $_POST['subject_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $name = trim(
            (string)(
                $_POST['name'] ?? ''
            )
        );

        $description = trim(
            (string)(
                $_POST['description'] ?? ''
            )
        );

        $status = trim(
            (string)(
                $_POST['status'] ?? 'Active'
            )
        );

        if (
            $subjectId === false ||
            $subjectId === null ||
            $subjectId <= 0
        ) {

            $error =
                'Please select a valid subject.';

        } elseif ($name === '') {

            $error =
                'Topic name is required.';

        } elseif (
            mb_strlen($name) > 150
        ) {

            $error =
                'Topic name cannot exceed 150 characters.';

        } elseif (
            mb_strlen($description) > 65535
        ) {

            $error =
                'Topic description is too long.';

        } elseif (
            !in_array(
                $status,
                ['Active', 'Inactive'],
                true
            )
        ) {

            $error =
                'Invalid topic status.';
        }

        if ($error === '') {

            try {

                $subjectStatement = $conn->prepare("
                    SELECT
                        id,
                        name,
                        status
                    FROM subjects
                    WHERE id = ?
                    LIMIT 1
                ");

                $subjectStatement->execute([
                    $subjectId
                ]);

                $subject =
                    $subjectStatement->fetch(
                        PDO::FETCH_ASSOC
                    );

                if (!$subject) {

                    $error =
                        'Selected subject does not exist.';

                } elseif (
                    (string)$subject['status'] !== 'Active'
                ) {

                    $error =
                        'Topic can only be created under an active subject.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Topic subject validation failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to validate the selected subject.';
            }
        }

        if ($error === '') {

            try {

                $duplicateStatement = $conn->prepare("
                    SELECT
                        id
                    FROM topics
                    WHERE subject_id = ?
                      AND LOWER(name) = LOWER(?)
                    LIMIT 1
                ");

                $duplicateStatement->execute([
                    $subjectId,
                    $name
                ]);

                if (
                    $duplicateStatement->fetch(
                        PDO::FETCH_ASSOC
                    )
                ) {

                    $error =
                        'A topic with this name already exists for the selected subject.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Topic duplicate validation failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to validate topic uniqueness.';
            }
        }

        if ($error === '') {

            try {

                $insertStatement = $conn->prepare("
                    INSERT INTO topics
                    (
                        subject_id,
                        name,
                        description,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");

                $insertStatement->execute([
                    (int)$subjectId,
                    $name,
                    $description !== ''
                        ? $description
                        : null,
                    $status
                ]);

                $_SESSION['success'] =
                    'Topic "' .
                    $name .
                    '" created successfully.';

                header('Location: index.php');
                exit;

            } catch (PDOException $exception) {

                error_log(
                    'Create topic failed: ' .
                    $exception->getMessage()
                );

                if (
                    isset(
                        $exception->errorInfo[1]
                    ) &&
                    (int)$exception->errorInfo[1] === 1062
                ) {

                    $error =
                        'A topic with this name already exists for the selected subject.';

                } else {

                    $error =
                        'Unable to create topic.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Create topic failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to create topic.';
            }
        }
    }
}

function topic_add_e(
    mixed $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

include "../includes/header.php";
?>

<style>

    .topic-form-page {
        max-width: 900px;
        margin: 0 auto;
    }

    .topic-form-card {
        padding: 30px;
        border-radius: 22px;
        background: rgba(255,255,255,.82);
        border: 1px solid rgba(93,64,55,.08);
        box-shadow: 0 20px 50px rgba(62,45,37,.09);
    }

    .topic-form-card h1 {
        color: #5d4037;
        font-weight: 900;
        margin-bottom: 6px;
    }

    .topic-form-card .subtitle {
        color: #746d68;
        margin-bottom: 26px;
    }

    .topic-form-group {
        margin-bottom: 19px;
    }

    .topic-form-group label {
        display: block;
        margin-bottom: 7px;
        font-size: .84rem;
        font-weight: 800;
        color: #5d4037;
    }

    .topic-form-group input,
    .topic-form-group select,
    .topic-form-group textarea {
        width: 100%;
        border: 1px solid #ddd3ca;
        border-radius: 12px;
        padding: 11px 13px;
        outline: none;
        background: #fff;
    }

    .topic-form-group textarea {
        min-height: 150px;
        resize: vertical;
    }

    .topic-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 25px;
        flex-wrap: wrap;
    }

    .topic-form-btn {
        min-height: 45px;
        padding: 0 17px;
        border: 0;
        border-radius: 11px;
        font-weight: 800;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .topic-form-btn.secondary {
        color: #5d4037;
        background: #eee9df;
    }

    .topic-form-btn.primary {
        color: #fff;
        background: #5d4037;
    }

</style>

<div class="topic-form-page">

    <div class="topic-form-card">

        <h1>
            Add Topic
        </h1>

        <div class="subtitle">
            Create a topic under an active subject.
        </div>

        <?php if ($error !== ''): ?>

            <div class="alert alert-danger">
                <?= topic_add_e($error) ?>
            </div>

        <?php endif; ?>

        <form method="post">

            <?= csrf_field() ?>

            <div class="topic-form-group">

                <label>
                    Subject *
                </label>

                <select
                    name="subject_id"
                    required
                >

                    <option value="">
                        Select Subject
                    </option>

                    <?php foreach ($subjects as $subject): ?>

                        <?php if (
                            (string)$subject['status'] !== 'Active'
                        ) {
                            continue;
                        } ?>

                        <option
                            value="<?= (int)$subject['id'] ?>"
                            <?= (string)$subjectId ===
                                (string)$subject['id']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= topic_add_e(
                                $subject['name']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="topic-form-group">

                <label>
                    Topic Name *
                </label>

                <input
                    type="text"
                    name="name"
                    value="<?= topic_add_e($name) ?>"
                    maxlength="150"
                    required
                >

            </div>

            <div class="topic-form-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    maxlength="65535"
                    placeholder="Write a short description..."
                ><?= topic_add_e($description) ?></textarea>

            </div>

            <div class="topic-form-group">

                <label>
                    Status
                </label>

                <select name="status">

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

            <div class="topic-form-actions">

                <a
                    href="index.php"
                    class="topic-form-btn secondary"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="topic-form-btn primary"
                >
                    <i class="fa-solid fa-plus"></i>
                    Create Topic
                </button>

            </div>

        </form>

    </div>

</div>

<?php include "../includes/footer.php"; ?>