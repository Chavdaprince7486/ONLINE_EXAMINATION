<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| SESSION / DATABASE / FUNCTIONS
|--------------------------------------------------------------------------
*/

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';


/*
|--------------------------------------------------------------------------
| JSON HEADERS
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: application/json; charset=UTF-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Pragma: no-cache'
);


/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function clear_response_json(
    bool $status,
    string $message,
    array $data = [],
    int $httpCode = 200
): never {

    http_response_code(
        $httpCode
    );


    echo json_encode(
        array_merge(
            [
                'status' => $status,
                'message' => $message
            ],
            $data
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );


    exit;
}


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    clear_response_json(
        false,
        'Invalid request method.',
        [],
        405
    );
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {

    clear_response_json(
        false,
        'Unauthorized access.',
        [],
        401
    );
}


$studentId =
    (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$requestToken =
    trim(
        (string) (
            $_POST['csrf_token'] ?? ''
        )
    );


if (
    $requestToken === ''
) {

    clear_response_json(
        false,
        'Invalid security token.',
        [],
        419
    );
}


$validCsrf =
    false;


/*
|--------------------------------------------------------------------------
| Prefer exam-specific token
|--------------------------------------------------------------------------
*/

$examCsrfToken =
    (string) (
        $_SESSION['exam_csrf_token'] ?? ''
    );


if (
    $examCsrfToken !== ''
) {

    $validCsrf =
        hash_equals(
            $examCsrfToken,
            $requestToken
        );
}


/*
|--------------------------------------------------------------------------
| Global token fallback
|--------------------------------------------------------------------------
*/

if (
    !$validCsrf
) {

    $globalCsrfToken =
        (string) (
            $_SESSION['csrf_token'] ?? ''
        );


    if (
        $globalCsrfToken !== ''
    ) {

        $validCsrf =
            hash_equals(
                $globalCsrfToken,
                $requestToken
            );
    }
}


if (
    !$validCsrf
) {

    clear_response_json(
        false,
        'Invalid security token.',
        [],
        419
    );
}


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$attemptId =
    filter_input(
        INPUT_POST,
        'attempt_id',
        FILTER_VALIDATE_INT
    );


$questionId =
    filter_input(
        INPUT_POST,
        'question_id',
        FILTER_VALIDATE_INT
    );


/*
|--------------------------------------------------------------------------
| VALIDATE ATTEMPT ID
|--------------------------------------------------------------------------
*/

