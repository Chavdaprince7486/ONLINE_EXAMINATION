<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';


function subscription_request_redirect(
    string $message,
    string $type = 'error'
): never {

    header(
        'Location: ../subscriptions.php?message=' .
        rawurlencode($message) .
        '&type=' .
        rawurlencode($type)
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| METHOD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    !==
    'POST'
) {

    subscription_request_redirect(
        'Invalid request.'
    );
}


/*
|--------------------------------------------------------------------------
| STUDENT AUTH
|--------------------------------------------------------------------------
*/

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
    'student'
) {

    header(
        'Location: ../../auth/login.php'
    );

    exit;
}


$studentId =
    (int)$_SESSION[
        'user_id'
    ];


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    !verify_csrf_token(
        $_POST[
            'csrf_token'
        ]
        ??
        null
    )
) {

    subscription_request_redirect(
        'Security verification failed. Please refresh the page and try again.'
    );
}


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$planId =
    filter_var(
        $_POST[
            'plan_id'
        ]
        ??
        null,
        FILTER_VALIDATE_INT
    );


$requestType =
    trim(
        (string)(
            $_POST[
                'request_type'
            ]
            ??
            'Paid'
        )
    );


if (
    !$planId
) {

    subscription_request_redirect(
        'Invalid subscription plan.'
    );
}


if (
    !in_array(
        $requestType,
        [
            'Trial',
            'Paid'
        ],
        true
    )
) {

    $requestType =
        'Paid';
}


$uploadPath =
    null;


