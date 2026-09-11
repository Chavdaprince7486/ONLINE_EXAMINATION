<?php

declare(strict_types=1);


require_once '../config/config.php';
require_once '../config/pdf.php';


/*
|--------------------------------------------------------------------------
| Input
|--------------------------------------------------------------------------
*/

$resultId =
    filter_input(
        INPUT_GET,
        'result_id',
        FILTER_VALIDATE_INT
    );


$expires =
    filter_input(
        INPUT_GET,
        'expires',
        FILTER_VALIDATE_INT
    );


$signature =
    trim(
        (string)(
            $_GET['signature'] ?? ''
        )
    );


/*
|--------------------------------------------------------------------------
| Basic validation
|--------------------------------------------------------------------------
*/

if (
    $resultId === false ||
    $resultId === null ||
    $resultId <= 0 ||
    $expires === false ||
    $expires === null ||
    $expires <= 0 ||
    $signature === ''
) {

    http_response_code(403);

    exit(
        'Invalid PDF access request.'
    );
}


/*
|--------------------------------------------------------------------------
| Expiration
|--------------------------------------------------------------------------
*/

if (
    $expires < time()
) {

    http_response_code(403);

    exit(
        'PDF access link has expired.'
    );
}


/*
|--------------------------------------------------------------------------
| Signature
|--------------------------------------------------------------------------
*/

$expectedSignature =
    hash_hmac(
        'sha256',
        (string)$resultId .
        '|' .
        (string)$expires,
        PDF_ACCESS_SECRET
    );


