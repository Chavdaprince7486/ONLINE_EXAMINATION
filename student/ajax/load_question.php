<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';


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

    header(
        'Content-Type: application/json; charset=UTF-8'
    );

    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
    );

    header(
        'Pragma: no-cache'
    );

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


$csrfValid =
    false;


if (
    $requestToken !== ''
    &&
    $examToken !== ''
) {

    $csrfValid =
        hash_equals(
            $examToken,
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
    &&
    $requestToken !== ''
) {

    $globalToken =
        (string) (
            $_SESSION['csrf_token']
            ?? ''
        );


    if (
        $globalToken !== ''
    ) {

        $csrfValid =
            hash_equals(
                $globalToken,
                $requestToken
            );
    }
}


if (
    !$csrfValid
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
    $attemptId === false
    ||
    $attemptId === null
    ||
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
    $questionNumber === false
    ||
    $questionNumber === null
    ||
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

                ea.status AS attempt_status,

                e.title AS exam_title,
                e.exam_type,

                e.status AS exam_status,

                e.duration_minutes,

                e.required_question_count,

                e.total_marks,
                e.passing_marks,

                e.negative_marking,

                e.subscription_required,

                e.starts_at,
                e.ends_at

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
    (string) $attempt[
        'attempt_status'
    ] !== 'Started'
) {

    load_question_response(
        false,
        'This examination is no longer active.',
        [

            'attempt_status' =>
                (string) $attempt[
                    'attempt_status'
                ]

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
        (string) $attempt[
            'exam_status'
        ],
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
| REQUIRED QUESTION COUNT
|--------------------------------------------------------------------------
*/

$requiredQuestionCount =
    (int) (
        $attempt[
            'required_question_count'
        ] ?? 0
    );


if (
    $requiredQuestionCount <= 0
) {

    load_question_response(
        false,
        'The examination has an invalid question configuration.',
        [],
        409
    );
}


/*
|--------------------------------------------------------------------------
| LOAD ACTIVE QUESTIONS
|--------------------------------------------------------------------------
|
| We load the complete active question set first.
| This guarantees that the question number corresponds to the same
| ordered question set used by the exam.
|--------------------------------------------------------------------------
*/

try {

    $questionListStatement =
        $conn->prepare(
            "
            SELECT

                eq.question_id,
                eq.position,

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

                eq.question_id ASC
            "
        );


    $questionListStatement->execute(
        [
            (int) $attempt['exam_id']
        ]
    );


    $questions =
        $questionListStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $exception
) {

    error_log(
        'Load question list failed: ' .
        $exception->getMessage()
    );


    load_question_response(
        false,
        'Unable to load examination questions.',
        [],
        500
    );
}


/*
|--------------------------------------------------------------------------
| EXACT QUESTION COUNT
|--------------------------------------------------------------------------
*/

$totalQuestions =
    count(
        $questions
    );


if (
    $totalQuestions !==
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
| CALCULATE MARKS DYNAMICALLY
|--------------------------------------------------------------------------
|
| Total Marks =
|
|     Total Questions × Marks Per Question
|
| The stored exams.total_marks value is deliberately not trusted.
|--------------------------------------------------------------------------
*/

$marksPerQuestion =
    null;


foreach (
    $questions as $question
) {

    $currentQuestionMarks =
        round(
            (float) (
                $question['marks'] ?? 0
            ),
            2
        );


    if (
        $currentQuestionMarks <= 0
    ) {

        load_question_response(
            false,
            'This examination contains a question with invalid marks.',
            [
                'question_id' =>
                    (int) $question[
                        'id'
                    ]
            ],
            409
        );
    }


    if (
        $marksPerQuestion === null
    ) {

        $marksPerQuestion =
            $currentQuestionMarks;

    } else {

        if (
            abs(
                $marksPerQuestion -
                $currentQuestionMarks
            ) > 0.00001
        ) {

            load_question_response(
                false,
                'The examination must use the same marks value for every question.',
                [
                    'question_id' =>
                        (int) $question[
                            'id'
                        ]
                ],
                409
            );
        }
    }
}


if (
    $marksPerQuestion === null
) {

    load_question_response(
        false,
        'The examination has no valid question marks.',
        [],
        409
    );
}


$actualTotalMarks =
    round(
        $totalQuestions *
        $marksPerQuestion,
        2
    );


if (
    $actualTotalMarks <= 0
) {

    load_question_response(
        false,
        'The examination total marks configuration is invalid.',
        [],
        409
    );
}


/*
|--------------------------------------------------------------------------
| QUESTION NUMBER RANGE
|--------------------------------------------------------------------------
*/

if (
    (int) $questionNumber >
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
        $attempt[
            'server_deadline'
        ]
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


/*
|--------------------------------------------------------------------------
| FALLBACK DEADLINE
|--------------------------------------------------------------------------
*/

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


        $durationMinutes =
            (int) (
                $attempt[
                    'duration_minutes'
                ] ?? 0
            );


        if (
            $durationMinutes <= 0
        ) {

            load_question_response(
                false,
                'Unable to verify examination duration.',
                [],
                500
            );
        }


        $deadline =
            $startedAt->modify(
                '+' .
                $durationMinutes .
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
| SERVER CURRENT TIME
|--------------------------------------------------------------------------
*/

$now =
    new DateTimeImmutable();


/*
|--------------------------------------------------------------------------
| EXPIRATION
|--------------------------------------------------------------------------
|
| Do not mark the attempt Auto Submitted here.
| Canonical final submission/grading is handled by submit_exam.php.
|--------------------------------------------------------------------------
*/

if (
    $now >= $deadline
) {

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
| UPDATE LAST ACTIVITY
|--------------------------------------------------------------------------
*/

try {

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
| SELECT CURRENT QUESTION
|--------------------------------------------------------------------------
*/

$questionIndex =
    (int) $questionNumber - 1;


$currentQuestion =
    $questions[
        $questionIndex
    ];


/*
|--------------------------------------------------------------------------
| CURRENT QUESTION ID
|--------------------------------------------------------------------------
*/

$currentQuestionId =
    (int) $currentQuestion[
        'id'
    ];


/*
|--------------------------------------------------------------------------
| CURRENT QUESTION MARKS
|--------------------------------------------------------------------------
*/

$currentQuestionMarks =
    round(
        (float) (
            $currentQuestion[
                'marks'
            ] ?? 0
        ),
        2
    );


if (
    $currentQuestionMarks <= 0
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

            ORDER BY id DESC

            LIMIT 1
            "
        );


    $answerStatement->execute(
        [

            (int) $attemptId,

            $currentQuestionId

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
| NORMALIZE SAVED ANSWER
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

$options =
    [];


$optionData = [

    'A' =>
        $currentQuestion[
            'option_a'
        ],

    'B' =>
        $currentQuestion[
            'option_b'
        ],

    'C' =>
        $currentQuestion[
            'option_c'
        ],

    'D' =>
        $currentQuestion[
            'option_d'
        ]

];


foreach (
    $optionData as $label => $text
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
        [

            'question_id' =>
                $currentQuestionId

        ],
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


$deadlineTimestamp =
    $deadline->getTimestamp();


$remainingSeconds =
    max(
        0,
        $deadlineTimestamp -
        $serverTimestamp
    );


/*
|--------------------------------------------------------------------------
| RESPONSE QUESTION
|--------------------------------------------------------------------------
|
| NEVER expose:
|
| correct_answer
| answer_key
| is_correct
| marks_awarded
| explanation
|
|--------------------------------------------------------------------------
*/

$questionPayload = [

    'id' =>
        $currentQuestionId,

    'question_number' =>
        (int) $questionNumber,

    'question_type' =>
        (string) (
            $currentQuestion[
                'question_type'
            ] ?? 'MCQ'
        ),

    'question_text' =>
        (string) (
            $currentQuestion[
                'question_text'
            ] ?? ''
        ),

    'question_image' =>
        (string) (
            $currentQuestion[
                'question_image'
            ] ?? ''
        ),

    'difficulty' =>
        (string) (
            $currentQuestion[
                'difficulty'
            ] ?? ''
        ),

    'marks' =>
        $currentQuestionMarks,

    'options' =>
        $options,

    'selected_answer' =>
        $selectedAnswer,

    'question_status' =>
        $questionStatus

];


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

load_question_response(
    true,
    'Question loaded.',
    [

        'attempt_id' =>
            (int) $attemptId,

        'exam_id' =>
            (int) $attempt[
                'exam_id'
            ],

        'exam_type' =>
            (string) $attempt[
                'exam_type'
            ],

        'question_number' =>
            (int) $questionNumber,

        'total_questions' =>
            $totalQuestions,

        'required_question_count' =>
            $requiredQuestionCount,

        'marks_per_question' =>
            $marksPerQuestion,

        'total_marks' =>
            $actualTotalMarks,

        'previous_question' =>
            $previousQuestion,

        'next_question' =>
            $nextQuestion,

        'deadline' =>
            $deadlineTimestamp,

        'server_time' =>
            $serverTimestamp,

        'remaining_seconds' =>
            $remainingSeconds,

        'question' =>
            $questionPayload

    ]
);