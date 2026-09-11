<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../config/exam_validation.php';


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
| RESPONSE
|--------------------------------------------------------------------------
*/

function load_question_response(
    bool $success,
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
                'status' =>
                    $success,

                'message' =>
                    $message
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
| POST ONLY
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    load_question_response(
        false,
        'Invalid request method.',
        [],
        405
    );
}


/*
|--------------------------------------------------------------------------
| STUDENT AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['user_id']
    )
    ||
    (
        $_SESSION['user_role'] ?? ''
    ) !== 'student'
) {

    load_question_response(
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
            $_POST['csrf_token']
            ?? ''
        )
    );


$examToken =
    (string) (
        $_SESSION['exam_csrf_token']
        ?? ''
    );


if (
    $requestToken === ''
    ||
    $examToken === ''
    ||
    !hash_equals(
        $examToken,
        $requestToken
    )
) {

    load_question_response(
        false,
        'Security verification failed.',
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
        $_POST['attempt_id']
        ?? null,
        FILTER_VALIDATE_INT
    );


$questionNumber =
    filter_var(
        $_POST['question_number']
        ?? null,
        FILTER_VALIDATE_INT
    );


if (
    $attemptId === false ||
    $attemptId === null ||
    $attemptId <= 0
) {

    load_question_response(
        false,
        'Invalid examination attempt.',
        [],
        422
    );
}


if (
    $questionNumber === false ||
    $questionNumber === null ||
    $questionNumber <= 0
) {

    load_question_response(
        false,
        'Invalid question number.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| LOAD ATTEMPT
|--------------------------------------------------------------------------
*/

