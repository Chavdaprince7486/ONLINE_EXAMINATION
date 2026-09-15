<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/auth.php';

require_login('student');

$studentId = (int)($_SESSION['user_id'] ?? 0);

$examId = filter_input(
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
    exit('Invalid exam ID.');
}

$examId = (int)$examId;


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


function start_exam_number(
    mixed $value
): string {

    $number = round(
        (float)$value,
        2
    );

    if (
        floor($number) === $number
    ) {

        return number_format(
            $number,
            0,
            '.',
            ''
        );
    }

    return rtrim(
        rtrim(
            number_format(
                $number,
                2,
                '.',
                ''
            ),
            '0'
        ),
        '.'
    );
}


function start_exam_error(
    string $message,
    int $status = 422,
    string $backUrl = 'practice_exams.php',
    string $backText = 'Back'
): never {

    http_response_code($status);

    echo '
    <!doctype html>

    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width,initial-scale=1"
        >

        <title>
            ExamSphere
        </title>

        <link
            href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap"
            rel="stylesheet"
        >

        <style>

            :root {

                --brown:
                    #5d4037;

                --dark:
                    #3e2723;

                --cream:
                    #f5f5dc;

                --olive:
                    #556b2f;

                --muted:
                    #766b63;

                --line:
                    #e4d9cf;

            }

            * {
                box-sizing:
                    border-box;
            }

            body {

                margin:
                    0;

                min-height:
                    100vh;

                display:
                    grid;

                place-items:
                    center;

                padding:
                    24px;

                background:
                    radial-gradient(
                        circle at 15% 10%,
                        rgba(
                            85,
                            107,
                            47,
                            .08
                        ),
                        transparent 28%
                    ),
                    radial-gradient(
                        circle at 85% 15%,
                        rgba(
                            93,
                            64,
                            55,
                            .08
                        ),
                        transparent 30%
                    ),
                    var(--cream);

                font-family:
                    Poppins,
                    sans-serif;

                color:
                    var(--dark);

            }

            .card {

                width:
                    min(
                        560px,
                        100%
                    );

                padding:
                    36px;

                border:
                    1px solid
                    var(--line);

                border-radius:
                    24px;

                background:
                    rgba(
                        255,
                        255,
                        255,
                        .94
                    );

                box-shadow:
                    0
                    20px
                    60px
                    rgba(
                        62,
                        39,
                        35,
                        .12
                    );

                text-align:
                    center;

            }

            .icon {

                width:
                    64px;

                height:
                    64px;

                margin:
                    0
                    auto
                    18px;

                display:
                    grid;

                place-items:
                    center;

                border-radius:
                    18px;

                background:
                    rgba(
                        171,
                        65,
                        58,
                        .08
                    );

                color:
                    #a0453d;

                font-size:
                    24px;

                font-weight:
                    900;

            }

            h1 {

                margin:
                    0
                    0
                    10px;

                font-size:
                    24px;

                font-weight:
                    900;

            }

            p {

                margin:
                    0;

                color:
                    var(--muted);

                line-height:
                    1.75;

                font-size:
                    13px;

            }

            a {

                display:
                    inline-flex;

                align-items:
                    center;

                justify-content:
                    center;

                gap:
                    8px;

                margin-top:
                    22px;

                min-height:
                    44px;

                padding:
                    0
                    18px;

                border-radius:
                    12px;

                background:
                    var(--brown);

                color:
                    #fff;

                text-decoration:
                    none;

                font-size:
                    12px;

                font-weight:
                    800;

            }

            a:hover {

                background:
                    #4e332d;

                color:
                    #fff;

            }

        </style>

    </head>

    <body>

        <div class="card">

            <div class="icon">
                !
            </div>

            <h1>
                Unable to continue
            </h1>

            <p>
                ' .
                start_exam_escape(
                    $message
                ) .
            '
            </p>

            <a
                href="' .
                start_exam_escape(
                    $backUrl
                ) .
            '"
            >

                <span>
                    ' .
                    start_exam_escape(
                        $backText
                    ) .
                    '
                </span>

            </a>

        </div>

    </body>

    </html>
    ';

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD EXAM
|--------------------------------------------------------------------------
*/

try {

    $examStmt =
        $conn->prepare(
            "
            SELECT

                e.id,
                e.subject_id,
                e.teacher_id,

                e.title,
                e.description,

                e.exam_type,

                e.duration_minutes,

                e.required_question_count,

                e.total_marks,
                e.passing_marks,

                e.negative_marking,

                e.exam_fee,

                e.subscription_required,

                e.starts_at,
                e.ends_at,

                e.status,

                s.name AS subject_name,
                s.code AS subject_code

            FROM exams e

            LEFT JOIN subjects s
                ON s.id = e.subject_id

            WHERE
                e.id = ?

            LIMIT 1
            "
        );

    $examStmt->execute([
        $examId
    ]);

    $exam =
        $examStmt->fetch(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $exception
) {

    error_log(
        'Student start exam load failed: ' .
        $exception->getMessage()
    );

    start_exam_error(
        'Unable to load this examination right now.',
        500
    );
}


if (
    !$exam
) {

    start_exam_error(
        'The examination could not be found.',
        404
    );
}


/*
|--------------------------------------------------------------------------
| BASIC EXAM DATA
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


$requiredQuestionCount =
    (int)(
        $exam[
            'required_question_count'
        ] ?? 0
    );


$durationMinutes =
    (int)(
        $exam[
            'duration_minutes'
        ] ?? 0
    );


$storedTotalMarks =
    round(
        (float)(
            $exam[
                'total_marks'
            ] ?? 0
        ),
        2
    );


$passingMarks =
    round(
        (float)(
            $exam[
                'passing_marks'
            ] ?? 0
        ),
        2
    );


$negativeMarking =
    (int)(
        $exam[
            'negative_marking'
        ] ?? 0
    ) === 1;


/*
|--------------------------------------------------------------------------
| EXAM TYPE VALIDATION
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

    start_exam_error(
        'This examination has an invalid examination type.'
    );
}


/*
|--------------------------------------------------------------------------
| SUBSCRIPTION ACCESS
|--------------------------------------------------------------------------
|
| Business rule:
|
| Practice = FREE
|
| Live = ACTIVE SUBSCRIPTION REQUIRED
|
|--------------------------------------------------------------------------
*/

$requiresSubscription =
    $examType === 'Live';


$hasActiveSubscription =
    false;


if (
    $requiresSubscription
) {

    try {

        $hasActiveSubscription =
            has_active_subscription(
                $conn,
                $studentId
            );

    } catch (
        Throwable $exception
    ) {

        error_log(
            'Live exam subscription verification failed: ' .
            $exception->getMessage()
        );

        start_exam_error(
            'Unable to verify your subscription right now.',
            500,
            'subscriptions.php',
            'Go to Subscription'
        );
    }


    if (
        !$hasActiveSubscription
    ) {

        start_exam_error(
            'An active subscription is required to attend this Live Exam.',
            403,
            'subscriptions.php',
            'View Subscription'
        );
    }
}


/*
|--------------------------------------------------------------------------
| EXAM STATUS
|--------------------------------------------------------------------------
*/

if (
    $examType === 'Practice'
) {

    if (
        $examStatus !== 'Active'
    ) {

        start_exam_error(
            'This practice examination is not currently available.'
        );
    }

} else {

    /*
    |--------------------------------------------------------------------------
    | LIVE EXAM STATUS
    |--------------------------------------------------------------------------
    |
    | Live exams may use Active, Live, Running or Scheduled
    | depending on the configured schedule/state.
    |
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $examStatus,
            [
                'Draft',
                'Cancelled',
                'Completed'
            ],
            true
        )
    ) {

        start_exam_error(
            'This Live Exam is not currently available.',
            403,
            'live_exams.php',
            'Back to Live Exams'
        );
    }
}


/*
|--------------------------------------------------------------------------
| QUESTION COUNT
|--------------------------------------------------------------------------
*/

if (
    $requiredQuestionCount < 1
) {

    start_exam_error(
        'This examination has an invalid question count.',
        422,
        $examType === 'Live'
            ? 'live_exams.php'
            : 'practice_exams.php',
        $examType === 'Live'
            ? 'Back to Live Exams'
            : 'Back to Practice Exams'
    );
}


if (
    $requiredQuestionCount > 50
) {

    start_exam_error(
        'A maximum of 50 questions can be used in one examination.',
        422,
        $examType === 'Live'
            ? 'live_exams.php'
            : 'practice_exams.php',
        $examType === 'Live'
            ? 'Back to Live Exams'
            : 'Back to Practice Exams'
    );
}


/*
|--------------------------------------------------------------------------
| DURATION
|--------------------------------------------------------------------------
*/

if (
    $durationMinutes < 1
) {

    start_exam_error(
        'This examination has an invalid duration.'
    );
}


/*
|--------------------------------------------------------------------------
| PASSING MARKS
|--------------------------------------------------------------------------
*/

if (
    $passingMarks < 0
) {

    start_exam_error(
        'This examination has invalid passing marks.'
    );
}


/*
|--------------------------------------------------------------------------
| SCHEDULE
|--------------------------------------------------------------------------
*/

$now =
    new DateTimeImmutable();


$startsAt =
    null;

$endsAt =
    null;


try {

    if (
        !empty(
            $exam['starts_at']
        )
    ) {

        $startsAt =
            new DateTimeImmutable(
                (string)$exam[
                    'starts_at'
                ]
            );
    }


    if (
        !empty(
            $exam['ends_at']
        )
    ) {

        $endsAt =
            new DateTimeImmutable(
                (string)$exam[
                    'ends_at'
                ]
            );
    }

} catch (
    Throwable $exception
) {

    start_exam_error(
        'This examination has an invalid schedule.',
        422,
        $examType === 'Live'
            ? 'live_exams.php'
            : 'practice_exams.php',
        $examType === 'Live'
            ? 'Back to Live Exams'
            : 'Back to Practice Exams'
    );
}


/*
|--------------------------------------------------------------------------
| SCHEDULE VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $examType === 'Live' &&
    $examStatus === 'Upcoming' &&
    $startsAt === null
) {

    start_exam_error(
        'This Live Exam has not been scheduled yet.',
        403,
        'live_exams.php',
        'Back to Live Exams'
    );
}


if (
    $startsAt !== null &&
    $now < $startsAt
) {

    start_exam_error(
        $examType === 'Live'
            ? 'This Live Exam has not started yet.'
            : 'This practice examination has not started yet.',
        403,
        $examType === 'Live'
            ? 'live_exams.php'
            : 'practice_exams.php',
        $examType === 'Live'
            ? 'Back to Live Exams'
            : 'Back to Practice Exams'
    );
}


if (
    $endsAt !== null &&
    $now > $endsAt
) {

    start_exam_error(
        $examType === 'Live'
            ? 'This Live Exam has ended.'
            : 'This practice examination has ended.',
        403,
        $examType === 'Live'
            ? 'live_exams.php'
            : 'practice_exams.php',
        $examType === 'Live'
            ? 'Back to Live Exams'
            : 'Back to Practice Exams'
    );
}


/*
|--------------------------------------------------------------------------
| LOAD EXAM QUESTIONS
|--------------------------------------------------------------------------
*/

try {

    $questionStmt =
        $conn->prepare(
            "
            SELECT

                eq.question_id,
                eq.position,

                q.marks,
                q.negative_marks,
                q.status

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE
                eq.exam_id = ?

            ORDER BY
                eq.position ASC,
                eq.question_id ASC
            "
        );


    $questionStmt->execute([
        $examId
    ]);


    $examQuestions =
        $questionStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $exception
) {

    error_log(
        'Student start exam question load failed: ' .
        $exception->getMessage()
    );

    start_exam_error(
        'Unable to load the questions for this examination.',
        500
    );
}


if (
    empty($examQuestions)
) {

    start_exam_error(
        $examType === 'Live'
            ? 'This Live Exam does not have any questions yet.'
            : 'This practice examination does not have any questions yet.',
        422,
        $examType === 'Live'
            ? 'live_exams.php'
            : 'practice_exams.php',
        $examType === 'Live'
            ? 'Back to Live Exams'
            : 'Back to Practice Exams'
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE QUESTION SET
|--------------------------------------------------------------------------
*/

$questionIds =
    [];

$activeQuestions =
    [];


foreach (
    $examQuestions
    as $question
) {

    $questionId =
        (int)(
            $question[
                'question_id'
            ] ?? 0
        );


    if (
        $questionId <= 0
    ) {

        start_exam_error(
            'This examination contains an invalid question.'
        );
    }


    if (
        isset(
            $questionIds[
                $questionId
            ]
        )
    ) {

        start_exam_error(
            'This examination contains a duplicate question.'
        );
    }


    $questionIds[
        $questionId
    ] =
        true;


    $questionStatus =
        trim(
            (string)(
                $question[
                    'status'
                ] ?? ''
            )
        );


    if (
        $questionStatus !== 'Active'
    ) {

        start_exam_error(
            'This examination contains inactive questions.'
        );
    }


    $questionMarks =
        round(
            (float)(
                $question[
                    'marks'
                ] ?? 0
            ),
            2
        );


    if (
        $questionMarks <= 0
    ) {

        start_exam_error(
            'Every question in this examination must have valid marks.'
        );
    }


    $activeQuestions[] =
        $question;
}


$actualQuestionCount =
    count(
        $activeQuestions
    );


/*
|--------------------------------------------------------------------------
| EXACT QUESTION COUNT
|--------------------------------------------------------------------------
*/

if (
    $actualQuestionCount !==
    $requiredQuestionCount
) {

    start_exam_error(
        'This examination is not ready. ' .
        $requiredQuestionCount .
        ' questions are configured, but ' .
        $actualQuestionCount .
        ' active questions are currently assigned.',
        422,
        $examType === 'Live'
            ? 'live_exams.php'
            : 'practice_exams.php',
        $examType === 'Live'
            ? 'Back to Live Exams'
            : 'Back to Practice Exams'
    );
}


/*
|--------------------------------------------------------------------------
| MARKS PER QUESTION
|--------------------------------------------------------------------------
*/

$marksPerQuestion =
    null;


foreach (
    $activeQuestions
    as $question
) {

    $questionMarks =
        round(
            (float)(
                $question[
                    'marks'
                ] ?? 0
            ),
            2
        );


    if (
        $marksPerQuestion === null
    ) {

        $marksPerQuestion =
            $questionMarks;

    } elseif (
        abs(
            $questionMarks -
            $marksPerQuestion
        ) > 0.000001
    ) {

        start_exam_error(
            'All questions in this examination must use the same marks per question.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| DYNAMIC TOTAL MARKS
|--------------------------------------------------------------------------
*/

$calculatedTotalMarks =
    round(
        $actualQuestionCount *
        (float)$marksPerQuestion,
        2
    );


if (
    $calculatedTotalMarks <= 0
) {

    start_exam_error(
        'Unable to calculate the total marks for this examination.'
    );
}


if (
    $passingMarks >
    $calculatedTotalMarks
) {

    start_exam_error(
        'Passing marks cannot be greater than the calculated total marks.'
    );
}


$totalMarks =
    $calculatedTotalMarks;


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION[
            'exam_csrf_token'
        ]
    )
) {

    try {

        $_SESSION[
            'exam_csrf_token'
        ] =
            bin2hex(
                random_bytes(32)
            );

    } catch (
        Throwable $exception
    ) {

        $_SESSION[
            'exam_csrf_token'
        ] =
            hash(
                'sha256',
                uniqid(
                    '',
                    true
                )
            );
    }
}


$examCsrfToken =
    (string)(
        $_SESSION[
            'exam_csrf_token'
        ]
    );


/*
|--------------------------------------------------------------------------
| START / RESUME ATTEMPT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    ===
    'POST'
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | CSRF
        |--------------------------------------------------------------------------
        */

        $requestToken =
            trim(
                (string)(
                    $_POST[
                        'csrf_token'
                    ] ?? ''
                )
            );


        if (
            $requestToken === '' ||
            $examCsrfToken === '' ||
            !hash_equals(
                $examCsrfToken,
                $requestToken
            )
        ) {

            throw new RuntimeException(
                'Security verification failed. Please refresh the page and try again.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | EXAM ID
        |--------------------------------------------------------------------------
        */

        $postedExamId =
            filter_input(
                INPUT_POST,
                'exam_id',
                FILTER_VALIDATE_INT
            );


        if (
            $postedExamId === false ||
            $postedExamId === null ||
            (int)$postedExamId !==
                $examId
        ) {

            throw new RuntimeException(
                'Invalid examination request.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | TRANSACTION
        |--------------------------------------------------------------------------
        */

        $conn->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | LOCK EXAM
        |--------------------------------------------------------------------------
        */

        $lockedExamStmt =
            $conn->prepare(
                "
                SELECT

                    id,
                    title,
                    description,

                    exam_type,

                    status,

                    duration_minutes,

                    required_question_count,

                    passing_marks,

                    negative_marking,

                    subscription_required,

                    starts_at,
                    ends_at

                FROM exams

                WHERE
                    id = ?

                LIMIT 1

                FOR UPDATE
                "
            );


        $lockedExamStmt->execute([
            $examId
        ]);


        $lockedExam =
            $lockedExamStmt->fetch(
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
        | LOCKED EXAM TYPE
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


        if (
            $lockedExamType !==
            $examType
        ) {

            throw new RuntimeException(
                'The examination configuration has changed. Please refresh the page.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LOCKED STATUS
        |--------------------------------------------------------------------------
        */

        $lockedStatus =
            trim(
                (string)(
                    $lockedExam[
                        'status'
                    ] ?? ''
                )
            );


        if (
            $lockedExamType ===
            'Practice'
        ) {

            if (
                $lockedStatus !==
                'Active'
            ) {

                throw new RuntimeException(
                    'This practice examination is no longer available.'
                );
            }

        } else {

            if (
                in_array(
                    $lockedStatus,
                    [
                        'Draft',
                        'Cancelled',
                        'Completed'
                    ],
                    true
                )
            ) {

                throw new RuntimeException(
                    'This Live Exam is no longer available.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | LIVE SUBSCRIPTION RECHECK
        |--------------------------------------------------------------------------
        |
        | Check again on POST so access cannot be bypassed
        | by loading an old page and posting later.
        |
        |--------------------------------------------------------------------------
        */

        if (
            $lockedExamType ===
            'Live'
        ) {

            if (
                !has_active_subscription(
                    $conn,
                    $studentId
                )
            ) {

                throw new RuntimeException(
                    'Your active subscription is required to start this Live Exam.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | LOCKED QUESTION COUNT
        |--------------------------------------------------------------------------
        */

        $lockedRequired =
            (int)(
                $lockedExam[
                    'required_question_count'
                ] ?? 0
            );


        $lockedDuration =
            (int)(
                $lockedExam[
                    'duration_minutes'
                ] ?? 0
            );


        $lockedPassingMarks =
            round(
                (float)(
                    $lockedExam[
                        'passing_marks'
                    ] ?? 0
                ),
                2
            );


        if (
            $lockedRequired < 1 ||
            $lockedRequired > 50
        ) {

            throw new RuntimeException(
                'The examination has an invalid required question count.'
            );
        }


        if (
            $lockedDuration < 1
        ) {

            throw new RuntimeException(
                'The examination has an invalid duration.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LOCKED QUESTIONS
        |--------------------------------------------------------------------------
        */

        $lockedQuestionStmt =
            $conn->prepare(
                "
                SELECT

                    eq.question_id,
                    eq.position,

                    q.marks,
                    q.negative_marks,
                    q.status

                FROM exam_questions eq

                INNER JOIN questions q
                    ON q.id =
                       eq.question_id

                WHERE
                    eq.exam_id = ?

                ORDER BY
                    eq.position ASC,
                    eq.question_id ASC
                "
            );


        $lockedQuestionStmt->execute([
            $examId
        ]);


        $lockedQuestions =
            $lockedQuestionStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        if (
            empty($lockedQuestions)
        ) {

            throw new RuntimeException(
                'This examination has no questions.'
            );
        }


        $lockedIds =
            [];

        $lockedActiveCount =
            0;

        $lockedMarksPerQuestion =
            null;


        foreach (
            $lockedQuestions
            as $question
        ) {

            $questionId =
                (int)(
                    $question[
                        'question_id'
                    ] ?? 0
                );


            if (
                $questionId <= 0
            ) {

                throw new RuntimeException(
                    'This examination contains an invalid question.'
                );
            }


            if (
                isset(
                    $lockedIds[
                        $questionId
                    ]
                )
            ) {

                throw new RuntimeException(
                    'This examination contains duplicate questions.'
                );
            }


            $lockedIds[
                $questionId
            ] =
                true;


            if (
                (string)(
                    $question[
                        'status'
                    ] ?? ''
                )
                !==
                'Active'
            ) {

                throw new RuntimeException(
                    'All questions in this examination must be Active.'
                );
            }


            $questionMarks =
                round(
                    (float)(
                        $question[
                            'marks'
                        ] ?? 0
                    ),
                    2
                );


            if (
                $questionMarks <= 0
            ) {

                throw new RuntimeException(
                    'A question in this examination has invalid marks.'
                );
            }


            if (
                $lockedMarksPerQuestion
                ===
                null
            ) {

                $lockedMarksPerQuestion =
                    $questionMarks;

            } else {

                if (
                    abs(
                        $questionMarks -
                        $lockedMarksPerQuestion
                    ) > 0.000001
                ) {

                    throw new RuntimeException(
                        'All questions in this examination must use the same marks per question.'
                    );
                }
            }


            $lockedActiveCount++;
        }


        /*
        |--------------------------------------------------------------------------
        | EXACT COUNT
        |--------------------------------------------------------------------------
        */

        if (
            $lockedActiveCount !==
            $lockedRequired
        ) {

            throw new RuntimeException(
                'The examination question set has changed and is no longer ready.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CALCULATE LOCKED TOTAL
        |--------------------------------------------------------------------------
        */

        $lockedCalculatedTotal =
            round(
                $lockedActiveCount *
                (float)$lockedMarksPerQuestion,
                2
            );


        if (
            $lockedCalculatedTotal <= 0
        ) {

            throw new RuntimeException(
                'Unable to calculate the examination total marks.'
            );
        }


        if (
            $lockedPassingMarks >
            $lockedCalculatedTotal
        ) {

            throw new RuntimeException(
                'Passing marks are greater than the calculated total marks.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LOCKED SCHEDULE
        |--------------------------------------------------------------------------
        */

        $transactionNow =
            new DateTimeImmutable();


        $lockedStartsAt =
            null;

        $lockedEndsAt =
            null;


        if (
            !empty(
                $lockedExam[
                    'starts_at'
                ]
            )
        ) {

            try {

                $lockedStartsAt =
                    new DateTimeImmutable(
                        (string)$lockedExam[
                            'starts_at'
                        ]
                    );

            } catch (
                Throwable $exception
            ) {

                throw new RuntimeException(
                    'The examination start schedule is invalid.'
                );
            }
        }


        if (
            !empty(
                $lockedExam[
                    'ends_at'
                ]
            )
        ) {

            try {

                $lockedEndsAt =
                    new DateTimeImmutable(
                        (string)$lockedExam[
                            'ends_at'
                        ]
                    );

            } catch (
                Throwable $exception
            ) {

                throw new RuntimeException(
                    'The examination end schedule is invalid.'
                );
            }
        }


        if (
            $lockedStartsAt !== null &&
            $transactionNow <
                $lockedStartsAt
        ) {

            throw new RuntimeException(
                $lockedExamType === 'Live'
                    ? 'This Live Exam has not started yet.'
                    : 'This practice examination has not started yet.'
            );
        }


        if (
            $lockedEndsAt !== null &&
            $transactionNow >
                $lockedEndsAt
        ) {

            throw new RuntimeException(
                $lockedExamType === 'Live'
                    ? 'This Live Exam has ended.'
                    : 'This practice examination has ended.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | EXISTING ACTIVE ATTEMPT
        |--------------------------------------------------------------------------
        */

        $existingStmt =
            $conn->prepare(
                "
                SELECT

                    id,
                    started_at,
                    server_deadline,
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
                "
            );


        $existingStmt->execute([
            $studentId,
            $examId
        ]);


        $existing =
            $existingStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            $existing
        ) {

            $deadline =
                null;


            if (
                !empty(
                    $existing[
                        'server_deadline'
                    ]
                )
            ) {

                try {

                    $deadline =
                        new DateTimeImmutable(
                            (string)$existing[
                                'server_deadline'
                            ]
                        );

                } catch (
                    Throwable $exception
                ) {

                    $deadline =
                        null;
                }
            }


            if (
                $deadline === null &&
                !empty(
                    $existing[
                        'started_at'
                    ]
                )
            ) {

                try {

                    $startedAt =
                        new DateTimeImmutable(
                            (string)$existing[
                                'started_at'
                            ]
                        );

                } catch (
                    Throwable $exception
                ) {

                    throw new RuntimeException(
                        'The existing examination attempt has invalid timing information.'
                    );
                }


                $deadline =
                    $startedAt->modify(
                        '+' .
                        $lockedDuration .
                        ' minutes'
                    );
            }


            if (
                $deadline === null
            ) {

                throw new RuntimeException(
                    'The existing examination attempt has invalid timing information.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | EXAM END BOUNDARY
            |--------------------------------------------------------------------------
            */

            if (
                $lockedEndsAt !== null &&
                $lockedEndsAt <
                    $deadline
            ) {

                $deadline =
                    $lockedEndsAt;
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

                $conn->commit();


                header(
                    'Location: take_exam.php?attempt_id=' .
                    (int)$existing[
                        'id'
                    ] .
                    '&auto_submit=1'
                );


                exit;
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE ACTIVITY
            |--------------------------------------------------------------------------
            */

            $updateAttempt =
                $conn->prepare(
                    "
                    UPDATE exam_attempts

                    SET

                        server_deadline = ?,
                        last_activity_at = NOW()

                    WHERE

                        id = ?

                        AND student_id = ?

                        AND exam_id = ?

                        AND status = 'Started'
                    "
                );


            $updateAttempt->execute([
                $deadline->format(
                    'Y-m-d H:i:s'
                ),
                (int)$existing[
                    'id'
                ],
                $studentId,
                $examId
            ]);


            $conn->commit();


            header(
                'Location: take_exam.php?attempt_id=' .
                (int)$existing[
                    'id'
                ]
            );


            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | NEW DEADLINE
        |--------------------------------------------------------------------------
        */

        $newDeadline =
            $transactionNow->modify(
                '+' .
                $lockedDuration .
                ' minutes'
            );


        if (
            $lockedEndsAt !== null &&
            $lockedEndsAt <
                $newDeadline
        ) {

            $newDeadline =
                $lockedEndsAt;
        }


        if (
            $newDeadline <=
            $transactionNow
        ) {

            throw new RuntimeException(
                'This examination does not have enough remaining time to start.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE ATTEMPT
        |--------------------------------------------------------------------------
        */

        $insertAttempt =
            $conn->prepare(
                "
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
                    ?,
                    NULL,
                    NOW(),
                    'Started',
                    0.00,
                    0.00
                )
                "
            );


        $insertAttempt->execute([
            $studentId,
            $examId,
            $newDeadline->format(
                'Y-m-d H:i:s'
            )
        ]);


        $attemptId =
            (int)$conn->lastInsertId();


        if (
            $attemptId <= 0
        ) {

            throw new RuntimeException(
                'Unable to start the examination.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | VERIFY ATTEMPT
        |--------------------------------------------------------------------------
        */

        $verifyAttempt =
            $conn->prepare(
                "
                SELECT

                    id,
                    student_id,
                    exam_id,
                    status,
                    server_deadline

                FROM exam_attempts

                WHERE
                    id = ?

                LIMIT 1
                "
            );


        $verifyAttempt->execute([
            $attemptId
        ]);


        $createdAttempt =
            $verifyAttempt->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$createdAttempt ||
            (int)$createdAttempt[
                'student_id'
            ] !==
                $studentId ||
            (int)$createdAttempt[
                'exam_id'
            ] !==
                $examId ||
            (string)$createdAttempt[
                'status'
            ] !==
                'Started'
        ) {

            throw new RuntimeException(
                'The examination attempt could not be verified.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        $conn->commit();


        /*
        |--------------------------------------------------------------------------
        | TAKE EXAM
        |--------------------------------------------------------------------------
        */

        header(
            'Location: take_exam.php?attempt_id=' .
            $attemptId
        );


        exit;


    } catch (
        Throwable $exception
    ) {

        if (
            $conn->inTransaction()
        ) {

            $conn->rollBack();
        }


        error_log(
            'Student exam start failed: ' .
            $exception->getMessage()
        );


        start_exam_error(
            $exception->getMessage(),
            422,
            $examType === 'Live'
                ? 'live_exams.php'
                : 'practice_exams.php',
            $examType === 'Live'
                ? 'Back to Live Exams'
                : 'Back to Practice Exams'
        );
    }
}


/*
|--------------------------------------------------------------------------
| DISPLAY VALUES
|--------------------------------------------------------------------------
*/

$isLive =
    $examType === 'Live';


$pageTypeLabel =
    $isLive
        ? 'LIVE EXAM'
        : 'FREE PRACTICE';


$accessLabel =
    $isLive
        ? 'Subscription Required'
        : 'Free Practice';


$accessTagIcon =
    $isLive
        ? 'fa-lock'
        : 'fa-unlock';


$backUrl =
    $isLive
        ? 'live_exams.php'
        : 'practice_exams.php';


$backText =
    $isLive
        ? 'Back to Live Exams'
        : 'Back to Practice Exams';


$startButtonText =
    $isLive
        ? 'Join Live Exam'
        : 'Start Practice Exam';


$questionsLabel =
    $actualQuestionCount .
    ' ' .
    (
        $actualQuestionCount === 1
            ? 'Question'
            : 'Questions'
    );


$negativeLabel =
    $negativeMarking
        ? 'Enabled'
        : 'None';


$startDateLabel =
    $startsAt !== null
        ? $startsAt->format(
            'd M Y, h:i A'
        )
        : 'Available now';


$endDateLabel =
    $endsAt !== null
        ? $endsAt->format(
            'd M Y, h:i A'
        )
        : 'No end time';


$subscriptionLabel =
    $isLive
        ? (
            $hasActiveSubscription
                ? 'Active subscription verified'
                : 'Active subscription required'
        )
        : 'No subscription required';

?>


<!doctype html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="theme-color"
        content="#f5f5dc"
    >


    <title>

        <?= start_exam_escape(
            $exam['title']
        ) ?>

        |

        <?= $isLive
            ? 'Live Exam'
            : 'Start Practice' ?>

        | ExamSphere

    </title>


    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >


    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >


    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >


    <style>

        :root {

            --cream:
                #f5f5dc;

            --brown:
                #5d4037;

            --brown-light:
                #806154;

            --olive:
                #556b2f;

            --olive-dark:
                #465b27;

            --text:
                #333333;

            --muted:
                #766e69;

            --line:
                rgba(
                    93,
                    64,
                    55,
                    .10
                );

            --white:
                rgba(
                    255,
                    255,
                    255,
                    .90
                );

            --shadow:
                0
                20px
                55px
                rgba(
                    51,
                    51,
                    51,
                    .08
                );

        }


        * {

            box-sizing:
                border-box;

        }


        body {

            margin:
                0;

            min-height:
                100vh;

            background:

                radial-gradient(
                    circle at 12% 10%,
                    rgba(
                        85,
                        107,
                        47,
                        .08
                    ),
                    transparent 28%
                ),

                radial-gradient(
                    circle at 88% 12%,
                    rgba(
                        93,
                        64,
                        55,
                        .08
                    ),
                    transparent 30%
                ),

                var(--cream);

            color:
                var(--text);

            font-family:
                Poppins,
                sans-serif;

        }


        .page {

            width:
                min(
                    1120px,
                    calc(
                        100% -
                        32px
                    )
                );

            margin:
                0
                auto;

            padding:
                42px
                0
                55px;

        }


        .top {

            display:
                flex;

            align-items:
                flex-end;

            justify-content:
                space-between;

            gap:
                20px;

            margin-bottom:
                20px;

        }


        .kicker {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            margin-bottom:
                8px;

            color:
                var(--olive);

            font-size:
                12px;

            font-weight:
                800;

            letter-spacing:
                .12em;

            text-transform:
                uppercase;

        }


        h1 {

            margin:
                0;

            color:
                var(--text);

            font-size:
                clamp(
                    32px,
                    5vw,
                    48px
                );

            font-weight:
                800;

            line-height:
                1.15;

        }


        .subtitle {

            max-width:
                780px;

            margin:
                10px
                0
                0;

            color:
                var(--muted);

            font-size:
                15px;

            line-height:
                1.7;

        }


        .back {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            min-height:
                42px;

            padding:
                0
                15px;

            border:
                1px solid
                var(--line);

            border-radius:
                12px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .84
                );

            color:
                var(--brown);

            text-decoration:
                none;

            font-size:
                13px;

            font-weight:
                800;

            white-space:
                nowrap;

            transition:
                .2s ease;

        }


        .back:hover {

            color:
                #fff;

            background:
                var(--brown);

            border-color:
                var(--brown);

            transform:
                translateY(
                    -2px
                );

        }


        .hero {

            padding:
                28px;

            border:
                1px solid
                var(--line);

            border-radius:
                24px;

            background:
                linear-gradient(
                    145deg,
                    rgba(
                        255,
                        255,
                        255,
                        .93
                    ),
                    rgba(
                        250,
                        247,
                        240,
                        .90
                    )
                );

            box-shadow:
                var(--shadow);

            backdrop-filter:
                blur(
                    14px
                );

        }


        .tags {

            display:
                flex;

            flex-wrap:
                wrap;

            gap:
                8px;

            margin-bottom:
                15px;

        }


        .tag {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            padding:
                7px
                10px;

            border-radius:
                999px;

            background:
                rgba(
                    93,
                    64,
                    55,
                    .07
                );

            color:
                var(--brown);

            font-size:
                11px;

            font-weight:
                800;

        }


        .tag.green {

            background:
                rgba(
                    85,
                    107,
                    47,
                    .10
                );

            color:
                var(--olive);

        }


        .tag.live {

            background:
                rgba(
                    93,
                    64,
                    55,
                    .08
                );

            color:
                var(--brown);

        }


        .stats {

            display:
                grid;

            grid-template-columns:
                repeat(
                    4,
                    minmax(
                        0,
                        1fr
                    )
                );

            gap:
                12px;

            margin-top:
                24px;

        }


        .stat {

            min-height:
                95px;

            padding:
                17px;

            border:
                1px solid
                var(--line);

            border-radius:
                16px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .82
                );

        }


        .stat span {

            display:
                block;

            margin-bottom:
                7px;

            color:
                #827b76;

            font-size:
                11px;

            font-weight:
                800;

            letter-spacing:
                .05em;

            text-transform:
                uppercase;

        }


        .stat strong {

            display:
                block;

            color:
                var(--brown);

            font-size:
                22px;

            font-weight:
                800;

        }


        .grid {

            display:
                grid;

            grid-template-columns:
                minmax(
                    0,
                    1fr
                )
                350px;

            gap:
                18px;

            margin-top:
                18px;

        }


        .card {

            padding:
                22px;

            border:
                1px solid
                var(--line);

            border-radius:
                21px;

            background:
                var(--white);

            box-shadow:
                0
                15px
                40px
                rgba(
                    51,
                    51,
                    51,
                    .06
                );

            backdrop-filter:
                blur(
                    13px
                );

        }


        .card h2 {

            margin:
                0;

            color:
                var(--text);

            font-size:
                17px;

            font-weight:
                800;

        }


        .card-description {

            margin:
                5px
                0
                16px;

            color:
                var(--muted);

            font-size:
                12px;

        }


        .row {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            padding:
                12px
                0;

            border-bottom:
                1px solid
                #f0e9e2;

        }


        .row:last-child {

            border-bottom:
                0;

        }


        .row span {

            color:
                var(--muted);

            font-size:
                12px;

        }


        .row strong {

            color:
                var(--text);

            font-size:
                12px;

            font-weight:
                800;

            text-align:
                right;

        }


        .verified {

            color:
                var(--olive) !important;

        }


        .rules {

            display:
                grid;

            gap:
                8px;

            margin-top:
                15px;

        }


        .rule {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                10px;

            padding:
                12px;

            border-radius:
                12px;

            background:
                #fbf8f4;

        }


        .rule i {

            margin-top:
                2px;

            color:
                var(--olive);

        }


        .rule strong {

            display:
                block;

            margin-bottom:
                2px;

            color:
                var(--text);

            font-size:
                12px;

        }


        .rule span {

            display:
                block;

            color:
                var(--muted);

            font-size:
                11px;

            line-height:
                1.5;

        }


        .subscription-rule {

            background:
                rgba(
                    85,
                    107,
                    47,
                    .065
                );

            border:
                1px solid
                rgba(
                    85,
                    107,
                    47,
                    .12
                );

        }


        .subscription-rule i {

            color:
                var(--olive);

        }


        .start-card {

            margin-top:
                18px;

            padding:
                22px;

            border:
                1px solid
                rgba(
                    85,
                    107,
                    47,
                    .15
                );

            border-radius:
                20px;

            background:
                linear-gradient(
                    145deg,
                    rgba(
                        85,
                        107,
                        47,
                        .065
                    ),
                    rgba(
                        255,
                        255,
                        255,
                        .92
                    )
                );

            box-shadow:
                0
                15px
                40px
                rgba(
                    51,
                    51,
                    51,
                    .055
                );

        }


        .start-top {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                18px;

        }


        .start-top strong {

            display:
                block;

            color:
                var(--text);

            font-size:
                16px;

            font-weight:
                800;

        }


        .start-top span {

            display:
                block;

            margin-top:
                5px;

            color:
                var(--muted);

            font-size:
                11px;

        }


        .start-btn {

            min-width:
                190px;

            min-height:
                52px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                9px;

            padding:
                0
                22px;

            border:
                0;

            border-radius:
                14px;

            background:
                linear-gradient(
                    135deg,
                    var(--brown),
                    var(--brown-light)
                );

            color:
                #fff;

            font-size:
                13px;

            font-weight:
                800;

            cursor:
                pointer;

            box-shadow:
                0
                16px
                28px
                rgba(
                    93,
                    64,
                    55,
                    .18
                );

            transition:
                .2s ease;

        }


        .start-btn:hover {

            transform:
                translateY(
                    -2px
                );

            box-shadow:
                0
                22px
                35px
                rgba(
                    93,
                    64,
                    55,
                    .23
                );

        }


        .start-btn:disabled {

            opacity:
                .72;

            cursor:
                not-allowed;

            transform:
                none;

        }


        .note {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                8px;

            margin-top:
                14px;

            padding:
                11px
                12px;

            border-radius:
                11px;

            background:
                rgba(
                    93,
                    64,
                    55,
                    .045
                );

            color:
                var(--muted);

            font-size:
                10px;

            line-height:
                1.55;

        }


        .note i {

            margin-top:
                1px;

            color:
                var(--brown);

        }


        .formula {

            margin-top:
                13px;

            padding:
                13px;

            border-radius:
                12px;

            background:
                rgba(
                    85,
                    107,
                    47,
                    .08
                );

            color:
                var(--olive);

            text-align:
                center;

            font-size:
                12px;

            font-weight:
                800;

        }


        @media (
            max-width:
            900px
        ) {

            .grid {

                grid-template-columns:
                    1fr;

            }


            .stats {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(
                            0,
                            1fr
                        )
                    );

            }

        }


        @media (
            max-width:
            600px
        ) {

            .page {

                width:
                    calc(
                        100% -
                        22px
                    );

                padding-top:
                    22px;

            }


            .top {

                align-items:
                    flex-start;

                flex-direction:
                    column;

            }


            .back {

                width:
                    100%;

            }


            .hero {

                padding:
                    22px
                    18px;

            }


            h1 {

                font-size:
                    35px;

            }


            .stats {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(
                            0,
                            1fr
                        )
                    );

            }


            .start-top {

                align-items:
                    stretch;

                flex-direction:
                    column;

            }


            .start-btn {

                width:
                    100%;

            }

        }

    </style>

</head>


<body>


<main class="page">


    <!-- =========================================================
         HEADER
         ========================================================= -->


    <section class="top">


        <div>

            <span class="kicker">

                <i
                    class="
                        fa-solid
                        <?= $isLive
                            ? 'fa-tower-broadcast'
                            : 'fa-pen-to-square' ?>
                    "
                ></i>

                <?= start_exam_escape(
                    $pageTypeLabel
                ) ?>

            </span>


            <h1>

                <?= start_exam_escape(
                    $exam['title']
                ) ?>

            </h1>


            <p
                class="subtitle"
            >

                <?= start_exam_escape(
                    $exam['description']
                    ?:
                    (
                        $isLive
                            ? 'Join this scheduled premium examination and demonstrate your preparation in a secure ExamSphere environment.'
                            : 'Build your confidence with this carefully configured practice examination.'
                    )
                ) ?>

            </p>

        </div>


        <a
            href="<?= start_exam_escape(
                $backUrl
            ) ?>"
            class="back"
        >

            <i
                class="fa-solid fa-arrow-left"
            ></i>

            <?= start_exam_escape(
                $backText
            ) ?>

        </a>


    </section>


    <!-- =========================================================
         HERO
         ========================================================= -->


    <section class="hero">


        <div class="tags">


            <span class="tag">

                <i
                    class="fa-solid fa-book-open"
                ></i>

                <?= start_exam_escape(
                    $exam[
                        'subject_name'
                    ]
                    ??
                    'Subject'
                ) ?>

            </span>


            <?php if (
                !empty(
                    $exam[
                        'subject_code'
                    ]
                )
            ): ?>

                <span class="tag">

                    <?= start_exam_escape(
                        $exam[
                            'subject_code'
                        ]
                    ) ?>

                </span>

            <?php endif; ?>


            <span
                class="
                    tag
                    <?= $isLive
                        ? 'live'
                        : 'green' ?>
                "
            >

                <i
                    class="
                        fa-solid
                        <?= $accessTagIcon ?>
                    "
                ></i>

                <?= start_exam_escape(
                    $accessLabel
                ) ?>

            </span>


            <?php if ($isLive): ?>

                <span class="tag green">

                    <i
                        class="fa-solid fa-circle-check"
                    ></i>

                    Subscription Verified

                </span>

            <?php endif; ?>


        </div>


        <div class="stats">


            <div class="stat">

                <span>
                    Questions
                </span>

                <strong>

                    <?= $actualQuestionCount ?>

                </strong>

            </div>


            <div class="stat">

                <span>
                    Total Marks
                </span>

                <strong>

                    <?= start_exam_number(
                        $totalMarks
                    ) ?>

                </strong>

            </div>


            <div class="stat">

                <span>
                    Passing Marks
                </span>

                <strong>

                    <?= start_exam_number(
                        $passingMarks
                    ) ?>

                </strong>

            </div>


            <div class="stat">

                <span>
                    Duration
                </span>

                <strong>

                    <?= $durationMinutes ?>

                    min

                </strong>

            </div>


        </div>


        <div class="formula">

            <?= $actualQuestionCount ?>

            Questions

            ×

            <?= start_exam_number(
                $marksPerQuestion
            ) ?>

            Marks / Question

            =

            <?= start_exam_number(
                $totalMarks
            ) ?>

            Total Marks

        </div>


    </section>


    <!-- =========================================================
         DETAIL GRID
         ========================================================= -->


    <section class="grid">


        <!-- =====================================================
             LEFT
             ===================================================== -->


        <div>


            <section class="card">


                <h2>
                    Examination Information
                </h2>


                <p
                    class="card-description"
                >

                    Everything you should know before starting.

                </p>


                <div class="row">

                    <span>
                        Examination Type
                    </span>

                    <strong>

                        <?= start_exam_escape(
                            $examType
                        ) ?>

                    </strong>

                </div>


                <div class="row">

                    <span>
                        Subject
                    </span>

                    <strong>

                        <?= start_exam_escape(
                            $exam[
                                'subject_name'
                            ]
                            ??
                            '—'
                        ) ?>

                    </strong>

                </div>


                <div class="row">

                    <span>
                        Question Set
                    </span>

                    <strong>

                        <?= start_exam_escape(
                            $questionsLabel
                        ) ?>

                    </strong>

                </div>


                <div class="row">

                    <span>
                        Marks Per Question
                    </span>

                    <strong>

                        <?= start_exam_number(
                            $marksPerQuestion
                        ) ?>

                    </strong>

                </div>


                <div class="row">

                    <span>
                        Calculated Total Marks
                    </span>

                    <strong>

                        <?= start_exam_number(
                            $totalMarks
                        ) ?>

                    </strong>

                </div>


                <div class="row">

                    <span>
                        Passing Marks
                    </span>

                    <strong>

                        <?= start_exam_number(
                            $passingMarks
                        ) ?>

                    </strong>

                </div>


                <div class="row">

                    <span>
                        Negative Marking
                    </span>

                    <strong>

                        <?= start_exam_escape(
                            $negativeLabel
                        ) ?>

                    </strong>

                </div>


                <div class="row">

                    <span>
                        Access
                    </span>

                    <strong
                        class="<?= $isLive
                            ? 'verified'
                            : '' ?>"
                    >

                        <?= start_exam_escape(
                            $subscriptionLabel
                        ) ?>

                    </strong>

                </div>


                <div class="row">

                    <span>
                        Available From
                    </span>

                    <strong>

                        <?= start_exam_escape(
                            $startDateLabel
                        ) ?>

                    </strong>

                </div>


                <div class="row">

                    <span>
                        Available Until
                    </span>

                    <strong>

                        <?= start_exam_escape(
                            $endDateLabel
                        ) ?>

                    </strong>

                </div>


            </section>


        </div>


        <!-- =====================================================
             RIGHT
             ===================================================== -->


        <div>


            <section class="card">


                <h2>
                    Before You Start
                </h2>


                <p
                    class="card-description"
                >

                    Please read these instructions carefully.

                </p>


                <div class="rules">


                    <div class="rule">

                        <i
                            class="fa-solid fa-clock"
                        ></i>


                        <div>

                            <strong>
                                Timer starts immediately
                            </strong>

                            <span>

                                Your server-controlled
                                examination time begins
                                when the attempt is created.

                            </span>

                        </div>

                    </div>


                    <div class="rule">

                        <i
                            class="fa-solid fa-floppy-disk"
                        ></i>


                        <div>

                            <strong>
                                Answers are saved
                            </strong>

                            <span>

                                Your answers are stored
                                during the examination.

                            </span>

                        </div>

                    </div>


                    <div class="rule">

                        <i
                            class="fa-solid fa-chart-line"
                        ></i>


                        <div>

                            <strong>
                                Result is calculated
                            </strong>

                            <span>

                                Your performance is evaluated
                                after final submission.

                            </span>

                        </div>

                    </div>


                    <?php if ($isLive): ?>


                        <div
                            class="
                                rule
                                subscription-rule
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-shield-halved
                                "
                            ></i>


                            <div>

                                <strong>
                                    Active Subscription Verified
                                </strong>

                                <span>

                                    This Live Exam is available
                                    only to students with a valid
                                    active subscription.

                                </span>

                            </div>

                        </div>


                    <?php else: ?>


                        <div class="rule">

                            <i
                                class="fa-solid fa-unlock"
                            ></i>


                            <div>

                                <strong>
                                    Free Practice Exam
                                </strong>

                                <span>

                                    No subscription is required
                                    for this practice examination.

                                </span>

                            </div>

                        </div>


                    <?php endif; ?>


                </div>


            </section>


        </div>


    </section>


    <!-- =========================================================
         START
         ========================================================= -->


    <section class="start-card">


        <div class="start-top">


            <div>

                <strong>
                    <?= $isLive
                        ? 'Ready to join the Live Exam?'
                        : 'Ready to begin?' ?>
                </strong>


                <span>

                    <?= $actualQuestionCount ?>

                    questions

                    ·

                    <?= $durationMinutes ?>

                    minutes

                    ·

                    <?= start_exam_escape(
                        $negativeLabel
                    ) ?>

                    negative marking

                </span>

            </div>


            <form
                method="post"
                id="startExamForm"
            >


                <input
                    type="hidden"
                    name="exam_id"
                    value="<?= $examId ?>"
                >


                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= start_exam_escape(
                        $examCsrfToken
                    ) ?>"
                >


                <button
                    type="submit"
                    class="start-btn"
                    id="startExamButton"
                >

                    <i
                        class="
                            fa-solid
                            <?= $isLive
                                ? 'fa-tower-broadcast'
                                : 'fa-rocket' ?>
                        "
                    ></i>

                    <?= start_exam_escape(
                        $startButtonText
                    ) ?>

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </button>


            </form>


        </div>


        <div class="note">


            <i
                class="fa-solid fa-shield-halved"
            ></i>


            <span>

                <?= $isLive
                    ? 'Starting this Live Exam creates one secure attempt for your student account. Your active subscription is verified again on the server before the attempt is created.'
                    : 'Starting the exam creates one secure attempt for your student account. Opening this page alone does not start the exam.' ?>

            </span>


        </div>


    </section>


</main>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const form =
            document.getElementById(
                'startExamForm'
            );


        const button =
            document.getElementById(
                'startExamButton'
            );


        if (
            form &&
            button
        ) {

            form.addEventListener(
                'submit',
                function () {

                    button.disabled =
                        true;


                    button.innerHTML =
                        '<i class="fa-solid fa-spinner fa-spin"></i> ' +
                        (
                            <?= json_encode(
                                $isLive
                            ) ?>
                                ? 'Joining Live Exam...'
                                : 'Starting Exam...'
                        );

                }
            );

        }

    }
);

</script>


</body>

</html>