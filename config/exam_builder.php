<?php

declare(strict_types=1);

const EXAM_BUILDER_MAX_TOTAL_MARKS = 300.00;
const EXAM_BUILDER_MAX_QUESTIONS = 65535;

function exam_builder_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function exam_builder_decimal(mixed $value): float
{
    return round(
        (float)$value,
        2
    );
}

function exam_builder_datetime(
    ?string $value
): ?string {

    $value =
        trim(
            (string)(
                $value ?? ''
            )
        );

    if (
        $value === ''
    ) {

        return null;
    }

    $timestamp =
        strtotime(
            $value
        );

    if (
        $timestamp === false
    ) {

        return null;
    }

    return date(
        'Y-m-d H:i:s',
        $timestamp
    );
}

function exam_builder_publish_status(
    string $examType,
    ?string $startsAt,
    ?string $endsAt,
    int $now = 0
): string {

    $now =
        $now > 0
            ? $now
            : time();

    $start =
        $startsAt
            ? strtotime(
                $startsAt
            )
            : false;

    $end =
        $endsAt
            ? strtotime(
                $endsAt
            )
            : false;

    if (
        $end !== false &&
        $end <= $now
    ) {

        return 'Completed';
    }

    if (
        $start !== false &&
        $start > $now
    ) {

        return 'Upcoming';
    }

    if (
        strtolower(
            $examType
        ) ===
        'live'
    ) {

        return 'Running';
    }

    return 'Active';
}

function exam_builder_normalize_csv_header(
    array $header
): array {

    $result = [];

    foreach (
        $header
        as $column
    ) {

        $column =
            preg_replace(
                '/^\xEF\xBB\xBF/',
                '',
                (string)$column
            ) ?? '';

        $column =
            strtolower(
                trim(
                    $column
                )
            );

        $column =
            preg_replace(
                '/[^a-z0-9_]+/',
                '_',
                $column
            ) ?? '';

        $result[] =
            trim(
                $column,
                '_'
            );
    }

    return $result;
}

