<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
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
| RESULT ID
|--------------------------------------------------------------------------
*/

$resultId =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );


if (
    $resultId === false ||
    $resultId === null ||
    $resultId <= 0
) {

    http_response_code(400);

    exit(
        'Invalid result.'
    );
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function result_escape(
    mixed $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function result_number(
    float|int|string|null $value
): string {

    $number =
        (float) $value;


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


function result_date(
    mixed $value
): string {

    if (
        empty($value)
    ) {

        return '-';
    }


    try {

        return (
            new DateTimeImmutable(
                (string) $value
            )
        )->format(
            'd M Y, h:i A'
        );

    } catch (Throwable) {

        return '-';
    }
}


/*
|--------------------------------------------------------------------------
| LOAD RESULT
|--------------------------------------------------------------------------
*/

try {

    $resultStatement =
        $conn->prepare("
            SELECT

                r.id AS result_id,

                r.attempt_id,
                r.student_id,
                r.exam_id,

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

                e.title AS exam_title,
                e.exam_type,

                e.duration_minutes,
                e.passing_marks,

                e.subject_id,

                ea.started_at,
                ea.server_deadline,
                ea.submitted_at,

                ea.status AS attempt_status,

                s.name AS subject_name,
                s.code AS subject_code

            FROM results r

            INNER JOIN exams e
                ON e.id = r.exam_id

            INNER JOIN exam_attempts ea
                ON ea.id = r.attempt_id
                AND ea.student_id = r.student_id
                AND ea.exam_id = r.exam_id

            LEFT JOIN subjects s
                ON s.id = e.subject_id

            WHERE

                r.id = ?

                AND r.student_id = ?

            LIMIT 1
        ");


    $resultStatement->execute([

        $resultId,

        $studentId

    ]);


    $result =
        $resultStatement->fetch(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $exception) {

    error_log(
        'ExamSphere result load failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load result.'
    );
}


/*
|--------------------------------------------------------------------------
| RESULT NOT FOUND
|--------------------------------------------------------------------------
*/

if (
    !$result
) {

    http_response_code(404);

    exit(
        'Result not found.'
    );
}


/*
|--------------------------------------------------------------------------
| ONLY FINALIZED ATTEMPTS
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        (string) $result['attempt_status'],
        [
            'Submitted',
            'Auto Submitted'
        ],
        true
    )
) {

    http_response_code(409);

    exit(
        'This result is not finalized yet.'
    );
}


/*
|--------------------------------------------------------------------------
| QUESTION-WISE ANALYSIS
|--------------------------------------------------------------------------
*/

$analysis = [];


try {

    $analysisStatement =
        $conn->prepare("
            SELECT

                q.id AS question_id,

                eq.position,

                q.question_text,

                q.option_a,
                q.option_b,
                q.option_c,
                q.option_d,

                q.correct_answer,

                q.explanation,

                q.marks,
                q.negative_marks,

                a.selected_answer,
                a.question_status,

                a.is_correct,
                a.marks_awarded

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            LEFT JOIN answers a
                ON a.attempt_id = ?
                AND a.question_id = q.id

            WHERE

                eq.exam_id = ?

            ORDER BY

                eq.position ASC,
                q.id ASC
        ");


    $analysisStatement->execute([

        (int) $result['attempt_id'],

        (int) $result['exam_id']

    ]);


    $analysis =
        $analysisStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'ExamSphere result analysis load failed: ' .
        $exception->getMessage()
    );

    $analysis = [];
}


/*
|--------------------------------------------------------------------------
| REMOVE DUPLICATE QUESTION IDS
|--------------------------------------------------------------------------
*/

$uniqueAnalysis = [];


foreach (
    $analysis as $question
) {

    $questionId =
        (int) $question['question_id'];


    if (
        isset(
            $uniqueAnalysis[
                $questionId
            ]
        )
    ) {

        continue;
    }


    $uniqueAnalysis[
        $questionId
    ] =
        $question;
}


$analysis =
    array_values(
        $uniqueAnalysis
    );


/*
|--------------------------------------------------------------------------
| SCORE VALUES
|--------------------------------------------------------------------------
*/

$percentage =
    max(
        0,
        min(
            100,
            (float) $result['percentage']
        )
    );


$obtainedMarks =
    max(
        0,
        (float) $result['obtained_marks']
    );


$totalMarks =
    max(
        0,
        (float) $result['total_marks']
    );


$correctAnswers =
    max(
        0,
        (int) $result['correct_answers']
    );


$wrongAnswers =
    max(
        0,
        (int) $result['wrong_answers']
    );


$unansweredQuestions =
    max(
        0,
        (int) $result['unanswered_questions']
    );


$attemptedQuestions =
    max(
        0,
        (int) $result['attempted_questions']
    );


$totalQuestions =
    max(
        0,
        (int) $result['total_questions']
    );


$isPassed =
    (
        (string) $result[
            'result_status'
        ]
        ===
        'Pass'
    );


$resultDate =
    result_date(
        $result['created_at']
    );


$performanceLabel =
    $percentage >= 80
        ? 'Excellent'
        : (
            $percentage >= 60
                ? 'Good'
                : (
                    $percentage >= 40
                        ? 'Needs Improvement'
                        : 'Needs More Practice'
                )
        );


/*
|--------------------------------------------------------------------------
| SCORE CIRCLE
|--------------------------------------------------------------------------
*/

$scoreDegree =
    round(
        $percentage * 3.6,
        2
    );


/*
|--------------------------------------------------------------------------
| ACCURACY + TIME USED
|--------------------------------------------------------------------------
*/

$accuracy =
    $attemptedQuestions > 0
        ? round(
            ($correctAnswers / $attemptedQuestions) * 100,
            2
        )
        : 0.00;

$timeUsedSeconds = 0;

try {
    if (
        !empty($result['started_at']) &&
        !empty($result['submitted_at'])
    ) {
        $startedAt = new DateTimeImmutable(
            (string) $result['started_at']
        );

        $submittedAt = new DateTimeImmutable(
            (string) $result['submitted_at']
        );

        $timeUsedSeconds = max(
            0,
            $submittedAt->getTimestamp() - $startedAt->getTimestamp()
        );
    }
} catch (Throwable) {
    $timeUsedSeconds = 0;
}

$timeUsedMinutes = intdiv($timeUsedSeconds, 60);
$timeUsedRemainder = $timeUsedSeconds % 60;

$timeUsedLabel = sprintf(
    '%dm %02ds',
    $timeUsedMinutes,
    $timeUsedRemainder
);


/*
|--------------------------------------------------------------------------
| QUESTION OPTION HELPER
|--------------------------------------------------------------------------
*/

function result_option_text(
    array $question,
    string $answer
): string {

    return match (
        strtoupper(
            trim($answer)
        )
    ) {

        'A' =>
            (string) (
                $question['option_a']
                ?? ''
            ),

        'B' =>
            (string) (
                $question['option_b']
                ?? ''
            ),

        'C' =>
            (string) (
                $question['option_c']
                ?? ''
            ),

        'D' =>
            (string) (
                $question['option_d']
                ?? ''
            ),

        default =>
            ''
    };
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
        content="#5D4037"
    >

    <title>

        Result |

        <?= result_escape(
            $result['exam_title']
        ) ?>

        | ExamSphere

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
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="assets/css/dashboard.css"
    >


    <link
        rel="stylesheet"
        href="assets/css/result.css"
    >


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >


    <style>

        :root {

            --result-brown:
                #5D4037;

            --result-brown-dark:
                #3E2723;

            --result-olive:
                #556B2F;

            --result-olive-dark:
                #465925;

            --result-cream:
                #F5F5DC;

            --result-cream-light:
                #FAF8F4;

            --result-text:
                #333333;

            --result-muted:
                #777;

            --result-border:
                #E8E1D8;

            --result-green:
                #27723C;

            --result-red:
                #A33A3A;

            --result-orange:
                #D78920;

        }


        * {
            box-sizing:
                border-box;
        }


        body {

            margin:
                0;

            background:
                radial-gradient(
                    circle at top left,
                    rgba(
                        85,
                        107,
                        47,
                        .08
                    ),
                    transparent 26%
                ),

                radial-gradient(
                    circle at bottom right,
                    rgba(
                        93,
                        64,
                        55,
                        .08
                    ),
                    transparent 30%
                ),

                linear-gradient(
                    135deg,
                    #F7F3ED,
                    var(--result-cream)
                );

            color:
                var(--result-text);

            font-family:
                Poppins,
                Arial,
                sans-serif;
        }


        .result-page {

            max-width:
                1280px;

            margin:
                0 auto;

            padding:
                32px 18px 65px;
        }


        .result-hero {

            position:
                relative;

            overflow:
                hidden;

            margin-bottom:
                22px;

            padding:
                32px;

            border:
                1px solid
                rgba(
                    93,
                    64,
                    55,
                    .10
                );

            border-radius:
                28px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .90
                );

            box-shadow:
                0 24px 70px
                rgba(
                    80,
                    55,
                    40,
                    .10
                );

            backdrop-filter:
                blur(
                    15px
                );
        }


        .result-hero::before {

            content:
                "";

            position:
                absolute;

            width:
                260px;

            height:
                260px;

            top:
                -110px;

            right:
                -70px;

            border-radius:
                50%;

            background:
                rgba(
                    85,
                    107,
                    47,
                    .10
                );

            pointer-events:
                none;
        }


        .result-title {

            position:
                relative;

            z-index:
                1;
        }


        .result-kicker {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            margin-bottom:
                8px;

            color:
                var(--result-olive);

            font-size:
                10px;

            font-weight:
                800;

            letter-spacing:
                1.5px;

            text-transform:
                uppercase;
        }


        .result-title h1 {

            margin:
                0;

            color:
                var(--result-brown);

            font-size:
                clamp(
                    26px,
                    4vw,
                    38px
                );

            font-weight:
                800;
        }


        .result-title p {

            max-width:
                780px;

            margin:
                7px 0 0;

            color:
                var(--result-muted);

            line-height:
                1.65;
        }


        .exam-meta-row {

            display:
                flex;

            flex-wrap:
                wrap;

            gap:
                10px;

            margin-top:
                18px;
        }


        .exam-meta-pill {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            padding:
                8px 12px;

            border:
                1px solid
                var(--result-border);

            border-radius:
                999px;

            color:
                var(--result-brown);

            background:
                var(--result-cream-light);

            font-size:
                11px;

            font-weight:
                600;
        }


        .status-pill {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            padding:
                10px 16px;

            border-radius:
                999px;

            font-size:
                13px;

            font-weight:
                800;
        }


        .status-pass {

            color:
                var(--result-green);

            background:
                #EDF7EF;
        }


        .status-fail {

            color:
                var(--result-red);

            background:
                #FFF0F0;
        }


        .result-actions {

            display:
                flex;

            flex-wrap:
                wrap;

            gap:
                10px;

            margin-top:
                22px;
        }


        .result-btn {

            min-height:
                46px;

            padding:
                10px 16px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            border:
                1px solid
                transparent;

            border-radius:
                12px;

            text-decoration:
                none;

            font-size:
                12px;

            font-weight:
                700;

            cursor:
                pointer;

            transition:
                .2s ease;
        }


        .result-btn:hover {

            transform:
                translateY(
                    -1px
                );
        }


        .result-btn-primary {

            color:
                #FFFFFF;

            background:
                var(--result-brown);

            box-shadow:
                0 10px 25px
                rgba(
                    93,
                    64,
                    55,
                    .16
                );
        }


        .result-btn-secondary {

            color:
                var(--result-brown);

            background:
                #FFFFFF;

            border-color:
                var(--result-border);
        }


        .result-btn-secondary:hover {

            color:
                var(--result-olive);

            border-color:
                rgba(
                    85,
                    107,
                    47,
                    .30
                );
        }


        .score-card {

            margin-bottom:
                22px;

            padding:
                30px;

            border:
                1px solid
                var(--result-border);

            border-radius:
                26px;

            background:
                #FFFFFF;

            box-shadow:
                0 18px 50px
                rgba(
                    80,
                    55,
                    40,
                    .08
                );
        }


        .score-circle {

            width:
                190px;

            height:
                190px;

            margin:
                0 auto;

            display:
                flex;

            flex-direction:
                column;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                50%;

            background:
                conic-gradient(
                    var(--result-brown)
                    <?= $scoreDegree ?>deg,
                    #EEE6DE
                    0
                );

            position:
                relative;
        }


        .score-circle::after {

            content:
                "";

            position:
                absolute;

            inset:
                13px;

            border-radius:
                50%;

            background:
                #FFFFFF;
        }


        .score-circle-content {

            position:
                relative;

            z-index:
                2;

            text-align:
                center;
        }


        .score-percentage {

            color:
                var(--result-brown);

            font-size:
                36px;

            font-weight:
                800;
        }


        .score-label {

            color:
                var(--result-muted);

            font-size:
                12px;

            font-weight:
                500;
        }


        .score-caption {

            margin-top:
                14px;

            text-align:
                center;

            color:
                var(--result-muted);

            font-size:
                11px;
        }


        .score-caption strong {

            color:
                var(--result-brown);
        }


        .info-box {

            padding:
                20px;

            border:
                1px solid
                var(--result-border);

            border-radius:
                20px;

            background:
                #FFFFFF;
        }


        .info-box h4 {

            margin:
                0 0 14px;

            color:
                var(--result-brown);

            font-size:
                16px;

            font-weight:
                800;
        }


        .info-row {

            display:
                flex;

            justify-content:
                space-between;

            gap:
                18px;

            padding:
                9px 0;

            border-bottom:
                1px dashed
                #E7E0D8;

            font-size:
                12px;
        }


        .info-row:last-child {

            border-bottom:
                0;
        }


        .info-row span:first-child {

            color:
                var(--result-muted);
        }


        .info-row span:last-child {

            color:
                var(--result-brown-dark);

            text-align:
                right;

            font-weight:
                700;
        }


        .metric-grid {

            display:
                grid;

            grid-template-columns:
                repeat(
                    4,
                    1fr
                );

            gap:
                13px;

            margin-top:
                25px;
        }


        .metric {

            padding:
                17px;

            border:
                1px solid
                var(--result-border);

            border-radius:
                17px;

            text-align:
                center;

            background:
                var(--result-cream-light);
        }


        .metric i {

            margin-bottom:
                6px;

            color:
                var(--result-brown);

            font-size:
                19px;
        }


        .metric strong {

            display:
                block;

            color:
                var(--result-brown);

            font-size:
                24px;

            font-weight:
                800;
        }


        .metric span {

            color:
                var(--result-muted);

            font-size:
                11px;
        }


        .section-card {

            margin-bottom:
                22px;

            padding:
                25px;

            border:
                1px solid
                var(--result-border);

            border-radius:
                24px;

            background:
                #FFFFFF;

            box-shadow:
                0 15px 42px
                rgba(
                    80,
                    55,
                    40,
                    .06
                );
        }


        .section-heading {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                15px;

            margin-bottom:
                19px;
        }


        .section-heading small {

            display:
                block;

            margin-bottom:
                3px;

            color:
                var(--result-olive);

            font-size:
                9px;

            font-weight:
                800;

            letter-spacing:
                1.5px;
        }


        .section-heading h3 {

            margin:
                0;

            color:
                var(--result-brown);

            font-size:
                21px;

            font-weight:
                800;
        }


        .section-heading > span {

            color:
                var(--result-muted);

            font-size:
                11px;

            font-weight:
                600;
        }


        .question-analysis {

            margin-bottom:
                13px;

            overflow:
                hidden;

            border:
                1px solid
                #EEE7E0;

            border-radius:
                18px;

            background:
                #FFFFFF;
        }


        .question-analysis:last-child {

            margin-bottom:
                0;
        }


        .question-analysis-top {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                flex-start;

            gap:
                15px;

            padding:
                15px 17px;

            background:
                #FAF8F4;
        }


        .question-analysis-left {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                11px;

            min-width:
                0;
        }


        .question-number {

            width:
                38px;

            height:
                38px;

            flex:
                0 0 38px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                12px;

            color:
                var(--result-brown);

            background:
                var(--result-cream);

            font-size:
                12px;

            font-weight:
                800;
        }


        .question-title {

            min-width:
                0;

            color:
                var(--result-brown-dark);

            font-size:
                13px;

            line-height:
                1.65;

            font-weight:
                700;
        }


        .question-result {

            flex:
                0 0 auto;

            padding:
                6px 9px;

            border-radius:
                999px;

            font-size:
                10px;

            font-weight:
                800;
        }


        .correct-result {

            color:
                var(--result-green);

            background:
                #EDF7EF;
        }


        .wrong-result {

            color:
                var(--result-red);

            background:
                #FFF0F0;
        }


        .unanswered-result {

            color:
                #6D675F;

            background:
                #F0EEE9;
        }


        .question-analysis-body {

            padding:
                17px;
        }


        .answer-line {

            display:
                flex;

            flex-wrap:
                wrap;

            gap:
                7px;

            margin-top:
                9px;

            color:
                var(--result-text);

            font-size:
                12px;

            line-height:
                1.65;
        }


        .answer-line:first-child {

            margin-top:
                0;
        }


        .answer-line > strong {

            color:
                var(--result-brown-dark);
        }


        .answer-correct {

            color:
                var(--result-green);

            font-weight:
                700;
        }


        .answer-wrong {

            color:
                var(--result-red);

            font-weight:
                700;
        }


        .answer-neutral {

            color:
                var(--result-muted);

            font-weight:
                600;
        }


        .marks-earned {

            color:
                var(--result-brown);

            font-weight:
                800;
        }


        .explanation {

            margin-top:
                14px;

            padding:
                14px;

            border-left:
                3px solid
                var(--result-olive);

            border-radius:
                0 12px 12px 0;

            background:
                #F7F5EF;

            color:
                #555;

            font-size:
                12px;

            line-height:
                1.75;
        }


        .footer-note {

            margin-top:
                20px;

            text-align:
                center;

            color:
                var(--result-muted);

            font-size:
                10px;

            line-height:
                1.6;
        }


        .empty-analysis {

            padding:
                45px 20px;

            text-align:
                center;

            color:
                var(--result-muted);
        }


        .empty-analysis i {

            margin-bottom:
                10px;

            font-size:
                32px;

            color:
                var(--result-olive);
        }


        @media (
            max-width: 900px
        ) {

            .metric-grid {

                grid-template-columns:
                    repeat(
                        2,
                        1fr
                    );
            }

        }


        @media (
            max-width: 700px
        ) {

            .result-page {

                padding:
                    18px 11px 45px;
            }


            .result-hero,
            .score-card,
            .section-card {

                padding:
                    19px;

                border-radius:
                    20px;
            }


            .result-actions .result-btn {

                flex:
                    1 1
                    calc(
                        50% - 6px
                    );
            }


            .metric-grid {

                grid-template-columns:
                    1fr 1fr;
            }


            .score-circle {

                width:
                    160px;

                height:
                    160px;
            }


            .score-percentage {

                font-size:
                    30px;
            }


            .question-analysis-top {

                flex-direction:
                    column;
            }


            .question-result {

                align-self:
                    flex-start;
            }

        }


        @media print {

            body {

                background:
                    #FFFFFF;
            }


            .result-page {

                max-width:
                    100%;

                padding:
                    0;
            }


            .result-actions {

                display:
                    none;
            }


            .result-hero,
            .score-card,
            .section-card {

                box-shadow:
                    none;

                border:
                    1px solid
                    #DDD;
            }

        }

    </style>

</head>


<body>


<main class="result-page">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="result-hero">

        <div class="result-title">

            <span class="result-kicker">

                <i
                    class="fa-solid fa-chart-column"
                ></i>

                ExamSphere Result

            </span>


            <div
                class="
                    d-flex
                    flex-wrap
                    justify-content-between
                    align-items-start
                    gap-3
                "
            >

                <div>

                    <h1>
                        Examination result
                    </h1>


                    <p>

                        <?= result_escape(
                            $result['exam_title']
                        ) ?>

                    </p>


                    <div class="exam-meta-row">


                        <span
                            class="exam-meta-pill"
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-book-open
                                "
                            ></i>

                            <?= result_escape(
                                $result['subject_name']
                                ?: 'General'
                            ) ?>

                        </span>


                        <span
                            class="exam-meta-pill"
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-file-lines
                                "
                            ></i>

                            <?= result_escape(
                                $result['exam_type']
                            ) ?>

                        </span>


                        <span
                            class="exam-meta-pill"
                        >

                            <i
                                class="
                                    fa-regular
                                    fa-calendar
                                "
                            ></i>

                            <?= result_escape(
                                $resultDate
                            ) ?>

                        </span>


                    </div>

                </div>


                <div>

                    <span
                        class="
                            status-pill
                            <?= $isPassed
                                ? 'status-pass'
                                : 'status-fail'
                            ?>
                        "
                    >

                        <i
                            class="
                                fa-solid
                                <?= $isPassed
                                    ? 'fa-circle-check'
                                    : 'fa-circle-xmark'
                                ?>
                            "
                        ></i>

                        <?= $isPassed
                            ? 'PASS'
                            : 'FAIL'
                        ?>

                    </span>

                </div>

            </div>


            <div class="result-actions">


                <a
                    href="dashboard.php"
                    class="
                        result-btn
                        result-btn-secondary
                    "
                >

                    <i
                        class="fa-solid fa-house"
                    ></i>

                    Dashboard

                </a>


                <a
                    href="my_exams.php"
                    class="
                        result-btn
                        result-btn-secondary
                    "
                >

                    <i
                        class="fa-solid fa-file-lines"
                    ></i>

                    My Exams

                </a>


                <a
                    href="ajax/download_result_pdf.php?attempt_id=<?= (int) $result['attempt_id'] ?>"
                    class="
                        result-btn
                        result-btn-primary
                    "
                >

                    <i
                        class="fa-solid fa-file-pdf"
                    ></i>

                    Download PDF

                </a>


                <button
                    type="button"
                    id="emailResult"
                    class="
                        result-btn
                        result-btn-secondary
                    "
                    data-attempt-id="<?= (int) $result['attempt_id'] ?>"
                    data-csrf-token="<?= result_escape(
                        $csrfToken
                    ) ?>"
                >

                    <i
                        class="fa-solid fa-envelope"
                    ></i>

                    Email Result

                </button>


                <button
                    type="button"
                    class="
                        result-btn
                        result-btn-secondary
                        btn-print
                    "
                >

                    <i
                        class="fa-solid fa-print"
                    ></i>

                    Print

                </button>


            </div>

        </div>

    </section>


    <!-- =====================================================
         SCORE
    ====================================================== -->

    <section class="score-card">

        <div class="row align-items-center">

            <div class="col-lg-4 text-center">

                <div
                    class="score-circle"
                    aria-label="
                        Score
                        <?= result_escape(
                            number_format(
                                $percentage,
                                2
                            )
                        ) ?> percent
                    "
                >

                    <div
                        class="score-circle-content"
                    >

                        <div class="score-percentage">

                            <?= result_escape(
                                number_format(
                                    $percentage,
                                    2
                                )
                            ) ?>%

                        </div>


                        <div class="score-label">

                            Overall Score

                        </div>

                    </div>

                </div>


                <div class="score-caption">

                    You scored

                    <strong>

                        <?= result_number(
                            $obtainedMarks
                        ) ?>

                        /

                        <?= result_number(
                            $totalMarks
                        ) ?>

                    </strong>

                    marks.

                </div>

            </div>


            <div class="col-lg-8">

                <div
                    class="
                        row
                        g-3
                        mt-3
                        mt-lg-0
                    "
                >


                    <div class="col-md-6">

                        <div class="info-box">

                            <h4>

                                <i
                                    class="
                                        fa-solid
                                        fa-trophy
                                        me-2
                                    "
                                ></i>

                                Final score

                            </h4>


                            <div class="info-row">

                                <span>
                                    Obtained marks
                                </span>

                                <span>

                                    <?= result_number(
                                        $obtainedMarks
                                    ) ?>

                                </span>

                            </div>


                            <div class="info-row">

                                <span>
                                    Total marks
                                </span>

                                <span>

                                    <?= result_number(
                                        $totalMarks
                                    ) ?>

                                </span>

                            </div>


                            <div class="info-row">

                                <span>
                                    Grade
                                </span>

                                <span>

                                    <?= result_escape(
                                        $result['grade']
                                    ) ?>

                                </span>

                            </div>


                            <div class="info-row">

                                <span>
                                    Result
                                </span>

                                <span>

                                    <?= $isPassed
                                        ? 'Passed'
                                        : 'Failed'
                                    ?>

                                </span>

                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="info-box">

                            <h4>

                                <i
                                    class="
                                        fa-solid
                                        fa-chart-simple
                                        me-2
                                    "
                                ></i>

                                Performance

                            </h4>


                            <div class="info-row">

                                <span>
                                    Passing marks
                                </span>

                                <span>

                                    <?= result_number(
                                        $result['passing_marks']
                                    ) ?>

                                </span>

                            </div>


                            <div class="info-row">

                                <span>
                                    Performance
                                </span>

                                <span>

                                    <?= result_escape(
                                        $performanceLabel
                                    ) ?>

                                </span>

                            </div>


                            <div class="info-row">

                                <span>
                                    Attempted
                                </span>

                                <span>

                                    <?= $attemptedQuestions ?>

                                    /

                                    <?= $totalQuestions ?>

                                </span>

                            </div>


                            <div class="info-row">

                                <span>
                                    Attempt status
                                </span>

                                <span>

                                    <?= result_escape(
                                        $result['attempt_status']
                                    ) ?>

                                </span>

                            </div>

                        </div>

                    </div>


                </div>

            </div>

        </div>


        <!-- =================================================
             METRICS
        ================================================== -->

        <div class="metric-grid">


            <div class="metric">

                <i
                    class="fa-solid fa-circle-check"
                ></i>


                <strong>
                    <?= $correctAnswers ?>
                </strong>


                <span>
                    Correct
                </span>

            </div>


            <div class="metric">

                <i
                    class="fa-solid fa-circle-xmark"
                ></i>


                <strong>
                    <?= $wrongAnswers ?>
                </strong>


                <span>
                    Wrong
                </span>

            </div>


            <div class="metric">

                <i
                    class="fa-solid fa-circle-question"
                ></i>


                <strong>
                    <?= $unansweredQuestions ?>
                </strong>


                <span>
                    Unanswered
                </span>

            </div>


            <div class="metric">

                <i
                    class="fa-solid fa-pen"
                ></i>


                <strong>
                    <?= $attemptedQuestions ?>
                </strong>


                <span>
                    Attempted
                </span>

            </div>


            <div class="metric">

                <i
                    class="fa-solid fa-bullseye"
                ></i>


                <strong>
                    <?= number_format($accuracy, 2) ?>%
                </strong>


                <span>
                    Accuracy
                </span>

            </div>


        </div>

    </section>


    <!-- =====================================================
         EXAM INFORMATION
    ====================================================== -->

    <section class="section-card">

        <div class="section-heading">

            <div>

                <small>
                    EXAMINATION DETAILS
                </small>

                <h3>
                    Timing & performance
                </h3>

            </div>

        </div>


        <div class="row g-3">


            <div class="col-lg-6">

                <div class="info-box">

                    <h4>

                        <i
                            class="
                                fa-solid
                                fa-clock
                                me-2
                            "
                        ></i>

                        Timing

                    </h4>


                    <div class="info-row">

                        <span>
                            Duration
                        </span>

                        <span>

                            <?= (int) (
                                $result[
                                    'duration_minutes'
                                ]
                                ?? 0
                            ) ?>

                            minutes

                        </span>

                    </div>


                    <div class="info-row">

                        <span>
                            Started
                        </span>

                        <span>

                            <?= result_escape(
                                result_date(
                                    $result[
                                        'started_at'
                                    ]
                                )
                            ) ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span>
                            Submitted
                        </span>

                        <span>

                            <?= result_escape(
                                result_date(
                                    $result[
                                        'submitted_at'
                                    ]
                                )
                            ) ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span>
                            Time used
                        </span>

                        <span>
                            <?= result_escape($timeUsedLabel) ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span>
                            Accuracy
                        </span>

                        <span>
                            <?= number_format($accuracy, 2) ?>%
                        </span>

                    </div>


                    <div class="info-row">

                        <span>
                            Completion
                        </span>

                        <span>

                            <?= $result[
                                'attempt_status'
                            ] === 'Auto Submitted'
                                ? 'Automatic submission'
                                : 'Manual submission'
                            ?>

                        </span>

                    </div>

                </div>

            </div>


            <div class="col-lg-6">

                <div class="info-box">

                    <h4>

                        <i
                            class="
                                fa-solid
                                fa-bullseye
                                me-2
                            "
                        ></i>

                        Performance

                    </h4>


                    <div class="info-row">

                        <span>
                            Passing marks
                        </span>

                        <span>

                            <?= result_number(
                                $result[
                                    'passing_marks'
                                ]
                            ) ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span>
                            Your marks
                        </span>

                        <span>

                            <?= result_number(
                                $obtainedMarks
                            ) ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span>
                            Percentage
                        </span>

                        <span>

                            <?= result_number(
                                $percentage
                            ) ?>%

                        </span>

                    </div>


                    <div class="info-row">

                        <span>
                            Grade
                        </span>

                        <span>

                            <?= result_escape(
                                $result['grade']
                            ) ?>

                        </span>

                    </div>

                </div>

            </div>


        </div>

    </section>


    <!-- =====================================================
         QUESTION ANALYSIS
    ====================================================== -->

    <section class="section-card">

        <div class="section-heading">

            <div>

                <small>
                    DETAILED REVIEW
                </small>

                <h3>
                    Question analysis
                </h3>

            </div>


            <span>

                <?= count(
                    $analysis
                ) ?>

                questions

            </span>

        </div>


        <?php if (
            empty($analysis)
        ): ?>

            <div class="empty-analysis">

                <i
                    class="
                        fa-solid
                        fa-chart-column
                    "
                ></i>


                <div>

                    No detailed question analysis
                    is available for this result.

                </div>

            </div>

        <?php else: ?>


            <?php foreach (
                $analysis
                as $index => $question
            ): ?>


                <?php

                $selectedAnswer =
                    strtoupper(
                        trim(
                            (string) (
                                $question[
                                    'selected_answer'
                                ]
                                ??
                                ''
                            )
                        )
                    );


                $correctAnswer =
                    strtoupper(
                        trim(
                            (string) (
                                $question[
                                    'correct_answer'
                                ]
                                ??
                                ''
                            )
                        )
                    );


                $validOptions = [
                    'A',
                    'B',
                    'C',
                    'D'
                ];


                $isAnswered =
                    in_array(
                        $selectedAnswer,
                        $validOptions,
                        true
                    );


                $isCorrect =
                    (
                        (int) (
                            $question[
                                'is_correct'
                            ]
                            ??
                            0
                        )
                        === 1
                    );


                $marksAwarded =
                    (float) (
                        $question[
                            'marks_awarded'
                        ]
                        ??
                        0
                    );


                $selectedText =
                    $isAnswered

                        ? result_option_text(
                            $question,
                            $selectedAnswer
                        )

                        : 'Not answered';


                $correctText =
                    in_array(
                        $correctAnswer,
                        $validOptions,
                        true
                    )

                        ? result_option_text(
                            $question,
                            $correctAnswer
                        )

                        : '';


                ?>



                <article class="question-analysis">


                    <div class="question-analysis-top">


                        <div class="question-analysis-left">

                            <div class="question-number">

                                <?= $index + 1 ?>

                            </div>


                            <div class="question-title">

                                <?= nl2br(
                                    result_escape(
                                        $question[
                                            'question_text'
                                        ]
                                    )
                                ) ?>

                            </div>

                        </div>


                        <div>

                            <?php if (
                                $isCorrect
                            ): ?>

                                <span
                                    class="
                                        question-result
                                        correct-result
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-check
                                        "
                                    ></i>

                                    Correct

                                </span>

                            <?php elseif (
                                $isAnswered
                            ): ?>

                                <span
                                    class="
                                        question-result
                                        wrong-result
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-xmark
                                        "
                                    ></i>

                                    Wrong

                                </span>

                            <?php else: ?>

                                <span
                                    class="
                                        question-result
                                        unanswered-result
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-minus
                                        "
                                    ></i>

                                    Unanswered

                                </span>

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="question-analysis-body">


                        <div class="answer-line">

                            <strong>
                                Your answer:
                            </strong>


                            <?php if (
                                $isAnswered
                            ): ?>

                                <span
                                    class="<?= $isCorrect
                                        ? 'answer-correct'
                                        : 'answer-wrong'
                                    ?>"
                                >

                                    <?= result_escape(
                                        $selectedAnswer
                                    ) ?>

                                    —

                                    <?= result_escape(
                                        $selectedText
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span
                                    class="answer-neutral"
                                >
                                    Not answered
                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="answer-line">

                            <strong>
                                Correct answer:
                            </strong>


                            <?php if (
                                $correctAnswer !== ''
                            ): ?>

                                <span
                                    class="
                                        answer-correct
                                    "
                                >

                                    <?= result_escape(
                                        $correctAnswer
                                    ) ?>

                                    —

                                    <?= result_escape(
                                        $correctText
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span
                                    class="
                                        answer-neutral
                                    "
                                >
                                    Not available
                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="answer-line">

                            <strong>
                                Marks:
                            </strong>


                            <span
                                class="marks-earned"
                            >

                                <?= result_number(
                                    $marksAwarded
                                ) ?>

                            </span>

                        </div>


                        <?php if (
                            !empty(
                                $question[
                                    'question_status'
                                ]
                            )
                        ): ?>

                            <div class="answer-line">

                                <strong>
                                    Question status:
                                </strong>


                                <span
                                    class="answer-neutral"
                                >

                                    <?= result_escape(
                                        $question[
                                            'question_status'
                                        ]
                                    ) ?>

                                </span>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            trim(
                                (string) (
                                    $question[
                                        'explanation'
                                    ]
                                    ??
                                    ''
                                )
                            ) !== ''
                        ): ?>

                            <div class="explanation">

                                <strong>

                                    <i
                                        class="
                                            fa-solid
                                            fa-lightbulb
                                            me-1
                                        "
                                    ></i>

                                    Explanation

                                </strong>


                                <br>


                                <?= nl2br(
                                    result_escape(
                                        $question[
                                            'explanation'
                                        ]
                                    )
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                </article>


            <?php endforeach; ?>

        <?php endif; ?>


        <p class="footer-note">

            Your final marks and result status are calculated
            by ExamSphere's server-side examination engine.

        </p>

    </section>


</main>


<script
    src="assets/js/result.js"
></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const emailButton = document.getElementById('emailResult');

    if (emailButton) {
        emailButton.addEventListener('click', function () {
            const attemptId = this.dataset.attemptId || '';
            const token = this.dataset.csrfToken || '';
            const params = new URLSearchParams();
            if (attemptId) params.set('attempt_id', attemptId);
            if (token) params.set('csrf_token', token);
            window.location.href = 'ajax/send_result_email.php?' + params.toString();
        });
    }

    document.querySelectorAll('.btn-print').forEach(function (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    });
});
</script>


</body>

</html>