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


$studentId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| RESULT ID
|--------------------------------------------------------------------------
*/

$resultId = filter_input(
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
    exit('Invalid result.');
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['csrf_token'])
) {
    try {
        $_SESSION['csrf_token'] =
            bin2hex(
                random_bytes(32)
            );
    } catch (Throwable) {
        $_SESSION['csrf_token'] =
            hash(
                'sha256',
                uniqid(
                    '',
                    true
                )
            );
    }
}


if (
    empty($_SESSION['exam_csrf_token'])
) {
    $_SESSION['exam_csrf_token'] =
        $_SESSION['csrf_token'];
}


$csrfToken =
    (string) $_SESSION['exam_csrf_token'];


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
    mixed $value
): string {

    $number =
        round(
            (float) $value,
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


function result_time_label(
    int $seconds
): string {

    $seconds =
        max(
            0,
            $seconds
        );


    $hours =
        intdiv(
            $seconds,
            3600
        );


    $minutes =
        intdiv(
            $seconds % 3600,
            60
        );


    $remainingSeconds =
        $seconds % 60;


    if (
        $hours > 0
    ) {

        return
            $hours .
            'h ' .
            $minutes .
            'm';

    }


    if (
        $minutes > 0
    ) {

        return
            $minutes .
            'm ' .
            $remainingSeconds .
            's';
    }


    return
        $remainingSeconds .
        's';
}


function result_option_text(
    array $question,
    string $answer
): string {

    $answer =
        strtoupper(
            trim(
                $answer
            )
        );


    $map = [

        'A' =>
            $question['option_a']
            ?? '',

        'B' =>
            $question['option_b']
            ?? '',

        'C' =>
            $question['option_c']
            ?? '',

        'D' =>
            $question['option_d']
            ?? ''

    ];


    if (
        !isset(
            $map[$answer]
        )
    ) {
        return '';
    }


    return trim(
        (string) $map[$answer]
    );
}


/*
|--------------------------------------------------------------------------
| LOAD RESULT
|--------------------------------------------------------------------------
*/

try {

    $resultStatement =
        $conn->prepare(
            "
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
                e.description AS exam_description,

                e.exam_type,
                e.duration_minutes,

                e.required_question_count,

                e.total_marks AS exam_configured_total_marks,

                e.passing_marks,

                e.negative_marking,

                e.exam_fee,
                e.subscription_required,

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
            "
        );


    $resultStatement->execute(
        [
            $resultId,
            $studentId
        ]
    );


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
| FINALIZED ATTEMPT
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
        $conn->prepare(
            "
            SELECT

                q.id AS question_id,

                eq.position,

                q.question_text,

                q.question_image,

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

                AND q.status = 'Active'

            ORDER BY

                eq.position ASC,
                q.id ASC
            "
        );


    $analysisStatement->execute(
        [
            (int) $result['attempt_id'],
            (int) $result['exam_id']
        ]
    );


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
| REMOVE DUPLICATES
|--------------------------------------------------------------------------
*/

$uniqueAnalysis = [];


foreach (
    $analysis as $question
) {

    $questionId =
        (int) (
            $question['question_id']
            ?? 0
        );


    if (
        $questionId <= 0
    ) {
        continue;
    }


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

$totalQuestions =
    max(
        0,
        (int) (
            $result['total_questions']
            ?? 0
        )
    );


$requiredQuestionCount =
    max(
        0,
        (int) (
            $result['required_question_count']
            ?? 0
        )
    );


if (
    $requiredQuestionCount > 0
    &&
    $totalQuestions !==
    $requiredQuestionCount
) {

    http_response_code(409);

    exit(
        'The result question configuration is inconsistent.'
    );
}


$attemptedQuestions =
    max(
        0,
        (int) (
            $result['attempted_questions']
            ?? 0
        )
    );


$correctAnswers =
    max(
        0,
        (int) (
            $result['correct_answers']
            ?? 0
        )
    );


$wrongAnswers =
    max(
        0,
        (int) (
            $result['wrong_answers']
            ?? 0
        )
    );


$unansweredQuestions =
    max(
        0,
        (int) (
            $result['unanswered_questions']
            ?? 0
        )
    );


$totalMarks =
    max(
        0,
        round(
            (float) (
                $result['total_marks']
                ?? 0
            ),
            2
        )
    );


$obtainedMarks =
    round(
        (float) (
            $result['obtained_marks']
            ?? 0
        ),
        2
    );


$obtainedMarks =
    max(
        0,
        min(
            $totalMarks,
            $obtainedMarks
        )
    );


$percentage =
    max(
        0,
        min(
            100,
            round(
                (float) (
                    $result['percentage']
                    ?? 0
                ),
                2
            )
        )
    );


$passingMarks =
    max(
        0,
        min(
            $totalMarks,
            round(
                (float) (
                    $result['passing_marks']
                    ?? 0
                ),
                2
            )
        )
    );


$grade =
    trim(
        (string) (
            $result['grade']
            ?? ''
        )
    );


$resultStatus =
    trim(
        (string) (
            $result['result_status']
            ?? ''
        )
    );


$isPassed =
    $resultStatus === 'Pass';


/*
|--------------------------------------------------------------------------
| DYNAMIC MARKS
|--------------------------------------------------------------------------
*/

$marksPerQuestion =
    null;


foreach (
    $analysis as $question
) {

    $questionMarks =
        round(
            (float) (
                $question['marks']
                ?? 0
            ),
            2
        );


    if (
        $questionMarks <= 0
    ) {
        continue;
    }


    if (
        $marksPerQuestion === null
    ) {

        $marksPerQuestion =
            $questionMarks;

    } elseif (
        abs(
            $marksPerQuestion -
            $questionMarks
        ) > 0.00001
    ) {

        http_response_code(409);

        exit(
            'The result contains inconsistent per-question marks.'
        );
    }
}


if (
    $marksPerQuestion === null
    &&
    $totalQuestions > 0
) {

    $marksPerQuestion =
        round(
            $totalMarks /
            $totalQuestions,
            2
        );
}


if (
    $marksPerQuestion === null
) {

    $marksPerQuestion =
        0;
}


$calculatedTotalMarks =
    round(
        $totalQuestions *
        $marksPerQuestion,
        2
    );


if (
    $totalQuestions > 0
    &&
    abs(
        $calculatedTotalMarks -
        $totalMarks
    ) > 0.01
) {

    http_response_code(409);

    exit(
        'The result total marks do not match the configured question marks.'
    );
}


/*
|--------------------------------------------------------------------------
| METRICS
|--------------------------------------------------------------------------
*/

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


$completion =
    $totalQuestions > 0
        ? round(
            (
                $attemptedQuestions /
                $totalQuestions
            ) * 100,
            2
        )
        : 0;


$correctPercentage =
    $totalQuestions > 0
        ? round(
            (
                $correctAnswers /
                $totalQuestions
            ) * 100,
            2
        )
        : 0;


$wrongPercentage =
    $totalQuestions > 0
        ? round(
            (
                $wrongAnswers /
                $totalQuestions
            ) * 100,
            2
        )
        : 0;


$unansweredPercentage =
    $totalQuestions > 0
        ? round(
            (
                $unansweredQuestions /
                $totalQuestions
            ) * 100,
            2
        )
        : 0;


/*
|--------------------------------------------------------------------------
| TIME USED
|--------------------------------------------------------------------------
*/

$timeUsedSeconds =
    0;


try {

    if (
        !empty(
            $result['started_at']
        )
        &&
        !empty(
            $result['submitted_at']
        )
    ) {

        $startedAt =
            new DateTimeImmutable(
                (string) $result[
                    'started_at'
                ]
            );


        $submittedAt =
            new DateTimeImmutable(
                (string) $result[
                    'submitted_at'
                ]
            );


        $timeUsedSeconds =
            max(
                0,
                $submittedAt->getTimestamp()
                -
                $startedAt->getTimestamp()
            );
    }

} catch (Throwable) {

    $timeUsedSeconds =
        0;
}


$timeUsedLabel =
    result_time_label(
        $timeUsedSeconds
    );


$durationMinutes =
    max(
        0,
        (int) (
            $result['duration_minutes']
            ?? 0
        )
    );


$durationSeconds =
    $durationMinutes * 60;


$timeEfficiency =
    $durationSeconds > 0
        ? round(
            min(
                100,
                (
                    $timeUsedSeconds /
                    $durationSeconds
                ) * 100
            ),
            1
        )
        : 0;


/*
|--------------------------------------------------------------------------
| PERFORMANCE LABEL
|--------------------------------------------------------------------------
*/

$performanceLabel =
    match (true) {

        $percentage >= 90 =>
            'Outstanding',

        $percentage >= 80 =>
            'Excellent',

        $percentage >= 70 =>
            'Very Good',

        $percentage >= 60 =>
            'Good',

        $percentage >= 50 =>
            'Fair',

        $percentage >= 40 =>
            'Needs Improvement',

        default =>
            'Needs More Practice'
    };


/*
|--------------------------------------------------------------------------
| PASS / FAIL PRESENTATION
|--------------------------------------------------------------------------
*/

$statusLabel =
    $isPassed
        ? 'PASS'
        : 'FAIL';


$statusIcon =
    $isPassed
        ? 'fa-circle-check'
        : 'fa-circle-xmark';


$statusClass =
    $isPassed
        ? 'is-pass'
        : 'is-fail';


$statusMessage =
    $isPassed
        ? 'Congratulations! You passed this examination.'
        : 'This attempt did not reach the configured passing marks.';


$statusSubmessage =
    $isPassed
        ? 'Your performance met or exceeded the required passing score.'
        : 'Use your detailed analysis below to improve your next attempt.';


/*
|--------------------------------------------------------------------------
| EXAM DATA
|--------------------------------------------------------------------------
*/

$examTitle =
    trim(
        (string) (
            $result['exam_title']
            ?? 'Examination'
        )
    );


$examType =
    trim(
        (string) (
            $result['exam_type']
            ?? 'Examination'
        )
    );


$subjectName =
    trim(
        (string) (
            $result['subject_name']
            ?? 'General'
        )
    );


$subjectCode =
    trim(
        (string) (
            $result['subject_code']
            ?? ''
        )
    );


$resultDate =
    result_date(
        $result['created_at']
    );


$submittedDate =
    result_date(
        $result['submitted_at']
    );


$examDescription =
    trim(
        (string) (
            $result['exam_description']
            ?? ''
        )
    );


/*
|--------------------------------------------------------------------------
| ANALYSIS COUNTERS
|--------------------------------------------------------------------------
*/

$analysisCorrect = 0;
$analysisWrong = 0;
$analysisUnanswered = 0;


foreach (
    $analysis as $question
) {

    $selected =
        strtoupper(
            trim(
                (string) (
                    $question[
                        'selected_answer'
                    ] ?? ''
                )
            )
        );


    if (
        !in_array(
            $selected,
            [
                'A',
                'B',
                'C',
                'D'
            ],
            true
        )
    ) {

        $analysisUnanswered++;

    } elseif (
        (int) (
            $question[
                'is_correct'
            ] ?? 0
        ) === 1
    ) {

        $analysisCorrect++;

    } else {

        $analysisWrong++;
    }
}


$analysisTotal =
    count(
        $analysis
    );


?>

<!DOCTYPE html>

<html
    lang="en"
>

<head>

    <meta
        charset="UTF-8"
    >

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#5D4037"
    >

    <title>

        <?= result_escape(
            $examTitle
        ); ?>

        | Result | ExamSphere

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
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        rel="stylesheet"
    >


    <style>

        :root {

            --brown-dark:
                #3E2723;

            --brown:
                #5D4037;

            --brown-soft:
                #795548;

            --olive:
                #556B2F;

            --olive-dark:
                #465925;

            --cream:
                #F5F5DC;

            --cream-light:
                #FAF9F4;

            --white:
                #FFFFFF;

            --text:
                #302A26;

            --muted:
                #7C736C;

            --border:
                #E4DED3;

            --green:
                #2E7D52;

            --green-bg:
                #EAF4E4;

            --red:
                #B84A42;

            --red-bg:
                #FBEAE7;

            --gold:
                #B48735;

            --gold-bg:
                #FFF4DD;

            --purple:
                #70579B;

            --purple-bg:
                #F0EAF8;

            --shadow:
                0 22px 65px
                rgba(
                    62,
                    39,
                    35,
                    .10
                );

            --radius:
                24px;
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

            min-height:
                100vh;

            background:

                radial-gradient(
                    circle at 8% 4%,
                    rgba(
                        85,
                        107,
                        47,
                        .09
                    ),
                    transparent 25%
                ),

                radial-gradient(
                    circle at 94% 8%,
                    rgba(
                        93,
                        64,
                        55,
                        .10
                    ),
                    transparent 25%
                ),

                linear-gradient(
                    180deg,
                    #F7F6E9 0%,
                    #F5F5DC 42%,
                    #F2F0E5 100%
                );

            color:
                var(--text);

            font-family:
                Poppins,
                Arial,
                sans-serif;
        }


        a {
            text-decoration:
                none;
        }


        button,
        a {
            -webkit-tap-highlight-color:
                transparent;
        }


        .result-shell {

            width:
                min(
                    1440px,
                    calc(
                        100% - 36px
                    )
                );

            margin:
                22px auto 50px;
        }


        /*
        |--------------------------------------------------------------------------
        | TOP NAV
        |--------------------------------------------------------------------------
        */

        .result-nav {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                18px;

            min-height:
                68px;

            padding:
                12px 16px 12px 18px;

            border:
                1px solid
                var(--border);

            border-radius:
                20px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .88
                );

            box-shadow:
                var(--shadow);

            backdrop-filter:
                blur(
                    18px
                );

            position:
                sticky;

            top:
                12px;

            z-index:
                100;
        }


        .brand {

            display:
                flex;

            align-items:
                center;

            gap:
                11px;
        }


        .brand-icon {

            width:
                43px;

            height:
                43px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                14px;

            color:
                var(--white);

            background:
                linear-gradient(
                    145deg,
                    var(--brown),
                    var(--brown-dark)
                );

            box-shadow:
                0 10px 25px
                rgba(
                    62,
                    39,
                    35,
                    .18
                );
        }


        .brand strong {

            display:
                block;

            color:
                var(--brown-dark);

            font-size:
                14px;

            font-weight:
                800;
        }


        .brand small {

            display:
                block;

            color:
                var(--muted);

            font-size:
                9px;

            margin-top:
                1px;
        }


        .nav-actions {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            flex-wrap:
                wrap;

            justify-content:
                flex-end;
        }


        .nav-btn {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                7px;

            min-height:
                40px;

            padding:
                0 13px;

            border:
                1px solid
                var(--border);

            border-radius:
                12px;

            color:
                var(--brown-dark);

            background:
                var(--white);

            font-size:
                10px;

            font-weight:
                700;

            transition:
                .2s ease;
        }


        .nav-btn:hover {

            color:
                var(--brown-dark);

            transform:
                translateY(
                    -1px
                );

            box-shadow:
                0 10px 22px
                rgba(
                    62,
                    39,
                    35,
                    .08
                );
        }


        .nav-btn.primary {

            color:
                var(--white);

            border-color:
                var(--brown);

            background:
                linear-gradient(
                    135deg,
                    var(--brown),
                    var(--brown-dark)
                );
        }


        .nav-btn.primary:hover {

            color:
                var(--white);
        }


        /*
        |--------------------------------------------------------------------------
        | HERO
        |--------------------------------------------------------------------------
        */

        .result-hero {

            position:
                relative;

            overflow:
                hidden;

            margin-top:
                20px;

            padding:
                28px;

            border-radius:
                30px;

            color:
                var(--white);

            background:
                linear-gradient(
                    135deg,
                    #5D4037 0%,
                    #4A302A 56%,
                    #3E2723 100%
                );

            box-shadow:
                0 28px 70px
                rgba(
                    62,
                    39,
                    35,
                    .19
                );
        }


        .result-hero::before {

            content:
                '';

            position:
                absolute;

            width:
                260px;

            height:
                260px;

            right:
                -80px;

            top:
                -100px;

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


        .result-hero::after {

            content:
                '';

            position:
                absolute;

            width:
                180px;

            height:
                180px;

            left:
                42%;

            bottom:
                -120px;

            border-radius:
                50%;

            background:
                rgba(
                    85,
                    107,
                    47,
                    .18
                );
        }


        .hero-content {

            position:
                relative;

            z-index:
                2;
        }


        .hero-top {

            display:
                flex;

            align-items:
                flex-start;

            justify-content:
                space-between;

            gap:
                24px;
        }


        .hero-kicker {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            padding:
                7px 10px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .18
                );

            border-radius:
                10px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .08
                );

            color:
                #E8DECE;

            font-size:
                9px;

            font-weight:
                800;

            letter-spacing:
                1.1px;

            text-transform:
                uppercase;
        }


        .hero-title {

            margin:
                12px 0 6px;

            max-width:
                900px;

            color:
                #FFFFFF;

            font-size:
                clamp(
                    25px,
                    4vw,
                    42px
                );

            line-height:
                1.18;

            font-weight:
                800;
        }


        .hero-meta {

            display:
                flex;

            align-items:
                center;

            flex-wrap:
                wrap;

            gap:
                8px 13px;

            color:
                #EDE8DF;

            font-size:
                11px;

            font-weight:
                500;
        }


        .hero-meta-item {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;
        }


        .hero-meta-divider {

            opacity:
                .45;
        }


        .hero-status {

            min-width:
                160px;

            padding:
                18px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .18
                );

            border-radius:
                20px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .08
                );

            text-align:
                center;

            backdrop-filter:
                blur(
                    12px
                );
        }


        .hero-status i {

            font-size:
                29px;

            margin-bottom:
                8px;
        }


        .hero-status-label {

            display:
                block;

            font-size:
                9px;

            letter-spacing:
                1.3px;

            font-weight:
                800;

            color:
                #DED5C8;

        }


        .hero-status strong {

            display:
                block;

            margin-top:
                2px;

            font-size:
                24px;

            font-weight:
                900;
        }


        .hero-status.is-pass {

            color:
                #DCECCF;
        }


        .hero-status.is-fail {

            color:
                #FFDAD5;
        }


        .hero-bottom {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            flex-wrap:
                wrap;

            gap:
                15px;

            margin-top:
                24px;

            padding-top:
                18px;

            border-top:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .13
                );
        }


        .hero-message {

            min-width:
                240px;
        }


        .hero-message strong {

            display:
                block;

            color:
                #FFFFFF;

            font-size:
                13px;

            font-weight:
                800;
        }


        .hero-message span {

            display:
                block;

            margin-top:
                4px;

            color:
                #DED7CE;

            font-size:
                10px;

            line-height:
                1.55;
        }


        .hero-report {

            text-align:
                right;
        }


        .hero-report small {

            display:
                block;

            color:
                #BFB5AA;

            font-size:
                8px;

            text-transform:
                uppercase;

            letter-spacing:
                1px;
        }


        .hero-report strong {

            display:
                block;

            margin-top:
                2px;

            color:
                #FFFFFF;

            font-size:
                12px;

            font-weight:
                800;
        }


        /*
        |--------------------------------------------------------------------------
        | ACTION BAR
        |--------------------------------------------------------------------------
        */

        .result-actions {

            display:
                flex;

            align-items:
                center;

            flex-wrap:
                wrap;

            gap:
                10px;

            margin-top:
                18px;
        }


        .action-btn {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            min-height:
                45px;

            padding:
                0 16px;

            border:
                1px solid
                var(--border);

            border-radius:
                13px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .96
                );

            color:
                var(--brown-dark);

            font-size:
                10px;

            font-weight:
                800;

            transition:
                .2s ease;

            cursor:
                pointer;
        }


        .action-btn:hover {

            color:
                var(--brown-dark);

            transform:
                translateY(
                    -2px
                );

            box-shadow:
                0 12px 28px
                rgba(
                    62,
                    39,
                    35,
                    .08
                );
        }


        .action-btn.primary {

            color:
                var(--white);

            border-color:
                var(--brown);

            background:
                linear-gradient(
                    135deg,
                    var(--brown),
                    var(--brown-dark)
                );
        }


        .action-btn.primary:hover {

            color:
                var(--white);
        }


        /*
        |--------------------------------------------------------------------------
        | SCORE LAYOUT
        |--------------------------------------------------------------------------
        */

        .main-grid {

            display:
                grid;

            grid-template-columns:
                minmax(
                    0,
                    1.65fr
                )
                minmax(
                    330px,
                    .85fr
                );

            gap:
                20px;

            margin-top:
                20px;
        }


        .card {

            border:
                1px solid
                var(--border);

            border-radius:
                var(--radius);

            background:
                rgba(
                    255,
                    255,
                    255,
                    .93
                );

            box-shadow:
                var(--shadow);

            backdrop-filter:
                blur(
                    16px
                );
        }


        .score-card {

            padding:
                25px;
        }


        .section-header {

            display:
                flex;

            align-items:
                flex-start;

            justify-content:
                space-between;

            gap:
                15px;

            margin-bottom:
                20px;
        }


        .section-heading small {

            display:
                block;

            color:
                var(--olive);

            font-size:
                8px;

            font-weight:
                800;

            text-transform:
                uppercase;

            letter-spacing:
                1.4px;
        }


        .section-heading h2 {

            margin:
                3px 0 0;

            color:
                var(--brown-dark);

            font-size:
                18px;

            font-weight:
                800;
        }


        .section-heading p {

            margin:
                5px 0 0;

            color:
                var(--muted);

            font-size:
                10px;
        }


        .score-layout {

            display:
                grid;

            grid-template-columns:
                270px
                1fr;

            align-items:
                center;

            gap:
                28px;
        }


        .score-circle-wrap {

            display:
                flex;

            justify-content:
                center;

            align-items:
                center;
        }


        .score-circle {

            width:
                220px;

            height:
                220px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                50%;

            background:
                conic-gradient(
                    var(--olive)
                    <?= result_number(
                        $percentage * 3.6
                    ); ?>deg,
                    #ECE7DD
                    <?= result_number(
                        $percentage * 3.6
                    ); ?>deg
                );

            position:
                relative;

            box-shadow:
                0 18px 40px
                rgba(
                    85,
                    107,
                    47,
                    .12
                );
        }


        .score-circle::before {

            content:
                '';

            position:
                absolute;

            inset:
                14px;

            border-radius:
                50%;

            background:
                var(--white);
        }


        .score-circle-content {

            position:
                relative;

            z-index:
                2;

            text-align:
                center;
        }


        .score-percent {

            color:
                var(--brown-dark);

            font-size:
                37px;

            line-height:
                1;

            font-weight:
                900;
        }


        .score-label {

            margin-top:
                7px;

            color:
                var(--muted);

            font-size:
                9px;

            font-weight:
                600;
        }


        .score-performance {

            display:
                inline-flex;

            margin-top:
                13px;

            padding:
                6px 9px;

            border-radius:
                9px;

            color:
                var(--olive-dark);

            background:
                var(--green-bg);

            font-size:
                8px;

            font-weight:
                800;
        }


        .score-details {

            display:
                grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(
                        0,
                        1fr
                    )
                );

            gap:
                11px;
        }


        .metric-box {

            padding:
                15px;

            border:
                1px solid
                var(--border);

            border-radius:
                17px;

            background:
                var(--cream-light);

            transition:
                .2s ease;
        }


        .metric-box:hover {

            transform:
                translateY(
                    -2px
                );

            box-shadow:
                0 12px 28px
                rgba(
                    62,
                    39,
                    35,
                    .06
                );
        }


        .metric-icon {

            width:
                36px;

            height:
                36px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                11px;

            margin-bottom:
                9px;

            font-size:
                13px;
        }


        .metric-icon.olive {

            color:
                var(--olive-dark);

            background:
                #EAF2DF;
        }


        .metric-icon.green {

            color:
                var(--green);

            background:
                var(--green-bg);
        }


        .metric-icon.red {

            color:
                var(--red);

            background:
                var(--red-bg);
        }


        .metric-icon.gold {

            color:
                #9B711F;

            background:
                var(--gold-bg);
        }


        .metric-icon.purple {

            color:
                var(--purple);

            background:
                var(--purple-bg);
        }


        .metric-label {

            color:
                var(--muted);

            font-size:
                8px;

            font-weight:
                600;
        }


        .metric-value {

            margin-top:
                2px;

            color:
                var(--brown-dark);

            font-size:
                20px;

            font-weight:
                900;
        }


        .metric-sub {

            margin-top:
                2px;

            color:
                var(--muted);

            font-size:
                7px;
        }


        /*
        |--------------------------------------------------------------------------
        | FORMULA
        |--------------------------------------------------------------------------
        */

        .formula-card {

            margin-top:
                20px;

            padding:
                16px 18px;

            border:
                1px solid
                #DCD4C6;

            border-radius:
                17px;

            background:
                linear-gradient(
                    135deg,
                    #FAF8F0,
                    #F3F1E7
                );
        }


        .formula-title {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            color:
                var(--olive-dark);

            font-size:
                8px;

            font-weight:
                800;

            text-transform:
                uppercase;

            letter-spacing:
                1px;
        }


        .formula-main {

            margin-top:
                9px;

            color:
                var(--brown-dark);

            font-size:
                clamp(
                    15px,
                    2vw,
                    21px
                );

            font-weight:
                900;

            text-align:
                center;
        }


        .formula-note {

            margin-top:
                5px;

            color:
                var(--muted);

            font-size:
                8px;

            text-align:
                center;
        }


        /*
        |--------------------------------------------------------------------------
        | PERFORMANCE CARD
        |--------------------------------------------------------------------------
        */

        .performance-card {

            padding:
                25px;
        }


        .performance-row {

            margin-bottom:
                18px;
        }


        .performance-row:last-child {

            margin-bottom:
                0;
        }


        .performance-top {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                10px;

            margin-bottom:
                7px;
        }


        .performance-name {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            color:
                var(--brown-dark);

            font-size:
                9px;

            font-weight:
                700;
        }


        .performance-value {

            color:
                var(--muted);

            font-size:
                9px;

            font-weight:
                700;
        }


        .progress-track {

            height:
                8px;

            overflow:
                hidden;

            border-radius:
                99px;

            background:
                #ECE7DC;
        }


        .progress-fill {

            height:
                100%;

            border-radius:
                inherit;

            min-width:
                2px;
        }


        .fill-green {

            background:
                linear-gradient(
                    90deg,
                    #6C8640,
                    #556B2F
                );
        }


        .fill-red {

            background:
                linear-gradient(
                    90deg,
                    #D1766C,
                    #B84A42
                );
        }


        .fill-gold {

            background:
                linear-gradient(
                    90deg,
                    #D2AE62,
                    #B48735
                );
        }


        .fill-gray {

            background:
                linear-gradient(
                    90deg,
                    #A9A099,
                    #7F766E
                );
        }


        .mini-grid {

            display:
                grid;

            grid-template-columns:
                repeat(
                    3,
                    1fr
                );

            gap:
                9px;

            margin-top:
                18px;
        }


        .mini-stat {

            padding:
                12px;

            border:
                1px solid
                var(--border);

            border-radius:
                14px;

            background:
                #FFFFFF;
        }


        .mini-stat span {

            display:
                block;

            color:
                var(--muted);

            font-size:
                7px;
        }


        .mini-stat strong {

            display:
                block;

            margin-top:
                4px;

            color:
                var(--brown-dark);

            font-size:
                14px;

            font-weight:
                900;
        }


        /*
        |--------------------------------------------------------------------------
        | EXAM INFO
        |--------------------------------------------------------------------------
        */

        .info-card {

            padding:
                25px;

            margin-top:
                20px;
        }


        .info-list {

            display:
                grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(
                        0,
                        1fr
                    )
                );

            gap:
                11px;
        }


        .info-item {

            padding:
                13px;

            border:
                1px solid
                var(--border);

            border-radius:
                14px;

            background:
                var(--cream-light);
        }


        .info-item span {

            display:
                block;

            color:
                var(--muted);

            font-size:
                7px;

            text-transform:
                uppercase;

            letter-spacing:
                .7px;
        }


        .info-item strong {

            display:
                block;

            margin-top:
                3px;

            color:
                var(--brown-dark);

            font-size:
                10px;

            font-weight:
                800;

            word-break:
                break-word;
        }


        /*
        |--------------------------------------------------------------------------
        | ANALYSIS
        |--------------------------------------------------------------------------
        */

        .analysis-card {

            margin-top:
                20px;

            padding:
                25px;
        }


        .analysis-summary {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            flex-wrap:
                wrap;

            gap:
                12px;

            margin-bottom:
                20px;

            padding:
                14px;

            border:
                1px solid
                var(--border);

            border-radius:
                16px;

            background:
                var(--cream-light);
        }


        .analysis-summary-left {

            display:
                flex;

            flex-wrap:
                wrap;

            align-items:
                center;

            gap:
                8px;
        }


        .summary-pill {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            min-height:
                30px;

            padding:
                0 9px;

            border-radius:
                9px;

            font-size:
                8px;

            font-weight:
                800;
        }


        .summary-pill.green {

            color:
                var(--green);

            background:
                var(--green-bg);
        }


        .summary-pill.red {

            color:
                var(--red);

            background:
                var(--red-bg);
        }


        .summary-pill.gray {

            color:
                #6F6861;

            background:
                #ECE8E1;
        }


        .summary-pill.olive {

            color:
                var(--olive-dark);

            background:
                #EAF2DF;
        }


        .analysis-item {

            overflow:
                hidden;

            margin-bottom:
                13px;

            padding:
                17px;

            border:
                1px solid
                var(--border);

            border-radius:
                18px;

            background:
                #FFFFFF;

            transition:
                .2s ease;
        }


        .analysis-item:hover {

            transform:
                translateY(
                    -1px
                );

            box-shadow:
                0 12px 30px
                rgba(
                    62,
                    39,
                    35,
                    .07
                );
        }


        .analysis-item.correct {

            border-left:
                4px solid
                var(--olive);
        }


        .analysis-item.wrong {

            border-left:
                4px solid
                var(--red);
        }


        .analysis-item.unanswered {

            border-left:
                4px solid
                #8B837B;
        }


        .analysis-head {

            display:
                flex;

            align-items:
                flex-start;

            justify-content:
                space-between;

            gap:
                14px;

            margin-bottom:
                10px;
        }


        .question-no {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            color:
                var(--brown-dark);

            font-size:
                10px;

            font-weight:
                900;
        }


        .question-no span {

            width:
                31px;

            height:
                31px;

            display:
                grid;

            place-items:
                center;

            border-radius:
                9px;

            color:
                var(--white);

            background:
                var(--brown);

            font-size:
                9px;
        }


        .question-result-badge {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            padding:
                6px 9px;

            border-radius:
                9px;

            font-size:
                8px;

            font-weight:
                800;

            white-space:
                nowrap;
        }


        .question-result-badge.correct {

            color:
                var(--green);

            background:
                var(--green-bg);
        }


        .question-result-badge.wrong {

            color:
                var(--red);

            background:
                var(--red-bg);
        }


        .question-result-badge.unanswered {

            color:
                #6D665F;

            background:
                #ECE8E1;
        }


        .question-text {

            color:
                var(--brown-dark);

            font-size:
                12px;

            line-height:
                1.65;

            font-weight:
                700;
        }


        .question-image {

            display:
                block;

            max-width:
                100%;

            max-height:
                280px;

            margin:
                12px 0;

            object-fit:
                contain;

            border:
                1px solid
                var(--border);

            border-radius:
                13px;

            background:
                #FAF8F3;
        }


        .answers-grid {

            display:
                grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(
                        0,
                        1fr
                    )
                );

            gap:
                9px;

            margin-top:
                13px;
        }


        .answer-box {

            padding:
                11px 12px;

            border:
                1px solid
                var(--border);

            border-radius:
                12px;

            background:
                var(--cream-light);
        }


        .answer-box span {

            display:
                block;

            margin-bottom:
                4px;

            color:
                var(--muted);

            font-size:
                7px;

            text-transform:
                uppercase;

            letter-spacing:
                .7px;
        }


        .answer-box strong {

            display:
                block;

            color:
                var(--brown-dark);

            font-size:
                9px;

            line-height:
                1.5;
        }


        .answer-box.your-answer.correct-answer {

            border-color:
                #CFE0C1;

            background:
                #F3F8ED;
        }


        .answer-box.your-answer.wrong-answer {

            border-color:
                #ECC9C4;

            background:
                #FCF2F0;
        }


        .explanation {

            margin-top:
                11px;

            padding:
                11px 12px;

            border-radius:
                12px;

            background:
                #F7F4EE;
        }


        .explanation-title {

            margin-bottom:
                4px;

            color:
                var(--olive-dark);

            font-size:
                8px;

            font-weight:
                800;
        }


        .explanation-text {

            color:
                #6B635D;

            font-size:
                9px;

            line-height:
                1.65;
        }


        .marks-line {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                12px;

            margin-top:
                11px;

            padding-top:
                10px;

            border-top:
                1px dashed
                #DDD6CC;

            color:
                var(--muted);

            font-size:
                8px;
        }


        .marks-line strong {

            color:
                var(--brown-dark);

            font-size:
                9px;

            font-weight:
                900;
        }


        /*
        |--------------------------------------------------------------------------
        | SIDEBAR
        |--------------------------------------------------------------------------
        */

        .sticky-column {

            position:
                relative;
        }


        .sidebar-card {

            padding:
                22px;
        }


        .score-big {

            padding:
                18px;

            border-radius:
                19px;

            color:
                var(--white);

            background:
                linear-gradient(
                    145deg,
                    var(--olive),
                    var(--olive-dark)
                );

            box-shadow:
                0 18px 35px
                rgba(
                    85,
                    107,
                    47,
                    .18
                );
        }


        .score-big small {

            display:
                block;

            color:
                #DCE7CF;

            font-size:
                8px;

            text-transform:
                uppercase;

            letter-spacing:
                1px;
        }


        .score-big strong {

            display:
                block;

            margin-top:
                5px;

            color:
                #FFFFFF;

            font-size:
                34px;

            line-height:
                1;

            font-weight:
                900;
        }


        .score-big span {

            display:
                block;

            margin-top:
                5px;

            color:
                #DDE6D2;

            font-size:
                8px;
        }


        .sidebar-section {

            margin-top:
                19px;
        }


        .sidebar-title {

            margin-bottom:
                10px;

            color:
                var(--brown-dark);

            font-size:
                10px;

            font-weight:
                800;
        }


        .detail-row {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                10px;

            padding:
                9px 0;

            border-bottom:
                1px dashed
                #E2DBD1;
        }


        .detail-row:last-child {

            border-bottom:
                0;
        }


        .detail-row span {

            color:
                var(--muted);

            font-size:
                8px;
        }


        .detail-row strong {

            color:
                var(--brown-dark);

            font-size:
                8px;

            font-weight:
                800;

            text-align:
                right;
        }


        .notice {

            margin-top:
                18px;

            padding:
                12px;

            border:
                1px solid
                #DDD7CA;

            border-radius:
                13px;

            background:
                #F8F5ED;

            color:
                var(--muted);

            font-size:
                8px;

            line-height:
                1.6;
        }


        .notice i {

            color:
                var(--olive);

            margin-right:
                4px;
        }


        /*
        |--------------------------------------------------------------------------
        | EMPTY ANALYSIS
        |--------------------------------------------------------------------------
        */

        .empty-state {

            padding:
                40px 20px;

            text-align:
                center;

            border:
                1px dashed
                var(--border);

            border-radius:
                18px;

            background:
                var(--cream-light);
        }


        .empty-state i {

            margin-bottom:
                10px;

            color:
                var(--muted);

            font-size:
                28px;
        }


        .empty-state h3 {

            margin:
                0 0 5px;

            color:
                var(--brown-dark);

            font-size:
                14px;

            font-weight:
                800;
        }


        .empty-state p {

            margin:
                0;

            color:
                var(--muted);

            font-size:
                9px;
        }


        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        .result-footer {

            margin-top:
                22px;

            padding:
                18px;

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
                    .75
                );

            color:
                var(--muted);

            font-size:
                8px;

            text-align:
                center;
        }


        /*
        |--------------------------------------------------------------------------
        | EMAIL MODAL
        |--------------------------------------------------------------------------
        */

        .modal-content {

            border:
                0;

            border-radius:
                22px;

            overflow:
                hidden;

            box-shadow:
                0 30px 80px
                rgba(
                    0,
                    0,
                    0,
                    .20
                );
        }


        .modal-header {

            color:
                var(--white);

            border:
                0;

            background:
                linear-gradient(
                    135deg,
                    var(--brown),
                    var(--brown-dark)
                );
        }


        .modal-title {

            font-size:
                15px;

            font-weight:
                800;
        }


        .modal-body {

            padding:
                22px;
        }


        .email-status {

            display:
                none;

            margin-top:
                14px;

            padding:
                10px 12px;

            border-radius:
                10px;

            font-size:
                9px;

            font-weight:
                700;
        }


        .email-status.show {
            display:
                block;
        }


        .email-status.success {

            color:
                var(--green);

            background:
                var(--green-bg);
        }


        .email-status.error {

            color:
                var(--red);

            background:
                var(--red-bg);
        }


        /*
        |--------------------------------------------------------------------------
        | PRINT
        |--------------------------------------------------------------------------
        */

        @media print {

            body {

                background:
                    #FFFFFF;
            }


            .result-shell {

                width:
                    100%;

                margin:
                    0;
            }


            .result-nav,
            .result-actions {

                display:
                    none !important;
            }


            .result-hero {

                margin-top:
                    0;

                box-shadow:
                    none;
            }


            .card {

                box-shadow:
                    none;

                break-inside:
                    avoid;
            }


            .analysis-item {

                break-inside:
                    avoid;
            }

        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (
            max-width: 1180px
        ) {

            .main-grid {

                grid-template-columns:
                    1fr;
            }


            .sticky-column {

                display:
                    grid;

                grid-template-columns:
                    repeat(
                        2,
                        minmax(
                            0,
                            1fr
                        )
                    );

                gap:
                    20px;
            }


            .sticky-column .info-card {

                margin-top:
                    0;
            }
        }


        @media (
            max-width: 900px
        ) {

            .score-layout {

                grid-template-columns:
                    1fr;
            }


            .score-circle-wrap {

                justify-content:
                    flex-start;
            }


            .sticky-column {

                display:
                    block;
            }


            .sticky-column .info-card {

                margin-top:
                    20px;
            }
        }


        @media (
            max-width: 700px
        ) {

            .result-shell {

                width:
                    calc(
                        100% - 18px
                    );

                margin:
                    9px auto 30px;
            }


            .result-nav {

                position:
                    static;

                border-radius:
                    17px;

                padding:
                    10px;
            }


            .nav-actions {

                display:
                    none;
            }


            .result-hero {

                margin-top:
                    12px;

                padding:
                    19px;

                border-radius:
                    22px;
            }


            .hero-top {

                flex-direction:
                    column;
            }


            .hero-status {

                width:
                    100%;

                min-width:
                    0;
            }


            .hero-title {

                font-size:
                    25px;
            }


            .hero-bottom {

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }


            .hero-report {

                text-align:
                    left;
            }


            .score-card,
            .performance-card,
            .info-card,
            .analysis-card,
            .sidebar-card {

                padding:
                    17px;

                border-radius:
                    19px;
            }


            .score-circle {

                width:
                    190px;

                height:
                    190px;
            }


            .score-details {

                grid-template-columns:
                    1fr
                    1fr;
            }


            .mini-grid {

                grid-template-columns:
                    1fr
                    1fr;
            }


            .info-list {

                grid-template-columns:
                    1fr;
            }


            .answers-grid {

                grid-template-columns:
                    1fr;
            }


            .analysis-head {

                flex-direction:
                    column;
            }


            .question-result-badge {

                align-self:
                    flex-start;
            }


            .action-btn {

                flex:
                    1 1 140px;
            }
        }


        @media (
            max-width: 450px
        ) {

            .score-details {

                grid-template-columns:
                    1fr;
            }


            .mini-grid {

                grid-template-columns:
                    1fr;
            }


            .brand small {

                display:
                    none;
            }


            .hero-meta-divider {

                display:
                    none;
            }
        }

    </style>

</head>


<body>


<div class="result-shell">


    <!-- =====================================================
         NAVIGATION
    ====================================================== -->

    <nav class="result-nav">


        <a
            href="dashboard.php"
            class="brand"
        >

            <span class="brand-icon">

                <i
                    class="
                        fa-solid
                        fa-graduation-cap
                    "
                ></i>

            </span>


            <span>

                <strong>
                    ExamSphere
                </strong>

                <small>
                    Examination Result
                </small>

            </span>

        </a>


        <div class="nav-actions">

            <a
                href="dashboard.php"
                class="nav-btn"
            >

                <i
                    class="
                        fa-solid
                        fa-house
                    "
                ></i>

                Dashboard

            </a>


            <a
                href="my_exams.php"
                class="nav-btn"
            >

                <i
                    class="
                        fa-solid
                        fa-file-lines
                    "
                ></i>

                My Exams

            </a>

        </div>

    </nav>


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section
        class="
            result-hero
            <?= $statusClass; ?>
        "
    >

        <div class="hero-content">


            <div class="hero-top">


                <div>


                    <div class="hero-kicker">

                        <i
                            class="
                                fa-solid
                                fa-award
                            "
                        ></i>

                        <?= result_escape(
                            $examType
                        ); ?>

                        Result

                    </div>


                    <h1 class="hero-title">

                        <?= result_escape(
                            $examTitle
                        ); ?>

                    </h1>


                    <div class="hero-meta">


                        <span
                            class="
                                hero-meta-item
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-book
                                "
                            ></i>

                            <?= result_escape(
                                $subjectName
                            ); ?>

                        </span>


                        <?php if (
                            $subjectCode !== ''
                        ): ?>

                            <span
                                class="
                                    hero-meta-divider
                                "
                            >
                                •
                            </span>


                            <span
                                class="
                                    hero-meta-item
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-hashtag
                                    "
                                ></i>

                                <?= result_escape(
                                    $subjectCode
                                ); ?>

                            </span>

                        <?php endif; ?>


                        <span
                            class="
                                hero-meta-divider
                            "
                        >
                            •
                        </span>


                        <span
                            class="
                                hero-meta-item
                            "
                        >

                            <i
                                class="
                                    fa-regular
                                    fa-calendar
                                "
                            ></i>

                            <?= result_escape(
                                $resultDate
                            ); ?>

                        </span>

                    </div>

                </div>


                <div
                    class="
                        hero-status
                        <?= $isPassed
                            ? 'is-pass'
                            : 'is-fail'
                        ?>"
                >

                    <i
                        class="
                            fa-solid
                            <?= $statusIcon; ?>
                        "
                    ></i>


                    <span class="hero-status-label">
                        Result Status
                    </span>


                    <strong>
                        <?= $statusLabel; ?>
                    </strong>

                </div>

            </div>


            <div class="hero-bottom">


                <div class="hero-message">

                    <strong>

                        <?= result_escape(
                            $statusMessage
                        ); ?>

                    </strong>


                    <span>

                        <?= result_escape(
                            $statusSubmessage
                        ); ?>

                    </span>

                </div>


                <div class="hero-report">

                    <small>
                        Result ID
                    </small>

                    <strong>
                        #<?= (int) $resultId; ?>
                    </strong>

                </div>


            </div>


        </div>

    </section>


    <!-- =====================================================
         ACTIONS
    ====================================================== -->

    <div class="result-actions">


        <a
            href="dashboard.php"
            class="action-btn"
        >

            <i
                class="
                    fa-solid
                    fa-house
                "
            ></i>

            Dashboard

        </a>


        <a
            href="my_exams.php"
            class="action-btn"
        >

            <i
                class="
                    fa-solid
                    fa-file-lines
                "
            ></i>

            My Exams

        </a>


        <a
            href="
                ajax/download_result_pdf.php?attempt_id=<?= (int) $result['attempt_id']; ?>
            "
            class="
                action-btn
                primary
            "
        >

            <i
                class="
                    fa-solid
                    fa-file-pdf
                "
            ></i>

            Download PDF

        </a>


        <button
            type="button"
            class="action-btn"
            id="emailResult"
            data-attempt-id="<?= (int) $result['attempt_id']; ?>"
            data-csrf-token="<?= result_escape(
                $csrfToken
            ); ?>"
        >

            <i
                class="
                    fa-solid
                    fa-envelope
                "
            ></i>

            Email Result

        </button>


        <button
            type="button"
            class="action-btn"
            id="printResult"
        >

            <i
                class="
                    fa-solid
                    fa-print
                "
            ></i>

            Print

        </button>

    </div>


    <!-- =====================================================
         MAIN GRID
    ====================================================== -->

    <div class="main-grid">


        <!-- =================================================
             LEFT
        ================================================== -->

        <div>


            <!-- =============================================
                 SCORE CARD
            ============================================== -->

            <section class="card score-card">


                <div class="section-header">


                    <div class="section-heading">

                        <small>
                            Final Performance
                        </small>

                        <h2>
                            Your Score
                        </h2>

                        <p>
                            Complete result summary from your finalized attempt.
                        </p>

                    </div>


                    <div
                        class="score-performance"
                    >

                        <i
                            class="
                                fa-solid
                                fa-chart-line
                                me-1
                            "
                        ></i>

                        <?= result_escape(
                            $performanceLabel
                        ); ?>

                    </div>


                </div>


                <div class="score-layout">


                    <!-- SCORE -->

                    <div class="score-circle-wrap">

                        <div
                            class="score-circle"
                            aria-label="
                                <?= result_escape(
                                    $percentage
                                ); ?> percent
                            "
                        >

                            <div
                                class="
                                    score-circle-content
                                "
                            >

                                <div
                                    class="
                                        score-percent
                                    "
                                >

                                    <?= result_number(
                                        $percentage
                                    ); ?>%

                                </div>


                                <div
                                    class="
                                        score-label
                                    "
                                >

                                    Overall Score

                                </div>


                                <div
                                    class="
                                        score-performance
                                    "
                                >

                                    <?= result_number(
                                        $obtainedMarks
                                    ); ?>

                                    /

                                    <?= result_number(
                                        $totalMarks
                                    ); ?>

                                    Marks

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- METRICS -->

                    <div class="score-details">


                        <div class="metric-box">

                            <div
                                class="
                                    metric-icon
                                    olive
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-list-check
                                    "
                                ></i>

                            </div>


                            <div class="metric-label">
                                Total Questions
                            </div>


                            <div class="metric-value">

                                <?= $totalQuestions; ?>

                            </div>


                            <div class="metric-sub">

                                Full configured set

                            </div>

                        </div>


                        <div class="metric-box">

                            <div
                                class="
                                    metric-icon
                                    green
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-circle-check
                                    "
                                ></i>

                            </div>


                            <div class="metric-label">
                                Correct
                            </div>


                            <div class="metric-value">

                                <?= $correctAnswers; ?>

                            </div>


                            <div class="metric-sub">

                                <?= result_number(
                                    $correctPercentage
                                ); ?>% of questions

                            </div>

                        </div>


                        <div class="metric-box">

                            <div
                                class="
                                    metric-icon
                                    red
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-circle-xmark
                                    "
                                ></i>

                            </div>


                            <div class="metric-label">
                                Wrong
                            </div>


                            <div class="metric-value">

                                <?= $wrongAnswers; ?>

                            </div>


                            <div class="metric-sub">

                                <?= result_number(
                                    $wrongPercentage
                                ); ?>% of questions

                            </div>

                        </div>


                        <div class="metric-box">

                            <div
                                class="
                                    metric-icon
                                    gold
                                "
                            >

                                <i
                                    class="
                                        fa-regular
                                        fa-circle
                                    "
                                ></i>

                            </div>


                            <div class="metric-label">
                                Unanswered
                            </div>


                            <div class="metric-value">

                                <?= $unansweredQuestions; ?>

                            </div>


                            <div class="metric-sub">

                                <?= result_number(
                                    $unansweredPercentage
                                ); ?>% of questions

                            </div>

                        </div>


                        <div class="metric-box">

                            <div
                                class="
                                    metric-icon
                                    purple
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-bullseye
                                    "
                                ></i>

                            </div>


                            <div class="metric-label">
                                Accuracy
                            </div>


                            <div class="metric-value">

                                <?= result_number(
                                    $accuracy
                                ); ?>%

                            </div>


                            <div class="metric-sub">

                                Correct / attempted

                            </div>

                        </div>


                        <div class="metric-box">

                            <div
                                class="
                                    metric-icon
                                    olive
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-stopwatch
                                    "
                                ></i>

                            </div>


                            <div class="metric-label">
                                Time Used
                            </div>


                            <div class="metric-value">

                                <?= result_escape(
                                    $timeUsedLabel
                                ); ?>

                            </div>


                            <div class="metric-sub">

                                <?= $durationMinutes; ?>
                                minute limit

                            </div>

                        </div>


                    </div>


                </div>


                <!-- FORMULA -->

                <div class="formula-card">


                    <div class="formula-title">

                        <i
                            class="
                                fa-solid
                                fa-calculator
                            "
                        ></i>

                        Dynamic Marks Calculation

                    </div>


                    <div class="formula-main">

                        <?= $totalQuestions; ?>

                        Questions

                        ×

                        <?= result_number(
                            $marksPerQuestion
                        ); ?>

                        Mark/Question

                        =

                        <?= result_number(
                            $calculatedTotalMarks
                        ); ?>

                        Total Marks

                    </div>


                    <div class="formula-note">

                        Final result total is calculated from the configured
                        question count and per-question marks.

                    </div>

                </div>


            </section>


            <!-- =============================================
                 PERFORMANCE
            ============================================== -->

            <section
                class="
                    card
                    performance-card
                "
            >


                <div class="section-header">

                    <div class="section-heading">

                        <small>
                            Performance Analysis
                        </small>

                        <h2>
                            Question Distribution
                        </h2>

                        <p>
                            Visual breakdown of your attempt.
                        </p>

                    </div>

                </div>


                <div class="performance-row">


                    <div class="performance-top">

                        <div
                            class="
                                performance-name
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-pen
                                "
                            ></i>

                            Attempted

                        </div>


                        <div
                            class="
                                performance-value
                            "
                        >

                            <?= $attemptedQuestions; ?>

                            /

                            <?= $totalQuestions; ?>

                            &nbsp;

                            (<?= result_number(
                                $completion
                            ); ?>%)

                        </div>

                    </div>


                    <div class="progress-track">

                        <div
                            class="
                                progress-fill
                                fill-gold
                            "
                            style="
                                width:<?= max(
                                    0,
                                    min(
                                        100,
                                        $completion
                                    )
                                ); ?>%;
                            "
                        ></div>

                    </div>

                </div>


                <div class="performance-row">


                    <div class="performance-top">

                        <div
                            class="
                                performance-name
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-check
                                "
                            ></i>

                            Correct

                        </div>


                        <div
                            class="
                                performance-value
                            "
                        >

                            <?= $correctAnswers; ?>

                            (<?= result_number(
                                $correctPercentage
                            ); ?>%)

                        </div>

                    </div>


                    <div class="progress-track">

                        <div
                            class="
                                progress-fill
                                fill-green
                            "
                            style="
                                width:<?= max(
                                    0,
                                    min(
                                        100,
                                        $correctPercentage
                                    )
                                ); ?>%;
                            "
                        ></div>

                    </div>

                </div>


                <div class="performance-row">


                    <div class="performance-top">

                        <div
                            class="
                                performance-name
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-xmark
                                "
                            ></i>

                            Wrong

                        </div>


                        <div
                            class="
                                performance-value
                            "
                        >

                            <?= $wrongAnswers; ?>

                            (<?= result_number(
                                $wrongPercentage
                            ); ?>%)

                        </div>

                    </div>


                    <div class="progress-track">

                        <div
                            class="
                                progress-fill
                                fill-red
                            "
                            style="
                                width:<?= max(
                                    0,
                                    min(
                                        100,
                                        $wrongPercentage
                                    )
                                ); ?>%;
                            "
                        ></div>

                    </div>

                </div>


                <div class="performance-row">


                    <div class="performance-top">

                        <div
                            class="
                                performance-name
                            "
                        >

                            <i
                                class="
                                    fa-regular
                                    fa-circle
                                "
                            ></i>

                            Unanswered

                        </div>


                        <div
                            class="
                                performance-value
                            "
                        >

                            <?= $unansweredQuestions; ?>

                            (<?= result_number(
                                $unansweredPercentage
                            ); ?>%)

                        </div>

                    </div>


                    <div class="progress-track">

                        <div
                            class="
                                progress-fill
                                fill-gray
                            "
                            style="
                                width:<?= max(
                                    0,
                                    min(
                                        100,
                                        $unansweredPercentage
                                    )
                                ); ?>%;
                            "
                        ></div>

                    </div>

                </div>


                <div class="mini-grid">


                    <div class="mini-stat">

                        <span>
                            Accuracy
                        </span>

                        <strong>

                            <?= result_number(
                                $accuracy
                            ); ?>%

                        </strong>

                    </div>


                    <div class="mini-stat">

                        <span>
                            Passing Score
                        </span>

                        <strong>

                            <?= result_number(
                                $passingMarks
                            ); ?>

                        </strong>

                    </div>


                    <div class="mini-stat">

                        <span>
                            Grade
                        </span>

                        <strong>

                            <?= result_escape(
                                $grade
                            ); ?>

                        </strong>

                    </div>

                </div>


            </section>


            <!-- =============================================
                 EXAM INFORMATION
            ============================================== -->

            <section
                class="
                    card
                    info-card
                "
            >


                <div class="section-header">

                    <div class="section-heading">

                        <small>
                            Examination Details
                        </small>

                        <h2>
                            Attempt Information
                        </h2>

                    </div>

                </div>


                <div class="info-list">


                    <div class="info-item">

                        <span>
                            Examination
                        </span>

                        <strong>

                            <?= result_escape(
                                $examTitle
                            ); ?>

                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Subject
                        </span>

                        <strong>

                            <?= result_escape(
                                $subjectName
                            ); ?>

                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Exam Type
                        </span>

                        <strong>

                            <?= result_escape(
                                $examType
                            ); ?>

                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Duration
                        </span>

                        <strong>

                            <?= $durationMinutes; ?>

                            minutes

                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Time Used
                        </span>

                        <strong>

                            <?= result_escape(
                                $timeUsedLabel
                            ); ?>

                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Submitted
                        </span>

                        <strong>

                            <?= result_escape(
                                $submittedDate
                            ); ?>

                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Negative Marking
                        </span>

                        <strong>

                            <?= (int) (
                                $result[
                                    'negative_marking'
                                ] ?? 0
                            ) === 1
                                ? 'Enabled'
                                : 'None'
                            ?>

                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Attempt Status
                        </span>

                        <strong>

                            <?= result_escape(
                                $result[
                                    'attempt_status'
                                ]
                            ); ?>

                        </strong>

                    </div>


                </div>


                <?php if (
                    $examDescription !== ''
                ): ?>

                    <div class="notice">

                        <i
                            class="
                                fa-solid
                                fa-circle-info
                            "
                        ></i>

                        <?= nl2br(
                            result_escape(
                                $examDescription
                            )
                        ); ?>

                    </div>

                <?php endif; ?>


            </section>


            <!-- =============================================
                 QUESTION ANALYSIS
            ============================================== -->

            <section
                class="
                    card
                    analysis-card
                "
                id="questionAnalysis"
            >


                <div class="section-header">

                    <div class="section-heading">

                        <small>
                            Detailed Review
                        </small>

                        <h2>
                            Question-wise Analysis
                        </h2>

                        <p>
                            Review every response and its awarded marks.
                        </p>

                    </div>

                </div>


                <div class="analysis-summary">


                    <div
                        class="
                            analysis-summary-left
                        "
                    >

                        <span
                            class="
                                summary-pill
                                olive
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-list
                                "
                            ></i>

                            <?= $analysisTotal; ?>

                            Questions

                        </span>


                        <span
                            class="
                                summary-pill
                                green
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-check
                                "
                            ></i>

                            <?= $analysisCorrect; ?>

                            Correct

                        </span>


                        <span
                            class="
                                summary-pill
                                red
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-xmark
                                "
                            ></i>

                            <?= $analysisWrong; ?>

                            Wrong

                        </span>


                        <span
                            class="
                                summary-pill
                                gray
                            "
                        >

                            <i
                                class="
                                    fa-regular
                                    fa-circle
                                "
                            ></i>

                            <?= $analysisUnanswered; ?>

                            Unanswered

                        </span>

                    </div>


                    <span
                        class="
                            summary-pill
                            olive
                        "
                    >

                        <?= result_number(
                            $obtainedMarks
                        ); ?>

                        /

                        <?= result_number(
                            $totalMarks
                        ); ?>

                        Marks

                    </span>


                </div>


                <?php if (
                    !empty(
                        $analysis
                    )
                ): ?>


                    <?php foreach (
                        $analysis as $index => $question
                    ): ?>


                        <?php

                        $selectedAnswer =
                            strtoupper(
                                trim(
                                    (string) (
                                        $question[
                                            'selected_answer'
                                        ] ?? ''
                                    )
                                )
                            );


                        $correctAnswer =
                            strtoupper(
                                trim(
                                    (string) (
                                        $question[
                                            'correct_answer'
                                        ] ?? ''
                                    )
                                )
                            );


                        $isAnswered =
                            in_array(
                                $selectedAnswer,
                                [
                                    'A',
                                    'B',
                                    'C',
                                    'D'
                                ],
                                true
                            );


                        $isCorrect =
                            (int) (
                                $question[
                                    'is_correct'
                                ] ?? 0
                            ) === 1;


                        $questionClass =
                            $isCorrect
                                ? 'correct'
                                : (
                                    $isAnswered
                                        ? 'wrong'
                                        : 'unanswered'
                                );


                        $statusText =
                            $isCorrect
                                ? 'Correct'
                                : (
                                    $isAnswered
                                        ? 'Wrong'
                                        : 'Not Answered'
                                );


                        $statusIcon =
                            $isCorrect
                                ? 'fa-check'
                                : (
                                    $isAnswered
                                        ? 'fa-xmark'
                                        : 'fa-circle'
                                );


                        $yourAnswerText =
                            $isAnswered
                                ? result_option_text(
                                    $question,
                                    $selectedAnswer
                                )
                                : 'Not answered';


                        $correctAnswerText =
                            result_option_text(
                                $question,
                                $correctAnswer
                            );


                        $questionMarks =
                            round(
                                (float) (
                                    $question[
                                        'marks'
                                    ] ?? 0
                                ),
                                2
                            );


                        $marksAwarded =
                            round(
                                (float) (
                                    $question[
                                        'marks_awarded'
                                    ] ?? 0
                                ),
                                2
                            );


                        ?>


                        <article
                            class="
                                analysis-item
                                <?= $questionClass; ?>
                            "
                        >


                            <div class="analysis-head">


                                <div class="question-no">

                                    <span>
                                        <?= $index + 1; ?>
                                    </span>

                                    Question
                                    <?= $index + 1; ?>

                                </div>


                                <div
                                    class="
                                        question-result-badge
                                        <?= $questionClass; ?>
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            <?= $statusIcon; ?>
                                        "
                                    ></i>

                                    <?= result_escape(
                                        $statusText
                                    ); ?>

                                </div>

                            </div>


                            <div class="question-text">

                                <?= nl2br(
                                    result_escape(
                                        $question[
                                            'question_text'
                                        ] ?? ''
                                    )
                                ); ?>

                            </div>


                            <?php if (
                                !empty(
                                    $question[
                                        'question_image'
                                    ]
                                )
                            ): ?>

                                <img
                                    src="<?= result_escape(
                                        $question[
                                            'question_image'
                                        ]
                                    ); ?>"
                                    alt="Question"
                                    class="question-image"
                                    loading="lazy"
                                >

                            <?php endif; ?>


                            <div class="answers-grid">


                                <div
                                    class="
                                        answer-box
                                        your-answer
                                        <?= $isCorrect
                                            ? 'correct-answer'
                                            : (
                                                $isAnswered
                                                    ? 'wrong-answer'
                                                    : ''
                                            )
                                        ?>
                                    "
                                >

                                    <span>
                                        Your Answer
                                    </span>

                                    <strong>

                                        <?php if (
                                            $isAnswered
                                        ): ?>

                                            <?= result_escape(
                                                $selectedAnswer
                                            ); ?>

                                            <?php if (
                                                $yourAnswerText !== ''
                                            ): ?>

                                                —
                                                <?= result_escape(
                                                    $yourAnswerText
                                                ); ?>

                                            <?php endif; ?>

                                        <?php else: ?>

                                            Not answered

                                        <?php endif; ?>

                                    </strong>

                                </div>


                                <div
                                    class="
                                        answer-box
                                        correct-answer
                                    "
                                >

                                    <span>
                                        Correct Answer
                                    </span>

                                    <strong>

                                        <?php if (
                                            $correctAnswer !== ''
                                        ): ?>

                                            <?= result_escape(
                                                $correctAnswer
                                            ); ?>

                                            <?php if (
                                                $correctAnswerText !== ''
                                            ): ?>

                                                —
                                                <?= result_escape(
                                                    $correctAnswerText
                                                ); ?>

                                            <?php endif; ?>

                                        <?php else: ?>

                                            Not available

                                        <?php endif; ?>

                                    </strong>

                                </div>


                            </div>


                            <?php if (
                                !empty(
                                    $question[
                                        'explanation'
                                    ]
                                )
                            ): ?>


                                <div class="explanation">


                                    <div
                                        class="
                                            explanation-title
                                        "
                                    >

                                        <i
                                            class="
                                                fa-solid
                                                fa-lightbulb
                                                me-1
                                            "
                                        ></i>

                                        Explanation

                                    </div>


                                    <div
                                        class="
                                            explanation-text
                                        "
                                    >

                                        <?= nl2br(
                                            result_escape(
                                                $question[
                                                    'explanation'
                                                ]
                                            )
                                        ); ?>

                                    </div>


                                </div>


                            <?php endif; ?>


                            <div class="marks-line">


                                <span>

                                    Question marks:

                                    <strong>

                                        <?= result_number(
                                            $questionMarks
                                        ); ?>

                                    </strong>

                                </span>


                                <span>

                                    Marks awarded:

                                    <strong>

                                        <?= result_number(
                                            $marksAwarded
                                        ); ?>

                                    </strong>

                                </span>


                            </div>


                        </article>


                    <?php endforeach; ?>


                <?php else: ?>


                    <div class="empty-state">

                        <i
                            class="
                                fa-regular
                                fa-folder-open
                            "
                        ></i>


                        <h3>
                            Analysis unavailable
                        </h3>


                        <p>
                            Question-wise analysis is not available for this result.
                        </p>

                    </div>


                <?php endif; ?>


            </section>


        </div>


        <!-- =================================================
             RIGHT SIDEBAR
        ================================================== -->

        <aside class="sticky-column">


            <!-- SCORE SUMMARY -->

            <section class="card sidebar-card">


                <div class="score-big">

                    <small>
                        Final Score
                    </small>


                    <strong>

                        <?= result_number(
                            $obtainedMarks
                        ); ?>

                        /

                        <?= result_number(
                            $totalMarks
                        ); ?>

                    </strong>


                    <span>

                        <?= result_number(
                            $percentage
                        ); ?>%

                        overall performance

                    </span>

                </div>


                <div class="sidebar-section">


                    <div class="sidebar-title">
                        Result Summary
                    </div>


                    <div class="detail-row">

                        <span>
                            Grade
                        </span>

                        <strong>

                            <?= result_escape(
                                $grade
                            ); ?>

                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Result
                        </span>

                        <strong>

                            <?= result_escape(
                                $resultStatus
                            ); ?>

                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Total Marks
                        </span>

                        <strong>

                            <?= result_number(
                                $totalMarks
                            ); ?>

                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Obtained Marks
                        </span>

                        <strong>

                            <?= result_number(
                                $obtainedMarks
                            ); ?>

                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Passing Marks
                        </span>

                        <strong>

                            <?= result_number(
                                $passingMarks
                            ); ?>

                        </strong>

                    </div>


                </div>


                <div class="sidebar-section">


                    <div class="sidebar-title">
                        Attempt Statistics
                    </div>


                    <div class="detail-row">

                        <span>
                            Attempted
                        </span>

                        <strong>

                            <?= $attemptedQuestions; ?>

                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Correct
                        </span>

                        <strong>

                            <?= $correctAnswers; ?>

                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Wrong
                        </span>

                        <strong>

                            <?= $wrongAnswers; ?>

                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Unanswered
                        </span>

                        <strong>

                            <?= $unansweredQuestions; ?>

                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Accuracy
                        </span>

                        <strong>

                            <?= result_number(
                                $accuracy
                            ); ?>%

                        </strong>

                    </div>


                </div>


                <div class="notice">

                    <i
                        class="
                            fa-solid
                            fa-shield-halved
                        "
                    ></i>

                    This result has been generated from the finalized
                    server-side examination attempt.

                </div>


            </section>


            <!-- DOWNLOAD -->

            <section
                class="
                    card
                    sidebar-card
                    info-card
                "
            >


                <div class="sidebar-title">
                    Result Tools
                </div>


                <a
                    href="
                        ajax/download_result_pdf.php?attempt_id=<?= (int) $result['attempt_id']; ?>
                    "
                    class="
                        action-btn
                        primary
                        w-100
                        mb-2
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-file-pdf
                        "
                    ></i>

                    Download Result PDF

                </a>


                <button
                    type="button"
                    class="
                        action-btn
                        w-100
                        mb-2
                    "
                    id="sidebarEmailResult"
                >

                    <i
                        class="
                            fa-solid
                            fa-envelope
                        "
                    ></i>

                    Email Result

                </button>


                <button
                    type="button"
                    class="
                        action-btn
                        w-100
                    "
                    id="sidebarPrintResult"
                >

                    <i
                        class="
                            fa-solid
                            fa-print
                        "
                    ></i>

                    Print Result

                </button>


            </section>


        </aside>


    </div>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <footer class="result-footer">

        <i
            class="
                fa-solid
                fa-shield-halved
                me-1
            "
        ></i>

        Final marks and result status are calculated
        by ExamSphere's server-side examination engine.

        &nbsp;•&nbsp;

        Result ID:

        #<?= (int) $resultId; ?>

    </footer>