function exam_builder_parse_csv_questions(
    string $tmpPath,
    int $subjectId,
    int $creatorTeacherId,
    float $marksPerQuestion,
    float $negativeMarks,
    string $requiredCount
): array {

    $handle =
        fopen(
            $tmpPath,
            'rb'
        );

    if (
        $handle === false
    ) {

        throw new RuntimeException(
            'Unable to read the uploaded CSV file.'
        );
    }

    try {

        $header =
            fgetcsv(
                $handle,
                0,
                ','
            );

        if (
            $header === false
        ) {

            throw new RuntimeException(
                'The CSV file is empty.'
            );
        }

        $header =
            exam_builder_normalize_csv_header(
                $header
            );

        $requiredHeaders = [

            'question_text',
            'option_a',
            'option_b',
            'correct_answer'

        ];

        foreach (
            $requiredHeaders
            as $required
        ) {

            if (
                !in_array(
                    $required,
                    $header,
                    true
                )
            ) {

                throw new RuntimeException(
                    "CSV is missing required column: {$required}."
                );
            }

        }

        $index =
            array_flip(
                $header
            );

        $rows = [];

        $line = 1;

        while (
            (
                $row =
                fgetcsv(
                    $handle,
                    0,
                    ','
                )
            ) !== false
        ) {

            $line++;

            if (
                count($row) === 1 &&
                trim(
                    (string)$row[0]
                ) === ''
            ) {

                continue;
            }

            $row =
                array_pad(
                    $row,
                    count($header),
                    ''
                );

            $get =
                static function (
                    string $key
                ) use (
                    $index,
                    $row
                ): string {

                    if (
                        !isset(
                            $index[$key]
                        )
                    ) {

                        return '';
                    }

                    return trim(
                        (string)(
                            $row[
                                $index[$key]
                            ] ?? ''
                        )
                    );
                };

            $questionText =
                $get(
                    'question_text'
                );

            $optionA =
                $get(
                    'option_a'
                );

            $optionB =
                $get(
                    'option_b'
                );

            $optionC =
                $get(
                    'option_c'
                );

            $optionD =
                $get(
                    'option_d'
                );

            $correct =
                strtoupper(
                    $get(
                        'correct_answer'
                    )
                );

            $type =
                $get(
                    'question_type'
                ) ?: 'MCQ';

            $difficulty =
                $get(
                    'difficulty'
                ) ?: 'Medium';

            $explanation =
                $get(
                    'explanation'
                );

            $topicIdRaw =
                $get(
                    'topic_id'
                );

            $timeRaw =
                $get(
                    'estimated_time_seconds'
                );

            if (
                $questionText === ''
            ) {

                throw new RuntimeException(
                    "CSV row {$line}: question_text is required."
                );
            }

            if (
                $optionA === '' ||
                $optionB === ''
            ) {

                throw new RuntimeException(
                    "CSV row {$line}: option_a and option_b are required."
                );
            }

            if (
                !in_array(
                    $correct,
                    [
                        'A',
                        'B',
                        'C',
                        'D'
                    ],
                    true
                )
            ) {

                throw new RuntimeException(
                    "CSV row {$line}: correct_answer must be A, B, C, or D."
                );
            }

            if (
                !in_array(
                    $type,
                    [
                        'MCQ',
                        'TrueFalse'
                    ],
                    true
                )
            ) {

                throw new RuntimeException(
                    "CSV row {$line}: question_type must be MCQ or TrueFalse."
                );
            }

            if (
                !in_array(
                    $difficulty,
                    [
                        'Easy',
                        'Medium',
                        'Hard'
                    ],
                    true
                )
            ) {

                throw new RuntimeException(
                    "CSV row {$line}: difficulty must be Easy, Medium, or Hard."
                );
            }

            $topicId =
                null;

            if (
                $topicIdRaw !== ''
            ) {

                $topicId =
                    filter_var(
                        $topicIdRaw,
                        FILTER_VALIDATE_INT
                    );

                if (
                    $topicId === false ||
                    $topicId <= 0
                ) {

                    throw new RuntimeException(
                        "CSV row {$line}: invalid topic_id."
                    );
                }

                $topicId =
                    (int)$topicId;
            }

            $estimated =
                null;

            if (
                $timeRaw !== ''
            ) {

                $estimated =
                    filter_var(
                        $timeRaw,
                        FILTER_VALIDATE_INT
                    );

                if (
                    $estimated === false ||
                    $estimated < 0
                ) {

                    throw new RuntimeException(
                        "CSV row {$line}: estimated_time_seconds must be zero or greater."
                    );
                }

                $estimated =
                    (int)$estimated;
            }

            $rows[] = [

                'subject_id' =>
                    $subjectId,

                'topic_id' =>
                    $topicId,

                'created_by_teacher_id' =>
                    $creatorTeacherId > 0
                        ? $creatorTeacherId
                        : null,

                'question_type' =>
                    $type,

                'question_text' =>
                    $questionText,

                'option_a' =>
                    $optionA,

                'option_b' =>
                    $optionB,

                'option_c' =>
                    $optionC !== ''
                        ? $optionC
                        : null,

                'option_d' =>
                    $optionD !== ''
                        ? $optionD
                        : null,

                'correct_answer' =>
                    $correct,

                'explanation' =>
                    $explanation !== ''
                        ? $explanation
                        : null,

                'difficulty' =>
                    $difficulty,

                'marks' =>
                    $marksPerQuestion,

                'negative_marks' =>
                    $negativeMarks,

                'estimated_time_seconds' =>
                    $estimated,

                'status' =>
                    'Active'

            ];

            if (
                count($rows) >
                (int)$requiredCount
            ) {

                throw new RuntimeException(
                    "CSV contains more than the required {$requiredCount} questions."
                );
            }

        }

        if (
            count($rows) !==
            (int)$requiredCount
        ) {

            throw new RuntimeException(
                'CSV must contain exactly ' .
                (int)$requiredCount .
                ' question rows; found ' .
                count($rows) .
                '.'
            );
        }

        return $rows;

    } finally {

        fclose(
            $handle
        );
    }
}

function exam_builder_load_existing_question_ids(
    PDO $conn,
    array $questionIds,
    int $subjectId,
    ?int $teacherId,
    float $marksPerQuestion,
    float $negativeMarks
): array {

    $questionIds =
        array_values(
            array_unique(
                array_map(
                    'intval',
                    $questionIds
                )
            )
        );

    if (
        !$questionIds
    ) {

        return [];
    }

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($questionIds),
                '?'
            )
        );

    $sql =
        "SELECT
            id,
            marks,
            negative_marks,
            subject_id,
            created_by_teacher_id,
            status

         FROM questions

         WHERE id IN (
             $placeholders
         )

         AND subject_id = ?

         AND status = 'Active'";

    $params =
        $questionIds;

    $params[] =
        $subjectId;

    if (
        $teacherId !== null
    ) {

        $sql .=
            ' AND created_by_teacher_id = ?';

        $params[] =
            $teacherId;
    }

    $stmt =
        $conn->prepare(
            $sql
        );

    $stmt->execute(
        $params
    );

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    $foundIds =
        array_map(
            'intval',
            array_column(
                $rows,
                'id'
            )
        );

    sort(
        $foundIds
    );

    $requested =
        $questionIds;

    sort(
        $requested
    );

    if (
        $foundIds !==
        $requested
    ) {

        throw new RuntimeException(
            'One or more selected questions are invalid, inactive, from another subject, or outside your question bank.'
        );
    }

    foreach (
        $rows
        as $row
    ) {

        if (
            abs(
                (float)$row['marks'] -
                $marksPerQuestion
            )
            >
            0.000001
        ) {

            throw new RuntimeException(
                'All selected question-bank questions must already use the configured marks per question. Use CSV import when you need a new mark value.'
            );
        }

        if (
            abs(
                (float)$row['negative_marks'] -
                $negativeMarks
            )
            >
            0.000001
        ) {

            throw new RuntimeException(
                'All selected question-bank questions must already use the configured negative marks. Use CSV import when you need a new negative-mark value.'
            );
        }
    }

    return $rows;
}

