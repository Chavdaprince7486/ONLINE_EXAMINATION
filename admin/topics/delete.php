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
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $id === false ||
    $id === null
) {
    $id = filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );
}

if (
    $id === false ||
    $id === null ||
    $id <= 0
) {
    $_SESSION['error'] =
        'Invalid topic.';

    header('Location: index.php');
    exit;
}

function topic_delete_e(
    mixed $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

try {

    $statement = $conn->prepare("
        SELECT
            t.id,
            t.name,
            t.description,
            t.status,
            s.name AS subject_name,

            (
                SELECT COUNT(*)
                FROM questions q
                WHERE q.topic_id = t.id
            ) AS question_count

        FROM topics t

        INNER JOIN subjects s
            ON s.id = t.subject_id

        WHERE
            t.id = ?

        LIMIT 1
    ");

    $statement->execute([
        $id
    ]);

    $topic = $statement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$topic) {

        $_SESSION['error'] =
            'Topic not found.';

        header('Location: index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        ?>

        <!DOCTYPE html>
        <html lang="en">

        <head>

            <meta charset="UTF-8">

            <meta
                name="viewport"
                content="width=device-width, initial-scale=1.0"
            >

            <title>
                Delete Topic -
                <?= topic_delete_e(SITE_NAME) ?>
            </title>

            <link
                rel="stylesheet"
                href="../assets/css/admin-dashboard.css"
            >

            <link
                rel="stylesheet"
                href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
            >

            <style>

                body {
                    margin: 0;
                    min-height: 100vh;
                    display: grid;
                    place-items: center;
                    padding: 24px;
                    background: #f5f5dc;
                    font-family:
                        Inter,
                        system-ui,
                        -apple-system,
                        BlinkMacSystemFont,
                        "Segoe UI",
                        sans-serif;
                    color: #333;
                }

                .topic-delete-card {
                    width: min(650px, 100%);
                    padding: 32px;
                    border-radius: 24px;
                    background: rgba(255,255,255,.84);
                    border: 1px solid rgba(93,64,55,.08);
                    box-shadow:
                        0 28px 70px rgba(62,45,37,.15);
                    backdrop-filter: blur(18px);
                }

                .topic-delete-icon {
                    width: 62px;
                    height: 62px;
                    display: grid;
                    place-items: center;
                    border-radius: 18px;
                    margin-bottom: 18px;
                    background: rgba(168,50,50,.10);
                    color: #a83232;
                    font-size: 27px;
                }

                .topic-delete-card h1 {
                    color: #5d4037;
                    margin: 0 0 8px;
                    font-size: 2rem;
                }

                .topic-delete-card p {
                    color: #746d68;
                    line-height: 1.65;
                }

                .topic-delete-info {
                    margin: 20px 0;
                    padding: 17px;
                    border-radius: 15px;
                    background: #faf7f0;
                }

                .topic-delete-name {
                    color: #5d4037;
                    font-weight: 900;
                    font-size: 1.15rem;
                }

                .topic-delete-meta {
                    margin-top: 5px;
                    color: #746d68;
                }

                .topic-delete-warning {
                    margin-bottom: 22px;
                    padding: 15px 16px;
                    border-radius: 14px;
                    background: rgba(168,50,50,.07);
                    color: #783030;
                    line-height: 1.55;
                }

                .topic-delete-actions {
                    display: flex;
                    justify-content: flex-end;
                    gap: 10px;
                    flex-wrap: wrap;
                }

                .topic-delete-btn {
                    min-height: 45px;
                    padding: 0 17px;
                    border-radius: 11px;
                    border: 0;
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    font-weight: 800;
                    cursor: pointer;
                }

                .topic-delete-btn.cancel {
                    background: #eee9df;
                    color: #5d4037;
                }

                .topic-delete-btn.danger {
                    background: #a83232;
                    color: #fff;
                }

            </style>

        </head>

        <body>

            <main class="topic-delete-card">

                <div class="topic-delete-icon">
                    <i class="fa-solid fa-trash"></i>
                </div>

                <h1>
                    Delete Topic?
                </h1>

                <p>
                    This action permanently removes the topic
                    record. Questions are not deleted, but they
                    will no longer belong to this topic.
                </p>

                <div class="topic-delete-info">

                    <div class="topic-delete-name">
                        <?= topic_delete_e(
                            $topic['name']
                        ) ?>
                    </div>

                    <div class="topic-delete-meta">
                        Subject:
                        <?= topic_delete_e(
                            $topic['subject_name']
                        ) ?>
                    </div>

                </div>

                <?php if (
                    (int)$topic['question_count'] > 0
                ): ?>

                    <div class="topic-delete-warning">

                        <strong>
                            <?= (int)$topic['question_count'] ?>
                            question(s)
                        </strong>
                        are currently linked to this topic.

                        The questions will remain,
                        but their topic relationship will
                        be cleared.

                        Deactivate the topic instead when
                        it should remain part of the
                        academic structure.

                    </div>

                <?php endif; ?>

                <form
                    method="post"
                    class="topic-delete-actions"
                >

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int)$topic['id'] ?>"
                    >

                    <a
                        href="index.php"
                        class="topic-delete-btn cancel"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="topic-delete-btn danger"
                    >
                        <i class="fa-solid fa-trash"></i>
                        Delete Topic
                    </button>

                </form>

            </main>

        </body>

        </html>

        <?php

        exit;
    }

    if (
        !verify_csrf_token(
            $_POST['csrf_token'] ?? null
        )
    ) {

        $_SESSION['error'] =
            'Security verification failed. Please try again.';

        header('Location: index.php');
        exit;
    }

    $conn->beginTransaction();

    $lockStatement = $conn->prepare("
        SELECT
            id,
            name
        FROM topics
        WHERE id = ?
        FOR UPDATE
    ");

    $lockStatement->execute([
        $id
    ]);

    $lockedTopic =
        $lockStatement->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$lockedTopic) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Topic no longer exists.';

        header('Location: index.php');
        exit;
    }

    $dependencyStatement = $conn->prepare("
        SELECT COUNT(*)
        FROM questions
        WHERE topic_id = ?
    ");

    $dependencyStatement->execute([
        $id
    ]);

    $questionCount =
        (int)$dependencyStatement->fetchColumn();

    if ($questionCount > 0) {

        /*
         * The database would SET NULL because of
         * fk_question_topic, but we protect the
         * academic structure from accidental removal.
         */
        $conn->rollBack();

        $_SESSION['error'] =
            'Topic "' .
            (string)$lockedTopic['name'] .
            '" cannot be deleted because ' .
            $questionCount .
            ' question(s) are linked to it. Deactivate it instead.';

        header('Location: index.php');
        exit;
    }

    $deleteStatement = $conn->prepare("
        DELETE FROM topics
        WHERE id = ?
    ");

    $deleteStatement->execute([
        $id
    ]);

    if ($deleteStatement->rowCount() !== 1) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Topic was not deleted. Please try again.';

        header('Location: index.php');
        exit;
    }

    $conn->commit();

    examsphere_event_admin_academic(
        $conn,
        'topic',
        (int)$id,
        (string)$lockedTopic['name'],
        'deleted'
    );

    $_SESSION['success'] =
        'Topic "' .
        (string)$lockedTopic['name'] .
        '" deleted successfully.';

    header('Location: index.php');
    exit;

} catch (Throwable $exception) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log(
        'Delete topic failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to delete topic. Please try again.';

    header('Location: index.php');
    exit;
}