</div>


<!-- =========================================================
     EMAIL MODAL
========================================================== -->

<div
    class="modal fade"
    id="emailModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="
            modal-dialog
            modal-dialog-centered
        "
    >

        <div class="modal-content">


            <div class="modal-header">

                <h5
                    class="modal-title"
                >

                    <i
                        class="
                            fa-solid
                            fa-envelope
                            me-2
                        "
                    ></i>

                    Email Result

                </h5>


                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body">


                <p
                    class="mb-3"
                    style="
                        color:#7C736C;
                        font-size:11px;
                        line-height:1.7;
                    "
                >

                    Your finalized examination result
                    will be sent to your registered email address.

                </p>


                <div
                    style="
                        padding:14px;
                        border:1px solid #E4DED3;
                        border-radius:14px;
                        background:#FAF9F4;
                    "
                >

                    <div
                        style="
                            font-size:8px;
                            color:#7C736C;
                            text-transform:uppercase;
                            letter-spacing:.7px;
                        "
                    >
                        Examination
                    </div>


                    <div
                        style="
                            margin-top:4px;
                            color:#3E2723;
                            font-size:11px;
                            font-weight:800;
                        "
                    >

                        <?= result_escape(
                            $examTitle
                        ); ?>

                    </div>


                    <div
                        style="
                            margin-top:10px;
                            font-size:8px;
                            color:#7C736C;
                        "
                    >
                        Score
                    </div>


                    <div
                        style="
                            margin-top:4px;
                            color:#556B2F;
                            font-size:16px;
                            font-weight:900;
                        "
                    >

                        <?= result_number(
                            $obtainedMarks
                        ); ?>

                        /

                        <?= result_number(
                            $totalMarks
                        ); ?>

                        &nbsp;(
                        <?= result_number(
                            $percentage
                        ); ?>%
                        )

                    </div>

                </div>


                <div
                    id="emailStatus"
                    class="email-status"
                ></div>


            </div>


            <div
                class="
                    modal-footer
                    border-0
                "
            >

                <button
                    type="button"
                    class="action-btn"
                    data-bs-dismiss="modal"
                >

                    Cancel

                </button>


                <button
                    type="button"
                    class="
                        action-btn
                        primary
                    "
                    id="confirmEmailResult"
                >

                    <i
                        class="
                            fa-solid
                            fa-paper-plane
                        "
                    ></i>

                    Send Result

                </button>

            </div>


        </div>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
