<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/exam_validation.php';


/*
|--------------------------------------------------------------------------
| STUDENT AUTHENTICATION
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
| EXAM ID
|--------------------------------------------------------------------------
*/

$examId =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );


if (
    $examId === false ||
    $examId === null ||
    $examId <= 0
) {

    http_response_code(400);

    exit(
        'Invalid exam ID.'
    );
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function start_exam_escape(
    mixed $value
): string {

    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function start_exam_fail(
    string $message,
    int $statusCode = 403
): never {

    http_response_code(
        $statusCode
    );

    exit(
        start_exam_escape(
            $message
        )
    );
}


/*
|--------------------------------------------------------------------------
| LOAD EXAM
|--------------------------------------------------------------------------
*/

try {

    $examStatement =
        $conn->prepare("
            SELECT

                e.id,

                e.subject_id,
                e.teacher_id,

                e.title,
                e.description,

                e.exam_type,
                e.status,

                e.duration_minutes,

                e.required_question_count,

                e.total_marks,
                e.passing_marks,

                e.negative_marking,

                e.exam_fee,
                e.subscription_required,

                e.starts_at,
                e.ends_at

            FROM exams e

            WHERE
                e.id = ?

            LIMIT 1
        ");


    $examStatement->execute([
        $examId
    ]);


    $exam =
        $examStatement->fetch(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Start exam load failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load examination.'
    );
}


if (
    !$exam
) {

    http_response_code(404);

    exit(
        'Examination not found.'
    );
}


/*
|--------------------------------------------------------------------------
| NORMALIZE
|--------------------------------------------------------------------------
*/

$examType =
    trim(
        (string)(
            $exam['exam_type']
            ?? ''
        )
    );


$examStatus =
    trim(
        (string)(
            $exam['status']
            ?? ''
        )
    );


$durationMinutes =
    (int)(
        $exam['duration_minutes']
        ?? 0
    );


$requiredQuestionCount =
    (int)(
        $exam['required_question_count']
        ?? 0
    );


$examTotalMarks =
    round(
        (float)(
            $exam['total_marks']
            ?? 0
        ),
        2
    );


$examPassingMarks =
    round(
        (float)(
            $exam['passing_marks']
            ?? 0
        ),
        2
    );


$subscriptionRequired =
    (int)(
        $exam['subscription_required']
        ?? 0
    );


/*
|--------------------------------------------------------------------------
| EXAM TYPE
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $examType,
        [
            'Practice',
            'Live'
        ],
        true
    )
) {

    start_exam_fail(
        'This examination has an invalid examination type.',
        422
    );
}


/*
|--------------------------------------------------------------------------
| EXAM STATUS
|--------------------------------------------------------------------------
|
| Practice:
| Active only.
|
| Live:
| Active or Live.
|
|--------------------------------------------------------------------------
*/

if (
    $examType === 'Practice'
) {

    $allowedStatuses = [
        'Active'
    ];

} else {

    $allowedStatuses = [
        'Active',
        'Live'
    ];
}


if (
    !in_array(
        $examStatus,
        $allowedStatuses,
        true
    )
) {

    start_exam_fail(
        'This examination is not currently available.'
    );
}


/*
|--------------------------------------------------------------------------
| HARD 50-QUESTION RULE
|--------------------------------------------------------------------------
*/

if (
    $requiredQuestionCount !==
    EXAM_REQUIRED_QUESTION_COUNT
) {

    start_exam_fail(
        'This examination has an invalid configuration. Every ExamSphere examination must require exactly ' .
        EXAM_REQUIRED_QUESTION_COUNT .
        ' questions.',
        422
    );
}


/*
|--------------------------------------------------------------------------
| BASIC EXAM VALUES
|--------------------------------------------------------------------------
*/

if (
    $durationMinutes <= 0
) {

    start_exam_fail(
        'This examination has an invalid duration. Please contact the administrator.',
        422
    );
}


if (
    $examTotalMarks <= 0
) {

    start_exam_fail(
        'This examination has an invalid total marks value. Please contact the administrator.',
        422
    );
}


if (
    $examPassingMarks < 0 ||
    $examPassingMarks >
    $examTotalMarks
) {

    start_exam_fail(
        'This examination has an invalid passing marks value. Please contact the administrator.',
        422
    );
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
| START / END SCHEDULE
|--------------------------------------------------------------------------
*/

$startsAt =
    null;


$endsAt =
    null;


if (
    !empty(
        $exam['starts_at']
    )
) {

    try {

        $startsAt =
            new DateTimeImmutable(
                (string)(
                    $exam['starts_at']
                )
            );

    } catch (Throwable $exception) {

        start_exam_fail(
            'This examination has an invalid start schedule. Please contact the administrator.',
            422
        );
    }
}


if (
    !empty(
        $exam['ends_at']
    )
) {

    try {

        $endsAt =
            new DateTimeImmutable(
                (string)(
                    $exam['ends_at']
                )
            );

    } catch (Throwable $exception) {

        start_exam_fail(
            'This examination has an invalid end schedule. Please contact the administrator.',
            422
        );
    }
}


/*
|--------------------------------------------------------------------------
| START TIME
|--------------------------------------------------------------------------
*/

if (
    $startsAt !== null &&
    $now < $startsAt
) {

    start_exam_fail(
        'This examination has not started yet.'
    );
}


/*
|--------------------------------------------------------------------------
| END TIME
|--------------------------------------------------------------------------
*/

if (
    $endsAt !== null &&
    $now > $endsAt
) {

    start_exam_fail(
        'This examination has ended.'
    );
}


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

try {

    if (
        $examType === 'Live'
    ) {

        $accessMessage =
            live_exam_access_message(
                $conn,
                $studentId,
                $exam
            );


        if (
            trim(
                $accessMessage
            ) !== ''
        ) {

            start_exam_fail(
                $accessMessage
            );
        }

    } elseif (
        $subscriptionRequired === 1
    ) {

        if (
            !has_active_subscription(
                $conn,
                $studentId
            )
        ) {

            start_exam_fail(
                'An active subscription is required for this practice exam.'
            );
        }
    }

} catch (Throwable $exception) {

    error_log(
        'Start exam access validation failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to verify examination access.'
    );
}


/*
|--------------------------------------------------------------------------
| EXAM CONFIGURATION CHECK
|--------------------------------------------------------------------------
|
| Authoritative database check:
|
| EXACTLY 50 DISTINCT QUESTIONS
| ALL ACTIVE
| EXACT TOTAL MARKS
|
|--------------------------------------------------------------------------
*/

try {

    $configurationStatement =
        $conn->prepare("
            SELECT

                COUNT(
                    DISTINCT eq.question_id
                ) AS question_count,

                COUNT(
                    CASE
                        WHEN q.status = 'Active'
                        THEN 1
                    END
                ) AS active_question_count,

                COALESCE(
                    SUM(
                        CASE
                            WHEN q.status = 'Active'
                            THEN q.marks
                            ELSE 0
                        END
                    ),
                    0
                ) AS active_marks

            FROM exam_questions eq

            LEFT JOIN questions q
                ON q.id = eq.question_id

            WHERE
                eq.exam_id = ?
        ");


    $configurationStatement->execute([
        $examId
    ]);


    $configuration =
        $configurationStatement->fetch(
            PDO::FETCH_ASSOC
        );


    $questionCount =
        (int)(
            $configuration[
                'question_count'
            ] ?? 0
        );


    $activeQuestionCount =
        (int)(
            $configuration[
                'active_question_count'
            ] ?? 0
        );


    $activeMarks =
        round(
            (float)(
                $configuration[
                    'active_marks'
                ] ?? 0
            ),
            2
        );


} catch (Throwable $exception) {

    error_log(
        'Start exam configuration validation failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to verify examination configuration.'
    );
}


/*
|--------------------------------------------------------------------------
| DISTINCT QUESTION COUNT
|--------------------------------------------------------------------------
*/

if (
    $questionCount !==
    EXAM_REQUIRED_QUESTION_COUNT
) {

    start_exam_fail(
        'This examination is not ready. It must contain exactly ' .
        EXAM_REQUIRED_QUESTION_COUNT .
        ' distinct questions, but currently contains ' .
        $questionCount .
        '.',
        422
    );
}


/*
|--------------------------------------------------------------------------
| ACTIVE QUESTION COUNT
|--------------------------------------------------------------------------
*/

if (
    $activeQuestionCount !==
    EXAM_REQUIRED_QUESTION_COUNT
) {

    start_exam_fail(
        'This examination is not ready because all 50 assigned questions must be Active.',
        422
    );
}


/*
|--------------------------------------------------------------------------
| EXACT MARK TOTAL
|--------------------------------------------------------------------------
*/

if (
    abs(
        $activeMarks -
        $examTotalMarks
    ) >
    0.000001
) {

    start_exam_fail(
        'This examination is not ready. Its 50 active questions total ' .
        number_format(
            $activeMarks,
            2
        ) .
        ' marks, but the exam is configured for exactly ' .
        number_format(
            $examTotalMarks,
            2
        ) .
        ' marks.',
        422
    );
}


/*
|--------------------------------------------------------------------------
| VERIFY CENTRAL VALIDATION
|--------------------------------------------------------------------------
*/

try {

    $centralValidation =
        validate_exam_from_database(
            $conn,
            $examId
        );

    if (
        !$centralValidation['valid']
    ) {

        start_exam_fail(
            'This examination is not ready: ' .
            (
                $centralValidation[
                    'validation'
                ]['message']
                ??
                'Invalid examination configuration.'
            ),
            422
        );
    }

} catch (Throwable $exception) {

    error_log(
        'Central exam validation failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to validate examination.'
    );
}


/*
|--------------------------------------------------------------------------
| TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | LOCK EXAM
    |--------------------------------------------------------------------------
    */

    $lockedExamStatement =
        $conn->prepare("
            SELECT

                id,

                subject_id,
                teacher_id,

                exam_type,
                status,

                duration_minutes,

                required_question_count,

                total_marks,
                passing_marks,

                subscription_required,

                starts_at,
                ends_at

            FROM exams

            WHERE
                id = ?

            LIMIT 1

            FOR UPDATE
        ");


    $lockedExamStatement->execute([
        $examId
    ]);


    $lockedExam =
        $lockedExamStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$lockedExam
    ) {

        throw new RuntimeException(
            'The examination no longer exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RE-CHECK FIXED QUESTION COUNT
    |--------------------------------------------------------------------------
    */

    $lockedRequiredQuestionCount =
        (int)(
            $lockedExam[
                'required_question_count'
            ] ?? 0
        );


    if (
        $lockedRequiredQuestionCount !==
        EXAM_REQUIRED_QUESTION_COUNT
    ) {

        throw new RuntimeException(
            'This examination is incorrectly configured. Exactly 50 questions are required.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RE-CHECK TYPE / STATUS
    |--------------------------------------------------------------------------
    */

    $lockedExamType =
        trim(
            (string)(
                $lockedExam[
                    'exam_type'
                ] ?? ''
            )
        );


    $lockedStatus =
        trim(
            (string)(
                $lockedExam[
                    'status'
                ] ?? ''
            )
        );


    $lockedAllowedStatuses =
        $lockedExamType === 'Live'

            ? [
                'Active',
                'Live'
            ]

            : [
                'Active'
            ];


    if (
        !in_array(
            $lockedStatus,
            $lockedAllowedStatuses,
            true
        )
    ) {

        throw new RuntimeException(
            'This examination is no longer available.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RE-CHECK SCHEDULE
    |--------------------------------------------------------------------------
    */

    $transactionNow =
        new DateTimeImmutable();


    if (
        !empty(
            $lockedExam['starts_at']
        )
    ) {

        $lockedStartsAt =
            new DateTimeImmutable(
                (string)(
                    $lockedExam[
                        'starts_at'
                    ]
                )
            );


        if (
            $transactionNow <
            $lockedStartsAt
        ) {

            throw new RuntimeException(
                'This examination has not started yet.'
            );
        }
    }


    if (
        !empty(
            $lockedExam['ends_at']
        )
    ) {

        $lockedEndsAt =
            new DateTimeImmutable(
                (string)(
                    $lockedExam[
                        'ends_at'
                    ]
                )
            );


        if (
            $transactionNow >
            $lockedEndsAt
        ) {

            throw new RuntimeException(
                'This examination has ended.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RE-CHECK ACCESS
    |--------------------------------------------------------------------------
    */

    if (
        $lockedExamType === 'Live'
    ) {

        $lockedAccessMessage =
            live_exam_access_message(
                $conn,
                $studentId,
                $lockedExam
            );


        if (
            trim(
                $lockedAccessMessage
            ) !== ''
        ) {

            throw new RuntimeException(
                $lockedAccessMessage
            );
        }

    } elseif (
        (int)(
            $lockedExam[
                'subscription_required'
            ] ?? 0
        ) === 1
    ) {

        if (
            !has_active_subscription(
                $conn,
                $studentId
            )
        ) {

            throw new RuntimeException(
                'An active subscription is required for this practice exam.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RE-CHECK QUESTION CONFIGURATION
    |--------------------------------------------------------------------------
    */

    $lockedQuestionStatement =
        $conn->prepare("
            SELECT

                COUNT(
                    DISTINCT eq.question_id
                ) AS total_questions,

                COUNT(
                    DISTINCT
                    CASE
                        WHEN q.status = 'Active'
                        THEN eq.question_id
                    END
                ) AS active_questions,

                COALESCE(
                    SUM(
                        CASE
                            WHEN q.status = 'Active'
                            THEN q.marks
                            ELSE 0
                        END
                    ),
                    0
                ) AS active_marks

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE
                eq.exam_id = ?
        ");


    $lockedQuestionStatement->execute([
        $examId
    ]);


    $lockedQuestionData =
        $lockedQuestionStatement->fetch(
            PDO::FETCH_ASSOC
        );


    $lockedTotalQuestions =
        (int)(
            $lockedQuestionData[
                'total_questions'
            ] ?? 0
        );


    $lockedActiveQuestions =
        (int)(
            $lockedQuestionData[
                'active_questions'
            ] ?? 0
        );


    $lockedActiveMarks =
        round(
            (float)(
                $lockedQuestionData[
                    'active_marks'
                ] ?? 0
            ),
            2
        );


    if (
        $lockedTotalQuestions !==
        EXAM_REQUIRED_QUESTION_COUNT
    ) {

        throw new RuntimeException(
            'This examination is not ready. Exactly 50 distinct questions are required.'
        );
    }


    if (
        $lockedActiveQuestions !==
        EXAM_REQUIRED_QUESTION_COUNT
    ) {

        throw new RuntimeException(
            'This examination is not ready. All 50 questions must be Active.'
        );
    }


    if (
        abs(
            $lockedActiveMarks -
            $lockedTotalMarks
        ) >
        0.000001
    ) {

        throw new RuntimeException(
            'This examination is not ready because the active question marks do not match the configured exam total.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EXISTING STARTED ATTEMPT
    |--------------------------------------------------------------------------
    */

    $existingStatement =
        $conn->prepare("
            SELECT

                id,

                started_at,
                server_deadline,

                submitted_at,
                last_activity_at,

                status

            FROM exam_attempts

            WHERE

                student_id = ?

                AND exam_id = ?

                AND status = 'Started'

            ORDER BY
                id DESC

            LIMIT 1

            FOR UPDATE
        ");


    $existingStatement->execute([
        $studentId,
        $examId
    ]);


    $existing =
        $existingStatement->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | RESUME EXISTING ATTEMPT
    |--------------------------------------------------------------------------
    */

    if (
        $existing
    ) {

        $deadline =
            null;


        if (
            !empty(
                $existing['server_deadline']
            )
        ) {

            try {

                $deadline =
                    new DateTimeImmutable(
                        (string)(
                            $existing[
                                'server_deadline'
                            ]
                        )
                    );

            } catch (Throwable $exception) {

                $deadline =
                    null;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | BACKFILL SERVER DEADLINE
        |--------------------------------------------------------------------------
        */

        if (
            $deadline === null
        ) {

            if (
                empty(
                    $existing['started_at']
                )
            ) {

                throw new RuntimeException(
                    'The existing examination attempt has invalid timing information.'
                );
            }


            try {

                $startedAt =
                    new DateTimeImmutable(
                        (string)(
                            $existing[
                                'started_at'
                            ]
                        )
                    );

            } catch (Throwable $exception) {

                throw new RuntimeException(
                    'The existing examination attempt has invalid start time.'
                );
            }


            $deadline =
                $startedAt->modify(
                    '+' .
                    (int)(
                        $lockedExam[
                            'duration_minutes'
                        ]
                    ) .
                    ' minutes'
                );


            $backfillStatement =
                $conn->prepare("
                    UPDATE exam_attempts

                    SET
                        server_deadline = ?,

                        last_activity_at = NOW()

                    WHERE

                        id = ?

                        AND student_id = ?

                        AND exam_id = ?

                        AND status = 'Started'
                ");


            $backfillStatement->execute([

                $deadline->format(
                    'Y-m-d H:i:s'
                ),

                (int)$existing['id'],

                $studentId,

                $examId

            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | EXPIRED EXISTING ATTEMPT
        |--------------------------------------------------------------------------
        */

        if (
            $transactionNow >=
            $deadline
        ) {

            /*
            * Keep the existing attempt.
            *
            * The normal submit endpoint is responsible for
            * final grading/closure.
            */

            $conn->commit();


            header(
                'Location: take_exam.php?attempt_id=' .
                (int)$existing['id'] .
                '&auto_submit=1'
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE ACTIVITY
        |--------------------------------------------------------------------------
        */

        $activityStatement =
            $conn->prepare("
                UPDATE exam_attempts

                SET

                    last_activity_at = NOW(),

                    server_deadline = ?

                WHERE

                    id = ?

                    AND student_id = ?

                    AND exam_id = ?

                    AND status = 'Started'
            ");


        $activityStatement->execute([

            $deadline->format(
                'Y-m-d H:i:s'
            ),

            (int)$existing['id'],

            $studentId,

            $examId

        ]);


        $conn->commit();


        /*
        |--------------------------------------------------------------------------
        | RESUME
        |--------------------------------------------------------------------------
        */

        header(
            'Location: take_exam.php?attempt_id=' .
            (int)$existing['id']
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | SAFETY CHECK FOR MULTIPLE ACTIVE ATTEMPTS
    |--------------------------------------------------------------------------
    */

    $activeAttemptStatement =
        $conn->prepare("
            SELECT

                COUNT(*)

            FROM exam_attempts

            WHERE

                student_id = ?

                AND exam_id = ?

                AND status = 'Started'
        ");


    $activeAttemptStatement->execute([
        $studentId,
        $examId
    ]);


    $activeAttemptCount =
        (int)(
            $activeAttemptStatement
            ->fetchColumn()
        );


    if (
        $activeAttemptCount > 0
    ) {

        throw new RuntimeException(
            'An active examination attempt already exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE ATTEMPT
    |--------------------------------------------------------------------------
    */

    $createAttempt =
        $conn->prepare("
            INSERT INTO exam_attempts
            (
                student_id,
                exam_id,

                started_at,
                server_deadline,

                submitted_at,
                last_activity_at,

                status,

                obtained_marks,
                percentage
            )

            VALUES
            (
                ?,
                ?,

                NOW(),

                DATE_ADD(
                    NOW(),
                    INTERVAL ? MINUTE
                ),

                NULL,
                NOW(),

                'Started',

                0.00,
                0.00
            )
        ");


    $createAttempt->execute([

        $studentId,

        $examId,

        (int)(
            $lockedExam[
                'duration_minutes'
            ]
        )

    ]);


    $newAttemptId =
        (int)$conn->lastInsertId();


    if (
        $newAttemptId <= 0
    ) {

        throw new RuntimeException(
            'Unable to create examination attempt.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | READ BACK ATTEMPT
    |--------------------------------------------------------------------------
    */

    $createdAttemptStatement =
        $conn->prepare("
            SELECT

                id,

                student_id,
                exam_id,

                started_at,
                server_deadline,

                status

            FROM exam_attempts

            WHERE
                id = ?

            LIMIT 1
        ");


    $createdAttemptStatement->execute([
        $newAttemptId
    ]);


    $createdAttempt =
        $createdAttemptStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$createdAttempt
    ) {

        throw new RuntimeException(
            'Unable to verify the created examination attempt.'
        );
    }


    if (
        (int)$createdAttempt['student_id'] !==
        $studentId
    ) {

        throw new RuntimeException(
            'The examination attempt owner could not be verified.'
        );
    }


    if (
        (int)$createdAttempt['exam_id'] !==
        $examId
    ) {

        throw new RuntimeException(
            'The examination attempt exam could not be verified.'
        );
    }


    if (
        (string)$createdAttempt['status'] !==
        'Started'
    ) {

        throw new RuntimeException(
            'The examination attempt was not started correctly.'
        );
    }


    if (
        empty(
            $createdAttempt[
                'server_deadline'
            ]
        )
    ) {

        throw new RuntimeException(
            'The examination server deadline could not be established.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FINAL COMMIT
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | ENTER EXAM
    |--------------------------------------------------------------------------
    */

    header(
        'Location: take_exam.php?attempt_id=' .
        $newAttemptId
    );

    exit;


} catch (Throwable $exception) {

    if (
        $conn->inTransaction()
    ) {

        $conn->rollBack();
    }


    error_log(
        'Start exam failed: ' .
        $exception->getMessage()
    );


    $message =
        $exception->getMessage();


    if (
        $message === ''
    ) {

        $message =
            'Unable to start the examination.';
    }


    start_exam_fail(
        $message,
        422
    );
}