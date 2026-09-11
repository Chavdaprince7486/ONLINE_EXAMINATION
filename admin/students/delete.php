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

if ($id === false || $id === null || $id <= 0) {
    $_SESSION['error'] = 'Invalid student.';
    header('Location: index.php');
    exit;
}

try {

    $studentStmt = $conn->prepare("
        SELECT
            id,
            full_name,
            student_code
        FROM students
        WHERE id = ?
        LIMIT 1
    ");

    $studentStmt->execute([$id]);

    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $_SESSION['error'] = 'Student not found.';
        header('Location: index.php');
        exit;
    }

    $dependencyStmt = $conn->prepare("
        SELECT
            (
                SELECT COUNT(*)
                FROM exam_attempts
                WHERE student_id = ?
            ) AS attempt_count,

            (
                SELECT COUNT(*)
                FROM results
                WHERE student_id = ?
            ) AS result_count,

            (
                SELECT COUNT(*)
                FROM subscriptions
                WHERE student_id = ?
            ) AS subscription_count,

            (
                SELECT COUNT(*)
                FROM subscription_payments
                WHERE student_id = ?
            ) AS subscription_payment_count,

            (
                SELECT COUNT(*)
                FROM live_exam_payments
                WHERE student_id = ?
            ) AS live_exam_payment_count
    ");

    $dependencyStmt->execute([
        $id,
        $id,
        $id,
        $id,
        $id
    ]);

    $dependencies = $dependencyStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'attempt_count' => 0,
        'result_count' => 0,
        'subscription_count' => 0,
        'subscription_payment_count' => 0,
        'live_exam_payment_count' => 0
    ];

    $dependencyMessages = [];

    if ((int)$dependencies['attempt_count'] > 0) {
        $dependencyMessages[] =
            (int)$dependencies['attempt_count'] .
            ' exam attempt(s)';
    }

    if ((int)$dependencies['result_count'] > 0) {
        $dependencyMessages[] =
            (int)$dependencies['result_count'] .
            ' result record(s)';
    }

    if ((int)$dependencies['subscription_count'] > 0) {
        $dependencyMessages[] =
            (int)$dependencies['subscription_count'] .
            ' subscription(s)';
    }

    if ((int)$dependencies['subscription_payment_count'] > 0) {
        $dependencyMessages[] =
            (int)$dependencies['subscription_payment_count'] .
            ' subscription payment(s)';
    }

    if ((int)$dependencies['live_exam_payment_count'] > 0) {
        $dependencyMessages[] =
            (int)$dependencies['live_exam_payment_count'] .
            ' live exam payment(s)';
    }

    if ($dependencyMessages) {

        $_SESSION['error'] =
            'Student "' .
            (string)$student['full_name'] .
            '" cannot be deleted because related records exist: ' .
            implode(', ', $dependencyMessages) .
            '. Deleting the student would break the database relationships. ' .
            'Deactivate the account instead.';

        header('Location: view.php?id=' . $id);
        exit;
    }

    $conn->beginTransaction();

    $deleteStmt = $conn->prepare("
        DELETE FROM students
        WHERE id = ?
    ");

    $deleteStmt->execute([$id]);

    if ($deleteStmt->rowCount() !== 1) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        $_SESSION['error'] =
            'Student was not deleted. Please try again.';

        header('Location: index.php');
        exit;
    }

    $conn->commit();

    $_SESSION['success'] =
        'Student "' .
        (string)$student['full_name'] .
        '" was deleted successfully.';

    header('Location: index.php');
    exit;

} catch (PDOException $exception) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log(
        'Student deletion failed: ' .
        $exception->getMessage()
    );

    /*
     * MySQL foreign-key protection is kept as a second safety layer.
     * The application already performs dependency checks above.
     */
    if (
        isset($exception->errorInfo[1]) &&
        (int)$exception->errorInfo[1] === 1451
    ) {
        $_SESSION['error'] =
            'This student cannot be deleted because other records are linked to the account. ' .
            'Deactivate the account instead.';
    } else {
        $_SESSION['error'] =
            'Unable to delete the student. Please try again.';
    }

    header('Location: view.php?id=' . $id);
    exit;

} catch (Throwable $exception) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log(
        'Student deletion failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'An unexpected error occurred while deleting the student.';

    header('Location: view.php?id=' . $id);
    exit;
}