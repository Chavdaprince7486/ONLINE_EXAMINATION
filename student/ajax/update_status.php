<?php

declare(strict_types=1);

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

function update_status_response(
    bool $success,
    string $message,
    array $data = [],
    int $httpCode = 200
): never {

    http_response_code($httpCode);

    echo json_encode(
        array_merge(
            [
                'status' => $success,
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

    update_status_response(
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
    (
        $_SESSION['user_role'] ?? ''
    ) !== 'student'
) {

    update_status_response(
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

    update_status_response(
        false,
        'Invalid security token.',
        [],
        419
    );
}


$csrfValid = false;


/*
|--------------------------------------------------------------------------
| EXAM CSRF TOKEN
|--------------------------------------------------------------------------
*/

$examCsrfToken =
    (string) (
        $_SESSION['exam_csrf_token'] ?? ''
    );


if (
    $examCsrfToken !== ''
) {

    $csrfValid =
        hash_equals(
            $examCsrfToken,
            $requestToken
        );
}


/*
|--------------------------------------------------------------------------
| GLOBAL CSRF FALLBACK
|--------------------------------------------------------------------------
*/

if (
    !$csrfValid
) {

    $globalCsrfToken =
        (string) (
            $_SESSION['csrf_token'] ?? ''
        );


    if (
        $globalCsrfToken !== ''
    ) {

        $csrfValid =
            hash_equals(
                $globalCsrfToken,
                $requestToken
            );
    }
}


if (
    !$csrfValid
) {

    update_status_response(
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
    filter_var(
        $_POST['attempt_id'] ?? null,
        FILTER_VALIDATE_INT
    );


$questionId =
    filter_var(
        $_POST['question_id'] ?? null,
        FILTER_VALIDATE_INT
    );


$requestedStatus =
    trim(
        (string) (
            $_POST['status']
            ??
            $_POST['question_status']
            ??
            ''
        )
    );


$action =
    strtoupper(
        trim(
            (string) (
                $_POST['action']
                ?? ''
            )
        )
    );


/*
|--------------------------------------------------------------------------
| VALIDATE IDS
|--------------------------------------------------------------------------
*/

if (
    $attemptId === false ||
    $attemptId === null ||
    $attemptId <= 0
) {

    update_status_response(
        false,
        'Invalid attempt ID.',
        [],
        422
    );
}


if (
    $questionId === false ||
    $questionId === null ||
    $questionId <= 0
) {

    update_status_response(
        false,
        'Invalid question ID.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| VALID STATUS VALUES
|--------------------------------------------------------------------------
*/

$allowedStatuses = [

    'Not Visited',

    'Not Answered',

    'Answered',

    'Marked for Review',

    'Answered & Marked for Review'

];


/*
|--------------------------------------------------------------------------
| VALID ACTION VALUES
|--------------------------------------------------------------------------
*/

$allowedActions = [

    'OPEN',

    'MARK',

    'UNMARK'

];


/*
|--------------------------------------------------------------------------
| REQUIRE ACTION OR STATUS
|--------------------------------------------------------------------------
*/

if (
    $requestedStatus === '' &&
    !in_array(
        $action,
        $allowedActions,
        true
    )
) {

    update_status_response(
        false,
        'Invalid question status or action.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| NORMALIZE STATUS/ACTION
|--------------------------------------------------------------------------
*/

if (
    $requestedStatus !== ''
) {

    if (
        in_array(
            $requestedStatus,
            $allowedStatuses,
            true
        )
    ) {

        /*
        Direct status mode.
        */

    } elseif (
        in_array(
            strtoupper(
                $requestedStatus
            ),
            $allowedActions,
            true
        )
    ) {

        /*
        Allow legacy clients that send the action
        through the status/question_status field.
        */

        $action =
            strtoupper(
                $requestedStatus
            );

        $requestedStatus = '';

    } else {

        update_status_response(
            false,
            'Invalid question status.',
            [],
            422
        );
    }
}


/*
|--------------------------------------------------------------------------
| MAIN TRANSACTION
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
        $conn->prepare(
            "
            SELECT

                ea.id,
                ea.student_id,
                ea.exam_id,

                ea.status,
                ea.server_deadline,

                e.exam_type,
                e.status AS exam_status,

                e.required_question_count

            FROM exam_attempts ea

            INNER JOIN exams e
                ON e.id = ea.exam_id

            WHERE

                ea.id = ?

                AND ea.student_id = ?

            LIMIT 1

            FOR UPDATE
            "
        );


    $attemptStatement->execute(
        [

            (int) $attemptId,

            $studentId

        ]
    );


    $attempt =
        $attemptStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$attempt
    ) {

        throw new RuntimeException(
            'Examination attempt not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY OWNERSHIP
    |--------------------------------------------------------------------------
    */

    if (
        (int) $attempt['student_id']
        !==
        $studentId
    ) {

        throw new RuntimeException(
            'You are not allowed to update this examination attempt.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ATTEMPT MUST BE ACTIVE
    |--------------------------------------------------------------------------
    */

    if (
        (string) $attempt['status']
        !==
        'Started'
    ) {

        throw new RuntimeException(
            'This examination attempt is no longer active.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EXAM MUST BE AVAILABLE
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
                (string) $attempt['server_deadline']
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
    | EXPIRED ATTEMPT
    |--------------------------------------------------------------------------
    */

    if (
        $serverNow >= $deadline
    ) {

        $expireStatement =
            $conn->prepare(
                "
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
                "
            );


        $expireStatement->execute(
            [

                (int) $attemptId,

                $studentId

            ]
        );


        $conn->commit();


        update_status_response(
            false,
            'The examination time has expired.',
            [

                'expired' =>
                    true,

                'attempt_id' =>
                    (int) $attemptId,

                'question_id' =>
                    (int) $questionId,

                'question_status' =>
                    'Not Answered',

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
    | VERIFY QUESTION BELONGS TO EXAM
    |--------------------------------------------------------------------------
    */

    $questionStatement =
        $conn->prepare(
            "
            SELECT

                q.id,
                q.status,
                q.marks

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE

                eq.exam_id = ?

                AND eq.question_id = ?

            LIMIT 1

            FOR UPDATE
            "
        );


    $questionStatement->execute(
        [

            (int) $attempt['exam_id'],

            (int) $questionId

        ]
    );


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
        (string) $question['status']
        !==
        'Active'
    ) {

        throw new RuntimeException(
            'This question is no longer active.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY EXACT QUESTION SET
    |--------------------------------------------------------------------------
    */

    $questionCountStatement =
        $conn->prepare(
            "
            SELECT

                COUNT(DISTINCT eq.question_id)

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE

                eq.exam_id = ?

                AND q.status = 'Active'
            "
        );


    $questionCountStatement->execute(
        [
            (int) $attempt['exam_id']
        ]
    );


    $activeQuestionCount =
        (int) (
            $questionCountStatement
            ->fetchColumn()
        );


    $requiredQuestionCount =
        (int) (
            $attempt['required_question_count']
        );


    if (
        $requiredQuestionCount <= 0 ||
        $activeQuestionCount !== $requiredQuestionCount
    ) {

        throw new RuntimeException(
            'The examination question set is no longer valid.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD EXISTING ANSWER STATE
    |--------------------------------------------------------------------------
    */

    $answerStatement =
        $conn->prepare(
            "
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
            "
        );


    $answerStatement->execute(
        [

            (int) $attemptId,

            (int) $questionId

        ]
    );


    $answer =
        $answerStatement->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | EXISTING ANSWER
    |--------------------------------------------------------------------------
    */

    $selectedAnswer =
        '';


    if (
        $answer
    ) {

        $selectedAnswer =
            strtoupper(
                trim(
                    (string) (
                        $answer['selected_answer']
                        ??
                        ''
                    )
                )
            );
    }


    $hasAnswer =
        in_array(
            $selectedAnswer,
            [
                'A',
                'B',
                'C',
                'D'
            ],
            true
        );


    /*
    |--------------------------------------------------------------------------
    | CURRENT STATUS
    |--------------------------------------------------------------------------
    */

    $currentStatus =
        $answer
            ? (string) (
                $answer['question_status']
                ??
                'Not Answered'
            )
            : 'Not Visited';


    if (
        !in_array(
            $currentStatus,
            $allowedStatuses,
            true
        )
    ) {

        $currentStatus =
            $hasAnswer
                ? 'Answered'
                : 'Not Answered';
    }


    /*
    |--------------------------------------------------------------------------
    | RESOLVE NEW STATUS
    |--------------------------------------------------------------------------
    */

    $newStatus =
        'Not Answered';


    if (
        $action !== ''
    ) {

        switch (
            $action
        ) {

            /*
            |--------------------------------------------------------------------------
            | OPEN
            |--------------------------------------------------------------------------
            */

            case 'OPEN':

                if (
                    $hasAnswer
                ) {

                    $newStatus =
                        str_contains(
                            $currentStatus,
                            'Marked for Review'
                        )
                            ? 'Answered & Marked for Review'
                            : 'Answered';

                } elseif (
                    $currentStatus ===
                    'Marked for Review'
                ) {

                    $newStatus =
                        'Marked for Review';

                } else {

                    $newStatus =
                        'Not Answered';
                }

                break;


            /*
            |--------------------------------------------------------------------------
            | MARK
            |--------------------------------------------------------------------------
            */

            case 'MARK':

                $newStatus =
                    $hasAnswer
                        ? 'Answered & Marked for Review'
                        : 'Marked for Review';

                break;


            /*
            |--------------------------------------------------------------------------
            | UNMARK
            |--------------------------------------------------------------------------
            */

            case 'UNMARK':

                $newStatus =
                    $hasAnswer
                        ? 'Answered'
                        : 'Not Answered';

                break;


            default:

                throw new RuntimeException(
                    'Invalid question action.'
                );
        }

    } else {

        /*
        |--------------------------------------------------------------------------
        | DIRECT STATUS MODE
        |--------------------------------------------------------------------------
        */

        $newStatus =
            $requestedStatus;


        /*
        |--------------------------------------------------------------------------
        | "NOT VISITED" CANNOT BE SAVED AFTER REQUEST
        |--------------------------------------------------------------------------
        */

        if (
            $newStatus ===
            'Not Visited'
        ) {

            $newStatus =
                'Not Answered';
        }


        /*
        |--------------------------------------------------------------------------
        | PREVENT IMPOSSIBLE ANSWERED STATE
        |--------------------------------------------------------------------------
        */

        if (
            !$hasAnswer &&
            (
                $newStatus ===
                'Answered'

                ||

                $newStatus ===
                'Answered & Marked for Review'
            )
        ) {

            $newStatus =
                $newStatus ===
                'Answered & Marked for Review'

                    ? 'Marked for Review'

                    : 'Not Answered';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FINAL STATUS VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $newStatus,
            $allowedStatuses,
            true
        )
    ) {

        throw new RuntimeException(
            'Unable to determine a valid question status.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE STATUS
    |--------------------------------------------------------------------------
    */

    if (
        $answer
    ) {

        $updateStatement =
            $conn->prepare(
                "
                UPDATE answers

                SET

                    question_status = ?

                WHERE

                    id = ?

                    AND attempt_id = ?

                    AND question_id = ?
                "
            );


        $updateStatement->execute(
            [

                $newStatus,

                (int) $answer['id'],

                (int) $attemptId,

                (int) $questionId

            ]
        );

    } else {

        /*
        |--------------------------------------------------------------------------
        | CREATE ANSWER STATE ROW
        |--------------------------------------------------------------------------
        */

        $insertStatement =
            $conn->prepare(
                "
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
                    ?,

                    NULL,

                    0,
                    0
                )

                ON DUPLICATE KEY UPDATE

                    question_status = VALUES(question_status)
                "
            );


        $insertStatement->execute(
            [

                (int) $attemptId,

                (int) $questionId,

                $newStatus

            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE LAST ACTIVITY
    |--------------------------------------------------------------------------
    */

    $activityStatement =
        $conn->prepare(
            "
            UPDATE exam_attempts

            SET
                last_activity_at = NOW()

            WHERE

                id = ?

                AND student_id = ?

                AND status = 'Started'
            "
        );


    $activityStatement->execute(
        [

            (int) $attemptId,

            $studentId

        ]
    );


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    update_status_response(
        true,
        'Question status updated successfully.',
        [

            'attempt_id' =>
                (int) $attemptId,

            'question_id' =>
                (int) $questionId,

            'question_status' =>
                $newStatus,

            'has_answer' =>
                $hasAnswer,

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
    | INTERNAL LOG
    |--------------------------------------------------------------------------
    */

    error_log(
        'ExamSphere update status failed: ' .
        $exception->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | SAFE ERROR MESSAGE
    |--------------------------------------------------------------------------
    */

    $safeMessages = [

        'Examination attempt not found.',

        'You are not allowed to update this examination attempt.',

        'This examination attempt is no longer active.',

        'This examination is no longer available.',

        'Examination deadline is missing.',

        'Examination deadline is invalid.',

        'This question does not belong to the current examination.',

        'This question is no longer active.',

        'The examination question set is no longer valid.',

        'Invalid question action.',

        'Unable to determine a valid question status.'

    ];


    $userMessage =
        in_array(
            $exception->getMessage(),
            $safeMessages,
            true
        )
            ? $exception->getMessage()
            : 'Unable to update question status. Please try again.';


    update_status_response(
        false,
        $userMessage,
        [],
        400
    );
}