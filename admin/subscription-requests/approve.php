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


    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | REQUEST
    |--------------------------------------------------------------------------
    */

    $requestStatement =
        $conn->prepare(
            "
            SELECT

                r.id,
                r.student_id,
                r.plan_id,
                r.amount,
                r.request_type,
                r.status,

                p.duration_months,
                p.name AS plan_name

            FROM subscription_requests r

            INNER JOIN subscription_plans p
                ON p.id = r.plan_id

            WHERE
                r.id = ?

            LIMIT 1

            FOR UPDATE
            "
        );


    $requestStatement->execute([
        $requestId
    ]);


    $request =
        $requestStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$request
    ) {

        throw new RuntimeException(
            'Subscription request not found.'
        );
    }


    if (
        $request[
            'status'
        ]
        !==
        'Pending'
    ) {

        throw new RuntimeException(
            'This request has already been reviewed.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PAID ONLY
    |--------------------------------------------------------------------------
    */

    if (
        $request[
            'request_type'
        ]
        !==
        'Paid'
        ||
        (float)$request[
            'amount'
        ]
        <=
        0
    ) {

        throw new RuntimeException(
            'Only paid requests can be approved here.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | STUDENT
    |--------------------------------------------------------------------------
    */

    $studentStatement =
        $conn->prepare(
            "
            SELECT
                id

            FROM students

            WHERE

                id = ?

                AND status = 'Active'

            LIMIT 1

            FOR UPDATE
            "
        );


    $studentStatement->execute([
        (int)$request[
            'student_id'
        ]
    ]);


    if (
        !$studentStatement
            ->fetchColumn()
    ) {

        throw new RuntimeException(
            'The student account is not active.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PLAN
    |--------------------------------------------------------------------------
    */

    $planStatement =
        $conn->prepare(
            "
            SELECT

                id,
                duration_months,
                status

            FROM subscription_plans

            WHERE

                id = ?

                AND status = 'Active'

            LIMIT 1

            FOR UPDATE
            "
        );


    $planStatement->execute([
        (int)$request[
            'plan_id'
        ]
    ]);


    $plan =
        $planStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$plan
    ) {

        throw new RuntimeException(
            'The requested plan is no longer active.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ACTIVE MEMBERSHIP
    |--------------------------------------------------------------------------
    */

    $today =
        new DateTimeImmutable(
            'today'
        );


    $activeStatement =
        $conn->prepare(
            "
            SELECT
                id

            FROM subscriptions

            WHERE

                student_id = ?

                AND status = 'Active'

                AND start_date <= ?

                AND end_date >= ?

            LIMIT 1

            FOR UPDATE
            "
        );


    $activeStatement->execute([
        (int)$request[
            'student_id'
        ],

        $today->format(
            'Y-m-d'
        ),

        $today->format(
            'Y-m-d'
        )
    ]);


    if (
        $activeStatement
            ->fetchColumn()
    ) {

        throw new RuntimeException(
            'The student already has an active subscription.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DATES
    |--------------------------------------------------------------------------
    */

    [
        $startDate,
        $endDate
    ] =
        subscription_period(
            $today,
            (int)$plan[
                'duration_months'
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | CREATE SUBSCRIPTION
    |--------------------------------------------------------------------------
    */

    $subscriptionInsert =
        $conn->prepare(
            "
            INSERT INTO subscriptions
            (
                student_id,
                plan_id,
                start_date,
                end_date,
                status
            )

            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                'Active'
            )
            "
        );


    $subscriptionInsert->execute([

        (int)$request[
            'student_id'
        ],

        (int)$request[
            'plan_id'
        ],

        $startDate
            ->format(
                'Y-m-d'
            ),

        $endDate
            ->format(
                'Y-m-d'
            )

    ]);


    /*
    |--------------------------------------------------------------------------
    | APPROVE REQUEST
    |--------------------------------------------------------------------------
    */

    $updateRequest =
        $conn->prepare(
            "
            UPDATE subscription_requests

            SET

                status = 'Approved',

                admin_id = ?,

                reviewed_at = NOW(),

                admin_note = ?

            WHERE
                id = ?

                AND status = 'Pending'

            LIMIT 1
            "
        );


    $updateRequest->execute([

        (int)$_SESSION[
            'user_id'
        ],

        'Payment verified and subscription activated.',

        $requestId

    ]);


    $conn->commit();


    $_SESSION['success'] =
        'Subscription request approved and membership activated.';


} catch (
    Throwable $exception
) {


    if (
        $conn->inTransaction()
    ) {

        $conn->rollBack();

    }


    error_log(
        'Approve subscription request failed: ' .
        $exception->getMessage()
    );


    $_SESSION['error'] =
        $exception->getMessage();
}


header(
    'Location: ../subscription_requests.php'
);

exit;