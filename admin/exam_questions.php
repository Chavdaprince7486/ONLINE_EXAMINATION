<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/exam_validation.php';


/*
|--------------------------------------------------------------------------
| ADMIN AUTH
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {

    header(
        'Location: ../auth/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function exam_questions_escape(
    mixed $value
): string {

    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| EXAM ID
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
        filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT
        );
}


if (
    $examId === false ||
    $examId === null ||
    $examId <= 0
) {

    header(
        'Location: exams.php'
    );

    exit;
}


$examId =
    (int)$examId;


/*
|--------------------------------------------------------------------------
| LOAD EXAM
|--------------------------------------------------------------------------
*/

$examQuery =
    $conn->prepare("
        SELECT

            e.id,

            e.subject_id,
            e.teacher_id,

            e.title,
            e.description,

            e.exam_type,

            e.duration_minutes,

            e.required_question_count,

            e.total_marks,
            e.passing_marks,

            e.negative_marking,

            e.exam_fee,
            e.subscription_required,

            e.starts_at,
            e.ends_at,

            e.status,

            e.created_at,
            e.updated_at,

            s.name AS subject_name,

            s.status AS subject_status,

            c.category_name,

            t.full_name AS teacher_name

        FROM exams e

        LEFT JOIN subjects s
            ON s.id = e.subject_id

        LEFT JOIN categories c
            ON c.id = s.category_id

        LEFT JOIN teachers t
            ON t.id = e.teacher_id

        WHERE
            e.id = ?

        LIMIT 1
    ");


$examQuery->execute([
    $examId
]);


$exam =
    $examQuery->fetch(
        PDO::FETCH_ASSOC
    );


if (
    !$exam
) {

    $_SESSION['error'] =
        'Exam not found.';

    header(
        'Location: exams.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

$message = '';

$error = '';


if (
    !empty($_SESSION['success_message'])
) {

    $message =
        (string)(
            $_SESSION['success_message']
        );

    unset(
        $_SESSION['success_message']
    );
}


if (
    !empty($_SESSION['success'])
) {

    $message =
        (string)(
            $_SESSION['success']
        );

    unset(
        $_SESSION['success']
    );
}


if (
    !empty($_SESSION['error'])
) {

    $error =
        (string)(
            $_SESSION['error']
        );

    unset(
        $_SESSION['error']
    );
}


/*
|--------------------------------------------------------------------------
| CURRENT SELECTED QUESTIONS
|--------------------------------------------------------------------------
*/

$selectedIds = [];


$selectedQuery =
    $conn->prepare("
        SELECT

            question_id

        FROM exam_questions

        WHERE
            exam_id = ?

        ORDER BY

            position ASC,
            question_id ASC
    ");


$selectedQuery->execute([
    $examId
]);


$selectedIds =
    array_map(
        'intval',
        $selectedQuery->fetchAll(
            PDO::FETCH_COLUMN
        )
    );


$selectedIds =
    array_values(
        array_unique(
            array_filter(
                $selectedIds,
                static function (
                    int $id
                ): bool {

                    return $id > 0;
                }
            )
        )
    );


/*
|--------------------------------------------------------------------------
| CURRENT DATABASE VALIDATION
|--------------------------------------------------------------------------
*/

$currentValidation =
    validate_exam_from_database(
        $conn,
        $examId
    );


$currentQuestions =
    $currentValidation['questions']
    ?? [];


$currentQuestionCount =
    count(
        $currentQuestions
    );


$currentQuestionMarks =
    0.00;


foreach (
    $currentQuestions as $currentQuestion
) {

    $currentQuestionMarks +=
        round(
            (float)(
                $currentQuestion['marks']
                ?? 0
            ),
            2
        );
}


$currentQuestionMarks =
    round(
        $currentQuestionMarks,
        2
    );


/*
|--------------------------------------------------------------------------
| ATTEMPTS
|--------------------------------------------------------------------------
*/

$attemptCount = 0;


try {

    $attemptStatement =
        $conn->prepare("
            SELECT
                COUNT(*)
            FROM exam_attempts
            WHERE
                exam_id = ?
        ");

    $attemptStatement->execute([
        $examId
    ]);

    $attemptCount =
        (int)$attemptStatement
            ->fetchColumn();

} catch (Throwable $exception) {

    error_log(
        'Exam question attempt count failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| POST SAVE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | CSRF
        |--------------------------------------------------------------------------
        */

        if (
            !verify_csrf_token(
                $_POST['csrf_token'] ?? null
            )
        ) {

            throw new RuntimeException(
                'Invalid security token. Please refresh the page and try again.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LOCK EXAM
        |--------------------------------------------------------------------------
        */

        $conn->beginTransaction();


        $lockedExamStatement =
            $conn->prepare("
                SELECT

                    id,
                    subject_id,

                    exam_type,

                    required_question_count,

                    total_marks,

                    passing_marks,

                    status

                FROM exams

                WHERE
                    id = ?

                LIMIT 1

                FOR UPDATE
            ");


        $lockedExamStatement->execute([
            $examId
        ]);


        $lockedExam =
            $lockedExamStatement->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$lockedExam
        ) {

            throw new RuntimeException(
                'The selected exam no longer exists.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | NO CHANGES AFTER ATTEMPTS
        |--------------------------------------------------------------------------
        */

        $attemptStatement =
            $conn->prepare("
                SELECT
                    COUNT(*)
                FROM exam_attempts
                WHERE
                    exam_id = ?
            ");


        $attemptStatement->execute([
            $examId
        ]);


        $lockedAttemptCount =
            (int)$attemptStatement->fetchColumn();


        if (
            $lockedAttemptCount > 0
        ) {

            throw new RuntimeException(
                'Questions cannot be changed after students have started this exam.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | READ POSTED QUESTION IDS
        |--------------------------------------------------------------------------
        */

        $postedIds =
            $_POST['question_ids'] ?? [];


        if (
            !is_array($postedIds)
        ) {

            $postedIds = [];
        }


        $selected = [];


        foreach (
            $postedIds as $questionId
        ) {

            $questionId =
                filter_var(
                    $questionId,
                    FILTER_VALIDATE_INT
                );


            if (
                $questionId === false ||
                $questionId === null ||
                $questionId <= 0
            ) {

                continue;
            }


            $selected[] =
                (int)$questionId;
        }


        /*
        |--------------------------------------------------------------------------
        | REMOVE DUPLICATES
        |--------------------------------------------------------------------------
        */

        $selected =
            array_values(
                array_unique(
                    $selected
                )
            );


        /*
        |--------------------------------------------------------------------------
        | EXACTLY 50
        |--------------------------------------------------------------------------
        */

        if (
            count($selected)
            !==
            EXAM_REQUIRED_QUESTION_COUNT
        ) {

            throw new RuntimeException(
                'Exactly ' .
                EXAM_REQUIRED_QUESTION_COUNT .
                ' questions are required. You selected ' .
                count($selected) .
                '.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | SUBJECT
        |--------------------------------------------------------------------------
        */

        $examSubjectId =
            (int)(
                $lockedExam[
                    'subject_id'
                ]
                ?? 0
            );


        if (
            $examSubjectId <= 0
        ) {

            throw new RuntimeException(
                'This exam does not have a valid subject.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | TOTAL MARKS
        |--------------------------------------------------------------------------
        */

        $targetTotalMarks =
            round(
                (float)(
                    $lockedExam[
                        'total_marks'
                    ]
                    ?? 0
                ),
                2
            );


        if (
            $targetTotalMarks <= 0
        ) {

            throw new RuntimeException(
                'This exam has an invalid total marks value.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LOAD EXACT SELECTED QUESTIONS
        |--------------------------------------------------------------------------
        */

        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($selected),
                    '?'
                )
            );


        $validQuestionStatement =
            $conn->prepare("
                SELECT

                    id,

                    subject_id,
                    topic_id,

                    question_type,
                    question_text,

                    marks,
                    negative_marks,

                    difficulty,
                    status

                FROM questions

                WHERE

                    id IN (
                        $placeholders
                    )

                    AND subject_id = ?

                    AND status = 'Active'

                ORDER BY
                    id ASC

                FOR UPDATE
            ");


        $validQuestionStatement->execute(
            array_merge(
                $selected,
                [
                    $examSubjectId
                ]
            )
        );


        $validQuestions =
            $validQuestionStatement->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | EVERY SELECTED QUESTION MUST EXIST
        |--------------------------------------------------------------------------
        */

        if (
            count($validQuestions)
            !==
            EXAM_REQUIRED_QUESTION_COUNT
        ) {

            throw new RuntimeException(
                'All 50 selected questions must exist, belong to the exam subject, and be Active.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | MAP QUESTION IDS
        |--------------------------------------------------------------------------
        */

        $validQuestionIds =
            array_map(
                static function (
                    array $question
                ): int {

                    return (int)$question['id'];

                },
                $validQuestions
            );


        sort(
            $validQuestionIds
        );


        $postedSortedIds =
            $selected;


        sort(
            $postedSortedIds
        );


        if (
            $validQuestionIds !==
            $postedSortedIds
        ) {

            throw new RuntimeException(
                'The selected questions could not be validated.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | TOPIC CHECK
        |--------------------------------------------------------------------------
        |
        | A topic, when present, must belong to the same subject.
        |
        */

        $topicIds =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static function (
                                array $question
                            ): int {

                                return (int)(
                                    $question[
                                        'topic_id'
                                    ] ?? 0
                                );

                            },
                            $validQuestions
                        ),
                        static function (
                            int $topicId
                        ): bool {

                            return $topicId > 0;
                        }
                    )
                )
            );


        if (
            !empty($topicIds)
        ) {

            $topicPlaceholders =
                implode(
                    ',',
                    array_fill(
                        0,
                        count($topicIds),
                        '?'
                    )
                );


            $topicStatement =
                $conn->prepare("
                    SELECT

                        id,
                        subject_id,
                        status

                    FROM topics

                    WHERE

                        id IN (
                            $topicPlaceholders
                        )

                    FOR UPDATE
                ");


            $topicStatement->execute(
                $topicIds
            );


            $topics =
                $topicStatement->fetchAll(
                    PDO::FETCH_ASSOC
                );


            $topicMap = [];


            foreach (
                $topics as $topic
            ) {

                $topicMap[
                    (int)$topic['id']
                ] =
                    $topic;
            }


            foreach (
                $topicIds as $topicId
            ) {

                if (
                    !isset(
                        $topicMap[
                            $topicId
                        ]
                    )
                ) {

                    throw new RuntimeException(
                        'One of the selected question topics does not exist.'
                    );
                }


                if (
                    (int)$topicMap[
                        $topicId
                    ]['subject_id']
                    !==
                    $examSubjectId
                ) {

                    throw new RuntimeException(
                        'Every selected question topic must belong to the exam subject.'
                    );
                }


                if (
                    (string)$topicMap[
                        $topicId
                    ]['status']
                    !==
                    'Active'
                ) {

                    throw new RuntimeException(
                        'Every selected question topic must be Active.'
                    );
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | CENTRAL VALIDATION
        |--------------------------------------------------------------------------
        */

        $centralValidation =
            validate_exam_question_configuration(
                $targetTotalMarks,
                $validQuestions,
                EXAM_REQUIRED_QUESTION_COUNT
            );


        if (
            !$centralValidation['valid']
        ) {

            throw new RuntimeException(
                $centralValidation['message']
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PASSING MARKS
        |--------------------------------------------------------------------------
        */

        $passingMarks =
            round(
                (float)(
                    $lockedExam[
                        'passing_marks'
                    ] ?? 0
                ),
                2
            );


        if (
            $passingMarks < 0 ||
            $passingMarks >
            $centralValidation[
                'actual_marks'
            ]
        ) {

            throw new RuntimeException(
                'Passing marks must be between zero and the final exam total marks.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | BUILD NEW STATUS
        |--------------------------------------------------------------------------
        */

        $examType =
            (string)(
                $lockedExam[
                    'exam_type'
                ] ?? ''
            );


        $requestedStatus =
            trim(
                (string)(
                    $_POST['status']
                    ?? ''
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Practice
        |--------------------------------------------------------------------------
        */

        if (
            $examType === 'Practice'
        ) {

            $newStatus =
                'Active';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Live
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $requestedStatus,
                    [
                        'Scheduled',
                        'Live'
                    ],
                    true
                )
            ) {

                $newStatus =
                    $requestedStatus;

            } else {

                $existingStatus =
                    (string)(
                        $lockedExam[
                            'status'
                        ] ?? ''
                    );


                if (
                    in_array(
                        $existingStatus,
                        [
                            'Scheduled',
                            'Live'
                        ],
                        true
                    )
                ) {

                    $newStatus =
                        $existingStatus;

                } else {

                    $newStatus =
                        'Scheduled';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | NEVER SET INVALID STATUS
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $newStatus,
                [
                    'Active',
                    'Scheduled',
                    'Live'
                ],
                true
            )
        ) {

            throw new RuntimeException(
                'Invalid exam status.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LIVE DATE CHECK
        |--------------------------------------------------------------------------
        */

        if (
            $examType === 'Live'
        ) {

            $startValue =
                trim(
                    (string)(
                        $_POST[
                            'starts_at'
                        ]
                        ?? ''
                    )
                );


            if (
                $startValue === ''
            ) {

                /*
                 * If no new date was submitted,
                 * the existing exam date remains valid.
                 */
                $existingStart =
                    trim(
                        (string)(
                            $lockedExam[
                                'starts_at'
                            ] ?? ''
                        )
                    );


                if (
                    $existingStart === ''
                ) {

                    /*
                     * We do not silently invent a date.
                     */
                    if (
                        $newStatus !==
                        'Scheduled'
                    ) {

                        throw new RuntimeException(
                            'A Live exam requires a valid start date and time.'
                        );
                    }
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE OLD LINKS
        |--------------------------------------------------------------------------
        */

        $deleteStatement =
            $conn->prepare("
                DELETE FROM exam_questions
                WHERE exam_id = ?
            ");


        $deleteStatement->execute([
            $examId
        ]);


        /*
        |--------------------------------------------------------------------------
        | INSERT 50 LINKS
        |--------------------------------------------------------------------------
        */

        $insertStatement =
            $conn->prepare("
                INSERT INTO exam_questions
                (
                    exam_id,
                    question_id,
                    position
                )
                VALUES
                (
                    ?,
                    ?,
                    ?
                )
            ");


        $position =
            1;


        foreach (
            $selected as $questionId
        ) {

            if (
                $position >
                EXAM_REQUIRED_QUESTION_COUNT
            ) {

                throw new RuntimeException(
                    'Question position exceeded the maximum of 50.'
                );
            }


            $insertStatement->execute([
                $examId,
                (int)$questionId,
                $position
            ]);


            $position++;
        }


        /*
        |--------------------------------------------------------------------------
        | FINAL COUNT
        |--------------------------------------------------------------------------
        */

        $finalCountStatement =
            $conn->prepare("
                SELECT
                    COUNT(*)
                FROM exam_questions
                WHERE
                    exam_id = ?
            ");


        $finalCountStatement->execute([
            $examId
        ]);


        $finalCount =
            (int)$finalCountStatement
                ->fetchColumn();


        if (
            $finalCount !==
            EXAM_REQUIRED_QUESTION_COUNT
        ) {

            throw new RuntimeException(
                'Final question count verification failed. The exam must contain exactly 50 questions.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | FINAL MARKS
        |--------------------------------------------------------------------------
        */

        $finalMarksStatement =
            $conn->prepare("
                SELECT

                    COALESCE(
                        SUM(q.marks),
                        0
                    )

                FROM exam_questions eq

                INNER JOIN questions q
                    ON q.id = eq.question_id

                WHERE
                    eq.exam_id = ?
            ");


        $finalMarksStatement->execute([
            $examId
        ]);


        $finalMarks =
            round(
                (float)(
                    $finalMarksStatement
                    ->fetchColumn()
                ),
                2
            );


        if (
            abs(
                $finalMarks -
                $targetTotalMarks
            ) >
            0.000001
        ) {

            throw new RuntimeException(
                'Final question marks total ' .
                number_format(
                    $finalMarks,
                    2
                ) .
                ', but exam total marks are ' .
                number_format(
                    $targetTotalMarks,
                    2
                ) .
                '.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | FINAL QUESTION STATUS CHECK
        |--------------------------------------------------------------------------
        */

        $finalStatusStatement =
            $conn->prepare("
                SELECT
                    COUNT(*)
                FROM exam_questions eq

                INNER JOIN questions q
                    ON q.id = eq.question_id

                WHERE
                    eq.exam_id = ?

                    AND q.status <> 'Active'
            ");


        $finalStatusStatement->execute([
            $examId
        ]);


        $inactiveQuestionCount =
            (int)(
                $finalStatusStatement
                ->fetchColumn()
            );


        if (
            $inactiveQuestionCount > 0
        ) {

            throw new RuntimeException(
                'Every assigned question must remain Active.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | FINAL REQUIRED COUNT + TOTAL MARKS + STATUS
        |--------------------------------------------------------------------------
        */

        $finalExamUpdate =
            $conn->prepare("
                UPDATE exams

                SET

                    required_question_count = ?,

                    total_marks = ?,

                    status = ?

                WHERE
                    id = ?
            ");


        $finalExamUpdate->execute([

            EXAM_REQUIRED_QUESTION_COUNT,

            number_format(
                $finalMarks,
                2,
                '.',
                ''
            ),

            $newStatus,

            $examId

        ]);


        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        $conn->commit();


        /*
        |--------------------------------------------------------------------------
        | MESSAGE
        |--------------------------------------------------------------------------
        */

        $message =
            'Exam updated successfully with exactly 50 Active questions and ' .
            number_format(
                $finalMarks,
                2
            ) .
            ' total marks.';


        /*
        |--------------------------------------------------------------------------
        | REFRESH
        |--------------------------------------------------------------------------
        */

        $examQuery->execute([
            $examId
        ]);


        $exam =
            $examQuery->fetch(
                PDO::FETCH_ASSOC
            );


        $selectedQuery->execute([
            $examId
        ]);


        $selectedIds =
            array_map(
                'intval',
                $selectedQuery->fetchAll(
                    PDO::FETCH_COLUMN
                )
            );


        $currentValidation =
            validate_exam_from_database(
                $conn,
                $examId
            );


        $currentQuestions =
            $currentValidation[
                'questions'
            ]
            ?? [];


        $currentQuestionCount =
            count(
                $currentQuestions
            );


        $currentQuestionMarks =
            0.00;


        foreach (
            $currentQuestions
            as $question
        ) {

            $currentQuestionMarks +=
                round(
                    (float)(
                        $question[
                            'marks'
                        ] ?? 0
                    ),
                    2
                );
        }


        $currentQuestionMarks =
            round(
                $currentQuestionMarks,
                2
            );


    } catch (Throwable $exception) {

        if (
            $conn->inTransaction()
        ) {

            $conn->rollBack();
        }


        error_log(
            'Admin exam question management failed: ' .
            $exception->getMessage()
        );


        $error =
            $exception->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| LOAD AVAILABLE ACTIVE QUESTIONS
|--------------------------------------------------------------------------
*/

$questions = [];


try {

    $questionQuery =
        $conn->prepare("
            SELECT

                q.id,

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

                q.estimated_time_seconds,

                q.difficulty,
                q.question_type,

                q.status,

                q.created_at,

                q.topic_id,

                t.name AS topic_name

            FROM questions q

            LEFT JOIN topics t
                ON t.id = q.topic_id

            WHERE

                q.subject_id = ?

                AND q.status = 'Active'

            ORDER BY

                q.created_at DESC,
                q.id DESC
        ");


    $questionQuery->execute([
        (int)$exam['subject_id']
    ]);


    $questions =
        $questionQuery->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Admin available exam questions failed: ' .
        $exception->getMessage()
    );


    if (
        $error === ''
    ) {

        $error =
            'Unable to load available questions.';
    }
}


/*
|--------------------------------------------------------------------------
| AVAILABLE COUNTS
|--------------------------------------------------------------------------
*/

$availableQuestions =
    count($questions);


$selectedMarksDisplay =
    0.00;


if (
    !empty($selectedIds)
) {

    $holders =
        implode(
            ',',
            array_fill(
                0,
                count($selectedIds),
                '?'
            )
        );


    try {

        $selectedMarksStatement =
            $conn->prepare("
                SELECT

                    COALESCE(
                        SUM(marks),
                        0
                    )

                FROM questions

                WHERE

                    id IN (
                        $holders
                    )

                    AND status = 'Active'
            ");


        $selectedMarksStatement->execute(
            $selectedIds
        );


        $selectedMarksDisplay =
            round(
                (float)(
                    $selectedMarksStatement
                    ->fetchColumn()
                ),
                2
            );

    } catch (Throwable $exception) {

        error_log(
            'Selected exam marks display failed: ' .
            $exception->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| READINESS
|--------------------------------------------------------------------------
*/

$ready =
    (
        $currentQuestionCount ===
        EXAM_REQUIRED_QUESTION_COUNT

        &&

        abs(
            $currentQuestionMarks -
            (float)$exam['total_marks']
        ) <=
        0.000001
    );


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$currentStatus =
    (string)$exam['status'];


$page_title =
    'Manage Exam Questions';


$page_css =
    'admin-subjects.css';


include 'includes/header.php';

?>

<style>

    .eq-page {
        max-width: 1550px;
        margin: 0 auto;
    }

    .eq-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .eq-kicker {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: #556b2f;
        font-size: .72rem;
        font-weight: 900;
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    .eq-heading h1 {
        margin: 7px 0 0;
        color: #5d4037;
        font-weight: 950;
        letter-spacing: -.035em;
    }

    .eq-heading p {
        margin: 6px 0 0;
        color: #746d68;
    }

    .eq-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .eq-action {
        min-height: 43px;
        padding: 0 14px;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        font-weight: 850;
    }

    .eq-action.back {
        background: #eee9df;
        color: #5d4037;
    }

    .eq-action.edit {
        background: #5d4037;
        color: #fff;
    }

    .eq-rule {
        display: flex;
        align-items: flex-start;
        gap: 13px;
        padding: 16px 17px;
        margin-bottom: 18px;
        border: 1px solid rgba(85,107,47,.15);
        border-radius: 15px;
        background: rgba(85,107,47,.07);
    }

    .eq-rule-icon {
        flex: 0 0 38px;
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        border-radius: 11px;
        background: rgba(85,107,47,.12);
        color: #556b2f;
    }

    .eq-rule strong {
        display: block;
        color: #5d4037;
        font-weight: 900;
    }

    .eq-rule span {
        display: block;
        margin-top: 3px;
        color: #746d68;
        font-size: .82rem;
        line-height: 1.55;
    }

    .eq-alert {
        padding: 14px 16px;
        margin-bottom: 17px;
        border-radius: 13px;
        line-height: 1.55;
        font-weight: 700;
    }

    .eq-alert.success {
        color: #486022;
        background: rgba(85,107,47,.09);
        border: 1px solid rgba(85,107,47,.14);
    }

    .eq-alert.error {
        color: #783030;
        background: rgba(168,50,50,.08);
        border: 1px solid rgba(168,50,50,.14);
    }

    .eq-stat-grid {
        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 19px;
    }

    .eq-stat {
        padding: 18px;
        border: 1px solid rgba(93,64,55,.08);
        border-radius: 18px;
        background: rgba(255,255,255,.82);
        box-shadow:
            0 14px 35px rgba(62,45,37,.07);
    }

    .eq-stat-label {
        color: #746d68;
        font-size: .74rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .eq-stat-value {
        margin-top: 6px;
        color: #5d4037;
        font-size: 1.55rem;
        font-weight: 950;
    }

    .eq-status {
        display: inline-flex;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 850;
    }

    .eq-status.ready {
        color: #556b2f;
        background: rgba(85,107,47,.12);
    }

    .eq-status.not-ready {
        color: #a83232;
        background: rgba(168,50,50,.10);
    }

    .eq-layout {
        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            minmax(350px, 400px);
        gap: 18px;
        align-items: start;
    }

    .eq-card {
        overflow: hidden;
        border: 1px solid rgba(93,64,55,.08);
        border-radius: 20px;
        background: rgba(255,255,255,.83);
        box-shadow:
            0 18px 45px rgba(62,45,37,.08);
    }

    .eq-card-head {
        padding: 19px 21px;
        border-bottom: 1px solid #eee7df;
    }

    .eq-card-head h2 {
        margin: 0;
        color: #5d4037;
        font-size: 1.04rem;
        font-weight: 900;
    }

    .eq-card-head p {
        margin: 5px 0 0;
        color: #746d68;
        font-size: .81rem;
    }

    .eq-card-body {
        padding: 19px;
    }

    .eq-toolbar {
        display: grid;
        grid-template-columns:
            1fr auto auto;
        gap: 9px;
        margin-bottom: 13px;
    }

    .eq-toolbar input,
    .eq-toolbar select {
        min-height: 43px;
        border: 1px solid #ddd3ca;
        border-radius: 11px;
        padding: 0 12px;
        outline: none;
        background: #fff;
    }

    .eq-question-list {
        max-height: 720px;
        overflow-y: auto;
        padding-right: 3px;
    }

    .eq-question {
        display: flex;
        align-items: flex-start;
        gap: 11px;
        padding: 13px;
        margin-bottom: 8px;
        border: 1px solid #ebe3dc;
        border-radius: 13px;
        background: #fff;
        cursor: pointer;
        transition:
            border-color .18s ease,
            transform .18s ease,
            box-shadow .18s ease;
    }

    .eq-question:hover {
        transform: translateY(-1px);
        border-color: #cdbfb4;
        box-shadow:
            0 7px 18px rgba(62,45,37,.06);
    }

    .eq-question.selected {
        border-color: rgba(85,107,47,.38);
        background: rgba(85,107,47,.035);
    }

    .eq-question-text {
        color: #3f3936;
        font-size: .86rem;
        line-height: 1.48;
    }

    .eq-question-meta {
        margin-top: 5px;
        color: #746d68;
        font-size: .72rem;
    }

    .eq-chip {
        display: inline-flex;
        padding: 4px 8px;
        margin-right: 4px;
        border-radius: 999px;
        background: #faf7f0;
        color: #5d4037;
        font-size: .68rem;
        font-weight: 800;
    }

    .eq-summary {
        padding: 15px;
        margin-bottom: 14px;
        border-radius: 14px;
        background: #faf7f0;
        border: 1px solid #ebe1d8;
    }

    .eq-summary-row {
        display: flex;
        justify-content: space-between;
        gap: 15px;
        padding: 5px 0;
        color: #746d68;
        font-size: .81rem;
    }

    .eq-summary-row strong {
        color: #5d4037;
        font-weight: 900;
    }

    .eq-progress {
        height: 8px;
        margin-top: 9px;
        overflow: hidden;
        border-radius: 999px;
        background: #e9e1d9;
    }

    .eq-progress-bar {
        height: 100%;
        width: 0%;
        border-radius: inherit;
        background: #556b2f;
        transition: width .2s ease;
    }

    .eq-btn {
        width: 100%;
        min-height: 48px;
        border: 0;
        border-radius: 12px;
        color: #fff;
        background: #5d4037;
        font-weight: 900;
    }

    .eq-btn:disabled {
        opacity: .48;
        cursor: not-allowed;
    }

    .eq-side-block {
        padding: 13px 0;
        border-bottom: 1px solid #eee7df;
    }

    .eq-side-block:last-child {
        border-bottom: 0;
    }

    .eq-side-label {
        color: #746d68;
        font-size: .73rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .eq-side-value {
        margin-top: 4px;
        color: #5d4037;
        font-weight: 900;
    }

    .eq-status-select {
        width: 100%;
        min-height: 43px;
        margin-top: 7px;
        border: 1px solid #ddd3ca;
        border-radius: 11px;
        padding: 0 11px;
        background: #fff;
    }

    @media (max-width: 1050px) {

        .eq-layout {
            grid-template-columns: 1fr;
        }

    }

    @media (max-width: 800px) {

        .eq-stat-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .eq-toolbar {
            grid-template-columns: 1fr;
        }

    }

    @media (max-width: 520px) {

        .eq-stat-grid {
            grid-template-columns: 1fr;
        }

    }

</style>


<div class="dashboard-wrapper">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">

        <?php include 'includes/navbar.php'; ?>

        <main class="dashboard-content">

            <div class="eq-page">

                <div class="eq-heading">

                    <div>

                        <span class="eq-kicker">

                            <i class="fa-solid fa-list-check"></i>

                            EXAM COMPLETION

                        </span>

                        <h1>
                            <?= exam_questions_escape(
                                $exam['title']
                            ) ?>
                        </h1>

                        <p>

                            <?= exam_questions_escape(
                                $exam['subject_name']
                                ?: 'General'
                            ) ?>

                            ·

                            Exam #<?= $examId ?>

                        </p>

                    </div>

                    <div class="eq-actions">

                        <a
                            href="exams.php"
                            class="eq-action back"
                        >

                            <i
                                class="fa-solid fa-arrow-left"
                            ></i>

                            Exams

                        </a>

                        <a
                            href="exams/edit.php?id=<?= $examId ?>"
                            class="eq-action edit"
                        >

                            <i
                                class="fa-solid fa-pen"
                            ></i>

                            Edit Exam

                        </a>

                    </div>

                </div>


                <div class="eq-rule">

                    <div class="eq-rule-icon">

                        <i
                            class="fa-solid fa-shield-check"
                        ></i>

                    </div>

                    <div>

                        <strong>
                            Exactly 50 Active Questions Required
                        </strong>

                        <span>
                            The exam can become ready only when exactly 50 Active
                            questions are assigned and their marks exactly equal
                            the exam total marks. Question changes are locked after
                            the first student attempt.
                        </span>

                    </div>

                </div>


                <?php if ($message !== ''): ?>

                    <div class="eq-alert success">

                        <i
                            class="fa-solid fa-circle-check me-1"
                        ></i>

                        <?= exam_questions_escape(
                            $message
                        ) ?>

                    </div>

                <?php endif; ?>


                <?php if ($error !== ''): ?>

                    <div class="eq-alert error">

                        <i
                            class="fa-solid fa-circle-exclamation me-1"
                        ></i>

                        <?= exam_questions_escape(
                            $error
                        ) ?>

                    </div>

                <?php endif; ?>


                <div class="eq-stat-grid">

                    <div class="eq-stat">

                        <div class="eq-stat-label">
                            Questions
                        </div>

                        <div class="eq-stat-value">
                            <?= $currentQuestionCount ?> / 50
                        </div>

                    </div>


                    <div class="eq-stat">

                        <div class="eq-stat-label">
                            Question Marks
                        </div>

                        <div class="eq-stat-value">
                            <?= number_format(
                                $currentQuestionMarks,
                                2
                            ) ?>
                        </div>

                    </div>


                    <div class="eq-stat">

                        <div class="eq-stat-label">
                            Exam Total
                        </div>

                        <div class="eq-stat-value">
                            <?= number_format(
                                (float)$exam['total_marks'],
                                2
                            ) ?>
                        </div>

                    </div>


                    <div class="eq-stat">

                        <div class="eq-stat-label">
                            Configuration
                        </div>

                        <div class="eq-stat-value">

                            <?php if ($ready): ?>

                                <span
                                    class="eq-status ready"
                                >
                                    READY
                                </span>

                            <?php else: ?>

                                <span
                                    class="eq-status not-ready"
                                >
                                    NOT READY
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <form
                    method="post"
                    id="examQuestionForm"
                >

                    <?= csrf_field() ?>


                    <div class="eq-layout">


                        <!-- =========================================
                             QUESTIONS
                        ========================================== -->

                        <section class="eq-card">

                            <div class="eq-card-head">

                                <h2>
                                    Select Questions
                                </h2>

                                <p>
                                    Active questions from
                                    <?= exam_questions_escape(
                                        $exam['subject_name']
                                    ) ?>
                                    only.
                                </p>

                            </div>


                            <div class="eq-card-body">


                                <div class="eq-toolbar">

                                    <input
                                        type="search"
                                        id="eqSearch"
                                        placeholder="Search question or topic..."
                                    >

                                    <select id="eqDifficulty">

                                        <option value="">
                                            All Levels
                                        </option>

                                        <option value="Easy">
                                            Easy
                                        </option>

                                        <option value="Medium">
                                            Medium
                                        </option>

                                        <option value="Hard">
                                            Hard
                                        </option>

                                    </select>

                                    <select id="eqTopic">

                                        <option value="">
                                            All Topics
                                        </option>

                                        <?php
                                        $topicOptions = [];

                                        foreach (
                                            $questions
                                            as $question
                                        ) {

                                            $topicId =
                                                (int)(
                                                    $question[
                                                        'topic_id'
                                                    ] ?? 0
                                                );

                                            $topicName =
                                                trim(
                                                    (string)(
                                                        $question[
                                                            'topic_name'
                                                        ]
                                                        ?? ''
                                                    )
                                                );

                                            if (
                                                $topicId > 0 &&
                                                $topicName !== ''
                                            ) {

                                                $topicOptions[
                                                    $topicId
                                                ] =
                                                    $topicName;
                                            }
                                        }

                                        asort(
                                            $topicOptions,
                                            SORT_NATURAL | SORT_FLAG_CASE
                                        );
                                        ?>

                                        <?php foreach (
                                            $topicOptions
                                            as $topicId =>
                                            $topicName
                                        ): ?>

                                            <option
                                                value="<?= (int)$topicId ?>"
                                            >

                                                <?= exam_questions_escape(
                                                    $topicName
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="eq-summary">

                                    <div class="eq-summary-row">

                                        <span>
                                            Selected
                                        </span>

                                        <strong
                                            id="eqSelectedCount"
                                        >
                                            0 / 50
                                        </strong>

                                    </div>

                                    <div class="eq-summary-row">

                                        <span>
                                            Selected Marks
                                        </span>

                                        <strong
                                            id="eqSelectedMarks"
                                        >
                                            0.00
                                        </strong>

                                    </div>

                                    <div class="eq-summary-row">

                                        <span>
                                            Target Marks
                                        </span>

                                        <strong>
                                            <?= number_format(
                                                (float)$exam['total_marks'],
                                                2
                                            ) ?>
                                        </strong>

                                    </div>

                                    <div class="eq-summary-row">

                                        <span>
                                            Available
                                        </span>

                                        <strong>
                                            <?= $availableQuestions ?>
                                        </strong>

                                    </div>


                                    <div class="eq-progress">

                                        <div
                                            class="eq-progress-bar"
                                            id="eqProgress"
                                        ></div>

                                    </div>

                                </div>


                                <div
                                    class="eq-question-list"
                                    id="eqQuestionList"
                                >

                                    <?php if (!$questions): ?>

                                        <div
                                            class="text-center py-5 text-muted"
                                        >

                                            <i
                                                class="fa-regular fa-circle-question d-block mb-3"
                                                style="font-size:38px;"
                                            ></i>

                                            No Active Questions are available
                                            for this subject.

                                        </div>

                                    <?php else: ?>

                                        <?php foreach (
                                            $questions
                                            as $question
                                        ): ?>

                                            <?php

                                            $questionId =
                                                (int)$question['id'];

                                            $topicId =
                                                (int)(
                                                    $question[
                                                        'topic_id'
                                                    ] ?? 0
                                                );

                                            $topicName =
                                                trim(
                                                    (string)(
                                                        $question[
                                                            'topic_name'
                                                        ]
                                                        ?? ''
                                                    )
                                                );

                                            $isSelected =
                                                in_array(
                                                    $questionId,
                                                    $selectedIds,
                                                    true
                                                );

                                            ?>

                                            <label
                                                class="
                                                    eq-question
                                                    <?= $isSelected
                                                        ? 'selected'
                                                        : ''
                                                    ?>
                                                "
                                                data-search="<?= exam_questions_escape(
                                                    strtolower(
                                                        (string)$question[
                                                            'question_text'
                                                        ]
                                                        . ' ' .
                                                        $topicName
                                                    )
                                                ) ?>"
                                                data-difficulty="<?= exam_questions_escape(
                                                    $question['difficulty']
                                                ) ?>"
                                                data-topic="<?= $topicId ?>"
                                            >

                                                <input
                                                    type="checkbox"
                                                    class="
                                                        form-check-input
                                                        mt-1
                                                        eq-question-checkbox
                                                    "
                                                    name="question_ids[]"
                                                    value="<?= $questionId ?>"
                                                    data-marks="<?= exam_questions_escape(
                                                        $question['marks']
                                                    ) ?>"
                                                    <?= $isSelected
                                                        ? 'checked'
                                                        : ''
                                                    ?>
                                                >


                                                <span>

                                                    <span
                                                        class="eq-question-text"
                                                    >

                                                        <?= exam_questions_escape(
                                                            $question[
                                                                'question_text'
                                                            ]
                                                        ) ?>

                                                    </span>


                                                    <span
                                                        class="eq-question-meta"
                                                    >

                                                        <span
                                                            class="eq-chip"
                                                        >

                                                            <?= exam_questions_escape(
                                                                $question[
                                                                    'question_type'
                                                                ]
                                                            ) ?>

                                                        </span>


                                                        <span
                                                            class="eq-chip"
                                                        >

                                                            <?= exam_questions_escape(
                                                                $question[
                                                                    'difficulty'
                                                                ]
                                                            ) ?>

                                                        </span>


                                                        <?php if (
                                                            $topicName !== ''
                                                        ): ?>

                                                            <span
                                                                class="eq-chip"
                                                            >

                                                                <?= exam_questions_escape(
                                                                    $topicName
                                                                ) ?>

                                                            </span>

                                                        <?php endif; ?>


                                                        <span
                                                            class="eq-chip"
                                                        >

                                                            <?= exam_questions_escape(
                                                                $question[
                                                                    'marks'
                                                                ]
                                                            ) ?>

                                                            marks

                                                        </span>

                                                    </span>

                                                </span>

                                            </label>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </section>


                        <!-- =========================================
                             CONTROL
                        ========================================== -->

                        <aside class="eq-card">

                            <div class="eq-card-head">

                                <h2>
                                    Exam Configuration
                                </h2>

                                <p>
                                    Final validation happens on the server.
                                </p>

                            </div>


                            <div class="eq-card-body">


                                <div class="eq-side-block">

                                    <div class="eq-side-label">
                                        Exam
                                    </div>

                                    <div class="eq-side-value">
                                        <?= exam_questions_escape(
                                            $exam['title']
                                        ) ?>
                                    </div>

                                </div>


                                <div class="eq-side-block">

                                    <div class="eq-side-label">
                                        Subject
                                    </div>

                                    <div class="eq-side-value">
                                        <?= exam_questions_escape(
                                            $exam['subject_name']
                                        ) ?>
                                    </div>

                                </div>


                                <div class="eq-side-block">

                                    <div class="eq-side-label">
                                        Teacher
                                    </div>

                                    <div class="eq-side-value">
                                        <?= exam_questions_escape(
                                            $exam['teacher_name']
                                            ?: 'Unassigned'
                                        ) ?>
                                    </div>

                                </div>


                                <div class="eq-side-block">

                                    <div class="eq-side-label">
                                        Attempts
                                    </div>

                                    <div class="eq-side-value">

                                        <?= $attemptCount ?>

                                        <?php if (
                                            $attemptCount > 0
                                        ): ?>

                                            <span class="text-danger">
                                                · Locked
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <div class="eq-side-block">

                                    <div class="eq-side-label">
                                        Current Status
                                    </div>

                                    <div class="eq-side-value">

                                        <?= exam_questions_escape(
                                            $currentStatus
                                        ) ?>

                                    </div>

                                </div>


                                <div class="eq-side-block">

                                    <div class="eq-side-label">
                                        Save Status
                                    </div>


                                    <select
                                        name="status"
                                        id="eqStatus"
                                        class="eq-status-select"
                                    >

                                        <?php if (
                                            $exam['exam_type']
                                            ===
                                            'Practice'
                                        ): ?>

                                            <option value="Active">
                                                Active
                                            </option>

                                        <?php else: ?>

                                            <option
                                                value="Scheduled"
                                                <?= $currentStatus ===
                                                    'Scheduled'
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                Scheduled
                                            </option>

                                            <option
                                                value="Live"
                                                <?= $currentStatus ===
                                                    'Live'
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                Live
                                            </option>

                                        <?php endif; ?>

                                    </select>


                                    <div
                                        class="mt-2 text-muted"
                                        style="font-size:.74rem;line-height:1.5;"
                                    >

                                        <?= $exam['exam_type'] === 'Practice'
                                            ? 'A valid Practice exam becomes Active.'
                                            : 'A valid Live exam becomes Scheduled or Live.'
                                        ?>

                                    </div>

                                </div>


                                <div class="eq-side-block">

                                    <div class="eq-side-label">
                                        Required
                                    </div>

                                    <div class="eq-side-value">
                                        Exactly 50 Questions
                                    </div>

                                </div>


                                <div class="eq-side-block">

                                    <div class="eq-side-label">
                                        Current Configuration
                                    </div>

                                    <div class="eq-side-value">

                                        <?php if (
                                            $ready
                                        ): ?>

                                            <span
                                                class="eq-status ready"
                                            >
                                                Valid

                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="eq-status not-ready"
                                            >
                                                Invalid

                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <button
                                    type="submit"
                                    id="eqSaveButton"
                                    class="eq-btn mt-3"
                                    <?= $attemptCount > 0
                                        ? 'disabled'
                                        : ''
                                    ?>
                                >

                                    <i
                                        class="fa-solid fa-floppy-disk me-1"
                                    ></i>

                                    Save 50 Questions

                                </button>


                                <?php if (
                                    $attemptCount > 0
                                ): ?>

                                    <div
                                        class="mt-2 text-danger"
                                        style="font-size:.74rem;line-height:1.5;"
                                    >

                                        This exam is locked because at least
                                        one student attempt exists.

                                    </div>

                                <?php endif; ?>

                            </div>

                        </aside>

                    </div>

                </form>

            </div>

        </main>

    </div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const checkboxes =
            Array.from(
                document.querySelectorAll(
                    '.eq-question-checkbox'
                )
            );


        const questionCards =
            Array.from(
                document.querySelectorAll(
                    '.eq-question'
                )
            );


        const selectedCount =
            document.getElementById(
                'eqSelectedCount'
            );


        const selectedMarks =
            document.getElementById(
                'eqSelectedMarks'
            );


        const targetMarks =
            Number(
                <?= json_encode(
                    round(
                        (float)$exam['total_marks'],
                        2
                    )
                ) ?>
            );


        const progress =
            document.getElementById(
                'eqProgress'
            );


        const saveButton =
            document.getElementById(
                'eqSaveButton'
            );


        const form =
            document.getElementById(
                'examQuestionForm'
            );


        const searchInput =
            document.getElementById(
                'eqSearch'
            );


        const difficulty =
            document.getElementById(
                'eqDifficulty'
            );


        const topic =
            document.getElementById(
                'eqTopic'
            );


        function updateSummary()
        {

            let count =
                0;


            let marks =
                0;


            checkboxes.forEach(
                function (
                    checkbox
                ) {

                    if (
                        checkbox.checked
                    ) {

                        count++;

                        marks += Number(
                            checkbox.dataset.marks
                            || 0
                        );
                    }

                }
            );


            marks =
                Number(
                    marks.toFixed(2)
                );


            if (
                selectedCount
            ) {

                selectedCount.textContent =
                    count +
                    ' / 50';
            }


            if (
                selectedMarks
            ) {

                selectedMarks.textContent =
                    marks.toFixed(2);
            }


            if (
                progress
            ) {

                const percentage =
                    Math.min(
                        100,
                        (
                            count /
                            50
                        ) * 100
                    );


                progress.style.width =
                    percentage +
                    '%';
            }


            const countValid =
                count === 50;


            const marksValid =
                Math.abs(
                    marks -
                    targetMarks
                ) <=
                0.000001;


            /*
             * The client only enables the button when the
             * final basic conditions look valid.
             *
             * Server validation remains authoritative.
             */

            if (
                saveButton
            ) {

                saveButton.disabled =
                    !(
                        countValid &&
                        marksValid
                    );
            }
        }


        checkboxes.forEach(
            function (
                checkbox
            ) {

                checkbox.addEventListener(
                    'change',
                    function () {

                        const checkedCount =
                            checkboxes.filter(
                                function (
                                    item
                                ) {

                                    return item.checked;
                                }
                            ).length;


                        if (
                            checkedCount > 50
                        ) {

                            checkbox.checked =
                                false;

                            alert(
                                'Exactly 50 questions are allowed per exam.'
                            );

                            return;
                        }


                        const card =
                            checkbox.closest(
                                '.eq-question'
                            );


                        if (
                            card
                        ) {

                            card.classList.toggle(
                                'selected',
                                checkbox.checked
                            );
                        }


                        updateSummary();
                    }
                );

            }
        );


        function filterQuestions()
        {

            const query =
                (
                    searchInput
                        ? searchInput.value
                        : ''
                )
                    .trim()
                    .toLowerCase();


            const level =
                difficulty
                    ? difficulty.value
                    : '';


            const selectedTopic =
                topic
                    ? topic.value
                    : '';


            questionCards.forEach(
                function (
                    card
                ) {

                    const text =
                        (
                            card.dataset.search
                            || ''
                        ).toLowerCase();


                    const cardDifficulty =
                        card.dataset.difficulty
                        || '';


                    const cardTopic =
                        card.dataset.topic
                        || '';


                    const searchMatch =
                        query === ''
                        ||
                        text.includes(
                            query
                        );


                    const difficultyMatch =
                        level === ''
                        ||
                        cardDifficulty ===
                            level;


                    const topicMatch =
                        selectedTopic === ''
                        ||
                        cardTopic ===
                            selectedTopic;


                    card.style.display =
                        (
                            searchMatch &&
                            difficultyMatch &&
                            topicMatch
                        )
                            ? ''
                            : 'none';
                }
            );
        }


        if (
            searchInput
        ) {

            searchInput.addEventListener(
                'input',
                filterQuestions
            );
        }


        if (
            difficulty
        ) {

            difficulty.addEventListener(
                'change',
                filterQuestions
            );
        }


        if (
            topic
        ) {

            topic.addEventListener(
                'change',
                filterQuestions
            );
        }


        if (
            form
        ) {

            form.addEventListener(
                'submit',
                function (
                    event
                ) {

                    const selected =
                        checkboxes.filter(
                            function (
                                checkbox
                            ) {

                                return checkbox.checked;
                            }
                        );


                    if (
                        selected.length !==
                        50
                    ) {

                        event.preventDefault();

                        alert(
                            'Exactly 50 questions must be selected.'
                        );

                        return;
                    }


                    let marks =
                        0;


                    selected.forEach(
                        function (
                            checkbox
                        ) {

                            marks +=
                                Number(
                                    checkbox.dataset.marks
                                    || 0
                                );
                        }
                    );


                    marks =
                        Number(
                            marks.toFixed(2)
                        );


                    if (
                        Math.abs(
                            marks -
                            targetMarks
                        ) >
                        0.000001
                    ) {

                        event.preventDefault();

                        alert(
                            'The selected 50 questions must total exactly ' +
                            targetMarks.toFixed(2) +
                            ' marks.'
                        );

                        return;
                    }


                    const confirmed =
                        confirm(
                            'Save exactly 50 questions for this exam?'
                        );


                    if (
                        !confirmed
                    ) {

                        event.preventDefault();
                    }

                }
            );
        }


        updateSummary();

    }
);

</script>


<?php include 'includes/footer.php'; ?>