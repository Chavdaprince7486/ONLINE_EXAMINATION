<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| EXAMSPHERE CENTRAL EXAM VALIDATION
|--------------------------------------------------------------------------
|
| FINAL EXAM RULES
|
| 1. Practice and Live exams use the configured question count.
| 2. Question count must be between 1 and 50.
| 3. Every assigned question must be Active.
| 4. Every question must have marks > 0.
| 5. All questions in one exam must use the same marks per question.
| 6. TOTAL MARKS = TOTAL QUESTIONS × MARKS PER QUESTION.
| 7. Stored exam.total_marks must match the calculated total.
| 8. Questions must be unique.
|
|--------------------------------------------------------------------------
*/


if (
    !defined('EXAM_MAX_QUESTION_COUNT')
) {

    define(
        'EXAM_MAX_QUESTION_COUNT',
        50
    );
}


/*
|--------------------------------------------------------------------------
| BACKWARD COMPATIBILITY
|--------------------------------------------------------------------------
*/

if (
    !defined('EXAM_REQUIRED_QUESTION_COUNT')
) {

    define(
        'EXAM_REQUIRED_QUESTION_COUNT',
        EXAM_MAX_QUESTION_COUNT
    );
}


/*
|--------------------------------------------------------------------------
| DECIMAL
|--------------------------------------------------------------------------
*/

function exam_validation_decimal(
    mixed $value
): float {

    return round(
        (float)$value,
        2
    );
}


/*
|--------------------------------------------------------------------------
| EXAM NORMALIZER
|--------------------------------------------------------------------------
*/

function exam_validation_normalize_exam(
    array $exam
): array {

    return [

        'total_marks' =>
            exam_validation_decimal(
                $exam['total_marks'] ?? 0
            ),

        'passing_marks' =>
            exam_validation_decimal(
                $exam['passing_marks'] ?? 0
            ),

        'required_question_count' =>
            (int)(
                $exam[
                    'required_question_count'
                ] ?? 0
            )

    ];
}


/*
|--------------------------------------------------------------------------
| REQUIRED QUESTION COUNT
|--------------------------------------------------------------------------
*/

function exam_validation_resolve_required_count(
    ?int $configuredRequiredCount
): int {

    if (
        $configuredRequiredCount === null
    ) {

        return EXAM_REQUIRED_QUESTION_COUNT;
    }


    $configuredRequiredCount =
        (int)$configuredRequiredCount;


    if (
        $configuredRequiredCount < 1 ||
        $configuredRequiredCount >
            EXAM_MAX_QUESTION_COUNT
    ) {

        return 0;
    }


    return $configuredRequiredCount;
}


/*
|--------------------------------------------------------------------------
| CALCULATE TOTAL MARKS
|--------------------------------------------------------------------------
|
| Business rule:
|
| TOTAL MARKS =
| TOTAL QUESTIONS × MARKS PER QUESTION
|
| Therefore all questions in one exam must have
| the same marks value.
|
|--------------------------------------------------------------------------
*/

