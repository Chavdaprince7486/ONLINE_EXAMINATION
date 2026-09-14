<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/auth.php';

require_login('student');


$studentId =
    (int) (
        $_SESSION['user_id']
        ?? 0
    );


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function live_escape(
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


function live_number(
    mixed $value
): string {

    $number =
        round(
            (float) (
                $value ?? 0
            ),
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


function live_parse_datetime(
    mixed $value
): ?DateTimeImmutable {

    if (
        $value === null ||
        trim(
            (string) $value
        ) === ''
    ) {

        return null;
    }


    try {

        return new DateTimeImmutable(
            (string) $value
        );

    } catch (
        Throwable $exception
    ) {

        return null;
    }
}


function live_exam_state(
    ?DateTimeImmutable $start,
    ?DateTimeImmutable $end,
    DateTimeImmutable $now,
    string $examStatus
): string {

    if (
        $examStatus === 'Cancelled'
    ) {

        return 'cancelled';
    }


    if (
        $examStatus === 'Completed'
    ) {

        return 'completed';
    }


    if (
        $end !== null &&
        $now > $end
    ) {

        return 'completed';
    }


    if (
        $start !== null &&
        $now < $start
    ) {

        return 'upcoming';
    }


    if (
        $start !== null &&
        $now >= $start &&
        (
            $end === null ||
            $now <= $end
        )
    ) {

        return 'live';
    }


    if (
        in_array(
            $examStatus,
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


function live_state_label(
    string $state
): string {

    return match ($state) {

        'live' =>
            'Live Now',

        'upcoming' =>
            'Upcoming',

        'completed' =>
            'Completed',

        'cancelled' =>
            'Cancelled',

        default =>
            'Scheduled'
    };
}


function live_state_icon(
    string $state
): string {

    return match ($state) {

        'live' =>
            'fa-tower-broadcast',

        'upcoming' =>
            'fa-calendar-days',

        'completed' =>
            'fa-circle-check',

        'cancelled' =>
            'fa-ban',

        default =>
            'fa-calendar'
    };
}


function live_state_badge_class(
    string $state
): string {

    return match ($state) {

        'live' =>
            'live-badge-live',

        'upcoming' =>
            'live-badge-upcoming',

        'completed' =>
            'live-badge-completed',

        'cancelled' =>
            'live-badge-cancelled',

        default =>
            'live-badge-upcoming'
    };
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search =
    trim(
        (string) (
            $_GET['search']
            ?? ''
        )
    );


$subjectId =
    filter_input(
        INPUT_GET,
        'subject_id',
        FILTER_VALIDATE_INT
    );


if (
    $subjectId === false ||
    $subjectId === null ||
    $subjectId <= 0
) {

    $subjectId =
        null;
}


/*
|--------------------------------------------------------------------------
| SUBJECTS
|--------------------------------------------------------------------------
*/

$subjects =
    [];


try {

    $subjectStatement =
        $conn->query(
            "
            SELECT

                id,
                name,
                code

            FROM subjects

            WHERE

                status = 'Active'

            ORDER BY

                name ASC,
                id ASC
            "
        );


    $subjects =
        $subjectStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $exception
) {

    error_log(
        'Live subjects load failed: ' .
        $exception->getMessage()
    );

    $subjects =
        [];
}


/*
|--------------------------------------------------------------------------
| ACTIVE SUBSCRIPTION
|--------------------------------------------------------------------------
*/

$hasActiveSubscription =
    false;


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
        'Live subscription check failed: ' .
        $exception->getMessage()
    );

    $hasActiveSubscription =
        false;
}


/*
|--------------------------------------------------------------------------
| LIVE EXAMS
|--------------------------------------------------------------------------
*/

$exams =
    [];


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

            e.passing_marks,

            e.negative_marking,

            e.exam_fee,

            e.subscription_required,

            e.status,

            e.starts_at,
            e.ends_at,

            e.created_at,
            e.updated_at,

            s.name AS subject_name,
            s.code AS subject_code,

            COUNT(
                DISTINCT eq.question_id
            ) AS active_question_count,

            MIN(
                q.marks
            ) AS marks_per_question_min,

            MAX(
                q.marks
            ) AS marks_per_question_max

        FROM exams e

        INNER JOIN subjects s

            ON s.id =
                e.subject_id

            AND s.status =
                'Active'

        LEFT JOIN exam_questions eq

            ON eq.exam_id =
                e.id

        LEFT JOIN questions q

            ON q.id =
                eq.question_id

            AND q.status =
                'Active'

        WHERE

            e.exam_type =
                'Live'

            AND e.required_question_count >
                0
    ";


    $parameters =
        [];


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


    /*
    |--------------------------------------------------------------------------
    | SUBJECT FILTER
    |--------------------------------------------------------------------------
    */

    if (
        $subjectId !== null
    ) {

        $sql .= "
            AND e.subject_id = ?
        ";


        $parameters[] =
            $subjectId;
    }


    $sql .= "

        GROUP BY

            e.id,
            e.subject_id,

            e.title,
            e.description,

            e.exam_type,

            e.duration_minutes,

            e.required_question_count,

            e.passing_marks,

            e.negative_marking,

            e.exam_fee,

            e.subscription_required,

            e.status,

            e.starts_at,
            e.ends_at,

            e.created_at,
            e.updated_at,

            s.name,
            s.code

        ORDER BY

            CASE

                WHEN
                    e.starts_at IS NOT NULL
                    AND e.starts_at > NOW()
                THEN 0

                WHEN
                    (
                        e.starts_at IS NULL
                        OR e.starts_at <= NOW()
                    )
                    AND
                    (
                        e.ends_at IS NULL
                        OR e.ends_at >= NOW()
                    )
                THEN 1

                ELSE 2

            END ASC,

            COALESCE(
                e.starts_at,
                e.created_at
            ) ASC,

            e.id ASC
    ";


    $examStatement =
        $conn->prepare(
            $sql
        );


    $examStatement->execute(
        $parameters
    );


    $exams =
        $examStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (
    Throwable $exception
) {

    error_log(
        'Live exams load failed: ' .
        $exception->getMessage()
    );

    $exams =
        [];
}


/*
|--------------------------------------------------------------------------
| EXAM IDS
|--------------------------------------------------------------------------
*/

$examIds =
    [];


foreach (
    $exams as $exam
) {

    $examIds[] =
        (int) $exam['id'];
}


$examIds =
    array_values(
        array_unique(
            $examIds
        )
    );


/*
|--------------------------------------------------------------------------
| LATEST RESULTS
|--------------------------------------------------------------------------
*/

$latestResults =
    [];


if (
    !empty($examIds)
) {

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($examIds),
                '?'
            )
        );


    try {

        $resultStatement =
            $conn->prepare(
                "
                SELECT

                    r.id,
                    r.exam_id,
                    r.attempt_id,

                    r.percentage,
                    r.grade,

                    r.result_status,

                    r.obtained_marks,
                    r.total_marks,

                    r.correct_answers,
                    r.wrong_answers,
                    r.unanswered_questions,

                    r.created_at

                FROM results r

                INNER JOIN (

                    SELECT

                        exam_id,

                        MAX(id)
                            AS latest_result_id

                    FROM results

                    WHERE

                        student_id = ?

                        AND exam_id IN (
                            {$placeholders}
                        )

                    GROUP BY

                        exam_id

                ) latest

                    ON latest.latest_result_id =
                        r.id

                WHERE

                    r.student_id = ?

                "
            );


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


        while (
            $row =
                $resultStatement->fetch(
                    PDO::FETCH_ASSOC
                )
        ) {

            $latestResults[
                (int) $row[
                    'exam_id'
                ]
            ] =
                $row;
        }

    } catch (
        Throwable $exception
    ) {

        error_log(
            'Live latest results lookup failed: ' .
            $exception->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| ACTIVE ATTEMPTS
|--------------------------------------------------------------------------
*/

$activeAttempts =
    [];


if (
    !empty($examIds)
) {

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($examIds),
                '?'
            )
        );


    try {

        $attemptStatement =
            $conn->prepare(
                "
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

                        MAX(id)
                            AS latest_attempt_id

                    FROM exam_attempts

                    WHERE

                        student_id = ?

                        AND exam_id IN (
                            {$placeholders}
                        )

                        AND status =
                            'Started'

                    GROUP BY

                        exam_id

                ) latest

                    ON latest.latest_attempt_id =
                        ea.id

                WHERE

                    ea.student_id = ?

                    AND ea.status =
                        'Started'

                ORDER BY

                    ea.id DESC
                "
            );


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


        while (
            $row =
                $attemptStatement->fetch(
                    PDO::FETCH_ASSOC
                )
        ) {

            $activeAttempts[
                (int) $row[
                    'exam_id'
                ]
            ] =
                $row;
        }

    } catch (
        Throwable $exception
    ) {

        error_log(
            'Live active attempts lookup failed: ' .
            $exception->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| ENRICH
|--------------------------------------------------------------------------
*/

$now =
    new DateTimeImmutable();


$liveNowCount =
    0;


$upcomingCount =
    0;


$completedCount =
    0;


$attemptedCount =
    0;


$inProgressCount =
    0;


$totalLiveQuestions =
    0;


$readyCount =
    0;


foreach (
    $exams as &$exam
) {

    $examId =
        (int) $exam['id'];


    $questionCount =
        (int) (
            $exam[
                'active_question_count'
            ] ?? 0
        );


    $requiredCount =
        (int) (
            $exam[
                'required_question_count'
            ] ?? 0
        );


    $marksMin =
        round(
            (float) (
                $exam[
                    'marks_per_question_min'
                ] ?? 0
            ),
            2
        );


    $marksMax =
        round(
            (float) (
                $exam[
                    'marks_per_question_max'
                ] ?? 0
            ),
            2
        );


    /*
    |--------------------------------------------------------------------------
    | DYNAMIC TOTAL
    |--------------------------------------------------------------------------
    */

    $dynamicTotalMarks =
        round(
            $questionCount *
            $marksMin,
            2
        );


    $exam[
        'marks_per_question'
    ] =
        $marksMin;


    $exam[
        'dynamic_total_marks'
    ] =
        $dynamicTotalMarks;


    /*
    |--------------------------------------------------------------------------
    | READY
    |--------------------------------------------------------------------------
    */

    $isReady =
        (
            $requiredCount > 0
            &&
            $questionCount ===
                $requiredCount
            &&
            $marksMin > 0
            &&
            $marksMin ===
                $marksMax
        );


    $exam[
        'exam_ready'
    ] =
        $isReady;


    if (
        $isReady
    ) {

        $readyCount++;
    }


    /*
    |--------------------------------------------------------------------------
    | DATES
    |--------------------------------------------------------------------------
    */

    $start =
        live_parse_datetime(
            $exam[
                'starts_at'
            ] ?? null
        );


    $end =
        live_parse_datetime(
            $exam[
                'ends_at'
            ] ?? null
        );


    $exam[
        'start_datetime'
    ] =
        $start;


    $exam[
        'end_datetime'
    ] =
        $end;


    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    $state =
        live_exam_state(
            $start,
            $end,
            $now,
            (string) (
                $exam[
                    'status'
                ] ?? ''
            )
        );


    $exam[
        'state'
    ] =
        $state;


    if (
        $state === 'live'
    ) {

        $liveNowCount++;

    } elseif (
        $state === 'upcoming'
    ) {

        $upcomingCount++;

    } elseif (
        $state === 'completed'
    ) {

        $completedCount++;
    }


    /*
    |--------------------------------------------------------------------------
    | RESULT
    |--------------------------------------------------------------------------
    */

    $exam[
        'latest_result'
    ] =
        $latestResults[
            $examId
        ] ?? null;


    /*
    |--------------------------------------------------------------------------
    | ATTEMPT
    |--------------------------------------------------------------------------
    */

    $exam[
        'active_attempt'
    ] =
        $activeAttempts[
            $examId
        ] ?? null;


    if (
        $exam[
            'active_attempt'
        ] !== null
    ) {

        $inProgressCount++;
    }


    if (
        $exam[
            'latest_result'
        ] !== null
    ) {

        $attemptedCount++;
    }


    $totalLiveQuestions +=
        $questionCount;
}


unset(
    $exam
);


/*
|--------------------------------------------------------------------------
| FILTER STATE
|--------------------------------------------------------------------------
*/

$filterActive =
    (
        $search !== ''
        ||
        $subjectId !== null
    );


$renderExamCount =
    count(
        $exams
    );


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$pageTitle =
    'Live Exams';

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
        content="#F5F5DC"
    >


    <meta
        name="csrf-token"
        content="<?= live_escape(
            function_exists('csrf_token')
                ? csrf_token()
                : (
                    $_SESSION[
                        'csrf_token'
                    ] ?? ''
                )
        ); ?>"
    >


    <title>

        <?= live_escape(
            $pageTitle
        ); ?>

        |

        ExamSphere

    </title>


    <!-- =====================================================
         FONT
    ====================================================== -->

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


    <!-- =====================================================
         ICONS
    ====================================================== -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >


    <!-- =====================================================
         EXISTING DASHBOARD CSS
    ====================================================== -->

    <link
        rel="stylesheet"
        href="assets/css/dashboard.css"
    >


    <style>

        /*
        =========================================================
        EXAMSPHERE LIVE EXAMS — PRACTICE STYLE
        =========================================================
        */

        :root {

            --cream:
                #F5F5DC;

            --cream-light:
                #FAF9F4;

            --white:
                #FFFFFF;

            --brown:
                #5D4037;

            --brown-dark:
                #3E2723;

            --brown-soft:
                #76574C;

            --olive:
                #556B2F;

            --olive-dark:
                #435620;

            --charcoal:
                #333333;

            --muted:
                #7F766E;

            --border:
                #E4DED3;

            --green-bg:
                #EAF3E2;

            --green:
                #597233;

            --gold-bg:
                #FFF4DE;

            --gold:
                #9B741F;

            --red-bg:
                #FBE9E6;

            --red:
                #A64B42;

            --purple-bg:
                #F1ECF8;

            --purple:
                #705A99;

            --shadow-sm:
                0 10px 30px
                rgba(
                    62,
                    39,
                    35,
                    .055
                );

            --shadow:
                0 22px 55px
                rgba(
                    62,
                    39,
                    35,
                    .09
                );

            --shadow-lg:
                0 30px 75px
                rgba(
                    62,
                    39,
                    35,
                    .14
                );

        }


        * {

            box-sizing:
                border-box;

        }


        html {

            scroll-behavior:
                smooth;

        }


        body {

            margin:
                0;

            color:
                var(--charcoal);

            font-family:
                'Poppins',
                Arial,
                sans-serif;

            background:

                radial-gradient(
                    circle at 5% 4%,
                    rgba(
                        85,
                        107,
                        47,
                        .10
                    ),
                    transparent 24%
                ),

                radial-gradient(
                    circle at 96% 3%,
                    rgba(
                        93,
                        64,
                        55,
                        .11
                    ),
                    transparent 25%
                ),

                linear-gradient(
                    180deg,
                    #F8F7EB 0%,
                    var(--cream) 46%,
                    #F1EFE5 100%
                );

        }


        a {

            text-decoration:
                none;

        }


        button,
        input,
        select {

            font-family:
                inherit;

        }


        /*
        =========================================================
        MAIN
        =========================================================
        */

        .live-premium-page {

            width:
                min(
                    1460px,
                    calc(
                        100% - 40px
                    )
                );

            margin:
                30px auto 70px;

        }


        /*
        =========================================================
        HERO
        =========================================================
        */

        .live-hero {

            position:
                relative;

            overflow:
                hidden;

            display:
                grid;

            grid-template-columns:
                minmax(
                    0,
                    1fr
                )
                310px;

            gap:
                30px;

            padding:
                34px;

            border:
                1px solid
                rgba(
                    228,
                    222,
                    211,
                    .92
                );

            border-radius:
                30px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .89
                );

            box-shadow:
                var(--shadow);

            backdrop-filter:
                blur(
                    20px
                );

        }


        .live-hero::before {

            content:
                '';

            position:
                absolute;

            width:
                320px;

            height:
                320px;

            top:
                -190px;

            right:
                -100px;

            border-radius:
                50%;

            background:
                rgba(
                    85,
                    107,
                    47,
                    .10
                );

        }


        .live-hero::after {

            content:
                '';

            position:
                absolute;

            width:
                210px;

            height:
                210px;

            bottom:
                -150px;

            left:
                33%;

            border-radius:
                50%;

            background:
                rgba(
                    93,
                    64,
                    55,
                    .065
                );

        }


        .live-hero-content {

            position:
                relative;

            z-index:
                2;

        }


        .live-kicker {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            padding:
                7px 10px;

            border:
                1px solid
                #DFD8CD;

            border-radius:
                9px;

            color:
                var(--olive);

            background:
                #F6F6EB;

            font-size:
                8px;

            font-weight:
                800;

            letter-spacing:
                1.4px;

            text-transform:
                uppercase;

        }


        .live-kicker i {

            font-size:
                10px;

        }


        .live-hero-title {

            margin:
                15px 0 10px;

            max-width:
                860px;

            color:
                var(--brown-dark);

            font-size:
                clamp(
                    32px,
                    4.2vw,
                    56px
                );

            line-height:
                1.05;

            letter-spacing:
                -.045em;

            font-weight:
                900;

        }


        .live-hero-title span {

            color:
                var(--olive);

            font-weight:
                700;

        }


        .live-hero-text {

            max-width:
                820px;

            margin:
                0;

            color:
                var(--muted);

            font-size:
                11px;

            line-height:
                1.85;

            font-weight:
                500;

        }


        .live-hero-pills {

            display:
                flex;

            flex-wrap:
                wrap;

            gap:
                8px;

            margin-top:
                18px;

        }


        .live-hero-pill {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            min-height:
                34px;

            padding:
                0 11px;

            border:
                1px solid
                var(--border);

            border-radius:
                999px;

            color:
                var(--brown);

            background:
                #FBFAF6;

            font-size:
                8px;

            font-weight:
                700;

        }


        .live-hero-pill i {

            color:
                var(--olive);

            font-size:
                10px;

        }


        /*
        =========================================================
        HERO SIDE
        =========================================================
        */

        .live-hero-side {

            position:
                relative;

            z-index:
                2;

            padding:
                23px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .18
                );

            border-radius:
                23px;

            color:
                #FFFFFF;

            background:
                linear-gradient(
                    145deg,
                    #5D4037 0%,
                    #452D27 55%,
                    #3E2723 100%
                );

            box-shadow:
                0
                25px
                50px
                rgba(
                    62,
                    39,
                    35,
                    .19
                );

        }


        .live-hero-side-icon {

            width:
                49px;

            height:
                49px;

            display:
                grid;

            place-items:
                center;

            margin-bottom:
                15px;

            border-radius:
                15px;

            color:
                #FFFFFF;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .10
                );

            font-size:
                18px;

        }


        .live-hero-side small {

            display:
                block;

            color:
                #D8CEC3;

            font-size:
                8px;

            text-transform:
                uppercase;

            letter-spacing:
                1.1px;

        }


        .live-hero-side strong {

            display:
                block;

            margin-top:
                4px;

            color:
                #FFFFFF;

            font-size:
                34px;

            line-height:
                1.1;

            font-weight:
                900;

        }


        .live-hero-side p {

            margin:
                8px 0 0;

            color:
                #DED6CE;

            font-size:
                8px;

            line-height:
                1.7;

        }


        .live-hero-side-bottom {

            display:
                grid;

            grid-template-columns:
                1fr
                1fr;

            gap:
                8px;

            margin-top:
                18px;

        }


        .live-side-stat {

            padding:
                11px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .11
                );

            border-radius:
                12px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .06
                );

        }


        .live-side-stat span {

            display:
                block;

            color:
                #BFB4A9;

            font-size:
                6.5px;

            text-transform:
                uppercase;

            letter-spacing:
                .6px;

        }


        .live-side-stat strong {

            margin-top:
                3px;

            font-size:
                13px;

        }


        /*
        =========================================================
        SUBSCRIPTION ALERT
        =========================================================
        */

        .live-subscription-alert {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            margin-top:
                15px;

            padding:
                10px 12px;

            border:
                1px solid
                #DFD8CD;

            border-radius:
                12px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .75
                );

            color:
                var(--brown);

            font-size:
                8px;

            font-weight:
                700;

        }


        .live-subscription-alert.active {

            color:
                var(--olive-dark);

            background:
                #F1F6E9;

            border-color:
                #D9E6C8;

        }


        .live-subscription-alert i {

            font-size:
                11px;

        }


        /*
        =========================================================
        SUMMARY
        =========================================================
        */

        .live-summary-grid {

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
                15px;

        }


        .live-summary-card {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

            min-height:
                83px;

            padding:
                14px;

            border:
                1px solid
                var(--border);

            border-radius:
                18px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .87
                );

            box-shadow:
                var(--shadow-sm);

            transition:
                transform .22s ease,
                box-shadow .22s ease;

        }


        .live-summary-card:hover {

            transform:
                translateY(
                    -2px
                );

            box-shadow:
                0
                18px
                38px
                rgba(
                    62,
                    39,
                    35,
                    .08
                );

        }


        .live-summary-icon {

            width:
                45px;

            height:
                45px;

            flex:
                0 0 auto;

            display:
                grid;

            place-items:
                center;

            border-radius:
                14px;

            color:
                var(--brown);

            background:
                #F0EAE2;

            font-size:
                14px;

        }


        .live-summary-card:nth-child(2)
        .live-summary-icon {

            color:
                var(--olive);

            background:
                var(--green-bg);

        }


        .live-summary-card:nth-child(3)
        .live-summary-icon {

            color:
                var(--gold);

            background:
                var(--gold-bg);

        }


        .live-summary-card:nth-child(4)
        .live-summary-icon {

            color:
                var(--purple);

            background:
                var(--purple-bg);

        }


        .live-summary-content span {

            display:
                block;

            color:
                var(--muted);

            font-size:
                7px;

            font-weight:
                600;

            text-transform:
                uppercase;

            letter-spacing:
                .7px;

        }


        .live-summary-content strong {

            display:
                block;

            margin-top:
                2px;

            color:
                var(--brown-dark);

            font-size:
                20px;

            line-height:
                1.15;

            font-weight:
                900;

        }


        /*
        =========================================================
        TOOLBAR
        =========================================================
        */

        .live-toolbar {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            margin-top:
                19px;

            padding:
                10px;

            border:
                1px solid
                var(--border);

            border-radius:
                18px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .90
                );

            box-shadow:
                var(--shadow-sm);

            backdrop-filter:
                blur(
                    18px
                );

        }


        .live-search {

            flex:
                1;

            min-height:
                47px;

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            padding:
                0 13px;

            border:
                1px solid
                #DDD6CB;

            border-radius:
                12px;

            background:
                #FCFBF8;

        }


        .live-search i,
        .live-subject i {

            color:
                #95897E;

            font-size:
                11px;

        }


        .live-search input {

            width:
                100%;

            height:
                100%;

            border:
                0;

            outline:
                0;

            color:
                var(--brown-dark);

            background:
                transparent;

            font-size:
                9px;

            font-weight:
                600;

        }


        .live-search input::placeholder {

            color:
                #9B9188;

        }


        .live-subject {

            width:
                245px;

            min-height:
                47px;

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            padding:
                0 12px;

            border:
                1px solid
                #DDD6CB;

            border-radius:
                12px;

            background:
                #FCFBF8;

        }


        .live-subject select {

            width:
                100%;

            border:
                0;

            outline:
                0;

            color:
                var(--brown-dark);

            background:
                transparent;

            font-size:
                9px;

            font-weight:
                600;

            cursor:
                pointer;

        }


        .live-toolbar-button {

            min-height:
                47px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                7px;

            padding:
                0 15px;

            border:
                0;

            border-radius:
                12px;

            color:
                #FFFFFF;

            background:
                linear-gradient(
                    135deg,
                    var(--brown),
                    var(--brown-dark)
                );

            font-size:
                8px;

            font-weight:
                800;

            cursor:
                pointer;

            transition:
                .2s ease;

        }


        .live-toolbar-button:hover {

            color:
                #FFFFFF;

            transform:
                translateY(
                    -1px
                );

            box-shadow:
                0
                10px
                24px
                rgba(
                    62,
                    39,
                    35,
                    .16
                );

        }


        .live-clear-button {

            color:
                var(--brown);

            background:
                #EEE9E0;

        }


        .live-clear-button:hover {

            color:
                var(--brown-dark);

            background:
                #E5DFD4;

            box-shadow:
                none;

        }


        /*
        =========================================================
        SECTION HEAD
        =========================================================
        */

        .live-section-head {

            display:
                flex;

            align-items:
                flex-end;

            justify-content:
                space-between;

            gap:
                15px;

            margin:
                30px 0 16px;

        }


        .live-section-kicker {

            color:
                var(--olive);

            font-size:
                8px;

            font-weight:
                800;

            letter-spacing:
                1.5px;

            text-transform:
                uppercase;

        }


        .live-section-head h2 {

            margin:
                4px 0 0;

            color:
                var(--brown-dark);

            font-size:
                25px;

            line-height:
                1.2;

            font-weight:
                900;

        }


        .live-section-head p {

            margin:
                5px 0 0;

            color:
                var(--muted);

            font-size:
                9px;

        }


        .live-result-count {

            text-align:
                right;

        }


        .live-result-count strong {

            color:
                var(--brown);

            font-size:
                26px;

            font-weight:
                900;

        }


        .live-result-count span {

            margin-left:
                4px;

            color:
                var(--muted);

            font-size:
                8px;

            font-weight:
                600;

        }


        /*
        =========================================================
        GRID
        =========================================================
        */

        .live-grid {

            display:
                grid;

            grid-template-columns:
                repeat(
                    3,
                    minmax(
                        0,
                        1fr
                    )
                );

            gap:
                16px;

        }


        /*
        =========================================================
        CARD
        =========================================================
        */

        .live-exam-card {

            position:
                relative;

            overflow:
                hidden;

            min-height:
                530px;

            display:
                flex;

            flex-direction:
                column;

            padding:
                19px;

            border:
                1px solid
                var(--border);

            border-radius:
                22px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .96
                );

            box-shadow:
                var(--shadow-sm);

            transition:
                transform .23s ease,
                box-shadow .23s ease,
                border-color .23s ease;

        }


        .live-exam-card::before {

            content:
                '';

            position:
                absolute;

            width:
                150px;

            height:
                150px;

            right:
                -90px;

            top:
                -90px;

            border-radius:
                50%;

            background:
                rgba(
                    85,
                    107,
                    47,
                    .065
                );

            transition:
                .3s ease;

        }


        .live-exam-card::after {

            content:
                '';

            position:
                absolute;

            height:
                3px;

            left:
                19px;

            right:
                19px;

            top:
                0;

            border-radius:
                0 0 99px 99px;

            background:
                linear-gradient(
                    90deg,
                    var(--brown),
                    var(--olive)
                );

            opacity:
                0;

            transform:
                scaleX(
                    .4
                );

            transition:
                .25s ease;

        }


        .live-exam-card:hover {

            transform:
                translateY(
                    -6px
                );

            border-color:
                #D8D0C4;

            box-shadow:
                var(--shadow-lg);

        }


        .live-exam-card:hover::before {

            transform:
                scale(
                    1.3
                );

        }


        .live-exam-card:hover::after {

            opacity:
                1;

            transform:
                scaleX(
                    1
                );

        }


        /*
        =========================================================
        CARD TOP
        =========================================================
        */

        .live-card-top {

            position:
                relative;

            z-index:
                2;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                8px;

        }


        .live-card-number {

            width:
                38px;

            height:
                38px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                12px;

            color:
                var(--brown);

            background:
                #F0EAE2;

            font-size:
                9px;

            font-weight:
                900;

        }


        .live-card-badges {

            display:
                flex;

            flex-wrap:
                wrap;

            justify-content:
                flex-end;

            gap:
                5px;

        }


        .live-card-badge {

            min-height:
                25px;

            display:
                inline-flex;

            align-items:
                center;

            gap:
                5px;

            padding:
                0 8px;

            border-radius:
                999px;

            font-size:
                6.8px;

            font-weight:
                800;

        }


        .live-badge-live {

            color:
                var(--olive-dark);

            background:
                #EEF5E4;

        }


        .live-badge-upcoming {

            color:
                var(--gold);

            background:
                var(--gold-bg);

        }


        .live-badge-completed {

            color:
                #746B63;

            background:
                #F1EDE7;

        }


        .live-badge-cancelled {

            color:
                var(--red);

            background:
                var(--red-bg);

        }


        .live-badge-premium {

            color:
                var(--brown);

            background:
                #F1E9DF;

        }


        /*
        =========================================================
        CARD ICON
        =========================================================
        */

        .live-card-icon {

            position:
                relative;

            z-index:
                2;

            width:
                54px;

            height:
                54px;

            display:
                grid;

            place-items:
                center;

            margin-top:
                17px;

            border-radius:
                17px;

            color:
                #FFFFFF;

            background:
                linear-gradient(
                    145deg,
                    var(--brown),
                    var(--brown-dark)
                );

            box-shadow:
                0
                13px
                27px
                rgba(
                    62,
                    39,
                    35,
                    .16
                );

            font-size:
                17px;

        }


        .live-card-icon.live {

            background:
                linear-gradient(
                    145deg,
                    var(--olive),
                    var(--olive-dark)
                );

        }


        /*
        =========================================================
        CONTENT
        =========================================================
        */

        .live-card-content {

            position:
                relative;

            z-index:
                2;

        }


        .live-subject-label {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                5px;

            margin-top:
                15px;

            color:
                var(--olive);

            font-size:
                7.5px;

            font-weight:
                800;

            text-transform:
                uppercase;

            letter-spacing:
                .9px;

        }


        .live-subject-label small {

            color:
                #A0978E;

            font-size:
                6.7px;

            letter-spacing:
                0;

        }


        .live-card-title {

            margin:
                6px 0 0;

            color:
                var(--brown-dark);

            font-size:
                19px;

            line-height:
                1.35;

            font-weight:
                900;

            letter-spacing:
                -.01em;

        }


        .live-card-description {

            display:
                -webkit-box;

            -webkit-line-clamp:
                3;

            -webkit-box-orient:
                vertical;

            overflow:
                hidden;

            min-height:
                48px;

            margin:
                8px 0 0;

            color:
                var(--muted);

            font-size:
                8px;

            line-height:
                1.75;

            font-weight:
                500;

        }


        /*
        =========================================================
        META
        =========================================================
        */

        .live-meta {

            display:
                grid;

            grid-template-columns:
                repeat(
                    3,
                    1fr
                );

            gap:
                7px;

            margin-top:
                16px;

        }


        .live-meta-item {

            padding:
                9px 7px;

            border:
                1px solid
                #E7E1D8;

            border-radius:
                12px;

            background:
                #FCFBF7;

            text-align:
                center;

        }


        .live-meta-item i {

            display:
                block;

            margin-bottom:
                4px;

            color:
                var(--olive);

            font-size:
                9px;

        }


        .live-meta-item span {

            display:
                block;

            color:
                #8E857C;

            font-size:
                6.2px;

            text-transform:
                uppercase;

            letter-spacing:
                .4px;

        }


        .live-meta-item strong {

            display:
                block;

            margin-top:
                2px;

            color:
                var(--brown-dark);

            font-size:
                9.5px;

            font-weight:
                900;

        }


        /*
        =========================================================
        SCHEDULE
        =========================================================
        */

        .live-schedule {

            margin-top:
                9px;

            padding:
                10px 11px;

            border:
                1px dashed
                #DCD5CA;

            border-radius:
                12px;

            background:
                #FAF8F2;

        }


        .live-schedule-row {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                8px;

            padding:
                3px 0;

        }


        .live-schedule-row span {

            color:
                var(--muted);

            font-size:
                6.7px;

        }


        .live-schedule-row strong {

            color:
                var(--brown);

            font-size:
                7.2px;

            font-weight:
                800;

            text-align:
                right;

        }


        /*
        =========================================================
        ACCESS
        =========================================================
        */

        .live-access {

            margin-top:
                8px;

            padding:
                9px 11px;

            border:
                1px solid
                #EEE9E1;

            border-radius:
                12px;

            background:
                #FFFFFF;

        }


        .live-access-row {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                10px;

        }


        .live-access-label {

            display:
                flex;

            align-items:
                center;

            gap:
                6px;

            color:
                var(--muted);

            font-size:
                7px;

        }


        .live-access-label i {

            color:
                var(--olive);

        }


        .live-access-value {

            font-size:
                7px;

            font-weight:
                800;

        }


        .live-access-value.granted {

            color:
                var(--olive);

        }


        .live-access-value.locked {

            color:
                var(--red);

        }


        /*
        =========================================================
        RESULT
        =========================================================
        */

        .live-result {

            margin-top:
                8px;

            padding:
                9px 11px;

            border:
                1px solid
                #E8E0D5;

            border-radius:
                12px;

            background:
                #FCFBF7;

        }


        .live-result-row {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                10px;

        }


        .live-result-label {

            color:
                var(--muted);

            font-size:
                7px;

        }


        .live-result-score {

            color:
                var(--brown);

            font-size:
                8px;

            font-weight:
                900;

        }


        .live-result-grade {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            min-width:
                28px;

            min-height:
                23px;

            padding:
                0 6px;

            border-radius:
                8px;

            color:
                var(--olive-dark);

            background:
                var(--green-bg);

            font-size:
                8px;

            font-weight:
                900;

        }


        /*
        =========================================================
        ACTION
        =========================================================
        */

        .live-card-action {

            display:
                flex;

            align-items:
                center;

            gap:
                7px;

            margin-top:
                auto;

            padding-top:
                14px;

        }


        .live-primary-btn {

            flex:
                1;

            min-height:
                42px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                7px;

            padding:
                0 11px;

            border:
                0;

            border-radius:
                12px;

            color:
                #FFFFFF;

            background:
                linear-gradient(
                    135deg,
                    var(--brown),
                    var(--brown-dark)
                );

            font-size:
                8px;

            font-weight:
                800;

            transition:
                .2s ease;

        }


        .live-primary-btn:hover {

            color:
                #FFFFFF;

            transform:
                translateY(
                    -1px
                );

            box-shadow:
                0
                11px
                23px
                rgba(
                    62,
                    39,
                    35,
                    .16
                );

        }


        .live-primary-btn.olive {

            background:
                linear-gradient(
                    135deg,
                    var(--olive),
                    var(--olive-dark)
                );

        }


        .live-lock-btn {

            flex:
                1;

            min-height:
                42px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                7px;

            padding:
                0 11px;

            border:
                1px solid
                #E5D7BB;

            border-radius:
                12px;

            color:
                #87671F;

            background:
                #FFF6E3;

            font-size:
                8px;

            font-weight:
                800;

        }


        .live-ended-btn {

            flex:
                1;

            min-height:
                42px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                7px;

            border:
                1px solid
                var(--border);

            border-radius:
                12px;

            color:
                #746B63;

            background:
                #F3EFE9;

            font-size:
                8px;

            font-weight:
                800;

        }


        .live-secondary-btn {

            min-width:
                42px;

            min-height:
                42px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            border:
                1px solid
                var(--border);

            border-radius:
                12px;

            color:
                var(--brown);

            background:
                #FFFFFF;

            font-size:
                9px;

            transition:
                .2s ease;

        }


        .live-secondary-btn:hover {

            color:
                var(--brown-dark);

            background:
                #F7F3EA;

        }


        /*
        =========================================================
        INFO STRIP
        =========================================================
        */

        .live-info-strip {

            display:
                grid;

            grid-template-columns:
                repeat(
                    3,
                    1fr
                );

            gap:
                10px;

            margin-top:
                18px;

            padding:
                11px;

            border:
                1px solid
                var(--border);

            border-radius:
                18px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .80
                );

            box-shadow:
                var(--shadow-sm);

        }


        .live-info {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                9px;

            padding:
                9px;

            border-radius:
                12px;

        }


        .live-info-icon {

            width:
                33px;

            height:
                33px;

            flex:
                0 0 auto;

            display:
                grid;

            place-items:
                center;

            border-radius:
                10px;

            color:
                var(--olive);

            background:
                var(--green-bg);

            font-size:
                10px;

        }


        .live-info strong {

            display:
                block;

            color:
                var(--brown-dark);

            font-size:
                7.5px;

            font-weight:
                800;

        }


        .live-info small {

            display:
                block;

            margin-top:
                2px;

            color:
                var(--muted);

            font-size:
                6.7px;

            line-height:
                1.55;

        }


        /*
        =========================================================
        EMPTY
        =========================================================
        */

        .live-empty {

            padding:
                70px 25px;

            border:
                1px dashed
                #D9D1C5;

            border-radius:
                23px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .73
                );

            text-align:
                center;

        }


        .live-empty-icon {

            width:
                70px;

            height:
                70px;

            display:
                grid;

            place-items:
                center;

            margin:
                0 auto 14px;

            border-radius:
                21px;

            color:
                var(--brown);

            background:
                #EFEAE1;

            font-size:
                21px;

        }


        .live-empty-kicker {

            color:
                var(--olive);

            font-size:
                7.5px;

            font-weight:
                800;

            letter-spacing:
                1.4px;

            text-transform:
                uppercase;

        }


        .live-empty h3 {

            margin:
                6px 0;

            color:
                var(--brown-dark);

            font-size:
                21px;

            font-weight:
                900;

        }


        .live-empty p {

            max-width:
                560px;

            margin:
                0 auto 18px;

            color:
                var(--muted);

            font-size:
                8.5px;

            line-height:
                1.75;

        }


        .live-empty-btn {

            min-height:
                42px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                7px;

            padding:
                0 14px;

            border-radius:
                12px;

            color:
                #FFFFFF;

            background:
                var(--brown);

            font-size:
                8px;

            font-weight:
                800;

        }


        .live-empty-btn:hover {

            color:
                #FFFFFF;

            background:
                var(--brown-dark);

        }


        /*
        =========================================================
        RESPONSIVE
        =========================================================
        */

        @media (
            max-width: 1250px
        ) {

            .live-grid {

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
            max-width: 1050px
        ) {

            .live-hero {

                grid-template-columns:
                    1fr;

            }


            .live-hero-side {

                width:
                    100%;

            }


            .live-summary-grid {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(
                            0,
                            1fr
                        )
                    );

            }


            .live-toolbar {

                flex-wrap:
                    wrap;

            }


            .live-search {

                flex:
                    1 1
                    100%;

            }


            .live-subject {

                flex:
                    1;

                width:
                    auto;

            }


            .live-info-strip {

                grid-template-columns:
                    1fr;

            }

        }


        @media (
            max-width: 720px
        ) {

            .live-premium-page {

                width:
                    calc(
                        100% - 18px
                    );

                margin:
                    12px auto 40px;

            }


            .live-hero {

                padding:
                    20px;

                border-radius:
                    21px;

            }


            .live-hero-title {

                font-size:
                    35px;

                letter-spacing:
                    -.04em;

            }


            .live-hero-text {

                font-size:
                    9px;

            }


            .live-summary-grid {

                grid-template-columns:
                    1fr;

            }


            .live-toolbar {

                display:
                    grid;

                grid-template-columns:
                    1fr;

            }


            .live-search,
            .live-subject {

                width:
                    100%;

            }


            .live-toolbar-button {

                width:
                    100%;

            }


            .live-section-head {

                align-items:
                    flex-start;

                flex-direction:
                    column;

            }


            .live-result-count {

                text-align:
                    left;

            }


            .live-grid {

                grid-template-columns:
                    1fr;

            }


            .live-exam-card {

                min-height:
                    0;

            }

        }


        @media (
            max-width: 430px
        ) {

            .live-hero-pills {

                flex-direction:
                    column;

            }


            .live-hero-pill {

                width:
                    fit-content;

            }


            .live-meta {

                grid-template-columns:
                    1fr;

            }


            .live-card-action {

                flex-direction:
                    column;

            }


            .live-secondary-btn {

                width:
                    100%;

            }


            .live-hero-side-bottom {

                grid-template-columns:
                    1fr;

            }

        }


        /*
        =========================================================
        REDUCED MOTION
        =========================================================
        */

        @media (
            prefers-reduced-motion: reduce
        ) {

            *,

            *::before,

            *::after {

                animation-duration:
                    .01ms !important;

                animation-iteration-count:
                    1 !important;

                transition-duration:
                    .01ms !important;

                scroll-behavior:
                    auto !important;

            }

        }

    </style>

