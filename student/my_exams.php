<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';


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
    (int)
    $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function my_exams_escape(
    mixed $value
): string {

    return htmlspecialchars(
        (string) (
            $value ?? ''
        ),
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function my_exams_number(
    float $value
): string {

    return rtrim(
        rtrim(
            number_format(
                $value,
                2,
                '.',
                ''
            ),
            '0'
        ),
        '.'
    );
}


function my_exams_date(
    ?string $value,
    string $format = 'd M Y, h:i A'
): string {

    if (
        empty($value)
    ) {

        return 'Not scheduled';
    }


    try {

        return (
            new DateTimeImmutable(
                $value
            )
        )->format(
            $format
        );

    } catch (
        Throwable $exception
    ) {

        return 'Not scheduled';
    }
}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search =
    trim(
        (string) (
            $_GET['search']
            ?? ''
        )
    );


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

$statusFilter =
    trim(
        (string) (
            $_GET['status']
            ?? 'All'
        )
    );


$allowedFilters = [

    'All',

    'Practice',

    'Live',

    'Active',

    'Upcoming',

    'Completed'

];


if (
    !in_array(
        $statusFilter,
        $allowedFilters,
        true
    )
) {

    $statusFilter =
        'All';
}


/*
|--------------------------------------------------------------------------
| LOAD EXAMS
|--------------------------------------------------------------------------
|
| Each student-state value is calculated independently.
|
| This avoids row multiplication caused by joining:
|
| exam_questions × questions × attempts × results
|
|--------------------------------------------------------------------------
*/

$examList = [];


try {

    $sql = "

        SELECT

            e.id,

            e.subject_id,

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

            e.created_at,

            e.updated_at,


            s.name AS subject_name,

            s.code AS subject_code,

            s.status AS subject_status,


            /*
             * Exact active assigned question count.
             */
            (

                SELECT
                    COUNT(DISTINCT eq.question_id)

                FROM exam_questions AS eq

                INNER JOIN questions AS q

                    ON q.id =
                       eq.question_id

                WHERE

                    eq.exam_id =
                    e.id

                    AND q.status =
                    'Active'

            ) AS active_question_count,


            /*
             * Latest STARTED attempt.
             */
            (

                SELECT
                    ea.id

                FROM exam_attempts AS ea

                WHERE

                    ea.exam_id =
                    e.id

                    AND ea.student_id =
                    :student_started_student

                    AND ea.status =
                    'Started'

                ORDER BY
                    ea.id DESC

                LIMIT 1

            ) AS started_attempt_id,


            /*
             * Latest completed attempt.
             */
            (

                SELECT
                    ea.id

                FROM exam_attempts AS ea

                WHERE

                    ea.exam_id =
                    e.id

                    AND ea.student_id =
                    :student_completed_student

                    AND ea.status IN (
                        'Submitted',
                        'Auto Submitted'
                    )

                ORDER BY
                    ea.id DESC

                LIMIT 1

            ) AS completed_attempt_id,


            /*
             * Latest result ID.
             */
            (

                SELECT
                    r.id

                FROM results AS r

                WHERE

                    r.exam_id =
                    e.id

                    AND r.student_id =
                    :student_result_student

                ORDER BY
                    r.id DESC

                LIMIT 1

            ) AS latest_result_id,


            /*
             * Latest result percentage.
             */
            (

                SELECT
                    r.percentage

                FROM results AS r

                WHERE

                    r.exam_id =
                    e.id

                    AND r.student_id =
                    :student_percentage_student

                ORDER BY
                    r.id DESC

                LIMIT 1

            ) AS latest_percentage,


            /*
             * Latest result status.
             */
            (

                SELECT
                    r.result_status

                FROM results AS r

                WHERE

                    r.exam_id =
                    e.id

                    AND r.student_id =
                    :student_result_status_student

                ORDER BY
                    r.id DESC

                LIMIT 1

            ) AS latest_result_status


        FROM exams AS e


        INNER JOIN subjects AS s

            ON s.id =
               e.subject_id


        WHERE 1 = 1

    ";


    $parameters = [

        ':student_started_student' =>
            $studentId,

        ':student_completed_student' =>
            $studentId,

        ':student_result_student' =>
            $studentId,

        ':student_percentage_student' =>
            $studentId,

        ':student_result_status_student' =>
            $studentId

    ];


    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

    if (
        $search !== ''
    ) {

        $sql .= "

            AND (

                e.title LIKE
                :search

                OR e.description LIKE
                :search

                OR s.name LIKE
                :search

                OR s.code LIKE
                :search

            )

        ";


        $parameters[
            ':search'
        ] =
            '%' .
            $search .
            '%';
    }


    /*
    |--------------------------------------------------------------------------
    | TYPE FILTER
    |--------------------------------------------------------------------------
    */

    if (
        $statusFilter ===
        'Practice'
    ) {

        $sql .= "
            AND e.exam_type = 'Practice'
        ";

    } elseif (
        $statusFilter ===
        'Live'
    ) {

        $sql .= "
            AND e.exam_type = 'Live'
        ";

    } elseif (
        $statusFilter ===
        'Active'
    ) {

        $sql .= "
            AND e.status = 'Active'
        ";
    }


    /*
    |--------------------------------------------------------------------------
    | ORDER
    |--------------------------------------------------------------------------
    */

    $sql .= "

        ORDER BY

            CASE

                WHEN e.exam_type = 'Live'

                    AND e.starts_at IS NOT NULL

                    AND e.starts_at <= NOW()

                    AND (
                        e.ends_at IS NULL
                        OR e.ends_at > NOW()
                    )

                    THEN 0


                WHEN e.exam_type = 'Live'

                    AND e.starts_at IS NOT NULL

                    AND e.starts_at > NOW()

                    THEN 1


                WHEN e.exam_type = 'Practice'

                    THEN 2


                ELSE 3

            END,


            COALESCE(
                e.starts_at,
                e.updated_at,
                e.created_at
            ) ASC,


            e.id ASC

    ";


    $statement =
        $conn->prepare(
            $sql
        );


    foreach (
        $parameters
        as $key => $value
    ) {

        $statement->bindValue(
            $key,
            $value
        );
    }


    $statement->execute();


    $rawExams =
        $statement->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | STUDENT-FACING STATE
    |--------------------------------------------------------------------------
    */

    foreach (
        $rawExams
        as $exam
    ) {

        $examId =
            (int)
            $exam['id'];


        $activeQuestionCount =
            (int) (
                $exam[
                    'active_question_count'
                ]
                ?? 0
            );


        $requiredQuestionCount =
            (int) (
                $exam[
                    'required_question_count'
                ]
                ?? 0
            );


        $subjectIsActive =
            (
                (string)
                (
                    $exam[
                        'subject_status'
                    ]
                    ?? ''
                )
                ===
                'Active'
            );


        /*
        |--------------------------------------------------------------------------
        | EXACT READINESS
        |--------------------------------------------------------------------------
        */

        $ready =

            $requiredQuestionCount > 0

            &&

            $activeQuestionCount ===
            $requiredQuestionCount

            &&

            $subjectIsActive;


        /*
        |--------------------------------------------------------------------------
        | ATTEMPTS / RESULT
        |--------------------------------------------------------------------------
        */

        $startedAttemptId =
            !empty(
                $exam[
                    'started_attempt_id'
                ]
            )
                ? (int)
                $exam[
                    'started_attempt_id'
                ]
                : null;


        $completedAttemptId =
            !empty(
                $exam[
                    'completed_attempt_id'
                ]
            )
                ? (int)
                $exam[
                    'completed_attempt_id'
                ]
                : null;


        $latestResultId =
            !empty(
                $exam[
                    'latest_result_id'
                ]
            )
                ? (int)
                $exam[
                    'latest_result_id'
                ]
                : null;


        /*
        |--------------------------------------------------------------------------
        | DATE OBJECTS
        |--------------------------------------------------------------------------
        */

        $now =
            new DateTimeImmutable();


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
                        (string)
                        $exam[
                            'starts_at'
                        ]
                    );

            } catch (
                Throwable $exception
            ) {

                $startsAt =
                    null;
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
                        (string)
                        $exam[
                            'ends_at'
                        ]
                    );

            } catch (
                Throwable $exception
            ) {

                $endsAt =
                    null;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | STATE DEFAULT
        |--------------------------------------------------------------------------
        */

        $state =
            'unavailable';


        $stateLabel =
            'Unavailable';


        $stateClass =
            'unavailable';


        $actionType =
            'details';


        $actionText =
            'View details';


        $actionUrl =
            'exam-details.php?exam_id='
            .
            $examId;


        /*
        |--------------------------------------------------------------------------
        | COMPLETED RESULT HAS PRIORITY
        |--------------------------------------------------------------------------
        |
        | Once a student has a completed attempt, show the result unless
        | there is a newer active attempt.
        |
        |--------------------------------------------------------------------------
        */

        if (
            $startedAttemptId !== null
        ) {

            /*
            |--------------------------------------------------------------------------
            | Active attempt
            |--------------------------------------------------------------------------
            */

            $state =
                'in-progress';


            $stateLabel =
                'In progress';


            $stateClass =
                'in-progress';


            $actionType =
                'resume';


            $actionText =
                (
                    $exam['exam_type']
                    ===
                    'Live'
                )
                    ? 'Resume live exam'
                    : 'Resume exam';


            $actionUrl =
                'start_exam.php?id=' .
                $examId;


        } elseif (
            $completedAttemptId !== null
            &&
            $latestResultId !== null
        ) {

            /*
            |--------------------------------------------------------------------------
            | Completed
            |--------------------------------------------------------------------------
            */

            $state =
                'completed';


            $stateLabel =
                'Completed';


            $stateClass =
                'completed';


            $actionType =
                'result';


            $actionText =
                'View result';


            $actionUrl =
                'result.php?id=' .
                $latestResultId;


        } elseif (
            $exam['exam_type'] ===
            'Practice'
        ) {

            /*
            |--------------------------------------------------------------------------
            | PRACTICE EXAM
            |--------------------------------------------------------------------------
            */

            if (
                $exam['status'] ===
                'Active'
                &&
                $ready
            ) {

                $state =
                    'available';


                $stateLabel =
                    'Available';


                $stateClass =
                    'available';


                $actionType =
                    'start';


                $actionText =
                    'Start practice';


                $actionUrl =
                    'start_exam.php?id=' .
                    $examId;


            } elseif (
                $exam['status'] ===
                'Active'
            ) {

                $state =
                    'preparing';


                $stateLabel =
                    'Preparing';


                $stateClass =
                    'preparing';


                $actionType =
                    'details';


                $actionText =
                    'View details';
            }


        } elseif (
            $exam['exam_type'] ===
            'Live'
        ) {

            /*
            |--------------------------------------------------------------------------
            | LIVE EXAM
            |--------------------------------------------------------------------------
            */

            if (
                $exam['status'] ===
                'Completed'
                ||
                $exam['status'] ===
                'Cancelled'
            ) {

                $state =
                    'ended';


                $stateLabel =
                    'Ended';


                $stateClass =
                    'ended';


            } elseif (
                !$ready
            ) {

                $state =
                    'preparing';


                $stateLabel =
                    'Preparing';


                $stateClass =
                    'preparing';


            } elseif (
                $startsAt !== null
                &&
                $now < $startsAt
            ) {

                $state =
                    'upcoming';


                $stateLabel =
                    'Upcoming';


                $stateClass =
                    'upcoming';


                $actionType =
                    'details';


                $actionText =
                    'View details';


            } elseif (
                $endsAt !== null
                &&
                $now >= $endsAt
            ) {

                $state =
                    'ended';


                $stateLabel =
                    'Ended';


                $stateClass =
                    'ended';


                $actionType =
                    'details';


                $actionText =
                    'View details';


            } elseif (
                $startsAt === null
                &&
                $endsAt === null
            ) {

                /*
                |--------------------------------------------------------------------------
                | Live exam without an explicit schedule.
                |--------------------------------------------------------------------------
                */

                if (
                    $exam['status'] ===
                    'Live'
                    ||
                    $exam['status'] ===
                    'Running'
                ) {

                    $state =
                        'live';


                    $stateLabel =
                        'Live now';


                    $stateClass =
                        'live';


                    $actionType =
                        'start';


                    $actionText =
                        'Join live exam';


                    $actionUrl =
                        'start_exam.php?id=' .
                        $examId;

                } else {

                    $state =
                        'upcoming';


                    $stateLabel =
                        'Upcoming';


                    $stateClass =
                        'upcoming';
                }


            } elseif (
                $exam['status'] ===
                'Live'
                ||
                $exam['status'] ===
                'Running'
                ||
                (
                    $startsAt !== null
                    &&
                    $now >= $startsAt
                    &&
                    (
                        $endsAt === null
                        ||
                        $now < $endsAt
                    )
                )
            ) {

                $state =
                    'live';


                $stateLabel =
                    'Live now';


                $stateClass =
                    'live';


                $actionType =
                    'start';


                $actionText =
                    'Join live exam';


                $actionUrl =
                    'start_exam.php?id=' .
                    $examId;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | LIVE RESULT AFTER END
        |--------------------------------------------------------------------------
        */

        if (
            $state ===
            'ended'
            &&
            $latestResultId !== null
        ) {

            $actionType =
                'result';


            $actionText =
                'View result';


            $actionUrl =
                'result.php?id=' .
                $latestResultId;
        }


        /*
        |--------------------------------------------------------------------------
        | FILTER
        |--------------------------------------------------------------------------
        */

        $includeExam =
            true;


        if (
            $statusFilter ===
            'Upcoming'
        ) {

            $includeExam =
                $state ===
                'upcoming';


        } elseif (
            $statusFilter ===
            'Completed'
        ) {

            $includeExam =
                $state ===
                'completed';
        }


        if (
            !$includeExam
        ) {

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | PRESENTATION DATA
        |--------------------------------------------------------------------------
        */

        $exam[
            'student_state'
        ] =
            $state;


        $exam[
            'student_state_label'
        ] =
            $stateLabel;


        $exam[
            'student_state_class'
        ] =
            $stateClass;


        $exam[
            'student_action_type'
        ] =
            $actionType;


        $exam[
            'student_action_text'
        ] =
            $actionText;


        $exam[
            'student_action_url'
        ] =
            $actionUrl;


        $exam[
            'ready'
        ] =
            $ready;


        $exam[
            'starts_at_object'
        ] =
            $startsAt;


        $exam[
            'ends_at_object'
        ] =
            $endsAt;


        $examList[] =
            $exam;
    }


} catch (
    Throwable $exception
) {

    error_log(
        'ExamSphere My Exams query failed: ' .
        $exception->getMessage()
    );


    $examList =
        [];

}


/*
|--------------------------------------------------------------------------
| PAGE STATS
|--------------------------------------------------------------------------
*/

$totalExams =
    count(
        $examList
    );


$availableCount =
    0;


$upcomingCount =
    0;


$completedCount =
    0;


$liveCount =
    0;


foreach (
    $examList
    as $exam
) {

    switch (
        $exam[
            'student_state'
        ]
    ) {

        case 'available':

            $availableCount++;

            break;


        case 'upcoming':

            $upcomingCount++;

            break;


        case 'completed':

            $completedCount++;

            break;


        case 'live':

            $liveCount++;

            break;

    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >


    <meta
        name="theme-color"
        content="#f5f5dc"
    >


    <title>
        My Exams | ExamSphere
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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >


    <link
        rel="stylesheet"
        href="assets/css/dashboard.css"
    >


    <link
        rel="stylesheet"
        href="assets/css/my-exams-pro.css"
    >

</head>


<body class="my-exams-body">


<?php include 'includes/navbar.php'; ?>


<main class="my-exams-pro">


<div class="container">


    <!-- =========================================================
         HEADER
    ========================================================== -->

    <section class="my-exams-header">


        <div>

            <span class="my-exams-kicker">

                <i
                    class="fa-solid fa-clipboard-list"
                ></i>

                EXAM CENTER

            </span>


            <h1>

                Your examinations.
                <em>All in one place.</em>

            </h1>


            <p>

                Track available practice exams, upcoming live
                examinations and your completed attempts.

            </p>

        </div>


        <a
            href="dashboard.php"
            class="my-exams-dashboard-link"
        >

            <i
                class="fa-solid fa-arrow-left"
            ></i>

            Dashboard

        </a>


    </section>


    <!-- =========================================================
         OVERVIEW
    ========================================================== -->

    <section class="my-exams-stats">


        <div class="my-exams-stat-card">

            <span>

                <i
                    class="fa-solid fa-layer-group"
                ></i>

            </span>


            <div>

                <small>
                    All exams
                </small>


                <strong>
                    <?= $totalExams ?>
                </strong>

            </div>

        </div>


        <div class="my-exams-stat-card">

            <span>

                <i
                    class="fa-solid fa-rocket"
                ></i>

            </span>


            <div>

                <small>
                    Available
                </small>


                <strong>
                    <?= $availableCount ?>
                </strong>

            </div>

        </div>


        <div class="my-exams-stat-card">

            <span>

                <i
                    class="fa-solid fa-tower-broadcast"
                ></i>

            </span>


            <div>

                <small>
                    Live now
                </small>


                <strong>
                    <?= $liveCount ?>
                </strong>

            </div>

        </div>


        <div class="my-exams-stat-card">

            <span>

                <i
                    class="fa-solid fa-calendar-days"
                ></i>

            </span>


            <div>

                <small>
                    Upcoming
                </small>


                <strong>
                    <?= $upcomingCount ?>
                </strong>

            </div>

        </div>


        <div class="my-exams-stat-card">

            <span>

                <i
                    class="fa-solid fa-chart-column"
                ></i>

            </span>


            <div>

                <small>
                    Completed
                </small>


                <strong>
                    <?= $completedCount ?>
                </strong>

            </div>

        </div>


    </section>


    <!-- =========================================================
         FILTER
    ========================================================== -->

    <section class="my-exams-filter-panel">


        <form
            method="GET"
            class="my-exams-filter-form"
        >


            <div class="my-exams-search">

                <i
                    class="fa-solid fa-magnifying-glass"
                ></i>


                <input
                    type="search"
                    name="search"
                    value="<?= my_exams_escape(
                        $search
                    ) ?>"
                    placeholder="Search exam, subject or code..."
                    maxlength="150"
                >

            </div>


            <select
                name="status"
                class="my-exams-filter-select"
            >

                <option
                    value="All"
                    <?= $statusFilter === 'All'
                        ? 'selected'
                        : ''
                    ?>
                >
                    All exams
                </option>


                <option
                    value="Practice"
                    <?= $statusFilter === 'Practice'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Practice exams
                </option>


                <option
                    value="Live"
                    <?= $statusFilter === 'Live'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Live exams
                </option>


                <option
                    value="Upcoming"
                    <?= $statusFilter === 'Upcoming'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Upcoming
                </option>


                <option
                    value="Completed"
                    <?= $statusFilter === 'Completed'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Completed
                </option>


                <option
                    value="Active"
                    <?= $statusFilter === 'Active'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Active exams
                </option>

            </select>


            <button
                type="submit"
                class="my-exams-filter-btn"
            >

                <i
                    class="fa-solid fa-filter"
                ></i>

                Apply

            </button>


            <?php if (
                $search !== ''
                ||
                $statusFilter !== 'All'
            ): ?>

                <a
                    href="my_exams.php"
                    class="my-exams-clear-btn"
                >

                    Clear

                </a>

            <?php endif; ?>


        </form>

    </section>


    <!-- =========================================================
         RESULT HEADER
    ========================================================== -->

    <section class="my-exams-results-header">


        <div>

            <span>
                EXAMINATIONS
            </span>


            <h2>
                Your exam list
            </h2>

        </div>


        <span class="my-exams-result-count">

            <?= $totalExams ?>

            <?= $totalExams === 1
                ? 'exam'
                : 'exams'
            ?>

        </span>


    </section>


    <!-- =========================================================
         EXAM GRID
    ========================================================== -->

    <?php if (
        !empty(
            $examList
        )
    ): ?>


        <section class="my-exams-grid">


            <?php foreach (
                $examList
                as $index => $exam
            ): ?>


                <?php

                $examId =
                    (int)
                    $exam['id'];


                $state =
                    (string)
                    $exam[
                        'student_state'
                    ];


                $stateLabel =
                    (string)
                    $exam[
                        'student_state_label'
                    ];


                $stateClass =
                    (string)
                    $exam[
                        'student_state_class'
                    ];


                $actionText =
                    (string)
                    $exam[
                        'student_action_text'
                    ];


                $actionUrl =
                    (string)
                    $exam[
                        'student_action_url'
                    ];


                $actionType =
                    (string)
                    $exam[
                        'student_action_type'
                    ];


                $number =
                    str_pad(
                        (string) (
                            $index + 1
                        ),
                        2,
                        '0',
                        STR_PAD_LEFT
                    );


                $isLive =
                    $state === 'live';


                $isUpcoming =
                    $state === 'upcoming';


                $isCompleted =
                    $state === 'completed';


                $isInProgress =
                    $state === 'in-progress';


                $requiredQuestions =
                    (int) (
                        $exam[
                            'required_question_count'
                        ]
                        ?? 0
                    );


                $activeQuestions =
                    (int) (
                        $exam[
                            'active_question_count'
                        ]
                        ?? 0
                    );


                $duration =
                    (int) (
                        $exam[
                            'duration_minutes'
                        ]
                        ?? 0
                    );


                $totalMarks =
                    (float) (
                        $exam[
                            'total_marks'
                        ]
                        ?? 0
                    );


                $passingMarks =
                    (float) (
                        $exam[
                            'passing_marks'
                        ]
                        ?? 0
                    );


                $negativeMarking =
                    (
                        (int) (
                            $exam[
                                'negative_marking'
                            ]
                            ?? 0
                        )
                        ===
                        1
                    );


                $subscriptionRequired =
                    (
                        (int) (
                            $exam[
                                'subscription_required'
                            ]
                            ?? 0
                        )
                        ===
                        1
                    );


                $examFee =
                    (float) (
                        $exam[
                            'exam_fee'
                        ]
                        ?? 0
                    );

                ?>


                <article
                    class="
                        my-exam-card
                        state-<?= my_exams_escape(
                            $stateClass
                        ) ?>
                    "
                >


                    <!-- =========================================
                         TOP
                    ========================================== -->

                    <div class="my-exam-card-top">


                        <span class="my-exam-number">

                            <?= $number ?>

                        </span>


                        <div
                            class="my-exam-card-badges"
                        >


                            <span
                                class="
                                    my-exam-type
                                    <?= $exam['exam_type'] === 'Live'
                                        ? 'live'
                                        : 'practice'
                                    ?>
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        <?= $exam['exam_type'] === 'Live'
                                            ? 'fa-tower-broadcast'
                                            : 'fa-pen-to-square'
                                        ?>
                                    "
                                ></i>


                                <?= my_exams_escape(
                                    $exam[
                                        'exam_type'
                                    ]
                                ) ?>


                            </span>


                            <span
                                class="
                                    my-exam-state
                                    <?= my_exams_escape(
                                        $stateClass
                                    ) ?>
                                "
                            >

                                <?= my_exams_escape(
                                    $stateLabel
                                ) ?>

                            </span>


                        </div>

                    </div>


                    <!-- =========================================
                         ICON
                    ========================================== -->

                    <div class="my-exam-icon">

                        <i
                            class="
                                fa-solid
                                <?= $isLive
                                    ? 'fa-tower-broadcast'
                                    : 'fa-file-pen'
                                ?>
                            "
                        ></i>

                    </div>


                    <!-- =========================================
                         CONTENT
                    ========================================== -->

                    <div class="my-exam-content">


                        <span class="my-exam-subject">


                            <?= my_exams_escape(
                                $exam[
                                    'subject_name'
                                ]
                            ) ?>


                            <?php if (
                                !empty(
                                    $exam[
                                        'subject_code'
                                    ]
                                )
                            ): ?>


                                <small>

                                    •
                                    <?= my_exams_escape(
                                        $exam[
                                            'subject_code'
                                        ]
                                    ) ?>

                                </small>


                            <?php endif; ?>


                        </span>


                        <h3>

                            <?= my_exams_escape(
                                $exam[
                                    'title'
                                ]
                            ) ?>

                        </h3>


                        <p>

                            <?= my_exams_escape(
                                $exam[
                                    'description'
                                ]
                                ?:
                                'Examination preparation through ExamSphere.'
                            ) ?>

                        </p>


                    </div>


                    <!-- =========================================
                         INFORMATION
                    ========================================== -->

                    <div class="my-exam-meta">


                        <span>

                            <i
                                class="fa-regular fa-clock"
                            ></i>

                            <?= $duration ?>

                            min

                        </span>


                        <span>

                            <i
                                class="fa-solid fa-list-check"
                            ></i>

                            <?= $activeQuestions ?>

                            questions

                        </span>


                        <span>

                            <i
                                class="fa-solid fa-star"
                            ></i>

                            <?= my_exams_number(
                                $totalMarks
                            ) ?>

                            marks

                        </span>


                    </div>


                    <!-- =========================================
                         STATUS
                    ========================================== -->

                    <div
                        class="my-exam-status-info"
                    >


                        <?php if (
                            $isUpcoming
                            &&
                            !empty(
                                $exam[
                                    'starts_at_object'
                                ]
                            )
                        ): ?>


                            <span>

                                <i
                                    class="fa-regular fa-calendar"
                                ></i>

                                Starts

                                <?= my_exams_escape(
                                    $exam[
                                        'starts_at_object'
                                    ]->format(
                                        'd M Y, h:i A'
                                    )
                                ) ?>

                            </span>


                        <?php elseif (
                            $isLive
                        ): ?>


                            <span class="live-message">

                                <i
                                    class="fa-solid fa-circle"
                                ></i>

                                This examination is live now.

                            </span>


                        <?php elseif (
                            $isInProgress
                        ): ?>


                            <span>

                                <i
                                    class="fa-solid fa-play"
                                ></i>

                                You have an active attempt.

                            </span>


                        <?php elseif (
                            $isCompleted
                        ): ?>


                            <span>

                                <i
                                    class="fa-solid fa-chart-line"
                                ></i>

                                Latest result is available.

                            </span>


                        <?php elseif (
                            !$exam['ready']
                        ): ?>


                            <span
                                class="warning-message"
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-triangle-exclamation
                                    "
                                ></i>

                                <?= $requiredQuestions ?>

                                required /

                                <?= $activeQuestions ?>

                                active

                            </span>


                        <?php elseif (
                            $state ===
                            'ended'
                        ): ?>


                            <span>

                                <i
                                    class="
                                        fa-solid
                                        fa-circle-xmark
                                    "
                                ></i>

                                This examination has ended.

                            </span>


                        <?php else: ?>


                            <span>

                                <i
                                    class="
                                        fa-solid
                                        fa-circle-check
                                    "
                                ></i>

                                Ready to take

                            </span>


                        <?php endif; ?>


                        <?php if (
                            $negativeMarking
                        ): ?>


                            <span
                                class="negative-marking"
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-triangle-exclamation
                                    "
                                ></i>

                                Negative marking

                            </span>


                        <?php endif; ?>


                        <?php if (
                            $subscriptionRequired
                        ): ?>


                            <span>

                                <i
                                    class="
                                        fa-solid
                                        fa-crown
                                    "
                                ></i>

                                Subscription required

                            </span>


                        <?php endif; ?>


                        <?php if (
                            $examFee > 0
                        ): ?>


                            <span>

                                <i
                                    class="
                                        fa-solid
                                        fa-indian-rupee-sign
                                    "
                                ></i>

                                Live fee ₹<?= my_exams_number(
                                    $examFee
                                ) ?>

                            </span>


                        <?php endif; ?>


                    </div>


                    <!-- =========================================
                         ACTION
                    ========================================== -->

                    <div class="my-exam-action">


                        <a
                            href="<?= my_exams_escape(
                                $actionUrl
                            ) ?>"
                            class="
                                my-exam-action-btn
                                <?= (
                                    $actionType === 'start'
                                    ||
                                    $actionType === 'resume'
                                )
                                    ? 'primary'
                                    : 'secondary'
                                ?>
                            "
                            data-exam-action
                        >


                            <span>


                                <i
                                    class="
                                        fa-solid
                                        <?= (
                                            $actionType ===
                                            'start'
                                        )
                                            ? 'fa-rocket'
                                            : (
                                                $actionType ===
                                                'resume'
                                                    ? 'fa-play'
                                                    : (
                                                        $actionType ===
                                                        'result'
                                                            ? 'fa-chart-column'
                                                            : 'fa-circle-info'
                                                    )
                                            )
                                        ?>
                                    "
                                ></i>


                                <?= my_exams_escape(
                                    $actionText
                                ) ?>


                            </span>


                            <i
                                class="
                                    fa-solid
                                    fa-arrow-right
                                "
                            ></i>


                        </a>


                    </div>


                </article>


            <?php endforeach; ?>


        </section>


    <?php else: ?>


        <!-- =====================================================
             EMPTY STATE
        ====================================================== -->

        <section class="my-exams-empty">


            <div class="my-exams-empty-icon">

                <i
                    class="
                        fa-solid
                        fa-clipboard-question
                    "
                ></i>

            </div>


            <span>
                NOTHING TO SHOW
            </span>


            <h2>
                No examinations match your filters.
            </h2>


            <p>

                <?php if (
                    $search !== ''
                    ||
                    $statusFilter !== 'All'
                ): ?>


                    Try another search or remove the
                    selected filters.


                <?php else: ?>


                    Available examinations will appear here
                    once they are published and ready.


                <?php endif; ?>

            </p>


            <div class="my-exams-empty-actions">


                <?php if (
                    $search !== ''
                    ||
                    $statusFilter !== 'All'
                ): ?>


                    <a
                        href="my_exams.php"
                        class="my-exams-empty-btn secondary"
                    >

                        Show all

                    </a>


                <?php endif; ?>


                <a
                    href="practice_exams.php"
                    class="my-exams-empty-btn primary"
                >

                    Explore practice exams


                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>


                </a>


            </div>


        </section>


    <?php endif; ?>


    <!-- =========================================================
         HELP STRIP
    ========================================================== -->

    <section class="my-exams-help-strip">


        <div>


            <i
                class="fa-solid fa-shield-halved"
            ></i>


            <span>


                <strong>
                    Ready exams only
                </strong>


                <small>

                    ExamSphere checks the exact active question
                    count before allowing an attempt.

                </small>


            </span>


        </div>


        <div>


            <i
                class="fa-solid fa-chart-line"
            ></i>


            <span>


                <strong>
                    Results are connected
                </strong>


                <small>

                    Completed attempts lead directly to
                    your result and performance history.

                </small>


            </span>


        </div>


        <div>


            <i
                class="fa-solid fa-graduation-cap"
            ></i>


            <span>


                <strong>
                    Prepare consistently
                </strong>


                <small>

                    Practice regularly and use your
                    performance data to improve.

                </small>


            </span>


        </div>


    </section>


</div>

</main>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const actionButtons =
            document.querySelectorAll(
                '[data-exam-action]'
            );


        actionButtons.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        if (
                            button.dataset.loading ===
                            '1'
                        ) {

                            return;

                        }


                        button.dataset.loading =
                            '1';


                        button.classList.add(
                            'is-loading'
                        );


                        const originalHTML =
                            button.innerHTML;


                        button.innerHTML =
                            '<span>' +
                            '<i class="fa-solid fa-spinner fa-spin"></i>' +
                            ' Loading...' +
                            '</span>';


                        window.setTimeout(
                            function () {

                                if (
                                    document.visibilityState ===
                                    'visible'
                                ) {

                                    button.dataset.loading =
                                        '0';


                                    button.classList.remove(
                                        'is-loading'
                                    );


                                    button.innerHTML =
                                        originalHTML;
                                }

                            },
                            5000
                        );

                    }
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | SEARCH UX
        |--------------------------------------------------------------------------
        */

        const searchInput =
            document.querySelector(
                'input[name="search"]'
            );


        if (
            searchInput
        ) {

            searchInput.addEventListener(
                'input',
                function () {

                    if (
                        this.value.length >
                        150
                    ) {

                        this.value =
                            this.value.slice(
                                0,
                                150
                            );
                    }

                }
            );

        }


    }
);

</script>


</body>

</html>