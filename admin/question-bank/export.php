<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {

    http_response_code(403);

    exit(
        "Access denied."
    );
}


/*
|--------------------------------------------------------------------------
| Optional exam filter
|--------------------------------------------------------------------------
*/

$examId =
    filter_input(
        INPUT_GET,
        'exam_id',
        FILTER_VALIDATE_INT
    );


if (
    $examId === false ||
    $examId === null ||
    $examId <= 0
) {

    $examId =
        null;
}


/*
|--------------------------------------------------------------------------
| Optional status filter
|--------------------------------------------------------------------------
*/

$status =
    trim(
        (string)(
            $_GET['status']
            ?? ''
        )
    );


if (
    !in_array(
        $status,
        [
            'Active',
            'Inactive'
        ],
        true
    )
) {

    $status =
        '';
}


/*
|--------------------------------------------------------------------------
| Filename
|--------------------------------------------------------------------------
*/

$fileName =
    "examsphere_questions_" .
    date(
        "Y-m-d_H-i-s"
    ) .
    ".csv";


/*
|--------------------------------------------------------------------------
| Headers
|--------------------------------------------------------------------------
*/

header(
    "Content-Type: text/csv; charset=UTF-8"
);

header(
    'Content-Disposition: attachment; filename="' .
    $fileName .
    '"'
);

header(
    "Cache-Control: no-store, no-cache, must-revalidate"
);

header(
    "Pragma: no-cache"
);

header(
    "Expires: 0"
);


/*
|--------------------------------------------------------------------------
| Output
|--------------------------------------------------------------------------
*/

$output =
    fopen(
        "php://output",
        "wb"
    );


if (
    $output === false
) {

    http_response_code(500);

    exit(
        "Unable to create CSV output."
    );
}


/*
|--------------------------------------------------------------------------
| UTF-8 BOM
|--------------------------------------------------------------------------
*/

fwrite(
    $output,
    "\xEF\xBB\xBF"
);


/*
|--------------------------------------------------------------------------
| Canonical header
|--------------------------------------------------------------------------
*/

fputcsv(
    $output,
    [

        "exam_id",

        "topic_id",

        "question_text",

        "question_type",

        "option_a",

        "option_b",

        "option_c",

        "option_d",

        "correct_answer",

        "explanation",

        "difficulty",

        "marks",

        "negative_marks",

        "estimated_time_seconds",

        "status",

        "position"

    ]
);


/*
|--------------------------------------------------------------------------
| Query
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT

        e.id AS exam_id,

        q.topic_id,

        q.question_text,

        q.question_type,

        q.option_a,
        q.option_b,
        q.option_c,
        q.option_d,

        q.correct_answer,

        q.explanation,

        q.difficulty,

        q.marks,

        q.negative_marks,

        q.estimated_time_seconds,

        q.status,

        eq.position

    FROM exam_questions eq

    INNER JOIN exams e
        ON e.id = eq.exam_id

    INNER JOIN questions q
        ON q.id = eq.question_id

";


$conditions = [];

$params = [];


if (
    $examId !== null
) {

    $conditions[] =
        "e.id = ?";

    $params[] =
        $examId;
}


if (
    $status !== ''
) {

    $conditions[] =
        "q.status = ?";

    $params[] =
        $status;
}


if (
    $conditions
) {

    $sql .=
        " WHERE " .
        implode(
            " AND ",
            $conditions
        );
}


$sql .= "

    ORDER BY

        e.id ASC,

        eq.position ASC,

        q.id ASC

";


try {

    $statement =
        $conn->prepare(
            $sql
        );


    $statement->execute(
        $params
    );


    while (
        $question =
            $statement->fetch(
                PDO::FETCH_ASSOC
            )
    ) {

        fputcsv(
            $output,
            [

                $question['exam_id'],

                $question['topic_id'],

                $question['question_text'],

                $question['question_type'],

                $question['option_a'],

                $question['option_b'],

                $question['option_c'],

                $question['option_d'],

                $question['correct_answer'],

                $question['explanation'],

                $question['difficulty'],

                $question['marks'],

                $question['negative_marks'],

                $question['estimated_time_seconds'],

                $question['status'],

                $question['position']

            ]
        );
    }


    fclose(
        $output
    );

    exit;


} catch (Throwable $exception) {

    error_log(
        "Question CSV export failed: " .
        $exception->getMessage()
    );

    fclose(
        $output
    );

    http_response_code(500);

    exit(
        "Unable to export questions."
    );
}