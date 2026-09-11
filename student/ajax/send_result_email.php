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
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function email_result_response(
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

function email_result_number(
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


function email_result_escape(
    mixed $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    email_result_response(
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
    ) ||
    (
        $_SESSION['user_role'] ?? ''
    ) !== 'student'
) {

    email_result_response(
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
*/

$requestToken =
    trim(
        (string) (
            $_POST['csrf_token']
            ?? ''
        )
    );


if (
    $requestToken === ''
) {

    email_result_response(
        false,
        'Security verification failed.',
        [],
        419
    );
}


$csrfValid =
    false;


/*
|--------------------------------------------------------------------------
| Global CSRF
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Exam CSRF fallback
|--------------------------------------------------------------------------
*/

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

    email_result_response(
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
        ?? null,
        FILTER_VALIDATE_INT
    );


if (
    $attemptId === false ||
    $attemptId === null ||
    $attemptId <= 0
) {

    email_result_response(
        false,
        'Invalid examination attempt.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| TEMP PDF PATH
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
    | LOAD RESULT
    |--------------------------------------------------------------------------
    */

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
        ");


    $resultStatement->execute([

        (int) $attemptId,

        $studentId

    ]);


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

        email_result_response(
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
        (int) $result['student_id']
        !==
        $studentId
    ) {

        email_result_response(
            false,
            'You are not authorized to access this result.',
            [],
            403
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FINALIZED RESULT ONLY
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

        email_result_response(
            false,
            'This result is not finalized yet.',
            [],
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VALID STUDENT EMAIL
    |--------------------------------------------------------------------------
    */

    $studentEmail =
        trim(
            (string) $result['email']
        );


    if (
        !filter_var(
            $studentEmail,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        email_result_response(
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
            (float) $result[
                'total_marks'
            ]
        );


    $obtainedMarks =
        max(
            0,
            min(
                $totalMarks,
                (float) $result[
                    'obtained_marks'
                ]
            )
        );


    $percentage =
        max(
            0,
            min(
                100,
                (float) $result[
                    'percentage'
                ]
            )
        );


    $grade =
        (string) $result[
            'grade'
        ];


    $resultStatus =
        (string) $result[
            'result_status'
        ];


    $passingMarks =
        max(
            0,
            (float) $result[
                'passing_marks'
            ]
        );


    $negativeMarking =
        (int) $result[
            'negative_marking'
        ];


    /*
    |--------------------------------------------------------------------------
    | TIME TAKEN
    |--------------------------------------------------------------------------
    */

    $timeTakenSeconds =
        0;


    if (
        !empty(
            $result['started_at']
        )
        &&
        !empty(
            $result['submitted_at']
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
        (int) ceil(
            $timeTakenSeconds / 60
        );


    /*
    |--------------------------------------------------------------------------
    | DERIVED PERCENTAGES
    |--------------------------------------------------------------------------
    */

    $attemptedPercent =
        $totalQuestions > 0

            ? min(
                100,
                round(
                    (
                        $attemptedQuestions
                        /
                        $totalQuestions
                    )
                    *
                    100
                )
            )

            : 0;


    $correctPercent =
        $totalQuestions > 0

            ? min(
                100,
                round(
                    (
                        $correctAnswers
                        /
                        $totalQuestions
                    )
                    *
                    100
                )
            )

            : 0;


    $wrongPercent =
        $totalQuestions > 0

            ? min(
                100,
                round(
                    (
                        $wrongAnswers
                        /
                        $totalQuestions
                    )
                    *
                    100
                )
            )

            : 0;


    $unansweredPercent =
        $totalQuestions > 0

            ? min(
                100,
                round(
                    (
                        $unansweredQuestions
                        /
                        $totalQuestions
                    )
                    *
                    100
                )
            )

            : 0;


    $accuracy =
        $attemptedQuestions > 0

            ? round(
                (
                    $correctAnswers
                    /
                    $attemptedQuestions
                )
                *
                100,
                2
            )

            : 0;


    $isPassed =
        $resultStatus === 'Pass';


    $remark =
        $isPassed

            ? 'Congratulations! You passed the examination.'

            : 'Keep practicing and improve your preparation.';


    $remarkText =
        $remark;


    /*
    |--------------------------------------------------------------------------
    | LOAD QUESTION ANALYSIS
    |--------------------------------------------------------------------------
    */

    $questionStatement =
        $conn->prepare("
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

        (int) $attemptId,

        (int) $result[
            'exam_id'
        ]

    ]);


    $questions =
        $questionStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | REMOVE DUPLICATE QUESTIONS
    |--------------------------------------------------------------------------
    */

    $uniqueQuestions =
        [];


    foreach (
        $questions as $question
    ) {

        $questionId =
            (int) $question[
                'question_id'
            ];


        if (
            isset(
                $uniqueQuestions[
                    $questionId
                ]
            )
        ) {

            continue;
        }


        $uniqueQuestions[
            $questionId
        ] =
            $question;
    }


    $questions =
        array_values(
            $uniqueQuestions
        );


    /*
    |--------------------------------------------------------------------------
    | PDF TEMP DIRECTORY
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
    | VERIFY mPDF
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
    | RESULT PDF TEMPLATE
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

        throw $exception;
    }


    if (
        trim($pdfHtml) === ''
    ) {

        throw new RuntimeException(
            'Result PDF template returned empty content.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE mPDF
    |--------------------------------------------------------------------------
    */

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
        'ExamSphere'
    );


    $mpdf->SetSubject(
        'Examination Result'
    );


    $mpdf->SetDisplayMode(
        'fullpage'
    );


    /*
    |--------------------------------------------------------------------------
    | PDF CSS
    |--------------------------------------------------------------------------
    */

    $css = <<<CSS

body {
    font-family: dejavusans;
    color: #333333;
    font-size: 8px;
    line-height: 1.45;
}

table {
    border-collapse: collapse;
}

CSS;


    $mpdf->WriteHTML(
        $css,
        \Mpdf\HTMLParserMode::HEADER_CSS
    );


    $mpdf->WriteHTML(
        $pdfHtml,
        \Mpdf\HTMLParserMode::HTML_BODY
    );


    /*
    |--------------------------------------------------------------------------
    | SAFE TEMP FILE NAME
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
    | ADDRESS
    |--------------------------------------------------------------------------
    */

    $mail->addAddress(

        $studentEmail,

        (string) (
            $result[
                'full_name'
            ]
            ?? ''
        )

    );


    /*
    |--------------------------------------------------------------------------
    | SUBJECT
    |--------------------------------------------------------------------------
    */

    $mail->Subject =
        'ExamSphere Result — '
        .
        $examTitle;


    /*
    |--------------------------------------------------------------------------
    | SAFE EMAIL VALUES
    |--------------------------------------------------------------------------
    */

    $safeName =
        email_result_escape(
            $result[
                'full_name'
            ]
            ?? 'Student'
        );


    $safeExam =
        email_result_escape(
            $examTitle
        );


    $safePercentage =
        email_result_escape(
            number_format(
                $percentage,
                2
            )
        );


    $safeResult =
        email_result_escape(
            $resultStatus
        );


    $safeGrade =
        email_result_escape(
            $grade
        );


    $safeObtainedMarks =
        email_result_escape(
            email_result_number(
                $obtainedMarks
            )
        );


    $safeTotalMarks =
        email_result_escape(
            email_result_number(
                $totalMarks
            )
        );


    /*
    |--------------------------------------------------------------------------
    | HTML EMAIL
    |--------------------------------------------------------------------------
    */

    $mail->isHTML(
        true
    );


    $mail->Body = '

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<title>
ExamSphere Result
</title>

</head>


<body style="
    margin:0;
    padding:30px;
    background:#f7f4ef;
    font-family:Arial,Helvetica,sans-serif;
">

    <div style="
        max-width:620px;
        margin:0 auto;
        background:#ffffff;
        border-radius:20px;
        overflow:hidden;
        border:1px solid #e8e1d8;
        box-shadow:0 18px 50px rgba(0,0,0,.08);
    ">


        <div style="
            padding:30px;
            background:#5D4037;
            color:#ffffff;
            text-align:center;
        ">

            <div style="
                font-size:28px;
                font-weight:800;
            ">

                ExamSphere

            </div>


            <div style="
                margin-top:7px;
                font-size:13px;
                opacity:.90;
            ">

                Examination Result

            </div>

        </div>


        <div style="
            padding:34px;
        ">


            <p style="
                color:#333333;
                font-size:16px;
                margin-top:0;
            ">

                Hello

                <strong>
                    ' . $safeName . '
                </strong>,

            </p>


            <p style="
                color:#666666;
                line-height:1.7;
                font-size:14px;
            ">

                Your ExamSphere examination result
                is ready. Your complete result PDF
                is attached to this email.

            </p>


            <div style="
                margin:25px 0;
                background:#f5f5dc;
                border-radius:16px;
                padding:24px;
            ">


                <div style="
                    color:#777777;
                    font-size:11px;
                    text-transform:uppercase;
                    letter-spacing:1px;
                ">

                    Examination

                </div>


                <div style="
                    margin-top:6px;
                    color:#5D4037;
                    font-size:20px;
                    font-weight:700;
                ">

                    ' . $safeExam . '

                </div>


                <div style="
                    margin-top:23px;
                    color:#777777;
                    font-size:11px;
                    text-transform:uppercase;
                    letter-spacing:1px;
                ">

                    Score

                </div>


                <div style="
                    margin-top:5px;
                    color:#5D4037;
                    font-size:34px;
                    font-weight:800;
                ">

                    ' . $safePercentage . '%

                </div>


                <div style="
                    margin-top:12px;
                    color:#555555;
                    font-size:14px;
                ">

                    Marks:

                    <strong>
                        ' . $safeObtainedMarks . '
                        /
                        ' . $safeTotalMarks . '
                    </strong>

                    &nbsp;&nbsp;|&nbsp;&nbsp;

                    Result:

                    <strong>
                        ' . $safeResult . '
                    </strong>

                    &nbsp;&nbsp;|&nbsp;&nbsp;

                    Grade:

                    <strong>
                        ' . $safeGrade . '
                    </strong>

                </div>

            </div>


            <p style="
                color:#666666;
                font-size:14px;
                line-height:1.7;
            ">

                Please keep the attached PDF for
                your records and preparation review.

            </p>

        </div>


        <div style="
            padding:18px;
            text-align:center;
            background:#fafafa;
            border-top:1px solid #eeeeee;
            color:#888888;
            font-size:11px;
        ">

            © ExamSphere — Secure Online Examination Platform

        </div>

    </div>

</body>

</html>
';


    /*
    |--------------------------------------------------------------------------
    | PLAIN TEXT EMAIL
    |--------------------------------------------------------------------------
    */

    $mail->AltBody =

        "ExamSphere Examination Result"
        . PHP_EOL
        . PHP_EOL

        . "Student: "
        . (string) (
            $result[
                'full_name'
            ]
            ?? ''
        )

        . PHP_EOL

        . "Exam: "
        . $examTitle

        . PHP_EOL

        . "Score: "
        . number_format(
            $percentage,
            2
        )
        . "%"

        . PHP_EOL

        . "Marks: "
        . email_result_number(
            $obtainedMarks
        )
        . " / "
        . email_result_number(
            $totalMarks
        )

        . PHP_EOL

        . "Result: "
        . $resultStatus

        . PHP_EOL

        . "Grade: "
        . $grade

        . PHP_EOL
        . PHP_EOL

        . "Your complete ExamSphere result PDF is attached.";


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
    | CLEAN PDF
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

    email_result_response(

        true,

        'Result PDF sent successfully to your registered email.',

        [

            'attempt_id' =>
                (int) $attemptId,

            'email' =>
                $studentEmail

        ]

    );


} catch (
    Throwable $exception
) {

    /*
    |--------------------------------------------------------------------------
    | CLEANUP ON FAILURE
    |--------------------------------------------------------------------------
    */

    if (
        is_string($pdfPath) &&
        $pdfPath !== '' &&
        is_file($pdfPath)
    ) {

        @unlink(
            $pdfPath
        );
    }


    /*
    |--------------------------------------------------------------------------
    | INTERNAL LOG
    |--------------------------------------------------------------------------
    */

    error_log(
        'ExamSphere result email failed: ' .
        $exception->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | SAFE RESPONSE
    |--------------------------------------------------------------------------
    */

    email_result_response(

        false,

        'Unable to send the result email right now. Please try again later.',

        [],

        500

    );
}