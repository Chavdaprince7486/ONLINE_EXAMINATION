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

function submit_exam_json(
    bool $success,
    string $message,
    array $data = [],
    int $code = 200
): never {

    $isAjax =
        strtolower(
            (string) (
                $_SERVER['HTTP_X_REQUESTED_WITH']
                ?? ''
            )
        ) === 'xmlhttprequest';


    if (
        !$isAjax &&
        $success &&
        isset(
            $data['redirect']
        )
    ) {

        header(
            'Location: ' .
            $data['redirect']
        );

        exit;
    }


    if (
        !$isAjax &&
        !$success
    ) {

        http_response_code(
            $code
        );


        $safeMessage =
            htmlspecialchars(
                $message,
                ENT_QUOTES |
                ENT_SUBSTITUTE,
                'UTF-8'
            );


        echo '<!doctype html>
<html lang="en">
<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>

<title>
    ExamSphere | Submission
</title>

<style>

:root{
    --cream:#F5F5DC;
    --brown:#5D4037;
    --dark:#3E2723;
    --border:#E3DED0;
    --muted:#6E625A;
}

*{
    box-sizing:border-box;
}

body{
    margin:0;
    min-height:100vh;
    display:grid;
    place-items:center;
    padding:24px;
    background:var(--cream);
    font-family:Arial,sans-serif;
    color:var(--dark);
}

.card{
    width:min(520px,100%);
    padding:32px;
    border:1px solid var(--border);
    border-radius:22px;
    background:#fff;
    box-shadow:0 20px 60px rgba(62,39,35,.12);
    text-align:center;
}

h1{
    margin:0 0 12px;
    font-size:22px;
}

p{
    margin:0;
    line-height:1.7;
    color:var(--muted);
}

a{
    display:inline-block;
    margin-top:20px;
    padding:12px 18px;
    border-radius:12px;
    background:var(--brown);
    color:#fff;
    text-decoration:none;
    font-weight:700;
}

</style>

</head>

<body>

<div class="card">

    <h1>
        Examination submission
    </h1>

    <p>
        ' .
        $safeMessage .
        '
    </p>

    <a href="../dashboard.php">
        Return to dashboard
    </a>

</div>

</body>
</html>';

        exit;
    }


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
        $code
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
| GRADE
|--------------------------------------------------------------------------
*/

function submit_exam_grade(
    float $percentage
): string {

    return match (true) {

        $percentage >= 90 =>
            'A+',

        $percentage >= 80 =>
            'A',

        $percentage >= 70 =>
            'B+',

        $percentage >= 60 =>
            'B',

        $percentage >= 50 =>
            'C',

        $percentage >= 40 =>
            'D',

        default =>
            'F'
    };
}


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    submit_exam_json(
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
    ) ||
    (
        $_SESSION['user_role'] ?? ''
    ) !== 'student'
) {

    submit_exam_json(
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


$globalToken =
    (string) (
        $_SESSION['csrf_token']
        ?? ''
    );


$csrfValid =
    false;


if (
    $requestToken !== '' &&
    $examToken !== ''
) {

    $csrfValid =
        hash_equals(
            $examToken,
            $requestToken
        );
}


if (
    !$csrfValid &&
    $requestToken !== '' &&
    $globalToken !== ''
) {

    $csrfValid =
        hash_equals(
            $globalToken,
            $requestToken
        );
}


if (
    !$csrfValid
) {

    submit_exam_json(
        false,
        'Security verification failed.',
        [],
        419
    );
}


/*
|--------------------------------------------------------------------------
| ATTEMPT ID
|--------------------------------------------------------------------------
*/

$attemptId =
    filter_var(
        $_POST['attempt_id']
        ?? null,
        FILTER_VALIDATE_INT
    );


if (
    $attemptId === false ||
    $attemptId === null ||
    $attemptId <= 0
) {

    submit_exam_json(
        false,
        'Invalid examination attempt.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| AUTO SUBMIT
|--------------------------------------------------------------------------
*/

$autoSubmit =
    (
        (string) (
            $_POST['auto_submit']
            ?? '0'
        )
    ) === '1';


/*
|--------------------------------------------------------------------------
| TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | LOCK ATTEMPT + EXAM
    |--------------------------------------------------------------------------
    */

    $attemptStatement =
        $conn->prepare(
            "
            SELECT

                ea.id AS attempt_id,

                ea.student_id,
                ea.exam_id,

                ea.started_at,
                ea.server_deadline,

                ea.submitted_at,

                ea.status AS attempt_status,

                e.title AS exam_title,

                e.exam_type,
                e.status AS exam_status,

                e.duration_minutes,

                e.required_question_count,

                e.total_marks,

                e.passing_marks,

                e.negative_marking,

                e.exam_fee,

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
    | OWNERSHIP
    |--------------------------------------------------------------------------
    */

    if (
        (int) $attempt['student_id']
        !==
        $studentId
    ) {

        throw new RuntimeException(
            'You are not allowed to submit this examination.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ALREADY SUBMITTED
    |--------------------------------------------------------------------------
    */

    if (
        (string) $attempt['attempt_status']
        !==
        'Started'
    ) {

        $existingResultStatement =
            $conn->prepare(
                "
                SELECT
                    id

                FROM results

                WHERE

                    attempt_id = ?

                    AND student_id = ?

                LIMIT 1
                "
            );


        $existingResultStatement->execute(
            [
                (int) $attemptId,
                $studentId
            ]
        );


        $existingResultId =
            $existingResultStatement
                ->fetchColumn();


        if (
            $existingResultId !== false &&
            $existingResultId !== null
        ) {

            $conn->commit();


            submit_exam_json(
                true,
                'Examination has already been submitted.',
                [

                    'already_submitted' =>
                        true,

                    'result_id' =>
                        (int) $existingResultId,

                    'attempt_id' =>
                        (int) $attemptId,

                    'redirect' =>
                        '../result.php?id=' .
                        (int) $existingResultId

                ]
            );
        }


        throw new RuntimeException(
            'This examination attempt is no longer active.'
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

        throw new RuntimeException(
            'This examination is no longer available.'
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

        } catch (Throwable) {

            throw new RuntimeException(
                'Examination deadline is invalid.'
            );
        }
    }


    if (
        $deadline === null
    ) {

        if (
            empty(
                $attempt['started_at']
            )
        ) {

            throw new RuntimeException(
                'Examination timing information is missing.'
            );
        }


        try {

            $startedAt =
                new DateTimeImmutable(
                    (string) $attempt[
                        'started_at'
                    ]
                );

        } catch (Throwable) {

            throw new RuntimeException(
                'Examination timing information is missing.'
            );
        }


        $deadline =
            $startedAt->modify(
                '+' .
                (int) $attempt[
                    'duration_minutes'
                ] .
                ' minutes'
            );


        $deadlineUpdate =
            $conn->prepare(
                "
                UPDATE exam_attempts

                SET

                    server_deadline = ?

                WHERE

                    id = ?

                    AND student_id = ?

                    AND status = 'Started'
                "
            );


        $deadlineUpdate->execute(
            [

                $deadline->format(
                    'Y-m-d H:i:s'
                ),

                (int) $attemptId,

                $studentId

            ]
        );
    }


    $serverNow =
        new DateTimeImmutable();


    $expired =
        $serverNow >= $deadline;


    /*
    |--------------------------------------------------------------------------
    | QUESTION COUNT CONFIGURATION
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

        throw new RuntimeException(
            'The examination has an invalid required question count.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD ACTIVE EXAM QUESTIONS
    |--------------------------------------------------------------------------
    |
    | Only active questions assigned to this exam are used for grading.
    |--------------------------------------------------------------------------
    */

    $questionStatement =
        $conn->prepare(
            "
            SELECT

                eq.exam_id,
                eq.question_id,

                eq.position,

                q.subject_id,

                q.correct_answer,

                q.marks,

                q.negative_marks,

                q.status

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE

                eq.exam_id = ?

                AND q.status = 'Active'

            ORDER BY

                eq.position ASC,

                eq.question_id ASC

            FOR UPDATE
            "
        );


    $questionStatement->execute(
        [
            (int) $attempt['exam_id']
        ]
    );


    $questions =
        $questionStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | EXACT QUESTION COUNT
    |--------------------------------------------------------------------------
    */

    $totalQuestionCount =
        count(
            $questions
        );


    if (
        $totalQuestionCount !==
        $requiredQuestionCount
    ) {

        throw new RuntimeException(
            'The examination question count is inconsistent.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE QUESTION MARKS + CALCULATE TOTAL
    |--------------------------------------------------------------------------
    |
    | Final total marks:
    |
    |     Total Questions × Per Question Marks
    |
    | The stored exams.total_marks field is NOT trusted for final grading.
    |--------------------------------------------------------------------------
    */

    $marksPerQuestion =
        null;


    foreach (
        $questions as $question
    ) {

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

            throw new RuntimeException(
                'The examination contains a question with invalid marks.'
            );
        }


        if (
            $marksPerQuestion === null
        ) {

            $marksPerQuestion =
                $questionMarks;

        } else {

            if (
                abs(
                    $marksPerQuestion -
                    $questionMarks
                ) > 0.00001
            ) {

                throw new RuntimeException(
                    'The examination must use the same marks value for every question.'
                );
            }
        }
    }


    if (
        $marksPerQuestion === null
    ) {

        throw new RuntimeException(
            'The examination contains no valid question marks.'
        );
    }


    $totalMarks =
        round(
            $totalQuestionCount *
            $marksPerQuestion,
            2
        );


    if (
        $totalMarks <= 0
    ) {

        throw new RuntimeException(
            'The examination total marks configuration is invalid.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD ANSWERS
    |--------------------------------------------------------------------------
    */

    $answerStatement =
        $conn->prepare(
            "
            SELECT

                id,

                question_id,

                selected_answer,

                question_status,

                answered_at,

                is_correct,

                marks_awarded

            FROM answers

            WHERE

                attempt_id = ?

            FOR UPDATE
            "
        );


    $answerStatement->execute(
        [
            (int) $attemptId
        ]
    );


    $answers =
        [];


    while (
        $answerRow =
            $answerStatement->fetch(
                PDO::FETCH_ASSOC
            )
    ) {

        $answerQuestionId =
            (int) $answerRow[
                'question_id'
            ];


        if (
            isset(
                $answers[
                    $answerQuestionId
                ]
            )
        ) {

            throw new RuntimeException(
                'The examination contains duplicate answer records.'
            );
        }


        $answers[
            $answerQuestionId
        ] =
            $answerRow;
    }


    /*
    |--------------------------------------------------------------------------
    | SCORE COUNTERS
    |--------------------------------------------------------------------------
    */

    $attemptedQuestions =
        0;


    $correctAnswers =
        0;


    $wrongAnswers =
        0;


    $unansweredQuestions =
        0;


    $obtainedMarks =
        0.00;


    /*
    |--------------------------------------------------------------------------
    | GRADE ALL QUESTIONS
    |--------------------------------------------------------------------------
    */

    foreach (
        $questions as $question
    ) {

        $questionId =
            (int) $question[
                'question_id'
            ];


        $answerRow =
            $answers[
                $questionId
            ]
            ?? null;


        $selectedAnswer =
            '';


        if (
            $answerRow
        ) {

            $selectedAnswer =
                strtoupper(
                    trim(
                        (string) (
                            $answerRow[
                                'selected_answer'
                            ]
                            ?? ''
                        )
                    )
                );
        }


        /*
        |--------------------------------------------------------------------------
        | INVALID / EMPTY ANSWER
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $selectedAnswer,
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
                '';
        }


        /*
        |--------------------------------------------------------------------------
        | UNANSWERED
        |--------------------------------------------------------------------------
        */

        if (
            $selectedAnswer === ''
        ) {

            $unansweredQuestions++;


            $previousStatus =
                $answerRow
                    ? (string) (
                        $answerRow[
                            'question_status'
                        ] ?? ''
                    )
                    : '';


            $isReview =
                in_array(
                    $previousStatus,
                    [
                        'Marked for Review',
                        'Answered & Marked for Review'
                    ],
                    true
                );


            $finalStatus =
                $isReview
                    ? 'Marked for Review'
                    : 'Not Answered';


            if (
                $answerRow
            ) {

                $update =
                    $conn->prepare(
                        "
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
                        "
                    );


                $update->execute(
                    [

                        $finalStatus,

                        (int) $answerRow[
                            'id'
                        ],

                        (int) $attemptId,

                        $questionId

                    ]
                );

            } else {

                $insert =
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
                        "
                    );


                $insert->execute(
                    [

                        (int) $attemptId,

                        $questionId,

                        $finalStatus

                    ]
                );
            }


            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | ATTEMPTED
        |--------------------------------------------------------------------------
        */

        $attemptedQuestions++;


        $correctAnswer =
            strtoupper(
                trim(
                    (string) (
                        $question[
                            'correct_answer'
                        ]
                        ?? ''
                    )
                )
            );


        if (
            !in_array(
                $correctAnswer,
                [
                    'A',
                    'B',
                    'C',
                    'D'
                ],
                true
            )
        ) {

            throw new RuntimeException(
                'The examination contains an invalid correct answer configuration.'
            );
        }


        $questionMarks =
            round(
                (float) (
                    $question[
                        'marks'
                    ] ?? 0
                ),
                2
            );


        $negativeMarks =
            round(
                (float) (
                    $question[
                        'negative_marks'
                    ] ?? 0
                ),
                2
            );


        if (
            $questionMarks <= 0
        ) {

            throw new RuntimeException(
                'The examination contains a question with invalid marks.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CORRECT / WRONG
        |--------------------------------------------------------------------------
        */

        $isCorrect =
            $selectedAnswer ===
            $correctAnswer;


        $marksAwarded =
            0.00;


        if (
            $isCorrect
        ) {

            $correctAnswers++;


            $marksAwarded =
                $questionMarks;

        } else {

            $wrongAnswers++;


            if (
                (int) (
                    $attempt[
                        'negative_marking'
                    ] ?? 0
                ) === 1
                &&
                $negativeMarks > 0
            ) {

                $marksAwarded =
                    -$negativeMarks;
            }
        }


        $obtainedMarks +=
            $marksAwarded;


        /*
        |--------------------------------------------------------------------------
        | PRESERVE REVIEW STATUS
        |--------------------------------------------------------------------------
        */

        $previousStatus =
            $answerRow
                ? (string) (
                    $answerRow[
                        'question_status'
                    ] ?? ''
                )
                : '';


        $isReview =
            in_array(
                $previousStatus,
                [
                    'Marked for Review',
                    'Answered & Marked for Review'
                ],
                true
            );


        $finalStatus =
            $isReview
                ? 'Answered & Marked for Review'
                : 'Answered';


        /*
        |--------------------------------------------------------------------------
        | SAVE GRADED ANSWER
        |--------------------------------------------------------------------------
        */

        if (
            $answerRow
        ) {

            $update =
                $conn->prepare(
                    "
                    UPDATE answers

                    SET

                        selected_answer = ?,

                        question_status = ?,

                        answered_at =
                            COALESCE(
                                answered_at,
                                NOW()
                            ),

                        is_correct = ?,

                        marks_awarded = ?

                    WHERE

                        id = ?

                        AND attempt_id = ?

                        AND question_id = ?
                    "
                );


            $update->execute(
                [

                    $selectedAnswer,

                    $finalStatus,

                    $isCorrect
                        ? 1
                        : 0,

                    $marksAwarded,

                    (int) $answerRow[
                        'id'
                    ],

                    (int) $attemptId,

                    $questionId

                ]
            );

        } else {

            $insert =
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

                        ?,
                        ?,

                        NOW(),

                        ?,
                        ?
                    )
                    "
                );


            $insert->execute(
                [

                    (int) $attemptId,

                    $questionId,

                    $selectedAnswer,

                    $finalStatus,

                    $isCorrect
                        ? 1
                        : 0,

                    $marksAwarded

                ]
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FINAL QUESTION COUNT CHECK
    |--------------------------------------------------------------------------
    */

    if (
        $attemptedQuestions +
        $unansweredQuestions
        !==
        $totalQuestionCount
    ) {

        throw new RuntimeException(
            'Final examination question totals are inconsistent.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FINAL SCORE
    |--------------------------------------------------------------------------
    |
    | Negative marking can reduce the raw score below zero, but the final
    | examination score is limited to a minimum of 0 and maximum of total.
    |--------------------------------------------------------------------------
    */

    $obtainedMarks =
        max(
            0.00,
            min(
                $totalMarks,
                $obtainedMarks
            )
        );


    $obtainedMarks =
        round(
            $obtainedMarks,
            2
        );


    /*
    |--------------------------------------------------------------------------
    | PERCENTAGE
    |--------------------------------------------------------------------------
    */

    $percentage =
        round(
            (
                $obtainedMarks /
                $totalMarks
            ) *
            100,
            2
        );


    $percentage =
        max(
            0.00,
            min(
                100.00,
                $percentage
            )
        );


    /*
    |--------------------------------------------------------------------------
    | GRADE
    |--------------------------------------------------------------------------
    */

    $grade =
        submit_exam_grade(
            $percentage
        );


    /*
    |--------------------------------------------------------------------------
    | PASS / FAIL
    |--------------------------------------------------------------------------
    */

    $passingMarks =
        max(
            0.00,
            (float) (
                $attempt[
                    'passing_marks'
                ] ?? 0
            )
        );


    /*
     * Passing marks should never exceed the calculated total.
     */

    $passingMarks =
        min(
            $passingMarks,
            $totalMarks
        );


    $resultStatus =
        $obtainedMarks >=
        $passingMarks
            ? 'Pass'
            : 'Fail';


    /*
    |--------------------------------------------------------------------------
    | FINAL ATTEMPT STATUS
    |--------------------------------------------------------------------------
    */

    $finalAttemptStatus =
        (
            $expired ||
            $autoSubmit
        )
            ? 'Auto Submitted'
            : 'Submitted';


    /*
    |--------------------------------------------------------------------------
    | FINALIZE ATTEMPT
    |--------------------------------------------------------------------------
    */

    $attemptUpdate =
        $conn->prepare(
            "
            UPDATE exam_attempts

            SET

                submitted_at = NOW(),

                status = ?,

                obtained_marks = ?,

                percentage = ?,

                last_activity_at = NOW()

            WHERE

                id = ?

                AND student_id = ?

                AND status = 'Started'
            "
        );


    $attemptUpdate->execute(
        [

            $finalAttemptStatus,

            $obtainedMarks,

            $percentage,

            (int) $attemptId,

            $studentId

        ]
    );


    if (
        $attemptUpdate->rowCount() !== 1
    ) {

        throw new RuntimeException(
            'The examination could not be finalized.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE OR UPDATE RESULT
    |--------------------------------------------------------------------------
    */

    $existingResultStatement =
        $conn->prepare(
            "
            SELECT

                id

            FROM results

            WHERE

                attempt_id = ?

                AND student_id = ?

            LIMIT 1

            FOR UPDATE
            "
        );


    $existingResultStatement->execute(
        [

            (int) $attemptId,

            $studentId

        ]
    );


    $resultId =
        $existingResultStatement
            ->fetchColumn();


    if (
        $resultId !== false &&
        $resultId !== null
    ) {

        $resultId =
            (int) $resultId;


        $resultUpdate =
            $conn->prepare(
                "
                UPDATE results

                SET

                    student_id = ?,

                    exam_id = ?,

                    total_questions = ?,

                    attempted_questions = ?,

                    correct_answers = ?,

                    wrong_answers = ?,

                    unanswered_questions = ?,

                    total_marks = ?,

                    obtained_marks = ?,

                    percentage = ?,

                    grade = ?,

                    result_status = ?

                WHERE

                    id = ?

                    AND attempt_id = ?

                    AND student_id = ?
                "
            );


        $resultUpdate->execute(
            [

                $studentId,

                (int) $attempt[
                    'exam_id'
                ],

                $totalQuestionCount,

                $attemptedQuestions,

                $correctAnswers,

                $wrongAnswers,

                $unansweredQuestions,

                $totalMarks,

                $obtainedMarks,

                $percentage,

                $grade,

                $resultStatus,

                $resultId,

                (int) $attemptId,

                $studentId

            ]
        );

    } else {

        $resultInsert =
            $conn->prepare(
                "
                INSERT INTO results
                (
                    attempt_id,

                    student_id,
                    exam_id,

                    total_questions,

                    attempted_questions,
                    correct_answers,
                    wrong_answers,
                    unanswered_questions,

                    total_marks,
                    obtained_marks,
                    percentage,

                    grade,
                    result_status
                )

                VALUES
                (
                    ?,

                    ?,
                    ?,

                    ?,

                    ?,
                    ?,
                    ?,
                    ?,

                    ?,
                    ?,
                    ?,

                    ?,
                    ?
                )
                "
            );


        $resultInsert->execute(
            [

                (int) $attemptId,

                $studentId,

                (int) $attempt[
                    'exam_id'
                ],

                $totalQuestionCount,

                $attemptedQuestions,

                $correctAnswers,

                $wrongAnswers,

                $unansweredQuestions,

                $totalMarks,

                $obtainedMarks,

                $percentage,

                $grade,

                $resultStatus

            ]
        );


        $resultId =
            (int) $conn->lastInsertId();
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY RESULT
    |--------------------------------------------------------------------------
    */

    if (
        $resultId <= 0
    ) {

        throw new RuntimeException(
            'The examination result could not be created.'
        );
    }


    $verifyStatement =
        $conn->prepare(
            "
            SELECT

                id,

                attempt_id,

                student_id,
                exam_id,

                total_questions,

                attempted_questions,
                correct_answers,
                wrong_answers,
                unanswered_questions,

                total_marks,
                obtained_marks,

                percentage,

                grade,

                result_status

            FROM results

            WHERE

                id = ?

                AND attempt_id = ?

                AND student_id = ?

            LIMIT 1
            "
        );


    $verifyStatement->execute(
        [

            $resultId,

            (int) $attemptId,

            $studentId

        ]
    );


    $result =
        $verifyStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$result
    ) {

        throw new RuntimeException(
            'The final examination result could not be verified.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY QUESTION COUNT
    |--------------------------------------------------------------------------
    */

    if (
        (int) $result[
            'total_questions'
        ]
        !==
        $totalQuestionCount
    ) {

        throw new RuntimeException(
            'The final result question count is inconsistent.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY ATTEMPTED + UNANSWERED
    |--------------------------------------------------------------------------
    */

    if (
        (
            (int) $result[
                'attempted_questions'
            ]
            +
            (int) $result[
                'unanswered_questions'
            ]
        )
        !==
        $totalQuestionCount
    ) {

        throw new RuntimeException(
            'The final result question totals are inconsistent.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY SCORE VALUES
    |--------------------------------------------------------------------------
    */

    $storedTotalMarks =
        round(
            (float) $result[
                'total_marks'
            ],
            2
        );


    $storedObtainedMarks =
        round(
            (float) $result[
                'obtained_marks'
            ],
            2
        );


    if (
        abs(
            $storedTotalMarks -
            $totalMarks
        ) > 0.01
    ) {

        throw new RuntimeException(
            'The final result total marks are inconsistent.'
        );
    }


    if (
        abs(
            $storedObtainedMarks -
            $obtainedMarks
        ) > 0.01
    ) {

        throw new RuntimeException(
            'The final result obtained marks are inconsistent.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE LAST ACTIVITY
    |--------------------------------------------------------------------------
    */

    $activityUpdate =
        $conn->prepare(
            "
            UPDATE exam_attempts

            SET

                last_activity_at = NOW()

            WHERE

                id = ?

                AND student_id = ?
            "
        );


    $activityUpdate->execute(
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
    | RESULT REDIRECT
    |--------------------------------------------------------------------------
    */

    $redirect =
        '../result.php?id=' .
        $resultId;


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    submit_exam_json(

        true,

        (
            $expired ||
            $autoSubmit
        )
            ? 'Examination submitted automatically.'
            : 'Examination submitted successfully.',

        [

            'result_id' =>
                $resultId,

            'attempt_id' =>
                (int) $attemptId,

            'exam_id' =>
                (int) $attempt[
                    'exam_id'
                ],

            'total_questions' =>
                $totalQuestionCount,

            'marks_per_question' =>
                $marksPerQuestion,

            'attempted_questions' =>
                $attemptedQuestions,

            'correct_answers' =>
                $correctAnswers,

            'wrong_answers' =>
                $wrongAnswers,

            'unanswered_questions' =>
                $unansweredQuestions,

            'total_marks' =>
                $totalMarks,

            'obtained_marks' =>
                $obtainedMarks,

            'percentage' =>
                $percentage,

            'grade' =>
                $grade,

            'passing_marks' =>
                $passingMarks,

            'result_status' =>
                $resultStatus,

            'attempt_status' =>
                $finalAttemptStatus,

            'server_time' =>
                $serverNow->format(
                    DateTimeInterface::ATOM
                ),

            'server_deadline' =>
                $deadline->format(
                    DateTimeInterface::ATOM
                ),

            'redirect' =>
                $redirect

        ]
    );


} catch (
    Throwable $exception
) {

    if (
        $conn->inTransaction()
    ) {

        $conn->rollBack();
    }


    error_log(
        'ExamSphere submit exam failed: ' .
        $exception->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | SAFE ERROR MESSAGES
    |--------------------------------------------------------------------------
    */

    $safeMessages = [

        'Examination attempt not found.',

        'You are not allowed to submit this examination.',

        'This examination attempt is no longer active.',

        'This examination is no longer available.',

        'Examination deadline is invalid.',

        'Examination timing information is missing.',

        'The examination has an invalid required question count.',

        'The examination question count is inconsistent.',

        'The examination contains duplicate answer records.',

        'The examination must use the same marks value for every question.',

        'The examination contains a question with invalid marks.',

        'The examination contains no valid question marks.',

        'The examination total marks configuration is invalid.',

        'The examination contains an invalid correct answer configuration.',

        'Final examination question totals are inconsistent.',

        'The examination could not be finalized.',

        'The examination result could not be created.',

        'The final examination result could not be verified.',

        'The final result question count is inconsistent.',

        'The final result question totals are inconsistent.',

        'The final result total marks are inconsistent.',

        'The final result obtained marks are inconsistent.'

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
            'Unable to submit the examination. Please try again.';
    }


    submit_exam_json(
        false,
        $message,
        [],
        400
    );
}