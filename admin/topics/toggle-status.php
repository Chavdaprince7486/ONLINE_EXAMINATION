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

try {

    $statement = $conn->prepare("
        SELECT
            t.id,
            t.subject_id,
            t.name,
            t.status,

            s.status AS subject_status

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

    $currentStatus =
        (string)$topic['status'];

    $newStatus =
        $currentStatus === 'Active'
            ? 'Inactive'
            : 'Active';

    if ($newStatus === 'Active') {

        if (
            (string)$topic['subject_status'] !==
            'Active'
        ) {

            $_SESSION['error'] =
                'This topic cannot be activated because its subject is inactive.';

            header('Location: index.php');
            exit;
        }
    }

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
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
            t.id,
            t.subject_id,
            t.name,
            t.status,

            s.status AS subject_status

        FROM topics t

        INNER JOIN subjects s
            ON s.id = t.subject_id

        WHERE
            t.id = ?

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

    $lockedStatus =
        (string)$lockedTopic['status'];

    if ($lockedStatus !== $currentStatus) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Topic status changed elsewhere. Please refresh the page.';

        header('Location: index.php');
        exit;
    }

    if (
        $newStatus === 'Active' &&
        (string)$lockedTopic['subject_status'] !==
        'Active'
    ) {

        $conn->rollBack();

        $_SESSION['error'] =
            'This topic cannot be activated because its subject is inactive.';

        header('Location: index.php');
        exit;
    }

    $updateStatement = $conn->prepare("
        UPDATE topics

        SET
            status = ?

        WHERE
            id = ?
            AND status = ?
    ");

    $updateStatement->execute([
        $newStatus,
        $id,
        $currentStatus
    ]);

    if ($updateStatement->rowCount() !== 1) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Topic status was not changed. Please try again.';

        header('Location: index.php');
        exit;
    }

    $conn->commit();

    examsphere_event_admin_academic(
        $conn,
        'topic',
        (int)$id,
        (string)$lockedTopic['name'],
        strtolower($newStatus) === 'active' ? 'activated' : 'deactivated'
    );

    $_SESSION['success'] =
        'Topic "' .
        (string)$lockedTopic['name'] .
        '" is now ' .
        $newStatus .
        '.';

    header('Location: index.php');
    exit;

} catch (Throwable $exception) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log(
        'Toggle topic status failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to update topic status. Please try again.';

    header('Location: index.php');
    exit;
}