function exam_builder_create_exam(
    PDO $conn,
    array $exam,
    array $questionIds,
    array $csvRows,
    int $creatorTeacherId
): int {

    $conn->beginTransaction();

    try {

        $requiredCount =
            (int)$exam[
                'required_question_count'
            ];

        $marksPerQuestion =
            (float)$exam[
                'marks_per_question'
            ];

        $totalMarks =
            round(
                $requiredCount *
                $marksPerQuestion,
                2
            );

        if (
            $requiredCount < 1 ||
            $requiredCount >
            EXAM_BUILDER_MAX_QUESTIONS
        ) {

            throw new RuntimeException(
                'Question count is invalid.'
            );
        }

        if (
            $marksPerQuestion <= 0
        ) {

            throw new RuntimeException(
                'Marks per question must be greater than zero.'
            );
        }

        if (
            $totalMarks >
            EXAM_BUILDER_MAX_TOTAL_MARKS
        ) {

            throw new RuntimeException(
                'Total marks cannot exceed 300.'
            );
        }

        if (
            (float)$exam['passing_marks'] >
            $totalMarks
        ) {

            throw new RuntimeException(
                'Passing marks cannot exceed total marks.'
            );
        }

        $status =
            (string)
            $exam['status'];

        $insert =
            $conn->prepare(
                "INSERT INTO exams (
                    subject_id,
                    teacher_id,
                    title,
                    description,
                    exam_type,
                    duration_minutes,
                    required_question_count,
                    total_marks,
                    passing_marks,
                    negative_marking,
                    exam_fee,
                    subscription_required,
                    starts_at,
                    ends_at,
                    status
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )"
            );

        $insert->execute([

            (int)$exam[
                'subject_id'
            ],

            $creatorTeacherId > 0
                ? $creatorTeacherId
                : $exam['teacher_id'],

            $exam['title'],

            $exam['description'] !== ''
                ? $exam['description']
                : null,

            $exam['exam_type'],

            (int)$exam[
                'duration_minutes'
            ],

            $requiredCount,

            number_format(
                $totalMarks,
                2,
                '.',
                ''
            ),

            number_format(
                (float)$exam[
                    'passing_marks'
                ],
                2,
                '.',
                ''
            ),

            $exam[
                'negative_marking'
            ]
                ? 1
                : 0,

            number_format(
                (float)$exam[
                    'exam_fee'
                ],
                2,
                '.',
                ''
            ),

            $exam[
                'subscription_required'
            ]
                ? 1
                : 0,

            $exam[
                'starts_at'
            ],

            $exam[
                'ends_at'
            ],

            $status

        ]);

        $examId =
            (int)
            $conn->lastInsertId();

        if (
            $examId <= 0
        ) {

            throw new RuntimeException(
                'Unable to create the examination.'
            );
        }

        $link =
            $conn->prepare(
                'INSERT INTO exam_questions (
                    exam_id,
                    question_id,
                    position
                )
                VALUES (
                    ?,
                    ?,
                    ?
                )'
            );

        $position =
            1;

        if (
            $csvRows
        ) {

            $insertQuestion =
                $conn->prepare(
                    "INSERT INTO questions (
                        subject_id,
                        topic_id,
                        created_by_teacher_id,
                        question_type,
                        question_text,
                        question_image,
                        option_a,
                        option_b,
                        option_c,
                        option_d,
                        correct_answer,
                        explanation,
                        marks,
                        negative_marks,
                        estimated_time_seconds,
                        difficulty,
                        status
                    )
                    VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )"
                );

            foreach (
                $csvRows
                as $row
            ) {

                $insertQuestion->execute([

                    $row[
                        'subject_id'
                    ],

                    $row[
                        'topic_id'
                    ],

                    $row[
                        'created_by_teacher_id'
                    ],

                    $row[
                        'question_type'
                    ],

                    $row[
                        'question_text'
                    ],

                    null,

                    $row[
                        'option_a'
                    ],

                    $row[
                        'option_b'
                    ],

                    $row[
                        'option_c'
                    ],

                    $row[
                        'option_d'
                    ],

                    $row[
                        'correct_answer'
                    ],

                    $row[
                        'explanation'
                    ],

                    $row[
                        'marks'
                    ],

                    $row[
                        'negative_marks'
                    ],

                    $row[
                        'estimated_time_seconds'
                    ],

                    $row[
                        'difficulty'
                    ],

                    $row[
                        'status'
                    ]

                ]);

                $questionId =
                    (int)
                    $conn->lastInsertId();

                $link->execute([

                    $examId,

                    $questionId,

                    $position++

                ]);
            }

        } else {

            foreach (
                $questionIds
                as $questionId
            ) {

                $link->execute([

                    $examId,

                    (int)$questionId,

                    $position++

                ]);
            }

        }

        $conn->commit();

        return $examId;

    } catch (
        Throwable $e
    ) {

        if (
            $conn->inTransaction()
        ) {

            $conn->rollBack();
        }

        throw $e;
    }
}