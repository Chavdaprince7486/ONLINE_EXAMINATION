<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| SESSION / DATABASE / MAIL
|--------------------------------------------------------------------------
*/

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/mail.php';


/*
|--------------------------------------------------------------------------
| JSON HEADERS
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: application/json; charset=UTF-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Pragma: no-cache'
);


/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

function result_email_response(
    bool $success,
    string $message,
    array $data = [],
    int $httpCode = 200
): never {

    http_response_code(
        $httpCode
    );


    echo json_encode(
        array_merge(
            [
                'status' =>
                    $success,

                'message' =>
                    $message
            ],
            $data
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );


    exit;
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function result_email_escape(
    mixed $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function result_email_number(
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


function result_email_date(
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


function result_email_status_class(
    string $status
): string {

    return $status === 'Pass'
        ? 'pass'
        : 'fail';
}


function result_email_grade(
    float $percentage
): string {

    return match (true) {

        $percentage >= 90 =>
            'A+',

        $percentage >= 80 =>
            'A',

        $percentage >= 70 =>
            'B+',

        $percentage >= 60 =>
            'B',

        $percentage >= 50 =>
            'C',

        $percentage >= 40 =>
            'D',

        default =>
            'F'
    };
}


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
|
| Current result.php may use GET while older implementations may use POST.
| Support both without weakening authentication or CSRF validation.
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $_SERVER['REQUEST_METHOD'],
        [
            'GET',
            'POST'
        ],
        true
    )
) {

    result_email_response(
        false,
        'Invalid request method.',
        [],
        405
    );
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['user_id']
    )
    ||
    (
        $_SESSION['user_role'] ?? ''
    ) !== 'student'
) {

    result_email_response(
        false,
        'Student login is required.',
        [],
        401
    );
}


$studentId =
    (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
|
| GET requests from the upgraded result page still carry csrf_token in
| the query string. POST requests use the same field in POST data.
|--------------------------------------------------------------------------
*/

$requestToken =
    trim(
        (string) (
            $_POST['csrf_token']
            ??
            $_GET['csrf_token']
            ??
            ''
        )
    );


if (
    $requestToken === ''
) {

    result_email_response(
        false,
        'Security verification failed.',
        [],
        419
    );
}


$csrfValid =
    false;


$globalToken =
    (string) (
        $_SESSION['csrf_token']
        ?? ''
    );


if (
    $globalToken !== ''
) {

    $csrfValid =
        hash_equals(
            $globalToken,
            $requestToken
        );
}


if (
    !$csrfValid
) {

    $examToken =
        (string) (
            $_SESSION['exam_csrf_token']
            ?? ''
        );


    if (
        $examToken !== ''
    ) {

        $csrfValid =
            hash_equals(
                $examToken,
                $requestToken
            );
    }
}


if (
    !$csrfValid
) {

    result_email_response(
        false,
        'Security verification failed.',
        [],
        419
    );
}


/*
|--------------------------------------------------------------------------
| ATTEMPT ID
|--------------------------------------------------------------------------
*/

$attemptId =
    filter_var(
        $_POST['attempt_id']
        ??
        $_GET['attempt_id']
        ??
        null,
        FILTER_VALIDATE_INT
    );


