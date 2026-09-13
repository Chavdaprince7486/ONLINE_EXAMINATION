<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

require_login('teacher');

$teacherId = current_user_id();

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

$autoloadPath =
    dirname(__DIR__) .
    DIRECTORY_SEPARATOR .
    'vendor' .
    DIRECTORY_SEPARATOR .
    'autoload.php';

if (!is_file($autoloadPath)) {
    http_response_code(500);
    exit('PDF library is not available.');
}

require_once $autoloadPath;

if (!class_exists('\\Mpdf\\Mpdf')) {
    http_response_code(500);
    exit('PDF library is not available.');
}

function teacher_pdf_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function teacher_pdf_number(mixed $value): string
{
    $number = (float)$value;

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

try {

    $stmt = $conn->prepare(
        "
        SELECT
            r.id AS result_id,
            r.attempt_id,
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

            s.student_code,
            s.full_name,
            s.email,

            e.title AS exam_title,
            e.exam_type,
            e.passing_marks,
            e.duration_minutes,

            sub.name AS subject_name

        FROM results r

        INNER JOIN exams e
            ON e.id = r.exam_id
            AND e.teacher_id = ?

        INNER JOIN students s
            ON s.id = r.student_id

        LEFT JOIN subjects sub
            ON sub.id = e.subject_id

        WHERE
            r.attempt_id = ?

        LIMIT 1
        "
    );

    $stmt->execute([
        $teacherId,
        $attemptId
    ]);

    $result =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$result) {
        http_response_code(404);
        exit('Result not found or access denied.');
    }

    $logoPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'exam_logo.png';

    if (!is_file($logoPath)) {
        throw new RuntimeException('ExamSphere logo file is missing.');
    }

    $logoUri = 'file://' . str_replace(DIRECTORY_SEPARATOR, '/', $logoPath);

    $html = '
    <style>
        body{
            font-family:dejavusans;
            color:#382e29;
            font-size:11px;
        }

        .logo{
            width:90px;
            height:auto;
            margin-bottom:7px;
        }

        .brand{
            color:#5d4037;
            font-size:19px;
            font-weight:bold;
            margin-bottom:2px;
        }
        .sub{
            color:#867970;
            font-size:9px;
            margin-bottom:18px;
        }
        .title{
            color:#5d4037;
            font-size:15px;
            font-weight:bold;
        }
        .box{
            border:1px solid #e7dfd5;
            border-radius:8px;
            padding:12px;
            margin-top:12px;
        }
        .label{
            color:#867970;
            font-size:8px;
        }
        .value{
            color:#5d4037;
            font-size:11px;
            font-weight:bold;
        }
        table{
            width:100%;
            border-collapse:collapse;
            margin-top:12px;
        }
        th{
            background:#f2ece1;
            color:#675a51;
            text-align:left;
            font-size:8px;
            padding:7px;
            border:1px solid #ded6ca;
        }
        td{
            padding:7px;
            border:1px solid #e5ddd3;
        }
        .pass{
            color:#4e713f;
            font-weight:bold;
        }
        .fail{
            color:#9b5148;
            font-weight:bold;
        }
    </style>

    <div style="text-align:center;">
        <img class="logo" src="' . $logoUri . '" alt="ExamSphere">
    </div>

    <div class="brand">ExamSphere</div>

    <div class="sub">
        Faculty Result Report
    </div>

    <div class="title">
        ' . teacher_pdf_e(
            $result['exam_title']
        ) . '
    </div>

    <div class="box">
        <table>
            <tr>
                <td>
                    <div class="label">Student</div>
                    <div class="value">'
                    . teacher_pdf_e(
                        $result['full_name']
                    )
                    . '</div>
                </td>
                <td>
                    <div class="label">Student Code</div>
                    <div class="value">'
                    . teacher_pdf_e(
                        $result['student_code']
                    )
                    . '</div>
                </td>
                <td>
                    <div class="label">Subject</div>
                    <div class="value">'
                    . teacher_pdf_e(
                        $result['subject_name']
                            ?? 'General'
                    )
                    . '</div>
                </td>
            </tr>
        </table>

        <table>
            <tr>
                <td>
                    <div class="label">Exam Type</div>
                    <div class="value">'
                    . teacher_pdf_e(
                        $result['exam_type']
                    )
                    . '</div>
                </td>
                <td>
                    <div class="label">Duration</div>
                    <div class="value">'
                    . (int)$result['duration_minutes']
                    . ' minutes</div>
                </td>
                <td>
                    <div class="label">Passing Marks</div>
                    <div class="value">'
                    . teacher_pdf_number(
                        $result['passing_marks']
                    )
                    . '</div>
                </td>
                <td>
                    <div class="label">Result Date</div>
                    <div class="value">'
                    . teacher_pdf_e(
                        $result['created_at']
                    )
                    . '</div>
                </td>
            </tr>
        </table>

        <table>
            <tr>
                <td>
                    <div class="label">Score</div>
                    <div class="value">'
                    . teacher_pdf_number(
                        $result['obtained_marks']
                    )
                    . ' / '
                    . teacher_pdf_number(
                        $result['total_marks']
                    )
                    . '</div>
                </td>
                <td>
                    <div class="label">Percentage</div>
                    <div class="value">'
                    . teacher_pdf_number(
                        $result['percentage']
                    )
                    . '%</div>
                </td>
                <td>
                    <div class="label">Correct / Wrong</div>
                    <div class="value">'
                    . (int)$result['correct_answers']
                    . ' / '
                    . (int)$result['wrong_answers']
                    . '</div>
                </td>
                <td>
                    <div class="label">Grade</div>
                    <div class="value">'
                    . teacher_pdf_e(
                        $result['grade']
                    )
                    . '</div>
                </td>
                <td>
                    <div class="label">Result</div>
                    <div class="value '
                    . (
                        $result['result_status'] === 'Pass'
                            ? 'pass'
                            : 'fail'
                    )
                    . '">'
                    . teacher_pdf_e(
                        $result['result_status']
                    )
                    . '</div>
                </td>
            </tr>
        </table>
    </div>
    ';

    $mpdf = new \Mpdf\Mpdf([
        'format' => 'A4',
        'margin_left' => 14,
        'margin_right' => 14,
        'margin_top' => 14,
        'margin_bottom' => 14
    ]);

    $mpdf->SetTitle(
        'ExamSphere Result - ' .
        (string)$result['exam_title']
    );

    $mpdf->WriteHTML(
        $html
    );

    $filename =
        'ExamSphere_Result_' .
        preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '_',
            (string)$result['student_code']
        ) .
        '_' .
        (int)$result['result_id'] .
        '.pdf';

    $mpdf->Output(
        $filename,
        \Mpdf\Output\Destination::DOWNLOAD
    );

} catch (Throwable $exception) {

    error_log(
        'ExamSphere teacher result PDF failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);
    exit(
        'Unable to generate the result PDF.'
    );
}
