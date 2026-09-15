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
    $_SESSION['error'] = 'Invalid category.';
    header('Location: index.php');
    exit;
}

try {

    /*
     * Load category.
     */
    $categoryStatement = $conn->prepare("
        SELECT
            id,
            category_name,
            status
        FROM categories
        WHERE id = ?
        LIMIT 1
    ");

    $categoryStatement->execute([$id]);

    $category = $categoryStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$category) {
        $_SESSION['error'] = 'Category not found.';
        header('Location: index.php');
        exit;
    }

    /*
     * Check linked subjects.
     *
     * subjects.category_id uses ON DELETE SET NULL in the database.
     * We intentionally prevent the application from silently removing
     * the category association from existing subjects.
     */
    $dependencyStatement = $conn->prepare("
        SELECT COUNT(*) AS subject_count
        FROM subjects
        WHERE category_id = ?
    ");

    $dependencyStatement->execute([$id]);

    $dependency =
        $dependencyStatement->fetch(
            PDO::FETCH_ASSOC
        ) ?: [
            'subject_count' => 0
        ];

    $subjectCount =
        (int)$dependency['subject_count'];

    if ($subjectCount > 0) {

        $_SESSION['error'] =
            'Category "' .
            (string)$category['category_name'] .
            '" cannot be deleted because ' .
            $subjectCount .
            ' subject' .
            ($subjectCount === 1 ? ' is' : 's are') .
            ' linked to it. Deactivate the category instead.';

        header('Location: index.php');
        exit;
    }

    /*
     * Start transaction and lock the category.
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

    /*
     * Re-check linked subjects after locking the category.
     */
    $finalDependencyStatement = $conn->prepare("
        SELECT COUNT(*) AS subject_count
        FROM subjects
        WHERE category_id = ?
    ");

    $finalDependencyStatement->execute([$id]);

    $finalDependency =
        $finalDependencyStatement->fetch(
            PDO::FETCH_ASSOC
        ) ?: [
            'subject_count' => 0
        ];

    $finalSubjectCount =
        (int)$finalDependency['subject_count'];

    if ($finalSubjectCount > 0) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Category "' .
            (string)$lockedCategory['category_name'] .
            '" cannot be deleted because ' .
            $finalSubjectCount .
            ' subject' .
            ($finalSubjectCount === 1 ? ' is' : 's are') .
            ' linked to it. Deactivate the category instead.';

        header('Location: index.php');
        exit;
    }

    /*
     * Delete only the category.
     *
     * categories has no updated_at column and no other application
     * fields need to be modified here.
     */
    $deleteStatement = $conn->prepare("
        DELETE FROM categories
        WHERE id = ?
    ");

    $deleteStatement->execute([$id]);

    if ($deleteStatement->rowCount() !== 1) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Category was not deleted. Please try again.';

        header('Location: index.php');
        exit;
    }

    $conn->commit();

    examsphere_event_admin_academic(
        $conn,
        'category',
        (int)$id,
        (string)$lockedCategory['category_name'],
        'deleted'
    );

    $_SESSION['success'] =
        'Category "' .
        (string)$lockedCategory['category_name'] .
        '" deleted successfully.';

    header('Location: index.php');
    exit;

} catch (PDOException $exception) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log(
        'Category deletion failed: ' .
        $exception->getMessage()
    );

    /*
     * Keep database foreign-key protection as the final safety layer.
     */
    if (
        isset($exception->errorInfo[1]) &&
        (int)$exception->errorInfo[1] === 1451
    ) {

        $_SESSION['error'] =
            'This category cannot be deleted because related records exist. ' .
            'Deactivate it instead.';

    } else {

        $_SESSION['error'] =
            'Unable to delete the category. Please try again.';
    }

    header('Location: index.php');
    exit;

} catch (Throwable $exception) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log(
        'Category deletion failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'An unexpected error occurred while deleting the category.';

    header('Location: index.php');
    exit;
}