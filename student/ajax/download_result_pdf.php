<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

if (
    session_status() !== PHP_SESSION_ACTIVE
) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| PROJECT CONFIG
|--------------------------------------------------------------------------
*/

require_once '../../config/config.php';


/*
|--------------------------------------------------------------------------
| DOMPDF
|--------------------------------------------------------------------------
*/

$autoloadFile =
    dirname(
        __DIR__,
        2
    )
    .
    '/vendor/autoload.php';


if (
    !is_file(
        $autoloadFile
    )
) {

    http_response_code(500);

    exit(
        'Dompdf is not installed. Please run: composer require dompdf/dompdf'
    );
}


require_once $autoloadFile;


use Dompdf\Dompdf;
use Dompdf\Options;


/*
|--------------------------------------------------------------------------
| STUDENT AUTH
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['user_id']
    )
    ||
    (
        $_SESSION['user_role']
        ??
        ''
    ) !==
    'student'
) {

    http_response_code(403);

    exit(
        'Unauthorized Access'
    );
}


$studentId =
    (int)(
        $_SESSION['user_id']
        ??
        0
    );


/*
|--------------------------------------------------------------------------
| ATTEMPT
|--------------------------------------------------------------------------
*/

$attemptId =
    filter_input(
        INPUT_GET,
        'attempt_id',
        FILTER_VALIDATE_INT
    );


