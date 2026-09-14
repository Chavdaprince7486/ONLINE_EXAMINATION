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

    exit(
        'Unauthorized access.'
    );
}


$studentId =
    (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$attemptId =
    filter_input(
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

    exit(
        'Invalid examination attempt.'
    );
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
    !is_file(
        $autoloadPath
    )
) {

    http_response_code(500);

    exit(
        'PDF library is not available.'
    );
}


require_once $autoloadPath;


if (
    !class_exists(
        '\Mpdf\Mpdf'
    )
) {

    http_response_code(500);

    exit(
        'PDF library is not available.'
    );
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
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function result_pdf_number(
    mixed $value
): string {

    $number =
        round(
            (float) $value,
            2
        );


    if (
        floor($number) ===
        $number
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


function result_pdf_date(
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

    } catch (
        Throwable
    ) {

        return '-';
    }
}


/*
|--------------------------------------------------------------------------
| LOAD FINAL RESULT
|--------------------------------------------------------------------------
|
| The results row is the authoritative source for the final score.
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

                r.created_at AS result_created_at,

                ea.started_at,
                ea.server_deadline,
                ea.submitted_at,

                ea.status AS attempt_status,

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

                e.starts_at,
                e.ends_at,

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
            "
        );


    $resultStatement->execute(
        [

            (int) $attemptId,

            $studentId

        ]
    );


    $result =
        $resultStatement->fetch(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $exception
) {

    error_log(
        'ExamSphere PDF result lookup failed: ' .
        $exception->getMessage()
    );


    http_response_code(500);

    exit(
        'Unable to prepare the result PDF.'
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
| FINALIZED ATTEMPT ONLY
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        (string) $result[
            'attempt_status'
        ],
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
| OWNERSHIP
|--------------------------------------------------------------------------
*/

if (
    (int) $result[
        'student_id'
    ]
    !==
    $studentId
) {

    http_response_code(403);

    exit(
        'Unauthorized result access.'
    );
}


/*
|--------------------------------------------------------------------------
| RESULT / ATTEMPT RELATIONSHIP
|--------------------------------------------------------------------------
*/

if (
    (int) $result[
        'attempt_id'
    ]
    !==
    (int) $attemptId
) {

    http_response_code(409);

    exit(
        'Invalid result relationship.'
    );
}


/*
|--------------------------------------------------------------------------
| RESULT VALUES
|--------------------------------------------------------------------------
*/

$totalQuestions =
    max(
        0,
        (int) $result[
            'total_questions'
        ]
    );


$attemptedQuestions =
    max(
        0,
        (int) $result[
            'attempted_questions'
        ]
    );


$correctAnswers =
    max(
        0,
        (int) $result[
            'correct_answers'
        ]
    );


$wrongAnswers =
    max(
        0,
        (int) $result[
            'wrong_answers'
        ]
    );


$unansweredQuestions =
    max(
        0,
        (int) $result[
            'unanswered_questions'
        ]
    );


$totalMarks =
    max(
        0,
        round(
            (float) $result[
                'total_marks'
            ],
            2
        )
    );


$obtainedMarks =
    round(
        (float) $result[
            'obtained_marks'
        ],
        2
    );


/*
|--------------------------------------------------------------------------
| FINAL SCORE BOUNDS
|--------------------------------------------------------------------------
*/

$obtainedMarks =
    max(
        0,
        min(
            $totalMarks,
            $obtainedMarks
        )
    );


$percentage =
    round(
        (float) $result[
            'percentage'
        ],
        2
    );


$percentage =
    max(
        0,
        min(
            100,
            $percentage
        )
    );


$passingMarks =
    max(
        0,
        min(
            $totalMarks,
            (float) $result[
                'passing_marks'
            ]
        )
    );


$grade =
    trim(
        (string) $result[
            'grade'
        ]
    );


$resultStatus =
    trim(
        (string) $result[
            'result_status'
        ]
    );


$examTitle =
    trim(
        (string) $result[
            'exam_title'
        ]
    );


$examType =
    trim(
        (string) $result[
            'exam_type'
        ]
    );


$subjectName =
    trim(
        (string) (
            $result[
                'subject_name'
            ] ?? ''
        )
    );


$studentName =
    trim(
        (string) $result[
            'full_name'
        ]
    );


$studentCode =
    trim(
        (string) $result[
            'student_code'
        ]
    );


$studentEmail =
    trim(
        (string) $result[
            'email'
        ]
    );


$negativeMarking =
    (int) (
        $result[
            'negative_marking'
        ] ?? 0
    );


$durationMinutes =
    max(
        0,
        (int) $result[
            'duration_minutes'
        ]
    );


$isPassed =
    $resultStatus === 'Pass';


/*
|--------------------------------------------------------------------------
| VERIFY RESULT QUESTION TOTAL
|--------------------------------------------------------------------------
|
| The final result should contain exactly the configured question count.
|--------------------------------------------------------------------------
*/

$configuredQuestionCount =
    (int) (
        $result[
            'required_question_count'
        ] ?? 0
    );


if (
    $configuredQuestionCount > 0 &&
    $totalQuestions !==
    $configuredQuestionCount
) {

    http_response_code(409);

    exit(
        'The final result question count is inconsistent.'
    );
}


/*
|--------------------------------------------------------------------------
| VERIFY QUESTION-LEVEL MARKS
|--------------------------------------------------------------------------
|
| Dynamic formula:
|
|     Total Questions × Per Question Marks
|
| Question data is used only for verification.
| Final result total remains the authoritative stored result value.
|--------------------------------------------------------------------------
*/

$questionRows = [];


try {

    $questionStatement =
        $conn->prepare(
            "
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

                AND q.status = 'Active'

            ORDER BY

                eq.position ASC,
                q.id ASC
            "
        );


    $questionStatement->execute(
        [

            (int) $attemptId,

            (int) $result[
                'exam_id'
            ]

        ]
    );


    $questionRows =
        $questionStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $exception
) {

    error_log(
        'ExamSphere PDF question lookup failed: ' .
        $exception->getMessage()
    );


    http_response_code(500);

    exit(
        'Unable to prepare the question analysis.'
    );
}


/*
|--------------------------------------------------------------------------
| UNIQUE ACTIVE QUESTION LIST
|--------------------------------------------------------------------------
*/

$questions = [];

$seenQuestionIds = [];


foreach (
    $questionRows as $question
) {

    $questionId =
        (int) $question[
            'question_id'
        ];


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


    $questions[] =
        $question;
}


/*
|--------------------------------------------------------------------------
| EXACT QUESTION COUNT
|--------------------------------------------------------------------------
*/

if (
    count($questions) !==
    $totalQuestions
) {

    http_response_code(409);

    exit(
        'The final result question set is inconsistent.'
    );
}


/*
|--------------------------------------------------------------------------
| CALCULATE QUESTION-LEVEL TOTAL
|--------------------------------------------------------------------------
*/

$marksPerQuestion =
    null;


$questionCalculatedTotal =
    0.00;


foreach (
    $questions as $question
) {

    $questionMarks =
        round(
            (float) (
                $question[
                    'marks'
                ] ?? 0
            ),
            2
        );


    if (
        $questionMarks <= 0
    ) {

        http_response_code(409);

        exit(
            'The result contains a question with invalid marks.'
        );
    }


    if (
        $marksPerQuestion === null
    ) {

        $marksPerQuestion =
            $questionMarks;

    } else {

        if (
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


    $questionCalculatedTotal +=
        $questionMarks;
}


$questionCalculatedTotal =
    round(
        $questionCalculatedTotal,
        2
    );


/*
|--------------------------------------------------------------------------
| DYNAMIC TOTAL MARKS VERIFICATION
|--------------------------------------------------------------------------
*/

if (
    abs(
        $questionCalculatedTotal -
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
| FALLBACK MARKS PER QUESTION
|--------------------------------------------------------------------------
*/

if (
    $marksPerQuestion === null
) {

    $marksPerQuestion =
        0.00;
}


/*
|--------------------------------------------------------------------------
| DERIVED VALUES
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
        : 0.00;


$completionPercentage =
    $totalQuestions > 0
        ? round(
            (
                $attemptedQuestions /
                $totalQuestions
            ) * 100,
            2
        )
        : 0.00;


$correctPercentage =
    $totalQuestions > 0
        ? round(
            (
                $correctAnswers /
                $totalQuestions
            ) * 100,
            2
        )
        : 0.00;


$wrongPercentage =
    $totalQuestions > 0
        ? round(
            (
                $wrongAnswers /
                $totalQuestions
            ) * 100,
            2
        )
        : 0.00;


$unansweredPercentage =
    $totalQuestions > 0
        ? round(
            (
                $unansweredQuestions /
                $totalQuestions
            ) * 100,
            2
        )
        : 0.00;


$scorePosition =
    max(
        0,
        min(
            100,
            $percentage
        )
    );


$remark =
    $isPassed
        ? 'Congratulations! You passed the examination.'
        : 'Keep practising and continue improving your preparation.';


$remarkText =
    $isPassed
        ? 'Your final score met or exceeded the configured passing marks.'
        : 'Review the detailed analysis and use the weak areas to guide your next practice sessions.';


$formattedDate =
    result_pdf_date(
        $result[
            'submitted_at'
        ]
        ??
        $result[
            'result_created_at'
        ]
    );


/*
|--------------------------------------------------------------------------
| TIME TAKEN
|--------------------------------------------------------------------------
*/

$timeTakenSeconds =
    0;


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


        $timeTakenSeconds =
            max(
                0,
                $submittedAt->getTimestamp()
                -
                $startedAt->getTimestamp()
            );

    } catch (
        Throwable
    ) {

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
| RESULT DATA FOR TEMPLATE
|--------------------------------------------------------------------------
*/

$pdfData = [

    'result_id' =>
        (int) $result[
            'result_id'
        ],

    'attempt_id' =>
        (int) $result[
            'attempt_id'
        ],

    'student_id' =>
        (int) $result[
            'student_id'
        ],

    'exam_id' =>
        (int) $result[
            'exam_id'
        ],

    'student_name' =>
        $studentName,

    'student_code' =>
        $studentCode,

    'student_email' =>
        $studentEmail,

    'exam_title' =>
        $examTitle,

    'exam_type' =>
        $examType,

    'subject_name' =>
        $subjectName,

    'total_questions' =>
        $totalQuestions,

    'attempted_questions' =>
        $attemptedQuestions,

    'correct_answers' =>
        $correctAnswers,

    'wrong_answers' =>
        $wrongAnswers,

    'unanswered_questions' =>
        $unansweredQuestions,

    'marks_per_question' =>
        $marksPerQuestion,

    'total_marks' =>
        $totalMarks,

    'obtained_marks' =>
        $obtainedMarks,

    'percentage' =>
        $percentage,

    'passing_marks' =>
        $passingMarks,

    'grade' =>
        $grade,

    'result_status' =>
        $resultStatus,

    'accuracy' =>
        $accuracy,

    'completion_percentage' =>
        $completionPercentage,

    'score_position' =>
        $scorePosition,

    'duration_minutes' =>
        $durationMinutes,

    'time_taken_text' =>
        $timeTakenText,

    'negative_marking' =>
        $negativeMarking,

    'remark' =>
        $remark,

    'remark_text' =>
        $remarkText,

    'formatted_date' =>
        $formattedDate,

    'is_passed' =>
        $isPassed,

    'correct_percentage' =>
        $correctPercentage,

    'wrong_percentage' =>
        $wrongPercentage,

    'unanswered_percentage' =>
        $unansweredPercentage

];


/*
|--------------------------------------------------------------------------
| TEMPLATE PATH
|--------------------------------------------------------------------------
*/

$templatePath =
    dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'templates'
    . DIRECTORY_SEPARATOR
    . 'result_pdf.php';


if (
    !is_file(
        $templatePath
    )
) {

    http_response_code(500);

    exit(
        'Result PDF template was not found.'
    );
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
    !is_dir(
        $tempDirectory
    )
) {

    if (
        !mkdir(
            $tempDirectory,
            0750,
            true
        )
        &&
        !is_dir(
            $tempDirectory
        )
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

    /*
     * Keep all legacy variables available to the existing PDF template.
     */

    require $templatePath;


    $pdfHtml =
        ob_get_clean();

} catch (
    Throwable $exception
) {

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
    trim(
        $pdfHtml
    ) === ''
) {

    http_response_code(500);

    exit(
        'Result PDF template returned empty content.'
    );
}


/*
|--------------------------------------------------------------------------
| GENERATE PDF
|--------------------------------------------------------------------------
*/

try {

    $mpdf =
        new \Mpdf\Mpdf(
            [

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

            ]
        );


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


    $mpdf->SetHTMLHeader(
        '
        <div
            style="
                text-align:right;
                font-size:7px;
                color:#777777;
            "
        >
            ExamSphere
        </div>
        '
    );


    $mpdf->SetHTMLFooter(
        '
        <div
            style="
                text-align:center;
                font-size:7px;
                color:#777777;
            "
        >
            ExamSphere Online Examination System
            &nbsp;|&nbsp;
            Page {PAGENO} of {nbpg}
        </div>
        '
    );


    $mpdf->WriteHTML(
        '
        <style>
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
        </style>
        ',
        \Mpdf\HTMLParserMode::HEADER_CSS
    );


    $mpdf->WriteHTML(
        $pdfHtml,
        \Mpdf\HTMLParserMode::HTML_BODY
    );


    /*
    |--------------------------------------------------------------------------
    | SAFE DOWNLOAD FILE NAME
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
        (int) $attemptId .
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

} catch (
    Throwable $exception
) {

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