try {


    $conn->beginTransaction();


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
        $studentId
    ]);


    if (
        !$studentStatement
            ->fetchColumn()
    ) {

        throw new RuntimeException(
            'Student account is not active.'
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
                name,
                duration_months,
                price,
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
        $planId
    ]);


    $plan =
        $planStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$plan
    ) {

        throw new RuntimeException(
            'The selected plan is not available.'
        );
    }


    $price =
        (float)$plan[
            'price'
        ];


    $isTrial =
        $price <= 0;


    /*
    |--------------------------------------------------------------------------
    | ACTIVE SUBSCRIPTION CHECK
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
        $studentId,
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
            'You already have an active subscription.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FREE TRIAL
    |--------------------------------------------------------------------------
    */

    if (
        $isTrial
    ) {


        if (
            $requestType
            !==
            'Trial'
        ) {

            throw new RuntimeException(
                'This free plan must use the trial activation flow.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE ACTIVE TRIAL DIRECTLY
        |--------------------------------------------------------------------------
        */

        [
            $subscriptionStart,
            $subscriptionEnd
        ] =
            subscription_period(
                $today,
                (int)$plan[
                    'duration_months'
                ]
            );


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
            $studentId,

            $planId,

            $subscriptionStart
                ->format(
                    'Y-m-d'
                ),

            $subscriptionEnd
                ->format(
                    'Y-m-d'
                )
        ]);


        $conn->commit();


        subscription_request_redirect(
            'Your free trial has been activated successfully.',
            'success'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PAID REQUEST
    |--------------------------------------------------------------------------
    */

    if (
        $requestType
        !==
        'Paid'
    ) {

        throw new RuntimeException(
            'This plan requires payment verification.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DUPLICATE PENDING CHECK
    |--------------------------------------------------------------------------
    */

    $pendingStatement =
        $conn->prepare(
            "
            SELECT
                id

            FROM subscription_requests

            WHERE

                student_id = ?

                AND plan_id = ?

                AND status = 'Pending'

            LIMIT 1

            FOR UPDATE
            "
        );


    $pendingStatement->execute([
        $studentId,
        $planId
    ]);


    if (
        $pendingStatement
            ->fetchColumn()
    ) {

        throw new RuntimeException(
            'You already have a pending request for this plan.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT CONFIRMATION
    |--------------------------------------------------------------------------
    */

    if (
        trim(
            (string)(
                $_POST[
                    'payment_confirmed'
                ]
                ??
                ''
            )
        )
        !==
        '1'
    ) {

        throw new RuntimeException(
            'Please confirm that the payment has been completed.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FILE
    |--------------------------------------------------------------------------
    */

    $file =
        $_FILES[
            'payment_screenshot'
        ]
        ??
        null;


    if (
        !is_array($file)
    ) {

        throw new RuntimeException(
            'Payment screenshot is required.'
        );
    }


    $fileError =
        (int)(
            $file[
                'error'
            ]
            ??
            UPLOAD_ERR_NO_FILE
        );


    if (
        $fileError
        !==
        UPLOAD_ERR_OK
    ) {

        throw new RuntimeException(
            'Payment screenshot is required.'
        );
    }


    $tmpFile =
        (string)(
            $file[
                'tmp_name'
            ]
            ??
            ''
        );


    $fileSize =
        (int)(
            $file[
                'size'
            ]
            ??
            0
        );


    if (
        $tmpFile === ''
        ||
        !is_uploaded_file(
            $tmpFile
        )
    ) {

        throw new RuntimeException(
            'Invalid payment screenshot upload.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SIZE
    |--------------------------------------------------------------------------
    */

    if (
        $fileSize <= 0
        ||
        $fileSize >
        5 * 1024 * 1024
    ) {

        throw new RuntimeException(
            'Payment screenshot must be between 1 byte and 5 MB.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MIME
    |--------------------------------------------------------------------------
    */

    $finfo =
        new finfo(
            FILEINFO_MIME_TYPE
        );


    $mime =
        (string)$finfo->file(
            $tmpFile
        );


    $allowedTypes = [

        'image/jpeg' =>
            'jpg',

        'image/png' =>
            'png',

        'image/webp' =>
            'webp'

    ];


    if (
        !isset(
            $allowedTypes[
                $mime
            ]
        )
    ) {

        throw new RuntimeException(
            'Only JPG, PNG and WebP payment screenshots are allowed.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPLOAD DIRECTORY
    |--------------------------------------------------------------------------
    */

    $uploadDirectory =
        dirname(
            __DIR__,
            2
        )
        .
        '/uploads/subscription_requests';


    if (
        !is_dir(
            $uploadDirectory
        )
        &&
        !mkdir(
            $uploadDirectory,
            0755,
            true
        )
        &&
        !is_dir(
            $uploadDirectory
        )
    ) {

        throw new RuntimeException(
            'Unable to prepare the payment proof directory.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FILE NAME
    |--------------------------------------------------------------------------
    */

    $fileName =
        'payment_' .
        $studentId .
        '_' .
        $planId .
        '_' .
        date(
            'YmdHis'
        ) .
        '_' .
        bin2hex(
            random_bytes(
                8
            )
        ) .
        '.' .
        $allowedTypes[
            $mime
        ];


    $destination =
        $uploadDirectory .
        '/' .
        $fileName;


    /*
    |--------------------------------------------------------------------------
    | MOVE
    |--------------------------------------------------------------------------
    */

    if (
        !move_uploaded_file(
            $tmpFile,
            $destination
        )
    ) {

        throw new RuntimeException(
            'Unable to save the payment screenshot.'
        );
    }


    $uploadPath =
        $destination;


    /*
    |--------------------------------------------------------------------------
    | INSERT REQUEST
    |--------------------------------------------------------------------------
    */

    $requestInsert =
        $conn->prepare(
            "
            INSERT INTO subscription_requests
            (
                student_id,
                plan_id,
                amount,
                request_type,
                payment_screenshot,
                status
            )

            VALUES
            (
                ?,
                ?,
                ?,
                'Paid',
                ?,
                'Pending'
            )
            "
        );


    $requestInsert->execute([

        $studentId,

        $planId,

        $price,

        'uploads/subscription_requests/' .
        $fileName

    ]);


    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    subscription_request_redirect(
        'Payment proof submitted. Your activation request is now waiting for Admin approval.',
        'success'
    );


} catch (
    Throwable $exception
) {


    if (
        $conn->inTransaction()
    ) {

        $conn->rollBack();

    }


    if (
        $uploadPath !== null
        &&
        is_file(
            $uploadPath
        )
    ) {

        @unlink(
            $uploadPath
        );

    }


    error_log(
        'Student subscription request failed: ' .
        $exception->getMessage()
    );


    $safeMessages = [

        'Student account is not active.',

        'The selected plan is not available.',

        'You already have an active subscription.',

        'This free plan must use the trial activation flow.',

        'This plan requires payment verification.',

        'You already have a pending request for this plan.',

        'Please confirm that the payment has been completed.',

        'Payment screenshot is required.',

        'Invalid payment screenshot upload.',

        'Payment screenshot must be between 1 byte and 5 MB.',

        'Only JPG, PNG and WebP payment screenshots are allowed.',

        'Unable to prepare the payment proof directory.',

        'Unable to save the payment screenshot.'

    ];


    $message =
        $exception->getMessage();


    if (
        !in_array(
            $message,
            $safeMessages,
            true
        )
    ) {

        $message =
            'Unable to submit the subscription request right now.';
    }


    subscription_request_redirect(
        $message
    );
}