if (
    !hash_equals(
        $expectedSignature,
        $signature
    )
) {

    http_response_code(403);

    exit(
        'Invalid PDF signature.'
    );
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function pdf_view_escape(
    ?string $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function pdf_view_number(
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
| Load result
|--------------------------------------------------------------------------
*/

try {

    $statement =
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

                ea.started_at,
                ea.submitted_at,
                ea.status AS attempt_status,

                e.title AS exam_title,
                e.description AS exam_description,
                e.exam_type,

                e.duration_minutes,
                e.required_question_count,
                e.passing_marks,
                e.negative_marking,

                s.full_name,
                s.student_code,
                s.email,

                sub.name AS subject_name,
                sub.code AS subject_code

            FROM results r

            INNER JOIN exam_attempts ea
                ON ea.id = r.attempt_id

            INNER JOIN exams e
                ON e.id = r.exam_id

            INNER JOIN students s
                ON s.id = r.student_id

            LEFT JOIN subjects sub
                ON sub.id = e.subject_id

            WHERE
                r.id = ?

            LIMIT 1
        ");


    $statement->execute([
        $resultId
    ]);


    $result =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $exception
) {

    error_log(
        'PDF result query failed: ' .
        $exception->getMessage()
    );


    http_response_code(500);

    exit(
        'Unable to load result.'
    );
}


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
| Values
|--------------------------------------------------------------------------
*/

$totalQuestions =
    (int)$result[
        'total_questions'
    ];


$attemptedQuestions =
    (int)$result[
        'attempted_questions'
    ];


$correctAnswers =
    (int)$result[
        'correct_answers'
    ];


$wrongAnswers =
    (int)$result[
        'wrong_answers'
    ];


$unansweredQuestions =
    (int)$result[
        'unanswered_questions'
    ];


$totalMarks =
    (float)$result[
        'total_marks'
    ];


$obtainedMarks =
    (float)$result[
        'obtained_marks'
    ];


$percentage =
    (float)$result[
        'percentage'
    ];


$passingMarks =
    (float)$result[
        'passing_marks'
    ];


$accuracy =
    $attemptedQuestions > 0
        ? round(
            (
                $correctAnswers /
                $attemptedQuestions
            ) * 100,
            2
        )
        : 0;


$negativeMarking =
    (int)$result[
        'negative_marking'
    ] === 1;


$isPassed =
    $result[
        'result_status'
    ] === 'Pass';


/*
|--------------------------------------------------------------------------
| Time taken
|--------------------------------------------------------------------------
*/

$timeTakenMinutes =
    null;


try {

    if (
        !empty(
            $result['started_at']
        ) &&
        !empty(
            $result['submitted_at']
        )
    ) {

        $started =
            new DateTimeImmutable(
                $result['started_at']
            );


        $submitted =
            new DateTimeImmutable(
                $result['submitted_at']
            );


        $seconds =
            max(
                0,
                $submitted->getTimestamp() -
                $started->getTimestamp()
            );


        $timeTakenMinutes =
            (int)ceil(
                $seconds / 60
            );
    }

} catch (
    Throwable $exception
) {
}


/*
|--------------------------------------------------------------------------
| Performance
|--------------------------------------------------------------------------
*/

if (
    $percentage >= 90
) {

    $performanceTitle =
        'Outstanding Performance';


    $performanceMessage =
        'Excellent work. Your performance is at a very strong level.';

} elseif (
    $percentage >= 75
) {

    $performanceTitle =
        'Excellent Performance';


    $performanceMessage =
        'You performed very well. Keep this consistency going.';

} elseif (
    $percentage >= 60
) {

    $performanceTitle =
        'Very Good Performance';


    $performanceMessage =
        'You have a good foundation. A little more practice can push you higher.';

} elseif (
    $percentage >= 40
) {

    $performanceTitle =
        'Good Effort';


    $performanceMessage =
        'Review your mistakes and keep improving.';

} else {

    $performanceTitle =
        'Keep Improving';


    $performanceMessage =
        'Use this result to identify weak areas and prepare for the next attempt.';
}

?>
<!doctype html>

<html lang="en">

<head>

    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >


    <title>
        ExamSphere Result
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


    <style>

        * {
            box-sizing:
                border-box;
        }


        html,
        body {

            margin:
                0;

            padding:
                0;

            background:
                #F7F6EE;

            color:
                #3E2723;

            font-family:
                Poppins,
                Arial,
                sans-serif;

            -webkit-print-color-adjust:
                exact;

            print-color-adjust:
                exact;
        }


        body {

            min-height:
                100vh;
        }


        .pdf-page {

            width:
                100%;

            max-width:
                1100px;

            margin:
                0 auto;

            padding:
                30px;
        }


        /* ==================================================
           BRAND
        ================================================== */

        .brand {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

            margin-bottom:
                18px;

            padding-bottom:
                14px;

            border-bottom:
                2px solid
                #E3DED5;
        }


        .brand-left {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;
        }


        .brand-logo {

            width:
                48px;

            height:
                48px;

            border-radius:
                12px;

            object-fit:
                contain;
        }


        .brand-title {

            color:
                #3E2723;

            font-size:
                20px;

            font-weight:
                800;

            line-height:
                1.1;
        }


        .brand-subtitle {

            margin-top:
                3px;

            color:
                #837B73;

            font-size:
                9px;
        }


        .report-label {

            color:
                #556B2F;

            font-size:
                9px;

            font-weight:
                800;

            letter-spacing:
                .10em;

            text-align:
                right;

            text-transform:
                uppercase;
        }


        /* ==================================================
           HERO
        ================================================== */

        .hero {

            display:
                grid;

            grid-template-columns:
                minmax(0,1fr)
                190px;

            gap:
                25px;

            padding:
                25px;

            overflow:
                hidden;

            border-radius:
                18px;

            background:
                linear-gradient(
                    120deg,
                    #3E2723,
                    #5D4037 55%,
                    #556B2F
                );

            color:
                #FFFFFF;

            page-break-inside:
                avoid;
        }


        .hero-kicker {

            color:
                #D9E4C5;

            font-size:
                8px;

            font-weight:
                800;

            letter-spacing:
                .14em;
        }


        .hero h1 {

            margin:
                8px 0 5px;

            color:
                #FFFFFF;

            font-size:
                25px;

            font-weight:
                800;

            line-height:
                1.16;
        }


        .hero-description {

            max-width:
                650px;

            margin:
                0;

            color:
                #E4DCD5;

            font-size:
                9px;

            line-height:
                1.7;
        }


        .hero-meta {

            display:
                flex;

            flex-wrap:
                wrap;

            gap:
                6px;

            margin-top:
                14px;
        }


        .hero-pill {

            padding:
                5px 8px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .15
                );

            border-radius:
                999px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .07
                );

            color:
                #E5DDD5;

            font-size:
                7px;
        }


        .score-circle {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            flex-direction:
                column;

            width:
                155px;

            height:
                155px;

            justify-self:
                center;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .17
                );

            border-radius:
                50%;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .08
                );
        }


        .score-circle strong {

            color:
                #FFFFFF;

            font-size:
                30px;

            line-height:
                1;
        }


        .score-circle small {

            margin-top:
                7px;

            color:
                #D7E2C4;

            font-size:
                7px;

            font-weight:
                700;

            letter-spacing:
                .08em;

            text-transform:
                uppercase;
        }


        /* ==================================================
           STATS
        ================================================== */

        .stats {

            display:
                grid;

            grid-template-columns:
                repeat(
                    4,
                    1fr
                );

            gap:
                9px;

            margin-top:
                12px;
        }


        .stat {

            padding:
                13px;

            border:
                1px solid
                #E3DED5;

            border-radius:
                12px;

            background:
                #FFFFFF;

            page-break-inside:
                avoid;
        }


        .stat small {

            display:
                block;

            color:
                #8D857D;

            font-size:
                7px;

            font-weight:
                700;

            text-transform:
                uppercase;
        }


        .stat strong {

            display:
                block;

            margin-top:
                5px;

            color:
                #3E2723;

            font-size:
                16px;
        }


        /* ==================================================
           STATUS
        ================================================== */

        .status-box {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            margin-top:
                12px;

            padding:
                14px 16px;

            border:
                1px solid
                #E2DDD5;

            border-radius:
                12px;

            background:
                #FFFFFF;

            page-break-inside:
                avoid;
        }


        .status-main {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;
        }


        .status-icon {

            display:
                grid;

            place-items:
                center;

            width:
                38px;

            height:
                38px;

            border-radius:
                11px;
        }


        .status-icon.pass {

            color:
                #556B2F;

            background:
                #EDF3E5;
        }


        .status-icon.fail {

            color:
                #98483E;

            background:
                #FAECE9;
        }


        .status-text small {

            display:
                block;

            color:
                #918980;

            font-size:
                7px;

            font-weight:
                700;
        }


        .status-text strong {

            display:
                block;

            margin-top:
                2px;

            color:
                #3E2723;

            font-size:
                10px;
        }


        .status-performance {

            color:
                #777069;

            font-size:
                8px;

            text-align:
                right;
        }


        /* ==================================================
           CONTENT
        ================================================== */

        .content {

            display:
                grid;

            grid-template-columns:
                minmax(0,1fr)
                320px;

            gap:
                12px;

            margin-top:
                12px;

            align-items:
                start;
        }


        .card {

            padding:
                17px;

            border:
                1px solid
                #E3DED5;

            border-radius:
                13px;

            background:
                #FFFFFF;

            page-break-inside:
                avoid;
        }


        .card + .card {

            margin-top:
                12px;
        }


        .card-title {

            margin:
                0 0 13px;

            color:
                #3E2723;

            font-size:
                13px;

            font-weight:
                700;
        }


        /* ==================================================
           BREAKDOWN
        ================================================== */

        .breakdown-row {

            display:
                grid;

            grid-template-columns:
                78px
                minmax(0,1fr)
                28px;

            align-items:
                center;

            gap:
                8px;

            margin-bottom:
                11px;
        }


        .breakdown-row:last-child {

            margin-bottom:
                0;
        }


        .breakdown-row label {

            color:
                #716960;

            font-size:
                8px;
        }


        .bar {

            height:
                7px;

            overflow:
                hidden;

            border-radius:
                999px;

            background:
                #ECE8E1;
        }


        .bar span {

            display:
                block;

            height:
                100%;

            border-radius:
                999px;

            background:
                #556B2F;
        }


        .breakdown-row strong {

            color:
                #3E2723;

            font-size:
                8px;

            text-align:
                right;
        }


        /* ==================================================
           DETAIL
        ================================================== */

        .detail-row {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                12px;

            padding:
                8px 0;

            border-bottom:
                1px solid
                #EEE9E1;
        }


        .detail-row:last-child {

            border-bottom:
                0;
        }


        .detail-row span {

            color:
                #8B837B;

            font-size:
                8px;
        }


        .detail-row strong {

            color:
                #4E4741;

            font-size:
                8px;

            text-align:
                right;
        }


        /* ==================================================
           NOTE
        ================================================== */

        .note {

            margin-top:
                12px;

            padding:
                13px;

            border:
                1px solid
                #DDE5D2;

            border-radius:
                11px;

            background:
                #F1F5E9;

            color:
                #687455;

            font-size:
                8px;

            line-height:
                1.65;

            page-break-inside:
                avoid;
        }


        /* ==================================================
           FOOTER
        ================================================== */

        .footer {

            margin-top:
                18px;

            padding-top:
                10px;

            border-top:
                1px solid
                #E3DED5;

            color:
                #958D85;

            font-size:
                7px;

            line-height:
                1.5;

            text-align:
                center;
        }


        @media print {

            body {

                background:
                    #F7F6EE !important;
            }


            .pdf-page {

                max-width:
                    none;

                padding:
                    0;
            }

        }


        @page {

            size:
                A4;

            margin:
                10mm;
        }

    </style>

