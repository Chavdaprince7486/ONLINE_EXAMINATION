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
    $_SESSION['error'] = 'Invalid topic.';
    header('Location: index.php');
    exit;
}

$page_title = 'Edit Topic';

$error = '';

$subjectId = '';
$name = '';
$description = '';
$status = 'Active';

$subjects = [];

try {

    $subjectStatement = $conn->query("
        SELECT
            id,
            name,
            status
        FROM subjects
        ORDER BY
            name ASC,
            id ASC
    ");

    $subjects = $subjectStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    $topicStatement = $conn->prepare("
        SELECT
            id,
            subject_id,
            name,
            description,
            status
        FROM topics
        WHERE id = ?
        LIMIT 1
    ");

    $topicStatement->execute([
        $id
    ]);

    $topic = $topicStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$topic) {

        $_SESSION['error'] =
            'Topic not found.';

        header('Location: index.php');
        exit;
    }

    $subjectId =
        (int)$topic['subject_id'];

    $name =
        (string)$topic['name'];

    $description =
        (string)($topic['description'] ?? '');

    $status =
        (string)$topic['status'];

} catch (Throwable $exception) {

    error_log(
        'Load topic edit failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to load topic.';

    header('Location: index.php');
    exit;
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
                $_POST['status'] ?? ''
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
                        'Topic can only belong to an active subject.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Edit topic subject validation failed: ' .
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
                      AND id <> ?
                    LIMIT 1
                ");

                $duplicateStatement->execute([
                    (int)$subjectId,
                    $name,
                    (int)$id
                ]);

                if (
                    $duplicateStatement->fetch(
                        PDO::FETCH_ASSOC
                    )
                ) {

                    $error =
                        'Another topic with this name already exists for the selected subject.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Edit topic duplicate validation failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to validate topic uniqueness.';
            }
        }

        if ($error === '') {

            try {

                $updateStatement = $conn->prepare("
                    UPDATE topics
                    SET
                        subject_id = ?,
                        name = ?,
                        description = ?,
                        status = ?
                    WHERE id = ?
                ");

                $updateStatement->execute([
                    (int)$subjectId,
                    $name,
                    $description !== ''
                        ? $description
                        : null,
                    $status,
                    (int)$id
                ]);

                examsphere_event_admin_academic(
                    $conn,
                    'topic',
                    (int)$id,
                    $name,
                    'updated'
                );

                $_SESSION['success'] =
                    'Topic "' .
                    $name .
                    '" updated successfully.';

                header(
                    'Location: view.php?id=' .
                    (int)$id
                );

                exit;

            } catch (PDOException $exception) {

                error_log(
                    'Update topic failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to update topic.';

            } catch (Throwable $exception) {

                error_log(
                    'Update topic failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to update topic.';
            }
        }
    }
}

function topic_edit_e(
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

    .topic-edit-page {
        max-width: 900px;
        margin: 0 auto;
    }

    .topic-edit-card {
        padding: 30px;
        border-radius: 22px;
        background: rgba(255,255,255,.82);
        border: 1px solid rgba(93,64,55,.08);
        box-shadow: 0 20px 50px rgba(62,45,37,.09);
    }

    .topic-edit-card h1 {
        color: #5d4037;
        font-weight: 900;
        margin-bottom: 6px;
    }

    .topic-edit-subtitle {
        color: #746d68;
        margin-bottom: 25px;
    }

    .topic-edit-group {
        margin-bottom: 19px;
    }

    .topic-edit-group label {
        display: block;
        margin-bottom: 7px;
        color: #5d4037;
        font-weight: 800;
        font-size: .84rem;
    }

    .topic-edit-group input,
    .topic-edit-group select,
    .topic-edit-group textarea {
        width: 100%;
        border: 1px solid #ddd3ca;
        border-radius: 12px;
        padding: 11px 13px;
        background: #fff;
        outline: none;
    }

    .topic-edit-group textarea {
        min-height: 150px;
        resize: vertical;
    }

    .topic-edit-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 24px;
    }

    .topic-edit-btn {
        min-height: 45px;
        padding: 0 17px;
        border: 0;
        border-radius: 11px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 800;
    }

    .topic-edit-btn.secondary {
        background: #eee9df;
        color: #5d4037;
    }

    .topic-edit-btn.primary {
        background: #5d4037;
        color: #fff;
    }

</style>

<div class="topic-edit-page">

    <div class="topic-edit-card">

        <h1>
            Edit Topic
        </h1>

        <div class="topic-edit-subtitle">
            Update the topic details and status.
        </div>

        <?php if ($error !== ''): ?>

            <div class="alert alert-danger">
                <?= topic_edit_e($error) ?>
            </div>

        <?php endif; ?>

        <form method="post">

            <?= csrf_field() ?>

            <div class="topic-edit-group">

                <label>
                    Subject *
                </label>

                <select
                    name="subject_id"
                    required
                >

                    <?php foreach ($subjects as $subject): ?>

                        <?php if (
                            (string)$subject['status'] !== 'Active' &&
                            (int)$subject['id'] !== (int)$subjectId
                        ) {
                            continue;
                        } ?>

                        <option
                            value="<?= (int)$subject['id'] ?>"
                            <?= (int)$subject['id'] ===
                                (int)$subjectId
                                ? 'selected'
                                : '' ?>
                        >
                            <?= topic_edit_e(
                                $subject['name']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="topic-edit-group">

                <label>
                    Topic Name *
                </label>

                <input
                    type="text"
                    name="name"
                    value="<?= topic_edit_e($name) ?>"
                    maxlength="150"
                    required
                >

            </div>

            <div class="topic-edit-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    maxlength="65535"
                ><?= topic_edit_e($description) ?></textarea>

            </div>

            <div class="topic-edit-group">

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

            <div class="topic-edit-actions">

                <a
                    href="view.php?id=<?= (int)$id ?>"
                    class="topic-edit-btn secondary"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="topic-edit-btn primary"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>

<?php include "../includes/footer.php"; ?>