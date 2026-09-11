<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| SESSION / DATABASE / FUNCTIONS
|--------------------------------------------------------------------------
*/

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {
    http_response_code(403);
    exit('Unauthorized access.');
}

$studentId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$attemptId = filter_input(
    INPUT_GET,
    'attempt_id',
    FILTER_VALIDATE_INT
);

if (
    $attemptId === false ||
    $attemptId === null ||
    $attemptId <= 0
) {
    http_response_code(400);
    exit('Invalid examination attempt.');
}


/*
|--------------------------------------------------------------------------
| PDF LIBRARY
|--------------------------------------------------------------------------
*/

$autoloadPath =
    dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR
    . 'vendor'
    . DIRECTORY_SEPARATOR
    . 'autoload.php';

if (
    !is_file($autoloadPath)
) {
    http_response_code(500);
    exit('PDF library is not available.');
}

require_once $autoloadPath;


if (
    !class_exists('\Mpdf\Mpdf')
) {
    http_response_code(500);
    exit('PDF library is not available.');
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function result_pdf_escape(
    mixed $value
): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function result_pdf_number(
    mixed $value
): string {
    $number = (float) $value;

    if (floor($number) === $number) {
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


function result_pdf_date(
    mixed $value
): string {
    if (empty($value)) {
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
| LOAD RESULT + ATTEMPT + STUDENT + EXAM
|--------------------------------------------------------------------------
*/

try {

    $resultStatement = $conn->prepare("
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
            ea.server_deadline,
            ea.submitted_at,
            ea.status AS attempt_status,

            e.title AS exam_title,
            e.description AS exam_description,

            e.exam_type,
            e.duration_minutes,
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
            AND ea.student_id = r.student_id
            AND ea.exam_id = r.exam_id

        INNER JOIN exams e
            ON e.id = r.exam_id

        INNER JOIN students s
            ON s.id = r.student_id

        LEFT JOIN subjects sub
            ON sub.id = e.subject_id

        WHERE

            r.attempt_id = ?
            AND r.student_id = ?

        LIMIT 1
    ");

    $resultStatement->execute([
        $attemptId,
        $studentId
    ]);

    $result = $resultStatement->fetch(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $exception) {

    error_log(
        'ExamSphere PDF result lookup failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);
    exit('Unable to prepare the result PDF.');
}


if (
    !$result
) {
    http_response_code(404);
    exit('Result not found.');
}


/*
|--------------------------------------------------------------------------
| FINALIZED ATTEMPT ONLY
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
    exit('This result is not finalized yet.');
}


/*
|--------------------------------------------------------------------------
| OWNERSHIP
|--------------------------------------------------------------------------
*/

if (
    (int) $result['student_id'] !==
    $studentId
) {
    http_response_code(403);
    exit('Unauthorized result access.');
}


/*
|--------------------------------------------------------------------------
| RESULT / ATTEMPT RELATIONSHIP
|--------------------------------------------------------------------------
*/

if (
    (int) $result['attempt_id'] !==
    (int) $attemptId
) {
    http_response_code(409);
    exit('Invalid result relationship.');
}


/*
|--------------------------------------------------------------------------
| NORMALIZE RESULT VALUES
|--------------------------------------------------------------------------
*/

$totalQuestions = max(
    0,
    (int) $result['total_questions']
);

$attemptedQuestions = max(
    0,
    (int) $result['attempted_questions']
);

$correctAnswers = max(
    0,
    (int) $result['correct_answers']
);

$wrongAnswers = max(
    0,
    (int) $result['wrong_answers']
);

$unansweredQuestions = max(
    0,
    (int) $result['unanswered_questions']
);

$totalMarks = max(
    0,
    (float) $result['total_marks']
);

$obtainedMarks = max(
    0,
    min(
        $totalMarks,
        (float) $result['obtained_marks']
    )
);

$percentage = max(
    0,
    min(
        100,
        (float) $result['percentage']
    )
);

$passingMarks = max(
    0,
    min(
        $totalMarks,
        (float) $result['passing_marks']
    )
);

$grade = trim(
    (string) $result['grade']
);

$resultStatus = trim(
    (string) $result['result_status']
);

$examTitle = trim(
    (string) $result['exam_title']
);

$examType = trim(
    (string) $result['exam_type']
);

$subjectName = trim(
    (string) (
        $result['subject_name'] ?? ''
    )
);

$studentName = trim(
    (string) $result['full_name']
);

$studentCode = trim(
    (string) $result['student_code']
);

$studentEmail = trim(
    (string) $result['email']
);

$negativeMarking = (int) (
    $result['negative_marking'] ?? 0
);

$durationMinutes = max(
    0,
    (int) $result['duration_minutes']
);

$isPassed =
    $resultStatus === 'Pass';


/*
|--------------------------------------------------------------------------
| DERIVED VALUES FOR TEMPLATE
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


$attemptedPercent =
    $totalQuestions > 0
        ? min(
            100,
            max(
                0,
                round(
                    (
                        $attemptedQuestions /
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
                        $correctAnswers /
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
                        $wrongAnswers /
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
                        $unansweredQuestions /
                        $totalQuestions
                    ) * 100
                )
            )
        )
        : 0;


$scorePosition =
    min(
        100,
        max(
            0,
            $percentage
        )
    );


$remark =
    $isPassed
        ? 'Congratulations! You passed the examination.'
        : 'Keep practising and continue improving your preparation.';


$remarkText =
    $isPassed
        ? 'Your final score has met or exceeded the configured passing marks.'
        : 'Review the detailed analysis and use the weak areas to guide your next practice sessions.';


$formattedDate =
    result_pdf_date(
        $result['submitted_at']
        ??
        $result['result_created_at']
    );


/*
|--------------------------------------------------------------------------
| TIME TAKEN
|--------------------------------------------------------------------------
*/

$timeTakenSeconds = 0;


if (
    !empty($result['started_at']) &&
    !empty($result['submitted_at'])
) {

    try {

        $startedAt =
            new DateTimeImmutable(
                (string) $result['started_at']
            );

        $submittedAt =
            new DateTimeImmutable(
                (string) $result['submitted_at']
            );

        $timeTakenSeconds =
            max(
                0,
                $submittedAt->getTimestamp()
                -
                $startedAt->getTimestamp()
            );

    } catch (Throwable) {

        $timeTakenSeconds = 0;
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
| QUESTION-WISE ANALYSIS
|--------------------------------------------------------------------------
*/

$questions = [];


try {

    $questionStatement = $conn->prepare("
        SELECT

            eq.position,

            q.id AS question_id,

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

        ORDER BY

            eq.position ASC,
            q.id ASC
    ");

    $questionStatement->execute([
        $attemptId,
        (int) $result['exam_id']
    ]);

    $questionRows =
        $questionStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | UNIQUE QUESTION MAP
    |--------------------------------------------------------------------------
    */

    $questionMap = [];


    foreach (
        $questionRows as $question
    ) {

        $questionId =
            (int) $question['question_id'];


        if (
            $questionId <= 0 ||
            isset(
                $questionMap[
                    $questionId
                ]
            )
        ) {
            continue;
        }


        $questionMap[
            $questionId
        ] = $question;
    }


    $questions =
        array_values(
            $questionMap
        );

} catch (Throwable $exception) {

    error_log(
        'ExamSphere PDF question lookup failed: ' .
        $exception->getMessage()
    );

    $questions = [];
}


/*
|--------------------------------------------------------------------------
| PDF TEMPLATE
|--------------------------------------------------------------------------
*/

$templatePath =
    dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'templates'
    . DIRECTORY_SEPARATOR
    . 'result_pdf.php';


if (
    !is_file($templatePath)
) {
    http_response_code(500);
    exit('Result PDF template was not found.');
}


/*
|--------------------------------------------------------------------------
| TEMP DIRECTORY
|--------------------------------------------------------------------------
*/

$tempDirectory =
    dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'temp';


if (
    !is_dir($tempDirectory)
) {

    if (
        !mkdir(
            $tempDirectory,
            0750,
            true
        )
        &&
        !is_dir($tempDirectory)
    ) {

        http_response_code(500);
        exit(
            'Unable to create PDF temporary directory.'
        );
    }
}


if (
    !is_writable(
        $tempDirectory
    )
) {

    http_response_code(500);
    exit(
        'PDF temporary directory is not writable.'
    );
}


/*
|--------------------------------------------------------------------------
| RENDER TEMPLATE
|--------------------------------------------------------------------------
*/

ob_start();


try {

    require $templatePath;

    $pdfHtml =
        ob_get_clean();

} catch (Throwable $exception) {

    ob_end_clean();

    error_log(
        'ExamSphere PDF template rendering failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to prepare the result PDF.'
    );
}


if (
    trim($pdfHtml) === ''
) {

    http_response_code(500);

    exit(
        'Result PDF template returned empty content.'
    );
}


/*
|--------------------------------------------------------------------------
| CREATE PDF
|--------------------------------------------------------------------------
*/

try {

    $mpdf =
        new \Mpdf\Mpdf([

            'mode' =>
                'utf-8',

            'format' =>
                'A4',

            'orientation' =>
                'P',

            'margin_left' =>
                15,

            'margin_right' =>
                15,

            'margin_top' =>
                20,

            'margin_bottom' =>
                20,

            'margin_header' =>
                8,

            'margin_footer' =>
                8,

            'tempDir' =>
                $tempDirectory,

            'default_font' =>
                'dejavusans',

            'default_font_size' =>
                8,

            'autoScriptToLang' =>
                true,

            'autoLangToFont' =>
                true

        ]);


    $mpdf->SetTitle(
        'ExamSphere Result - ' .
        $examTitle
    );


    $mpdf->SetAuthor(
        'ExamSphere'
    );


    $mpdf->SetCreator(
        'ExamSphere Online Examination System'
    );


    $mpdf->SetSubject(
        'Examination Result'
    );


    $mpdf->SetDisplayMode(
        'fullpage'
    );


    $mpdf->WriteHTML(
        <<<CSS
body {
    font-family: dejavusans;
    color: #333333;
    font-size: 8px;
    line-height: 1.45;
}

table {
    border-collapse: collapse;
}

.pdf-page {
    width: 100%;
}
CSS,
        \Mpdf\HTMLParserMode::HEADER_CSS
    );


    $mpdf->WriteHTML(
        $pdfHtml,
        \Mpdf\HTMLParserMode::HTML_BODY
    );


    /*
    |--------------------------------------------------------------------------
    | SAFE DOWNLOAD NAME
    |--------------------------------------------------------------------------
    */

    $safeExamName =
        preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '_',
            $examTitle
        );


    $safeExamName =
        trim(
            (string) $safeExamName,
            '_'
        );


    if (
        $safeExamName === ''
    ) {

        $safeExamName =
            'Exam';
    }


    $safeExamName =
        substr(
            $safeExamName,
            0,
            80
        );


    $fileName =
        'ExamSphere_Result_' .
        $safeExamName .
        '_' .
        $attemptId .
        '.pdf';


    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD
    |--------------------------------------------------------------------------
    */

    $mpdf->Output(
        $fileName,
        \Mpdf\Output\Destination::DOWNLOAD
    );


} catch (Throwable $exception) {

    error_log(
        'ExamSphere result PDF generation failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to generate the result PDF.'
    );
}


exit;