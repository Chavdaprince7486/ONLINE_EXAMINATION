<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';


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


$studentId =
    (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Default analytics
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
| Overall performance
|--------------------------------------------------------------------------
*/

try {

    $overviewStatement =
        $conn->prepare("
            SELECT

                COUNT(*) AS total_results,

                COALESCE(
                    AVG(r.percentage),
                    0
                ) AS average_percentage,

                COALESCE(
                    MAX(r.percentage),
                    0
                ) AS best_percentage,

                COALESCE(
                    (
                        SUM(
                            r.correct_answers
                        ) /
                        NULLIF(
                            SUM(r.attempted_questions),
                            0
                        )
                    ) * 100,
                    0
                ) AS accuracy_percentage,

                COALESCE(
                    AVG(
                        CASE
                            WHEN ea.started_at IS NOT NULL
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
                    SUM(total_questions),
                    0
                ) AS total_questions,

                COALESCE(
                    SUM(attempted_questions),
                    0
                ) AS total_attempted,

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

            FROM results r

            INNER JOIN exam_attempts ea
                ON ea.id = r.attempt_id

            WHERE r.student_id = ?
        ");


    $overviewStatement->execute([
        $studentId
    ]);


    $overview =
        $overviewStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if ($overview) {

        foreach ($analytics as $key => $value) {

            if (
                array_key_exists(
                    $key,
                    $overview
                )
            ) {

                $analytics[$key] =
                    is_numeric(
                        $overview[$key]
                    )
                        ? (
                            str_contains(
                                (string) $overview[$key],
                                '.'
                            )
                                ? (float) $overview[$key]
                                : (int) $overview[$key]
                        )
                        : $value;
            }
        }
    }


} catch (Throwable $exception) {

    error_log(
        'Student performance overview failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| Recent performance
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

            WHERE r.student_id = ?

            ORDER BY
                r.created_at ASC,
                r.id ASC

            LIMIT 10
        ");


    $recentStatement->execute([
        $studentId
    ]);


    $recentPerformance =
        $recentStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $exception) {

    error_log(
        'Student recent performance failed: ' .
        $exception->getMessage()
    );

    $recentPerformance = [];
}


/*
|--------------------------------------------------------------------------
| Subject performance
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
                        SUM(r.correct_answers) /
                        NULLIF(
                            SUM(r.attempted_questions),
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

            WHERE r.student_id = ?

            GROUP BY
                e.subject_id,
                s.name

            ORDER BY
                average_percentage DESC,
                attempts DESC

            LIMIT 10
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
        'Student subject performance failed: ' .
        $exception->getMessage()
    );

    $subjectPerformance = [];
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function performance_escape(
    ?string $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
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
        Performance | ExamSphere
    </title>


    <link
        rel="stylesheet"
        href="../assets/css/main.css"
    >


    <link
        rel="stylesheet"
        href="assets/css/student-performance.css"
    >


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >


    <script
        src="https://cdn.jsdelivr.net/npm/chart.js"
    ></script>

</head>


<body>

<?php include 'includes/navbar.php'; ?>


<main class="student-performance-page">

    <div class="container">


        <!-- =================================================
             HEADER
        ================================================== -->

        <div class="student-performance-header">

            <div>

                <span class="student-performance-kicker">

                    <i class="fa-solid fa-chart-line"></i>

                    PERFORMANCE ANALYTICS

                </span>


                <h1>
                    Understand your
                    <em>progress.</em>
                </h1>


                <p>
                    Use your completed exam history to understand
                    your accuracy, consistency and subject-level performance.
                </p>

            </div>


            <a
                href="dashboard.php"
                class="performance-back"
            >

                <i class="fa-solid fa-arrow-left"></i>

                Dashboard

            </a>

        </div>


        <?php if (
            (int) $analytics['total_results'] > 0
        ): ?>


            <!-- =================================================
                 KPI CARDS
            ================================================== -->

            <section class="performance-kpi-grid">


                <article class="performance-kpi">

                    <span class="performance-kpi-icon">

                        <i
                            class="fa-solid fa-file-circle-check"
                        ></i>

                    </span>


                    <div>

                        <small>
                            Exams completed
                        </small>

                        <strong>
                            <?= (int) (
                                $analytics[
                                    'total_results'
                                ]
                            ) ?>
                        </strong>

                    </div>

                </article>


                <article class="performance-kpi">

                    <span class="performance-kpi-icon">

                        <i
                            class="fa-solid fa-chart-line"
                        ></i>

                    </span>


                    <div>

                        <small>
                            Average score
                        </small>

                        <strong>
                            <?= performance_number(
                                (float) $analytics[
                                    'average_percentage'
                                ]
                            ) ?>%
                        </strong>

                    </div>

                </article>


                <article class="performance-kpi">

                    <span class="performance-kpi-icon">

                        <i
                            class="fa-solid fa-trophy"
                        ></i>

                    </span>


                    <div>

                        <small>
                            Best score
                        </small>

                        <strong>
                            <?= performance_number(
                                (float) $analytics[
                                    'best_percentage'
                                ]
                            ) ?>%
                        </strong>

                    </div>

                </article>


                <article class="performance-kpi">

                    <span class="performance-kpi-icon">

                        <i
                            class="fa-solid fa-circle-check"
                        ></i>

                    </span>


                    <div>

                        <small>
                            Pass rate
                        </small>

                        <strong>
                            <?= performance_number(
                                (float) $analytics[
                                    'pass_rate'
                                ]
                            ) ?>%
                        </strong>

                    </div>

                </article>

            </section>


            <!-- =================================================
                 CHART ROW
            ================================================== -->

            <section class="performance-chart-grid">


                <article class="performance-panel">

                    <div class="performance-panel-heading">

                        <div>

                            <span>
                                RECENT TREND
                            </span>

                            <h2>
                                Score progression
                            </h2>

                        </div>


                        <i
                            class="fa-solid fa-chart-line"
                        ></i>

                    </div>


                    <div
                        class="performance-line-chart"
                    >

                        <canvas
                            id="scoreProgressChart"
                        ></canvas>

                    </div>

                </article>


                <article class="performance-panel">

                    <div class="performance-panel-heading">

                        <div>

                            <span>
                                ANSWER QUALITY
                            </span>

                            <h2>
                                Overall answer analysis
                            </h2>

                        </div>


                        <i
                            class="fa-solid fa-chart-pie"
                        ></i>

                    </div>


                    <div class="performance-doughnut-wrap">

                        <div
                            class="performance-doughnut"
                        >

                            <canvas
                                id="answerAnalysisChart"
                            ></canvas>

                        </div>


                        <div
                            class="performance-answer-legend"
                        >

                            <div>

                                <span>
                                    <i class="correct-dot"></i>
                                    Correct
                                </span>

                                <strong>
                                    <?= (int) (
                                        $analytics[
                                            'total_correct'
                                        ]
                                    ) ?>
                                </strong>

                            </div>


                            <div>

                                <span>
                                    <i class="wrong-dot"></i>
                                    Wrong
                                </span>

                                <strong>
                                    <?= (int) (
                                        $analytics[
                                            'total_wrong'
                                        ]
                                    ) ?>
                                </strong>

                            </div>


                            <div>

                                <span>
                                    <i class="unanswered-dot"></i>
                                    Unanswered
                                </span>

                                <strong>
                                    <?= (int) (
                                        $analytics[
                                            'total_unanswered'
                                        ]
                                    ) ?>
                                </strong>

                            </div>

                        </div>

                    </div>

                </article>

            </section>


            <!-- =================================================
                 SUBJECT PERFORMANCE
            ================================================== -->

            <section class="performance-panel subject-performance-panel">

                <div class="performance-panel-heading">

                    <div>

                        <span>
                            SUBJECT ANALYSIS
                        </span>

                        <h2>
                            Performance by subject
                        </h2>

                    </div>


                    <i
                        class="fa-solid fa-book-open"
                    ></i>

                </div>


                <?php if (
                    !empty($subjectPerformance)
                ): ?>

                    <div class="subject-performance-list">

                        <?php foreach (
                            $subjectPerformance
                            as $subject
                        ): ?>

                            <?php

                            $subjectAverage =
                                (float) (
                                    $subject[
                                        'average_percentage'
                                    ] ?? 0
                                );

                            $subjectBest =
                                (float) (
                                    $subject[
                                        'best_percentage'
                                    ] ?? 0
                                );

                            $subjectAttempted =
                                (int) (
                                    $subject[
                                        'attempted_questions'
                                    ] ?? 0
                                );

                            $subjectCorrect =
                                (int) (
                                    $subject[
                                        'correct_answers'
                                    ] ?? 0
                                );

                            $subjectAccuracy =
                                $subjectAttempted > 0
                                    ? round(
                                        (
                                            $subjectCorrect /
                                            $subjectAttempted
                                        ) * 100,
                                        2
                                    )
                                    : 0;

                            ?>


                            <div class="subject-performance-row">


                                <div class="subject-performance-name">

                                    <span
                                        class="subject-performance-icon"
                                    >

                                        <i
                                            class="fa-solid fa-book"
                                        ></i>

                                    </span>


                                    <span>

                                        <strong>

                                            <?= performance_escape(
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

                                            <?= (
                                                (int) $subject[
                                                    'attempts'
                                                ] === 1
                                            )
                                                ? 'exam'
                                                : 'exams'
                                            ?>

                                        </small>

                                    </span>

                                </div>


                                <div class="subject-performance-meter">

                                    <div
                                        class="performance-meter-track"
                                    >

                                        <span
                                            style="
                                                width: <?= max(
                                                    0,
                                                    min(
                                                        100,
                                                        $subjectAverage
                                                    )
                                                ) ?>%;
                                            "
                                        ></span>

                                    </div>


                                    <small>

                                        <?= performance_number(
                                            $subjectAverage
                                        ) ?>%
                                        average

                                    </small>

                                </div>


                                <div class="subject-performance-values">

                                    <span>

                                        Best

                                        <strong>
                                            <?= performance_number(
                                                $subjectBest
                                            ) ?>%
                                        </strong>

                                    </span>


                                    <span>

                                        Accuracy

                                        <strong>
                                            <?= performance_number(
                                                $subjectAccuracy
                                            ) ?>%
                                        </strong>

                                    </span>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="performance-empty-small">

                        Subject performance will appear after
                        you complete examinations.

                    </div>

                <?php endif; ?>

            </section>


            <!-- =================================================
                 RECENT EXAMS
            ================================================== -->

            <section class="performance-panel recent-performance-panel">

                <div class="performance-panel-heading">

                    <div>

                        <span>
                            EXAM HISTORY
                        </span>

                        <h2>
                            Recent performance
                        </h2>

                    </div>


                    <a
                        href="results.php"
                        class="performance-view-results"
                    >

                        View all

                        <i
                            class="fa-solid fa-arrow-right"
                        ></i>

                    </a>

                </div>


                <div class="recent-performance-table-wrap">

                    <table class="recent-performance-table">

                        <thead>

                            <tr>

                                <th>
                                    Exam
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
                            array_reverse(
                                $recentPerformance
                            )
                            as $recent
                        ): ?>

                            <tr>

                                <td>

                                    <?= performance_escape(
                                        $recent[
                                            'exam_title'
                                        ]
                                    ) ?>

                                </td>


                                <td>

                                    <strong>

                                        <?= performance_number(
                                            (float) $recent[
                                                'percentage'
                                            ]
                                        ) ?>%

                                    </strong>

                                </td>


                                <td>

                                    <span
                                        class="recent-correct"
                                    >

                                        <?= (int) (
                                            $recent[
                                                'correct_answers'
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <span
                                        class="recent-wrong"
                                    >

                                        <?= (int) (
                                            $recent[
                                                'wrong_answers'
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?php

                                    try {

                                        echo performance_escape(
    (
        new DateTimeImmutable(
            (string) $recent[
                'created_at'
            ]
        )
    )->format(
        'd M Y'
    )
);

                                    } catch (Throwable $exception) {

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


            <!-- =================================================
                 EMPTY STATE
            ================================================== -->

            <section class="performance-empty">

                <div class="performance-empty-icon">

                    <i
                        class="fa-solid fa-chart-line"
                    ></i>

                </div>


                <span>
                    PERFORMANCE STARTS HERE
                </span>


                <h2>
                    Complete your first exam to unlock analytics.
                </h2>


                <p>
                    Once you complete an examination, ExamSphere will
                    calculate your score, accuracy, pass rate and
                    subject-wise performance automatically.
                </p>


                <a
                    href="practice_exams.php"
                    class="performance-empty-btn"
                >

                    Start a practice exam

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </section>

        <?php endif; ?>

    </div>

</main>


<?php if (
    (int) $analytics['total_results'] > 0
): ?>

<script>

const examSpherePerformance = <?= json_encode(
    [
        'recent' => $recentPerformance,

        'correct' =>
            (int) $analytics['total_correct'],

        'wrong' =>
            (int) $analytics['total_wrong'],

        'unanswered' =>
            (int) $analytics['total_unanswered']
    ],
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
) ?>;


document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
        |--------------------------------------------------------------------------
        | Score progression
        |--------------------------------------------------------------------------
        */

        const lineCanvas =
            document.getElementById(
                'scoreProgressChart'
            );


        if (
            lineCanvas &&
            typeof Chart !== 'undefined'
        ) {

            const data =
                examSpherePerformance.recent || [];


            const labels =
                data.map(
                    function (item, index) {

                        return 'Exam ' +
                            (index + 1);
                    }
                );


            const values =
                data.map(
                    function (item) {

                        return Number(
                            item.percentage || 0
                        );
                    }
                );


            new Chart(
                lineCanvas,
                {
                    type: 'line',

                    data: {

                        labels: labels,

                        datasets: [

                            {

                                data: values,

                                borderColor: '#556B2F',

                                backgroundColor:
                                    'rgba(85,107,47,.08)',

                                fill: true,

                                tension: .35,

                                pointRadius: 4,

                                pointHoverRadius: 6

                            }

                        ]

                    },

                    options: {

                        responsive: true,

                        maintainAspectRatio: false,

                        scales: {

                            y: {

                                beginAtZero: true,

                                max: 100,

                                ticks: {
                                    callback:
                                        function (value) {
                                            return value + '%';
                                        }
                                }

                            }

                        },

                        plugins: {

                            legend: {
                                display: false
                            }

                        }

                    }

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Overall answer analysis
        |--------------------------------------------------------------------------
        */

        const doughnutCanvas =
            document.getElementById(
                'answerAnalysisChart'
            );


        if (
            doughnutCanvas &&
            typeof Chart !== 'undefined'
        ) {

            new Chart(
                doughnutCanvas,
                {
                    type: 'doughnut',

                    data: {

                        labels: [
                            'Correct',
                            'Wrong',
                            'Unanswered'
                        ],

                        datasets: [

                            {

                                data: [

                                    Number(
                                        examSpherePerformance.correct || 0
                                    ),

                                    Number(
                                        examSpherePerformance.wrong || 0
                                    ),

                                    Number(
                                        examSpherePerformance.unanswered || 0
                                    )

                                ],

                                backgroundColor: [
                                    '#556B2F',
                                    '#A84538',
                                    '#B99B63'
                                ],

                                borderWidth: 0,

                                hoverOffset: 5

                            }

                        ]

                    },

                    options: {

                        responsive: true,

                        maintainAspectRatio: false,

                        cutout: '72%',

                        plugins: {

                            legend: {
                                display: false
                            }

                        }

                    }

                }
            );
        }

    }
);

</script>

<?php endif; ?>


</body>

</html>