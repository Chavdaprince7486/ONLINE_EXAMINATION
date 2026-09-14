<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| EXAMSPHERE RESULT PDF TEMPLATE
|--------------------------------------------------------------------------
| Compatible with:
| - student/ajax/download_result_pdf.php
| - student/ajax/send_result_email.php
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists('pdf_escape')) {

    function pdf_escape(mixed $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}


if (!function_exists('pdf_number')) {

    function pdf_number(mixed $value): string
    {
        $number = round(
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
}


if (!function_exists('pdf_date')) {

    function pdf_date(mixed $value): string
    {
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
| SOURCE DATA
|--------------------------------------------------------------------------
*/

$result =
    is_array(
        $result ?? null
    )
        ? $result
        : [];


$questions =
    is_array(
        $questions ?? null
    )
        ? $questions
        : [];


/*
|--------------------------------------------------------------------------
| RESULT VALUES
|--------------------------------------------------------------------------
*/

$totalQuestions = max(
    0,
    (int) (
        $result['total_questions']
        ?? 0
    )
);


$attemptedQuestions = max(
    0,
    (int) (
        $result['attempted_questions']
        ?? 0
    )
);


$correctAnswers = max(
    0,
    (int) (
        $result['correct_answers']
        ?? 0
    )
);


$wrongAnswers = max(
    0,
    (int) (
        $result['wrong_answers']
        ?? 0
    )
);


$unansweredQuestions = max(
    0,
    (int) (
        $result['unanswered_questions']
        ?? 0
    )
);


$totalMarks = max(
    0,
    round(
        (float) (
            $result['total_marks']
            ?? 0
        ),
        2
    )
);


$obtainedMarks = round(
    (float) (
        $result['obtained_marks']
        ?? 0
    ),
    2
);


$obtainedMarks = max(
    0,
    min(
        $totalMarks,
        $obtainedMarks
    )
);


$percentage = round(
    (float) (
        $result['percentage']
        ?? 0
    ),
    2
);


$percentage = max(
    0,
    min(
        100,
        $percentage
    )
);


$passingMarks = max(
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


if ($grade === '') {

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


/*
|--------------------------------------------------------------------------
| EXAM / STUDENT
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
| DATE / TIME
|--------------------------------------------------------------------------
*/

$formattedDate =
    pdf_date(
        $submittedAt
    );


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
    $timeTakenSeconds > 0
        ? (int) ceil(
            $timeTakenSeconds / 60
        )
        : 0;


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
| PERCENTAGES
|--------------------------------------------------------------------------
*/

$attemptedPercent =
    $totalQuestions > 0
        ? round(
            (
                $attemptedQuestions /
                $totalQuestions
            ) * 100,
            1
        )
        : 0;


$correctPercent =
    $totalQuestions > 0
        ? round(
            (
                $correctAnswers /
                $totalQuestions
            ) * 100,
            1
        )
        : 0;


$wrongPercent =
    $totalQuestions > 0
        ? round(
            (
                $wrongAnswers /
                $totalQuestions
            ) * 100,
            1
        )
        : 0;


$unansweredPercent =
    $totalQuestions > 0
        ? round(
            (
                $unansweredQuestions /
                $totalQuestions
            ) * 100,
            1
        )
        : 0;


$accuracy =
    $attemptedQuestions > 0
        ? round(
            (
                $correctAnswers /
                $attemptedQuestions
            ) * 100,
            1
        )
        : 0;


$completion =
    $totalQuestions > 0
        ? round(
            (
                $attemptedQuestions /
                $totalQuestions
            ) * 100,
            1
        )
        : 0;


/*
|--------------------------------------------------------------------------
| PER QUESTION MARKS
|--------------------------------------------------------------------------
*/

$marksPerQuestion =
    null;


$questionRows =
    [];


$seenQuestionIds =
    [];


foreach (
    $questions as $question
) {

    $questionId =
        (int) (
            $question['question_id']
            ??
            $question['id']
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
            $seenQuestionIds[
                $questionId
            ]
        )
    ) {
        continue;
    }


    $seenQuestionIds[
        $questionId
    ] = true;


    $questionMarks =
        round(
            (float) (
                $question['marks']
                ?? 0
            ),
            2
        );


    if (
        $questionMarks > 0
    ) {

        if (
            $marksPerQuestion === null
        ) {

            $marksPerQuestion =
                $questionMarks;
        }
    }


    $questionRows[] =
        $question;
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


/*
|--------------------------------------------------------------------------
| DYNAMIC TOTAL FORMULA
|--------------------------------------------------------------------------
*/

$calculatedTotalMarks =
    round(
        $totalQuestions *
        $marksPerQuestion,
        2
    );


/*
|--------------------------------------------------------------------------
| RESULT MESSAGE
|--------------------------------------------------------------------------
*/

$statusText =
    $isPassed
        ? 'PASS'
        : 'FAIL';


$statusColor =
    $isPassed
        ? '#556B2F'
        : '#93483E';


$statusBg =
    $isPassed
        ? '#EEF4E5'
        : '#F8E9E6';


$resultMessage =
    $isPassed
        ? 'Congratulations! You passed the examination.'
        : 'Keep practising and continue improving your preparation.';


$resultSubMessage =
    $isPassed
        ? 'Your obtained marks meet or exceed the configured passing marks.'
        : 'Use this analysis to identify weak areas and improve your next attempt.';


/*
|--------------------------------------------------------------------------
| LOGO
|--------------------------------------------------------------------------
*/

$logoSrc =
    '';


$logoCandidates = [

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


foreach (
    $logoCandidates as $candidate
) {

    if (
        is_file($candidate)
    ) {

        $real =
            realpath(
                $candidate
            );


        if (
            $real !== false
        ) {

            $logoSrc =
                str_replace(
                    '\\',
                    '/',
                    $real
                );

            break;
        }
    }
}

?>


<style>

/*
|--------------------------------------------------------------------------
| GLOBAL
|--------------------------------------------------------------------------
*/

body {

    font-family:
        dejavusans;

    color:
        #332D29;

    font-size:
        8px;

    line-height:
        1.45;
}


table {

    width:
        100%;

    border-collapse:
        collapse;
}


td {

    vertical-align:
        top;
}


.page {

    width:
        100%;
}


.muted {

    color:
        #756D67;
}


/*
|--------------------------------------------------------------------------
| COVER / HEADER
|--------------------------------------------------------------------------
*/

.header-table {

    width:
        100%;

    margin-bottom:
        16px;
}


.logo-cell {

    width:
        52px;

    vertical-align:
        middle;
}


.logo {

    width:
        42px;

    height:
        42px;
}


.brand-cell {

    vertical-align:
        middle;
}


.brand {

    color:
        #5D4037;

    font-size:
        20px;

    font-weight:
        bold;
}


.brand-sub {

    margin-top:
        2px;

    color:
        #8A817A;

    font-size:
        7px;
}


.report-cell {

    width:
        180px;

    text-align:
        right;
}


.report-label {

    color:
        #8A817A;

    font-size:
        7px;

    text-transform:
        uppercase;
}


.report-id {

    margin-top:
        2px;

    color:
        #3E2723;

    font-size:
        12px;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| HERO
|--------------------------------------------------------------------------
*/

.hero {

    margin-bottom:
        14px;

    padding:
        14px 16px;

    border-radius:
        12px;

    background:
        #5D4037;

    color:
        #FFFFFF;
}


.hero-kicker {

    color:
        #E8DEC7;

    font-size:
        7px;

    font-weight:
        bold;

    text-transform:
        uppercase;

    letter-spacing:
        1.1px;
}


.hero-title {

    margin-top:
        4px;

    font-size:
        18px;

    font-weight:
        bold;
}


.hero-meta {

    margin-top:
        6px;

    color:
        #F3EEE5;

    font-size:
        8px;
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.status-wrap {

    margin-top:
        10px;
}


.status-badge {

    display:
        inline-block;

    padding:
        5px 12px;

    border-radius:
        12px;

    background:
        <?= $statusBg ?>;

    color:
        <?= $statusColor ?>;

    font-size:
        9px;

    font-weight:
        bold;

    text-transform:
        uppercase;
}


/*
|--------------------------------------------------------------------------
| SCORE CARD
|--------------------------------------------------------------------------
*/

.score-card {

    margin-bottom:
        12px;

    padding:
        14px;

    border:
        1px solid
        #E5DED3;

    border-radius:
        12px;

    background:
        #FBF9F4;
}


.score-label {

    color:
        #81776F;

    font-size:
        7px;

    text-transform:
        uppercase;

    letter-spacing:
        .7px;
}


.score-value {

    margin-top:
        3px;

    color:
        #3E2723;

    font-size:
        27px;

    font-weight:
        bold;
}


.score-total {

    color:
        #857B74;

    font-size:
        11px;

    font-weight:
        normal;
}


.score-percent {

    color:
        #556B2F;

    font-size:
        13px;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| SECTION
|--------------------------------------------------------------------------
*/

.section {

    margin-bottom:
        12px;

    page-break-inside:
        avoid;
}


.section-title {

    margin-bottom:
        8px;

    padding-bottom:
        6px;

    border-bottom:
        1px solid
        #DED7CE;

    color:
        #3E2723;

    font-size:
        10px;

    font-weight:
        bold;

    text-transform:
        uppercase;

    letter-spacing:
        .5px;
}


/*
|--------------------------------------------------------------------------
| INFORMATION
|--------------------------------------------------------------------------
*/

.info-table td {

    width:
        50%;

    padding:
        5px 0;
}


.info-label {

    color:
        #827870;

    font-size:
        7px;

    text-transform:
        uppercase;
}


.info-value {

    margin-top:
        2px;

    color:
        #3E2723;

    font-size:
        8px;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| STAT GRID
|--------------------------------------------------------------------------
*/

.stat-table td {

    width:
        25%;

    padding-right:
        6px;
}


.stat-table td:last-child {

    padding-right:
        0;
}


.stat {

    min-height:
        54px;

    padding:
        9px;

    border:
        1px solid
        #E6DED4;

    border-radius:
        10px;

    background:
        #FFFFFF;
}


.stat-label {

    color:
        #81776F;

    font-size:
        6.5px;

    text-transform:
        uppercase;
}


.stat-value {

    margin-top:
        4px;

    color:
        #3E2723;

    font-size:
        15px;

    font-weight:
        bold;
}


.stat-green .stat-value {

    color:
        #556B2F;
}


.stat-red .stat-value {

    color:
        #93483E;
}


.stat-olive .stat-value {

    color:
        #667D35;
}


/*
|--------------------------------------------------------------------------
| FORMULA
|--------------------------------------------------------------------------
*/

.formula {

    margin:
        8px 0 12px;

    padding:
        9px;

    border:
        1px solid
        #DCD4C8;

    border-radius:
        9px;

    background:
        #F6F2E9;

    text-align:
        center;

    color:
        #5D4037;

    font-size:
        9px;

    font-weight:
        bold;
}


.formula small {

    display:
        block;

    margin-top:
        3px;

    color:
        #81776F;

    font-size:
        6.5px;

    font-weight:
        normal;
}


/*
|--------------------------------------------------------------------------
| BREAKDOWN
|--------------------------------------------------------------------------
*/

.breakdown {

    width:
        100%;
}


.breakdown-row td {

    padding:
        5px 0;

    vertical-align:
        middle;
}


.breakdown-name {

    width:
        22%;

    color:
        #605851;

    font-size:
        7px;
}


.breakdown-bar-cell {

    width:
        58%;

    padding-left:
        5px;

    padding-right:
        8px;
}


.bar {

    height:
        7px;

    background:
        #EBE5DA;

    border-radius:
        5px;
}


.bar-inner {

    height:
        7px;

    border-radius:
        5px;
}


.bar-attempted {

    background:
        #A58F5C;
}


.bar-correct {

    background:
        #556B2F;
}


.bar-wrong {

    background:
        #93483E;
}


.bar-unanswered {

    background:
        #8D847B;
}


.breakdown-value {

    width:
        20%;

    text-align:
        right;

    color:
        #3E2723;

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

.details {

    border:
        1px solid
        #E6DFD6;

    border-radius:
        10px;

    overflow:
        hidden;
}


.details tr {

    border-bottom:
        1px solid
        #EEE8E0;
}


.details tr:last-child {

    border-bottom:
        0;
}


.details td {

    padding:
        7px 9px;
}


.details td:first-child {

    color:
        #81776F;

    width:
        65%;
}


.details td:last-child {

    color:
        #3E2723;

    text-align:
        right;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| FINAL REMARK
|--------------------------------------------------------------------------
*/

.remark {

    padding:
        11px 13px;

    border-left:
        3px solid
        <?= $statusColor ?>;

    background:
        #FAF8F3;
}


.remark-title {

    color:
        #3E2723;

    font-size:
        9px;

    font-weight:
        bold;
}


.remark-text {

    margin-top:
        4px;

    color:
        #736B64;

    font-size:
        7px;

    line-height:
        1.6;
}


/*
|--------------------------------------------------------------------------
| QUESTION ANALYSIS
|--------------------------------------------------------------------------
*/

.question-card {

    margin-bottom:
        9px;

    padding:
        9px;

    border:
        1px solid
        #E5DED5;

    border-radius:
        8px;

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
        #8D847B;
}


.question-head {

    width:
        100%;

    margin-bottom:
        5px;
}


.question-number {

    width:
        65%;

    color:
        #3E2723;

    font-size:
        8px;

    font-weight:
        bold;
}


.question-marks {

    width:
        35%;

    text-align:
        right;

    color:
        #556B2F;

    font-size:
        7px;

    font-weight:
        bold;
}


.question-text {

    color:
        #312B27;

    font-size:
        8px;

    font-weight:
        bold;

    line-height:
        1.55;
}


.answer-line {

    margin-top:
        4px;

    color:
        #716963;

    font-size:
        7px;

    line-height:
        1.45;
}


.answer-line strong {

    color:
        #5D4037;
}


.explanation {

    margin-top:
        6px;

    padding:
        6px 8px;

    background:
        #F7F4EE;

    color:
        #716963;

    font-size:
        6.8px;

    line-height:
        1.55;
}


/*
|--------------------------------------------------------------------------
| FOOTER NOTE
|--------------------------------------------------------------------------
*/

.footer-note {

    margin-top:
        14px;

    padding-top:
        8px;

    border-top:
        1px solid
        #E5DED5;

    color:
        #948B83;

    font-size:
        6.5px;

    text-align:
        center;
}

</style>


<div class="page">


    <!-- ======================================================
         HEADER
    ======================================================= -->

    <table class="header-table">

        <tr>

            <?php if (
                $logoSrc !== ''
            ): ?>

                <td class="logo-cell">

                    <img
                        src="<?= pdf_escape(
                            $logoSrc
                        ); ?>"
                        class="logo"
                        alt="ExamSphere"
                    >

                </td>

            <?php endif; ?>


            <td class="brand-cell">

                <div class="brand">
                    ExamSphere
                </div>

                <div class="brand-sub">
                    Online Examination System
                </div>

            </td>


            <td class="report-cell">

                <div class="report-label">
                    Result Report
                </div>

                <div class="report-id">

                    #<?= (int) $resultId; ?>

                </div>

            </td>

        </tr>

    </table>


    <!-- ======================================================
         HERO
    ======================================================= -->

    <div class="hero">

        <div class="hero-kicker">

            <?= pdf_escape(
                $examType
            ); ?>

            Examination

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

            &nbsp;&nbsp;•&nbsp;&nbsp;

            <?= pdf_escape(
                $formattedDate
            ); ?>

        </div>


        <div class="status-wrap">

            <span class="status-badge">

                <?= pdf_escape(
                    $statusText
                ); ?>

            </span>

        </div>

    </div>


    <!-- ======================================================
         FINAL SCORE
    ======================================================= -->

    <div class="section">

        <div class="section-title">
            Final Score
        </div>


        <div class="score-card">

            <div class="score-label">
                Obtained Marks
            </div>


            <div class="score-value">

                <?= pdf_number(
                    $obtainedMarks
                ); ?>

                <span class="score-total">

                    /

                    <?= pdf_number(
                        $totalMarks
                    ); ?>

                </span>

            </div>


            <div class="score-percent">

                <?= pdf_number(
                    $percentage
                ); ?>

                %


                &nbsp;&nbsp;•&nbsp;&nbsp;


                Grade:

                <?= pdf_escape(
                    $grade
                ); ?>

            </div>

        </div>


        <div class="formula">

            <?= $totalQuestions; ?>

            Questions

            ×

            <?= pdf_number(
                $marksPerQuestion
            ); ?>

            Mark/Question

            =

            <?= pdf_number(
                $totalMarks
            ); ?>

            Total Marks


            <small>

                Dynamic total marks calculated from the final question configuration.

            </small>

        </div>

    </div>


    <!-- ======================================================
         STUDENT + EXAM DETAILS
    ======================================================= -->

    <div class="section">

        <div class="section-title">

            Student & Examination Details

        </div>


        <table class="info-table">

            <tr>

                <td>

                    <div class="info-label">
                        Student Name
                    </div>

                    <div class="info-value">

                        <?= pdf_escape(
                            $studentName
                        ); ?>

                    </div>

                </td>


                <td>

                    <div class="info-label">
                        Student Code
                    </div>

                    <div class="info-value">

                        <?= pdf_escape(
                            $studentCode
                        ); ?>

                    </div>

                </td>

            </tr>


            <tr>

                <td>

                    <div class="info-label">
                        Email
                    </div>

                    <div class="info-value">

                        <?= pdf_escape(
                            $email
                        ); ?>

                    </div>

                </td>


                <td>

                    <div class="info-label">
                        Examination Type
                    </div>

                    <div class="info-value">

                        <?= pdf_escape(
                            $examType
                        ); ?>

                    </div>

                </td>

            </tr>


            <tr>

                <td>

                    <div class="info-label">
                        Duration
                    </div>

                    <div class="info-value">

                        <?= $durationMinutes; ?>

                        minutes

                    </div>

                </td>


                <td>

                    <div class="info-label">
                        Time Taken
                    </div>

                    <div class="info-value">

                        <?= pdf_escape(
                            $timeTakenText
                        ); ?>

                    </div>

                </td>

            </tr>

        </table>

    </div>


    <!-- ======================================================
         PERFORMANCE SNAPSHOT
    ======================================================= -->

    <div class="section">

        <div class="section-title">

            Performance Snapshot

        </div>


        <table class="stat-table">

            <tr>


                <td>

                    <div class="stat stat-olive">

                        <div class="stat-label">
                            Total Questions
                        </div>

                        <div class="stat-value">

                            <?= $totalQuestions; ?>

                        </div>

                    </div>

                </td>


                <td>

                    <div class="stat stat-green">

                        <div class="stat-label">
                            Attempted
                        </div>

                        <div class="stat-value">

                            <?= $attemptedQuestions; ?>

                        </div>

                    </div>

                </td>


                <td>

                    <div class="stat stat-green">

                        <div class="stat-label">
                            Correct
                        </div>

                        <div class="stat-value">

                            <?= $correctAnswers; ?>

                        </div>

                    </div>

                </td>


                <td>

                    <div class="stat stat-red">

                        <div class="stat-label">
                            Wrong
                        </div>

                        <div class="stat-value">

                            <?= $wrongAnswers; ?>

                        </div>

                    </div>

                </td>


            </tr>

        </table>


        <table
            class="stat-table"
            style="
                margin-top:7px;
            "
        >

            <tr>


                <td>

                    <div class="stat">

                        <div class="stat-label">
                            Unanswered
                        </div>

                        <div class="stat-value">

                            <?= $unansweredQuestions; ?>

                        </div>

                    </div>

                </td>


                <td>

                    <div class="stat stat-green">

                        <div class="stat-label">
                            Accuracy
                        </div>

                        <div class="stat-value">

                            <?= pdf_number(
                                $accuracy
                            ); ?>%

                        </div>

                    </div>

                </td>


                <td>

                    <div class="stat stat-olive">

                        <div class="stat-label">
                            Completion
                        </div>

                        <div class="stat-value">

                            <?= pdf_number(
                                $completion
                            ); ?>%

                        </div>

                    </div>

                </td>


                <td>

                    <div class="stat">

                        <div class="stat-label">
                            Passing Marks
                        </div>

                        <div class="stat-value">

                            <?= pdf_number(
                                $passingMarks
                            ); ?>

                        </div>

                    </div>

                </td>


            </tr>

        </table>

    </div>


    <!-- ======================================================
         QUESTION BREAKDOWN
    ======================================================= -->

    <div class="section">

        <div class="section-title">

            Question Breakdown

        </div>


        <table class="breakdown">


            <tr class="breakdown-row">

                <td class="breakdown-name">
                    Attempted
                </td>


                <td class="breakdown-bar-cell">

                    <div class="bar">

                        <div
                            class="
                                bar-inner
                                bar-attempted
                            "
                            style="
                                width:<?= max(
                                    0,
                                    min(
                                        100,
                                        $attemptedPercent
                                    )
                                ); ?>%;
                            "
                        ></div>

                    </div>

                </td>


                <td class="breakdown-value">

                    <?= $attemptedQuestions; ?>

                    (<?= pdf_number(
                        $attemptedPercent
                    ); ?>%)

                </td>

            </tr>


            <tr class="breakdown-row">

                <td class="breakdown-name">
                    Correct
                </td>


                <td class="breakdown-bar-cell">

                    <div class="bar">

                        <div
                            class="
                                bar-inner
                                bar-correct
                            "
                            style="
                                width:<?= max(
                                    0,
                                    min(
                                        100,
                                        $correctPercent
                                    )
                                ); ?>%;
                            "
                        ></div>

                    </div>

                </td>


                <td class="breakdown-value">

                    <?= $correctAnswers; ?>

                    (<?= pdf_number(
                        $correctPercent
                    ); ?>%)

                </td>

            </tr>


            <tr class="breakdown-row">

                <td class="breakdown-name">
                    Wrong
                </td>


                <td class="breakdown-bar-cell">

                    <div class="bar">

                        <div
                            class="
                                bar-inner
                                bar-wrong
                            "
                            style="
                                width:<?= max(
                                    0,
                                    min(
                                        100,
                                        $wrongPercent
                                    )
                                ); ?>%;
                            "
                        ></div>

                    </div>

                </td>


                <td class="breakdown-value">

                    <?= $wrongAnswers; ?>

                    (<?= pdf_number(
                        $wrongPercent
                    ); ?>%)

                </td>

            </tr>


            <tr class="breakdown-row">

                <td class="breakdown-name">
                    Unanswered
                </td>


                <td class="breakdown-bar-cell">

                    <div class="bar">

                        <div
                            class="
                                bar-inner
                                bar-unanswered
                            "
                            style="
                                width:<?= max(
                                    0,
                                    min(
                                        100,
                                        $unansweredPercent
                                    )
                                ); ?>%;
                            "
                        ></div>

                    </div>

                </td>


                <td class="breakdown-value">

                    <?= $unansweredQuestions; ?>

                    (<?= pdf_number(
                        $unansweredPercent
                    ); ?>%)

                </td>

            </tr>


        </table>

    </div>


    <!-- ======================================================
         MARKING DETAILS
    ======================================================= -->

    <div class="section">

        <div class="section-title">

            Marking Details

        </div>


        <table class="details">

            <tr>

                <td>
                    Total Questions
                </td>

                <td>

                    <?= $totalQuestions; ?>

                </td>

            </tr>


            <tr>

                <td>
                    Marks Per Question
                </td>

                <td>

                    <?= pdf_number(
                        $marksPerQuestion
                    ); ?>

                </td>

            </tr>


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

                <td>

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
                    Negative Marking
                </td>

                <td>

                    <?= $negativeMarking === 1
                        ? 'Enabled'
                        : 'None'
                    ?>

                </td>

            </tr>


            <tr>

                <td>
                    Grade
                </td>

                <td>

                    <?= pdf_escape(
                        $grade
                    ); ?>

                </td>

            </tr>


            <tr>

                <td>
                    Result Status
                </td>

                <td>

                    <?= pdf_escape(
                        $statusText
                    ); ?>

                </td>

            </tr>

        </table>

    </div>


    <!-- ======================================================
         FINAL REMARK
    ======================================================= -->

    <div class="section">

        <div class="section-title">

            Final Assessment

        </div>


        <div class="remark">

            <div class="remark-title">

                <?= pdf_escape(
                    $resultMessage
                ); ?>

            </div>


            <div class="remark-text">

                <?= pdf_escape(
                    $resultSubMessage
                ); ?>

            </div>

        </div>

    </div>


    <!-- ======================================================
         QUESTION-WISE ANALYSIS
    ======================================================= -->

    <?php if (
        !empty(
            $questionRows
        )
    ): ?>


        <pagebreak />


        <div class="section">

            <div class="section-title">

                Question-wise Analysis

            </div>


            <?php foreach (
                $questionRows
                as $index => $question
            ): ?>


                <?php

                $questionId =
                    (int) (
                        $question[
                            'question_id'
                        ]
                        ??
                        $question[
                            'id'
                        ]
                        ??
                        0
                    );


                $questionText =
                    trim(
                        (string) (
                            $question[
                                'question_text'
                            ]
                            ??
                            ''
                        )
                    );


                $questionMarks =
                    round(
                        (float) (
                            $question[
                                'marks'
                            ]
                            ??
                            0
                        ),
                        2
                    );


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


                $questionStatus =
                    trim(
                        (string) (
                            $question[
                                'question_status'
                            ]
                            ??
                            ''
                        )
                    );


                $marksAwarded =
                    round(
                        (float) (
                            $question[
                                'marks_awarded'
                            ]
                            ??
                            0
                        ),
                        2
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


                $questionClass =
                    $isCorrect
                        ? 'correct'
                        : (
                            $isAnswered
                                ? 'wrong'
                                : 'unanswered'
                        );


                $optionMap = [

                    'A' =>
                        trim(
                            (string) (
                                $question[
                                    'option_a'
                                ]
                                ??
                                ''
                            )
                        ),

                    'B' =>
                        trim(
                            (string) (
                                $question[
                                    'option_b'
                                ]
                                ??
                                ''
                            )
                        ),

                    'C' =>
                        trim(
                            (string) (
                                $question[
                                    'option_c'
                                ]
                                ??
                                ''
                            )
                        ),

                    'D' =>
                        trim(
                            (string) (
                                $question[
                                    'option_d'
                                ]
                                ??
                                ''
                            )
                        )

                ];


                $selectedText =
                    $isAnswered
                        ? (
                            $optionMap[
                                $selectedAnswer
                            ]
                            ??
                            ''
                        )
                        : 'Not answered';


                $correctText =
                    $correctAnswer !== ''
                        ? (
                            $optionMap[
                                $correctAnswer
                            ]
                            ??
                            ''
                        )
                        : '';


                $explanation =
                    trim(
                        (string) (
                            $question[
                                'explanation'
                            ]
                            ??
                            ''
                        )
                    );


                $status =
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
                        );

                ?>


                <div
                    class="
                        question-card
                        <?= $questionClass; ?>
                    "
                >

                    <table
                        class="question-head"
                    >

                        <tr>

                            <td
                                class="question-number"
                            >

                                Question
                                <?= $index + 1; ?>

                            </td>


                            <td
                                class="question-marks"
                            >

                                <?= pdf_number(
                                    $questionMarks
                                ); ?>

                                marks

                            </td>

                        </tr>

                    </table>


                    <div class="question-text">

                        <?= pdf_escape(
                            $questionText
                        ); ?>

                    </div>


                    <div class="answer-line">

                        <strong>
                            Your Answer:
                        </strong>

                        <?= $isAnswered
                            ? pdf_escape(
                                $selectedAnswer
                                .
                                ' — '
                                .
                                $selectedText
                            )
                            : 'Not answered'
                        ?>

                    </div>


                    <div class="answer-line">

                        <strong>
                            Correct Answer:
                        </strong>

                        <?= $correctAnswer !== ''
                            ? pdf_escape(
                                $correctAnswer
                                .
                                (
                                    $correctText !== ''
                                        ? ' — ' .
                                          $correctText
                                        : ''
                                )
                            )
                            : '-'
                        ?>

                    </div>


                    <div class="answer-line">

                        <strong>
                            Status:
                        </strong>

                        <?= pdf_escape(
                            $status
                        ); ?>

                    </div>


                    <div class="answer-line">

                        <strong>
                            Marks Awarded:
                        </strong>

                        <?= pdf_number(
                            $marksAwarded
                        ); ?>

                        /

                        <?= pdf_number(
                            $questionMarks
                        ); ?>

                    </div>


                    <?php if (
                        $explanation !== ''
                    ): ?>

                        <div class="explanation">

                            <strong>
                                Explanation:
                            </strong>

                            <?= pdf_escape(
                                $explanation
                            ); ?>

                        </div>

                    <?php endif; ?>

                </div>


            <?php endforeach; ?>


        </div>

    <?php endif; ?>


    <!-- ======================================================
         FOOTER NOTE
    ======================================================= -->

    <div class="footer-note">

        This result report was generated by
        ExamSphere Online Examination System.
        Final marks shown above are based on the
        finalized examination result and configured
        question-wise marks.

    </div>

</div>