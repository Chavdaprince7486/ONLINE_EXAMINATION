<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| Result PDF Template
|--------------------------------------------------------------------------
|
| Variables supplied by:
|
| - student/ajax/download_result_pdf.php
| - student/ajax/send_result_email.php
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| SAFE HELPERS
|--------------------------------------------------------------------------
*/

if (
    !function_exists('pdf_escape')
) {

    function pdf_escape(
        mixed $value
    ): string {

        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}


if (
    !function_exists('pdf_number')
) {

    function pdf_number(
        mixed $value
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
}


if (
    !function_exists('pdf_date')
) {

    function pdf_date(
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
}


/*
|--------------------------------------------------------------------------
| RESULT VALUES
|--------------------------------------------------------------------------
*/

$totalQuestions =
    max(
        0,
        (int) (
            $totalQuestions
            ??
            $result['total_questions']
            ??
            0
        )
    );


$attemptedQuestions =
    max(
        0,
        (int) (
            $attemptedQuestions
            ??
            $result['attempted_questions']
            ??
            0
        )
    );


$correctAnswers =
    max(
        0,
        (int) (
            $correctAnswers
            ??
            $result['correct_answers']
            ??
            0
        )
    );


$wrongAnswers =
    max(
        0,
        (int) (
            $wrongAnswers
            ??
            $result['wrong_answers']
            ??
            0
        )
    );


$unansweredQuestions =
    max(
        0,
        (int) (
            $unansweredQuestions
            ??
            $result['unanswered_questions']
            ??
            0
        )
    );


$totalMarks =
    max(
        0,
        (float) (
            $totalMarks
            ??
            $result['total_marks']
            ??
            0
        )
    );


$obtainedMarks =
    max(
        0,
        min(
            $totalMarks,
            (float) (
                $obtainedMarks
                ??
                $result['obtained_marks']
                ??
                0
            )
        )
    );


$percentage =
    max(
        0,
        min(
            100,
            (float) (
                $percentage
                ??
                $result['percentage']
                ??
                0
            )
        )
    );


$passingMarks =
    max(
        0,
        (float) (
            $passingMarks
            ??
            $result['passing_marks']
            ??
            0
        )
    );


$accuracy =
    $attemptedQuestions > 0

        ? round(
            (
                $correctAnswers
                /
                $attemptedQuestions
            ) * 100,
            2
        )

        : 0;


$attemptedPercent =
    $totalQuestions > 0
        ? min(
            100,
            max(
                0,
                round(
                    (
                        $attemptedQuestions
                        /
                        $totalQuestions
                    ) * 100
                )
            )
        )
        : 0;


$correctPercent =
    $totalQuestions > 0
        ? min(
            100,
            max(
                0,
                round(
                    (
                        $correctAnswers
                        /
                        $totalQuestions
                    ) * 100
                )
            )
        )
        : 0;


$wrongPercent =
    $totalQuestions > 0
        ? min(
            100,
            max(
                0,
                round(
                    (
                        $wrongAnswers
                        /
                        $totalQuestions
                    ) * 100
                )
            )
        )
        : 0;


$unansweredPercent =
    $totalQuestions > 0
        ? min(
            100,
            max(
                0,
                round(
                    (
                        $unansweredQuestions
                        /
                        $totalQuestions
                    ) * 100
                )
            )
        )
        : 0;


$grade =
    trim(
        (string) (
            $result['grade']
            ?? ''
        )
    );


if (
    $grade === ''
) {

    $grade =
        match (true) {

            $percentage >= 90 => 'A+',
            $percentage >= 80 => 'A',
            $percentage >= 70 => 'B+',
            $percentage >= 60 => 'B',
            $percentage >= 50 => 'C',
            $percentage >= 40 => 'D',

            default => 'F'
        };
}


$resultStatus =
    trim(
        (string) (
            $result['result_status']
            ?? ''
        )
    );


$isPassed =
    $resultStatus === 'Pass';


$statusText =
    $isPassed
        ? 'PASS'
        : 'FAIL';


$statusColor =
    $isPassed
        ? '#556B2F'
        : '#93483E';


$statusBackground =
    $isPassed
        ? '#EEF4E5'
        : '#F9ECE9';


$examTitle =
    trim(
        (string) (
            $result['exam_title']
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


if (
    $subjectName === ''
) {

    $subjectName =
        'General';
}


$examType =
    trim(
        (string) (
            $result['exam_type']
            ?? 'Examination'
        )
    );


$studentName =
    trim(
        (string) (
            $result['full_name']
            ?? 'Student'
        )
    );


$studentCode =
    trim(
        (string) (
            $result['student_code']
            ?? '-'
        )
    );


$email =
    trim(
        (string) (
            $result['email']
            ?? '-'
        )
    );


$resultId =
    (int) (
        $result['result_id']
        ?? 0
    );


$attemptId =
    (int) (
        $result['attempt_id']
        ?? 0
    );


$durationMinutes =
    max(
        0,
        (int) (
            $result['duration_minutes']
            ?? 0
        )
    );


$negativeMarking =
    (int) (
        $result['negative_marking']
        ?? 0
    );


$submittedAt =
    $result['submitted_at']
    ??
    $result['result_created_at']
    ??
    null;


$startedAt =
    $result['started_at']
    ??
    null;


/*
|--------------------------------------------------------------------------
| TIME TAKEN
|--------------------------------------------------------------------------
*/

$timeTakenSeconds =
    0;


if (
    !empty($startedAt)
    &&
    !empty($submittedAt)
) {

    try {

        $start =
            new DateTimeImmutable(
                (string) $startedAt
            );


        $end =
            new DateTimeImmutable(
                (string) $submittedAt
            );


        $timeTakenSeconds =
            max(
                0,
                $end->getTimestamp()
                -
                $start->getTimestamp()
            );

    } catch (Throwable) {

        $timeTakenSeconds =
            0;
    }
}


$timeTakenMinutes =
    $timeTakenMinutes
    ??
    (
        $timeTakenSeconds > 0
            ? (int) ceil(
                $timeTakenSeconds / 60
            )
            : 0
    );


if (
    $timeTakenMinutes >= 60
) {

    $hours =
        intdiv(
            $timeTakenMinutes,
            60
        );


    $minutes =
        $timeTakenMinutes % 60;


    $timeTakenText =
        $hours .
        ' hr ' .
        $minutes .
        ' min';

} elseif (
    $timeTakenMinutes > 0
) {

    $timeTakenText =
        $timeTakenMinutes .
        ' min';

} else {

    $timeTakenText =
        'Not available';
}


/*
|--------------------------------------------------------------------------
| RESULT DATE
|--------------------------------------------------------------------------
*/

$formattedDate =
    pdf_date(
        $submittedAt
    );


/*
|--------------------------------------------------------------------------
| PERFORMANCE MESSAGE
|--------------------------------------------------------------------------
*/

$remark =
    $isPassed

        ? 'Congratulations! You passed the examination.'

        : 'Keep practising and continue improving your preparation.';


$remarkText =
    $isPassed

        ? 'Your final score has met or exceeded the configured passing marks.'

        : 'Review the detailed analysis and use the weak areas to guide your next practice sessions.';


/*
|--------------------------------------------------------------------------
| SCORE POSITION
|--------------------------------------------------------------------------
*/

$scorePosition =
    min(
        100,
        max(
            0,
            $percentage
        )
    );


/*
|--------------------------------------------------------------------------
| LOGO
|--------------------------------------------------------------------------
*/

$possibleLogoPaths = [

    dirname(
        __DIR__,
        2
    )
    . DIRECTORY_SEPARATOR
    . 'assets'
    . DIRECTORY_SEPARATOR
    . 'images'
    . DIRECTORY_SEPARATOR
    . 'exam_logo.png',

    dirname(
        __DIR__,
        2
    )
    . DIRECTORY_SEPARATOR
    . 'student'
    . DIRECTORY_SEPARATOR
    . 'assets'
    . DIRECTORY_SEPARATOR
    . 'images'
    . DIRECTORY_SEPARATOR
    . 'exam_logo.png'

];


$logoSrc =
    '';


foreach (
    $possibleLogoPaths
    as $candidate
) {

    if (
        is_file($candidate)
    ) {

        $realLogo =
            realpath(
                $candidate
            );


        if (
            $realLogo !== false
        ) {

            $logoSrc =
                str_replace(
                    '\\',
                    '/',
                    $realLogo
                );


            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| QUESTION ANALYSIS
|--------------------------------------------------------------------------
*/

$questionRows =
    is_array(
        $questions
        ??
        []
    )
        ? $questions
        : [];


$uniqueQuestionRows =
    [];


foreach (
    $questionRows
    as $question
) {

    $questionId =
        (int) (
            $question['question_id']
            ??
            0
        );


    if (
        $questionId <= 0
    ) {
        continue;
    }


    if (
        isset(
            $uniqueQuestionRows[
                $questionId
            ]
        )
    ) {
        continue;
    }


    $uniqueQuestionRows[
        $questionId
    ] =
        $question;
}


$questionRows =
    array_values(
        $uniqueQuestionRows
    );


?>

<style>

/*
|--------------------------------------------------------------------------
| PDF GLOBAL
|--------------------------------------------------------------------------
*/

body {

    color:
        #333333;

    font-family:
        dejavusans;

    font-size:
        8px;

    line-height:
        1.45;
}


table {

    border-collapse:
        collapse;
}


.pdf-page {

    width:
        100%;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.top-header {

    width:
        100%;

    margin-bottom:
        16px;
}


.brand-side {

    width:
        62%;

    vertical-align:
        middle;
}


.report-side {

    width:
        38%;

    vertical-align:
        top;

    text-align:
        right;
}


.logo {

    width:
        38px;

    height:
        38px;

    margin-right:
        10px;

    vertical-align:
        middle;
}


.brand-block {

    display:
        inline-block;

    vertical-align:
        middle;
}


.brand-name {

    color:
        #5D4037;

    font-size:
        20px;

    font-weight:
        bold;
}


.brand-line {

    margin-top:
        3px;

    color:
        #7C7167;

    font-size:
        6px;

    letter-spacing:
        .7px;
}


.report-kicker {

    color:
        #556B2F;

    font-size:
        7px;

    font-weight:
        bold;

    letter-spacing:
        1px;
}


.report-id,
.report-attempt,
.report-date {

    margin-top:
        4px;

    color:
        #777777;

    font-size:
        7px;
}


/*
|--------------------------------------------------------------------------
| HERO
|--------------------------------------------------------------------------
*/

.hero-table {

    width:
        100%;

    margin-bottom:
        15px;

    background:
        #5D4037;
}


.hero-main {

    width:
        73%;

    padding:
        20px;

    color:
        #FFFFFF;
}


.hero-score {

    width:
        27%;

    padding:
        20px 15px;

    text-align:
        center;

    color:
        #FFFFFF;

    background:
        #3E2723;
}


.hero-overline {

    color:
        #E2EFCF;

    font-size:
        7px;

    font-weight:
        bold;

    letter-spacing:
        1.2px;
}


.hero-title {

    margin-top:
        5px;

    font-size:
        20px;

    font-weight:
        bold;

    line-height:
        1.25;
}


.hero-meta {

    margin-top:
        7px;

    color:
        #F1EADF;

    font-size:
        8px;
}


.hero-description {

    margin-top:
        10px;

    color:
        #EDE5DD;

    font-size:
        7px;

    line-height:
        1.6;
}


.score-caption {

    color:
        #E1D5CD;

    font-size:
        7px;

    font-weight:
        bold;

    letter-spacing:
        1px;
}


.score-value {

    margin-top:
        7px;

    font-size:
        29px;

    font-weight:
        bold;
}


.score-status {

    margin-top:
        6px;

    font-size:
        8px;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| SECTION
|--------------------------------------------------------------------------
*/

.section-card {

    margin-bottom:
        14px;

    padding:
        14px;

    border:
        1px solid
        #E4DDD6;

    background:
        #FFFFFF;
}


.section-heading {

    margin-bottom:
        11px;

    color:
        #5D4037;

    font-size:
        9px;

    font-weight:
        bold;

    letter-spacing:
        .8px;
}


.section-number {

    display:
        inline-block;

    width:
        19px;

    height:
        17px;

    margin-right:
        6px;

    padding-top:
        2px;

    border-radius:
        5px;

    color:
        #FFFFFF;

    background:
        #556B2F;

    text-align:
        center;

    font-size:
        7px;

    vertical-align:
        middle;
}


/*
|--------------------------------------------------------------------------
| PROFILE
|--------------------------------------------------------------------------
*/

.profile-grid {

    width:
        100%;
}


.profile-grid td {

    width:
        33.33%;

    padding:
        8px 10px;

    border:
        1px solid
        #EEE8E1;

    vertical-align:
        top;
}


.label {

    margin-bottom:
        3px;

    color:
        #8A8179;

    font-size:
        6px;

    font-weight:
        bold;

    letter-spacing:
        .8px;
}


.value {

    color:
        #3E2723;

    font-size:
        8px;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| SNAPSHOT
|--------------------------------------------------------------------------
*/

.snapshot-table {

    width:
        100%;
}


.snapshot-table td {

    width:
        25%;

    padding:
        4px;
}


.snapshot-card {

    min-height:
        59px;

    padding:
        10px;

    border:
        1px solid
        #E8E1D8;
}


.snapshot-card.olive {

    background:
        #EEF4E5;
}


.snapshot-card.green {

    background:
        #EEF7EF;
}


.snapshot-card.brown {

    background:
        #F6EFEB;
}


.snapshot-card.beige {

    background:
        #F5F5DC;
}


.snapshot-label {

    color:
        #7A736C;

    font-size:
        6px;

    font-weight:
        bold;

    letter-spacing:
        .7px;
}


.snapshot-value {

    margin-top:
        5px;

    color:
        #5D4037;

    font-size:
        17px;

    font-weight:
        bold;
}


.snapshot-value span {

    color:
        #877B72;

    font-size:
        8px;

    font-weight:
        normal;
}


.snapshot-value.dark {

    color:
        #333333;
}


/*
|--------------------------------------------------------------------------
| BREAKDOWN
|--------------------------------------------------------------------------
*/

.breakdown-table {

    width:
        100%;
}


.breakdown-table td {

    padding:
        6px 0;

    border-bottom:
        1px solid
        #F0EBE5;
}


.breakdown-name {

    width:
        20%;

    color:
        #5D4037;

    font-weight:
        bold;
}


.breakdown-track-cell {

    width:
        62%;

    padding-left:
        9px !important;

    padding-right:
        9px !important;
}


.bar-track {

    width:
        100%;

    height:
        8px;

    background:
        #EDE8E2;
}


.bar-fill {

    height:
        8px;
}


.bar-fill.attempted {

    background:
        #806A57;
}


.bar-fill.correct {

    background:
        #556B2F;
}


.bar-fill.wrong {

    background:
        #93483E;
}


.bar-fill.unanswered {

    background:
        #A9A093;
}


.breakdown-value {

    width:
        18%;

    color:
        #3E2723;

    text-align:
        right;

    font-weight:
        bold;
}


.breakdown-value span {

    color:
        #8B8178;

    font-weight:
        normal;
}


.total-row {

    margin-top:
        10px;

    padding:
        8px 10px;

    background:
        #F5F5DC;

    color:
        #5D4037;

    font-size:
        7px;

    font-weight:
        bold;
}


.total-row strong {

    float:
        right;

    font-size:
        10px;
}


/*
|--------------------------------------------------------------------------
| PAGE TWO
|--------------------------------------------------------------------------
*/

.page-two-title {

    margin-bottom:
        13px;

    padding-bottom:
        10px;

    border-bottom:
        2px solid
        #5D4037;
}


.small-kicker {

    color:
        #556B2F;

    font-size:
        6px;

    font-weight:
        bold;

    letter-spacing:
        1px;
}


.page-two-heading {

    margin-top:
        3px;

    color:
        #5D4037;

    font-size:
        17px;

    font-weight:
        bold;
}


.page-two-code {

    float:
        right;

    margin-top:
        -20px;

    color:
        #8A8179;

    font-size:
        7px;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| DETAILS
|--------------------------------------------------------------------------
*/

.details-columns {

    width:
        100%;
}


.details-columns > tbody > tr > td {

    width:
        50%;

    padding:
        0 5px;
    
    vertical-align:
        top;
}


.details-columns > tbody > tr > td:first-child {

    padding-left:
        0;
}


.details-columns > tbody > tr > td:last-child {

    padding-right:
        0;
}


.compact-card {

    min-height:
        220px;
}


.detail-table {

    width:
        100%;
}


.detail-table td {

    padding:
        7px 0;

    border-bottom:
        1px solid
        #EEE8E1;
}


.detail-table td:first-child {

    color:
        #7D746D;
}


.detail-table td:last-child {

    color:
        #3E2723;

    text-align:
        right;

    font-weight:
        bold;
}


.detail-table .green-value {

    color:
        #556B2F;
}


/*
|--------------------------------------------------------------------------
| REVIEW
|--------------------------------------------------------------------------
*/

.review-table {

    width:
        100%;
}


.review-score {

    width:
        25%;

    padding:
        15px;

    background:
        #F5F5DC;

    text-align:
        center;

    vertical-align:
        middle;
}


.review-main {

    width:
        75%;

    padding:
        15px;

    vertical-align:
        middle;
}


.review-score-label {

    color:
        #7B7169;

    font-size:
        6px;

    font-weight:
        bold;

    letter-spacing:
        .8px;
}


.review-score-value {

    margin-top:
        6px;

    color:
        #5D4037;

    font-size:
        22px;

    font-weight:
        bold;
}


.review-status {

    display:
        inline-block;

    padding:
        5px 8px;

    font-size:
        7px;

    font-weight:
        bold;
}


.review-title {

    margin-top:
        8px;

    color:
        #3E2723;

    font-size:
        10px;

    font-weight:
        bold;
}


.review-text {

    margin-top:
        5px;

    color:
        #77706A;

    font-size:
        7px;

    line-height:
        1.6;
}


/*
|--------------------------------------------------------------------------
| SCORE POSITION
|--------------------------------------------------------------------------
*/

.score-position {

    width:
        100%;
}


.score-position td {

    vertical-align:
        middle;
}


.score-position .position-label {

    color:
        #80766E;

    font-size:
        6px;

    font-weight:
        bold;
}


.position-number {

    margin-top:
        3px;

    color:
        #5D4037;

    font-size:
        15px;

    font-weight:
        bold;
}


.position-line-cell {

    width:
        65%;

    padding:
        0 15px;
}


.position-line {

    width:
        100%;

    height:
        9px;

    background:
        #EDE8E2;
}


.position-progress {

    height:
        9px;

    background:
        #556B2F;
}


.position-scale {

    display:
        table;

    width:
        100%;

    margin-top:
        4px;

    color:
        #8C837C;

    font-size:
        6px;
}


.position-scale span {

    display:
        table-cell;

    width:
        33.33%;

    text-align:
        left;
}


.position-scale span:nth-child(2) {

    text-align:
        center;
}


.position-scale span:last-child {

    text-align:
        right;
}


.grade-box {

    width:
        18%;

    padding:
        10px;

    background:
        #3E2723;

    text-align:
        center;
}


.grade-label {

    color:
        #D9CEC7;

    font-size:
        6px;

    font-weight:
        bold;
}


.grade-value {

    margin-top:
        3px;

    color:
        #FFFFFF;

    font-size:
        19px;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| DESCRIPTION
|--------------------------------------------------------------------------
*/

.exam-description {

    padding:
        11px;

    background:
        #FAF8F4;

    color:
        #5F5954;

    font-size:
        8px;

    line-height:
        1.7;
}


/*
|--------------------------------------------------------------------------
| FINAL BANNER
|--------------------------------------------------------------------------
*/

.final-banner {

    margin-top:
        13px;

    padding:
        13px;

    page-break-inside:
        avoid;
}


.final-banner.pass {

    background:
        #EEF4E5;

    border:
        1px solid
        #D8E6C5;
}


.final-banner.fail {

    background:
        #F9ECE9;

    border:
        1px solid
        #EBD4CF;
}


.final-banner table {

    width:
        100%;
}


.final-icon {

    width:
        34px;

    height:
        34px;

    color:
        #FFFFFF;

    background:
        #556B2F;

    text-align:
        center;

    font-size:
        17px;

    font-weight:
        bold;
}


.fail .final-icon {

    background:
        #93483E;
}


.final-title {

    padding-left:
        10px;

    color:
        #3E2723;

    font-size:
        9px;

    font-weight:
        bold;
}


.final-text {

    padding:
        4px 10px 0;

    color:
        #756D66;

    font-size:
        7px;

    line-height:
        1.5;
}


.final-grade {

    width:
        70px;

    color:
        #5D4037;

    text-align:
        right;

    font-size:
        20px;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

.pdf-footer {

    margin-top:
        16px;

    padding-top:
        10px;

    border-top:
        1px solid
        #DDD5CD;

    color:
        #817870;

    font-size:
        6px;
}


.pdf-footer table {

    width:
        100%;
}


.pdf-footer strong {

    color:
        #5D4037;
}


.footer-right {

    text-align:
        right;
}


.footer-note {

    margin-top:
        7px;

    color:
        #99918A;

    text-align:
        center;

    line-height:
        1.5;
}


/*
|--------------------------------------------------------------------------
| QUESTION DETAIL
|--------------------------------------------------------------------------
*/

.question-list {

    width:
        100%;
}


.question-card {

    margin-bottom:
        9px;

    padding:
        9px;

    border:
        1px solid
        #E8E1D8;

    page-break-inside:
        avoid;
}


.question-card.correct {

    border-left:
        3px solid
        #556B2F;
}


.question-card.wrong {

    border-left:
        3px solid
        #93483E;
}


.question-card.unanswered {

    border-left:
        3px solid
        #A9A093;
}


.question-card-title {

    color:
        #3E2723;

    font-size:
        8px;

    font-weight:
        bold;

    line-height:
        1.6;
}


.answer-detail {

    margin-top:
        5px;

    color:
        #66615C;

    font-size:
        7px;
}


.answer-detail strong {

    color:
        #5D4037;
}


.explanation-box {

    margin-top:
        6px;

    padding:
        7px;

    background:
        #F8F6F1;

    color:
        #66615C;

    font-size:
        7px;

    line-height:
        1.6;
}

</style>


<div class="pdf-page">


<!-- =====================================================
     HEADER
====================================================== -->

<table
    class="top-header"
    cellpadding="0"
    cellspacing="0"
>

<tr>

<td class="brand-side">


<?php if (
    $logoSrc !== ''
): ?>

<img
    src="<?= pdf_escape(
        $logoSrc
    ); ?>"
    class="logo"
    alt="ExamSphere"
>

<?php endif; ?>


<div class="brand-block">

    <div class="brand-name">
        ExamSphere
    </div>


    <div class="brand-line">

        SMART ASSESSMENT
        ·
        SEAMLESS LEARNING
        ·
        REAL RESULTS

    </div>

</div>

</td>


<td class="report-side">

    <div class="report-kicker">
        OFFICIAL RESULT REPORT
    </div>


    <div class="report-id">

        Result #

        <?= $resultId; ?>

    </div>


    <div class="report-attempt">

        Attempt #

        <?= $attemptId; ?>

    </div>


    <div class="report-date">

        <?= pdf_escape(
            $formattedDate
        ); ?>

    </div>

</td>

</tr>

</table>


<!-- =====================================================
     HERO
====================================================== -->

<table
    class="hero-table"
    cellpadding="0"
    cellspacing="0"
>

<tr>

<td class="hero-main">

    <div class="hero-overline">

        EXAMSPHERE PERFORMANCE REPORT

    </div>


    <div class="hero-title">

        <?= pdf_escape(
            $examTitle
        ); ?>

    </div>


    <div class="hero-meta">

        <?= pdf_escape(
            $subjectName
        ); ?>


        <?php if (
            !empty(
                $result['subject_code']
            )
        ): ?>

            ·

            <?= pdf_escape(
                $result['subject_code']
            ); ?>

        <?php endif; ?>


        ·

        <?= pdf_escape(
            $examType
        ); ?>

    </div>


    <div class="hero-description">

        Completed examination performance report
        generated from your authenticated ExamSphere account.

    </div>

</td>


<td class="hero-score">

    <div class="score-caption">
        FINAL SCORE
    </div>


    <div class="score-value">

        <?= pdf_number(
            $percentage
        ); ?>%

    </div>


    <div
        class="score-status"
        style="
            color:<?= $isPassed
                ? '#E2EFCF'
                : '#F4D9D4'
            ?>;
        "
    >

        <?= pdf_escape(
            $statusText
        ); ?>

        · Grade

        <?= pdf_escape(
            $grade
        ); ?>

    </div>

</td>

</tr>

</table>


<!-- =====================================================
     STUDENT PROFILE
====================================================== -->

<div class="section-card">

<div class="section-heading">

    <span class="section-number">
        01
    </span>

    STUDENT PROFILE

</div>


<table
    class="profile-grid"
    cellpadding="0"
    cellspacing="0"
>

<tr>

<td>

    <div class="label">
        STUDENT NAME
    </div>

    <div class="value">

        <?= pdf_escape(
            $studentName
        ); ?>

    </div>

</td>


<td>

    <div class="label">
        STUDENT CODE
    </div>

    <div class="value">

        <?= pdf_escape(
            $studentCode
        ); ?>

    </div>

</td>


<td>

    <div class="label">
        EXAM TYPE
    </div>

    <div class="value">

        <?= pdf_escape(
            $examType
        ); ?>

    </div>

</td>

</tr>


<tr>

<td>

    <div class="label">
        EMAIL
    </div>

    <div class="value">

        <?= pdf_escape(
            $email
        ); ?>

    </div>

</td>


<td>

    <div class="label">
        SUBJECT
    </div>

    <div class="value">

        <?= pdf_escape(
            $subjectName
        ); ?>

    </div>

</td>


<td>

    <div class="label">
        RESULT DATE
    </div>

    <div class="value">

        <?= pdf_escape(
            $formattedDate
        ); ?>

    </div>

</td>

</tr>

</table>

</div>


<!-- =====================================================
     PERFORMANCE SNAPSHOT
====================================================== -->

<div class="section-card">

<div class="section-heading">

    <span class="section-number">
        02
    </span>

    PERFORMANCE SNAPSHOT

</div>


<table
    class="snapshot-table"
    cellpadding="0"
    cellspacing="0"
>

<tr>

<td>

<div class="snapshot-card olive">

    <div class="snapshot-label">
        OBTAINED MARKS
    </div>


    <div class="snapshot-value">

        <?= pdf_number(
            $obtainedMarks
        ); ?>


        <span>

            /

            <?= pdf_number(
                $totalMarks
            ); ?>

        </span>

    </div>

</div>

</td>


<td>

<div class="snapshot-card green">

    <div class="snapshot-label">
        CORRECT
    </div>


    <div class="snapshot-value">

        <?= $correctAnswers; ?>

    </div>

</div>

</td>


<td>

<div class="snapshot-card brown">

    <div class="snapshot-label">
        WRONG
    </div>


    <div class="snapshot-value">

        <?= $wrongAnswers; ?>

    </div>

</div>

</td>


<td>

<div class="snapshot-card beige">

    <div class="snapshot-label">
        ACCURACY
    </div>


    <div class="snapshot-value dark">

        <?= pdf_number(
            $accuracy
        ); ?>%

    </div>

</div>

</td>

</tr>

</table>

</div>


<!-- =====================================================
     QUESTION BREAKDOWN
====================================================== -->

<div class="section-card">

<div class="section-heading">

    <span class="section-number">
        03
    </span>

    QUESTION BREAKDOWN

</div>


<table
    class="breakdown-table"
    cellpadding="0"
    cellspacing="0"
>

<tr>

<td class="breakdown-name">
    Attempted
</td>


<td class="breakdown-track-cell">

    <div class="bar-track">

        <div
            class="bar-fill attempted"
            style="
                width:<?= $attemptedPercent; ?>%;
            "
        ></div>

    </div>

</td>


<td class="breakdown-value">

    <?= $attemptedQuestions; ?>

    <span>
        (<?= $attemptedPercent; ?>%)
    </span>

</td>

</tr>


<tr>

<td class="breakdown-name">
    Correct
</td>


<td class="breakdown-track-cell">

    <div class="bar-track">

        <div
            class="bar-fill correct"
            style="
                width:<?= $correctPercent; ?>%;
            "
        ></div>

    </div>

</td>


<td class="breakdown-value">

    <?= $correctAnswers; ?>

    <span>
        (<?= $correctPercent; ?>%)
    </span>

</td>

</tr>


<tr>

<td class="breakdown-name">
    Wrong
</td>


<td class="breakdown-track-cell">

    <div class="bar-track">

        <div
            class="bar-fill wrong"
            style="
                width:<?= $wrongPercent; ?>%;
            "
        ></div>

    </div>

</td>


<td class="breakdown-value">

    <?= $wrongAnswers; ?>

    <span>
        (<?= $wrongPercent; ?>%)
    </span>

</td>

</tr>


<tr>

<td class="breakdown-name">
    Unanswered
</td>


<td class="breakdown-track-cell">

    <div class="bar-track">

        <div
            class="bar-fill unanswered"
            style="
                width:<?= $unansweredPercent; ?>%;
            "
        ></div>

    </div>

</td>


<td class="breakdown-value">

    <?= $unansweredQuestions; ?>

    <span>
        (<?= $unansweredPercent; ?>%)
    </span>

</td>

</tr>

</table>


<div class="total-row">

    <span>
        TOTAL QUESTIONS
    </span>


    <strong>

        <?= $totalQuestions; ?>

    </strong>

</div>

</div>


<!-- =====================================================
     PAGE TWO
====================================================== -->

<pagebreak />


<div class="page-two-title">

    <div class="small-kicker">

        EXAMSPHERE RESULT DETAILS

    </div>


    <div class="page-two-heading">

        Detailed Performance Report

    </div>


    <div class="page-two-code">

        RESULT #

        <?= $resultId; ?>

    </div>

</div>


<!-- =====================================================
     MARKING + EXAM DETAILS
====================================================== -->

<table
    class="details-columns"
    cellpadding="0"
    cellspacing="0"
>

<tr>

<td>


<div class="section-card compact-card">

<div class="section-heading">

    <span class="section-number">
        04
    </span>

    MARKING DETAILS

</div>


<table
    class="detail-table"
    cellpadding="0"
    cellspacing="0"
>

<tr>

<td>
    Total Marks
</td>

<td>

    <?= pdf_number(
        $totalMarks
    ); ?>

</td>

</tr>


<tr>

<td>
    Obtained Marks
</td>

<td class="green-value">

    <?= pdf_number(
        $obtainedMarks
    ); ?>

</td>

</tr>


<tr>

<td>
    Passing Marks
</td>

<td>

    <?= pdf_number(
        $passingMarks
    ); ?>

</td>

</tr>


<tr>

<td>
    Correct Answers
</td>

<td>

    <?= $correctAnswers; ?>

</td>

</tr>


<tr>

<td>
    Wrong Answers
</td>

<td>

    <?= $wrongAnswers; ?>

</td>

</tr>


<tr>

<td>
    Unanswered
</td>

<td>

    <?= $unansweredQuestions; ?>

</td>

</tr>


<tr>

<td>
    Negative Marking
</td>

<td>

    <?= $negativeMarking === 1
        ? 'Enabled'
        : 'Disabled'
    ?>

</td>

</tr>


<tr>

<td>
    Final Result
</td>

<td
    style="
        color:<?= $statusColor; ?>;
        font-weight:bold;
    "
>

    <?= pdf_escape(
        $statusText
    ); ?>

</td>

</tr>

</table>

</div>


</td>


<td>


<div class="section-card compact-card">

<div class="section-heading">

    <span class="section-number">
        05
    </span>

    EXAM DETAILS

</div>


<table
    class="detail-table"
    cellpadding="0"
    cellspacing="0"
>

<tr>

<td>
    Examination
</td>

<td>

    <?= pdf_escape(
        $examTitle
    ); ?>

</td>

</tr>


<tr>

<td>
    Subject
</td>

<td>

    <?= pdf_escape(
        $subjectName
    ); ?>

</td>

</tr>


<tr>

<td>
    Exam Type
</td>

<td>

    <?= pdf_escape(
        $examType
    ); ?>

</td>

</tr>


<tr>

<td>
    Questions
</td>

<td>

    <?= $totalQuestions; ?>

</td>

</tr>


<tr>

<td>
    Exam Duration
</td>

<td>

    <?= $durationMinutes; ?>

    min

</td>

</tr>


<tr>

<td>
    Time Taken
</td>

<td>

    <?= pdf_escape(
        $timeTakenText
    ); ?>

</td>

</tr>


<tr>

<td>
    Attempt Number
</td>

<td>

    #

    <?= $attemptId; ?>

</td>

</tr>

</table>

</div>


</td>

</tr>

</table>


<!-- =====================================================
     PERFORMANCE REVIEW
====================================================== -->

<div class="section-card">

<div class="section-heading">

    <span class="section-number">
        06
    </span>

    PERFORMANCE REVIEW

</div>


<table
    class="review-table"
    cellpadding="0"
    cellspacing="0"
>

<tr>

<td class="review-score">

    <div class="review-score-label">
        PERCENTAGE
    </div>


    <div class="review-score-value">

        <?= pdf_number(
            $percentage
        ); ?>%

    </div>

</td>


<td class="review-main">

    <div
        class="review-status"
        style="
            color:<?= $statusColor; ?>;
            background:<?= $statusBackground; ?>;
        "
    >

        <?= pdf_escape(
            $statusText
        ); ?>

        · Grade

        <?= pdf_escape(
            $grade
        ); ?>

    </div>


    <div class="review-title">

        <?= pdf_escape(
            $remark
        ); ?>

    </div>


    <div class="review-text">

        <?= pdf_escape(
            $remarkText
        ); ?>

    </div>

</td>

</tr>

</table>

</div>


<!-- =====================================================
     SCORE POSITION
====================================================== -->

<div class="section-card">

<div class="section-heading">

    <span class="section-number">
        07
    </span>

    SCORE POSITION

</div>


<table
    class="score-position"
    cellpadding="0"
    cellspacing="0"
>

<tr>

<td>

    <div class="position-label">
        YOUR SCORE
    </div>


    <div class="position-number">

        <?= pdf_number(
            $percentage
        ); ?>%

    </div>

</td>


<td class="position-line-cell">

    <div class="position-line">

        <div
            class="position-progress"
            style="
                width:<?= $scorePosition; ?>%;
            "
        ></div>

    </div>


    <div class="position-scale">

        <span>
            0%
        </span>

        <span>
            50%
        </span>

        <span>
            100%
        </span>

    </div>

</td>


<td class="grade-box">

    <div class="grade-label">
        GRADE
    </div>


    <div class="grade-value">

        <?= pdf_escape(
            $grade
        ); ?>

    </div>

</td>

</tr>

</table>

</div>


<!-- =====================================================
     QUESTION-WISE SUMMARY
====================================================== -->

<?php if (
    !empty(
        $questionRows
    )
): ?>

<pagebreak />


<div class="page-two-title">

    <div class="small-kicker">

        EXAMSPHERE DETAILED REVIEW

    </div>


    <div class="page-two-heading">

        Question-wise Analysis

    </div>

</div>


<div class="section-card">

    <div class="section-heading">

        <span class="section-number">
            08
        </span>

        ANSWER ANALYSIS

    </div>


    <table
        class="question-list"
        cellpadding="0"
        cellspacing="0"
    >


    <?php foreach (
        $questionRows
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
                ]
                ??
                0
            ) === 1;


        $questionStatus =
            (string) (
                $question[
                    'question_status'
                ]
                ??
                ''
            );


        $marksAwarded =
            (float) (
                $question[
                    'marks_awarded'
                ]
                ??
                0
            );


        $questionClass =
            $isCorrect

                ? 'correct'

                : (
                    $isAnswered
                        ? 'wrong'
                        : 'unanswered'
                );


        $optionsMap = [

            'A' =>
                (string) (
                    $question[
                        'option_a'
                    ]
                    ??
                    ''
                ),

            'B' =>
                (string) (
                    $question[
                        'option_b'
                    ]
                    ??
                    ''
                ),

            'C' =>
                (string) (
                    $question[
                        'option_c'
                    ]
                    ??
                    ''
                ),

            'D' =>
                (string) (
                    $question[
                        'option_d'
                    ]
                    ??
                    ''
                )

        ];


        $selectedText =
            $isAnswered
                ? (
                    $optionsMap[
                        $selectedAnswer
                    ]
                    ??
                    ''
                )
                : 'Not answered';


        $correctText =
            isset(
                $optionsMap[
                    $correctAnswer
                ]
            )
                ? $optionsMap[
                    $correctAnswer
                ]
                : '';


        ?>


        <tr>

        <td>

        <div
            class="
                question-card
                <?= $questionClass; ?>
            "
        >

            <div class="question-card-title">

                Q<?= $index + 1; ?>.

                <?= pdf_escape(
                    $question[
                        'question_text'
                    ]
                    ??
                    ''
                ); ?>

            </div>


            <div class="answer-detail">

                <strong>
                    Your answer:
                </strong>


                <?= $isAnswered
                    ? pdf_escape(
                        $selectedAnswer .
                        ' — ' .
                        $selectedText
                    )
                    : 'Not answered'
                ?>

            </div>


            <div class="answer-detail">

                <strong>
                    Correct answer:
                </strong>


                <?= pdf_escape(
                    $correctAnswer
                    . (
                        $correctText !== ''
                            ? ' — ' . $correctText
                            : ''
                    )
                ); ?>

            </div>


            <div class="answer-detail">

                <strong>
                    Status:
                </strong>


                <?= pdf_escape(
                    $questionStatus !== ''
                        ? $questionStatus
                        : (
                            $isCorrect
                                ? 'Answered'
                                : (
                                    $isAnswered
                                        ? 'Answered'
                                        : 'Not Answered'
                                )
                        )
                ); ?>

            </div>


            <div class="answer-detail">

                <strong>
                    Marks awarded:
                </strong>


                <?= pdf_number(
                    $marksAwarded
                ); ?>

            </div>


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

                <div class="explanation-box">

                    <strong>

                        Explanation:

                    </strong>


                    <br>


                    <?= pdf_escape(
                        $question[
                            'explanation'
                        ]
                    ); ?>

                </div>

            <?php endif; ?>


        </div>

        </td>

        </tr>


    <?php endforeach; ?>


    </table>

</div>

<?php endif; ?>


<!-- =====================================================
     ABOUT EXAM
====================================================== -->

<?php if (
    trim(
        (string) (
            $result[
                'exam_description'
            ]
            ??
            ''
        )
    ) !== ''
): ?>

<div class="section-card">

<div class="section-heading">

    <span class="section-number">

        09

    </span>

    ABOUT THIS EXAM

</div>


<div class="exam-description">

    <?= nl2br(
        pdf_escape(
            $result[
                'exam_description'
            ]
        )
    ); ?>

</div>

</div>

<?php endif; ?>


<!-- =====================================================
     FINAL BANNER
====================================================== -->

<div
    class="
        final-banner
        <?= $isPassed
            ? 'pass'
            : 'fail'
        ?>
    "
>

<table
    cellpadding="0"
    cellspacing="0"
>

<tr>

<td class="final-icon">

    <?= $isPassed
        ? '✓'
        : '!'
    ?>

</td>


<td>

    <div class="final-title">

        <?= pdf_escape(
            $statusText
        ); ?>

        ·

        <?= pdf_escape(
            $remark
        ); ?>

    </div>


    <div class="final-text">

        <?= pdf_escape(
            $remarkText
        ); ?>

    </div>

</td>


<td class="final-grade">

    <?= pdf_escape(
        $grade
    ); ?>

</td>

</tr>

</table>

</div>


<!-- =====================================================
     FOOTER
====================================================== -->

<div class="pdf-footer">

<table
    cellpadding="0"
    cellspacing="0"
>

<tr>

<td>

    <strong>
        ExamSphere
    </strong>


    <br>


    Secure Examination Platform

</td>


<td class="footer-right">

    Result #

    <?= $resultId; ?>


    <br>


    Attempt #

    <?= $attemptId; ?>

</td>

</tr>

</table>


<div class="footer-note">

    This report is generated from the finalized examination
    result stored for the authenticated student account.

</div>

</div>


</div>