<?php
declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';

header(
    'Content-Type: application/json; charset=UTF-8'
);

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'teacher'
) {

    http_response_code(403);

    echo json_encode([
        'error' => 'Unauthorized'
    ]);

    exit;
}

$subjectId = filter_input(
    INPUT_GET,
    'subject_id',
    FILTER_VALIDATE_INT
);

if (
    $subjectId === false ||
    $subjectId === null ||
    $subjectId <= 0
) {

    http_response_code(400);

    echo json_encode([
        'error' => 'Invalid subject'
    ]);

    exit;
}

try {

    $statement = $conn->prepare("
        SELECT
            id,
            name
        FROM topics
        WHERE
            subject_id = ?
            AND status = 'Active'
        ORDER BY
            name ASC,
            id ASC
    ");

    $statement->execute([
        (int)$subjectId
    ]);

    echo json_encode(
        $statement->fetchAll(
            PDO::FETCH_ASSOC
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

} catch (Throwable $exception) {

    error_log(
        'Teacher topics AJAX failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'error' => 'Unable to load topics'
    ]);
}