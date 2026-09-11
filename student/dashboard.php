<?php

declare(strict_types=1);

require_once '../config/auth.php';


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

require_role('student');


$studentId =
    current_user_id();



/*
|--------------------------------------------------------------------------
| Load active student
|--------------------------------------------------------------------------
*/

try {

    $studentStatement =
        $conn->prepare("
            SELECT
                id,
                student_code,
                full_name,
                email,
                profile_photo,
                email_verified,
                status,
                last_login,
                created_at

            FROM students

            WHERE id = ?
                AND status = 'Active'

            LIMIT 1
        ");


    $studentStatement->execute([
        $studentId
    ]);


    $student =
        $studentStatement->fetch(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $exception) {

    error_log(
        'Student dashboard student query failed: ' .
        $exception->getMessage()
    );

    exit(
        'Unable to load your dashboard.'
    );
}


if (!$student) {

    session_destroy();

    header(
        'Location: ../auth/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function dashboard_escape(
    ?string $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function dashboard_number(
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


function dashboard_date(
    ?string $date,
    string $format = 'd M Y'
): string {

    if (
        empty($date)
    ) {
        return '—';
    }


    try {

        return (
            new DateTimeImmutable(
                $date
            )
        )->format(
            $format
        );

    } catch (Throwable $exception) {

        return '—';
    }
}


/*
|--------------------------------------------------------------------------
| Dashboard summary
|--------------------------------------------------------------------------
*/

$stats = [
    'completed_exams' => 0,
    'practice_exams' => 0,
    'average_score' => 0.0,
    'best_score' => 0.0,
    'pass_rate' => 0.0,
    'total_correct' => 0,
    'total_wrong' => 0,
    'total_unanswered' => 0
];


try {

    $statsStatement =
        $conn->prepare("
            SELECT

                COUNT(*) AS completed_exams,

                COALESCE(
                    AVG(percentage),
                    0
                ) AS average_score,

                COALESCE(
                    MAX(percentage),
                    0
                ) AS best_score,

                COALESCE(
                    (
                        SUM(
                            CASE
                                WHEN result_status = 'Pass'
                                THEN 1
                                ELSE 0
                            END
                        ) /
                        NULLIF(
                            COUNT(*),
                            0
                        )
                    ) * 100,
                    0
                ) AS pass_rate,

                COALESCE(
                    SUM(correct_answers),
                    0
                ) AS total_correct,

                COALESCE(
                    SUM(wrong_answers),
                    0
                ) AS total_wrong,

                COALESCE(
                    SUM(unanswered_questions),
                    0
                ) AS total_unanswered

            FROM results

            WHERE student_id = ?
        ");


    $statsStatement->execute([
        $studentId
    ]);


    $statsRow =
        $statsStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if ($statsRow) {

        $stats['completed_exams'] =
            (int) $statsRow['completed_exams'];

        $stats['average_score'] =
            (float) $statsRow['average_score'];

        $stats['best_score'] =
            (float) $statsRow['best_score'];

        $stats['pass_rate'] =
            (float) $statsRow['pass_rate'];

        $stats['total_correct'] =
            (int) $statsRow['total_correct'];

        $stats['total_wrong'] =
            (int) $statsRow['total_wrong'];

        $stats['total_unanswered'] =
            (int) $statsRow['total_unanswered'];
    }


} catch (Throwable $exception) {

    error_log(
        'Student dashboard statistics failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| Completed Practice Exam count
|--------------------------------------------------------------------------
*/

try {

    $practiceAttemptStatement =
        $conn->prepare("
            SELECT COUNT(DISTINCT r.exam_id)

            FROM results r

            INNER JOIN exams e
                ON e.id = r.exam_id

            WHERE r.student_id = ?
                AND e.exam_type = 'Practice'
        ");


    $practiceAttemptStatement->execute([
        $studentId
    ]);


    $stats['practice_exams'] =
        (int) $practiceAttemptStatement->fetchColumn();


} catch (Throwable $exception) {

    error_log(
        'Student dashboard practice count failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| Current rank
|--------------------------------------------------------------------------
|
| Rank is based on each student's best completed score.
|
*/

$rank =
    0;


if (
    $stats['completed_exams'] > 0
) {

    try {

        $rankStatement =
            $conn->prepare("
                SELECT
                    COUNT(*) + 1

                FROM (

                    SELECT
                        student_id,
                        MAX(percentage) AS best_percentage

                    FROM results

                    GROUP BY student_id

                ) leaderboard

                WHERE leaderboard.best_percentage >
                    (
                        SELECT
                            COALESCE(
                                MAX(percentage),
                                0
                            )

                        FROM results

                        WHERE student_id = ?
                    )
            ");


        $rankStatement->execute([
            $studentId
        ]);


        $rank =
            (int) $rankStatement->fetchColumn();


    } catch (Throwable $exception) {

        error_log(
            'Student dashboard rank failed: ' .
            $exception->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| Active subscription
|--------------------------------------------------------------------------
*/

$activeSubscription =
    null;


try {

    $subscriptionStatement =
        $conn->prepare("
            SELECT

                s.id,
                s.start_date,
                s.end_date,
                s.status,

                p.id AS plan_id,
                p.name AS plan_name,
                p.duration_months,
                p.price

            FROM subscriptions s

            INNER JOIN subscription_plans p
                ON p.id = s.plan_id

            WHERE s.student_id = ?

                AND s.status = 'Active'

                AND s.end_date >= CURDATE()

            ORDER BY
                s.end_date DESC,
                s.id DESC

            LIMIT 1
        ");


    $subscriptionStatement->execute([
        $studentId
    ]);


    $activeSubscription =
        $subscriptionStatement->fetch(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $exception) {

    error_log(
        'Student dashboard subscription failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| Subscription remaining days
|--------------------------------------------------------------------------
*/

$subscriptionDaysRemaining =
    0;


if (
    $activeSubscription
) {

    try {

        $today =
            new DateTimeImmutable(
                'today'
            );

        $endDate =
            new DateTimeImmutable(
                (string) $activeSubscription['end_date']
            );


        $subscriptionDaysRemaining =
            max(
                0,
                (int) $today->diff(
                    $endDate
                )->format('%r%a')
            );

    } catch (Throwable $exception) {

        $subscriptionDaysRemaining =
            0;
    }
}


/*
|--------------------------------------------------------------------------
| Upcoming Live Exams
|--------------------------------------------------------------------------
|
| Only complete exams are shown:
|
| active exam
| + valid live status
| + required question count
| + exact active question count
|
*/

$liveExams = [];


try {

    $liveStatement =
        $conn->prepare("
            SELECT

                e.id,
                e.title,
                e.description,
                e.starts_at,
                e.ends_at,
                e.duration_minutes,
                e.required_question_count,
                e.total_marks,
                e.exam_fee,
                e.subscription_required,

                s.name AS subject_name,

                COUNT(
                    DISTINCT
                    CASE
                        WHEN q.status = 'Active'
                        THEN q.id
                    END
                ) AS active_question_count

            FROM exams e

            LEFT JOIN subjects s
                ON s.id = e.subject_id
                AND s.status = 'Active'

            LEFT JOIN exam_questions eq
                ON eq.exam_id = e.id

            LEFT JOIN questions q
                ON q.id = eq.question_id

            WHERE e.exam_type = 'Live'

                AND e.status IN (
                    'Scheduled',
                    'Live',
                    'Upcoming',
                    'Running'
                )

                AND e.starts_at IS NOT NULL

                AND e.required_question_count > 0

            GROUP BY

                e.id,
                e.title,
                e.description,
                e.starts_at,
                e.ends_at,
                e.duration_minutes,
                e.required_question_count,
                e.total_marks,
                e.exam_fee,
                e.subscription_required,

                s.name

            HAVING
                active_question_count =
                e.required_question_count

            ORDER BY

                CASE
                    WHEN e.starts_at <= NOW()
                    AND (
                        e.ends_at IS NULL
                        OR e.ends_at >= NOW()
                    )
                    THEN 0
                    ELSE 1
                END,

                e.starts_at ASC,
                e.id ASC

            LIMIT 4
        ");


    $liveStatement->execute();


    $liveExams =
        $liveStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $exception) {

    error_log(
        'Student dashboard live exams failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| Recent results
|--------------------------------------------------------------------------
*/

$recentResults = [];


try {

    $recentResultStatement =
        $conn->prepare("
            SELECT

                r.id,
                r.exam_id,
                r.percentage,
                r.obtained_marks,
                r.total_marks,
                r.correct_answers,
                r.wrong_answers,
                r.unanswered_questions,
                r.result_status,
                r.grade,
                r.created_at,

                e.title AS exam_title,
                e.exam_type,

                s.name AS subject_name

            FROM results r

            INNER JOIN exams e
                ON e.id = r.exam_id

            LEFT JOIN subjects s
                ON s.id = e.subject_id

            WHERE r.student_id = ?

            ORDER BY
                r.created_at DESC,
                r.id DESC

            LIMIT 5
        ");


    $recentResultStatement->execute([
        $studentId
    ]);


    $recentResults =
        $recentResultStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $exception) {

    error_log(
        'Student dashboard recent results failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| Subject Performance
|--------------------------------------------------------------------------
*/

$subjectPerformance = [];


try {

    $subjectStatement =
        $conn->prepare("
            SELECT

                COALESCE(
                    s.name,
                    'General'
                ) AS subject_name,

                COUNT(r.id) AS attempts,

                ROUND(
                    AVG(r.percentage),
                    2
                ) AS average_percentage,

                ROUND(
                    MAX(r.percentage),
                    2
                ) AS best_percentage

            FROM results r

            INNER JOIN exams e
                ON e.id = r.exam_id

            LEFT JOIN subjects s
                ON s.id = e.subject_id

            WHERE r.student_id = ?

            GROUP BY
                e.subject_id,
                s.name

            ORDER BY
                average_percentage DESC,
                attempts DESC

            LIMIT 5
        ");


    $subjectStatement->execute([
        $studentId
    ]);


    $subjectPerformance =
        $subjectStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $exception) {

    error_log(
        'Student dashboard subject performance failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| Recommended Practice Exams
|--------------------------------------------------------------------------
|
| Only complete active exams.
|
*/

$recommendedExams = [];


try {

    $recommendedStatement =
        $conn->prepare("
            SELECT

                e.id,
                e.title,
                e.description,
                e.duration_minutes,
                e.required_question_count,
                e.total_marks,

                s.name AS subject_name,

                COUNT(
                    DISTINCT
                    CASE
                        WHEN q.status = 'Active'
                        THEN q.id
                    END
                ) AS active_question_count

            FROM exams e

            INNER JOIN subjects s
                ON s.id = e.subject_id
                AND s.status = 'Active'

            LEFT JOIN exam_questions eq
                ON eq.exam_id = e.id

            LEFT JOIN questions q
                ON q.id = eq.question_id

            WHERE e.exam_type = 'Practice'

                AND e.status = 'Active'

                AND e.required_question_count > 0

            GROUP BY

                e.id,
                e.title,
                e.description,
                e.duration_minutes,
                e.required_question_count,
                e.total_marks,

                s.name

            HAVING
                active_question_count =
                e.required_question_count

            ORDER BY
                e.updated_at DESC,
                e.id DESC

            LIMIT 4
        ");


    $recommendedStatement->execute();


    $recommendedExams =
        $recommendedStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $exception) {

    error_log(
        'Student dashboard recommended exams failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| First name / greeting
|--------------------------------------------------------------------------
*/

$fullName =
    trim(
        (string) $student['full_name']
    );


$nameParts =
    preg_split(
        '/\s+/',
        $fullName
    );


$firstName =
    !empty($nameParts[0])
        ? $nameParts[0]
        : 'Student';


$currentHour =
    (int) date('G');


if ($currentHour < 12) {

    $greeting =
        'Good morning';

} elseif ($currentHour < 17) {

    $greeting =
        'Good afternoon';

} else {

    $greeting =
        'Good evening';
}


/*
|--------------------------------------------------------------------------
| Average score label
|--------------------------------------------------------------------------
*/

if (
    $stats['completed_exams'] === 0
) {

    $scoreMessage =
        'Your first result will appear here after your first completed exam.';

} elseif (
    $stats['average_score'] >= 80
) {

    $scoreMessage =
        'Excellent consistency. Keep pushing your strongest subjects.';

} elseif (
    $stats['average_score'] >= 60
) {

    $scoreMessage =
        'Good progress. Focus on consistency to raise your average.';

} else {

    $scoreMessage =
        'Keep practising steadily. Every completed exam gives you useful feedback.';
}


/*
|--------------------------------------------------------------------------
| Overall score ring
|--------------------------------------------------------------------------
*/

$averageScore =
    max(
        0,
        min(
            100,
            (float) $stats['average_score']
        )
    );


/*
|--------------------------------------------------------------------------
| Today's date
|--------------------------------------------------------------------------
*/

$todayLabel =
    date(
        'D, d M Y'
    );

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
        Student Dashboard | ExamSphere
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
        href="assets/css/student-dashboard-modern.css"
    >


    <link
        rel="stylesheet"
        href="assets/css/student-dashboard-pro.css"
    >

</head>


<body class="student-dashboard-body">


<?php include 'includes/navbar.php'; ?>


<main class="student-dashboard-pro">


    <!-- =====================================================
         WELCOME
    ====================================================== -->

    <section class="student-pro-welcome">

        <div class="student-pro-welcome-content">

            <span class="student-pro-kicker">

                <i class="fa-solid fa-hand-sparkles"></i>

                <?= dashboard_escape(
                    $greeting
                ) ?>

                <?= dashboard_escape(
                    $firstName
                ) ?>

            </span>


            <h1>

                Keep learning.
                <em>Keep growing.</em>

            </h1>


            <p>

                Your personal ExamSphere command center for
                practice exams, live examinations, results,
                study resources and performance tracking.

            </p>


            <div class="student-pro-welcome-actions">

                <a
                    href="practice_exams.php"
                    class="student-pro-primary-btn"
                >

                    <i
                        class="fa-solid fa-rocket"
                    ></i>

                    Start practising

                </a>


                <a
                    href="live_exams.php"
                    class="student-pro-secondary-btn"
                >

                    <i
                        class="fa-solid fa-tower-broadcast"
                    ></i>

                    View live exams

                </a>

            </div>

        </div>


        <div class="student-pro-date-card">

            <span>

                <i
                    class="fa-regular fa-calendar-check"
                ></i>

                TODAY

            </span>


            <strong>

                <?= dashboard_escape(
                    $todayLabel
                ) ?>

            </strong>


            <small>

                Make your next attempt count.

            </small>

        </div>

    </section>


    <!-- =====================================================
         KPIs
    ====================================================== -->

    <section
        class="student-pro-kpi-grid"
        aria-label="Student performance"
    >


        <article class="student-pro-kpi">

            <span class="student-pro-kpi-icon olive">

                <i
                    class="fa-solid fa-file-circle-check"
                ></i>

            </span>


            <div>

                <small>
                    Completed exams
                </small>

                <strong>
                    <?= $stats['completed_exams'] ?>
                </strong>


                <a href="results.php">

                    View results

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>

        </article>


        <article class="student-pro-kpi">

            <span class="student-pro-kpi-icon brown">

                <i
                    class="fa-solid fa-pen-ruler"
                ></i>

            </span>


            <div>

                <small>
                    Practice exams
                </small>

                <strong>
                    <?= $stats['practice_exams'] ?>
                </strong>


                <a href="practice_exams.php">

                    Practice more

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>

        </article>


        <article class="student-pro-kpi">

            <span class="student-pro-kpi-icon gold">

                <i
                    class="fa-solid fa-trophy"
                ></i>

            </span>


            <div>

                <small>
                    Best score
                </small>

                <strong>
                    <?= dashboard_number(
                        $stats['best_score']
                    ) ?>%
                </strong>


                <a href="performance.php">

                    Performance

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>

        </article>


        <article class="student-pro-kpi">

            <span class="student-pro-kpi-icon green">

                <i
                    class="fa-solid fa-chart-line"
                ></i>

            </span>


            <div>

                <small>
                    Average score
                </small>

                <strong>
                    <?= dashboard_number(
                        $stats['average_score']
                    ) ?>%
                </strong>


                <a href="performance.php">

                    Track progress

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>

        </article>


        <article class="student-pro-kpi">

            <span class="student-pro-kpi-icon slate">

                <i
                    class="fa-solid fa-ranking-star"
                ></i>

            </span>


            <div>

                <small>
                    Current rank
                </small>

                <strong>

                    <?= $rank > 0
                        ? '#' . $rank
                        : '—'
                    ?>

                </strong>


                <a href="leaderboard.php">

                    View leaderboard

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>

        </article>


    </section>


    <!-- =====================================================
         MAIN GRID
    ====================================================== -->

    <section class="student-pro-main-grid">


        <!-- =================================================
             PREPARATION
        ================================================== -->

        <article
            class="
                student-pro-panel
                preparation-pro-panel
            "
        >

            <div class="student-pro-panel-heading">

                <div>

                    <span>
                        PREPARATION
                    </span>

                    <h2>
                        Your overall progress
                    </h2>

                </div>


                <a
                    href="performance.php"
                >

                    Analytics

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>


            <div class="preparation-pro-content">


                <div
                    class="pro-score-ring"
                    style="
                        --score:
                        <?= $averageScore ?>;
                    "
                >

                    <div>

                        <strong>
                            <?= dashboard_number(
                                $averageScore
                            ) ?>%
                        </strong>

                        <span>
                            Average score
                        </span>

                    </div>

                </div>


                <div class="preparation-pro-side">

                    <div class="preparation-pro-line">

                        <span>

                            <i
                                class="fa-solid fa-check"
                            ></i>

                            Correct answers

                        </span>


                        <strong>
                            <?= $stats['total_correct'] ?>
                        </strong>

                    </div>


                    <div class="preparation-pro-line">

                        <span>

                            <i
                                class="fa-solid fa-xmark"
                            ></i>

                            Wrong answers

                        </span>


                        <strong>
                            <?= $stats['total_wrong'] ?>
                        </strong>

                    </div>


                    <div class="preparation-pro-line">

                        <span>

                            <i
                                class="fa-solid fa-minus"
                            ></i>

                            Unanswered

                        </span>


                        <strong>
                            <?= $stats['total_unanswered'] ?>
                        </strong>

                    </div>


                    <div class="preparation-pro-message">

                        <i
                            class="fa-solid fa-bullseye"
                        ></i>


                        <span>

                            <?= dashboard_escape(
                                $scoreMessage
                            ) ?>

                        </span>

                    </div>

                </div>

            </div>

        </article>


        <!-- =================================================
             SUBSCRIPTION
        ================================================== -->

        <article
            class="
                student-pro-panel
                subscription-pro-panel
                <?= $activeSubscription
                    ? 'subscription-active'
                    : ''
                ?>"
        >

            <div class="student-pro-panel-heading">

                <div>

                    <span>
                        MEMBERSHIP
                    </span>

                    <h2>
                        Your subscription
                    </h2>

                </div>


                <i
                    class="fa-solid fa-gem"
                ></i>

            </div>


            <?php if (
                $activeSubscription
            ): ?>

                <div class="subscription-active-card">

                    <div class="subscription-plan-row">

                        <span class="subscription-crown">

                            <i
                                class="fa-solid fa-crown"
                            ></i>

                        </span>


                        <div>

                            <strong>

                                <?= dashboard_escape(
                                    $activeSubscription[
                                        'plan_name'
                                    ]
                                ) ?>

                            </strong>


                            <small>

                                Active membership

                            </small>

                        </div>

                    </div>


                    <div class="subscription-date-row">

                        <span>

                            <small>
                                Started
                            </small>

                            <strong>

                                <?= dashboard_date(
                                    $activeSubscription[
                                        'start_date'
                                    ]
                                ) ?>

                            </strong>

                        </span>


                        <span>

                            <small>
                                Expires
                            </small>

                            <strong>

                                <?= dashboard_date(
                                    $activeSubscription[
                                        'end_date'
                                    ]
                                ) ?>

                            </strong>

                        </span>

                    </div>


                    <div class="subscription-remaining">

                        <span>

                            <i
                                class="fa-solid fa-hourglass-half"
                            ></i>

                            <?= $subscriptionDaysRemaining ?>

                            <?= $subscriptionDaysRemaining === 1
                                ? 'day'
                                : 'days'
                            ?>

                            remaining

                        </span>


                        <a
                            href="subscriptions.php"
                        >

                            Manage

                            <i
                                class="fa-solid fa-arrow-right"
                            ></i>

                        </a>

                    </div>

                </div>

            <?php else: ?>

                <div class="subscription-empty">

                    <div class="subscription-empty-icon">

                        <i
                            class="fa-solid fa-gem"
                        ></i>

                    </div>


                    <h3>

                        No active membership

                    </h3>


                    <p>

                        Practice exams remain available.
                        Upgrade when you need subscription-only resources.

                    </p>


                    <a
                        href="subscriptions.php"
                    >

                        Explore plans

                        <i
                            class="fa-solid fa-arrow-right"
                        ></i>

                    </a>

                </div>

            <?php endif; ?>

        </article>


        <!-- =================================================
             LIVE EXAMS
        ================================================== -->

        <article
            class="
                student-pro-panel
                student-pro-live-panel
            "
        >

            <div class="student-pro-panel-heading">

                <div>

                    <span>
                        LIVE EXAMS
                    </span>

                    <h2>
                        Upcoming opportunities
                    </h2>

                </div>


                <a
                    href="live_exams.php"
                >

                    View all

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>


            <?php if (
                !empty($liveExams)
            ): ?>

                <div class="student-pro-live-list">

                    <?php foreach (
                        $liveExams
                        as $liveExam
                    ): ?>

                        <?php

                        $startsAt =
                            null;

                        $isLiveNow =
                            false;


                        if (
                            !empty(
                                $liveExam['starts_at']
                            )
                        ) {

                            try {

                                $startsAt =
                                    new DateTimeImmutable(
                                        $liveExam['starts_at']
                                    );

                            } catch (Throwable $exception) {

                                $startsAt =
                                    null;
                            }
                        }


                        if (
                            $startsAt !== null
                        ) {

                            try {

                                $now =
                                    new DateTimeImmutable();

                                $endsAt =
                                    !empty(
                                        $liveExam['ends_at']
                                    )
                                        ? new DateTimeImmutable(
                                            $liveExam['ends_at']
                                        )
                                        : null;


                                $isLiveNow =
                                    $now >= $startsAt &&
                                    (
                                        $endsAt === null ||
                                        $now <= $endsAt
                                    );

                            } catch (Throwable $exception) {

                                $isLiveNow =
                                    false;
                            }
                        }

                        ?>


                        <div class="student-pro-live-row">

                            <span class="live-pro-icon">

                                <i
                                    class="
                                        fa-solid
                                        <?= $isLiveNow
                                            ? 'fa-tower-broadcast'
                                            : 'fa-calendar-days'
                                        ?>
                                    "
                                ></i>

                            </span>


                            <div>

                                <strong>

                                    <?= dashboard_escape(
                                        $liveExam['title']
                                    ) ?>

                                </strong>


                                <small>

                                    <?= dashboard_escape(
                                        $liveExam['subject_name']
                                        ?: 'General'
                                    ) ?>


                                    <?php if (
                                        $startsAt !== null
                                    ): ?>

                                        •
                                        <?= dashboard_escape(
                                            $startsAt->format(
                                                'd M, h:i A'
                                            )
                                        ) ?>

                                    <?php endif; ?>

                                </small>

                            </div>


                            <?php if (
                                $isLiveNow
                            ): ?>

                                <span
                                    class="live-now-pill"
                                >

                                    LIVE

                                </span>

                            <?php else: ?>

                                <a
                                    href="live_exams.php"
                                >
                                    View
                                </a>

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="student-pro-empty-mini">

                    <i
                        class="fa-regular fa-calendar-xmark"
                    ></i>


                    <strong>
                        No live exams scheduled.
                    </strong>


                    <span>
                        Upcoming eligible examinations will appear here.
                    </span>


                    <a href="live_exams.php">

                        Check live exams

                    </a>

                </div>

            <?php endif; ?>

        </article>


        <!-- =================================================
             SUBJECTS
        ================================================== -->

        <article
            class="
                student-pro-panel
                subject-pro-panel
            "
        >

            <div class="student-pro-panel-heading">

                <div>

                    <span>
                        SUBJECTS
                    </span>

                    <h2>
                        Your strongest areas
                    </h2>

                </div>


                <a
                    href="performance.php"
                >

                    Details

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>


            <?php if (
                !empty($subjectPerformance)
            ): ?>

                <div class="subject-pro-list">

                    <?php foreach (
                        $subjectPerformance
                        as $subject
                    ): ?>

                        <?php

                        $subjectScore =
                            max(
                                0,
                                min(
                                    100,
                                    (float) (
                                        $subject[
                                            'average_percentage'
                                        ] ?? 0
                                    )
                                )
                            );

                        ?>


                        <div
                            class="subject-pro-row"
                        >

                            <div
                                class="subject-pro-name"
                            >

                                <span>

                                    <i
                                        class="fa-solid fa-book"
                                    ></i>

                                </span>


                                <div>

                                    <strong>

                                        <?= dashboard_escape(
                                            $subject[
                                                'subject_name'
                                            ]
                                        ) ?>

                                    </strong>


                                    <small>

                                        <?= (int) (
                                            $subject[
                                                'attempts'
                                            ]
                                        ) ?>

                                        <?= (int) (
                                            $subject[
                                                'attempts'
                                            ]
                                        ) === 1
                                            ? 'exam'
                                            : 'exams'
                                        ?>

                                    </small>

                                </div>

                            </div>


                            <div
                                class="subject-pro-meter"
                            >

                                <div>

                                    <span
                                        style="
                                            width:
                                            <?= $subjectScore ?>%;
                                        "
                                    ></span>

                                </div>

                                <small>

                                    <?= dashboard_number(
                                        $subjectScore
                                    ) ?>%

                                </small>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="student-pro-empty-mini">

                    <i
                        class="fa-solid fa-chart-simple"
                    ></i>


                    <strong>
                        No subject data yet.
                    </strong>


                    <span>
                        Complete an exam to build your subject profile.
                    </span>

                </div>

            <?php endif; ?>

        </article>


        <!-- =================================================
             RECENT RESULTS
        ================================================== -->

        <article
            class="
                student-pro-panel
                recent-pro-panel
            "
        >

            <div class="student-pro-panel-heading">

                <div>

                    <span>
                        RESULTS
                    </span>

                    <h2>
                        Recent performance
                    </h2>

                </div>


                <a
                    href="results.php"
                >

                    View all

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>


            <?php if (
                !empty($recentResults)
            ): ?>

                <div class="recent-pro-list">

                    <?php foreach (
                        $recentResults
                        as $recent
                    ): ?>

                        <?php

                        $passed =
                            $recent['result_status']
                            === 'Pass';

                        ?>


                        <a
                            href="result.php?id=<?= (int) $recent['id'] ?>"
                            class="recent-pro-row"
                        >

                            <span
                                class="
                                    recent-result-icon
                                    <?= $passed
                                        ? 'pass'
                                        : 'fail'
                                    ?>
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        <?= $passed
                                            ? 'fa-check'
                                            : 'fa-xmark'
                                        ?>
                                    "
                                ></i>

                            </span>


                            <span
                                class="recent-result-name"
                            >

                                <strong>

                                    <?= dashboard_escape(
                                        $recent['exam_title']
                                    ) ?>

                                </strong>


                                <small>

                                    <?= dashboard_escape(
                                        $recent['subject_name']
                                        ?: $recent['exam_type']
                                    ) ?>


                                    •
                                    <?= dashboard_escape(
                                        dashboard_date(
                                            $recent['created_at']
                                        )
                                    ) ?>

                                </small>

                            </span>


                            <span
                                class="recent-result-score"
                            >

                                <strong>

                                    <?= dashboard_number(
                                        (float) $recent['percentage']
                                    ) ?>%

                                </strong>


                                <small>

                                    <?= dashboard_number(
                                        (float) $recent['obtained_marks']
                                    ) ?>

                                    /
                                    <?= dashboard_number(
                                        (float) $recent['total_marks']
                                    ) ?>

                                </small>

                            </span>


                            <i
                                class="fa-solid fa-chevron-right"
                            ></i>

                        </a>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="student-pro-empty-mini">

                    <i
                        class="fa-solid fa-chart-column"
                    ></i>


                    <strong>
                        No completed results yet.
                    </strong>


                    <span>
                        Your result history will appear here after your first exam.
                    </span>


                    <a
                        href="practice_exams.php"
                    >

                        Start a practice exam

                    </a>

                </div>

            <?php endif; ?>

        </article>


        <!-- =================================================
             RECOMMENDED
        ================================================== -->

        <article
            class="
                student-pro-panel
                recommended-pro-panel
            "
        >

            <div class="student-pro-panel-heading">

                <div>

                    <span>
                        READY TO PRACTISE?
                    </span>

                    <h2>
                        Recommended exams
                    </h2>

                </div>


                <a
                    href="practice_exams.php"
                >

                    Explore all

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>


            <?php if (
                !empty($recommendedExams)
            ): ?>

                <div class="recommended-pro-list">

                    <?php foreach (
                        $recommendedExams
                        as $recommended
                    ): ?>

                        <div
                            class="recommended-pro-row"
                        >

                            <span>

                                <i
                                    class="fa-solid fa-pen-to-square"
                                ></i>

                            </span>


                            <div>

                                <strong>

                                    <?= dashboard_escape(
                                        $recommended['title']
                                    ) ?>

                                </strong>


                                <small>

                                    <?= dashboard_escape(
                                        $recommended['subject_name']
                                    ) ?>

                                    •

                                    <?= (int) (
                                        $recommended[
                                            'active_question_count'
                                        ]
                                    ) ?>

                                    questions

                                    •

                                    <?= (int) (
                                        $recommended[
                                            'duration_minutes'
                                        ]
                                    ) ?>

                                    min

                                </small>

                            </div>


                            <a
                                href="start_exam.php?id=<?= (int) $recommended['id'] ?>"
                            >

                                Start

                            </a>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="student-pro-empty-mini">

                    <i
                        class="fa-solid fa-book-open"
                    ></i>


                    <strong>
                        No practice exams are ready.
                    </strong>


                    <span>
                        Complete exams will appear here automatically.
                    </span>

                </div>

            <?php endif; ?>

        </article>


    </section>


    <!-- =====================================================
         QUICK LINKS
    ====================================================== -->

    <section class="student-pro-quick-actions">

        <a
            href="practice_exams.php"
            class="quick-action-primary"
        >

            <span>

                <i
                    class="fa-solid fa-pen-to-square"
                ></i>

            </span>


            <strong>

                Practice Exams

                <small>
                    Build your confidence
                </small>

            </strong>


            <i
                class="fa-solid fa-arrow-right"
            ></i>

        </a>


        <a
            href="materials.php"
            class="quick-action-secondary"
        >

            <span>

                <i
                    class="fa-solid fa-book-open"
                ></i>

            </span>


            <strong>

                Study Materials

                <small>
                    Learn beyond the exam
                </small>

            </strong>


            <i
                class="fa-solid fa-arrow-right"
            ></i>

        </a>


        <a
            href="performance.php"
            class="quick-action-secondary"
        >

            <span>

                <i
                    class="fa-solid fa-chart-line"
                ></i>

            </span>


            <strong>

                Performance

                <small>
                    Understand your progress
                </small>

            </strong>


            <i
                class="fa-solid fa-arrow-right"
            ></i>

        </a>


        <a
            href="leaderboard.php"
            class="quick-action-secondary"
        >

            <span>

                <i
                    class="fa-solid fa-ranking-star"
                ></i>

            </span>


            <strong>

                Leaderboard

                <small>
                    Compare your best score
                </small>

            </strong>


            <i
                class="fa-solid fa-arrow-right"
            ></i>

        </a>

    </section>


    <!-- =====================================================
         FOOT NOTE
    ====================================================== -->

    <div class="student-pro-footer-note">

        <i
            class="fa-solid fa-graduation-cap"
        ></i>


        <span>

            Keep learning, keep growing.

        </span>


        <strong>
            ExamSphere
        </strong>

    </div>


</main>


<?php include 'includes/footer.php'; ?>


</body>

</html>