if (
    !$attemptId
    ||
    $attemptId <= 0
) {

    http_response_code(400);

    exit(
        'Invalid attempt ID.'
    );
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function pdfEscape(
    mixed $value
): string {

    return htmlspecialchars(
        (string)(
            $value
            ??
            ''
        ),
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function pdfNumber(
    mixed $value,
    int $decimals = 2
): string {

    return number_format(
        (float)(
            $value
            ??
            0
        ),
        $decimals
    );
}


function pdfDate(
    mixed $value,
    string $format = 'd M Y'
): string {

    if (
        empty(
            $value
        )
    ) {

        return '—';
    }


    try {

        return (
            new DateTimeImmutable(
                (string)$value
            )
        )->format(
            $format
        );

    } catch (
        Throwable
    ) {

        return '—';
    }
}


function pdfInitials(
    string $name
): string {

    $name =
        trim(
            $name
        );


    if (
        $name === ''
    ) {

        return 'ES';
    }


    $parts =
        preg_split(
            '/\s+/',
            $name
        );


    if (
        count(
            $parts
        ) >= 2
    ) {

        return strtoupper(
            mb_substr(
                $parts[0],
                0,
                1
            )
            .
            mb_substr(
                $parts[
                    count(
                        $parts
                    ) - 1
                ],
                0,
                1
            )
        );
    }


    return strtoupper(
        mb_substr(
            $name,
            0,
            2
        )
    );
}


function pdfFileDataUri(
    string $filePath
): ?string {

    if (
        !is_file(
            $filePath
        )
    ) {

        return null;
    }


    $contents =
        @file_get_contents(
            $filePath
        );


    if (
        $contents === false
    ) {

        return null;
    }


    $mime =
        mime_content_type(
            $filePath
        );


    if (
        !$mime
    ) {

        $extension =
            strtolower(
                pathinfo(
                    $filePath,
                    PATHINFO_EXTENSION
                )
            );


        $mime =
            match (
                $extension
            ) {

                'png' =>
                    'image/png',

                'jpg',
                'jpeg' =>
                    'image/jpeg',

                'webp' =>
                    'image/webp',

                default =>
                    null
            };
    }


    if (
        !$mime
    ) {

        return null;
    }


    return
        'data:' .
        $mime .
        ';base64,' .
        base64_encode(
            $contents
        );
}


/*
|--------------------------------------------------------------------------
| RESULT DATA
|--------------------------------------------------------------------------
*/

$resultSql = "

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
        r.created_at AS result_created_at,

        ea.started_at,
        ea.submitted_at,

        e.title AS exam_title,
        e.passing_marks,
        e.duration_minutes,
        e.exam_type,

        s.student_code,
        s.full_name,
        s.email

    FROM results r

    INNER JOIN exam_attempts ea
        ON ea.id = r.attempt_id

    INNER JOIN exams e
        ON e.id = r.exam_id

    INNER JOIN students s
        ON s.id = r.student_id

    WHERE

        r.attempt_id = ?

        AND r.student_id = ?

    LIMIT 1
";


$resultStatement =
    $conn->prepare(
        $resultSql
    );


$resultStatement->execute([
    (int)$attemptId,
    $studentId
]);


$result =
    $resultStatement->fetch(
        PDO::FETCH_ASSOC
    );


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
| VALUES
|--------------------------------------------------------------------------
*/

$totalQuestions =
    (int)(
        $result[
            'total_questions'
        ]
        ??
        0
    );


$attemptedQuestions =
    (int)(
        $result[
            'attempted_questions'
        ]
        ??
        0
    );


$correctAnswers =
    (int)(
        $result[
            'correct_answers'
        ]
        ??
        0
    );


$wrongAnswers =
    (int)(
        $result[
            'wrong_answers'
        ]
        ??
        0
    );


$unansweredQuestions =
    (int)(
        $result[
            'unanswered_questions'
        ]
        ??
        0
    );


$totalMarks =
    (float)(
        $result[
            'total_marks'
        ]
        ??
        0
    );


$obtainedMarks =
    (float)(
        $result[
            'obtained_marks'
        ]
        ??
        0
    );


$percentage =
    (float)(
        $result[
            'percentage'
        ]
        ??
        0
    );


$passingMarks =
    (float)(
        $result[
            'passing_marks'
        ]
        ??
        0
    );


$grade =
    strtoupper(
        trim(
            (string)(
                $result[
                    'grade'
                ]
                ??
                'F'
            )
        )
    );


$resultStatus =
    trim(
        (string)(
            $result[
                'result_status'
            ]
            ??
            'Fail'
        )
    );


$isPassed =
    strcasecmp(
        $resultStatus,
        'Pass'
    ) === 0;


/*
|--------------------------------------------------------------------------
| ACCURACY
|--------------------------------------------------------------------------
*/

$accuracy =
    $attemptedQuestions > 0

        ? (
            $correctAnswers /
            $attemptedQuestions
        ) * 100

        : 0;


$accuracy =
    max(
        0,
        min(
            100,
            $accuracy
        )
    );


/*
|--------------------------------------------------------------------------
| DATE
|--------------------------------------------------------------------------
*/

$examDate =
    pdfDate(
        $result[
            'submitted_at'
        ]
        ??
        $result[
            'result_created_at'
        ]
        ??
        $result[
            'started_at'
        ]
        ??
        null
    );


/*
|--------------------------------------------------------------------------
| TIME TAKEN
|--------------------------------------------------------------------------
*/

$timeTakenMinutes =
    null;


if (
    !empty(
        $result[
            'started_at'
        ]
    )
    &&
    !empty(
        $result[
            'submitted_at'
        ]
    )
) {

    try {

        $started =
            new DateTimeImmutable(
                (string)(
                    $result[
                        'started_at'
                    ]
                )
            );


        $submitted =
            new DateTimeImmutable(
                (string)(
                    $result[
                        'submitted_at'
                    ]
                )
            );


        $seconds =
            $submitted->getTimestamp()
            -
            $started->getTimestamp();


        if (
            $seconds >= 0
        ) {

            $timeTakenMinutes =
                round(
                    $seconds / 60,
                    2
                );
        }

    } catch (
        Throwable
    ) {

        $timeTakenMinutes =
            null;
    }
}


$timeTakenText =
    $timeTakenMinutes !== null

        ? pdfNumber(
            $timeTakenMinutes
        )
        . ' min'

        : 'Not available';


/*
|--------------------------------------------------------------------------
| STUDENT INITIALS
|--------------------------------------------------------------------------
*/

$studentName =
    (string)(
        $result[
            'full_name'
        ]
        ??
        'Student'
    );


$initials =
    pdfInitials(
        $studentName
    );


/*
|--------------------------------------------------------------------------
| OPTIONAL LOGO
|--------------------------------------------------------------------------
|
| Put your logo at one of these locations
| if you have it.
|--------------------------------------------------------------------------
*/

$logoDataUri = null;


$logoCandidates = [

    dirname(
        __DIR__,
        2
    )
    .
    '/assets/images/logo.png',

    dirname(
        __DIR__,
        2
    )
    .
    '/assets/images/examsphere-logo.png',

    dirname(
        __DIR__,
        2
    )
    .
    '/assets/images/exam_logo.png'

];


foreach (
    $logoCandidates
    as $logoCandidate
) {

    $logoDataUri =
        pdfFileDataUri(
            $logoCandidate
        );


    if (
        $logoDataUri !== null
    ) {

        break;
    }
}


/*
|--------------------------------------------------------------------------
| DOMPDF OPTIONS
|--------------------------------------------------------------------------
*/

$options =
    new Options();


$options->set(
    'isHtml5ParserEnabled',
    true
);


$options->set(
    'isRemoteEnabled',
    false
);


$options->set(
    'defaultFont',
    'DejaVu Sans'
);


/*
|--------------------------------------------------------------------------
| CREATE DOMPDF
|--------------------------------------------------------------------------
*/

$dompdf =
    new Dompdf(
        $options
    );


/*
|--------------------------------------------------------------------------
| PREMIUM HTML
|--------------------------------------------------------------------------
*/

ob_start();

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">


<style>

/* =========================================================
   PAGE
========================================================= */

@page{
    size:A4;
    margin:0;
}


*{
    box-sizing:border-box;
}


html,
body{
    margin:0;
    padding:0;
}


body{

    font-family:
        "DejaVu Sans",
        Arial,
        sans-serif;

    color:#3E2723;

    background:#F5F5DC;

    font-size:10px;
}


/* =========================================================
   COLORS
========================================================= */

:root{

    --brown:#5D4037;
    --dark:#3E2723;

    --olive:#556B2F;
    --olive-light:#ECF2E2;

    --gold:#A47B29;
    --gold-light:#FFF2D7;

    --red:#A84538;
    --red-light:#FBEAE7;

    --cream:#F5F5DC;

    --soft:#FBF9F3;

    --line:#E3DCD2;

    --muted:#766D65;

    --light:#968C83;

}


/* =========================================================
   PAGE WRAPPER
========================================================= */

.page{

    width:100%;

    min-height:297mm;

    padding:
        14mm 14mm 12mm;

    background:
        #FBF9F3;

}


/* =========================================================
   TOP BRAND
========================================================= */

.brand-row{

    width:100%;

    display:table;

    table-layout:fixed;

    margin-bottom:
        8mm;

}


.brand-left{

    display:table-cell;

    width:65%;

    vertical-align:middle;

}


.brand-right{

    display:table-cell;

    width:35%;

    text-align:right;

    vertical-align:middle;

}


.logo-box{

    width:18mm;

    height:18mm;

    display:inline-block;

    vertical-align:middle;

    margin-right:4mm;

    border-radius:5mm;

    background:#5D4037;

    text-align:center;

    overflow:hidden;

}


.logo-image{

    width:18mm;

    height:18mm;

    object-fit:contain;

}


.logo-initials{

    color:#fff;

    font-size:14px;

    font-weight:bold;

    line-height:18mm;

}


.brand-name{

    display:inline-block;

    vertical-align:middle;

    color:#3E2723;

    font-size:19px;

    font-weight:bold;

}


.brand-tagline{

    margin-top:1mm;

    color:#8A8179;

    font-size:7px;

}


.verified{

    display:inline-block;

    padding:
        2.5mm 4mm;

    border-radius:
        999px;

    color:#556B2F;

    background:#ECF2E2;

    font-size:7px;

    font-weight:bold;

}


/* =========================================================
   RESULT HERO
========================================================= */

.hero{

    width:100%;

    min-height:42mm;

    padding:
        8mm;

    border-radius:
        6mm;

    background:

        linear-gradient(
            135deg,
            #5D4037 0%,
            #46312B 57%,
            #556B2F 100%
        );

    color:#fff;

    margin-bottom:
        6mm;

}


.hero-kicker{

    color:#D7D09F;

    font-size:7px;

    font-weight:bold;

    letter-spacing:1px;

}


.hero-title{

    margin-top:2mm;

    font-size:21px;

    font-weight:bold;

}


.hero-exam{

    margin-top:2mm;

    color:#E0D5CC;

    font-size:9px;

}


/* =========================================================
   STUDENT CARD
========================================================= */

.info-card{

    width:100%;

    border:
        1px solid #E3DCD2;

    border-radius:
        5mm;

    background:#fff;

    margin-bottom:
        5mm;

}


.info-table{

    width:100%;

    border-collapse:
        collapse;

}


.info-cell{

    width:50%;

    padding:
        5mm 6mm;

    vertical-align:top;

}


.info-divider{

    width:1px;

    background:#E9E2DA;

}


.section-label{

    color:#556B2F;

    font-size:7px;

    font-weight:bold;

    letter-spacing:.7px;

}


.student-name{

    margin-top:2mm;

    color:#3E2723;

    font-size:15px;

    font-weight:bold;

}


.info-label{

    margin-top:3mm;

    color:#968C83;

    font-size:6.5px;

}


.info-value{

    margin-top:1mm;

    color:#3E2723;

    font-size:8.5px;

    font-weight:bold;

}


/* =========================================================
   SCORE
========================================================= */

.score-card{

    width:100%;

    border-radius:
        6mm;

    background:#3E2723;

    color:#fff;

    margin-bottom:
        5mm;

}


.score-table{

    width:100%;

    border-collapse:
        collapse;

}


.score-main{

    width:48%;

    padding:
        6mm 7mm;

}


.score-side{

    width:26%;

    padding:
        6mm 5mm;

    text-align:center;

}


.score-status{

    width:26%;

    padding:
        6mm 5mm;

    text-align:center;

}


.score-label{

    color:#CDBFB6;

    font-size:7px;

    font-weight:bold;

    letter-spacing:.7px;

}


.score-number{

    margin-top:1mm;

    color:#fff;

    font-size:28px;

    font-weight:bold;

}


.score-marks{

    margin-top:1mm;

    color:#D9CEC7;

    font-size:8px;

}


.grade-box{

    padding:
        4mm;

    border-radius:
        4mm;

    background:#ECF2E2;

    color:#3E2723;

}


.grade-label{

    font-size:6px;

    color:#556B2F;

    font-weight:bold;

}


.grade-number{

    margin-top:1mm;

    font-size:22px;

    font-weight:bold;

}


.status-box{

    padding:
        4mm;

    border-radius:
        4mm;

}


.status-pass{

    background:#E0F1E1;

    color:#2E7D32;

}


.status-fail{

    background:#FBE8E4;

    color:#A84538;

}


.status-label{

    font-size:6px;

    font-weight:bold;

}


.status-number{

    margin-top:1mm;

    font-size:15px;

    font-weight:bold;

}


/* =========================================================
   PERFORMANCE HEADER
========================================================= */

.section-header{

    margin-top:
        5mm;

    margin-bottom:
        3mm;

}


.section-kicker{

    color:#556B2F;

    font-size:7px;

    font-weight:bold;

    letter-spacing:.8px;

}


.section-title{

    margin-top:1mm;

    color:#3E2723;

    font-size:14px;

    font-weight:bold;

}


/* =========================================================
   KPI CARDS
========================================================= */

.kpi-table{

    width:100%;

    border-collapse:
        separate;

    border-spacing:
        2.5mm 0;

    margin-left:
        -2.5mm;

    margin-right:
        -2.5mm;

}


.kpi{

    width:25%;

    padding:
        4mm;

    border:
        1px solid #E3DCD2;

    border-radius:
        4mm;

    background:#fff;

    vertical-align:top;

}


.kpi-accent{

    width:2mm;

    height:11mm;

    float:left;

    margin-right:3mm;

    border-radius:
        2mm;

}


.kpi-green{
    background:#556B2F;
}


.kpi-red{
    background:#A84538;
}


.kpi-gold{
    background:#A47B29;
}


.kpi-brown{
    background:#5D4037;
}


.kpi-label{

    color:#968C83;

    font-size:6px;

    font-weight:bold;

}


.kpi-value{

    margin-top:1mm;

    color:#3E2723;

    font-size:16px;

    font-weight:bold;

}


/* =========================================================
   QUESTION SUMMARY
========================================================= */

.summary-card{

    margin-top:
        5mm;

    padding:
        5mm 6mm;

    border:
        1px solid #E3DCD2;

    border-radius:
        5mm;

    background:#F5F5DC;

}


.summary-table{

    width:100%;

    border-collapse:
        collapse;

}


.summary-item{

    width:25%;

    vertical-align:top;

}


.summary-label{

    color:#766D65;

    font-size:6.5px;

}


.summary-value{

    margin-top:1mm;

    color:#3E2723;

    font-size:10px;

    font-weight:bold;

}


/* =========================================================
   PROGRESS
========================================================= */

.progress-card{

    margin-top:
        5mm;

    padding:
        5mm 6mm;

    border:
        1px solid #E3DCD2;

    border-radius:
        5mm;

    background:#fff;

}


.progress-header{

    width:100%;

    display:table;

}


.progress-label{

    display:table-cell;

    color:#556B2F;

    font-size:7px;

    font-weight:bold;

}


.progress-value{

    display:table-cell;

    color:#3E2723;

    font-size:9px;

    font-weight:bold;

    text-align:right;

}


.progress-track{

    width:100%;

    height:5mm;

    margin-top:3mm;

    border-radius:
        999px;

    background:#EAE5DD;

    overflow:hidden;

}


.progress-fill{

    height:5mm;

    border-radius:
        999px;

    background:#556B2F;

}


/* =========================================================
   MESSAGE
========================================================= */

.message{

    margin-top:
        5mm;

    padding:
        4mm 5mm;

    border-radius:
        4mm;

    background:#ECF2E2;

    color:#556B2F;

    font-size:7px;

    font-weight:bold;

}


/* =========================================================
   FOOTER
========================================================= */

.footer{

    position:absolute;

    left:14mm;

    right:14mm;

    bottom:8mm;

    padding-top:
        3mm;

    border-top:
        1px solid #E3DCD2;

    color:#968C83;

    font-size:6.5px;

}


.footer-table{

    width:100%;

    border-collapse:
        collapse;

}


.footer-left{

    width:70%;

}


.footer-right{

    width:30%;

    text-align:right;

}

</style>

</head>


<body>


<div class="page">


<!-- =====================================================
     BRAND
====================================================== -->

<div class="brand-row">


<div class="brand-left">


<div class="logo-box">

<?php if (
    $logoDataUri !== null
): ?>


<img
    src="<?= $logoDataUri ?>"
    class="logo-image"
    alt="ExamSphere"
>


<?php else: ?>


<div class="logo-initials">

ES

</div>


<?php endif; ?>

</div>


<div
    class="brand-name"
>

ExamSphere


<div
    class="brand-tagline"
>

Smart • Secure • Success

</div>


</div>


</div>


<div
    class="brand-right"
>


<div class="verified">

✓ VERIFIED RESULT

</div>


</div>


</div>


<!-- =====================================================
     HERO
====================================================== -->

<div class="hero">


<div
    class="hero-kicker"
>

OFFICIAL EXAMINATION RESULT

</div>


<div
    class="hero-title"
>

Examination Result

</div>


<div
    class="hero-exam"
>

<?= pdfEscape(
    $result[
        'exam_title'
    ]
    ??
    'Examination'
) ?>


</div>


</div>


<!-- =====================================================
     STUDENT + EXAM INFO
====================================================== -->

<div class="info-card">


<table class="info-table">


<tr>


<td
    class="info-cell"
>


<div class="section-label">

STUDENT INFORMATION

</div>


<div class="student-name">

<?= pdfEscape(
    $studentName
) ?>

</div>


<div class="info-label">
    Student Code
</div>


<div class="info-value">

<?= pdfEscape(
    $result[
        'student_code'
    ]
    ??
    '—'
) ?>

</div>


<div class="info-label">
    Email
</div>


<div class="info-value">

<?= pdfEscape(
    $result[
        'email'
    ]
    ??
    '—'
) ?>

</div>


</td>


<td
    class="
        info-cell
        info-divider
    "
>


<div class="section-label">

EXAMINATION DETAILS

</div>


<div class="info-label">
    Exam
</div>


<div class="info-value">

<?= pdfEscape(
    $result[
        'exam_title'
    ]
) ?>

</div>


<div class="info-label">
    Exam Date
</div>


<div class="info-value">

<?= pdfEscape(
    $examDate
) ?>

</div>


<div class="info-label">
    Time Taken
</div>


<div class="info-value">

<?= pdfEscape(
    $timeTakenText
) ?>

</div>


</td>


</tr>


</table>


</div>


<!-- =====================================================
     SCORE HERO
====================================================== -->

<div class="score-card">


<table
    class="score-table"
>


<tr>


<td
    class="score-main"
>


<div class="score-label">

OVERALL SCORE

</div>


<div class="score-number">

<?= pdfNumber(
    $percentage
) ?>%

</div>


<div class="score-marks">

Obtained:

<?= pdfNumber(
    $obtainedMarks
) ?>

/

<?= pdfNumber(
    $totalMarks
) ?>

marks

</div>


</td>


<td
    class="score-side"
>


<div class="grade-box">


<div
    class="grade-label"
>

GRADE

</div>


<div
    class="grade-number"
>

<?= pdfEscape(
    $grade
) ?>

</div>


</div>


</td>


<td
    class="score-status"
>


<div
    class="
        status-box
        <?= $isPassed
            ? 'status-pass'
            : 'status-fail' ?>"
>


<div
    class="status-label"
>

RESULT STATUS

</div>


<div
    class="status-number"
>

<?= $isPassed
    ? 'PASS'
    : 'FAIL' ?>

</div>


</div>


</td>


</tr>


</table>


</div>


<!-- =====================================================
     PERFORMANCE
====================================================== -->

<div class="section-header">


<div
    class="section-kicker"
>

PERFORMANCE BREAKDOWN

</div>


<div
    class="section-title"
>

Your examination performance

</div>


</div>


<table class="kpi-table">


<tr>


<td class="kpi">


<div
    class="
        kpi-accent
        kpi-green
    "
></div>


<div class="kpi-label">

CORRECT ANSWERS

</div>


<div class="kpi-value">

<?= $correctAnswers ?>

</div>


</td>


<td class="kpi">


<div
    class="
        kpi-accent
        kpi-red
    "
></div>


<div class="kpi-label">

WRONG ANSWERS

</div>


<div class="kpi-value">

<?= $wrongAnswers ?>

</div>


</td>


<td class="kpi">


<div
    class="
        kpi-accent
        kpi-gold
    "
></div>


<div class="kpi-label">

UNANSWERED

</div>


<div class="kpi-value">

<?= $unansweredQuestions ?>

</div>


</td>


<td class="kpi">


<div
    class="
        kpi-accent
        kpi-brown
    "
></div>


<div class="kpi-label">

ACCURACY

</div>


<div class="kpi-value">

<?= pdfNumber(
    $accuracy
) ?>%

</div>


</td>


</tr>


</table>


<!-- =====================================================
     QUESTION SUMMARY
====================================================== -->

<div class="summary-card">


<div
    class="section-label"
>

QUESTION SUMMARY

</div>


<table
    class="summary-table"
    style="margin-top:3mm;"
>


<tr>


<td class="summary-item">


<div class="summary-label">

Total Questions

</div>


<div class="summary-value">

<?= $totalQuestions ?>

</div>


</td>


<td class="summary-item">


<div class="summary-label">

Attempted

</div>


<div class="summary-value">

<?= $attemptedQuestions ?>

</div>


</td>


<td class="summary-item">


<div class="summary-label">

Skipped

</div>


<div class="summary-value">

<?= $unansweredQuestions ?>

</div>


</td>


<td class="summary-item">


<div class="summary-label">

Passing Marks

</div>


<div class="summary-value">

<?= pdfNumber(
    $passingMarks
) ?>

</div>


</td>


</tr>


</table>


</div>


<!-- =====================================================
     PROGRESS
====================================================== -->

<div class="progress-card">


<div class="progress-header">


<div class="progress-label">

SCORE PROGRESS

</div>


<div class="progress-value">

<?= pdfNumber(
    $percentage
) ?>%

</div>


</div>


<div class="progress-track">


<div
    class="progress-fill"
    style="
        width:
            <?= max(
                0,
                min(
                    100,
                    $percentage
                )
            ) ?>%;
    "
></div>


</div>


</div>


<!-- =====================================================
     MESSAGE
====================================================== -->

<div class="message">


<?php if (
    $isPassed
): ?>


Congratulations! You have successfully completed this examination.


<?php else: ?>


Examination completed. Keep practicing and prepare strongly for your next attempt.


<?php endif; ?>


</div>


<!-- =====================================================
     FOOTER
====================================================== -->

<div class="footer">


<table class="footer-table">


<tr>


<td
    class="footer-left"
>


ExamSphere


<br>


This is an electronically generated examination result.


<br>


Generated on:

<?= pdfEscape(
    date(
        'd M Y, h:i A'
    )
) ?>


</td>


<td
    class="footer-right"
>


Result ID:

<?= (int)(
    $result[
        'result_id'
    ]
) ?>


<br>


Attempt #<?= (int)$attemptId ?>


</td>


</tr>


</table>


</div>


</div>


</body>

</html>

<?php

$html =
    ob_get_clean();


/*
|--------------------------------------------------------------------------
| LOAD HTML
|--------------------------------------------------------------------------
*/

$dompdf->loadHtml(
    $html,
    'UTF-8'
);


/*
|--------------------------------------------------------------------------
| A4
|--------------------------------------------------------------------------
*/

$dompdf->setPaper(
    'A4',
    'portrait'
);


/*
|--------------------------------------------------------------------------
| RENDER
|--------------------------------------------------------------------------
*/

$dompdf->render();


/*
|--------------------------------------------------------------------------
| DOWNLOAD NAME
|--------------------------------------------------------------------------
*/

$examTitle =
    (string)(
        $result[
            'exam_title'
        ]
        ??
        'Exam'
    );


$safeExamTitle =
    preg_replace(
        '/[^A-Za-z0-9_-]+/',
        '_',
        $examTitle
    );


if (
    !is_string(
        $safeExamTitle
    )
    ||
    $safeExamTitle === ''
) {

    $safeExamTitle =
        'Exam';
}


$fileName =
    'ExamSphere_Result_' .
    $safeExamTitle .
    '_' .
    $attemptId .
    '.pdf';


/*
|--------------------------------------------------------------------------
| OUTPUT
|--------------------------------------------------------------------------
*/

while (
    ob_get_level() > 0
) {

    ob_end_clean();
}


$dompdf->stream(
    $fileName,
    [
        'Attachment' => true
    ]
);


exit;