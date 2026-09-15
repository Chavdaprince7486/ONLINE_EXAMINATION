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

if ($id === false || $id === null || $id <= 0) {
    $_SESSION['error'] = 'Invalid subject.';
    header('Location: index.php');
    exit;
}

try {

    $statement = $conn->prepare("
        SELECT
            id,
            category_id,
            name,
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

    $currentStatus = (string)$subject['status'];

    if ($currentStatus === 'Active') {

        $newStatus = 'Inactive';

    } elseif ($currentStatus === 'Inactive') {

        $newStatus = 'Active';

    } else {

        $_SESSION['error'] =
            'Invalid subject status.';

        header('Location: index.php');
        exit;
    }

    /*
     * A subject can only become Active when its category
     * exists and is Active.
     */
    if ($newStatus === 'Active') {

        $categoryStatement = $conn->prepare("
            SELECT
                id,
                status
            FROM categories
            WHERE id = ?
            LIMIT 1
        ");

        $categoryStatement->execute([
            (int)$subject['category_id']
        ]);

        $category = $categoryStatement->fetch(
            PDO::FETCH_ASSOC
        );

        if (
            !$category ||
            (string)$category['status'] !== 'Active'
        ) {

            $_SESSION['error'] =
                'This subject cannot be activated because its category is missing or inactive.';

            header('Location: index.php');
            exit;
        }
    }

    $conn->beginTransaction();

    /*
     * Lock the subject before changing its status.
     */
    $lockStatement = $conn->prepare("
        SELECT
            id,
            category_id,
            name,
            status
        FROM subjects
        WHERE id = ?
        FOR UPDATE
    ");

    $lockStatement->execute([$id]);

    $lockedSubject = $lockStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$lockedSubject) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Subject no longer exists.';

        header('Location: index.php');
        exit;
    }

    $lockedStatus = (string)$lockedSubject['status'];

    /*
     * Prevent overwriting a status changed by another request.
     */
    if ($lockedStatus !== $currentStatus) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Subject status changed elsewhere. Please refresh the page and try again.';

        header('Location: index.php');
        exit;
    }

    /*
     * Re-check the category inside the transaction when activating.
     */
    if ($newStatus === 'Active') {

        $finalCategoryCheck = $conn->prepare("
            SELECT
                id
            FROM categories
            WHERE id = ?
              AND status = 'Active'
            LIMIT 1
        ");

        $finalCategoryCheck->execute([
            (int)$lockedSubject['category_id']
        ]);

        if (!$finalCategoryCheck->fetch(PDO::FETCH_ASSOC)) {

            $conn->rollBack();

            $_SESSION['error'] =
                'This subject cannot be activated because its category is missing or inactive.';

            header('Location: index.php');
            exit;
        }
    }

    $update = $conn->prepare("
        UPDATE subjects
        SET status = ?
        WHERE id = ?
          AND status = ?
    ");

    $update->execute([
        $newStatus,
        $id,
        $currentStatus
    ]);

    if ($update->rowCount() !== 1) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Subject status was not changed. Please try again.';

        header('Location: index.php');
        exit;
    }

    $conn->commit();

    examsphere_event_admin_academic(
        $conn,
        'subject',
        (int)$id,
        (string)$subject['name'],
        strtolower($newStatus) === 'active' ? 'activated' : 'deactivated'
    );

    $_SESSION['success'] =
        'Subject "' .
        (string)$subject['name'] .
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
        'Toggle subject status failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to update subject status. Please try again.';

    header('Location: index.php');
    exit;
}