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
    $_SESSION['error'] = 'Invalid teacher.';
    header('Location: index.php');
    exit;
}

try {

    $stmt = $conn->prepare("
        SELECT
            id,
            full_name,
            status
        FROM teachers
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        $_SESSION['error'] = 'Teacher not found.';
        header('Location: index.php');
        exit;
    }

    $currentStatus = (string)$teacher['status'];

    if ($currentStatus === 'Active') {

        $newStatus = 'Inactive';

    } elseif ($currentStatus === 'Inactive') {

        $newStatus = 'Active';

    } else {

        $_SESSION['error'] =
            'Invalid teacher account status.';

        header('Location: index.php');
        exit;
    }

    $update = $conn->prepare("
        UPDATE teachers
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
            'Teacher status was not changed. Please try again.';

        header('Location: index.php');
        exit;
    }

    $_SESSION['success'] =
        'Teacher "' .
        (string)$teacher['full_name'] .
        '" status changed to ' .
        $newStatus .
        '.';

    header('Location: index.php');
    exit;

} catch (Throwable $exception) {

    error_log(
        'Teacher status update failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to change the teacher status. Please try again.';

    header('Location: index.php');
    exit;
}