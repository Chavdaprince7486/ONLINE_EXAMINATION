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

function practice_escape(
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


function practice_number(
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
        'Practice subjects load failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| PRACTICE EXAMS
|--------------------------------------------------------------------------
|
| Practice business rule:
|
| - exam_type = Practice
| - status = Active
| - active question count = required question count
| - every question has valid marks
| - same marks per question
| - Practice is FREE
|
| Total marks:
|
|     Question Count × Marks Per Question
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

        INNER JOIN exam_questions eq

            ON eq.exam_id =
                e.id

        INNER JOIN questions q

            ON q.id =
                eq.question_id

            AND q.status =
                'Active'

        WHERE

            e.exam_type =
                'Practice'

            AND e.status =
                'Active'

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
    | SUBJECT
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

        HAVING

            active_question_count =
                e.required_question_count

            AND marks_per_question_min > 0

            AND marks_per_question_min =
                marks_per_question_max

        ORDER BY

            COALESCE(
                e.updated_at,
                e.created_at
            ) DESC,

            e.id DESC
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
        'Practice exams load failed: ' .
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

                ORDER BY

                    r.id DESC
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
                (int) $row['exam_id']
            ] =
                $row;
        }

    } catch (
        Throwable $exception
    ) {

        error_log(
            'Practice latest results lookup failed: ' .
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
                (int) $row['exam_id']
            ] =
                $row;
        }

    } catch (
        Throwable $exception
    ) {

        error_log(
            'Practice active attempts lookup failed: ' .
            $exception->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| ENRICH EXAM DATA
|--------------------------------------------------------------------------
*/

$availableExamCount =
    0;


$attemptedExamCount =
    0;


$inProgressCount =
    0;


$totalPracticeQuestions =
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


    $marksPerQuestion =
        (float) (
            $exam[
                'marks_per_question_min'
            ] ?? 0
        );


    $marksPerQuestion =
        round(
            $marksPerQuestion,
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
            $marksPerQuestion,
            2
        );


    $exam[
        'marks_per_question'
    ] =
        $marksPerQuestion;


    $exam[
        'dynamic_total_marks'
    ] =
        $dynamicTotalMarks;


    /*
    |--------------------------------------------------------------------------
    | PRACTICE ACCESS
    |--------------------------------------------------------------------------
    */

    $exam[
        'exam_access'
    ] =
        true;


    $exam[
        'access_message'
    ] =
        'Free for every registered student.';


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
    | ACTIVE ATTEMPT
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

        $attemptedExamCount++;
    }


    $availableExamCount++;


    $totalPracticeQuestions +=
        $questionCount;
}


