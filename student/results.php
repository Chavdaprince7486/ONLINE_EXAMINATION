<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';


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


function results_history_escape(
    ?string $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function results_history_number(
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


$results =
    [];


try {

    $statement =
        $conn->prepare("
            SELECT

                r.id,
                r.attempt_id,

                r.total_questions,
                r.attempted_questions,

                r.correct_answers,
                r.wrong_answers,
                r.unanswered_questions,

                r.total_marks,
                r.obtained_marks,
                r.percentage,

                r.grade,
                r.result_status,

                r.created_at,

                ea.started_at,
                ea.submitted_at,

                e.title AS exam_title,
                e.exam_type,

                sub.name AS subject_name,
                sub.code AS subject_code

            FROM results r

            INNER JOIN exam_attempts ea
                ON ea.id = r.attempt_id

            INNER JOIN exams e
                ON e.id = r.exam_id

            LEFT JOIN subjects sub
                ON sub.id = e.subject_id

            WHERE
                r.student_id = ?

            ORDER BY
                r.created_at DESC,
                r.id DESC
        ");


    $statement->execute([
        $studentId
    ]);


    $results =
        $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Student result history failed: ' .
        $exception->getMessage()
    );


    $results =
        [];
}


/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

$totalAttempts =
    count($results);


$passedAttempts =
    0;

$failedAttempts =
    0;

$averagePercentage =
    0;

$bestPercentage =
    0;


$percentageSum =
    0;


foreach (
    $results
    as $item
) {

    $percentage =
        (float)$item[
            'percentage'
        ];


    $percentageSum +=
        $percentage;


    $bestPercentage =
        max(
            $bestPercentage,
            $percentage
        );


    if (
        $item['result_status'] ===
        'Pass'
    ) {

        $passedAttempts++;

    } else {

        $failedAttempts++;
    }
}


if (
    $totalAttempts > 0
) {

    $averagePercentage =
        round(
            $percentageSum /
            $totalAttempts,
            2
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

    <title>
        My Results | ExamSphere
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


    <style>

        :root {

            --rh-brown:
                #5d4037;

            --rh-dark:
                #3e2723;

            --rh-olive:
                #556b2f;

            --rh-bg:
                #f7f6ee;

            --rh-border:
                #e5e0d7;

            --rh-muted:
                #7d756d;
        }


        * {
            box-sizing:
                border-box;
        }


        body {

            margin:
                0;

            color:
                var(--rh-dark);

            background:
                var(--rh-bg);

            font-family:
                Poppins,
                Arial,
                sans-serif;
        }


        .results-page {

            min-height:
                100vh;

            padding:
                30px 0 65px;

            background:

                radial-gradient(
                    circle at 4% 8%,
                    rgba(85,107,47,.07),
                    transparent 23%
                ),

                radial-gradient(
                    circle at 96% 84%,
                    rgba(93,64,55,.05),
                    transparent 24%
                );
        }


        .results-container {

            width:
                min(
                    1180px,
                    calc(100% - 30px)
                );

            margin:
                auto;
        }


        .results-heading {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            margin-bottom:
                18px;
        }


        .results-heading h1 {

            margin:
                0;

            font-size:
                1.55rem;

            letter-spacing:
                -.045em;
        }


        .results-heading p {

            margin:
                5px 0 0;

            color:
                var(--rh-muted);

            font-size:
                .58rem;
        }


        .results-heading a {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            padding:
                9px 12px;

            color:
                #fff;

            border:
                1px solid
                var(--rh-brown);

            border-radius:
                9px;

            background:
                var(--rh-brown);

            font-size:
                .52rem;

            font-weight:
                700;

            text-decoration:
                none;
        }


        .results-heading a:hover {

            border-color:
                var(--rh-olive);

            background:
                var(--rh-olive);
        }


        .results-summary {

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
                10px;

            margin-bottom:
                18px;
        }


        .results-summary-card {

            padding:
                14px;

            border:
                1px solid
                var(--rh-border);

            border-radius:
                13px;

            background:
                #fff;

            box-shadow:
                0 8px 19px
                rgba(62,39,35,.04);
        }


        .results-summary-card small {

            display:
                block;

            color:
                #918980;

            font-size:
                .46rem;
        }


        .results-summary-card strong {

            display:
                block;

            margin-top:
                4px;

            color:
                var(--rh-dark);

            font-size:
                1rem;
        }


        .results-summary-card i {

            float:
                right;

            color:
                var(--rh-olive);

            font-size:
                .7rem;
        }


        .results-table-card {

            overflow:
                hidden;

            border:
                1px solid
                var(--rh-border);

            border-radius:
                16px;

            background:
                #fff;

            box-shadow:
                0 10px 25px
                rgba(62,39,35,.045);
        }


        .results-table-wrap {

            overflow-x:
                auto;
        }


        table {

            width:
                100%;

            min-width:
                880px;

            border-collapse:
                collapse;
        }


        th {

            padding:
                13px 15px;

            color:
                #817970;

            border-bottom:
                1px solid
                #ebe7df;

            background:
                #faf9f6;

            font-size:
                .47rem;

            font-weight:
                800;

            text-align:
                left;

            letter-spacing:
                .05em;

            text-transform:
                uppercase;
        }


        td {

            padding:
                14px 15px;

            color:
                #625a53;

            border-bottom:
                1px solid
                #eee9e1;

            font-size:
                .55rem;

            vertical-align:
                middle;
        }


        tbody tr:last-child td {

            border-bottom:
                0;
        }


        tbody tr:hover {

            background:
                #fcfcf8;
        }


        .result-exam-name {

            display:
                flex;

            flex-direction:
                column;

            gap:
                2px;
        }


        .result-exam-name strong {

            color:
                var(--rh-dark);

            font-size:
                .57rem;
        }


        .result-exam-name small {

            color:
                #918980;

            font-size:
                .44rem;
        }


        .result-status-pill {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                5px;

            padding:
                5px 7px;

            border-radius:
                999px;

            font-size:
                .43rem;

            font-weight:
                800;
        }


        .result-status-pill.pass {

            color:
                #5c723e;

            background:
                #edf4e5;

            border:
                1px solid
                #d5e0c4;
        }


        .result-status-pill.fail {

            color:
                #98473e;

            background:
                #faedeb;

            border:
                1px solid
                #e8cac4;
        }


        .result-grade {

            display:
                inline-grid;

            place-items:
                center;

            width:
                30px;

            height:
                30px;

            color:
                var(--rh-olive);

            border-radius:
                9px;

            background:
                #edf3e5;

            font-size:
                .5rem;

            font-weight:
                800;
        }


        .result-view-btn {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                5px;

            padding:
                7px 9px;

            color:
                var(--rh-olive);

            border:
                1px solid
                #ccd7b9;

            border-radius:
                8px;

            background:
                #f3f6ec;

            font-size:
                .47rem;

            font-weight:
                700;

            text-decoration:
                none;
        }


        .result-view-btn:hover {

            color:
                var(--rh-brown);

            background:
                #edf2e4;
        }


        .results-empty {

            padding:
                75px 20px;

            text-align:
                center;
        }


        .results-empty-icon {

            display:
                grid;

            place-items:
                center;

            width:
                60px;

            height:
                60px;

            margin:
                0 auto 13px;

            color:
                var(--rh-olive);

            border-radius:
                16px;

            background:
                #edf3e5;

            font-size:
                .9rem;
        }


        .results-empty h2 {

            margin:
                0 0 6px;

            font-size:
                1rem;
        }


        .results-empty p {

            max-width:
                500px;

            margin:
                0 auto 17px;

            color:
                var(--rh-muted);

            font-size:
                .57rem;

            line-height:
                1.7;
        }


        .results-empty a {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            padding:
                9px 12px;

            color:
                #fff;

            border-radius:
                9px;

            background:
                var(--rh-brown);

            font-size:
                .52rem;

            font-weight:
                700;

            text-decoration:
                none;
        }


        @media (max-width: 800px) {

            .results-summary {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(
                            0,
                            1fr
                        )
                    );
            }


            .results-heading {

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }


            .results-heading a {

                width:
                    100%;

                justify-content:
                    center;
            }

        }


        @media (max-width: 460px) {

            .results-summary {

                grid-template-columns:
                    1fr;
            }

        }

    </style>

</head>


<body>


<?php include 'includes/navbar.php'; ?>


<main class="results-page">

<div class="results-container">


    <!-- HEADER -->

    <section class="results-heading">

        <div>

            <h1>
                My Results
            </h1>


            <p>

                Your complete examination performance history.

            </p>

        </div>


        <a
            href="practice_exams.php"
        >

            <i
                class="fa-solid fa-rocket"
            ></i>

            Take another exam

        </a>

    </section>


    <!-- SUMMARY -->

    <section class="results-summary">


        <div class="results-summary-card">

            <i
                class="fa-solid fa-layer-group"
            ></i>

            <small>
                Total Attempts
            </small>


            <strong>
                <?= $totalAttempts ?>
            </strong>

        </div>


        <div class="results-summary-card">

            <i
                class="fa-solid fa-circle-check"
            ></i>

            <small>
                Passed
            </small>


            <strong>
                <?= $passedAttempts ?>
            </strong>

        </div>


        <div class="results-summary-card">

            <i
                class="fa-solid fa-chart-line"
            ></i>

            <small>
                Average
            </small>


            <strong>

                <?= results_history_number(
                    $averagePercentage
                ) ?>%

            </strong>

        </div>


        <div class="results-summary-card">

            <i
                class="fa-solid fa-trophy"
            ></i>

            <small>
                Best Score
            </small>


            <strong>

                <?= results_history_number(
                    $bestPercentage
                ) ?>%

            </strong>

        </div>

    </section>


    <!-- TABLE -->

    <section class="results-table-card">


        <?php if (
            !empty($results)
        ): ?>


            <div class="results-table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Examination
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Marks
                            </th>

                            <th>
                                Percentage
                            </th>

                            <th>
                                Grade
                            </th>

                            <th>
                                Result
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $results
                        as $item
                    ): ?>

                        <tr>


                            <td>

                                <div
                                    class="
                                        result-exam-name
                                    "
                                >

                                    <strong>

                                        <?= results_history_escape(
                                            $item[
                                                'exam_title'
                                            ]
                                        ) ?>

                                    </strong>


                                    <small>

                                        <?= results_history_escape(
                                            $item[
                                                'subject_name'
                                            ] ?: 'General'
                                        ) ?>

                                        •

                                        <?= results_history_escape(
                                            $item[
                                                'exam_type'
                                            ]
                                        ) ?>

                                    </small>

                                </div>

                            </td>


                            <td>

                                <?php

                                try {

                                    echo results_history_escape(
                                        (
                                            new DateTimeImmutable(
                                                $item[
                                                    'created_at'
                                                ]
                                            )
                                        )->format(
                                            'd M Y'
                                        )
                                    );

                                } catch (
                                    Throwable $exception
                                ) {

                                    echo results_history_escape(
                                        $item[
                                            'created_at'
                                        ]
                                    );
                                }

                                ?>

                            </td>


                            <td>

                                <strong>

                                    <?= results_history_number(
                                        (float)$item[
                                            'obtained_marks'
                                        ]
                                    ) ?>

                                </strong>

                                /

                                <?= results_history_number(
                                    (float)$item[
                                        'total_marks'
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <strong>

                                    <?= results_history_number(
                                        (float)$item[
                                            'percentage'
                                        ]
                                    ) ?>%

                                </strong>

                            </td>


                            <td>

                                <span class="result-grade">

                                    <?= results_history_escape(
                                        $item[
                                            'grade'
                                        ]
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="
                                        result-status-pill
                                        <?= $item[
                                            'result_status'
                                        ] === 'Pass'
                                            ? 'pass'
                                            : 'fail'
                                        ?>
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            <?= $item[
                                                'result_status'
                                            ] === 'Pass'
                                                ? 'fa-check'
                                                : 'fa-xmark'
                                            ?>
                                        "
                                    ></i>


                                    <?= results_history_escape(
                                        $item[
                                            'result_status'
                                        ]
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <a
                                    href="
                                        result.php?id=<?= (int)$item['id']; ?>
                                    "
                                    class="
                                        result-view-btn
                                    "
                                >

                                    View

                                    <i
                                        class="
                                            fa-solid
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>

                            </td>


                        </tr>

                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <div class="results-empty">

                <div
                    class="results-empty-icon"
                >

                    <i
                        class="
                            fa-solid
                            fa-chart-column
                        "
                    ></i>

                </div>


                <h2>
                    No completed results yet.
                </h2>


                <p>

                    Complete your first examination and
                    your performance report will appear here.

                </p>


                <a
                    href="practice_exams.php"
                >

                    Explore practice exams

                    <i
                        class="
                            fa-solid
                            fa-arrow-right
                        "
                    ></i>

                </a>

            </div>

        <?php endif; ?>


    </section>


</div>

</main>


</body>

</html>