function exam_validation_calculate_total_marks(
    array $questions
): array {

    $result = [

        'valid' =>
            false,

        'question_count' =>
            0,

        'marks_per_question' =>
            null,

        'total_marks' =>
            0.00,

        'message' =>
            ''

    ];


    if (
        empty($questions)
    ) {

        $result['message'] =
            'No questions are assigned to this exam.';

        return $result;
    }


    $questionCount =
        count($questions);


    $result['question_count'] =
        $questionCount;


    $marksPerQuestion =
        null;


    foreach (
        $questions as $index => $question
    ) {

        if (
            !is_array($question)
        ) {

            $result['message'] =
                'Invalid question data at position ' .
                (
                    (int)$index + 1
                ) .
                '.';

            return $result;
        }


        /*
        |--------------------------------------------------------------------------
        | QUESTION MARKS
        |--------------------------------------------------------------------------
        */

        $questionMarks =
            exam_validation_decimal(
                $question[
                    'marks'
                ] ?? 0
            );


        if (
            $questionMarks <= 0
        ) {

            $result['message'] =
                'Every question must have marks greater than zero.';

            return $result;
        }


        /*
        |--------------------------------------------------------------------------
        | FIRST QUESTION DEFINES PER-QUESTION MARKS
        |--------------------------------------------------------------------------
        */

        if (
            $marksPerQuestion === null
        ) {

            $marksPerQuestion =
                $questionMarks;

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | ALL QUESTIONS MUST USE SAME MARKS
        |--------------------------------------------------------------------------
        */

        if (
            abs(
                $questionMarks -
                $marksPerQuestion
            ) > 0.000001
        ) {

            $result['message'] =
                'All questions in one exam must have the same marks per question.';

            return $result;
        }
    }


    if (
        $marksPerQuestion === null
    ) {

        $result['message'] =
            'Unable to determine marks per question.';

        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | TOTAL = QUESTION COUNT × MARKS
    |--------------------------------------------------------------------------
    */

    $totalMarks =
        exam_validation_decimal(
            $questionCount *
            $marksPerQuestion
        );


    if (
        $totalMarks <= 0
    ) {

        $result['message'] =
            'Calculated total marks must be greater than zero.';

        return $result;
    }


    $result['valid'] =
        true;


    $result['marks_per_question'] =
        $marksPerQuestion;


    $result['total_marks'] =
        $totalMarks;


    $result['message'] =
        'Total marks calculated as ' .
        $questionCount .
        ' × ' .
        number_format(
            $marksPerQuestion,
            2
        ) .
        ' = ' .
        number_format(
            $totalMarks,
            2
        ) .
        '.';


    return $result;
}


/*
|--------------------------------------------------------------------------
| MAIN QUESTION CONFIGURATION VALIDATOR
|--------------------------------------------------------------------------
*/

function validate_exam_question_configuration(
    float|int|string $examTotalMarks,
    array $questions,
    ?int $configuredRequiredCount = null
): array {

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE
    |--------------------------------------------------------------------------
    */

    $storedTotalMarks =
        exam_validation_decimal(
            $examTotalMarks
        );


    $requiredCount =
        exam_validation_resolve_required_count(
            $configuredRequiredCount
        );


    $result = [

        'valid' =>
            false,

        'mode' =>
            'dynamic',

        'total_marks' =>
            $storedTotalMarks,

        'calculated_total_marks' =>
            0.00,

        'actual_marks' =>
            0.00,

        'question_count' =>
            0,

        'required_question_count' =>
            $requiredCount,

        'marks_per_question' =>
            null,

        'same_marks' =>
            false,

        'difference' =>
            0.00,

        'message' =>
            ''

    ];


    /*
    |--------------------------------------------------------------------------
    | REQUIRED COUNT
    |--------------------------------------------------------------------------
    */

    if (
        $requiredCount < 1
    ) {

        $result['message'] =
            'Question count must be between 1 and ' .
            EXAM_MAX_QUESTION_COUNT .
            '.';

        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | QUESTIONS
    |--------------------------------------------------------------------------
    */

    if (
        !is_array($questions)
    ) {

        $result['message'] =
            'Invalid question configuration.';

        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | EXACT QUESTION COUNT
    |--------------------------------------------------------------------------
    */

    $questionCount =
        count($questions);


    $result['question_count'] =
        $questionCount;


    if (
        $questionCount !==
        $requiredCount
    ) {

        $result['message'] =
            'Exactly ' .
            $requiredCount .
            ' questions are required. Currently ' .
            $questionCount .
            ' question(s) are assigned.';

        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | UNIQUE QUESTION IDs
    |--------------------------------------------------------------------------
    */

    $questionIds = [];


    foreach (
        $questions as $index => $question
    ) {

        if (
            !is_array($question)
        ) {

            $result['message'] =
                'Invalid question data at position ' .
                (
                    (int)$index + 1
                ) .
                '.';

            return $result;
        }


        if (
            isset(
                $question['question_id']
            )
        ) {

            $questionId =
                (int)(
                    $question[
                        'question_id'
                    ] ?? 0
                );

        } else {

            $questionId =
                (int)(
                    $question[
                        'id'
                    ] ?? 0
                );
        }


        if (
            $questionId <= 0
        ) {

            $result['message'] =
                'Every question must have a valid question ID.';

            return $result;
        }


        if (
            isset(
                $questionIds[
                    $questionId
                ]
            )
        ) {

            $result['message'] =
                'The same question cannot be assigned more than once to an exam.';

            return $result;
        }


        $questionIds[
            $questionId
        ] = true;


        /*
        |--------------------------------------------------------------------------
        | ACTIVE STATUS
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists(
                'status',
                $question
            )
        ) {

            $questionStatus =
                strtolower(
                    trim(
                        (string)(
                            $question[
                                'status'
                            ]
                        )
                    )
                );


            if (
                $questionStatus !==
                'active'
            ) {

                $result['message'] =
                    'All assigned questions must be Active.';

                return $result;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULATE MARKS
    |--------------------------------------------------------------------------
    */

    $calculation =
        exam_validation_calculate_total_marks(
            $questions
        );


    if (
        !$calculation['valid']
    ) {

        $result['message'] =
            $calculation['message'];

        return $result;
    }


    $calculatedTotal =
        exam_validation_decimal(
            $calculation[
                'total_marks'
            ]
        );


    $marksPerQuestion =
        exam_validation_decimal(
            $calculation[
                'marks_per_question'
            ]
        );


    $result['calculated_total_marks'] =
        $calculatedTotal;


    $result['actual_marks'] =
        $calculatedTotal;


    $result['marks_per_question'] =
        $marksPerQuestion;


    $result['same_marks'] =
        true;


    /*
    |--------------------------------------------------------------------------
    | TOTAL MARK DIFFERENCE
    |--------------------------------------------------------------------------
    */

    $difference =
        exam_validation_decimal(
            $calculatedTotal -
            $storedTotalMarks
        );


    $result['difference'] =
        $difference;


    /*
    |--------------------------------------------------------------------------
    | STORED TOTAL MUST MATCH CALCULATION
    |--------------------------------------------------------------------------
    |
    | The database should contain the dynamically calculated value.
    |
    */

    if (
        abs($difference) >
        0.000001
    ) {

        $result['message'] =
            'Exam total marks are incorrect. ' .
            $questionCount .
            ' questions × ' .
            number_format(
                $marksPerQuestion,
                2
            ) .
            ' marks = ' .
            number_format(
                $calculatedTotal,
                2
            ) .
            ' total marks.';

        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | VALID
    |--------------------------------------------------------------------------
    */

    $result['valid'] =
        true;


    $result['mode'] =
        'dynamic_same_marks';


    $result['message'] =
        'Valid exam configuration: ' .
        $questionCount .
        ' questions × ' .
        number_format(
            $marksPerQuestion,
            2
        ) .
        ' marks = ' .
        number_format(
            $calculatedTotal,
            2
        ) .
        ' total marks.';


    return $result;
}


/*
|--------------------------------------------------------------------------
| LOAD EXAM QUESTIONS
|--------------------------------------------------------------------------
*/

function load_exam_question_configuration(
    PDO $conn,
    int $examId
): array {

    if (
        $examId <= 0
    ) {

        return [];
    }


    $statement =
        $conn->prepare(
            "
            SELECT

                eq.question_id,
                eq.position,

                q.marks,
                q.status,
                q.subject_id,
                q.question_type

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE
                eq.exam_id = ?

            ORDER BY
                eq.position ASC,
                eq.question_id ASC
            "
        );


    $statement->execute([
        $examId
    ]);


    return $statement->fetchAll(
        PDO::FETCH_ASSOC
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE COMPLETE EXAM FROM DATABASE
|--------------------------------------------------------------------------
*/

function validate_exam_from_database(
    PDO $conn,
    int $examId
): array {

    $result = [

        'valid' =>
            false,

        'exam' =>
            null,

        'validation' =>
            null,

        'questions' =>
            []

    ];


    if (
        $examId <= 0
    ) {

        $result['validation'] = [

            'valid' =>
                false,

            'message' =>
                'Invalid exam ID.'

        ];

        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD EXAM
    |--------------------------------------------------------------------------
    */

    $examStatement =
        $conn->prepare(
            "
            SELECT

                id,
                subject_id,
                teacher_id,

                exam_type,
                status,

                duration_minutes,

                required_question_count,

                total_marks,
                passing_marks,

                negative_marking,

                exam_fee,
                subscription_required,

                starts_at,
                ends_at

            FROM exams

            WHERE
                id = ?

            LIMIT 1
            "
        );


    $examStatement->execute([
        $examId
    ]);


    $exam =
        $examStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$exam
    ) {

        $result['validation'] = [

            'valid' =>
                false,

            'message' =>
                'Exam not found.'

        ];

        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD QUESTIONS
    |--------------------------------------------------------------------------
    */

    $questions =
        load_exam_question_configuration(
            $conn,
            $examId
        );


    /*
    |--------------------------------------------------------------------------
    | REQUIRED COUNT
    |--------------------------------------------------------------------------
    */

    $requiredCount =
        (int)(
            $exam[
                'required_question_count'
            ] ?? 0
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDATE
    |--------------------------------------------------------------------------
    */

    $validation =
        validate_exam_question_configuration(

            $exam[
                'total_marks'
            ],

            $questions,

            $requiredCount

        );


    /*
    |--------------------------------------------------------------------------
    | ATTACH CALCULATED VALUES
    |--------------------------------------------------------------------------
    */

    if (
        isset(
            $validation[
                'calculated_total_marks'
            ]
        )
    ) {

        $exam[
            'calculated_total_marks'
        ] =
            (float)(
                $validation[
                    'calculated_total_marks'
                ]
            );
    }


    if (
        isset(
            $validation[
                'marks_per_question'
            ]
        )
    ) {

        $exam[
            'calculated_marks_per_question'
        ] =
            $validation[
                'marks_per_question'
            ];
    }


    $result['exam'] =
        $exam;


    $result['questions'] =
        $questions;


    $result['validation'] =
        $validation;


    $result['valid'] =
        (
            $validation[
                'valid'
            ] === true
        );


    return $result;
}


/*
|--------------------------------------------------------------------------
| QUICK READY CHECK
|--------------------------------------------------------------------------
*/

function exam_is_ready(
    PDO $conn,
    int $examId
): bool {

    $result =
        validate_exam_from_database(
            $conn,
            $examId
        );


    return
        $result['valid'] === true;
}


/*
|--------------------------------------------------------------------------
| REQUIRED QUESTION COUNT
|--------------------------------------------------------------------------
*/

function exam_required_question_count(
    PDO $conn,
    int $examId
): int {

    if (
        $examId <= 0
    ) {

        return 0;
    }


    try {

        $statement =
            $conn->prepare(
                "
                SELECT
                    required_question_count

                FROM exams

                WHERE
                    id = ?

                LIMIT 1
                "
            );


        $statement->execute([
            $examId
        ]);


        $count =
            (int)(
                $statement->fetchColumn()
                ?? 0
            );


        if (
            $count >= 1 &&
            $count <=
                EXAM_MAX_QUESTION_COUNT
        ) {

            return $count;
        }


    } catch (
        Throwable $exception
    ) {

        error_log(
            'Exam required question count lookup failed: ' .
            $exception->getMessage()
        );
    }


    return 0;
}


/*
|--------------------------------------------------------------------------
| ACTUAL CALCULATED EXAM MARKS
|--------------------------------------------------------------------------
*/

function exam_actual_question_marks(
    PDO $conn,
    int $examId
): float {

    if (
        $examId <= 0
    ) {

        return 0.00;
    }


    $questions =
        load_exam_question_configuration(
            $conn,
            $examId
        );


    if (
        empty($questions)
    ) {

        return 0.00;
    }


    $calculation =
        exam_validation_calculate_total_marks(
            $questions
        );


    if (
        !$calculation['valid']
    ) {

        return 0.00;
    }


    return
        exam_validation_decimal(
            $calculation[
                'total_marks'
            ]
        );
}


/*
|--------------------------------------------------------------------------
| MARKS PER QUESTION
|--------------------------------------------------------------------------
*/

function exam_marks_per_question(
    PDO $conn,
    int $examId
): float {

    if (
        $examId <= 0
    ) {

        return 0.00;
    }


    $questions =
        load_exam_question_configuration(
            $conn,
            $examId
        );


    if (
        empty($questions)
    ) {

        return 0.00;
    }


    $calculation =
        exam_validation_calculate_total_marks(
            $questions
        );


    if (
        !$calculation['valid']
    ) {

        return 0.00;
    }


    return
        exam_validation_decimal(
            $calculation[
                'marks_per_question'
            ]
        );
}