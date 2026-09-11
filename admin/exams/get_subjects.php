<?php
declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

/*
 * Only authenticated admins can request subject data.
 */
if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    http_response_code(403);

    header('Content-Type: text/plain; charset=UTF-8');

    echo 'Unauthorized';

    exit;
}

header('Content-Type: text/html; charset=UTF-8');

$categoryId = filter_input(
    INPUT_GET,
    'category_id',
    FILTER_VALIDATE_INT
);

if (
    $categoryId === false ||
    $categoryId === null ||
    $categoryId <= 0
) {
    echo '<option value="">Select Subject</option>';
    exit;
}

try {

    /*
     * Confirm that the category exists and is active.
     */
    $categoryStatement = $conn->prepare("
        SELECT
            id
        FROM categories
        WHERE id = ?
          AND status = 'Active'
        LIMIT 1
    ");

    $categoryStatement->execute([
        $categoryId
    ]);

    if (!$categoryStatement->fetch(PDO::FETCH_ASSOC)) {

        echo '<option value="">Select Subject</option>';

        exit;
    }

    /*
     * IMPORTANT:
     * The real subjects table uses `name`, not `subject_name`.
     */
    $subjectStatement = $conn->prepare("
        SELECT
            id,
            name
        FROM subjects
        WHERE category_id = ?
          AND status = 'Active'
        ORDER BY
            name ASC,
            id ASC
    ");

    $subjectStatement->execute([
        $categoryId
    ]);

    $subjects = $subjectStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    echo '<option value="">Select Subject</option>';

    foreach ($subjects as $subject) {

        echo '<option value="' .
            (int)$subject['id'] .
            '">' .
            htmlspecialchars(
                (string)$subject['name'],
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) .
            '</option>';
    }

} catch (Throwable $exception) {

    error_log(
        'Admin subject AJAX lookup failed: ' .
        $exception->getMessage()
    );

    echo '<option value="">Unable to load subjects</option>';
}