try {

    $attemptStatement =
        $conn->prepare(
            "
            SELECT

                ea.id,
                ea.exam_id,
                ea.student_id,

                ea.started_at,
                ea.server_deadline,
                ea.last_activity_at,

                ea.status,

                e.title AS exam_title,
                e.exam_type,

                e.status AS exam_status,

                e.duration_minutes,

                e.required_question_count,

                e.total_marks,
                e.passing_marks,

                e.negative_marking

            FROM exam_attempts ea

            INNER JOIN exams e
                ON e.id = ea.exam_id

            WHERE

                ea.id = ?

                AND ea.student_id = ?

            LIMIT 1
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

} catch (
    Throwable $exception
) {

    error_log(
        'Load question attempt query failed: ' .
        $exception->getMessage()
    );


    load_question_response(
        false,
        'Unable to verify the examination attempt.',
        [],
        500
    );
}


if (
    !$attempt
) {

    load_question_response(
        false,
        'Exam attempt not found.',
        [],
        404
    );
}


/*
|--------------------------------------------------------------------------
| ATTEMPT STATUS
|--------------------------------------------------------------------------
*/

if (
    (string) $attempt['status']
    !==
    'Started'
) {

    load_question_response(
        false,
        'This examination is no longer active.',
        [
            'attempt_status' =>
                (string) $attempt['status']
        ],
        409
    );
}


/*
|--------------------------------------------------------------------------
| EXAM STATUS
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

    load_question_response(
        false,
        'This examination is no longer available.',
        [],
        409
    );
}


/*
|--------------------------------------------------------------------------
| CENTRAL EXAM VALIDATION
|--------------------------------------------------------------------------
*/

try {

    $examValidation =
        validate_exam_from_database(
            $conn,
            (int) $attempt['exam_id']
        );

} catch (
    Throwable $exception
) {

    error_log(
        'Load question exam validation failed: ' .
        $exception->getMessage()
    );


    load_question_response(
        false,
        'Unable to verify the examination configuration.',
        [],
        500
    );
}


if (
    !$examValidation['valid']
) {

    load_question_response(
        false,
        'The examination question configuration is no longer valid.',
        [

            'validation_message' =>
                $examValidation[
                    'validation'
                ]['message']
                ?? ''

        ],
        409
    );
}


$validation =
    $examValidation[
        'validation'
    ];


$requiredQuestionCount =
    (int) (
        $validation[
            'required_question_count'
        ] ?? 0
    );


$totalQuestions =
    (int) (
        $validation[
            'question_count'
        ] ?? 0
    );


$actualTotalMarks =
    round(
        (float) (
            $validation[
                'actual_marks'
            ] ?? 0
        ),
        2
    );


$examTotalMarks =
    round(
        (float) (
            $attempt[
                'total_marks'
            ] ?? 0
        ),
        2
    );


/*
|--------------------------------------------------------------------------
| EXACT QUESTION COUNT
|--------------------------------------------------------------------------
*/

if (
    $totalQuestions
    !==
    $requiredQuestionCount
) {

    load_question_response(
        false,
        'The examination question set has changed and is no longer valid.',
        [

            'expected_questions' =>
                $requiredQuestionCount,

            'actual_questions' =>
                $totalQuestions

        ],
        409
    );
}


/*
|--------------------------------------------------------------------------
| EXACT TOTAL MARKS
|--------------------------------------------------------------------------
*/

if (
    abs(
        $actualTotalMarks -
        $examTotalMarks
    ) >
    0.000001
) {

    load_question_response(
        false,
        'The examination question marks no longer match the configured total marks.',
        [

            'configured_total_marks' =>
                $examTotalMarks,

            'actual_question_marks' =>
                $actualTotalMarks

        ],
        409
    );
}


/*
|--------------------------------------------------------------------------
| QUESTION NUMBER
|--------------------------------------------------------------------------
*/

if (
    $questionNumber >
    $totalQuestions
) {

    load_question_response(
        false,
        'Invalid question number.',
        [

            'total_questions' =>
                $totalQuestions

        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| SERVER DEADLINE
|--------------------------------------------------------------------------
*/

$deadline =
    null;


if (
    !empty(
        $attempt['server_deadline']
    )
) {

    try {

        $deadline =
            new DateTimeImmutable(
                (string) $attempt[
                    'server_deadline'
                ]
            );

    } catch (
        Throwable
    ) {

        $deadline =
            null;
    }
}


if (
    $deadline === null
) {

    try {

        $startedAt =
            new DateTimeImmutable(
                (string) $attempt[
                    'started_at'
                ]
            );


        $deadline =
            $startedAt->modify(
                '+' .
                (int) $attempt[
                    'duration_minutes'
                ] .
                ' minutes'
            );

    } catch (
        Throwable $exception
    ) {

        error_log(
            'Load question deadline calculation failed: ' .
            $exception->getMessage()
        );


        load_question_response(
            false,
            'Unable to verify examination time.',
            [],
            500
        );
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT TIME
|--------------------------------------------------------------------------
*/

$now =
    new DateTimeImmutable();


/*
|--------------------------------------------------------------------------
| EXPIRED
|--------------------------------------------------------------------------
*/

if (
    $now >= $deadline
) {

    try {

        $conn->beginTransaction();


        $lockAttempt =
            $conn->prepare(
                "
                SELECT

                    id,
                    status

                FROM exam_attempts

                WHERE

                    id = ?

                    AND student_id = ?

                LIMIT 1

                FOR UPDATE
                "
            );


        $lockAttempt->execute(
            [

                (int) $attemptId,

                $studentId

            ]
        );


        $locked =
            $lockAttempt->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            $locked &&
            (string) $locked['status']
            ===
            'Started'
        ) {

            $expire =
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


            $expire->execute(
                [

                    (int) $attemptId,

                    $studentId

                ]
            );
        }


        $conn->commit();

    } catch (
        Throwable $exception
    ) {

        if (
            $conn->inTransaction()
        ) {

            $conn->rollBack();
        }


        error_log(
            'Load question expiry update failed: ' .
            $exception->getMessage()
        );
    }


    load_question_response(
        false,
        'The examination time has expired.',
        [

            'expired' =>
                true,

            'attempt_id' =>
                (int) $attemptId,

            'deadline' =>
                $deadline->getTimestamp(),

            'server_time' =>
                $now->getTimestamp(),

            'remaining_seconds' =>
                0

        ],
        409
    );
}


/*
|--------------------------------------------------------------------------
| UPDATE ACTIVITY
|--------------------------------------------------------------------------
*/

try {

    $activityStatement =
        $conn->prepare(
            "
            UPDATE exam_attempts

            SET

                last_activity_at =
                    NOW()

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

} catch (
    Throwable $exception
) {

    error_log(
        'Load question activity update failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| ZERO BASED OFFSET
|--------------------------------------------------------------------------
*/

$offset =
    (int) $questionNumber - 1;


/*
|--------------------------------------------------------------------------
| LOAD QUESTION
|--------------------------------------------------------------------------
*/

try {

    $questionSql =
        "
        SELECT

            q.id,

            q.question_type,

            q.question_text,

            q.question_image,

            q.option_a,
            q.option_b,
            q.option_c,
            q.option_d,

            q.difficulty,

            q.marks

        FROM exam_questions eq

        INNER JOIN questions q
            ON q.id = eq.question_id

        WHERE

            eq.exam_id = ?

            AND q.status = 'Active'

        ORDER BY

            eq.position ASC,

            q.id ASC

        LIMIT 1
        OFFSET {$offset}
        ";


    $questionStatement =
        $conn->prepare(
            $questionSql
        );


    $questionStatement->execute(
        [
            (int) $attempt['exam_id']
        ]
    );


    $question =
        $questionStatement->fetch(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $exception
) {

    error_log(
        'Load question query failed: ' .
        $exception->getMessage()
    );


    load_question_response(
        false,
        'Unable to load this question.',
        [],
        500
    );
}


if (
    !$question
) {

    load_question_response(
        false,
        'Question not found.',
        [],
        404
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE QUESTION MARK
|--------------------------------------------------------------------------
*/

$questionMarks =
    round(
        (float) (
            $question['marks']
            ?? 0
        ),
        2
    );


if (
    $questionMarks <= 0
) {

    load_question_response(
        false,
        'This question has an invalid marks configuration.',
        [],
        409
    );
}


/*
|--------------------------------------------------------------------------
| LOAD SAVED ANSWER
|--------------------------------------------------------------------------
*/

$savedAnswer =
    null;


try {

    $answerStatement =
        $conn->prepare(
            "
            SELECT

                selected_answer,

                question_status

            FROM answers

            WHERE

                attempt_id = ?

                AND question_id = ?

            LIMIT 1
            "
        );


    $answerStatement->execute(
        [

            (int) $attemptId,

            (int) $question['id']

        ]
    );


    $savedAnswer =
        $answerStatement->fetch(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $exception
) {

    error_log(
        'Load question saved answer failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| NORMALIZE ANSWER
|--------------------------------------------------------------------------
*/

$selectedAnswer =
    '';


$questionStatus =
    'Not Visited';


if (
    $savedAnswer
) {

    $candidateAnswer =
        strtoupper(
            trim(
                (string) (
                    $savedAnswer[
                        'selected_answer'
                    ] ?? ''
                )
            )
        );


    if (
        in_array(
            $candidateAnswer,
            [
                'A',
                'B',
                'C',
                'D'
            ],
            true
        )
    ) {

        $selectedAnswer =
            $candidateAnswer;
    }


    $storedStatus =
        trim(
            (string) (
                $savedAnswer[
                    'question_status'
                ] ?? ''
            )
        );


    $allowedStatuses = [

        'Not Visited',

        'Not Answered',

        'Answered',

        'Marked for Review',

        'Answered & Marked for Review'

    ];


    if (
        in_array(
            $storedStatus,
            $allowedStatuses,
            true
        )
    ) {

        $questionStatus =
            $storedStatus;

    } elseif (
        $selectedAnswer !== ''
    ) {

        $questionStatus =
            'Answered';

    } else {

        $questionStatus =
            'Not Answered';
    }
}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

$previousQuestion =
    $questionNumber > 1
        ? $questionNumber - 1
        : null;


$nextQuestion =
    $questionNumber < $totalQuestions
        ? $questionNumber + 1
        : null;


/*
|--------------------------------------------------------------------------
| OPTIONS
|--------------------------------------------------------------------------
*/

$options = [];


foreach (
    [
        'A' =>
            $question['option_a'],

        'B' =>
            $question['option_b'],

        'C' =>
            $question['option_c'],

        'D' =>
            $question['option_d']

    ]
    as $label => $text
) {

    $text =
        trim(
            (string) (
                $text ?? ''
            )
        );


    if (
        $text === ''
    ) {

        continue;
    }


    $options[] = [

        'label' =>
            $label,

        'text' =>
            $text

    ];
}


/*
|--------------------------------------------------------------------------
| EXACT FOUR OPTIONS
|--------------------------------------------------------------------------
*/

if (
    count($options) !== 4
) {

    load_question_response(
        false,
        'This question does not contain a valid option configuration.',
        [],
        409
    );
}


/*
|--------------------------------------------------------------------------
| TIME
|--------------------------------------------------------------------------
*/

$serverTimestamp =
    $now->getTimestamp();


$remainingSeconds =
    max(
        0,
        $deadline->getTimestamp()
        -
        $serverTimestamp
    );


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Never return:
|
| correct_answer
| explanation
| answer_key
| is_correct
| marks_awarded
|
|--------------------------------------------------------------------------
*/

load_question_response(
    true,
    'Question loaded.',
    [

        'attempt_id' =>
            (int) $attemptId,

        'exam_id' =>
            (int) $attempt['exam_id'],

        'question_number' =>
            (int) $questionNumber,

        'total_questions' =>
            $totalQuestions,

        'required_question_count' =>
            $requiredQuestionCount,

        'total_marks' =>
            $examTotalMarks,

        'actual_question_marks' =>
            $actualTotalMarks,

        'previous_question' =>
            $previousQuestion,

        'next_question' =>
            $nextQuestion,

        'deadline' =>
            $deadline->getTimestamp(),

        'server_time' =>
            $serverTimestamp,

        'remaining_seconds' =>
            $remainingSeconds,

        'question' => [

            'id' =>
                (int) $question['id'],

            'question_type' =>
                (string) (
                    $question[
                        'question_type'
                    ] ?? ''
                ),

            'question_text' =>
                (string) (
                    $question[
                        'question_text'
                    ] ?? ''
                ),

            'question_image' =>
                (string) (
                    $question[
                        'question_image'
                    ] ?? ''
                ),

            'difficulty' =>
                (string) (
                    $question[
                        'difficulty'
                    ] ?? ''
                ),

            'marks' =>
                $questionMarks,

            'options' =>
                $options,

            'selected_answer' =>
                $selectedAnswer,

            'answer' =>
                $selectedAnswer,

            'question_status' =>
                $questionStatus,

            'status' =>
                $questionStatus

        ]

    ]
);