unset(
    $exam
);


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
    'Practice Exams';


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
        content="<?= practice_escape(
            function_exists('csrf_token')
                ? csrf_token()
                : (
                    $_SESSION['csrf_token']
                    ?? ''
                )
        ); ?>"
    >


    <title>

        <?= practice_escape(
            $pageTitle
        ); ?>

        | ExamSphere

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
         EXISTING NAV / DASHBOARD STYLE
    ====================================================== -->

    <link
        rel="stylesheet"
        href="assets/css/dashboard.css"
    >


    <style>

        /*
        =========================================================
        EXAMSPHERE PRACTICE — PREMIUM DESIGN
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

            --border-dark:
                #D8D0C3;

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
        MAIN WRAPPER
        =========================================================
        */

        .practice-premium-page {

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

        .practice-hero {

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


        .practice-hero::before {

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


        .practice-hero::after {

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


        .practice-hero-content {

            position:
                relative;

            z-index:
                2;
        }


        .practice-kicker {

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


        .practice-kicker i {
            font-size:
                10px;
        }


        .practice-hero-title {

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


        .practice-hero-title span {

            color:
                var(--olive);

            font-weight:
                700;
        }


        .practice-hero-text {

            max-width:
                800px;

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


        .practice-hero-pills {

            display:
                flex;

            flex-wrap:
                wrap;

            gap:
                8px;

            margin-top:
                18px;
        }


        .practice-hero-pill {

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


        .practice-hero-pill i {

            color:
                var(--olive);

            font-size:
                10px;
        }


        /*
        =========================================================
        HERO SIDE CARD
        =========================================================
        */

        .practice-hero-side {

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
                0 25px 50px
                rgba(
                    62,
                    39,
                    35,
                    .19
                );
        }


        .practice-hero-side-icon {

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


        .practice-hero-side small {

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


        .practice-hero-side strong {

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


        .practice-hero-side p {

            margin:
                8px 0 0;

            color:
                #DED6CE;

            font-size:
                8px;

            line-height:
                1.7;
        }


        .practice-hero-side-bottom {

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


        .practice-side-stat {

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


        .practice-side-stat span {

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


        .practice-side-stat strong {

            margin-top:
                3px;

            font-size:
                13px;
        }


        /*
        =========================================================
        SUMMARY
        =========================================================
        */

        .practice-summary-grid {

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


        .practice-summary-card {

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


        .practice-summary-card:hover {

            transform:
                translateY(
                    -2px
                );

            box-shadow:
                0 18px 38px
                rgba(
                    62,
                    39,
                    35,
                    .08
                );
        }


        .practice-summary-icon {

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


        .practice-summary-card:nth-child(2)
        .practice-summary-icon {

            color:
                var(--olive);

            background:
                var(--green-bg);
        }


        .practice-summary-card:nth-child(3)
        .practice-summary-icon {

            color:
                var(--gold);

            background:
                var(--gold-bg);
        }


        .practice-summary-card:nth-child(4)
        .practice-summary-icon {

            color:
                var(--purple);

            background:
                var(--purple-bg);
        }


        .practice-summary-content span {

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


        .practice-summary-content strong {

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

        .practice-toolbar {

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


        .practice-search {

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


        .practice-search i,
        .practice-subject i {

            color:
                #95897E;

            font-size:
                11px;
        }


        .practice-search input {

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


        .practice-search input::placeholder {

            color:
                #9B9188;
        }


        .practice-subject {

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


        .practice-subject select {

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


        .practice-toolbar-button {

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


        .practice-toolbar-button:hover {

            color:
                #FFFFFF;

            transform:
                translateY(
                    -1px
                );

            box-shadow:
                0 10px 24px
                rgba(
                    62,
                    39,
                    35,
                    .16
                );
        }


        .practice-clear-button {

            color:
                var(--brown);

            background:
                #EEE9E0;
        }


        .practice-clear-button:hover {

            color:
                var(--brown-dark);

            background:
                #E5DFD4;

            box-shadow:
                none;
        }


        /*
        =========================================================
        HEADING
        =========================================================
        */

        .practice-section-head {

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


        .practice-section-kicker {

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


        .practice-section-head h2 {

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


        .practice-section-head p {

            margin:
                5px 0 0;

            color:
                var(--muted);

            font-size:
                9px;
        }


        .practice-result-count {

            text-align:
                right;
        }


        .practice-result-count strong {

            color:
                var(--brown);

            font-size:
                26px;

            font-weight:
                900;
        }


        .practice-result-count span {

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
        EXAM GRID
        =========================================================
        */

        .practice-grid {

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
        EXAM CARD
        =========================================================
        */

        .practice-exam-card {

            position:
                relative;

            overflow:
                hidden;

            min-height:
                485px;

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


        .practice-exam-card::before {

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


        .practice-exam-card::after {

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
                .0;

            transform:
                scaleX(.4);

            transition:
                .25s ease;
        }


        .practice-exam-card:hover {

            transform:
                translateY(
                    -6px
                );

            border-color:
                #D8D0C4;

            box-shadow:
                var(--shadow-lg);
        }


        .practice-exam-card:hover::before {

            transform:
                scale(
                    1.3
                );
        }


        .practice-exam-card:hover::after {

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

        .practice-card-top {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                8px;

            position:
                relative;

            z-index:
                2;
        }


        .practice-card-number {

            display:
                grid;

            place-items:
                center;

            width:
                38px;

            height:
                38px;

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


        .practice-card-badges {

            display:
                flex;

            flex-wrap:
                wrap;

            justify-content:
                flex-end;

            gap:
                5px;
        }


        .practice-card-badge {

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


        .badge-ready {

            color:
                var(--green);

            background:
                var(--green-bg);
        }


        .badge-progress {

            color:
                var(--gold);

            background:
                var(--gold-bg);
        }


        .badge-attempted {

            color:
                var(--brown);

            background:
                #F1E9DF;
        }


        .badge-free {

            color:
                var(--olive-dark);

            background:
                #F0F4E9;
        }


        /*
        =========================================================
        CARD ICON
        =========================================================
        */

        .practice-card-icon {

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
                0 13px 27px
                rgba(
                    62,
                    39,
                    35,
                    .16
                );

            font-size:
                17px;
        }


        /*
        =========================================================
        CARD CONTENT
        =========================================================
        */

        .practice-card-content {

            position:
                relative;

            z-index:
                2;
        }


        .practice-subject-label {

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


        .practice-subject-label small {

            color:
                #A0978E;

            font-size:
                6.7px;

            letter-spacing:
                0;
        }


        .practice-card-title {

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


        .practice-card-description {

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
        CARD META
        =========================================================
        */

        .practice-meta {

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


        .practice-meta-item {

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


        .practice-meta-item i {

            display:
                block;

            margin-bottom:
                4px;

            color:
                var(--olive);

            font-size:
                9px;
        }


        .practice-meta-item span {

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


        .practice-meta-item strong {

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
        FORMULA
        =========================================================
        */

        .practice-formula {

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


        .practice-formula-label {

            display:
                flex;

            align-items:
                center;

            gap:
                6px;

            color:
                var(--olive-dark);

            font-size:
                6.6px;

            font-weight:
                800;

            text-transform:
                uppercase;

            letter-spacing:
                .6px;
        }


        .practice-formula-label i {

            font-size:
                8px;
        }


        .practice-formula-value {

            margin-top:
                5px;

            color:
                var(--brown);

            font-size:
                10px;

            font-weight:
                900;

            text-align:
                center;
        }


        /*
        =========================================================
        MARKING
        =========================================================
        */

        .practice-marking {

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


        .practice-marking-row {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                10px;

            color:
                var(--muted);

            font-size:
                7px;
        }


        .practice-marking-row
        + .practice-marking-row {

            margin-top:
                6px;

            padding-top:
                6px;

            border-top:
                1px dashed
                #EAE4DA;
        }


        .practice-marking-row i {

            margin-right:
                3px;

            color:
                var(--olive);
        }


        .practice-marking-row.warning,
        .practice-marking-row.warning i {

            color:
                var(--red);
        }


        .practice-marking-row strong {

            color:
                var(--brown-dark);

            font-size:
                7px;

            font-weight:
                800;
        }


        /*
        =========================================================
        LATEST RESULT
        =========================================================
        */

        .practice-latest-result {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                8px;

            margin-top:
                9px;

            padding:
                9px 10px;

            border-radius:
                12px;

            background:
                #F8F5EE;
        }


        .practice-latest-result span {

            display:
                block;

            color:
                #8C8278;

            font-size:
                6px;

            text-transform:
                uppercase;

            letter-spacing:
                .5px;
        }


        .practice-latest-result strong {

            display:
                block;

            margin-top:
                2px;

            color:
                var(--brown-dark);

            font-size:
                10px;

            font-weight:
                900;
        }


        .practice-result-grade {

            min-width:
                33px;

            height:
                29px;

            display:
                grid;

            place-items:
                center;

            padding:
                0 6px;

            border-radius:
                9px;

            color:
                var(--olive-dark);

            background:
                #EAF2DF;

            font-size:
                8px;

            font-weight:
                900;
        }


        /*
        =========================================================
        ACCESS MESSAGE
        =========================================================
        */

        .practice-access {

            margin-top:
                9px;

            padding:
                8px 10px;

            border-radius:
                10px;

            color:
                var(--olive-dark);

            background:
                var(--green-bg);

            font-size:
                7px;

            line-height:
                1.5;

            font-weight:
                600;
        }


        /*
        =========================================================
        CARD ACTION
        =========================================================
        */

        .practice-card-action {

            display:
                flex;

            gap:
                7px;

            margin-top:
                auto;

            padding-top:
                14px;
        }


        .practice-primary-btn {

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


        .practice-primary-btn:hover {

            color:
                #FFFFFF;

            transform:
                translateY(
                    -1px
                );

            box-shadow:
                0 11px 23px
                rgba(
                    62,
                    39,
                    35,
                    .16
                );
        }


        .practice-primary-btn.olive {

            background:
                linear-gradient(
                    135deg,
                    var(--olive),
                    var(--olive-dark)
                );
        }


        .practice-secondary-btn {

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


        .practice-secondary-btn:hover {

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

        .practice-info-strip {

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


        .practice-info {

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


        .practice-info-icon {

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


        .practice-info strong {

            display:
                block;

            color:
                var(--brown-dark);

            font-size:
                7.5px;

            font-weight:
                800;
        }


        .practice-info small {

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
        EMPTY STATE
        =========================================================
        */

        .practice-empty {

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


        .practice-empty-icon {

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


        .practice-empty-kicker {

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


        .practice-empty h3 {

            margin:
                6px 0;

            color:
                var(--brown-dark);

            font-size:
                21px;

            font-weight:
                900;
        }


        .practice-empty p {

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


        .practice-empty-btn {

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


        .practice-empty-btn:hover {

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

            .practice-grid {

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

            .practice-hero {

                grid-template-columns:
                    1fr;
            }


            .practice-hero-side {

                width:
                    100%;
            }


            .practice-summary-grid {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(
                            0,
                            1fr
                        )
                    );
            }


            .practice-toolbar {

                flex-wrap:
                    wrap;
            }


            .practice-search {

                flex:
                    1 1
                    100%;
            }


            .practice-subject {

                flex:
                    1;

                width:
                    auto;
            }


            .practice-info-strip {

                grid-template-columns:
                    1fr;
            }
        }


        @media (
            max-width: 720px
        ) {

            .practice-premium-page {

                width:
                    calc(
                        100% - 18px
                    );

                margin:
                    12px auto 40px;
            }


            .practice-hero {

                padding:
                    20px;

                border-radius:
                    21px;
            }


            .practice-hero-title {

                font-size:
                    35px;

                letter-spacing:
                    -.04em;
            }


            .practice-hero-text {

                font-size:
                    9px;
            }


            .practice-summary-grid {

                grid-template-columns:
                    1fr;
            }


            .practice-toolbar {

                display:
                    grid;

                grid-template-columns:
                    1fr;
            }


            .practice-search,
            .practice-subject {

                width:
                    100%;
            }


            .practice-toolbar-button {

                width:
                    100%;
            }


            .practice-section-head {

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }


            .practice-result-count {

                text-align:
                    left;
            }


            .practice-grid {

                grid-template-columns:
                    1fr;
            }


            .practice-exam-card {

                min-height:
                    0;
            }
        }


        @media (
            max-width: 430px
        ) {

            .practice-hero-pills {

                flex-direction:
                    column;
            }


            .practice-hero-pill {

                width:
                    fit-content;
            }


            .practice-meta {

                grid-template-columns:
                    1fr;
            }


            .practice-card-action {

                flex-direction:
                    column;
            }


            .practice-secondary-btn {

                width:
                    100%;
            }


            .practice-hero-side-bottom {

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
        practice-premium-page
    "
>


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section
        class="
            practice-hero
        "
    >


        <div
            class="
                practice-hero-content
            "
        >


            <span
                class="
                    practice-kicker
                "
            >

                <i
                    class="
                        fa-solid
                        fa-bolt
                    "
                ></i>

                FREE PRACTICE ZONE

            </span>


            <h1
                class="
                    practice-hero-title
                "
            >

                Practice
                smarter.

                <span>
                    Perform stronger.
                </span>

            </h1>


            <p
                class="
                    practice-hero-text
                "
            >

                Build real examination confidence with
                complete practice examinations designed
                to help you understand your preparation,
                improve accuracy and perform better.

            </p>


            <div
                class="
                    practice-hero-pills
                "
            >


                <span
                    class="
                        practice-hero-pill
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-infinity
                        "
                    ></i>

                    Always Free

                </span>


                <span
                    class="
                        practice-hero-pill
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-shield-halved
                        "
                    ></i>

                    Verified Questions

                </span>


                <span
                    class="
                        practice-hero-pill
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-chart-line
                        "
                    ></i>

                    Instant Analysis

                </span>


                <span
                    class="
                        practice-hero-pill
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-rotate
                        "
                    ></i>

                    Practise Again

                </span>


            </div>


        </div>


        <div
            class="
                practice-hero-side
            "
        >


            <div
                class="
                    practice-hero-side-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-graduation-cap
                    "
                ></i>

            </div>


            <small>
                Practice Library
            </small>


            <strong>
                <?= $availableExamCount; ?>
            </strong>


            <p>

                Complete, active practice
                examinations currently available
                for you.

            </p>


            <div
                class="
                    practice-hero-side-bottom
                "
            >


                <div
                    class="
                        practice-side-stat
                    "
                >

                    <span>
                        Questions
                    </span>

                    <strong>

                        <?= $totalPracticeQuestions; ?>

                    </strong>

                </div>


                <div
                    class="
                        practice-side-stat
                    "
                >

                    <span>
                        Attempted
                    </span>

                    <strong>

                        <?= $attemptedExamCount; ?>

                    </strong>

                </div>


            </div>


        </div>


    </section>


    <!-- =====================================================
         SUMMARY
    ====================================================== -->

    <section
        class="
            practice-summary-grid
        "
    >


        <div
            class="
                practice-summary-card
            "
        >


            <div
                class="
                    practice-summary-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-file-circle-check
                    "
                ></i>

            </div>


            <div
                class="
                    practice-summary-content
                "
            >

                <span>
                    Ready Exams
                </span>

                <strong>

                    <?= $availableExamCount; ?>

                </strong>

            </div>


        </div>


        <div
            class="
                practice-summary-card
            "
        >


            <div
                class="
                    practice-summary-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-infinity
                    "
                ></i>

            </div>


            <div
                class="
                    practice-summary-content
                "
            >

                <span>
                    Free Access
                </span>

                <strong>

                    <?= $availableExamCount; ?>

                </strong>

            </div>


        </div>


        <div
            class="
                practice-summary-card
            "
        >


            <div
                class="
                    practice-summary-icon
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
                    practice-summary-content
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
                practice-summary-card
            "
        >


            <div
                class="
                    practice-summary-icon
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
                    practice-summary-content
                "
            >

                <span>
                    Attempted
                </span>

                <strong>

                    <?= $attemptedExamCount; ?>

                </strong>

            </div>


        </div>


    </section>


    <!-- =====================================================
         FILTER TOOLBAR
    ====================================================== -->

    <section
        class="
            practice-toolbar
        "
    >


        <form
            method="GET"
            action="practice_exams.php"
            style="display:contents;"
        >


            <label
                class="
                    practice-search
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
                    value="<?= practice_escape(
                        $search
                    ); ?>"
                    placeholder="Search exam, subject or code..."
                    autocomplete="off"
                >

            </label>


            <label
                class="
                    practice-subject
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


                    <option value="">
                        All Subjects
                    </option>


                    <?php foreach (
                        $subjects as $subject
                    ): ?>


                        <option
                            value="<?= (int) $subject['id']; ?>"
                            <?= (
                                $subjectId !== null
                                &&
                                $subjectId ===
                                    (int) $subject['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= practice_escape(
                                $subject['name']
                            ); ?>

                            <?php if (
                                !empty(
                                    $subject['code']
                                )
                            ): ?>

                                —
                                <?= practice_escape(
                                    $subject['code']
                                ); ?>

                            <?php endif; ?>

                        </option>


                    <?php endforeach; ?>


                </select>


            </label>


            <button
                type="submit"
                class="
                    practice-toolbar-button
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
                    href="practice_exams.php"
                    class="
                        practice-toolbar-button
                        practice-clear-button
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
         SECTION HEADING
    ====================================================== -->

    <section
        class="
            practice-section-head
        "
    >


        <div>


            <div
                class="
                    practice-section-kicker
                "
            >

                <?= $filterActive
                    ? 'Filtered Practice Library'
                    : 'Complete Practice Library'
                ?>

            </div>


            <h2>
                Choose your next challenge
            </h2>


            <p>

                <?= $filterActive
                    ? 'Practice exams matching your current filters.'
                    : 'Published active practice exams appear here automatically.'
                ?>

            </p>


        </div>


        <div
            class="
                practice-result-count
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
         EXAMS
    ====================================================== -->

    <?php if (
        !empty($exams)
    ): ?>


        <section
            class="
                practice-grid
            "
        >


            <?php foreach (
                $exams as $index => $exam
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


                $negativeMarking =
                    (int) (
                        $exam[
                            'negative_marking'
                        ] ?? 0
                    ) === 1;


                $latestResult =
                    $exam[
                        'latest_result'
                    ] ?? null;


                $activeAttempt =
                    $exam[
                        'active_attempt'
                    ] ?? null;


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
                        'Strengthen your preparation with this complete practice examination.';
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


                /*
                |--------------------------------------------------------------------------
                | STATE
                |--------------------------------------------------------------------------
                */

                if (
                    $activeAttempt
                ) {

                    $stateClass =
                        'badge-progress';


                    $stateIcon =
                        'fa-play';


                    $stateLabel =
                        'In Progress';

                } elseif (
                    $latestResult
                ) {

                    $stateClass =
                        'badge-attempted';


                    $stateIcon =
                        'fa-check';


                    $stateLabel =
                        'Attempted';

                } else {

                    $stateClass =
                        'badge-ready';


                    $stateIcon =
                        'fa-circle-check';


                    $stateLabel =
                        'Ready';
                }


                ?>


                <article
                    class="
                        practice-exam-card
                    "
                    data-exam-id="<?= $examId; ?>"
                >


                    <!-- =================================================
                         CARD TOP
                    ================================================== -->

                    <div
                        class="
                            practice-card-top
                        "
                    >


                        <span
                            class="
                                practice-card-number
                            "
                        >

                            <?= practice_escape(
                                $cardNumber
                            ); ?>

                        </span>


                        <div
                            class="
                                practice-card-badges
                            "
                        >


                            <span
                                class="
                                    practice-card-badge
                                    <?= $stateClass; ?>
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        <?= $stateIcon; ?>
                                    "
                                ></i>

                                <?= practice_escape(
                                    $stateLabel
                                ); ?>

                            </span>


                            <span
                                class="
                                    practice-card-badge
                                    badge-free
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-infinity
                                    "
                                ></i>

                                Free

                            </span>


                        </div>


                    </div>


                    <!-- =================================================
                         ICON
                    ================================================== -->

                    <div
                        class="
                            practice-card-icon
                        "
                    >

                        <i
                            class="
                                fa-solid
                                fa-file-pen
                            "
                        ></i>

                    </div>


                    <!-- =================================================
                         CONTENT
                    ================================================== -->

                    <div
                        class="
                            practice-card-content
                        "
                    >


                        <div
                            class="
                                practice-subject-label
                            "
                        >

                            <?= practice_escape(
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
                                    <?= practice_escape(
                                        $exam[
                                            'subject_code'
                                        ]
                                    ); ?>

                                </small>

                            <?php endif; ?>


                        </div>


                        <h3
                            class="
                                practice-card-title
                            "
                        >

                            <?= practice_escape(
                                $exam[
                                    'title'
                                ]
                            ); ?>

                        </h3>


                        <p
                            class="
                                practice-card-description
                            "
                        >

                            <?= practice_escape(
                                $description
                            ); ?>

                        </p>


                    </div>


                    <!-- =================================================
                         META
                    ================================================== -->

                    <div
                        class="
                            practice-meta
                        "
                    >


                        <div
                            class="
                                practice-meta-item
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
                                practice-meta-item
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
                                practice-meta-item
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

                                <?= practice_number(
                                    $totalMarks
                                ); ?>

                            </strong>

                        </div>


                    </div>


                    <!-- =================================================
                         DYNAMIC FORMULA
                    ================================================== -->

                    <div
                        class="
                            practice-formula
                        "
                    >


                        <div
                            class="
                                practice-formula-label
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-calculator
                                "
                            ></i>

                            Dynamic marks calculation

                        </div>


                        <div
                            class="
                                practice-formula-value
                            "
                        >

                            <?= $questionCount; ?>

                            ×

                            <?= practice_number(
                                $marksPerQuestion
                            ); ?>

                            =

                            <?= practice_number(
                                $totalMarks
                            ); ?>

                            Marks

                        </div>


                    </div>


                    <!-- =================================================
                         MARKING
                    ================================================== -->

                    <div
                        class="
                            practice-marking
                        "
                    >


                        <div
                            class="
                                practice-marking-row
                            "
                        >

                            <span>

                                <i
                                    class="
                                        fa-solid
                                        fa-award
                                    "
                                ></i>

                                Passing Marks

                            </span>


                            <strong>

                                <?= practice_number(
                                    $passingMarks
                                ); ?>

                            </strong>

                        </div>


                        <div
                            class="
                                practice-marking-row
                            "
                        >

                            <span>

                                <i
                                    class="
                                        fa-solid
                                        fa-circle-check
                                    "
                                ></i>

                                Per Question

                            </span>


                            <strong>

                                <?= practice_number(
                                    $marksPerQuestion
                                ); ?>

                            </strong>

                        </div>


                        <div
                            class="
                                practice-marking-row
                                <?= $negativeMarking
                                    ? 'warning'
                                    : ''
                                ?>"
                        >

                            <span>


                                <?php if (
                                    $negativeMarking
                                ): ?>

                                    <i
                                        class="
                                            fa-solid
                                            fa-triangle-exclamation
                                        "
                                    ></i>

                                    Negative Marking

                                <?php else: ?>

                                    <i
                                        class="
                                            fa-solid
                                            fa-shield-check
                                        "
                                    ></i>

                                    Negative Marking

                                <?php endif; ?>


                            </span>


                            <strong>

                                <?= $negativeMarking
                                    ? 'Enabled'
                                    : 'None'
                                ?>

                            </strong>

                        </div>


                        <div
                            class="
                                practice-marking-row
                            "
                        >

                            <span>

                                <i
                                    class="
                                        fa-solid
                                        fa-database
                                    "
                                ></i>

                                Question Setup

                            </span>


                            <strong>

                                <?= $questionCount; ?>

                                /

                                <?= $requiredCount; ?>

                            </strong>

                        </div>


                    </div>


                    <!-- =================================================
                         LATEST RESULT
                    ================================================== -->

                    <?php if (
                        $latestResult
                    ): ?>


                        <div
                            class="
                                practice-latest-result
                            "
                        >


                            <div>


                                <span>
                                    Latest Result
                                </span>


                                <strong>

                                    <?= practice_number(
                                        $latestResult[
                                            'obtained_marks'
                                        ] ?? 0
                                    ); ?>

                                    /

                                    <?= practice_number(
                                        $latestResult[
                                            'total_marks'
                                        ] ?? $totalMarks
                                    ); ?>

                                    &nbsp;•&nbsp;

                                    <?= practice_number(
                                        $latestResult[
                                            'percentage'
                                        ] ?? 0
                                    ); ?>%

                                </strong>


                            </div>


                            <div
                                class="
                                    practice-result-grade
                                "
                            >

                                <?= practice_escape(
                                    $latestResult[
                                        'grade'
                                    ] ?? '-'
                                ); ?>

                            </div>


                        </div>


                    <?php endif; ?>


                    <!-- =================================================
                         ACCESS
                    ================================================== -->

                    <div
                        class="
                            practice-access
                        "
                    >

                        <i
                            class="
                                fa-solid
                                fa-circle-check
                            "
                        ></i>

                        Free access for registered students

                    </div>


                    <!-- =================================================
                         ACTION
                    ================================================== -->

                    <div
                        class="
                            practice-card-action
                        "
                    >


                        <?php if (
                            $activeAttempt
                        ): ?>


                            <a
                                href="start_exam.php?id=<?= $examId; ?>"
                                class="
                                    practice-primary-btn
                                    olive
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-play
                                    "
                                ></i>

                                Resume Practice

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
                                    href="result.php?id=<?= (int) $latestResult['id']; ?>"
                                    class="
                                        practice-secondary-btn
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
                            $latestResult
                        ): ?>


                            <a
                                href="start_exam.php?id=<?= $examId; ?>"
                                class="
                                    practice-primary-btn
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-rotate
                                    "
                                ></i>

                                Practise Again

                                <i
                                    class="
                                        fa-solid
                                        fa-arrow-right
                                    "
                                ></i>

                            </a>


                            <a
                                href="result.php?id=<?= (int) $latestResult['id']; ?>"
                                class="
                                    practice-secondary-btn
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


                        <?php else: ?>


                            <a
                                href="start_exam.php?id=<?= $examId; ?>"
                                class="
                                    practice-primary-btn
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-rocket
                                    "
                                ></i>

                                Start Practice

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
             EMPTY STATE
        ================================================== -->

        <section
            class="
                practice-empty
            "
        >


            <div
                class="
                    practice-empty-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-magnifying-glass
                    "
                ></i>

            </div>


            <div
                class="
                    practice-empty-kicker
                "
            >

                <?= $filterActive
                    ? 'NO MATCHING EXAMS'
                    : 'PRACTICE LIBRARY EMPTY'
                ?>

            </div>


            <h3>

                <?= $filterActive
                    ? 'No matching practice exams found.'
                    : 'No practice exams are available yet.'
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

                    New active practice examinations
                    published by Admin or Teacher will
                    automatically appear here.

                <?php endif; ?>

            </p>


            <?php if (
                $filterActive
            ): ?>


                <a
                    href="practice_exams.php"
                    class="
                        practice-empty-btn
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
            practice-info-strip
        "
    >


        <div
            class="
                practice-info
            "
        >

            <span
                class="
                    practice-info-icon
            "
            >

                <i
                    class="
                        fa-solid
                        fa-shield-halved
                    "
                ></i>

            </span>


            <div>

                <strong>
                    Verified question set
                </strong>

                <small>

                    Only exams with the complete configured
                    active question set are shown.

                </small>

            </div>

        </div>


        <div
            class="
                practice-info
            "
        >

            <span
                class="
                    practice-info-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-calculator
                    "
                ></i>

            </span>


            <div>

                <strong>
                    Dynamic total marks
                </strong>

                <small>

                    Total marks are calculated from
                    questions × marks per question.

                </small>

            </div>

        </div>


        <div
            class="
                practice-info
            "
        >

            <span
                class="
                    practice-info-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-infinity
                    "
                ></i>

            </span>


            <div>

                <strong>
                    Always free
                </strong>

                <small>

                    Practice exams do not require
                    a subscription or premium payment.

                </small>

            </div>

        </div>


    </section>


</main>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        /*
        |--------------------------------------------------------------------------
        | SEARCH AUTO FOCUS
        |--------------------------------------------------------------------------
        */

        const search =
            document.querySelector(
                'input[name="search"]'
            );


        if (
            search
        ) {

            search.addEventListener(
                'keydown',
                function (event) {

                    if (
                        event.key ===
                        'Enter'
                    ) {

                        const form =
                            search.closest(
                                'form'
                            );


                        if (
                            form
                        ) {

                            form.submit();
                        }
                    }

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CARD ACCESSIBILITY
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                '.practice-exam-card'
            )
            .forEach(
                function (card) {

                    card.addEventListener(
                        'keydown',
                        function (event) {

                            if (
                                event.key !==
                                'Enter'
                            ) {

                                return;
                            }


                            if (
                                event.target.closest(
                                    'a, button, input, select'
                                )
                            ) {

                                return;
                            }


                            const action =
                                card.querySelector(
                                    '.practice-primary-btn'
                                );


                            if (
                                action
                            ) {

                                action.click();
                            }

                        }
                    );

                }
            );


        /*
        |--------------------------------------------------------------------------
        | SUBTLE CARD REVEAL
        |--------------------------------------------------------------------------
        */

        const cards =
            document.querySelectorAll(
                '.practice-exam-card'
            );


        cards.forEach(
            function (
                card,
                index
            ) {

                card.style.opacity =
                    '0';

                card.style.transform =
                    'translateY(14px)';


                setTimeout(
                    function () {

                        card.style.transition =
                            'opacity .45s ease, transform .45s ease';


                        card.style.opacity =
                            '1';

                        card.style.transform =
                            'translateY(0)';

                    },
                    40 +
                    (
                        index *
                        55
                    )
                );

            }
        );


    }
);

</script>


</body>

</html>