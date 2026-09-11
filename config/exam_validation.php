<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| EXAMSPHERE CENTRAL EXAM VALIDATION
|--------------------------------------------------------------------------
|
| Fixed business rule:
|
| Every exam MUST contain exactly 50 questions.
|
| An exam is valid only when:
|
| 1. required_question_count = 50
| 2. exactly 50 distinct questions are assigned
| 3. every assigned question is Active
| 4. every question has valid marks
| 5. sum(question.marks) = exam.total_marks
|
|--------------------------------------------------------------------------
*/


if (
    !defined('EXAM_REQUIRED_QUESTION_COUNT')
) {

    define(
        'EXAM_REQUIRED_QUESTION_COUNT',
        50
    );
}


/*
|--------------------------------------------------------------------------
| DECIMAL NORMALIZATION
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
| MAIN QUESTION CONFIGURATION VALIDATOR
|--------------------------------------------------------------------------
*/

function validate_exam_question_configuration(
    float|int|string $examTotalMarks,
    array $questions,
    ?int $configuredRequiredCount = null
): array {

    $targetMarks =
        exam_validation_decimal(
            $examTotalMarks
        );


    $requiredCount =
        EXAM_REQUIRED_QUESTION_COUNT;


    $result = [

        'valid' =>
            false,

        'mode' =>
            'fixed_50',

        'total_marks' =>
            $targetMarks,

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
    | TOTAL MARKS
    |--------------------------------------------------------------------------
    */

    if (
        $targetMarks <= 0
    ) {

        $result['message'] =
            'Exam total marks must be greater than zero.';

        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | QUESTION ARRAY
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
    | STORED QUESTION COUNT
    |--------------------------------------------------------------------------
    */

    if (
        $configuredRequiredCount !== null &&
        $configuredRequiredCount !==
            $requiredCount
    ) {

        $result['message'] =
            'Invalid stored required question count. It must be exactly ' .
            $requiredCount .
            '.';

        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | DISTINCT QUESTION IDS
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
            !isset(
                $question['question_id']
            )
        ) {

            /*
             * Some callers use "id" instead.
             */
            $questionId =
                (int)(
                    $question['id']
                    ?? 0
                );

        } else {

            $questionId =
                (int)(
                    $question['question_id']
                    ?? 0
                );
        }


        if (
            $questionId <= 0
        ) {

            $result['message'] =
                'Every exam question must have a valid question ID.';

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
        | QUESTION STATUS
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists(
                'status',
                $question
            )
        ) {

            if (
                strtolower(
                    trim(
                        (string)$question['status']
                    )
                ) !== 'active'
            ) {

                $result['message'] =
                    'All 50 assigned questions must be Active.';

                return $result;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | QUESTION MARKS
        |--------------------------------------------------------------------------
        */

        $marks =
            exam_validation_decimal(
                $question['marks'] ?? 0
            );


        if (
            $marks <= 0
        ) {

            $result['message'] =
                'Every exam question must have marks greater than zero.';

            return $result;
        }


        $result['actual_marks'] +=
            $marks;
    }


    /*
    |--------------------------------------------------------------------------
    | FINAL MARK TOTAL
    |--------------------------------------------------------------------------
    */

    $actualMarks =
        exam_validation_decimal(
            $result['actual_marks']
        );


    $result['actual_marks'] =
        $actualMarks;


    $difference =
        exam_validation_decimal(
            $actualMarks -
            $targetMarks
        );


    $result['difference'] =
        $difference;


    /*
    |--------------------------------------------------------------------------
    | EXACT MARK MATCH
    |--------------------------------------------------------------------------
    */

    if (
        abs($difference) >
        0.000001
    ) {

        $result['message'] =
            'The 50 questions total ' .
            number_format(
                $actualMarks,
                2
            ) .
            ' marks, but the exam total is ' .
            number_format(
                $targetMarks,
                2
            ) .
            ' marks.';

        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | SAME MARK INFORMATION
    |--------------------------------------------------------------------------
    */

    $marksList = [];


    foreach (
        $questions as $question
    ) {

        $marksList[] =
            exam_validation_decimal(
                $question['marks'] ?? 0
            );
    }


    $uniqueMarks =
        array_values(
            array_unique(
                array_map(
                    static function (
                        float $marks
                    ): string {

                        return number_format(
                            $marks,
                            2,
                            '.',
                            ''
                        );
                    },
                    $marksList
                )
            )
        );


    if (
        count($uniqueMarks) === 1
    ) {

        $result['same_marks'] =
            true;

        $result['marks_per_question'] =
            $marksList[0];

        $result['mode'] =
            'fixed_50_same_marks';

    } else {

        $result['same_marks'] =
            false;

        $result['marks_per_question'] =
            null;

        $result['mode'] =
            'fixed_50_mixed_marks';
    }


    /*
    |--------------------------------------------------------------------------
    | VALID
    |--------------------------------------------------------------------------
    */

    $result['valid'] =
        true;


    $result['message'] =
        'Valid exam configuration: exactly 50 Active questions and exact total marks.';


    return $result;
}


/*
|--------------------------------------------------------------------------
| LOAD EXAM QUESTIONS FROM DATABASE
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
        $conn->prepare("
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
        ");


    $statement->execute([
        $examId
    ]);


    return $statement->fetchAll(
        PDO::FETCH_ASSOC
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE EXAM FROM DATABASE
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
        $conn->prepare("
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
        ");


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
    | LOAD ASSIGNED QUESTIONS
    |--------------------------------------------------------------------------
    */

    $questions =
        load_exam_question_configuration(
            $conn,
            $examId
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

            (int)(
                $exam[
                    'required_question_count'
                ] ?? 0
            )

        );


    $result['exam'] =
        $exam;

    $result['questions'] =
        $questions;

    $result['validation'] =
        $validation;

    $result['valid'] =
        (
            $validation['valid'] ===
            true
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
        $result['valid'] ===
        true;
}


/*
|--------------------------------------------------------------------------
| REQUIRED COUNT
|--------------------------------------------------------------------------
*/

function exam_required_question_count(
    PDO $conn,
    int $examId
): int {

    /*
     * ExamSphere rule is fixed.
     *
     * Do not derive the count from marks.
     */

    return
        EXAM_REQUIRED_QUESTION_COUNT;
}


/*
|--------------------------------------------------------------------------
| ACTUAL QUESTION MARKS
|--------------------------------------------------------------------------
*/

function exam_actual_question_marks(
    PDO $conn,
    int $examId
): float {

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


    $total =
        0.00;


    foreach (
        $questions as $question
    ) {

        $total +=
            exam_validation_decimal(
                $question['marks'] ?? 0
            );
    }


    return
        exam_validation_decimal(
            $total
        );
}