if (
    $attemptId === false
    ||
    $attemptId === null
    ||
    $attemptId <= 0
) {

    result_email_response(
        false,
        'Invalid examination attempt.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| PDF PATH
|--------------------------------------------------------------------------
*/

$pdfPath =
    null;


/*
|--------------------------------------------------------------------------
| MAIN PROCESS
|--------------------------------------------------------------------------
*/

try {


    /*
    |--------------------------------------------------------------------------
    | LOAD FINAL RESULT
    |--------------------------------------------------------------------------
    */

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

                e.passing_marks,
                e.negative_marking,

                e.subject_id,

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


    /*
    |--------------------------------------------------------------------------
    | RESULT NOT FOUND
    |--------------------------------------------------------------------------
    */

    if (
        !$result
    ) {

        result_email_response(
            false,
            'Result not found.',
            [],
            404
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

        result_email_response(
            false,
            'You are not authorized to access this result.',
            [],
            403
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FINALIZED ONLY
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

        result_email_response(
            false,
            'This result is not finalized yet.',
            [],
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EMAIL
    |--------------------------------------------------------------------------
    */

    $studentEmail =
        trim(
            (string) (
                $result[
                    'email'
                ] ?? ''
            )
        );


    if (
        !filter_var(
            $studentEmail,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        result_email_response(
            false,
            'No valid email address is available for this student account.',
            [],
            422
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
            (int) (
                $result[
                    'total_questions'
                ] ?? 0
            )
        );


    $requiredQuestionCount =
        max(
            0,
            (int) (
                $result[
                    'required_question_count'
                ] ?? 0
            )
        );


    if (
        $requiredQuestionCount > 0
        &&
        $totalQuestions !==
        $requiredQuestionCount
    ) {

        result_email_response(
            false,
            'The final result question configuration is inconsistent.',
            [],
            409
        );
    }


    $attemptedQuestions =
        max(
            0,
            (int) (
                $result[
                    'attempted_questions'
                ] ?? 0
            )
        );


    $correctAnswers =
        max(
            0,
            (int) (
                $result[
                    'correct_answers'
                ] ?? 0
            )
        );


    $wrongAnswers =
        max(
            0,
            (int) (
                $result[
                    'wrong_answers'
                ] ?? 0
            )
        );


    $unansweredQuestions =
        max(
            0,
            (int) (
                $result[
                    'unanswered_questions'
                ] ?? 0
            )
        );


    $totalMarks =
        max(
            0,
            round(
                (float) (
                    $result[
                        'total_marks'
                    ] ?? 0
                ),
                2
            )
        );


    $obtainedMarks =
        round(
            (float) (
                $result[
                    'obtained_marks'
                ] ?? 0
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
        round(
            (float) (
                $result[
                    'percentage'
                ] ?? 0
            ),
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
                round(
                    (float) (
                        $result[
                            'passing_marks'
                        ] ?? 0
                    ),
                    2
                )
            )
        );


    $resultStatus =
        trim(
            (string) (
                $result[
                    'result_status'
                ] ?? ''
            )
        );


    $grade =
        trim(
            (string) (
                $result[
                    'grade'
                ] ?? ''
            )
        );


    if (
        $grade === ''
    ) {

        $grade =
            result_email_grade(
                $percentage
            );
    }


    $isPassed =
        $resultStatus === 'Pass';


    /*
    |--------------------------------------------------------------------------
    | VERIFY QUESTION-LEVEL MARKS
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | REMOVE DUPLICATES
    |--------------------------------------------------------------------------
    */

    $questions =
        [];


    $seenQuestionIds =
        [];


    foreach (
        $questionRows as $question
    ) {

        $questionId =
            (int) (
                $question[
                    'question_id'
                ] ?? 0
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


        $questions[] =
            $question;
    }


    /*
    |--------------------------------------------------------------------------
    | EXACT QUESTION COUNT
    |--------------------------------------------------------------------------
    */

    if (
        count($questions)
        !==
        $totalQuestions
    ) {

        result_email_response(
            false,
            'The final result question set is inconsistent.',
            [],
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PER QUESTION MARKS
    |--------------------------------------------------------------------------
    */

    $marksPerQuestion =
        null;


    $calculatedTotalMarks =
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

            result_email_response(
                false,
                'The result contains a question with invalid marks.',
                [],
                409
            );
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

            result_email_response(
                false,
                'The result contains inconsistent per-question marks.',
                [],
                409
            );
        }


        $calculatedTotalMarks +=
            $questionMarks;
    }


    $calculatedTotalMarks =
        round(
            $calculatedTotalMarks,
            2
        );


    /*
    |--------------------------------------------------------------------------
    | TOTAL MARKS FORMULA
    |--------------------------------------------------------------------------
    */

    if (
        $totalQuestions > 0
    ) {

        $formulaTotal =
            round(
                $totalQuestions *
                $marksPerQuestion,
                2
            );


        if (
            abs(
                $formulaTotal -
                $totalMarks
            ) > 0.01
        ) {

            result_email_response(
                false,
                'The final result total marks do not match the configured question marks.',
                [],
                409
            );
        }


        if (
            abs(
                $formulaTotal -
                $calculatedTotalMarks
            ) > 0.01
        ) {

            result_email_response(
                false,
                'The question-wise marks configuration is inconsistent.',
                [],
                409
            );
        }
    }


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

        } catch (Throwable) {

            $timeTakenSeconds =
                0;
        }
    }


    $timeTakenMinutes =
        $timeTakenSeconds > 0
            ? (int) ceil(
                $timeTakenSeconds /
                60
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
            $timeTakenMinutes %
            60;


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
    | PERFORMANCE
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

            throw new RuntimeException(
                'Unable to create PDF temporary directory.'
            );
        }
    }


    if (
        !is_writable(
            $tempDirectory
        )
    ) {

        throw new RuntimeException(
            'PDF temporary directory is not writable.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | COMPOSER
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

        throw new RuntimeException(
            'Composer autoload file was not found.'
        );
    }


    require_once $autoloadPath;


    /*
    |--------------------------------------------------------------------------
    | mPDF
    |--------------------------------------------------------------------------
    */

    if (
        !class_exists(
            '\Mpdf\Mpdf'
        )
    ) {

        throw new RuntimeException(
            'mPDF library is not available.'
        );
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
        !is_file(
            $templatePath
        )
    ) {

        throw new RuntimeException(
            'Result PDF template was not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TEMPLATE DATA
    |--------------------------------------------------------------------------
    |
    | Variables below are deliberately provided because the existing
    | result PDF template uses them directly.
    |--------------------------------------------------------------------------
    */

    $templateResult = [

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

        'full_name' =>
            (string) $result[
                'full_name'
            ],

        'student_code' =>
            (string) $result[
                'student_code'
            ],

        'email' =>
            (string) $result[
                'email'
            ],

        'exam_title' =>
            (string) $result[
                'exam_title'
            ],

        'exam_type' =>
            (string) $result[
                'exam_type'
            ],

        'subject_name' =>
            (string) (
                $result[
                    'subject_name'
                ] ?? ''
            ),

        'subject_code' =>
            (string) (
                $result[
                    'subject_code'
                ] ?? ''
            ),

        'exam_description' =>
            (string) (
                $result[
                    'exam_description'
                ] ?? ''
            ),

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

        'negative_marking' =>
            (int) (
                $result[
                    'negative_marking'
                ] ?? 0
            ),

        'duration_minutes' =>
            (int) (
                $result[
                    'duration_minutes'
                ] ?? 0
            ),

        'started_at' =>
            $result[
                'started_at'
            ] ?? null,

        'submitted_at' =>
            $result[
                'submitted_at'
            ] ?? null,

        'result_created_at' =>
            $result[
                'result_created_at'
            ] ?? null,

        'attempt_status' =>
            (string) $result[
                'attempt_status'
            ]

    ];


    /*
    |--------------------------------------------------------------------------
    | REQUIRED TEMPLATE VARIABLES
    |--------------------------------------------------------------------------
    */

    $templateData =
        $templateResult;


    /*
    |--------------------------------------------------------------------------
    | MAKE VARIABLES AVAILABLE TO TEMPLATE
    |--------------------------------------------------------------------------
    */

    $result =
        $templateData;


    $questions =
        $questions;


    /*
    |--------------------------------------------------------------------------
    | RENDER HTML
    |--------------------------------------------------------------------------
    */

    ob_start();


    try {

        require $templatePath;

        $pdfHtml =
            ob_get_clean();

    } catch (Throwable $exception) {

        ob_end_clean();

        throw $exception;
    }


    if (
        trim(
            $pdfHtml
        ) === ''
    ) {

        throw new RuntimeException(
            'Result PDF template returned empty content.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE PDF
    |--------------------------------------------------------------------------
    */

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


    $examTitle =
        (string) $result[
            'exam_title'
        ];


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
    | SAFE FILE NAME
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


    $pdfPath =
        $tempDirectory
        . DIRECTORY_SEPARATOR
        . 'ExamSphere_Result_'
        . $safeExamName
        . '_'
        . (int) $attemptId
        . '_'
        . bin2hex(
            random_bytes(8)
        )
        . '.pdf';


    $mpdf->Output(
        $pdfPath,
        \Mpdf\Output\Destination::FILE
    );


    if (
        !is_file(
            $pdfPath
        )
        ||
        filesize(
            $pdfPath
        ) <= 0
    ) {

        throw new RuntimeException(
            'Unable to create the result PDF.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MAILER
    |--------------------------------------------------------------------------
    */

    $mail =
        getMailer();


    /*
    |--------------------------------------------------------------------------
    | RECIPIENT
    |--------------------------------------------------------------------------
    */

    $mail->addAddress(

        $studentEmail,

        (string) (
            $result[
                'full_name'
            ] ?? ''
        )

    );


    /*
    |--------------------------------------------------------------------------
    | SUBJECT
    |--------------------------------------------------------------------------
    */

    $mail->Subject =
        'ExamSphere Result — ' .
        $examTitle;


    /*
    |--------------------------------------------------------------------------
    | EMAIL DATA
    |--------------------------------------------------------------------------
    */

    $safeName =
        result_email_escape(
            $result[
                'full_name'
            ] ?? 'Student'
        );


    $safeExam =
        result_email_escape(
            $examTitle
        );


    $safeSubject =
        result_email_escape(
            $result[
                'subject_name'
            ] ?? ''
        );


    $safeType =
        result_email_escape(
            $result[
                'exam_type'
            ] ?? 'Examination'
        );


    $safeDate =
        result_email_escape(
            result_email_date(
                $result[
                    'submitted_at'
                ]
                ??
                $result[
                    'result_created_at'
                ]
            )
        );


    $safePercentage =
        result_email_escape(
            result_email_number(
                $percentage
            )
        );


    $safeObtained =
        result_email_escape(
            result_email_number(
                $obtainedMarks
            )
        );


    $safeTotal =
        result_email_escape(
            result_email_number(
                $totalMarks
            )
        );


    $safePassing =
        result_email_escape(
            result_email_number(
                $passingMarks
            )
        );


    $safeGrade =
        result_email_escape(
            $grade
        );


    $safeStatus =
        result_email_escape(
            $resultStatus
        );


    $safeAccuracy =
        result_email_escape(
            result_email_number(
                $accuracy
            )
        );


    $safeTimeTaken =
        result_email_escape(
            $timeTakenText
        );


    $safeMarksPerQuestion =
        result_email_escape(
            result_email_number(
                $marksPerQuestion
            )
        );


    $safeTotalQuestions =
        (int) $totalQuestions;


    $safeAttempted =
        (int) $attemptedQuestions;


    $safeCorrect =
        (int) $correctAnswers;


    $safeWrong =
        (int) $wrongAnswers;


    $safeUnanswered =
        (int) $unansweredQuestions;


    $statusBg =
        $isPassed
            ? '#EAF4E4'
            : '#FBEAE7';


    $statusText =
        $isPassed
            ? '#556B2F'
            : '#B84A42';


    $statusMessage =
        $isPassed
            ? 'Congratulations! You passed the examination.'
            : 'Keep practising and continue improving your preparation.';


    $statusSubMessage =
        $isPassed
            ? 'Your score met or exceeded the configured passing marks.'
            : 'Use the detailed result analysis to improve your next attempt.';


    /*
    |--------------------------------------------------------------------------
    | HTML EMAIL
    |--------------------------------------------------------------------------
    */

    $mail->isHTML(
        true
    );


    $mail->Body =

        '<!DOCTYPE html>

        <html lang="en">

        <head>

            <meta charset="UTF-8">

            <meta
                name="viewport"
                content="width=device-width,initial-scale=1.0"
            >

            <title>
                ExamSphere Result
            </title>

        </head>


        <body
            style="
                margin:0;
                padding:0;
                background:#F5F5DC;
                font-family:
                    Arial,
                    Helvetica,
                    sans-serif;
                color:#332D29;
            "
        >


            <div
                style="
                    width:100%;
                    padding:32px 14px;
                    box-sizing:border-box;
                "
            >


                <div
                    style="
                        max-width:680px;
                        margin:0 auto;
                        background:#FFFFFF;
                        border:1px solid #E4DED3;
                        border-radius:24px;
                        overflow:hidden;
                    "
                >


                    <div
                        style="
                            padding:28px;
                            background:
                                linear-gradient(
                                    135deg,
                                    #5D4037,
                                    #3E2723
                                );
                            color:#FFFFFF;
                            text-align:center;
                        "
                    >

                        <div
                            style="
                                font-size:28px;
                                line-height:1;
                                font-weight:800;
                            "
                        >
                            ExamSphere
                        </div>


                        <div
                            style="
                                margin-top:7px;
                                color:#E9E1D8;
                                font-size:12px;
                            "
                        >
                            Online Examination Result
                        </div>

                    </div>


                    <div
                        style="
                            padding:30px;
                        "
                    >


                        <div
                            style="
                                color:#6F6760;
                                font-size:12px;
                                margin-bottom:7px;
                            "
                        >
                            RESULT NOTIFICATION
                        </div>


                        <div
                            style="
                                color:#3E2723;
                                font-size:21px;
                                line-height:1.35;
                                font-weight:800;
                            "
                        >

                            Hello
                            ' .
                            $safeName .
                            '

                        </div>


                        <p
                            style="
                                margin:12px 0 0;
                                color:#6F6760;
                                font-size:13px;
                                line-height:1.8;
                            "
                        >

                            Your finalized ExamSphere examination
                            result is ready. Your complete result
                            report is attached to this email as a PDF.

                        </p>


                        <div
                            style="
                                margin-top:24px;
                                padding:22px;
                                border-radius:18px;
                                background:#FAF9F4;
                                border:1px solid #E5DED3;
                            "
                        >


                            <div
                                style="
                                    color:#7C736C;
                                    font-size:10px;
                                    text-transform:uppercase;
                                    letter-spacing:1px;
                                "
                            >
                                Examination
                            </div>


                            <div
                                style="
                                    margin-top:6px;
                                    color:#5D4037;
                                    font-size:19px;
                                    line-height:1.4;
                                    font-weight:800;
                                "
                            >

                                ' .
                                $safeExam .
                                '

                            </div>


                            <div
                                style="
                                    margin-top:8px;
                                    color:#7C736C;
                                    font-size:11px;
                                "
                            >

                                ' .
                                $safeSubject .
                                '

                                &nbsp; • &nbsp;

                                ' .
                                $safeType .
                                '

                            </div>


                            <div
                                style="
                                    margin-top:24px;
                                    padding:18px;
                                    border-radius:16px;
                                    background:
                                        ' .
                                        $statusBg .
                                        ';
                                    text-align:center;
                                "
                            >

                                <div
                                    style="
                                        color:' .
                                        $statusText .
                                        ';
                                        font-size:11px;
                                        text-transform:uppercase;
                                        letter-spacing:1px;
                                        font-weight:800;
                                    "
                                >
                                    Result Status
                                </div>


                                <div
                                    style="
                                        margin-top:4px;
                                        color:' .
                                        $statusText .
                                        ';
                                        font-size:28px;
                                        font-weight:900;
                                    "
                                >

                                    ' .
                                    $safeStatus .
                                    '

                                </div>

                            </div>


                            <div
                                style="
                                    margin-top:20px;
                                    text-align:center;
                                "
                            >

                                <div
                                    style="
                                        color:#7C736C;
                                        font-size:10px;
                                        text-transform:uppercase;
                                        letter-spacing:1px;
                                    "
                                >
                                    Overall Score
                                </div>


                                <div
                                    style="
                                        margin-top:6px;
                                        color:#5D4037;
                                        font-size:36px;
                                        font-weight:900;
                                    "
                                >

                                    ' .
                                    $safePercentage .
                                    '%

                                </div>


                                <div
                                    style="
                                        margin-top:5px;
                                        color:#6E655E;
                                        font-size:13px;
                                    "
                                >

                                    ' .
                                    $safeObtained .
                                    '
                                    /
                                    ' .
                                    $safeTotal .
                                    '
                                    Marks

                                </div>

                            </div>

                        </div>


                        <div
                            style="
                                margin-top:20px;
                            "
                        >

                            <table
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                style="
                                    border-collapse:separate;
                                    border-spacing:8px;
                                    margin-left:-8px;
                                    width:calc(100% + 16px);
                                "
                            >

                                <tr>

                                    <td
                                        width="50%"
                                        style="
                                            background:#F7F4ED;
                                            border:1px solid #E5DED3;
                                            border-radius:14px;
                                            padding:14px;
                                        "
                                    >

                                        <div
                                            style="
                                                color:#7C736C;
                                                font-size:9px;
                                                text-transform:uppercase;
                                            "
                                        >
                                            Total Questions
                                        </div>


                                        <div
                                            style="
                                                margin-top:4px;
                                                color:#3E2723;
                                                font-size:18px;
                                                font-weight:800;
                                            "
                                        >

                                            ' .
                                            $safeTotalQuestions .
                                            '

                                        </div>

                                    </td>


                                    <td
                                        width="50%"
                                        style="
                                            background:#F7F4ED;
                                            border:1px solid #E5DED3;
                                            border-radius:14px;
                                            padding:14px;
                                        "
                                    >

                                        <div
                                            style="
                                                color:#7C736C;
                                                font-size:9px;
                                                text-transform:uppercase;
                                            "
                                        >
                                            Marks / Question
                                        </div>


                                        <div
                                            style="
                                                margin-top:4px;
                                                color:#3E2723;
                                                font-size:18px;
                                                font-weight:800;
                                            "
                                        >

                                            ' .
                                            $safeMarksPerQuestion .
                                            '

                                        </div>

                                    </td>

                                </tr>


                                <tr>

                                    <td
                                        width="50%"
                                        style="
                                            background:#F7F4ED;
                                            border:1px solid #E5DED3;
                                            border-radius:14px;
                                            padding:14px;
                                        "
                                    >

                                        <div
                                            style="
                                                color:#7C736C;
                                                font-size:9px;
                                                text-transform:uppercase;
                                            "
                                        >
                                            Correct
                                        </div>


                                        <div
                                            style="
                                                margin-top:4px;
                                                color:#556B2F;
                                                font-size:18px;
                                                font-weight:800;
                                            "
                                        >

                                            ' .
                                            $safeCorrect .
                                            '

                                        </div>

                                    </td>


                                    <td
                                        width="50%"
                                        style="
                                            background:#F7F4ED;
                                            border:1px solid #E5DED3;
                                            border-radius:14px;
                                            padding:14px;
                                        "
                                    >

                                        <div
                                            style="
                                                color:#7C736C;
                                                font-size:9px;
                                                text-transform:uppercase;
                                            "
                                        >
                                            Wrong
                                        </div>


                                        <div
                                            style="
                                                margin-top:4px;
                                                color:#B84A42;
                                                font-size:18px;
                                                font-weight:800;
                                            "
                                        >

                                            ' .
                                            $safeWrong .
                                            '

                                        </div>

                                    </td>

                                </tr>


                                <tr>

                                    <td
                                        width="50%"
                                        style="
                                            background:#F7F4ED;
                                            border:1px solid #E5DED3;
                                            border-radius:14px;
                                            padding:14px;
                                        "
                                    >

                                        <div
                                            style="
                                                color:#7C736C;
                                                font-size:9px;
                                                text-transform:uppercase;
                                            "
                                        >
                                            Unanswered
                                        </div>


                                        <div
                                            style="
                                                margin-top:4px;
                                                color:#6F6861;
                                                font-size:18px;
                                                font-weight:800;
                                            "
                                        >

                                            ' .
                                            $safeUnanswered .
                                            '

                                        </div>

                                    </td>


                                    <td
                                        width="50%"
                                        style="
                                            background:#F7F4ED;
                                            border:1px solid #E5DED3;
                                            border-radius:14px;
                                            padding:14px;
                                        "
                                    >

                                        <div
                                            style="
                                                color:#7C736C;
                                                font-size:9px;
                                                text-transform:uppercase;
                                            "
                                        >
                                            Accuracy
                                        </div>


                                        <div
                                            style="
                                                margin-top:4px;
                                                color:#5D4037;
                                                font-size:18px;
                                                font-weight:800;
                                            "
                                        >

                                            ' .
                                            $safeAccuracy .
                                            '%

                                        </div>

                                    </td>

                                </tr>

                            </table>

                        </div>


                        <div
                            style="
                                margin-top:20px;
                                padding:15px 16px;
                                border-radius:14px;
                                background:#F5F5DC;
                                border:1px solid #DED7C9;
                                text-align:center;
                            "
                        >

                            <div
                                style="
                                    color:#5D4037;
                                    font-size:14px;
                                    font-weight:800;
                                "
                            >

                                ' .
                                $safeTotalQuestions .
                                '

                                Questions

                                ×

                                ' .
                                $safeMarksPerQuestion .
                                '

                                Mark/Question

                                =

                                ' .
                                $safeTotal .
                                '

                                Total Marks

                            </div>


                            <div
                                style="
                                    margin-top:5px;
                                    color:#756D67;
                                    font-size:10px;
                                "
                            >

                                Dynamic total marks based on the
                                finalized examination configuration.

                            </div>

                        </div>


                        <div
                            style="
                                margin-top:22px;
                                padding:17px;
                                border-left:4px solid ' .
                                $statusText .
                                ';
                                border-radius:10px;
                                background:#FAF9F4;
                            "
                        >

                            <div
                                style="
                                    color:#3E2723;
                                    font-size:13px;
                                    font-weight:800;
                                "
                            >

                                ' .
                                result_email_escape(
                                    $statusMessage
                                ) .
                                '

                            </div>


                            <div
                                style="
                                    margin-top:5px;
                                    color:#756D67;
                                    font-size:11px;
                                    line-height:1.7;
                                "
                            >

                                ' .
                                result_email_escape(
                                    $statusSubMessage
                                ) .
                                '

                            </div>

                        </div>


                        <div
                            style="
                                margin-top:24px;
                                color:#6E665F;
                                font-size:11px;
                                line-height:1.8;
                            "
                        >

                            <strong
                                style="
                                    color:#3E2723;
                                "
                            >
                                Exam Date:
                            </strong>

                            ' .
                            $safeDate .
                            '

                            <br>

                            <strong
                                style="
                                    color:#3E2723;
                                "
                            >
                                Grade:
                            </strong>

                            ' .
                            $safeGrade .
                            '

                            &nbsp;&nbsp;•&nbsp;&nbsp;

                            <strong
                                style="
                                    color:#3E2723;
                                "
                            >
                                Passing Marks:
                            </strong>

                            ' .
                            $safePassing .
                            '

                            <br>

                            <strong
                                style="
                                    color:#3E2723;
                                "
                            >
                                Time Taken:
                            </strong>

                            ' .
                            $safeTimeTaken .
                            '

                            &nbsp;&nbsp;•&nbsp;&nbsp;

                            <strong
                                style="
                                    color:#3E2723;
                                "
                            >
                                Result ID:
                            </strong>

                            #'
                            .
                            (int) $result[
                                'result_id'
                            ]
                            .
                            '

                        </div>


                        <p
                            style="
                                margin:24px 0 0;
                                color:#777069;
                                font-size:11px;
                                line-height:1.8;
                            "
                        >

                            Your complete ExamSphere result
                            report is attached as a PDF.
                            Please keep it for your records
                            and future preparation review.

                        </p>

                    </div>


                    <div
                        style="
                            padding:18px 24px;
                            background:#FAFAF8;
                            border-top:1px solid #ECE7DE;
                            text-align:center;
                            color:#948B83;
                            font-size:10px;
                            line-height:1.6;
                        "
                    >

                        ExamSphere Online Examination System

                        <br>

                        Secure • Structured • Student Focused

                    </div>


                </div>

            </div>

        </body>

        </html>';


    /*
    |--------------------------------------------------------------------------
    | PLAIN TEXT EMAIL
    |--------------------------------------------------------------------------
    */

    $mail->AltBody =

        'ExamSphere Examination Result'
        . PHP_EOL
        . PHP_EOL

        . 'Hello '
        . (
            string
        ) (
            $result[
                'full_name'
            ]
            ?? 'Student'
        )
        . ','
        . PHP_EOL
        . PHP_EOL

        . 'Your finalized ExamSphere examination result is ready.'
        . PHP_EOL
        . PHP_EOL

        . 'Examination: '
        . $examTitle
        . PHP_EOL

        . 'Subject: '
        . (
            string
        ) (
            $result[
                'subject_name'
            ] ?? ''
        )
        . PHP_EOL

        . 'Exam Type: '
        . (
            string
        ) (
            $result[
                'exam_type'
            ] ?? ''
        )
        . PHP_EOL
        . PHP_EOL

        . 'Total Questions: '
        . $totalQuestions
        . PHP_EOL

        . 'Marks Per Question: '
        . result_email_number(
            $marksPerQuestion
        )
        . PHP_EOL

        . 'Total Marks: '
        . result_email_number(
            $totalMarks
        )
        . PHP_EOL

        . 'Obtained Marks: '
        . result_email_number(
            $obtainedMarks
        )
        . PHP_EOL

        . 'Percentage: '
        . result_email_number(
            $percentage
        )
        . '%'
        . PHP_EOL

        . 'Correct Answers: '
        . $correctAnswers
        . PHP_EOL

        . 'Wrong Answers: '
        . $wrongAnswers
        . PHP_EOL

        . 'Unanswered Questions: '
        . $unansweredQuestions
        . PHP_EOL

        . 'Accuracy: '
        . result_email_number(
            $accuracy
        )
        . '%'
        . PHP_EOL

        . 'Grade: '
        . $grade
        . PHP_EOL

        . 'Result: '
        . $resultStatus
        . PHP_EOL

        . 'Passing Marks: '
        . result_email_number(
            $passingMarks
        )
        . PHP_EOL

        . 'Time Taken: '
        . $timeTakenText
        . PHP_EOL
        . PHP_EOL

        . $statusMessage
        . PHP_EOL

        . 'Your complete ExamSphere result PDF is attached.';


    /*
    |--------------------------------------------------------------------------
    | ATTACH PDF
    |--------------------------------------------------------------------------
    */

    $mail->addAttachment(

        $pdfPath,

        'ExamSphere_Result_'
        .
        (int) $attemptId
        .
        '.pdf',

        'base64',

        'application/pdf'

    );


    /*
    |--------------------------------------------------------------------------
    | SEND
    |--------------------------------------------------------------------------
    */

    $mail->send();


    /*
    |--------------------------------------------------------------------------
    | CLEAN TEMP PDF
    |--------------------------------------------------------------------------
    */

    if (
        is_file(
            $pdfPath
        )
    ) {

        @unlink(
            $pdfPath
        );


        $pdfPath =
            null;
    }


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    result_email_response(
        true,
        'Result PDF sent successfully to your registered email.',
        [

            'attempt_id' =>
                (int) $attemptId,

            'result_id' =>
                (int) $result[
                    'result_id'
                ],

            'email' =>
                $studentEmail,

            'total_questions' =>
                $totalQuestions,

            'marks_per_question' =>
                $marksPerQuestion,

            'total_marks' =>
                $totalMarks,

            'obtained_marks' =>
                $obtainedMarks,

            'percentage' =>
                $percentage,

            'result_status' =>
                $resultStatus,

            'grade' =>
                $grade

        ]
    );


} catch (
    Throwable $exception
) {

    /*
    |--------------------------------------------------------------------------
    | TEMP FILE CLEANUP
    |--------------------------------------------------------------------------
    */

    if (
        is_string($pdfPath)
        &&
        $pdfPath !== ''
        &&
        is_file($pdfPath)
    ) {

        @unlink(
            $pdfPath
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ERROR LOG
    |--------------------------------------------------------------------------
    */

    error_log(
        'ExamSphere result email failed: '
        .
        $exception->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | SAFE RESPONSE
    |--------------------------------------------------------------------------
    */

    result_email_response(
        false,
        'Unable to send the result email right now. Please try again later.',
        [],
        500
    );
}