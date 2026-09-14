<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";
require_once "../../config/functions.php";

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: ../../auth/login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| ONLY POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: ../subscriptions.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    !verify_csrf_token(
        $_POST['csrf_token'] ?? null
    )
) {

    $_SESSION['error'] =
        'Security verification failed. Please refresh and try again.';

    header(
        'Location: ../subscriptions.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| SUBSCRIPTION ID
|--------------------------------------------------------------------------
*/

$id = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $id === false ||
    $id === null ||
    $id < 1
) {

    $_SESSION['error'] =
        'Invalid subscription record.';

    header(
        'Location: ../subscriptions.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ACTION
|--------------------------------------------------------------------------
*/

$action = trim(
    (string)($_POST['action'] ?? '')
);

if ($action !== 'toggle') {

    $_SESSION['error'] =
        'Invalid subscription action.';

    header(
        'Location: ../subscriptions.php'
    );

    exit;
}


try {


    /*
    |--------------------------------------------------------------------------
    | GET CURRENT STATUS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare(
        "
        SELECT
            id,
            status,
            start_date,
            end_date
        FROM subscriptions
        WHERE id = ?
        LIMIT 1
        "
    );

    $stmt->execute([
        $id
    ]);

    $subscription =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$subscription) {

        $_SESSION['error'] =
            'Subscription record not found.';

        header(
            'Location: ../subscriptions.php'
        );

        exit;
    }


    $currentStatus =
        (string)$subscription['status'];


    /*
    |--------------------------------------------------------------------------
    | EXPIRED CANNOT TOGGLE
    |--------------------------------------------------------------------------
    */

    if ($currentStatus === 'Expired') {

        $_SESSION['error'] =
            'Expired subscriptions cannot be activated with the toggle.';

        header(
            'Location: ../subscriptions.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | TOGGLE
    |--------------------------------------------------------------------------
    */

    if ($currentStatus === 'Active') {

        $newStatus = 'Cancelled';

    } elseif ($currentStatus === 'Cancelled') {

        /*
        |--------------------------------------------------------------------------
        | DATE VALIDATION BEFORE REACTIVATION
        |--------------------------------------------------------------------------
        */

        $today =
            new DateTimeImmutable(
                'today'
            );

        $endDate =
            DateTimeImmutable::createFromFormat(
                'Y-m-d',
                (string)$subscription['end_date']
            );

        if (
            !$endDate ||
            $endDate < $today
        ) {

            $_SESSION['error'] =
                'This subscription has already passed its end date and cannot be reactivated.';

            header(
                'Location: ../subscriptions.php'
            );

            exit;
        }

        $newStatus = 'Active';

    } else {

        $_SESSION['error'] =
            'This subscription cannot be toggled from its current status.';

        header(
            'Location: ../subscriptions.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    $update = $conn->prepare(
        "
        UPDATE subscriptions
        SET status = ?
        WHERE id = ?
        LIMIT 1
        "
    );

    $update->execute([
        $newStatus,
        $id
    ]);


    /*
    |--------------------------------------------------------------------------
    | SUCCESS MESSAGE
    |--------------------------------------------------------------------------
    */

    if ($newStatus === 'Active') {

        $_SESSION['success'] =
            'Subscription activated successfully.';

    } else {

        $_SESSION['success'] =
            'Subscription deactivated successfully.';
    }


} catch (Throwable $exception) {

    error_log(
        'Subscription toggle failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to update subscription status.';
}


/*
|--------------------------------------------------------------------------
| BACK
|--------------------------------------------------------------------------
*/

header(
    'Location: ../subscriptions.php'
);

exit;