></script>


<script
    src="assets/js/result.js"
></script>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const emailButton =
            document.getElementById(
                'emailResult'
            );


        const sidebarEmailButton =
            document.getElementById(
                'sidebarEmailResult'
            );


        const confirmEmailButton =
            document.getElementById(
                'confirmEmailResult'
            );


        const emailStatus =
            document.getElementById(
                'emailStatus'
            );


        const printButton =
            document.getElementById(
                'printResult'
            );


        const sidebarPrintButton =
            document.getElementById(
                'sidebarPrintResult'
            );


        const modalElement =
            document.getElementById(
                'emailModal'
            );


        let emailModal = null;


        if (
            modalElement &&
            typeof bootstrap !== 'undefined'
        ) {

            emailModal =
                bootstrap.Modal.getOrCreateInstance(
                    modalElement
                );
        }


        function openEmailModal() {

            if (
                emailStatus
            ) {

                emailStatus.className =
                    'email-status';

                emailStatus.textContent =
                    '';
            }


            if (
                emailModal
            ) {

                emailModal.show();

            } else {

                sendResultEmail();
            }
        }


        if (
            emailButton
        ) {

            emailButton.addEventListener(
                'click',
                openEmailModal
            );
        }


        if (
            sidebarEmailButton
        ) {

            sidebarEmailButton.addEventListener(
                'click',
                openEmailModal
            );
        }


        async function sendResultEmail() {

            const attemptId =
                emailButton
                    ?.dataset
                    ?.attemptId
                ||
                <?= (int) $result['attempt_id']; ?>;


            const token =
                emailButton
                    ?.dataset
                    ?.csrfToken
                ||
                <?= json_encode(
                    $csrfToken
                ); ?>;


            if (
                confirmEmailButton
            ) {

                confirmEmailButton.disabled =
                    true;

                confirmEmailButton.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
            }


            if (
                emailStatus
            ) {

                emailStatus.className =
                    'email-status';

                emailStatus.textContent =
                    '';
            }


            try {

                const params =
                    new URLSearchParams();


                params.set(
                    'attempt_id',
                    String(
                        attemptId
                    )
                );


                params.set(
                    'csrf_token',
                    String(
                        token
                    )
                );


                const response =
                    await fetch(
                        'ajax/send_result_email.php?' +
                        params.toString(),
                        {
                            method:
                                'GET',

                            credentials:
                                'same-origin',

                            cache:
                                'no-store',

                            headers:
                                {
                                    'X-Requested-With':
                                        'XMLHttpRequest'
                                }
                        }
                    );


                const text =
                    await response.text();


                let data = null;


                try {

                    data =
                        JSON.parse(
                            text
                        );

                } catch (
                    parseError
                ) {

                    data = null;
                }


                if (
                    !response.ok
                ) {

                    throw new Error(
                        data?.message
                        ||
                        'Unable to send the result email.'
                    );
                }


                if (
                    data &&
                    data.status === false
                ) {

                    throw new Error(
                        data.message
                        ||
                        'Unable to send the result email.'
                    );
                }


                if (
                    emailStatus
                ) {

                    emailStatus.className =
                        'email-status show success';


                    emailStatus.textContent =
                        data?.message
                        ||
                        'Result email sent successfully.';
                }


                if (
                    confirmEmailButton
                ) {

                    confirmEmailButton.innerHTML =
                        '<i class="fa-solid fa-check"></i> Sent';
                }


                setTimeout(
                    function () {

                        if (
                            emailModal
                        ) {

                            emailModal.hide();
                        }


                        if (
                            confirmEmailButton
                        ) {

                            confirmEmailButton.disabled =
                                false;

                            confirmEmailButton.innerHTML =
                                '<i class="fa-solid fa-paper-plane"></i> Send Result';
                        }

                    },
                    1200
                );

            } catch (
                error
            ) {

                console.error(
                    'Result email error:',
                    error
                );


                if (
                    emailStatus
                ) {

                    emailStatus.className =
                        'email-status show error';


                    emailStatus.textContent =
                        error.message
                        ||
                        'Unable to send the result email.';
                }


                if (
                    confirmEmailButton
                ) {

                    confirmEmailButton.disabled =
                        false;

                    confirmEmailButton.innerHTML =
                        '<i class="fa-solid fa-paper-plane"></i> Send Result';
                }
            }
        }


        if (
            confirmEmailButton
        ) {

            confirmEmailButton.addEventListener(
                'click',
                sendResultEmail
            );
        }


        function printResult() {

            window.print();
        }


        if (
            printButton
        ) {

            printButton.addEventListener(
                'click',
                printResult
            );
        }


        if (
            sidebarPrintButton
        ) {

            sidebarPrintButton.addEventListener(
                'click',
                printResult
            );
        }


        /*
        |--------------------------------------------------------------------------
        | SMOOTH ANALYSIS NAVIGATION
        |--------------------------------------------------------------------------
        */

        const analysis =
            document.getElementById(
                'questionAnalysis'
            );


        if (
            analysis
        ) {

            const analysisLink =
                document.querySelector(
                    'a[href="#questionAnalysis"]'
                );


            if (
                analysisLink
            ) {

                analysisLink.addEventListener(
                    'click',
                    function (event) {

                        event.preventDefault();

                        analysis.scrollIntoView(
                            {
                                behavior:
                                    'smooth',

                                block:
                                    'start'
                            }
                        );
                    }
                );
            }
        }

    }
);

</script>


</body>

</html>