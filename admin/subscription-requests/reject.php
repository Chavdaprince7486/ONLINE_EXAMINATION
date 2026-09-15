<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';


if (
    empty(
        $_SESSION['user_id']
    )
    ||
    (
        $_SESSION['user_role']
        ??
        ''
    )
    !==
    'admin'
) {

    header(
        'Location: ../../auth/login.php'
    );

    exit;
}


if (
    $_SERVER['REQUEST_METHOD']
    !==
    'POST'
    ||
    !verify_csrf_token(
        $_POST[
            'csrf_token'
        ]
        ??
        null
    )
) {

    $_SESSION['error'] =
        'Security verification failed.';

    header(
        'Location: ../subscription_requests.php'
    );

    exit;
}


$requestId =
    filter_var(
        $_POST[
            'id'
        ]
        ??
        null,
        FILTER_VALIDATE_INT
    );


if (
    !$requestId
) {

    $_SESSION['error'] =
        'Invalid subscription request.';

    header(
        'Location: ../subscription_requests.php'
    );

    exit;
}


try {


    $statement =
        $conn->prepare(
            "
            UPDATE subscription_requests

            SET

                status = 'Rejected',

                admin_id = ?,

                reviewed_at = NOW(),

                admin_note = ?

            WHERE

                id = ?

                AND status = 'Pending'

            LIMIT 1
            "
        );


    $statement->execute([

        (int)$_SESSION[
            'user_id'
        ],

        'Payment proof rejected by administrator.',

        $requestId

    ]);


    if (
        $statement->rowCount()
        <
        1
    ) {

        throw new RuntimeException(
            'This request has already been reviewed or does not exist.'
        );
    }


    $_SESSION['success'] =
        'Subscription request rejected.';


} catch (
    Throwable $exception
) {


    error_log(
        'Reject subscription request failed: ' .
        $exception->getMessage()
    );


    $_SESSION['error'] =
        $exception->getMessage();
}


header(
    'Location: ../subscription_requests.php'
);

exit;