<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {
    header('Location: ../auth/login.php');
    exit;
}

$studentId =
    (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| DEFAULT ANALYTICS
|--------------------------------------------------------------------------
*/

$analytics = [

    'total_results' => 0,

    'average_percentage' => 0,

    'best_percentage' => 0,

    'pass_rate' => 0,

    'total_questions' => 0,

    'total_attempted' => 0,

    'total_correct' => 0,

    'total_wrong' => 0,

    'total_unanswered' => 0,

    'accuracy_percentage' => 0,

    'average_time_minutes' => 0

];


$recentPerformance = [];

$subjectPerformance = [];


/*
|--------------------------------------------------------------------------
| OVERALL PERFORMANCE
|--------------------------------------------------------------------------
*/

try {

    $overviewStatement =
        $conn->prepare("
            SELECT

                COUNT(*) AS total_results,

                COALESCE(
                    AVG(
                        r.percentage
                    ),
                    0
                ) AS average_percentage,

                COALESCE(
                    MAX(
                        r.percentage
                    ),
                    0
                ) AS best_percentage,

                COALESCE(
                    (
                        SUM(
                            r.correct_answers
                        ) /
                        NULLIF(
                            SUM(
                                r.attempted_questions
                            ),
                            0
                        )
                    ) * 100,
                    0
                ) AS accuracy_percentage,

                COALESCE(
                    AVG(
                        CASE
                            WHEN
                                ea.started_at IS NOT NULL
                                AND ea.submitted_at IS NOT NULL

                            THEN TIMESTAMPDIFF(
                                MINUTE,
                                ea.started_at,
                                ea.submitted_at
                            )

                            ELSE NULL
                        END
                    ),
                    0
                ) AS average_time_minutes,

                COALESCE(
                    (
                        SUM(
                            CASE
                                WHEN
                                    r.result_status = 'Pass'
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
                    SUM(
                        r.total_questions
                    ),
                    0
                ) AS total_questions,

                COALESCE(
                    SUM(
                        r.attempted_questions
                    ),
                    0
                ) AS total_attempted,

                COALESCE(
                    SUM(
                        r.correct_answers
                    ),
                    0
                ) AS total_correct,

                COALESCE(
                    SUM(
                        r.wrong_answers
                    ),
                    0
                ) AS total_wrong,

                COALESCE(
                    SUM(
                        r.unanswered_questions
                    ),
                    0
                ) AS total_unanswered

            FROM results r

            INNER JOIN exam_attempts ea
                ON ea.id = r.attempt_id

            WHERE
                r.student_id = ?
        "
        );


    $overviewStatement->execute([
        $studentId
    ]);


    $overview =
        $overviewStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        is_array(
            $overview
        )
    ) {

        foreach (
            $analytics
            as $key => $value
        ) {

            if (
                array_key_exists(
                    $key,
                    $overview
                )
                &&
                is_numeric(
                    $overview[$key]
                )
            ) {

                $analytics[$key] =
                    str_contains(
                        (string)$overview[$key],
                        '.'
                    )
                        ? (float)$overview[$key]
                        : (int)$overview[$key];

            }

        }

    }


} catch (
    Throwable $exception
) {

    error_log(
        'Student performance overview failed: ' .
        $exception->getMessage()
    );

}


/*
|--------------------------------------------------------------------------
| RECENT PERFORMANCE
|--------------------------------------------------------------------------
*/

try {

    $recentStatement =
        $conn->prepare("
            SELECT

                r.id,

                r.percentage,

                r.result_status,

                r.correct_answers,

                r.wrong_answers,

                r.unanswered_questions,

                r.created_at,

                e.title AS exam_title

            FROM results r

            INNER JOIN exams e
                ON e.id = r.exam_id

            WHERE
                r.student_id = ?

            ORDER BY

                r.created_at ASC,

                r.id ASC

            LIMIT 10
        "
        );


    $recentStatement->execute([
        $studentId
    ]);


    $recentPerformance =
        $recentStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (
    Throwable $exception
) {

    error_log(
        'Student recent performance failed: ' .
        $exception->getMessage()
    );

    $recentPerformance = [];

}


/*
|--------------------------------------------------------------------------
| SUBJECT PERFORMANCE
|--------------------------------------------------------------------------
*/

try {

    $subjectStatement =
        $conn->prepare("
            SELECT

                COALESCE(
                    s.name,
                    'Uncategorized'
                ) AS subject_name,

                COUNT(
                    r.id
                ) AS attempts,

                ROUND(
                    AVG(
                        r.percentage
                    ),
                    2
                ) AS average_percentage,

                ROUND(
                    MAX(
                        r.percentage
                    ),
                    2
                ) AS best_percentage,

                SUM(
                    r.correct_answers
                ) AS correct_answers,

                SUM(
                    r.wrong_answers
                ) AS wrong_answers,

                SUM(
                    r.attempted_questions
                ) AS attempted_questions,

                COALESCE(
                    (
                        SUM(
                            r.correct_answers
                        ) /
                        NULLIF(
                            SUM(
                                r.attempted_questions
                            ),
                            0
                        )
                    ) * 100,
                    0
                ) AS accuracy_percentage

            FROM results r

            INNER JOIN exams e
                ON e.id = r.exam_id

            LEFT JOIN subjects s
                ON s.id = e.subject_id

            WHERE
                r.student_id = ?

            GROUP BY

                e.subject_id,

                s.name

            ORDER BY

                average_percentage DESC,

                attempts DESC

            LIMIT 10
        "
        );


    $subjectStatement->execute([
        $studentId
    ]);


    $subjectPerformance =
        $subjectStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (
    Throwable $exception
) {

    error_log(
        'Student subject performance failed: ' .
        $exception->getMessage()
    );

    $subjectPerformance = [];

}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function performance_escape(
    ?string $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );

}


function performance_number(
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


/*
|--------------------------------------------------------------------------
| ANALYTICS VALUES
|--------------------------------------------------------------------------
*/

$totalResults =
    (int)$analytics[
        'total_results'
    ];


$averageScore =
    (float)$analytics[
        'average_percentage'
    ];


$bestScore =
    (float)$analytics[
        'best_percentage'
    ];


$passRate =
    (float)$analytics[
        'pass_rate'
    ];


$accuracy =
    (float)$analytics[
        'accuracy_percentage'
    ];


$averageTime =
    (float)$analytics[
        'average_time_minutes'
    ];


$totalQuestions =
    (int)$analytics[
        'total_questions'
    ];


$totalAttempted =
    (int)$analytics[
        'total_attempted'
    ];


$totalCorrect =
    (int)$analytics[
        'total_correct'
    ];


$totalWrong =
    (int)$analytics[
        'total_wrong'
    ];


$totalUnanswered =
    (int)$analytics[
        'total_unanswered'
    ];


/*
|--------------------------------------------------------------------------
| DERIVED VALUES
|--------------------------------------------------------------------------
*/

$attemptedRate =
    $totalQuestions > 0

        ? round(
            (
                $totalAttempted /
                $totalQuestions
            ) * 100,
            1
        )

        : 0;


$correctRate =
    $totalAttempted > 0

        ? round(
            (
                $totalCorrect /
                $totalAttempted
            ) * 100,
            1
        )

        : 0;


$wrongRate =
    $totalAttempted > 0

        ? round(
            (
                $totalWrong /
                $totalAttempted
            ) * 100,
            1
        )

        : 0;


$unansweredRate =
    $totalQuestions > 0

        ? round(
            (
                $totalUnanswered /
                $totalQuestions
            ) * 100,
            1
        )

        : 0;


/*
|--------------------------------------------------------------------------
| LATEST / PREVIOUS
|--------------------------------------------------------------------------
*/

$recentReverse =
    array_reverse(
        $recentPerformance
    );


$latestResult =
    $recentReverse[0]
    ?? null;


$previousResult =
    $recentReverse[1]
    ?? null;


$trendDelta =
    null;


if (
    $latestResult &&
    $previousResult
) {

    $trendDelta =
        round(
            (float)$latestResult[
                'percentage'
            ]
            -
            (float)$previousResult[
                'percentage'
            ],
            2
        );

}


/*
|--------------------------------------------------------------------------
| SUBJECT INSIGHTS
|--------------------------------------------------------------------------
*/

$strongestSubject =
    $subjectPerformance[0]
    ?? null;


$improvementSubject =
    null;


if (
    !empty(
        $subjectPerformance
    )
) {

    $sortedSubjects =
        $subjectPerformance;


    usort(
        $sortedSubjects,
        static function (
            array $a,
            array $b
        ): int {

            return
                (float)$a[
                    'average_percentage'
                ]
                <=>
                (float)$b[
                    'average_percentage'
                ];

        }
    );


    $improvementSubject =
        $sortedSubjects[0]
        ?? null;

}


/*
|--------------------------------------------------------------------------
| CHART DATA
|--------------------------------------------------------------------------
*/

$scoreLabels = [];

$scoreValues = [];

$scoreTitles = [];


foreach (
    $recentPerformance
    as $index => $recent
) {

    $scoreLabels[] =
        'Exam ' .
        (
            $index + 1
        );


    $scoreValues[] =
        (float)(
            $recent[
                'percentage'
            ]
            ??
            0
        );


    $scoreTitles[] =
        (string)(
            $recent[
                'exam_title'
            ]
            ??
            'Exam'
        );

}


$chartPayload = [

    'labels' =>
        $scoreLabels,

    'values' =>
        $scoreValues,

    'titles' =>
        $scoreTitles,

    'correct' =>
        $totalCorrect,

    'wrong' =>
        $totalWrong,

    'unanswered' =>
        $totalUnanswered

];

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="
            width=device-width,
            initial-scale=1.0
        "
    >


    <meta
        name="theme-color"
        content="#f5f5dc"
    >


    <title>
        Performance | ExamSphere
    </title>


    <link
        rel="stylesheet"
        href="../assets/css/main.css"
    >


    <link
        rel="stylesheet"
        href="
            assets/css/student-performance.css
        "
    >


    <link
        rel="stylesheet"
        href="
            https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css
        "
    >


    <script
        src="
            https://cdn.jsdelivr.net/npm/chart.js
        "
    ></script>

</head>


<body>


<?php include 'includes/navbar.php'; ?>


<main
    class="
        student-performance-page
    "
>


<div
    class="
        performance-container
    "
>


<?php if (
    $totalResults > 0
): ?>


<!-- =====================================================
     HERO
====================================================== -->

<section
    class="
        performance-hero
    "
>


<div
    class="
        performance-hero-content
    "
>


<div
    class="
        performance-eyebrow
    "
>

    <span
        class="
            performance-eyebrow-dot
        "
    ></span>

    PERFORMANCE ANALYTICS

</div>


<h1>

    Know your performance.

    <span>
        Own your progress.
    </span>

</h1>


<p>

    Turn every completed examination into a
    clearer picture of your preparation,
    accuracy, consistency and growth.

</p>


<div
    class="
        performance-hero-actions
    "
>


<a
    href="results.php"
    class="
        performance-primary-btn
    "
>

    <i
        class="
            fa-solid
            fa-chart-column
        "
    ></i>

    View Results

</a>


<a
    href="practice_exams.php"
    class="
        performance-secondary-btn
    "
>

    <i
        class="
            fa-solid
            fa-file-pen
        "
    ></i>

    Practice Again

</a>


</div>


</div>


<div
    class="
        performance-hero-score
    "
>


<div
    class="
        hero-score-top
    "
>

    <span>
        AVERAGE SCORE
    </span>


    <i
        class="
            fa-solid
            fa-chart-line
        "
    ></i>

</div>


<strong>

    <?= performance_number(
        $averageScore
    ) ?>%

</strong>


<div
    class="
        hero-score-meta
    "
>

<?php if (
    $trendDelta !== null
): ?>

    <span
        class="
            trend-pill
            <?= $trendDelta >= 0
                ? 'positive'
                : 'negative' ?>"
    >

        <i
            class="
                fa-solid
                <?= $trendDelta >= 0
                    ? 'fa-arrow-up'
                    : 'fa-arrow-down' ?>"
        ></i>

        <?= performance_number(
            abs(
                $trendDelta
            )
        ) ?>%

    </span>


    <span>
        vs previous exam
    </span>


<?php else: ?>

    <span>
        Based on completed results
    </span>

<?php endif; ?>

</div>


<div
    class="
        hero-score-track
    "
>

    <span
        style="
            width:
            <?= max(
                0,
                min(
                    100,
                    $averageScore
                )
            ) ?>%;
        "
    ></span>

</div>


</div>


</section>


<!-- =====================================================
     KPI
====================================================== -->

<section
    class="
        performance-kpi-grid
    "
>


<article
    class="
        performance-kpi
    "
>

    <div
        class="
            kpi-icon
            kpi-brown
        "
    >

        <i
            class="
                fa-solid
                fa-file-circle-check
            "
        ></i>

    </div>


    <div>

        <span>
            Exams completed
        </span>


        <strong>
            <?= $totalResults ?>
        </strong>


        <small>
            verified results
        </small>

    </div>

</article>


<article
    class="
        performance-kpi
    "
>

    <div
        class="
            kpi-icon
            kpi-green
        "
    >

        <i
            class="
                fa-solid
                fa-bullseye
            "
        ></i>

    </div>


    <div>

        <span>
            Accuracy
        </span>


        <strong>

            <?= performance_number(
                $accuracy
            ) ?>%

        </strong>


        <small>
            of attempted questions
        </small>

    </div>

</article>


<article
    class="
        performance-kpi
    "
>

    <div
        class="
            kpi-icon
            kpi-gold
        "
    >

        <i
            class="
                fa-solid
                fa-trophy
            "
        ></i>

    </div>


    <div>

        <span>
            Best score
        </span>


        <strong>

            <?= performance_number(
                $bestScore
            ) ?>%

        </strong>


        <small>
            personal best
        </small>

    </div>

</article>


<article
    class="
        performance-kpi
    "
>

    <div
        class="
            kpi-icon
            kpi-purple
        "
    >

        <i
            class="
                fa-solid
                fa-circle-check
            "
        ></i>

    </div>


    <div>

        <span>
            Pass rate
        </span>


        <strong>

            <?= performance_number(
                $passRate
            ) ?>%

        </strong>


        <small>
            successful exams
        </small>

    </div>

</article>


</section>


<!-- =====================================================
     MAIN CHARTS
====================================================== -->

<section
    class="
        performance-overview-grid
    "
>


<article
    class="
        performance-panel
        performance-main-chart
    "
>


<div
    class="
        performance-panel-head
    "
>


<div>

    <span>
        RECENT TREND
    </span>


    <h2>
        Score progression
    </h2>


    <p>
        See how your exam scores have moved over time.
    </p>

</div>


<div
    class="
        panel-head-icon
    "
>

    <i
        class="
            fa-solid
            fa-arrow-trend-up
        "
    ></i>

</div>


</div>


<div
    class="
        score-summary-strip
    "
>


<div>

    <span>
        Latest
    </span>


    <strong>

        <?= $latestResult
            ? performance_number(
                (float)$latestResult[
                    'percentage'
                ]
            ) . '%'
            : '—' ?>

    </strong>

</div>


<div>

    <span>
        Best
    </span>


    <strong>

        <?= performance_number(
            $bestScore
        ) ?>%

    </strong>

</div>


<div>

    <span>
        Average
    </span>


    <strong>

        <?= performance_number(
            $averageScore
        ) ?>%

    </strong>

</div>


</div>


<div
    class="
        performance-line-chart
    "
>

    <canvas
        id="scoreProgressChart"
    ></canvas>

</div>


</article>


<article
    class="
        performance-panel
        performance-quality-card
    "
>


<div
    class="
        performance-panel-head
    "
>


<div>

    <span>
        ANSWER QUALITY
    </span>


    <h2>
        How you answered
    </h2>


    <p>
        Your complete question-level breakdown.
    </p>

</div>


<div
    class="
        panel-head-icon
    "
>

    <i
        class="
            fa-solid
            fa-chart-pie
        "
    ></i>

</div>


</div>


<div
    class="
        quality-chart-area
    "
>


<div
    class="
        quality-chart
    "
>

    <canvas
        id="answerAnalysisChart"
    ></canvas>


    <div
        class="
            quality-center
        "
    >

        <strong>

            <?= performance_number(
                $accuracy
            ) ?>%

        </strong>


        <span>
            accuracy
        </span>

    </div>

</div>


<div
    class="
        quality-legend
    "
>


<div
    class="
        quality-row
    "
>

    <span>

        <i
            class="
                quality-dot
                quality-correct
            "
        ></i>

        Correct

    </span>


    <strong>
        <?= $totalCorrect ?>
    </strong>

</div>


<div
    class="
        quality-row
    "
>

    <span>

        <i
            class="
                quality-dot
                quality-wrong
            "
        ></i>

        Wrong

    </span>


    <strong>
        <?= $totalWrong ?>
    </strong>

</div>


<div
    class="
        quality-row
    "
>

    <span>

        <i
            class="
                quality-dot
                quality-unanswered
            "
        ></i>

        Unanswered

    </span>


    <strong>
        <?= $totalUnanswered ?>
    </strong>

</div>


</div>


</div>


</article>


</section>


<!-- =====================================================
     INSIGHTS
====================================================== -->

<section
    class="
        insight-grid
    "
>


<article
    class="
        insight-card
        insight-green
    "
>


<div
    class="
        insight-icon
    "
>

    <i
        class="
            fa-solid
            fa-bolt
        "
    ></i>

</div>


<div>

    <span>
        YOUR STRENGTH
    </span>


    <strong>

        <?= $strongestSubject
            ? performance_escape(
                $strongestSubject[
                    'subject_name'
                ]
            )
            : 'Keep practising' ?>

    </strong>


    <p>

<?php if (
    $strongestSubject
): ?>

    <?= performance_number(
        (float)$strongestSubject[
            'average_percentage'
        ]
    ) ?>%

    average score across

    <?= (int)$strongestSubject[
        'attempts'
    ] ?>

    <?= (int)$strongestSubject[
        'attempts'
    ] === 1
        ? 'exam'
        : 'exams' ?>.

<?php else: ?>

    Complete more exams to unlock
    subject insights.

<?php endif; ?>

    </p>

</div>


</article>


<article
    class="
        insight-card
        insight-brown
    "
>


<div
    class="
        insight-icon
    "
>

    <i
        class="
            fa-solid
            fa-compass
        "
    ></i>

</div>


<div>

    <span>
        FOCUS NEXT
    </span>


    <strong>

        <?= $improvementSubject
            ? performance_escape(
                $improvementSubject[
                    'subject_name'
                ]
            )
            : 'Build your data' ?>

    </strong>


    <p>

<?php if (
    $improvementSubject
): ?>

    Current average is

    <?= performance_number(
        (float)$improvementSubject[
            'average_percentage'
        ]
    ) ?>%.

    A focused practice cycle
    can lift this area.

<?php else: ?>

    More completed exams will make
    improvement areas clearer.

<?php endif; ?>

    </p>

</div>


</article>


<article
    class="
        insight-card
        insight-gold
    "
>


<div
    class="
        insight-icon
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

    <span>
        PACE
    </span>


    <strong>

        <?= $averageTime > 0
            ? performance_number(
                $averageTime
            ) . ' min'
            : '—' ?>

    </strong>


    <p>
        Average time spent per completed examination.
    </p>

</div>


</article>


</section>


<!-- =====================================================
     SUBJECT ANALYSIS
====================================================== -->

<section
    class="
        performance-panel
        subject-panel
    "
>


<div
    class="
        performance-panel-head
    "
>


<div>

    <span>
        SUBJECT ANALYSIS
    </span>


    <h2>
        Where you perform best
    </h2>


    <p>
        Compare your average score and accuracy across subjects.
    </p>

</div>


<div
    class="
        panel-head-icon
    "
>

    <i
        class="
            fa-solid
            fa-book-open
        "
    ></i>

</div>


</div>


<?php if (
    !empty(
        $subjectPerformance
    )
): ?>


<div
    class="
        subject-list
    "
>


<?php foreach (
    $subjectPerformance
    as $subject
): ?>


<?php

$subjectAverage =
    (float)(
        $subject[
            'average_percentage'
        ]
        ??
        0
    );


$subjectBest =
    (float)(
        $subject[
            'best_percentage'
        ]
        ??
        0
    );


$subjectAccuracy =
    (float)(
        $subject[
            'accuracy_percentage'
        ]
        ??
        0
    );


$subjectAttempts =
    (int)(
        $subject[
            'attempts'
        ]
        ??
        0
    );

?>


<div
    class="
        subject-row
    "
>


<div
    class="
        subject-title
    "
>


<div
    class="
        subject-symbol
    "
>

    <i
        class="
            fa-solid
            fa-book
        "
    ></i>

</div>


<div>

    <strong>

        <?= performance_escape(
            $subject[
                'subject_name'
            ]
        ) ?>

    </strong>


    <small>

        <?= $subjectAttempts ?>

        <?= $subjectAttempts === 1
            ? 'exam'
            : 'exams' ?>

    </small>

</div>


</div>


<div
    class="
        subject-progress
    "
>


<div
    class="
        subject-progress-top
    "
>

    <span>
        Average score
    </span>


    <strong>

        <?= performance_number(
            $subjectAverage
        ) ?>%

    </strong>

</div>


<div
    class="
        subject-progress-track
    "
>

    <span
        style="
            width:
            <?= max(
                0,
                min(
                    100,
                    $subjectAverage
                )
            ) ?>%;
        "
    ></span>

</div>


</div>


<div
    class="
        subject-mini-stats
    "
>


<div>

    <small>
        Best
    </small>


    <strong>

        <?= performance_number(
            $subjectBest
        ) ?>%

    </strong>

</div>


<div>

    <small>
        Accuracy
    </small>


    <strong>

        <?= performance_number(
            $subjectAccuracy
        ) ?>%

    </strong>

</div>


</div>


</div>


<?php endforeach; ?>


</div>


<?php else: ?>


<div
    class="
        panel-empty-small
    "
>

    Subject performance will appear after
    you complete examinations.

</div>


<?php endif; ?>


</section>


<!-- =====================================================
     RECENT PERFORMANCE
====================================================== -->

<section
    class="
        performance-panel
        recent-panel
    "
>


<div
    class="
        performance-panel-head
    "
>


<div>

    <span>
        EXAM HISTORY
    </span>


    <h2>
        Recent performance
    </h2>


    <p>
        Your latest completed examination results.
    </p>

</div>


<a
    href="results.php"
    class="
        panel-link
    "
>

    View all

    <i
        class="
            fa-solid
            fa-arrow-right
        "
    ></i>

</a>


</div>


<div
    class="
        recent-table-wrap
    "
>


<table
    class="
        recent-table
    "
>


<thead>

<tr>

    <th>
        Examination
    </th>


    <th>
        Score
    </th>


    <th>
        Correct
    </th>


    <th>
        Wrong
    </th>


    <th>
        Date
    </th>

</tr>

</thead>


<tbody>


<?php foreach (
    $recentReverse
    as $recent
): ?>


<tr>


<td>

    <strong>

        <?= performance_escape(
            $recent[
                'exam_title'
            ]
        ) ?>

    </strong>

</td>


<td>

    <span
        class="
            score-badge
        "
    >

        <?= performance_number(
            (float)$recent[
                'percentage'
            ]
        ) ?>%

    </span>

</td>


<td>

    <span
        class="
            count-badge
            count-correct
        "
    >

        <?= (int)$recent[
            'correct_answers'
        ] ?>

    </span>

</td>


<td>

    <span
        class="
            count-badge
            count-wrong
        "
    >

        <?= (int)$recent[
            'wrong_answers'
        ] ?>

    </span>

</td>


<td>

<?php

try {

    echo performance_escape(

        (
            new DateTimeImmutable(
                (string)$recent[
                    'created_at'
                ]
            )
        )->format(
            'd M Y'
        )

    );

} catch (
    Throwable
) {

    echo '—';

}

?>

</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


</section>


<?php else: ?>


<!-- =====================================================
     EMPTY STATE
====================================================== -->

<section
    class="
        performance-empty
    "
>


<div
    class="
        empty-icon
    "
>

    <i
        class="
            fa-solid
            fa-chart-line
        "
    ></i>

</div>


<span>
    PERFORMANCE STARTS HERE
</span>


<h1>
    Complete your first exam to unlock analytics.
</h1>


<p>

    Once you complete an examination,
    ExamSphere will calculate your score,
    accuracy, pass rate and subject
    performance automatically.

</p>


<a
    href="practice_exams.php"
    class="
        performance-primary-btn
    "
>

    Start a practice exam

    <i
        class="
            fa-solid
            fa-arrow-right
        "
    ></i>

</a>


</section>


<?php endif; ?>


</div>


</main>


<?php if (
    $totalResults > 0
): ?>


<script>

window.EXAMSPHERE_PERFORMANCE =

<?= json_encode(
    $chartPayload,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_INVALID_UTF8_SUBSTITUTE
) ?>;

</script>


<script
    src="
        assets/js/student-performance.js
    "
    defer
></script>


<?php endif; ?>


</body>

</html>