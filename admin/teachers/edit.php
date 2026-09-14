<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {

    header(
        'Location: ../../auth/login.php'
    );

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

    $_SESSION['error'] =
        'Invalid teacher.';

    header(
        'Location: index.php'
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| EDIT IS INTENTIONALLY DISABLED
|--------------------------------------------------------------------------
*/

$_SESSION['error'] =
    'Teacher details cannot be edited after the teacher has been created.';

header(
    'Location: view.php?id=' .
    $id
);

exit;