if (
    $attemptId === false ||
    $attemptId === null ||
    $attemptId <= 0
) {

    clear_response_json(
        false,
        'Invalid attempt ID.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE QUESTION ID
|--------------------------------------------------------------------------
*/

if (
    $questionId === false ||
    $questionId === null ||
    $questionId <= 0
) {

    clear_response_json(
        false,
        'Invalid question ID.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | LOAD + LOCK ATTEMPT
    |--------------------------------------------------------------------------
    */

    $attemptStatement =
        $conn->prepare("
            SELECT

                ea.id,
                ea.exam_id,
                ea.student_id,

                ea.status,
                ea.server_deadline,

                e.required_question_count,
                e.exam_type,
                e.status AS exam_status

            FROM exam_attempts ea

            INNER JOIN exams e
                ON e.id = ea.exam_id

            WHERE

                ea.id = ?

                AND ea.student_id = ?

            LIMIT 1

            FOR UPDATE
        ");


    $attemptStatement->execute([

        (int) $attemptId,

        $studentId

    ]);


    $attempt =
        $attemptStatement->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | ATTEMPT NOT FOUND
    |--------------------------------------------------------------------------
    */

    if (
        !$attempt
    ) {

        throw new RuntimeException(
            'Examination attempt not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | OWNERSHIP
    |--------------------------------------------------------------------------
    */

    if (
        (int) $attempt['student_id'] !==
        $studentId
    ) {

        throw new RuntimeException(
            'You are not allowed to modify this examination attempt.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ATTEMPT MUST BE STARTED
    |--------------------------------------------------------------------------
    */

    if (
        (string) $attempt['status'] !==
        'Started'
    ) {

        throw new RuntimeException(
            'This examination attempt is no longer active.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EXAM MUST STILL BE ACTIVE
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            (string) $attempt['exam_status'],
            [
                'Active',
                'Live'
            ],
            true
        )
    ) {

        throw new RuntimeException(
            'This examination is no longer available.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SERVER DEADLINE
    |--------------------------------------------------------------------------
    */

    if (
        empty(
            $attempt['server_deadline']
        )
    ) {

        throw new RuntimeException(
            'Examination deadline is missing.'
        );
    }


    try {

        $deadline =
            new DateTimeImmutable(
                (string) $attempt[
                    'server_deadline'
                ]
            );

    } catch (Throwable) {

        throw new RuntimeException(
            'Examination deadline is invalid.'
        );
    }


    $serverNow =
        new DateTimeImmutable();


    /*
    |--------------------------------------------------------------------------
    | EXPIRY
    |--------------------------------------------------------------------------
    */

    if (
        $serverNow >= $deadline
    ) {

        /*
        |--------------------------------------------------------------------------
        | Automatically close expired attempt
        |--------------------------------------------------------------------------
        */

        $expireStatement =
            $conn->prepare("
                UPDATE exam_attempts

                SET

                    status = 'Auto Submitted',

                    submitted_at =
                        COALESCE(
                            submitted_at,
                            NOW()
                        ),

                    last_activity_at =
                        NOW()

                WHERE

                    id = ?

                    AND student_id = ?

                    AND status = 'Started'
            ");


        $expireStatement->execute([

            (int) $attemptId,

            $studentId

        ]);


        $conn->commit();


        clear_response_json(

            false,

            'The examination time has expired.',

            [

                'expired' =>
                    true,

                'attempt_id' =>
                    (int) $attemptId,

                'server_time' =>
                    $serverNow->format(
                        DateTimeInterface::ATOM
                    ),

                'server_deadline' =>
                    $deadline->format(
                        DateTimeInterface::ATOM
                    ),

                'remaining_seconds' =>
                    0

            ],

            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY QUESTION BELONGS TO THIS EXAM
    |--------------------------------------------------------------------------
    */

    $questionStatement =
        $conn->prepare("
            SELECT

                q.id,
                q.status

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE

                eq.exam_id = ?

                AND eq.question_id = ?

            LIMIT 1

            FOR UPDATE
        ");


    $questionStatement->execute([

        (int) $attempt['exam_id'],

        (int) $questionId

    ]);


    $question =
        $questionStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$question
    ) {

        throw new RuntimeException(
            'This question does not belong to the current examination.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | QUESTION MUST BE ACTIVE
    |--------------------------------------------------------------------------
    */

    if (
        (string) (
            $question['status'] ?? ''
        ) !== 'Active'
    ) {

        throw new RuntimeException(
            'This question is no longer active.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY CURRENT QUESTION SET
    |--------------------------------------------------------------------------
    */

    $questionCountStatement =
        $conn->prepare("
            SELECT

                COUNT(DISTINCT eq.question_id)

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE

                eq.exam_id = ?

                AND q.status = 'Active'
        ");


    $questionCountStatement->execute([
        (int) $attempt['exam_id']
    ]);


    $activeQuestionCount =
        (int) $questionCountStatement->fetchColumn();


    $requiredQuestionCount =
        (int) $attempt[
            'required_question_count'
        ];


    if (
        $requiredQuestionCount <= 0 ||
        $activeQuestionCount !==
        $requiredQuestionCount
    ) {

        throw new RuntimeException(
            'The examination question set is no longer valid.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD EXISTING ANSWER
    |--------------------------------------------------------------------------
    */

    $answerStatement =
        $conn->prepare("
            SELECT

                id,
                selected_answer,
                question_status

            FROM answers

            WHERE

                attempt_id = ?

                AND question_id = ?

            ORDER BY
                id DESC

            LIMIT 1

            FOR UPDATE
        ");


    $answerStatement->execute([

        (int) $attemptId,

        (int) $questionId

    ]);


    $answer =
        $answerStatement->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | DETERMINE NEW STATUS
    |--------------------------------------------------------------------------
    */

    $newStatus =
        'Not Answered';


    /*
    |--------------------------------------------------------------------------
    | NO ANSWER ROW
    |--------------------------------------------------------------------------
    */

    if (
        !$answer
    ) {

        $insertStatement =
            $conn->prepare("
                INSERT INTO answers
                (
                    attempt_id,
                    question_id,

                    selected_answer,
                    question_status,

                    answered_at,

                    is_correct,
                    marks_awarded
                )

                VALUES
                (
                    ?,
                    ?,

                    NULL,
                    'Not Answered',

                    NULL,

                    0,
                    0
                )
            ");


        $insertStatement->execute([

            (int) $attemptId,

            (int) $questionId

        ]);


        $newStatus =
            'Not Answered';


    } else {

        /*
        |--------------------------------------------------------------------------
        | Preserve review intent
        |--------------------------------------------------------------------------
        */

        $currentStatus =
            (string) (
                $answer[
                    'question_status'
                ] ?? ''
            );


        $wasMarkedForReview =
            in_array(
                $currentStatus,
                [
                    'Marked for Review',
                    'Answered & Marked for Review'
                ],
                true
            );


        $newStatus =
            $wasMarkedForReview
                ? 'Marked for Review'
                : 'Not Answered';


        /*
        |--------------------------------------------------------------------------
        | CLEAR ANSWER
        |--------------------------------------------------------------------------
        |
        | Once the selected option is removed:
        |
        | Answered
        | ->
        | Not Answered
        |
        | Answered & Marked for Review
        | ->
        | Marked for Review
        |--------------------------------------------------------------------------
        */

        $updateStatement =
            $conn->prepare("
                UPDATE answers

                SET

                    selected_answer = NULL,

                    question_status = ?,

                    answered_at = NULL,

                    is_correct = 0,

                    marks_awarded = 0

                WHERE

                    id = ?

                    AND attempt_id = ?

                    AND question_id = ?
            ");


        $updateStatement->execute([

            $newStatus,

            (int) $answer['id'],

            (int) $attemptId,

            (int) $questionId

        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE LAST ACTIVITY
    |--------------------------------------------------------------------------
    */

    $activityStatement =
        $conn->prepare("
            UPDATE exam_attempts

            SET
                last_activity_at = NOW()

            WHERE

                id = ?

                AND student_id = ?

                AND status = 'Started'
        ");


    $activityStatement->execute([

        (int) $attemptId,

        $studentId

    ]);


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | FINAL RESPONSE
    |--------------------------------------------------------------------------
    |
    | The response now uses $newStatus, not the stale previous answer row.
    |--------------------------------------------------------------------------
    */

    clear_response_json(

        true,

        'Response cleared successfully.',

        [

            'cleared' =>
                true,

            'attempt_id' =>
                (int) $attemptId,

            'question_id' =>
                (int) $questionId,

            'selected_answer' =>
                null,

            'question_status' =>
                $newStatus,

            'server_time' =>
                $serverNow->format(
                    DateTimeInterface::ATOM
                ),

            'server_deadline' =>
                $deadline->format(
                    DateTimeInterface::ATOM
                ),

            'remaining_seconds' =>
                max(
                    0,
                    $deadline->getTimestamp()
                    -
                    $serverNow->getTimestamp()
                )

        ]

    );


} catch (
    Throwable $exception
) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    if (
        $conn->inTransaction()
    ) {

        $conn->rollBack();
    }


    /*
    |--------------------------------------------------------------------------
    | LOG INTERNAL ERROR
    |--------------------------------------------------------------------------
    */

    error_log(
        'ExamSphere clear response failed: ' .
        $exception->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | SAFE ERROR MESSAGE
    |--------------------------------------------------------------------------
    */

    $safeMessages = [

        'Examination attempt not found.',

        'You are not allowed to modify this examination attempt.',

        'This examination attempt is no longer active.',

        'This examination is no longer available.',

        'Examination deadline is missing.',

        'Examination deadline is invalid.',

        'The examination time has expired.',

        'This question does not belong to the current examination.',

        'This question is no longer active.',

        'The examination question set is no longer valid.'
    ];


    $userMessage =
        in_array(
            $exception->getMessage(),
            $safeMessages,
            true
        )
            ? $exception->getMessage()
            : 'Unable to clear the response. Please try again.';


    clear_response_json(

        false,

        $userMessage,

        [],

        400

    );
}