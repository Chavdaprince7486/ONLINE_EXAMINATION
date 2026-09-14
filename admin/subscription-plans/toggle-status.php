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

$planId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$planId || $planId < 1) {
    header('Location: index.php');
    exit;
}

try {

    $stmt = $conn->prepare(
        "
        UPDATE subscription_plans
        SET
            status = CASE
                WHEN status = 'Active'
                THEN 'Inactive'
                ELSE 'Active'
            END
        WHERE id = ?
        "
    );

    $stmt->execute([$planId]);

} catch (Throwable $exception) {

    error_log(
        'Subscription plan status update failed: ' .
        $exception->getMessage()
    );
}

header('Location: index.php');

exit;