</head>


<body>

<div class="pdf-page">


    <!-- =================================================
         BRAND
    ================================================== -->

    <div class="brand">

        <div class="brand-left">

            <?php

            /*
             * Change this path only if your actual logo is
             * located somewhere else.
             */
            $logoRelativePath =
                'assets/images/exam_logo.png';

            ?>

            <?php if (
                is_file(
                    __DIR__ .
                    DIRECTORY_SEPARATOR .
                    $logoRelativePath
                )
            ): ?>

                <img
                    src="<?= pdf_view_escape(
                        $logoRelativePath
                    ); ?>"
                    alt="ExamSphere"
                    class="brand-logo"
                >

            <?php endif; ?>


            <div>

                <div class="brand-title">
                    ExamSphere
                </div>


                <div class="brand-subtitle">
                    Smart • Secure • Instant Online Examination
                </div>

            </div>

        </div>


        <div class="report-label">

            Examination Result

            <br>

            Performance Report

        </div>

    </div>


    <!-- =================================================
         HERO
    ================================================== -->

    <section class="hero">


        <div>

            <div class="hero-kicker">

                EXAMSPHERE PERFORMANCE REPORT

            </div>


            <h1>

                <?= pdf_view_escape(
                    $result['exam_title']
                ); ?>

            </h1>


            <p class="hero-description">

                <?= pdf_view_escape(
                    $performanceMessage
                ); ?>

            </p>


            <div class="hero-meta">

                <span class="hero-pill">

                    <?= pdf_view_escape(
                        $result['full_name']
                    ); ?>

                </span>


                <?php if (
                    !empty(
                        $result['student_code']
                    )
                ): ?>

                    <span class="hero-pill">

                        ID:
                        <?= pdf_view_escape(
                            $result['student_code']
                        ); ?>

                    </span>

                <?php endif; ?>


                <?php if (
                    !empty(
                        $result['subject_name']
                    )
                ): ?>

                    <span class="hero-pill">

                        <?= pdf_view_escape(
                            $result['subject_name']
                        ); ?>

                    </span>

                <?php endif; ?>

            </div>

        </div>


        <div class="score-circle">

            <strong>

                <?= pdf_view_number(
                    $percentage
                ); ?>%

            </strong>


            <small>
                Final Score
            </small>

        </div>

    </section>


    <!-- =================================================
         STATS
    ================================================== -->

    <section class="stats">


        <div class="stat">

            <small>
                Obtained Marks
            </small>


            <strong>

                <?= pdf_view_number(
                    $obtainedMarks
                ); ?>

                /

                <?= pdf_view_number(
                    $totalMarks
                ); ?>

            </strong>

        </div>


        <div class="stat">

            <small>
                Correct
            </small>


            <strong>
                <?= $correctAnswers; ?>
            </strong>

        </div>


        <div class="stat">

            <small>
                Wrong
            </small>


            <strong>
                <?= $wrongAnswers; ?>
            </strong>

        </div>


        <div class="stat">

            <small>
                Accuracy
            </small>


            <strong>

                <?= pdf_view_number(
                    $accuracy
                ); ?>%

            </strong>

        </div>

    </section>


    <!-- =================================================
         STATUS
    ================================================== -->

    <section class="status-box">


        <div class="status-main">

            <div
                class="
                    status-icon
                    <?= $isPassed
                        ? 'pass'
                        : 'fail'
                    ?>
                "
            >

                <span>

                    <?= $isPassed
                        ? '✓'
                        : '×'
                    ?>

                </span>

            </div>


            <div class="status-text">

                <small>
                    RESULT STATUS
                </small>


                <strong>

                    <?= pdf_view_escape(
                        $result['result_status']
                    ); ?>

                    · Grade

                    <?= pdf_view_escape(
                        $result['grade']
                    ); ?>

                </strong>

            </div>

        </div>


        <div class="status-performance">

            <?= pdf_view_escape(
                $performanceTitle
            ); ?>

        </div>

    </section>


    <!-- =================================================
         CONTENT
    ================================================== -->

    <section class="content">


        <!-- LEFT -->

        <div>


            <!-- QUESTION BREAKDOWN -->

            <div class="card">

                <h2 class="card-title">
                    Question Breakdown
                </h2>


                <div class="breakdown-row">

                    <label>
                        Attempted
                    </label>


                    <div class="bar">

                        <span
                            style="
                                width:
                                <?= $totalQuestions > 0
                                    ? min(
                                        100,
                                        (
                                            $attemptedQuestions /
                                            $totalQuestions
                                        ) * 100
                                    )
                                    : 0
                                ?>%;
                            "
                        ></span>

                    </div>


                    <strong>
                        <?= $attemptedQuestions; ?>
                    </strong>

                </div>


                <div class="breakdown-row">

                    <label>
                        Correct
                    </label>


                    <div class="bar">

                        <span
                            style="
                                width:
                                <?= $totalQuestions > 0
                                    ? min(
                                        100,
                                        (
                                            $correctAnswers /
                                            $totalQuestions
                                        ) * 100
                                    )
                                    : 0
                                ?>%;
                            "
                        ></span>

                    </div>


                    <strong>
                        <?= $correctAnswers; ?>
                    </strong>

                </div>


                <div class="breakdown-row">

                    <label>
                        Wrong
                    </label>


                    <div class="bar">

                        <span
                            style="
                                width:
                                <?= $totalQuestions > 0
                                    ? min(
                                        100,
                                        (
                                            $wrongAnswers /
                                            $totalQuestions
                                        ) * 100
                                    )
                                    : 0
                                ?>%;
                                background:
                                #9A554A;
                            "
                        ></span>

                    </div>


                    <strong>
                        <?= $wrongAnswers; ?>
                    </strong>

                </div>


                <div class="breakdown-row">

                    <label>
                        Unanswered
                    </label>


                    <div class="bar">

                        <span
                            style="
                                width:
                                <?= $totalQuestions > 0
                                    ? min(
                                        100,
                                        (
                                            $unansweredQuestions /
                                            $totalQuestions
                                        ) * 100
                                    )
                                    : 0
                                ?>%;
                                background:
                                #B49D72;
                            "
                        ></span>

                    </div>


                    <strong>
                        <?= $unansweredQuestions; ?>
                    </strong>

                </div>

            </div>


            <!-- PERFORMANCE -->

            <div class="card">

                <h2 class="card-title">
                    Performance
                </h2>


                <div class="detail-row">

                    <span>
                        Percentage
                    </span>


                    <strong>

                        <?= pdf_view_number(
                            $percentage
                        ); ?>%

                    </strong>

                </div>


                <div class="detail-row">

                    <span>
                        Accuracy
                    </span>


                    <strong>

                        <?= pdf_view_number(
                            $accuracy
                        ); ?>%

                    </strong>

                </div>


                <div class="detail-row">

                    <span>
                        Grade
                    </span>


                    <strong>

                        <?= pdf_view_escape(
                            $result['grade']
                        ); ?>

                    </strong>

                </div>


                <div class="detail-row">

                    <span>
                        Result
                    </span>


                    <strong>

                        <?= pdf_view_escape(
                            $result['result_status']
                        ); ?>

                    </strong>

                </div>


                <div class="detail-row">

                    <span>
                        Performance
                    </span>


                    <strong>

                        <?= pdf_view_escape(
                            $performanceTitle
                        ); ?>

                    </strong>

                </div>

            </div>

        </div>


        <!-- RIGHT -->

        <div>


            <!-- EXAM DETAILS -->

            <div class="card">

                <h2 class="card-title">
                    Examination Details
                </h2>


                <div class="detail-row">

                    <span>
                        Exam Type
                    </span>


                    <strong>

                        <?= pdf_view_escape(
                            $result['exam_type']
                        ); ?>

                    </strong>

                </div>


                <div class="detail-row">

                    <span>
                        Total Questions
                    </span>


                    <strong>
                        <?= $totalQuestions; ?>
                    </strong>

                </div>


                <div class="detail-row">

                    <span>
                        Duration
                    </span>


                    <strong>

                        <?= (int)(
                            $result[
                                'duration_minutes'
                            ]
                        ); ?>

                        min

                    </strong>

                </div>


                <div class="detail-row">

                    <span>
                        Passing Marks
                    </span>


                    <strong>

                        <?= pdf_view_number(
                            $passingMarks
                        ); ?>

                    </strong>

                </div>


                <div class="detail-row">

                    <span>
                        Negative Marking
                    </span>


                    <strong>

                        <?= $negativeMarking
                            ? 'Enabled'
                            : 'Disabled'
                        ?>

                    </strong>

                </div>


                <div class="detail-row">

                    <span>
                        Attempt ID
                    </span>


                    <strong>

                        #<?= (int)(
                            $result[
                                'attempt_id'
                            ]
                        ); ?>

                    </strong>

                </div>


                <?php if (
                    $timeTakenMinutes !== null
                ): ?>

                    <div class="detail-row">

                        <span>
                            Time Taken
                        </span>


                        <strong>

                            <?php if (
                                $timeTakenMinutes >= 60
                            ): ?>

                                <?= intdiv(
                                    $timeTakenMinutes,
                                    60
                                ); ?>

                                h

                                <?= $timeTakenMinutes % 60; ?>

                                min

                            <?php else: ?>

                                <?= $timeTakenMinutes; ?>

                                min

                            <?php endif; ?>

                        </strong>

                    </div>

                <?php endif; ?>

            </div>


            <!-- STUDENT -->

            <div class="card">

                <h2 class="card-title">
                    Student Information
                </h2>


                <div class="detail-row">

                    <span>
                        Name
                    </span>


                    <strong>

                        <?= pdf_view_escape(
                            $result['full_name']
                        ); ?>

                    </strong>

                </div>


                <div class="detail-row">

                    <span>
                        Student Code
                    </span>


                    <strong>

                        <?= pdf_view_escape(
                            $result['student_code']
                        ); ?>

                    </strong>

                </div>


                <div class="detail-row">

                    <span>
                        Subject
                    </span>


                    <strong>

                        <?= pdf_view_escape(
                            $result['subject_name']
                            ?: 'General'
                        ); ?>

                    </strong>

                </div>


                <div class="detail-row">

                    <span>
                        Email
                    </span>


                    <strong>

                        <?= pdf_view_escape(
                            $result['email']
                        ); ?>

                    </strong>

                </div>

            </div>

        </div>

    </section>


    <!-- =================================================
         NOTE
    ================================================== -->

    <div class="note">

        <strong>
            ExamSphere Performance Note:
        </strong>

        <?= pdf_view_escape(
            $performanceMessage
        ); ?>

        This report is generated from the final result
        recorded for the completed examination attempt.

    </div>


    <!-- =================================================
         FOOTER
    ================================================== -->

    <div class="footer">

        ExamSphere · Secure Examination Platform

        &nbsp;•&nbsp;

        Attempt #<?= (int)(
            $result['attempt_id']
        ); ?>

        &nbsp;•&nbsp;

        Generated
        <?= date(
            'd M Y, h:i A'
        ); ?>

    </div>


</div>

</body>

</html>