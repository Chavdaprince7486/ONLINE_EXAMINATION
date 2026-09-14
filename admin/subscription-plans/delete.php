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

    $check = $conn->prepare(
        "
        SELECT id
        FROM subscription_plans
        WHERE id = ?
        LIMIT 1
        "
    );

    $check->execute([$planId]);

    if (!$check->fetchColumn()) {

        header('Location: index.php?error=not_found');
        exit;
    }


    $usage = $conn->prepare(
        "
        SELECT COUNT(*)
        FROM subscriptions
        WHERE plan_id = ?
        "
    );

    $usage->execute([$planId]);

    $usageCount = (int)$usage->fetchColumn();


    if ($usageCount > 0) {

        header('Location: index.php?error=in_use');
        exit;
    }


    $delete = $conn->prepare(
        "
        DELETE FROM subscription_plans
        WHERE id = ?
        "
    );

    $delete->execute([$planId]);

    header('Location: index.php?success=deleted');

    exit;

} catch (Throwable $exception) {

    error_log(
        'Subscription plan deletion failed: ' .
        $exception->getMessage()
    );

    header('Location: index.php?error=delete');

    exit;
}