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

if (!$id || $id < 1) {
    header('Location: ../subscriptions.php');
    exit;
}

try {

    $check = $conn->prepare(
        "
        SELECT id
        FROM subscriptions
        WHERE id = ?
        LIMIT 1
        "
    );

    $check->execute([$id]);

    if (!$check->fetchColumn()) {

        header(
            'Location: ../subscriptions.php?error=not_found'
        );

        exit;
    }


    $delete = $conn->prepare(
        "
        DELETE FROM subscriptions
        WHERE id = ?
        "
    );

    $delete->execute([$id]);


    header(
        'Location: ../subscriptions.php?success=deleted'
    );

    exit;

} catch (Throwable $exception) {

    error_log(
        'Subscription deletion failed: ' .
        $exception->getMessage()
    );

    header(
        'Location: ../subscriptions.php?error=delete'
    );

    exit;
}