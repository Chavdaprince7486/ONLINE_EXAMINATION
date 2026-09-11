<?php

declare(strict_types=1);

session_start();

header(
    'Content-Type: application/json; charset=UTF-8'
);

require_once '../../config/config.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {

    echo json_encode([
        'status' => 'error',
        'count' => 0
    ]);

    exit;
}

$studentId =
    (int) $_SESSION['user_id'];

try {

    $stmt = $conn->prepare(
        "SELECT COUNT(*)
         FROM notifications
         WHERE recipient_type = 'Student'
           AND recipient_id = ?
           AND is_read = 0"
    );

    $stmt->execute([
        $studentId
    ]);

    echo json_encode([

        'status' =>
            'success',

        'count' =>
            (int) $stmt->fetchColumn()

    ]);

} catch (Throwable $exception) {

    error_log(
        'Student notification count failed: '
        . $exception->getMessage()
    );

    echo json_encode([

        'status' =>
            'error',

        'count' =>
            0

    ]);
}