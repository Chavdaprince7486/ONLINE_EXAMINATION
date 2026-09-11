<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/razorpay.php';


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {
    header('Location: ../auth/login.php');
    exit;
}

$studentId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function live_exams_escape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function live_exams_number(float|int|string|null $value): string
{
    $number = (float) $value;

    if (floor($number) === $number) {
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

function live_exams_parse_date(?string $value): ?DateTimeImmutable
{
    if (
        trim((string) $value) === ''
    ) {
        return null;
    }

    try {
        return new DateTimeImmutable(
            (string) $value
        );
    } catch (Throwable) {
        return null;
    }
}

function live_exams_state(
    string $status,
    ?DateTimeImmutable $startsAt,
    ?DateTimeImmutable $endsAt,
    DateTimeImmutable $now
): string {

    if (
        $status === 'Completed' ||
        (
            $endsAt !== null &&
            $now > $endsAt
        )
    ) {
        return 'completed';
    }

    if (
        $startsAt !== null &&
        $now < $startsAt
    ) {
        return 'upcoming';
    }

    if (
        $startsAt !== null &&
        $now >= $startsAt &&
        (
            $endsAt === null ||
            $now <= $endsAt
        )
    ) {
        return 'live';
    }

    if (
        $startsAt === null &&
        in_array(
            $status,
            [
                'Live',
                'Running',
                'Active'
            ],
            true
        )
    ) {
        return 'live';
    }

    return 'upcoming';
}

function live_exams_state_label(
    string $state
): string {

    return match ($state) {
        'live' => 'Live now',
        'completed' => 'Ended',
        default => 'Upcoming'
    };
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim(
    (string) (
        $_GET['search'] ?? ''
    )
);

$view = trim(
    (string) (
        $_GET['view'] ?? 'all'
    )
);

$allowedViews = [
    'all',
    'upcoming',
    'live',
    'completed'
];

if (
    !in_array(
        $view,
        $allowedViews,
        true
    )
) {
    $view = 'all';
}


/*
|--------------------------------------------------------------------------
| Flash
|--------------------------------------------------------------------------
*/

$paymentMessage = '';
$paymentType = '';

if (
    !empty(
        $_SESSION['live_exam_flash']
    )
) {

    $paymentMessage =
        (string) $_SESSION[
            'live_exam_flash'
        ];

    $paymentType =
        'success';

    unset(
        $_SESSION['live_exam_flash']
    );
}


/*
|--------------------------------------------------------------------------
| PAYMENT FLASH
|--------------------------------------------------------------------------
| Real Razorpay checkout is launched client-side after a server-created
| order is returned by the protected AJAX endpoint.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = trim((string)($_POST['csrf_token'] ?? ''));

    if (!function_exists('verify_csrf_token') || !verify_csrf_token($postedCsrf)) {
        $paymentMessage = 'Your session security token is invalid. Please refresh and try again.';
        $paymentType = 'error';
    }
}


/*
|--------------------------------------------------------------------------
| Load Live Exams
|--------------------------------------------------------------------------
|
| Only exams having the exact required number of ACTIVE questions
| are shown.
|--------------------------------------------------------------------------
*/

$rawLiveExams = [];

try {

    $sql = "
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

            e.created_at,
            e.updated_at,

            s.name AS subject_name,
            s.code AS subject_code,

            (
                SELECT COUNT(DISTINCT eq1.question_id)

                FROM exam_questions eq1

                INNER JOIN questions q1
                    ON q1.id = eq1.question_id

                WHERE
                    eq1.exam_id = e.id
                    AND q1.status = 'Active'
            ) AS active_question_count

        FROM exams e

        INNER JOIN subjects s
            ON s.id = e.subject_id
            AND s.status = 'Active'

        WHERE

            e.exam_type = 'Live'

            AND e.status IN (
                'Scheduled',
                'Live',
                'Completed',
                'Active',
                'Upcoming',
                'Running'
            )

            AND e.required_question_count > 0
    ";

    $parameters = [];


    if (
        $search !== ''
    ) {

        $sql .= "
            AND (
                e.title LIKE ?
                OR e.description LIKE ?
                OR s.name LIKE ?
                OR s.code LIKE ?
            )
        ";

        $searchValue =
            '%' .
            $search .
            '%';

        $parameters[] =
            $searchValue;

        $parameters[] =
            $searchValue;

        $parameters[] =
            $searchValue;

        $parameters[] =
            $searchValue;
    }


    $sql .= "
        ORDER BY

            CASE

                WHEN
                    e.starts_at IS NOT NULL
                    AND e.starts_at <= NOW()
                    AND (
                        e.ends_at IS NULL
                        OR e.ends_at >= NOW()
                    )

                THEN 0

                WHEN
                    e.starts_at IS NOT NULL
                    AND e.starts_at > NOW()

                THEN 1

                ELSE 2

            END,

            COALESCE(
                e.starts_at,
                e.updated_at,
                e.created_at
            ) ASC,

            e.id ASC
    ";


    $liveStatement =
        $conn->prepare(
            $sql
        );

    $liveStatement->execute(
        $parameters
    );

    $rawLiveExams =
        $liveStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Live exams listing failed: ' .
        $exception->getMessage()
    );

    $rawLiveExams = [];
}


/*
|--------------------------------------------------------------------------
| Readiness Filter
|--------------------------------------------------------------------------
*/

$readyLiveExams = [];

foreach (
    $rawLiveExams as $exam
) {

    $requiredCount =
        (int) $exam[
            'required_question_count'
        ];

    $activeCount =
        (int) $exam[
            'active_question_count'
        ];

    if (
        $requiredCount <= 0 ||
        $activeCount !== $requiredCount
    ) {
        continue;
    }

    $readyLiveExams[] =
        $exam;
}


/*
|--------------------------------------------------------------------------
| Current Date
|--------------------------------------------------------------------------
*/

$now =
    new DateTimeImmutable();


/*
|--------------------------------------------------------------------------
| Load Latest Results In One Query
|--------------------------------------------------------------------------
*/

$latestResults = [];


if (
    !empty($readyLiveExams)
) {

    try {

        $examIds = array_map(
            static function (
                array $exam
            ): int {
                return (int) $exam['id'];
            },
            $readyLiveExams
        );


        $examIds =
            array_values(
                array_unique(
                    $examIds
                )
            );


        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($examIds),
                    '?'
                )
            );


        $resultStatement =
            $conn->prepare("
                SELECT

                    r.id,
                    r.exam_id,
                    r.percentage,
                    r.grade,
                    r.result_status,
                    r.created_at

                FROM results r

                INNER JOIN (
                    SELECT

                        exam_id,
                        MAX(id) AS latest_result_id

                    FROM results

                    WHERE
                        student_id = ?

                        AND exam_id IN (
                            {$placeholders}
                        )

                    GROUP BY exam_id

                ) latest

                    ON latest.latest_result_id = r.id

                WHERE
                    r.student_id = ?
            ");


        $resultParameters =
            array_merge(
                [
                    $studentId
                ],
                $examIds,
                [
                    $studentId
                ]
            );


        $resultStatement->execute(
            $resultParameters
        );


        foreach (
            $resultStatement->fetchAll(PDO::FETCH_ASSOC)
            as $result
        ) {

            $latestResults[
                (int) $result['exam_id']
            ] = $result;
        }

    } catch (Throwable $exception) {

        error_log(
            'Live exam results lookup failed: ' .
            $exception->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| Load Active Attempts In One Query
|--------------------------------------------------------------------------
*/

$activeAttempts = [];


if (
    !empty($readyLiveExams)
) {

    try {

        $examIds = array_map(
            static function (
                array $exam
            ): int {
                return (int) $exam['id'];
            },
            $readyLiveExams
        );


        $examIds =
            array_values(
                array_unique(
                    $examIds
                )
            );


        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($examIds),
                    '?'
                )
            );


        $attemptStatement =
            $conn->prepare("
                SELECT

                    ea.id,
                    ea.exam_id,
                    ea.started_at,
                    ea.server_deadline,
                    ea.status

                FROM exam_attempts ea

                INNER JOIN (
                    SELECT

                        exam_id,
                        MAX(id) AS latest_attempt_id

                    FROM exam_attempts

                    WHERE
                        student_id = ?

                        AND status = 'Started'

                        AND exam_id IN (
                            {$placeholders}
                        )

                    GROUP BY exam_id

                ) latest

                    ON latest.latest_attempt_id = ea.id

                WHERE
                    ea.student_id = ?
                    AND ea.status = 'Started'
            ");


        $attemptParameters =
            array_merge(
                [
                    $studentId
                ],
                $examIds,
                [
                    $studentId
                ]
            );


        $attemptStatement->execute(
            $attemptParameters
        );


        foreach (
            $attemptStatement->fetchAll(PDO::FETCH_ASSOC)
            as $attempt
        ) {

            $activeAttempts[
                (int) $attempt['exam_id']
            ] = $attempt;
        }

    } catch (Throwable $exception) {

        error_log(
            'Live exam attempt lookup failed: ' .
            $exception->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| Subscription State
|--------------------------------------------------------------------------
*/

$hasSubscription = false;


try {

    $subscriptionStatement =
        $conn->prepare("
            SELECT id

            FROM subscriptions

            WHERE
                student_id = ?

                AND status = 'Active'

                AND start_date <= CURDATE()

                AND end_date >= CURDATE()

            ORDER BY
                end_date DESC

            LIMIT 1
        ");

    $subscriptionStatement->execute([
        $studentId
    ]);

    $hasSubscription =
        (bool) $subscriptionStatement->fetchColumn();

} catch (Throwable $exception) {

    error_log(
        'Live exam subscription status failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| Build Student-Facing Exam List
|--------------------------------------------------------------------------
*/

$liveExams = [];

$upcomingCount = 0;
$liveCount = 0;
$completedCount = 0;


foreach (
    $readyLiveExams as $exam
) {

    $examId =
        (int) $exam['id'];


    $startsAt =
        live_exams_parse_date(
            $exam['starts_at']
        );


    $endsAt =
        live_exams_parse_date(
            $exam['ends_at']
        );


    $state =
        live_exams_state(
            (string) $exam['status'],
            $startsAt,
            $endsAt,
            $now
        );


    $stateLabel =
        live_exams_state_label(
            $state
        );


    if (
        $state === 'upcoming'
    ) {
        $upcomingCount++;
    } elseif (
        $state === 'live'
    ) {
        $liveCount++;
    } else {
        $completedCount++;
    }


    /*
     * Access verification.
     */
    $accessMessage = '';


    try {

        $accessMessage =
            live_exam_access_message(
                $conn,
                $studentId,
                $exam
            );

    } catch (Throwable $exception) {

        error_log(
            'Live exam access lookup failed: ' .
            $exception->getMessage()
        );

        $accessMessage =
            'Unable to verify live exam access right now.';
    }


    $accessMessageLower =
        strtolower(
            $accessMessage
        );


    $requiresPayment =
        (
            (float) $exam['exam_fee'] > 0
            &&
            str_contains(
                $accessMessageLower,
                'payment'
            )
        );


    $requiresSubscription =
        (
            (int) $exam[
                'subscription_required'
            ] === 1

            &&

            str_contains(
                $accessMessageLower,
                'subscription'
            )
        );


    /*
     * Active attempt.
     */
    $activeAttempt =
        $activeAttempts[
            $examId
        ] ?? null;


    /*
     * Latest result.
     */
    $latestResult =
        $latestResults[
            $examId
        ] ?? null;


    /*
     * Authoritative action.
     */
    $actionType =
        'details';

    $actionText =
        'View details';

    $actionUrl =
        'exam-details.php?exam_id=' .
        $examId;


    /*
     * Payment has priority.
     */
    if (
        $requiresPayment
    ) {

        $actionType =
            'payment';

        $actionText =
            'Pay to unlock';

        $actionUrl =
            '#payment-' .
            $examId;

    } elseif (
        $requiresSubscription
    ) {

        $actionType =
            'subscription';

        $actionText =
            'Activate subscription';

        $actionUrl =
            'subscriptions.php';

    } elseif (
        $state === 'live'
    ) {

        /*
         * start_exam.php is the authoritative gate.
         * Only expose the entry button when the database
         * status is one accepted by start_exam.php.
         */
        if (
            in_array(
                (string) $exam['status'],
                [
                    'Active',
                    'Live'
                ],
                true
            )
        ) {

            $actionType =
                $activeAttempt
                    ? 'resume'
                    : 'start';

            $actionText =
                $activeAttempt
                    ? 'Resume live exam'
                    : 'Enter live exam';

            $actionUrl =
                'start_exam.php?id=' .
                $examId;

        } else {

            $actionType =
                'waiting';

            $actionText =
                'Waiting for activation';

            $actionUrl =
                'exam-details.php?exam_id=' .
                $examId;
        }

    } elseif (
        $state === 'completed' &&
        $latestResult
    ) {

        $actionType =
            'result';

        $actionText =
            'View result';

        $actionUrl =
            'result.php?id=' .
            (int) $latestResult['id'];

    } elseif (
        $state === 'completed'
    ) {

        $actionType =
            'ended';

        $actionText =
            'Exam ended';

        $actionUrl =
            'exam-details.php?exam_id=' .
            $examId;

    } else {

        $actionType =
            'upcoming';

        $actionText =
            'Available at scheduled time';

        $actionUrl =
            'exam-details.php?exam_id=' .
            $examId;
    }


    /*
     * Current view filter.
     */
    $include =
        match ($view) {
            'upcoming' => $state === 'upcoming',
            'live' => $state === 'live',
            'completed' => $state === 'completed',
            default => true
        };


    if (
        !$include
    ) {
        continue;
    }


    $exam['state'] =
        $state;

    $exam['state_label'] =
        $stateLabel;

    $exam['state_class'] =
        $state;

    $exam['starts_object'] =
        $startsAt;

    $exam['ends_object'] =
        $endsAt;

    $exam['access_message'] =
        $accessMessage;

    $exam['requires_payment'] =
        $requiresPayment;

    $exam['requires_subscription'] =
        $requiresSubscription;

    $exam['active_attempt'] =
        $activeAttempt;

    $exam['latest_result'] =
        $latestResult;

    $exam['action_type'] =
        $actionType;

    $exam['action_text'] =
        $actionText;

    $exam['action_url'] =
        $actionUrl;


    $liveExams[] =
        $exam;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$csrfToken =
    function_exists('csrf_token')
        ? csrf_token()
        : '';


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
        Live Exams | ExamSphere
    </title>


    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
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
        href="assets/css/live-exams-pro.css"
    >

</head>


<body class="live-exams-body">


<?php include 'includes/navbar.php'; ?>


<main class="live-exams-pro">

    <div class="container">


        <!-- =================================================
             HERO
        ================================================== -->

        <section class="live-exams-hero">

            <div class="live-exams-hero-content">

                <span class="live-exams-kicker">

                    <i
                        class="fa-solid fa-tower-broadcast"
                    ></i>

                    LIVE EXAMINATION CENTER

                </span>


                <h1>

                    Compete live.
                    <em>Perform your best.</em>

                </h1>


                <p>

                    Join scheduled ExamSphere examinations,
                    prepare before the clock starts and experience
                    a structured real-time examination environment.

                </p>


                <div class="live-hero-actions">

                    <a
                        href="#live-exam-list"
                        class="live-hero-primary"
                    >

                        Explore live exams

                        <i
                            class="fa-solid fa-arrow-down"
                        ></i>

                    </a>


                    <?php if (
                        !$hasSubscription
                    ): ?>

                        <a
                            href="subscriptions.php"
                            class="live-hero-secondary"
                        >

                            <i
                                class="fa-solid fa-crown"
                            ></i>

                            Explore membership

                        </a>

                    <?php endif; ?>

                </div>

            </div>


            <div class="live-hero-visual">

                <div
                    class="live-hero-orbit orbit-one"
                ></div>

                <div
                    class="live-hero-orbit orbit-two"
                ></div>


                <div class="live-hero-core">

                    <span>

                        <i
                            class="fa-solid fa-tower-broadcast"
                        ></i>

                    </span>

                    <strong>
                        LIVE
                    </strong>

                    <small>
                        EXAMSPHERE
                    </small>

                </div>

            </div>

        </section>


        <!-- =================================================
             FLASH
        ================================================== -->

        <?php if (
            $paymentMessage !== ''
        ): ?>

            <div
                class="
                    live-exam-alert
                    <?= live_exams_escape(
                        $paymentType
                    ) ?>
                "
            >

                <i
                    class="
                        fa-solid
                        <?= $paymentType === 'success'
                            ? 'fa-circle-check'
                            : (
                                $paymentType === 'error'
                                    ? 'fa-circle-exclamation'
                                    : 'fa-circle-info'
                            )
                        ?>
                    "
                ></i>


                <span>

                    <?= live_exams_escape(
                        $paymentMessage
                    ) ?>

                </span>

            </div>

        <?php endif; ?>


        <!-- =================================================
             STATS
        ================================================== -->

        <section class="live-exam-stats">


            <div class="live-exam-stat">

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


            <div class="live-exam-stat live-stat">

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


            <div class="live-exam-stat">

                <span>

                    <i
                        class="fa-solid fa-chart-column"
                    ></i>

                </span>

                <div>

                    <small>
                        Ended
                    </small>

                    <strong>
                        <?= $completedCount ?>
                    </strong>

                </div>

            </div>


            <div class="live-exam-stat">

                <span>

                    <i
                        class="fa-solid fa-shield-halved"
                    ></i>

                </span>

                <div>

                    <small>
                        Your membership
                    </small>

                    <strong>
                        <?= $hasSubscription
                            ? 'Active'
                            : 'Not active'
                        ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- =================================================
             FILTER
        ================================================== -->

        <section class="live-exam-filter-panel">

            <form
                method="GET"
                class="live-exam-filter-form"
            >

                <div class="live-exam-search">

                    <i
                        class="fa-solid fa-magnifying-glass"
                    ></i>

                    <input
                        type="search"
                        name="search"
                        value="<?= live_exams_escape(
                            $search
                        ) ?>"
                        placeholder="Search live exam or subject..."
                    >

                </div>


                <div class="live-exam-view-tabs">


                    <?php

                    $querySearch =
                        $search !== ''
                            ? '&search=' .
                                urlencode($search)
                            : '';

                    ?>


                    <a
                        href="live_exams.php?view=all<?= $querySearch ?>"
                        class="<?= $view === 'all'
                            ? 'active'
                            : ''
                        ?>"
                    >
                        All
                    </a>


                    <a
                        href="live_exams.php?view=upcoming<?= $querySearch ?>"
                        class="<?= $view === 'upcoming'
                            ? 'active'
                            : ''
                        ?>"
                    >
                        Upcoming
                    </a>


                    <a
                        href="live_exams.php?view=live<?= $querySearch ?>"
                        class="<?= $view === 'live'
                            ? 'active'
                            : ''
                        ?>"
                    >
                        Live
                    </a>


                    <a
                        href="live_exams.php?view=completed<?= $querySearch ?>"
                        class="<?= $view === 'completed'
                            ? 'active'
                            : ''
                        ?>"
                    >
                        Ended
                    </a>

                </div>


                <button
                    type="submit"
                    class="live-exam-filter-button"
                >

                    <i
                        class="fa-solid fa-magnifying-glass"
                    ></i>

                    Search

                </button>

            </form>

        </section>


        <!-- =================================================
             LIST HEADER
        ================================================== -->

        <section
            id="live-exam-list"
            class="live-exam-list-header"
        >

            <div>

                <span>
                    SCHEDULED EXAMINATIONS
                </span>

                <h2>
                    Live exam center
                </h2>

            </div>


            <span class="live-exam-list-count">

                <?= count($liveExams) ?>

                <?= count($liveExams) === 1
                    ? 'exam'
                    : 'exams'
                ?>

            </span>

        </section>


        <!-- =================================================
             EXAM LIST
        ================================================== -->

        <?php if (
            !empty($liveExams)
        ): ?>

            <section class="live-exam-grid">


                <?php foreach (
                    $liveExams as $index => $exam
                ): ?>


                    <?php

                    $examId =
                        (int) $exam['id'];

                    $state =
                        (string) $exam['state'];

                    $stateLabel =
                        (string) $exam['state_label'];

                    $number =
                        str_pad(
                            (string) (
                                $index + 1
                            ),
                            2,
                            '0',
                            STR_PAD_LEFT
                        );

                    $startsAt =
                        $exam[
                            'starts_object'
                        ];

                    $endsAt =
                        $exam[
                            'ends_object'
                        ];

                    $paymentRequired =
                        (bool) $exam[
                            'requires_payment'
                        ];

                    $subscriptionRequired =
                        (bool) $exam[
                            'requires_subscription'
                        ];

                    $latestResult =
                        $exam[
                            'latest_result'
                        ] ?? null;

                    $accessMessage =
                        (string) $exam[
                            'access_message'
                        ];

                    ?>


                    <article
                        class="
                            live-exam-card
                            state-<?= live_exams_escape(
                                $state
                            ) ?>
                        "
                    >


                        <!-- TOP -->

                        <div class="live-exam-card-top">

                            <span
                                class="live-exam-card-number"
                            >

                                <?= live_exams_escape(
                                    $number
                                ) ?>

                            </span>


                            <div class="live-exam-card-badges">

                                <span
                                    class="
                                        live-state-pill
                                        <?= live_exams_escape(
                                            $state
                                        ) ?>
                                    "
                                >

                                    <?php if (
                                        $state === 'live'
                                    ): ?>

                                        <i
                                            class="
                                                fa-solid
                                                fa-circle
                                            "
                                        ></i>

                                    <?php elseif (
                                        $state === 'upcoming'
                                    ): ?>

                                        <i
                                            class="
                                                fa-regular
                                                fa-clock
                                            "
                                        ></i>

                                    <?php else: ?>

                                        <i
                                            class="
                                                fa-solid
                                                fa-check
                                            "
                                        ></i>

                                    <?php endif; ?>


                                    <?= live_exams_escape(
                                        $stateLabel
                                    ) ?>

                                </span>


                                <?php if (
                                    $paymentRequired
                                ): ?>

                                    <span
                                        class="
                                            live-access-pill
                                            payment
                                        "
                                    >

                                        <i
                                            class="
                                                fa-solid
                                                fa-indian-rupee-sign
                                            "
                                        ></i>

                                        Payment

                                    </span>


                                <?php elseif (
                                    $subscriptionRequired
                                ): ?>

                                    <span
                                        class="
                                            live-access-pill
                                            subscription
                                        "
                                    >

                                        <i
                                            class="
                                                fa-solid
                                                fa-crown
                                            "
                                        ></i>

                                        Membership

                                    </span>


                                <?php else: ?>

                                    <span
                                        class="
                                            live-access-pill
                                            free
                                        "
                                    >

                                        Eligible

                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- ICON -->

                        <div class="live-exam-card-icon">

                            <i
                                class="
                                    fa-solid
                                    <?= $state === 'live'
                                        ? 'fa-tower-broadcast'
                                        : (
                                            $state === 'completed'
                                                ? 'fa-chart-column'
                                                : 'fa-calendar-days'
                                        )
                                    ?>
                                "
                            ></i>

                        </div>


                        <!-- CONTENT -->

                        <div class="live-exam-card-content">

                            <span
                                class="live-exam-subject"
                            >

                                <?= live_exams_escape(
                                    $exam['subject_name']
                                    ?: 'General'
                                ) ?>


                                <?php if (
                                    !empty(
                                        $exam['subject_code']
                                    )
                                ): ?>

                                    <small>

                                        •

                                        <?= live_exams_escape(
                                            $exam['subject_code']
                                        ) ?>

                                    </small>

                                <?php endif; ?>

                            </span>


                            <h3>

                                <?= live_exams_escape(
                                    $exam['title']
                                ) ?>

                            </h3>


                            <p>

                                <?= live_exams_escape(
                                    $exam['description']
                                    ?: 'A scheduled ExamSphere live examination.'
                                ) ?>

                            </p>

                        </div>


                        <!-- META -->

                        <div class="live-exam-meta">

                            <span>

                                <i
                                    class="fa-regular fa-clock"
                                ></i>

                                <?= (int) (
                                    $exam[
                                        'duration_minutes'
                                    ]
                                ) ?>

                                min

                            </span>


                            <span>

                                <i
                                    class="fa-solid fa-list-check"
                                ></i>

                                <?= (int) (
                                    $exam[
                                        'active_question_count'
                                    ]
                                ) ?>

                                questions

                            </span>


                            <span>

                                <i
                                    class="fa-solid fa-star"
                                ></i>

                                <?= live_exams_number(
                                    $exam[
                                        'total_marks'
                                    ]
                                ) ?>

                                marks

                            </span>

                        </div>


                        <!-- SCHEDULE -->

                        <div class="live-exam-schedule">

                            <div>

                                <small>
                                    Starts
                                </small>


                                <strong>

                                    <?= $startsAt
                                        ? live_exams_escape(
                                            $startsAt->format(
                                                'd M Y, h:i A'
                                            )
                                        )
                                        : 'Schedule pending'
                                    ?>

                                </strong>

                            </div>


                            <?php if (
                                $endsAt
                            ): ?>

                                <div>

                                    <small>
                                        Ends
                                    </small>


                                    <strong>

                                        <?= live_exams_escape(
                                            $endsAt->format(
                                                'd M Y, h:i A'
                                            )
                                        ) ?>

                                    </strong>

                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- ACCESS MESSAGE -->

                        <?php if (
                            $accessMessage !== '' &&
                            (
                                $paymentRequired ||
                                $subscriptionRequired
                            )
                        ): ?>

                            <div
                                class="
                                    live-exam-access-message
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-circle-info
                                    "
                                ></i>


                                <span>

                                    <?= live_exams_escape(
                                        $accessMessage
                                    ) ?>

                                </span>

                            </div>

                        <?php endif; ?>


                        <!-- ACTION -->

                        <div class="live-exam-card-action">


                            <?php if (
                                $paymentRequired
                            ): ?>

                                <div
                                    id="payment-<?= $examId ?>"
                                    class="live-payment-box"
                                >

                                    <div>

                                        <span>

                                            <i
                                                class="
                                                    fa-solid
                                                    fa-lock
                                                "
                                            ></i>

                                            Unlock live access

                                        </span>


                                        <strong>

                                            ₹<?= live_exams_number(
                                                $exam['exam_fee']
                                            ) ?>

                                        </strong>

                                    </div>


                                    <button
                                        type="button"
                                        class="live-exam-action-btn primary live-payment-button"
                                        data-exam-id="<?= $examId ?>"
                                        data-amount="<?= (int) round(((float) $exam['exam_fee']) * 100) ?>"
                                        data-csrf="<?= live_exams_escape($csrfToken) ?>"
                                        data-title="<?= live_exams_escape((string) $exam['title']) ?>"
                                    >

                                        <span>
                                            <i class="fa-solid fa-credit-card"></i>
                                            Continue to Razorpay
                                        </span>

                                        <i class="fa-solid fa-arrow-right"></i>

                                    </button>

                                </div>


                                <a
                                    href="exam-details.php?exam_id=<?= $examId ?>"
                                    class="live-secondary-link"
                                >

                                    View exam details

                                    <i
                                        class="
                                            fa-solid
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>


                            <?php elseif (
                                $subscriptionRequired
                            ): ?>

                                <a
                                    href="subscriptions.php"
                                    class="
                                        live-exam-action-btn
                                        primary
                                    "
                                >

                                    <span>

                                        <i
                                            class="
                                                fa-solid
                                                fa-crown
                                            "
                                        ></i>

                                        Activate subscription

                                    </span>


                                    <i
                                        class="
                                            fa-solid
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>


                                <a
                                    href="exam-details.php?exam_id=<?= $examId ?>"
                                    class="live-secondary-link"
                                >

                                    View exam details

                                    <i
                                        class="
                                            fa-solid
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>


                            <?php elseif (
                                $exam['action_type'] === 'start'
                            ): ?>

                                <a
                                    href="<?= live_exams_escape(
                                        $exam['action_url']
                                    ) ?>"
                                    class="
                                        live-exam-action-btn
                                        primary
                                    "
                                >

                                    <span>

                                        <i
                                            class="
                                                fa-solid
                                                fa-tower-broadcast
                                            "
                                        ></i>

                                        Enter live exam

                                    </span>


                                    <i
                                        class="
                                            fa-solid
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>


                                <a
                                    href="exam-details.php?exam_id=<?= $examId ?>"
                                    class="live-secondary-link"
                                >

                                    View exam details

                                    <i
                                        class="
                                            fa-solid
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>


                            <?php elseif (
                                $exam['action_type'] === 'resume'
                            ): ?>

                                <a
                                    href="<?= live_exams_escape(
                                        $exam['action_url']
                                    ) ?>"
                                    class="
                                        live-exam-action-btn
                                        primary
                                    "
                                >

                                    <span>

                                        <i
                                            class="
                                                fa-solid
                                                fa-play
                                            "
                                        ></i>

                                        Resume live exam

                                    </span>


                                    <i
                                        class="
                                            fa-solid
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>


                                <a
                                    href="exam-details.php?exam_id=<?= $examId ?>"
                                    class="live-secondary-link"
                                >

                                    View exam details

                                    <i
                                        class="
                                            fa-solid
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>


                            <?php elseif (
                                $exam['action_type'] === 'result'
                            ): ?>

                                <a
                                    href="<?= live_exams_escape(
                                        $exam['action_url']
                                    ) ?>"
                                    class="
                                        live-exam-action-btn
                                        secondary
                                    "
                                >

                                    <span>

                                        <i
                                            class="
                                                fa-solid
                                                fa-chart-column
                                            "
                                        ></i>

                                        View result

                                    </span>


                                    <i
                                        class="
                                            fa-solid
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>


                                <a
                                    href="exam-details.php?exam_id=<?= $examId ?>"
                                    class="live-secondary-link"
                                >

                                    View exam details

                                    <i
                                        class="
                                            fa-solid
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>


                            <?php elseif (
                                $exam['action_type'] === 'waiting'
                            ): ?>

                                <button
                                    type="button"
                                    class="
                                        live-exam-action-btn
                                        disabled
                                    "
                                    disabled
                                >

                                    <span>

                                        <i
                                            class="
                                                fa-solid
                                                fa-hourglass-half
                                            "
                                        ></i>

                                        Waiting for activation

                                    </span>

                                </button>


                                <a
                                    href="exam-details.php?exam_id=<?= $examId ?>"
                                    class="live-secondary-link"
                                >

                                    View exam details

                                    <i
                                        class="
                                            fa-solid
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>


                            <?php elseif (
                                $state === 'completed'
                            ): ?>

                                <button
                                    type="button"
                                    class="
                                        live-exam-action-btn
                                        disabled
                                    "
                                    disabled
                                >

                                    <span>

                                        <i
                                            class="
                                                fa-regular
                                                fa-clock
                                            "
                                        ></i>

                                        Exam ended

                                    </span>

                                </button>


                                <a
                                    href="exam-details.php?exam_id=<?= $examId ?>"
                                    class="live-secondary-link"
                                >

                                    View exam details

                                    <i
                                        class="
                                            fa-solid
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>


                            <?php else: ?>

                                <button
                                    type="button"
                                    class="
                                        live-exam-action-btn
                                        disabled
                                    "
                                    disabled
                                >

                                    <span>

                                        <i
                                            class="
                                                fa-regular
                                                fa-clock
                                            "
                                        ></i>

                                        Available at scheduled time

                                    </span>

                                </button>


                                <a
                                    href="exam-details.php?exam_id=<?= $examId ?>"
                                    class="live-secondary-link"
                                >

                                    View exam details

                                    <i
                                        class="
                                            fa-solid
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </section>


        <?php else: ?>


            <!-- =================================================
                 EMPTY
            ================================================== -->

            <section class="live-exam-empty">

                <div class="live-exam-empty-icon">

                    <i
                        class="
                            fa-solid
                            fa-calendar-xmark
                        "
                    ></i>

                </div>


                <span>
                    NO LIVE EXAMS FOUND
                </span>


                <h2>

                    There are no matching live
                    examinations right now.

                </h2>


                <p>

                    <?php if (
                        $search !== '' ||
                        $view !== 'all'
                    ): ?>

                        Try a different search or switch back
                        to all live examinations.

                    <?php else: ?>

                        Once an administrator publishes a complete
                        live examination, it will appear here automatically.

                    <?php endif; ?>

                </p>


                <div class="live-empty-actions">


                    <?php if (
                        $search !== '' ||
                        $view !== 'all'
                    ): ?>

                        <a
                            href="live_exams.php"
                            class="
                                live-empty-btn
                                secondary
                            "
                        >

                            Show all

                        </a>

                    <?php endif; ?>


                    <a
                        href="practice_exams.php"
                        class="
                            live-empty-btn
                            primary
                        "
                    >

                        Practice meanwhile

                        <i
                            class="
                                fa-solid
                                fa-arrow-right
                            "
                        ></i>

                    </a>

                </div>

            </section>


        <?php endif; ?>


        <!-- =================================================
             INFORMATION
        ================================================== -->

        <section class="live-exam-info-strip">

            <div>

                <i
                    class="fa-solid fa-shield-halved"
                ></i>


                <span>

                    <strong>
                        Secure live access
                    </strong>

                    <small>

                        ExamSphere validates your account and
                        access before the examination begins.

                    </small>

                </span>

            </div>


            <div>

                <i
                    class="fa-solid fa-clock"
                ></i>


                <span>

                    <strong>
                        Respect the schedule
                    </strong>

                    <small>

                        Join only while the examination's configured
                        live window is active.

                    </small>

                </span>

            </div>


            <div>

                <i
                    class="fa-solid fa-chart-line"
                ></i>


                <span>

                    <strong>
                        Results are tracked
                    </strong>

                    <small>

                        Completed live attempts become part of
                        your result and performance history.

                    </small>

                </span>

            </div>

        </section>

    </div>

</main>


<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const paymentButtons = document.querySelectorAll('.live-payment-button');

    paymentButtons.forEach(function (button) {
        button.addEventListener('click', async function () {
            if (button.dataset.processing === '1') return;

            const examId = button.dataset.examId || '';
            const csrf = button.dataset.csrf || '';

            button.dataset.processing = '1';
            button.disabled = true;
            button.innerHTML = '<span><i class="fa-solid fa-spinner fa-spin"></i> Preparing payment...</span>';

            try {
                const response = await fetch('ajax/create_live_exam_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        exam_id: examId,
                        csrf_token: csrf
                    })
                });

                const data = await response.json();
                if (!response.ok || !data.status) {
                    throw new Error(data.message || 'Unable to start payment.');
                }

                if (typeof Razorpay === 'undefined') {
                    throw new Error('Razorpay Checkout could not be loaded. Check your internet connection and try again.');
                }

                const rzp = new Razorpay({
                    key: data.key_id,
                    amount: data.amount,
                    currency: data.currency,
                    name: 'ExamSphere',
                    description: data.exam_title,
                    order_id: data.order_id,
                    prefill: data.prefill || {},
                    notes: data.notes || {},
                    theme: {
                        color: '#5D4037'
                    },
                    modal: {
                        ondismiss: function () {
                            button.dataset.processing = '0';
                            button.disabled = false;
                            button.innerHTML = '<span><i class="fa-solid fa-credit-card"></i> Continue to Razorpay</span><i class="fa-solid fa-arrow-right"></i>';
                        }
                    },
                    handler: async function (paymentResponse) {
                        button.innerHTML = '<span><i class="fa-solid fa-spinner fa-spin"></i> Verifying payment...</span>';

                        try {
                            const verifyResponse = await fetch('ajax/verify_live_exam_payment.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: new URLSearchParams({
                                    exam_id: examId,
                                    csrf_token: csrf,
                                    razorpay_order_id: paymentResponse.razorpay_order_id || '',
                                    razorpay_payment_id: paymentResponse.razorpay_payment_id || '',
                                    razorpay_signature: paymentResponse.razorpay_signature || ''
                                })
                            });

                            const verifyData = await verifyResponse.json();
                            if (!verifyResponse.ok || !verifyData.status) {
                                throw new Error(verifyData.message || 'Payment verification failed.');
                            }

                            window.location.href = verifyData.redirect || ('live_exams.php#payment-' + examId);
                        } catch (error) {
                            alert(error.message || 'Payment verification failed.');
                            button.dataset.processing = '0';
                            button.disabled = false;
                            button.innerHTML = '<span><i class="fa-solid fa-credit-card"></i> Continue to Razorpay</span><i class="fa-solid fa-arrow-right"></i>';
                        }
                    }
                });

                rzp.open();
            } catch (error) {
                alert(error.message || 'Unable to start payment.');
                button.dataset.processing = '0';
                button.disabled = false;
                button.innerHTML = '<span><i class="fa-solid fa-credit-card"></i> Continue to Razorpay</span><i class="fa-solid fa-arrow-right"></i>';
            }
        });
    });

    if (window.location.hash.startsWith('#payment-')) {
        const target = document.querySelector(window.location.hash);
        if (target) {
            setTimeout(function () {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 150);
        }
    }
});
</script>


</body>

</html>