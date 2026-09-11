<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| SESSION + DATABASE
|--------------------------------------------------------------------------
*/

require_once '../config/session.php';
require_once '../config/config.php';


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {

    header(
        'Location: ../auth/login.php'
    );

    exit;
}


$studentId =
    (int)$_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    header(
        'Location: dashboard.php'
    );

    exit;
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


$csrfToken =
    (string)(
        $_POST['csrf_token']
        ?? ''
    );


$submittedAnswers =
    $_POST['answer']
    ?? [];


$autoSubmit =
    (
        (string)(
            $_POST['auto_submit']
            ?? '0'
        )
    ) === '1';


if (
    $attemptId === false ||
    $attemptId === null ||
    $attemptId <= 0
) {

    http_response_code(400);

    exit(
        'Invalid examination attempt.'
    );
}


if (
    !is_array(
        $submittedAnswers
    )
) {

    $submittedAnswers =
        [];
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$sessionToken =
    (string)(
        $_SESSION[
            'exam_csrf_token'
        ]
        ?? ''
    );


if (
    $sessionToken === '' ||
    $csrfToken === '' ||
    !hash_equals(
        $sessionToken,
        $csrfToken
    )
) {

    http_response_code(419);

    exit(
        'Invalid security token.'
    );
}


/*
|--------------------------------------------------------------------------
| GRADE
|--------------------------------------------------------------------------
*/

function calculateExamGrade(
    float $percentage
): string {

    if (
        $percentage >= 90
    ) {

        return 'A+';
    }


    if (
        $percentage >= 75
    ) {

        return 'A';
    }


    if (
        $percentage >= 60
    ) {

        return 'B';
    }


    if (
        $percentage >= 40
    ) {

        return 'C';
    }


    return 'F';
}


/*
|--------------------------------------------------------------------------
| RESULT REDIRECT
|--------------------------------------------------------------------------
*/

function redirectToResult(
    int $resultId
): never {

    header(
        'Location: result.php?id=' .
        $resultId
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| MAIN TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $conn->beginTransaction();


    /*
     * ------------------------------------------------------------
     * Load and lock the attempt.
     * ------------------------------------------------------------
     */
    $attemptStatement =
        $conn->prepare("
            SELECT

                ea.id AS attempt_id,
                ea.student_id,
                ea.exam_id,

                ea.started_at,
                ea.server_deadline,
                ea.submitted_at,

                ea.status,

                e.title AS exam_title,
                e.exam_type,

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

            FOR UPDATE
        ");


    $attemptStatement->execute([

        $attemptId,

        $studentId

    ]);


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
     * ------------------------------------------------------------
     * Already submitted?
     * ------------------------------------------------------------
     */
    if (
        $attempt['status'] !==
        'Started'
    ) {

        $existingResultStatement =
            $conn->prepare("
                SELECT

                    id

                FROM results

                WHERE
                    attempt_id = ?

                LIMIT 1
            ");


        $existingResultStatement->execute([
            $attemptId
        ]);


        $existingResultId =
            $existingResultStatement->fetchColumn();


        $conn->commit();


        if (
            $existingResultId
        ) {

            redirectToResult(
                (int)$existingResultId
            );
        }


        exit(
            'This examination attempt has already been closed.'
        );
    }


    /*
     * ------------------------------------------------------------
     * Determine official server deadline.
     * ------------------------------------------------------------
     */
    if (
        !empty(
            $attempt[
                'server_deadline'
            ]
        )
    ) {

        $serverDeadline =
            new DateTimeImmutable(
                (string)(
                    $attempt[
                        'server_deadline'
                    ]
                )
            );

    } else {

        /*
         * Compatibility for older attempts.
         */
        $startedAt =
            new DateTimeImmutable(
                (string)$attempt[
                    'started_at'
                ]
            );


        $serverDeadline =
            $startedAt->modify(
                '+' .
                (int)$attempt[
                    'duration_minutes'
                ] .
                ' minutes'
            );
    }


    $serverNow =
        new DateTimeImmutable();


    $isExpired =
        $serverNow >=
        $serverDeadline;


    /*
     * ------------------------------------------------------------
     * Load exact exam question set.
     * ------------------------------------------------------------
     */
    $questionStatement =
        $conn->prepare("
            SELECT

                eq.question_id,

                q.correct_answer,
                q.marks,
                q.negative_marks

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE

                eq.exam_id = ?

                AND q.status = 'Active'

            ORDER BY

                eq.position ASC,
                q.id ASC
        ");


    $questionStatement->execute([

        (int)$attempt[
            'exam_id'
        ]

    ]);


    $questions =
        $questionStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    $questionCount =
        count($questions);


    $requiredCount =
        (int)$attempt[
            'required_question_count'
        ];


    /*
     * The exam must contain exactly the configured
     * number of active questions.
     */
    if (
        $requiredCount <= 0
        ||
        $questionCount !==
        $requiredCount
    ) {

        throw new RuntimeException(
            'The examination question set is invalid.'
        );
    }


    /*
     * ------------------------------------------------------------
     * Build valid question map.
     * ------------------------------------------------------------
     */
    $questionMap =
        [];


    foreach (
        $questions as $question
    ) {

        $questionMap[
            (int)$question[
                'question_id'
            ]
        ] =
            $question;
    }


    /*
     * ------------------------------------------------------------
     * Synchronize latest browser answers.
     *
     * Browser values are only used to update the answer choices.
     * Marks are NEVER accepted from the browser.
     * ------------------------------------------------------------
     */

    $existingAnswerStatement =
        $conn->prepare("
            SELECT

                id,
                question_id,
                selected_answer,
                question_status

            FROM answers

            WHERE
                attempt_id = ?

            ORDER BY
                id ASC

            FOR UPDATE
        ");


    $existingAnswerStatement->execute([
        $attemptId
    ]);


    $answerRows =
        [];


    while (
        $row =
            $existingAnswerStatement->fetch(
                PDO::FETCH_ASSOC
            )
    ) {

        $answerRows[
            (int)$row[
                'question_id'
            ]
        ] =
            $row;

    }


    /*
     * Update only valid browser answers.
     */
    $updateBrowserAnswer =
        $conn->prepare("
            UPDATE answers

            SET

                selected_answer = ?,

                question_status =
                    CASE

                        WHEN question_status =
                            'Marked for Review'

                        THEN
                            'Answered & Marked for Review'

                        ELSE
                            'Answered'

                    END,

                answered_at =
                    COALESCE(
                        answered_at,
                        NOW()
                    )

            WHERE

                id = ?

                AND attempt_id = ?
        ");


    $insertBrowserAnswer =
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
                ?,
                'Answered',
                NOW(),
                0,
                0
            )
        ");


    foreach (
        $submittedAnswers
        as $questionIdKey =>
        $submittedAnswer
    ) {

        $questionId =
            filter_var(
                $questionIdKey,
                FILTER_VALIDATE_INT
            );


        if (
            $questionId === false ||
            $questionId <= 0
        ) {

            continue;
        }


        /*
         * Never accept a question ID that does not
         * belong to this examination.
         */
        if (
            !isset(
                $questionMap[
                    $questionId
                ]
            )
        ) {

            continue;
        }


        $answer =
            strtoupper(
                trim(
                    (string)$submittedAnswer
                )
            );


        /*
         * Only A/B/C/D are valid.
         */
        if (
            !in_array(
                $answer,
                [
                    'A',
                    'B',
                    'C',
                    'D'
                ],
                true
            )
        ) {

            continue;
        }


        if (
            isset(
                $answerRows[
                    $questionId
                ]
            )
        ) {

            $updateBrowserAnswer->execute([

                $answer,

                (int)$answerRows[
                    $questionId
                ]['id'],

                $attemptId

            ]);

        } else {

            $insertBrowserAnswer->execute([

                $attemptId,

                $questionId,

                $answer

            ]);

        }

    }


    /*
     * ------------------------------------------------------------
     * Reload answers AFTER synchronization.
     *
     * This is now the source of truth.
     * ------------------------------------------------------------
     */
    $finalAnswerStatement =
        $conn->prepare("
            SELECT

                id,
                question_id,
                selected_answer,
                question_status

            FROM answers

            WHERE
                attempt_id = ?

            ORDER BY
                id ASC

            FOR UPDATE
        ");


    $finalAnswerStatement->execute([
        $attemptId
    ]);


    $finalAnswers =
        [];


    while (
        $row =
            $finalAnswerStatement->fetch(
                PDO::FETCH_ASSOC
            )
    ) {

        /*
         * Latest row wins if an old database accidentally
         * contains duplicates.
         */
        $finalAnswers[
            (int)$row[
                'question_id'
            ]
        ] =
            $row;
    }


    /*
     * ------------------------------------------------------------
     * Prepare result calculations.
     * ------------------------------------------------------------
     */

    $attempted =
        0;

    $correct =
        0;

    $wrong =
        0;

    $unanswered =
        0;

    $obtainedMarks =
        0.0;

    $maximumMarks =
        0.0;


    /*
     * Preload calculation statement.
     */
    $updateAnswerResult =
        $conn->prepare("
            UPDATE answers

            SET

                is_correct = ?,

                marks_awarded = ?,

                question_status = ?,

                answered_at =
                    CASE

                        WHEN ? IN (
                            'Answered',
                            'Answered & Marked for Review'
                        )

                        THEN COALESCE(
                            answered_at,
                            NOW()
                        )

                        ELSE answered_at

                    END

            WHERE

                id = ?

                AND attempt_id = ?
        ");


    /*
     * ------------------------------------------------------------
     * Grade EVERY exam question.
     * ------------------------------------------------------------
     */
    foreach (
        $questions as $question
    ) {

        $questionId =
            (int)$question[
                'question_id'
            ];


        $questionMarks =
            (float)$question[
                'marks'
            ];


        $maximumMarks +=
            $questionMarks;


        /*
         * No answer row.
         */
        if (
            !isset(
                $finalAnswers[
                    $questionId
                ]
            )
        ) {

            $unanswered++;

            continue;
        }


        $answerRow =
            $finalAnswers[
                $questionId
            ];


        $selectedAnswer =
            strtoupper(
                trim(
                    (string)(
                        $answerRow[
                            'selected_answer'
                        ] ?? ''
                    )
                )
            );


        /*
         * Blank / invalid answer.
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

            $unanswered++;

            continue;
        }


        $attempted++;


        /*
         * Correct answer.
         */
        $isCorrect =
            $selectedAnswer ===
            strtoupper(
                (string)$question[
                    'correct_answer'
                ]
            );


        if (
            $isCorrect
        ) {

            $correct++;


            $marksAwarded =
                $questionMarks;

        } else {

            $wrong++;


            /*
             * Negative marking is controlled by the exam.
             */
            if (
                (int)$attempt[
                    'negative_marking'
                ] === 1
            ) {

                $marksAwarded =
                    -(
                        (float)$question[
                            'negative_marks'
                        ]
                    );

            } else {

                $marksAwarded =
                    0.0;
            }
        }


        $obtainedMarks +=
            $marksAwarded;


        /*
         * Preserve review state.
         */
        $wasReviewed =
            in_array(
                (string)(
                    $answerRow[
                        'question_status'
                    ]
                ),
                [
                    'Marked for Review',
                    'Answered & Marked for Review'
                ],
                true
            );


        $newStatus =
            $wasReviewed

                ? 'Answered & Marked for Review'

                : 'Answered';


        $updateAnswerResult->execute([

            $isCorrect
                ? 1
                : 0,

            $marksAwarded,

            $newStatus,

            $newStatus,

            (int)$answerRow[
                'id'
            ],

            $attemptId

        ]);

    }


    /*
     * ------------------------------------------------------------
     * Ensure unanswered count is correct.
     * ------------------------------------------------------------
     */
    $unanswered =
        max(
            0,
            $questionCount -
            $attempted
        );


    /*
     * ------------------------------------------------------------
     * Total marks.
     *
     * Use the database question marks as the fallback.
     * ------------------------------------------------------------
     */
    $examTotalMarks =
        (float)$attempt[
            'total_marks'
        ];


    if (
        $examTotalMarks <= 0
    ) {

        $examTotalMarks =
            $maximumMarks;
    }


    /*
     * ------------------------------------------------------------
     * Percentage.
     * ------------------------------------------------------------
     */
    $percentage =
        $examTotalMarks > 0

            ? round(
                (
                    $obtainedMarks /
                    $examTotalMarks
                ) * 100,
                2
            )

            : 0.0;


    /*
     * Don't display impossible percentage.
     */
    $percentage =
        max(
            0.0,
            min(
                100.0,
                $percentage
            )
        );


    /*
     * ------------------------------------------------------------
     * Grade
     * ------------------------------------------------------------
     */
    $grade =
        calculateExamGrade(
            $percentage
        );


    /*
     * ------------------------------------------------------------
     * Pass / Fail
     * ------------------------------------------------------------
     */
    $passingMarks =
        (float)$attempt[
            'passing_marks'
        ];


    $resultStatus =
        $obtainedMarks >=
        $passingMarks

            ? 'Pass'

            : 'Fail';


    /*
     * ------------------------------------------------------------
     * Final attempt status
     * ------------------------------------------------------------
     */
    $attemptStatus =
        (
            $isExpired ||
            $autoSubmit
        )

            ? 'Auto Submitted'

            : 'Submitted';


    /*
     * ------------------------------------------------------------
     * Update attempt.
     *
     * The WHERE status='Started' ensures that this request
     * cannot finalize the same attempt twice.
     * ------------------------------------------------------------
     */
    $updateAttempt =
        $conn->prepare("
            UPDATE exam_attempts

            SET

                status = ?,

                submitted_at = NOW(),

                last_activity_at = NOW(),

                obtained_marks = ?,

                percentage = ?

            WHERE

                id = ?

                AND student_id = ?

                AND status = 'Started'
        ");


    $updateAttempt->execute([

        $attemptStatus,

        $obtainedMarks,

        $percentage,

        $attemptId,

        $studentId

    ]);


    /*
     * If nothing changed, another request may have finalized it.
     */
    if (
        $updateAttempt->rowCount() !== 1
    ) {

        /*
         * Check result created by previous request.
         */
        $duplicateResultStatement =
            $conn->prepare("
                SELECT

                    id

                FROM results

                WHERE
                    attempt_id = ?

                LIMIT 1
            ");


        $duplicateResultStatement->execute([
            $attemptId
        ]);


        $duplicateResultId =
            $duplicateResultStatement->fetchColumn();


        if (
            $duplicateResultId
        ) {

            $conn->commit();


            redirectToResult(
                (int)$duplicateResultId
            );
        }


        throw new RuntimeException(
            'The examination could not be finalized.'
        );
    }


    /*
     * ------------------------------------------------------------
     * Create one result only.
     * ------------------------------------------------------------
     */
    $existingResultStatement =
        $conn->prepare("
            SELECT

                id

            FROM results

            WHERE
                attempt_id = ?

            LIMIT 1
        ");


    $existingResultStatement->execute([
        $attemptId
    ]);


    $existingResultId =
        $existingResultStatement->fetchColumn();


    /*
     * Result already exists.
     */
    if (
        $existingResultId
    ) {

        $resultId =
            (int)$existingResultId;

    } else {

        /*
         * Generate secure certificate hash.
         */
        $certificateHash =
            hash(
                'sha256',
                $attemptId .
                '|' .
                $studentId .
                '|' .
                bin2hex(
                    random_bytes(16)
                )
            );


        $insertResultStatement =
            $conn->prepare("
                INSERT INTO results
                (
                    certificate_hash,
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

                    ?,
                    ?
                )
            ");


        try {

            $insertResultStatement->execute([

                $certificateHash,

                $attemptId,

                $studentId,

                (int)$attempt[
                    'exam_id'
                ],

                $questionCount,

                $attempted,

                $correct,

                $wrong,

                $unanswered,

                $examTotalMarks,

                $obtainedMarks,

                $percentage,

                $grade,

                $resultStatus

            ]);


            $resultId =
                (int)$conn->lastInsertId();


        } catch (
            PDOException $exception
        ) {

            /*
             * Because results.attempt_id is UNIQUE,
             * a concurrent submission can reach this point.
             *
             * Instead of failing, fetch the existing result.
             */
            if (
                (int)$exception->errorInfo[1] ===
                1062
            ) {

                $existingAfterRace =
                    $conn->prepare("
                        SELECT

                            id

                        FROM results

                        WHERE
                            attempt_id = ?

                        LIMIT 1
                    ");


                $existingAfterRace->execute([
                    $attemptId
                ]);


                $resultId =
                    (int)(
                        $existingAfterRace->fetchColumn()
                    );


                if (
                    $resultId <= 0
                ) {

                    throw $exception;
                }

            } else {

                throw $exception;
            }
        }
    }


    /*
     * ------------------------------------------------------------
     * Commit ATOMIC transaction.
     * ------------------------------------------------------------
     */
    $conn->commit();


    /*
     * ------------------------------------------------------------
     * Redirect.
     * ------------------------------------------------------------
     */
    redirectToResult(
        $resultId
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
        'Final examination submission failed: ' .
        $exception->getMessage()
    );


    http_response_code(400);


    exit(
        'Unable to finalize the examination. Please try again.'
    );
}