</head>


<body>


<?php include 'includes/navbar.php'; ?>


<main
    class="
        live-premium-page
    "
>


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section
        class="
            live-hero
        "
    >


        <div
            class="
                live-hero-content
            "
        >


            <span
                class="
                    live-kicker
                "
            >

                <i
                    class="
                        fa-solid
                        fa-tower-broadcast
                    "
                ></i>

                PREMIUM LIVE ZONE

            </span>


            <h1
                class="
                    live-hero-title
                "
            >

                Join live.

                <span>
                    Perform at your best.
                </span>

            </h1>


            <p
                class="
                    live-hero-text
                "
            >

                Take scheduled premium examinations in a secure
                ExamSphere environment. Active subscribers can
                join eligible live examinations during their
                configured schedule.

            </p>


            <div
                class="
                    live-hero-pills
                "
            >


                <span
                    class="
                        live-hero-pill
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-shield-halved
                        "
                    ></i>

                    Verified Access

                </span>


                <span
                    class="
                        live-hero-pill
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-clock
                        "
                    ></i>

                    Scheduled Exams

                </span>


                <span
                    class="
                        live-hero-pill
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-bolt
                        "
                    ></i>

                    Live Performance

                </span>


                <span
                    class="
                        live-hero-pill
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-lock
                        "
                    ></i>

                    Subscription Based

                </span>


            </div>


        </div>


        <div
            class="
                live-hero-side
            "
        >


            <div
                class="
                    live-hero-side-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-satellite-dish
                    "
                ></i>

            </div>


            <small>
                Live Exam Library
            </small>


            <strong>
                <?= $renderExamCount; ?>
            </strong>


            <p>

                Scheduled live examinations currently
                available in your ExamSphere account.

            </p>


            <div
                class="
                    live-hero-side-bottom
                "
            >


                <div
                    class="
                        live-side-stat
                    "
                >

                    <span>
                        Live Now
                    </span>

                    <strong>

                        <?= $liveNowCount; ?>

                    </strong>

                </div>


                <div
                    class="
                        live-side-stat
                    "
                >

                    <span>
                        Upcoming
                    </span>

                    <strong>

                        <?= $upcomingCount; ?>

                    </strong>

                </div>


            </div>


            <div
                class="
                    live-subscription-alert
                    <?= $hasActiveSubscription
                        ? 'active'
                        : '' ?>"
            >

                <i
                    class="
                        fa-solid
                        <?= $hasActiveSubscription
                            ? 'fa-circle-check'
                            : 'fa-lock' ?>
                    "
                ></i>


                <span>

                    <?= $hasActiveSubscription
                        ? 'Active subscription verified'
                        : 'Active subscription required'
                    ?>

                </span>

            </div>


        </div>


    </section>


    <!-- =====================================================
         SUMMARY
    ====================================================== -->

    <section
        class="
            live-summary-grid
        "
    >


        <div
            class="
                live-summary-card
            "
        >

            <div
                class="
                    live-summary-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-tower-broadcast
                    "
                ></i>

            </div>


            <div
                class="
                    live-summary-content
                "
            >

                <span>
                    Live Now
                </span>


                <strong>

                    <?= $liveNowCount; ?>

                </strong>

            </div>

        </div>


        <div
            class="
                live-summary-card
            "
        >

            <div
                class="
                    live-summary-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-calendar-days
                    "
                ></i>

            </div>


            <div
                class="
                    live-summary-content
                "
            >

                <span>
                    Upcoming
                </span>


                <strong>

                    <?= $upcomingCount; ?>

                </strong>

            </div>

        </div>


        <div
            class="
                live-summary-card
            "
        >

            <div
                class="
                    live-summary-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-play
                    "
                ></i>

            </div>


            <div
                class="
                    live-summary-content
                "
            >

                <span>
                    In Progress
                </span>


                <strong>

                    <?= $inProgressCount; ?>

                </strong>

            </div>

        </div>


        <div
            class="
                live-summary-card
            "
        >

            <div
                class="
                    live-summary-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-chart-column
                    "
                ></i>

            </div>


            <div
                class="
                    live-summary-content
                "
            >

                <span>
                    Attempted
                </span>


                <strong>

                    <?= $attemptedCount; ?>

                </strong>

            </div>

        </div>


    </section>


    <!-- =====================================================
         TOOLBAR
    ====================================================== -->

    <section
        class="
            live-toolbar
        "
    >


        <form
            method="GET"
            action="live_exams.php"
            style="display:contents;"
        >


            <label
                class="
                    live-search
                "
            >

                <i
                    class="
                        fa-solid
                        fa-magnifying-glass
                    "
                ></i>


                <input
                    type="search"
                    name="search"
                    value="<?= live_escape(
                        $search
                    ); ?>"
                    placeholder="Search live exam, subject or code..."
                    autocomplete="off"
                >

            </label>


            <label
                class="
                    live-subject
                "
            >

                <i
                    class="
                        fa-solid
                        fa-book-open
                    "
                ></i>


                <select
                    name="subject_id"
                    aria-label="Filter by subject"
                >

                    <option
                        value=""
                    >
                        All Subjects
                    </option>


                    <?php foreach (
                        $subjects
                        as $subject
                    ): ?>


                        <option
                            value="<?= (int) $subject[
                                'id'
                            ]; ?>"
                            <?= (
                                $subjectId !== null
                                &&
                                $subjectId ===
                                    (int) $subject[
                                        'id'
                                    ]
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= live_escape(
                                $subject[
                                    'name'
                                ]
                            ); ?>


                            <?php if (
                                !empty(
                                    $subject[
                                        'code'
                                    ]
                                )
                            ): ?>

                                —
                                <?= live_escape(
                                    $subject[
                                        'code'
                                    ]
                                ); ?>

                            <?php endif; ?>


                        </option>


                    <?php endforeach; ?>


                </select>


            </label>


            <button
                type="submit"
                class="
                    live-toolbar-button
                "
            >

                <i
                    class="
                        fa-solid
                        fa-filter
                    "
                ></i>

                Apply Filter

            </button>


            <?php if (
                $filterActive
            ): ?>


                <a
                    href="live_exams.php"
                    class="
                        live-toolbar-button
                        live-clear-button
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-xmark
                        "
                    ></i>

                    Clear

                </a>


            <?php endif; ?>


        </form>


    </section>


    <!-- =====================================================
         SECTION HEAD
    ====================================================== -->

    <section
        class="
            live-section-head
        "
    >


        <div>


            <div
                class="
                    live-section-kicker
                "
            >

                <?= $filterActive
                    ? 'Filtered Live Library'
                    : 'Scheduled Live Library'
                ?>

            </div>


            <h2>
                Choose your next live challenge
            </h2>


            <p>

                <?= $filterActive
                    ? 'Live examinations matching your current filters.'
                    : 'Published scheduled live examinations appear here automatically.'
                ?>

            </p>


        </div>


        <div
            class="
                live-result-count
            "
        >

            <strong>

                <?= $renderExamCount; ?>

            </strong>


            <span>

                <?= $renderExamCount === 1
                    ? 'exam available'
                    : 'exams available'
                ?>

            </span>

        </div>


    </section>


    <!-- =====================================================
         LIVE EXAMS
    ====================================================== -->

    <?php if (
        !empty($exams)
    ): ?>


        <section
            class="
                live-grid
            "
        >


            <?php foreach (
                $exams
                as $index => $exam
            ): ?>


                <?php

                $examId =
                    (int) $exam[
                        'id'
                    ];


                $questionCount =
                    (int) $exam[
                        'active_question_count'
                    ];


                $requiredCount =
                    (int) $exam[
                        'required_question_count'
                    ];


                $duration =
                    max(
                        0,
                        (int) $exam[
                            'duration_minutes'
                        ]
                    );


                $marksPerQuestion =
                    (float) $exam[
                        'marks_per_question'
                    ];


                $totalMarks =
                    (float) $exam[
                        'dynamic_total_marks'
                    ];


                $passingMarks =
                    (float) (
                        $exam[
                            'passing_marks'
                        ] ?? 0
                    );


                $start =
                    $exam[
                        'start_datetime'
                    ];


                $end =
                    $exam[
                        'end_datetime'
                    ];


                $state =
                    (string) $exam[
                        'state'
                    ];


                $stateLabel =
                    live_state_label(
                        $state
                    );


                $stateIcon =
                    live_state_icon(
                        $state
                    );


                $stateBadgeClass =
                    live_state_badge_class(
                        $state
                    );


                $latestResult =
                    $exam[
                        'latest_result'
                    ] ?? null;


                $activeAttempt =
                    $exam[
                        'active_attempt'
                    ] ?? null;


                $examReady =
                    (bool) $exam[
                        'exam_ready'
                    ];


                $description =
                    trim(
                        (string) (
                            $exam[
                                'description'
                            ] ?? ''
                        )
                    );


                if (
                    $description === ''
                ) {

                    $description =
                        'Join this scheduled premium examination and demonstrate your preparation.';

                }


                $cardNumber =
                    str_pad(
                        (string) (
                            $index + 1
                        ),
                        2,
                        '0',
                        STR_PAD_LEFT
                    );


                $startText =
                    $start !== null
                        ? $start->format(
                            'd M Y, h:i A'
                        )
                        : 'Available now';


                $endText =
                    $end !== null
                        ? $end->format(
                            'd M Y, h:i A'
                        )
                        : 'No fixed end time';


                $accessGranted =
                    $hasActiveSubscription;


                ?>


                <article
                    class="
                        live-exam-card
                    "
                    data-exam-id="<?= $examId; ?>"
                >


                    <!-- =================================================
                         CARD TOP
                    ================================================== -->

                    <div
                        class="
                            live-card-top
                        "
                    >


                        <span
                            class="
                                live-card-number
                            "
                        >

                            <?= live_escape(
                                $cardNumber
                            ); ?>

                        </span>


                        <div
                            class="
                                live-card-badges
                            "
                        >


                            <span
                                class="
                                    live-card-badge
                                    <?= $stateBadgeClass; ?>
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        <?= live_escape(
                                            $stateIcon
                                        ); ?>
                                    "
                                ></i>

                                <?= live_escape(
                                    $stateLabel
                                ); ?>

                            </span>


                            <span
                                class="
                                    live-card-badge
                                    live-badge-premium
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-lock
                                    "
                                ></i>

                                Premium

                            </span>


                        </div>


                    </div>


                    <!-- =================================================
                         ICON
                    ================================================== -->

                    <div
                        class="
                            live-card-icon
                            <?= $state === 'live'
                                ? 'live'
                                : '' ?>
                        "
                    >

                        <i
                            class="
                                fa-solid
                                <?= $state === 'live'
                                    ? 'fa-tower-broadcast'
                                    : 'fa-file-pen' ?>
                            "
                        ></i>

                    </div>


                    <!-- =================================================
                         CONTENT
                    ================================================== -->

                    <div
                        class="
                            live-card-content
                        "
                    >


                        <div
                            class="
                                live-subject-label
                            "
                        >

                            <?= live_escape(
                                $exam[
                                    'subject_name'
                                ]
                            ); ?>


                            <?php if (
                                !empty(
                                    $exam[
                                        'subject_code'
                                    ]
                                )
                            ): ?>

                                <small>

                                    •
                                    <?= live_escape(
                                        $exam[
                                            'subject_code'
                                        ]
                                    ); ?>

                                </small>

                            <?php endif; ?>


                        </div>


                        <h3
                            class="
                                live-card-title
                            "
                        >

                            <?= live_escape(
                                $exam[
                                    'title'
                                ]
                            ); ?>

                        </h3>


                        <p
                            class="
                                live-card-description
                            "
                        >

                            <?= live_escape(
                                $description
                            ); ?>

                        </p>


                    </div>


                    <!-- =================================================
                         META
                    ================================================== -->

                    <div
                        class="
                            live-meta
                        "
                    >


                        <div
                            class="
                                live-meta-item
                            "
                        >

                            <i
                                class="
                                    fa-regular
                                    fa-clock
                                "
                            ></i>


                            <span>
                                Duration
                            </span>


                            <strong>

                                <?= $duration; ?>

                                min

                            </strong>

                        </div>


                        <div
                            class="
                                live-meta-item
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-list-check
                                "
                            ></i>


                            <span>
                                Questions
                            </span>


                            <strong>

                                <?= $questionCount; ?>

                            </strong>

                        </div>


                        <div
                            class="
                                live-meta-item
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-star
                                "
                            ></i>


                            <span>
                                Total Marks
                            </span>


                            <strong>

                                <?= live_number(
                                    $totalMarks
                                ); ?>

                            </strong>

                        </div>


                    </div>


                    <!-- =================================================
                         SCHEDULE
                    ================================================== -->

                    <div
                        class="
                            live-schedule
                        "
                    >


                        <div
                            class="
                                live-schedule-row
                            "
                        >

                            <span>

                                <i
                                    class="
                                        fa-regular
                                        fa-calendar
                                    "
                                ></i>

                                Start

                            </span>


                            <strong>

                                <?= live_escape(
                                    $startText
                                ); ?>

                            </strong>

                        </div>


                        <div
                            class="
                                live-schedule-row
                            "
                        >

                            <span>

                                <i
                                    class="
                                        fa-regular
                                        fa-calendar-check
                                    "
                                ></i>

                                End

                            </span>


                            <strong>

                                <?= live_escape(
                                    $endText
                                ); ?>

                            </strong>

                        </div>


                    </div>


                    <!-- =================================================
                         ACCESS
                    ================================================== -->

                    <div
                        class="
                            live-access
                        "
                    >

                        <div
                            class="
                                live-access-row
                            "
                        >

                            <span
                                class="
                                    live-access-label
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-shield-halved
                                    "
                                ></i>

                                Subscription Access

                            </span>


                            <span
                                class="
                                    live-access-value
                                    <?= $accessGranted
                                        ? 'granted'
                                        : 'locked' ?>
                                "
                            >

                                <?= $accessGranted
                                    ? 'Verified'
                                    : 'Required'
                                ?>

                            </span>

                        </div>


                    </div>


                    <!-- =================================================
                         RESULT
                    ================================================== -->

                    <?php if (
                        $latestResult
                    ): ?>


                        <div
                            class="
                                live-result
                            "
                        >


                            <div
                                class="
                                    live-result-row
                                "
                            >


                                <span
                                    class="
                                        live-result-label
                                    "
                                >

                                    Latest Result

                                </span>


                                <span
                                    class="
                                        live-result-score
                                    "
                                >

                                    <?= live_number(
                                        $latestResult[
                                            'obtained_marks'
                                        ] ?? 0
                                    ); ?>

                                    /

                                    <?= live_number(
                                        $latestResult[
                                            'total_marks'
                                        ] ?? $totalMarks
                                    ); ?>

                                    ·

                                    <?= live_number(
                                        $latestResult[
                                            'percentage'
                                        ] ?? 0
                                    ); ?>%

                                </span>


                                <span
                                    class="
                                        live-result-grade
                                    "
                                >

                                    <?= live_escape(
                                        $latestResult[
                                            'grade'
                                        ] ?? '-'
                                    ); ?>

                                </span>


                            </div>


                        </div>


                    <?php endif; ?>


                    <!-- =================================================
                         ACTION
                    ================================================== -->

                    <div
                        class="
                            live-card-action
                        "
                    >


                        <?php if (
                            $state === 'upcoming'
                        ): ?>


                            <div
                                class="
                                    live-ended-btn
                                "
                            >

                                <i
                                    class="
                                        fa-regular
                                        fa-calendar
                                    "
                                ></i>

                                Starts Soon

                            </div>


                            <?php if (
                                $latestResult
                            ): ?>


                                <a
                                    href="result.php?id=<?= (int) $latestResult[
                                        'id'
                                    ]; ?>"
                                    class="
                                        live-secondary-btn
                                    "
                                    title="View latest result"
                                    aria-label="View latest result"
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-chart-column
                                        "
                                    ></i>

                                </a>


                            <?php endif; ?>


                        <?php elseif (
                            $state === 'completed'
                        ): ?>


                            <div
                                class="
                                    live-ended-btn
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-circle-check
                                    "
                                ></i>

                                Exam Ended

                            </div>


                            <?php if (
                                $latestResult
                            ): ?>


                                <a
                                    href="result.php?id=<?= (int) $latestResult[
                                        'id'
                                    ]; ?>"
                                    class="
                                        live-secondary-btn
                                    "
                                    title="View result"
                                    aria-label="View result"
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-chart-column
                                        "
                                    ></i>

                                </a>


                            <?php endif; ?>


                        <?php elseif (
                            $state === 'cancelled'
                        ): ?>


                            <div
                                class="
                                    live-ended-btn
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-ban
                                    "
                                ></i>

                                Cancelled

                            </div>


                        <?php elseif (
                            !$examReady
                        ): ?>


                            <div
                                class="
                                    live-ended-btn
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-triangle-exclamation
                                    "
                                ></i>

                                Exam Not Ready

                            </div>


                        <?php elseif (
                            !$hasActiveSubscription
                        ): ?>


                            <a
                                href="subscriptions.php"
                                class="
                                    live-lock-btn
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-lock
                                    "
                                ></i>

                                Subscription Required

                            </a>


                        <?php elseif (
                            $activeAttempt
                        ): ?>


                            <a
                                href="start_exam.php?id=<?= $examId; ?>"
                                class="
                                    live-primary-btn
                                    olive
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-play
                                    "
                                ></i>

                                Resume Live Exam

                                <i
                                    class="
                                        fa-solid
                                        fa-arrow-right
                                    "
                                ></i>

                            </a>


                            <?php if (
                                $latestResult
                            ): ?>


                                <a
                                    href="result.php?id=<?= (int) $latestResult[
                                        'id'
                                    ]; ?>"
                                    class="
                                        live-secondary-btn
                                    "
                                    title="View latest result"
                                    aria-label="View latest result"
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-chart-column
                                        "
                                    ></i>

                                </a>


                            <?php endif; ?>


                        <?php else: ?>


                            <a
                                href="start_exam.php?id=<?= $examId; ?>"
                                class="
                                    live-primary-btn
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-tower-broadcast
                                    "
                                ></i>

                                Join Live Exam

                                <i
                                    class="
                                        fa-solid
                                        fa-arrow-right
                                    "
                                ></i>

                            </a>


                            <?php if (
                                $latestResult
                            ): ?>


                                <a
                                    href="result.php?id=<?= (int) $latestResult[
                                        'id'
                                    ]; ?>"
                                    class="
                                        live-secondary-btn
                                    "
                                    title="View latest result"
                                    aria-label="View latest result"
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-chart-column
                                        "
                                    ></i>

                                </a>


                            <?php endif; ?>


                        <?php endif; ?>


                    </div>


                </article>


            <?php endforeach; ?>


        </section>


    <?php else: ?>


        <!-- =================================================
             EMPTY STATE
        ================================================== -->

        <section
            class="
                live-empty
            "
        >


            <div
                class="
                    live-empty-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-tower-broadcast
                    "
                ></i>

            </div>


            <div
                class="
                    live-empty-kicker
                "
            >

                <?= $filterActive
                    ? 'NO MATCHING LIVE EXAMS'
                    : 'LIVE EXAM LIBRARY EMPTY'
                ?>

            </div>


            <h3>

                <?= $filterActive
                    ? 'No matching live examinations found.'
                    : 'No live examinations are scheduled yet.'
                ?>

            </h3>


            <p>

                <?php if (
                    $filterActive
                ): ?>

                    Try another search term,
                    select a different subject,
                    or clear your current filters.

                <?php else: ?>

                    New scheduled live examinations
                    published by Admin or Teacher will
                    automatically appear here.

                <?php endif; ?>


            </p>


            <?php if (
                !$hasActiveSubscription
            ): ?>


                <a
                    href="subscriptions.php"
                    class="
                        live-empty-btn
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-gem
                        "
                    ></i>

                    View Subscription

                </a>


            <?php elseif (
                $filterActive
            ): ?>


                <a
                    href="live_exams.php"
                    class="
                        live-empty-btn
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-rotate-left
                        "
                    ></i>

                    Show All Exams

                </a>


            <?php endif; ?>


        </section>


    <?php endif; ?>


    <!-- =====================================================
         INFORMATION STRIP
    ====================================================== -->

    <section
        class="
            live-info-strip
        "
    >


        <div
            class="
                live-info
            "
        >


            <div
                class="
                    live-info-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-lock
                    "
                ></i>

            </div>


            <div>

                <strong>
                    Subscription Protected
                </strong>

                <small>

                    Only students with a valid
                    active subscription can join
                    premium live examinations.

                </small>

            </div>


        </div>


        <div
            class="
                live-info
            "
        >


            <div
                class="
                    live-info-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-clock
                    "
                ></i>

            </div>


            <div>

                <strong>
                    Scheduled Access
                </strong>

                <small>

                    Live examinations become
                    joinable only during their
                    configured examination window.

                </small>

            </div>


        </div>


        <div
            class="
                live-info
            "
        >


            <div
                class="
                    live-info-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-chart-line
                    "
                ></i>

            </div>


            <div>

                <strong>
                    Same Exam Experience
                </strong>

                <small>

                    Live exams use the same premium
                    CBT examination interface as
                    your practice exams.

                </small>

            </div>


        </div>


    </section>


</main>


</body>

</html>