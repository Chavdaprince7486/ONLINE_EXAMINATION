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

    /*
     * Load the current category.
     */
    $statement = $conn->prepare("
        SELECT
            id,
            category_name,
            status
        FROM categories
        WHERE id = ?
        LIMIT 1
    ");

    $statement->execute([$id]);

    $category = $statement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$category) {
        $_SESSION['error'] = 'Category not found.';
        header('Location: index.php');
        exit;
    }

    $currentStatus = (string)$category['status'];

    if ($currentStatus === 'Active') {

        $newStatus = 'Inactive';

    } elseif ($currentStatus === 'Inactive') {

        $newStatus = 'Active';

    } else {

        $_SESSION['error'] =
            'Invalid category status.';

        header('Location: index.php');
        exit;
    }

    /*
     * If deactivating a category, make sure no application flow
     * is relying on it being active. Existing linked subjects remain
     * intact, so deactivation itself is safe.
     *
     * When activating, the category only needs to be a valid existing
     * category because categories are top-level records.
     */

    /*
     * Lock the record before changing its status.
     */
    $conn->beginTransaction();

    $lockStatement = $conn->prepare("
        SELECT
            id,
            category_name,
            status
        FROM categories
        WHERE id = ?
        FOR UPDATE
    ");

    $lockStatement->execute([$id]);

    $lockedCategory = $lockStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$lockedCategory) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Category no longer exists.';

        header('Location: index.php');
        exit;
    }

    $lockedStatus =
        (string)$lockedCategory['status'];

    /*
     * Prevent an old request from overwriting a newer status.
     */
    if ($lockedStatus !== $currentStatus) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Category status changed elsewhere. Please refresh the page and try again.';

        header('Location: index.php');
        exit;
    }

    /*
     * Update ONLY the real column.
     *
     * categories has:
     * id
     * category_name
     * description
     * icon
     * status
     * created_at
     *
     * There is NO updated_at.
     */
    $update = $conn->prepare("
        UPDATE categories
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
            'Category status was not changed. Please try again.';

        header('Location: index.php');
        exit;
    }

    $conn->commit();

    $_SESSION['success'] =
        'Category "' .
        (string)$category['category_name'] .
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
        'Category status update failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to update the category status. Please try again.';

    header('Location: index.php');
    exit;
}