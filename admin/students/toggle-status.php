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

    $stmt = $conn->prepare("
        SELECT
            id,
            full_name,
            status
        FROM students
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $_SESSION['error'] = 'Student not found.';
        header('Location: index.php');
        exit;
    }

    $currentStatus = (string)$student['status'];

    if ($currentStatus === 'Active') {
        $newStatus = 'Inactive';
    } elseif ($currentStatus === 'Inactive') {
        $newStatus = 'Active';
    } else {
        $_SESSION['error'] = 'Invalid student status.';
        header('Location: index.php');
        exit;
    }

    $update = $conn->prepare("
        UPDATE students
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
        $_SESSION['error'] =
            'Student status was not changed. Please try again.';

        header('Location: index.php');
        exit;
    }

    $_SESSION['success'] =
        'Student "' .
        (string)$student['full_name'] .
        '" status changed to ' .
        $newStatus .
        '.';

    header('Location: index.php');
    exit;

} catch (Throwable $exception) {

    error_log(
        'Student status update failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to change the student status. Please try again.';

    header('Location: index.php');
    exit;
}