<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

$page_title = "Edit Question";


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['admin_id'])
) {
    header("Location: ../../auth/login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function edit_question_escape(
    ?string $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['question_edit_csrf'])
) {

    $_SESSION['question_edit_csrf'] =
        bin2hex(
            random_bytes(32)
        );
}


$csrfToken =
    (string) $_SESSION[
        'question_edit_csrf'
    ];


/*
|--------------------------------------------------------------------------
| Question ID
|--------------------------------------------------------------------------
*/

$questionId =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );


if (
    $questionId === false ||
    $questionId === null ||
    $questionId <= 0
) {

    $_SESSION['error'] =
        "Invalid question.";

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load question
|--------------------------------------------------------------------------
|
| IMPORTANT:
| A question does NOT contain exam_id.
| The exam-question relationship is stored in exam_questions.
|
*/

try {

    $questionStatement =
        $conn->prepare("
            SELECT

                q.id,
                q.subject_id,
                q.created_by_teacher_id,

                q.question_type,
                q.question_text,

                q.option_a,
                q.option_b,
                q.option_c,
                q.option_d,

                q.correct_answer,

                q.marks,
                q.negative_marks,

                q.difficulty,
                q.status,

                q.created_at,

                eq.exam_id,
                eq.position,

                e.title AS exam_title,
                e.exam_type,

                e.subject_id AS exam_subject_id,
                e.required_question_count,
                e.total_marks,
                e.status AS exam_status,

                s.name AS subject_name,
                s.code AS subject_code,
                s.status AS subject_status

            FROM questions q

            INNER JOIN exam_questions eq
                ON eq.question_id = q.id

            INNER JOIN exams e
                ON e.id = eq.exam_id

            LEFT JOIN subjects s
                ON s.id = e.subject_id

            WHERE
                q.id = ?

            LIMIT 1
        ");


    $questionStatement->execute([
        $questionId
    ]);


    $question =
        $questionStatement->fetch(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        "Edit question load failed: " .
        $exception->getMessage()
    );


    $_SESSION['error'] =
        "Unable to load question.";

    header(
        "Location: index.php"
    );

    exit;
}


if (
    !$question
) {

    $_SESSION['error'] =
        "Question not found.";

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Form defaults
|--------------------------------------------------------------------------
*/

$examId =
    (int) $question[
        'exam_id'
    ];

$examTitle =
    (string) $question[
        'exam_title'
    ];

$subjectName =
    (string) (
        $question[
            'subject_name'
        ] ?? ''
    );

$subjectCode =
    (string) (
        $question[
            'subject_code'
        ] ?? ''
    );


$questionType =
    (string) (
        $question[
            'question_type'
        ] ?? 'MCQ'
    );


$questionText =
    (string) (
        $question[
            'question_text'
        ] ?? ''
    );


$optionA =
    (string) (
        $question[
            'option_a'
        ] ?? ''
    );


$optionB =
    (string) (
        $question[
            'option_b'
        ] ?? ''
    );


$optionC =
    (string) (
        $question[
            'option_c'
        ] ?? ''
    );


$optionD =
    (string) (
        $question[
            'option_d'
        ] ?? ''
    );


$correctAnswer =
    (string) (
        $question[
            'correct_answer'
        ] ?? ''
    );


$marks =
    (string) (
        $question[
            'marks'
        ] ?? '1'
    );


$negativeMarks =
    (string) (
        $question[
            'negative_marks'
        ] ?? '0'
    );


$difficulty =
    (string) (
        $question[
            'difficulty'
        ] ?? 'Medium'
    );


$status =
    (string) (
        $question[
            'status'
        ] ?? 'Active'
    );


$position =
    (int) (
        $question[
            'position'
        ] ?? 1
    );


$error =
    '';


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    /*
     * CSRF
     */
    $postedCsrf =
        (string) (
            $_POST[
                'csrf_token'
            ] ?? ''
        );


    if (
        $postedCsrf === '' ||
        !hash_equals(
            $csrfToken,
            $postedCsrf
        )
    ) {

        $error =
            "Security verification failed.";

    } else {

        /*
         * Read submitted values.
         */
        $questionType =
            trim(
                (string) (
                    $_POST[
                        'question_type'
                    ] ?? 'MCQ'
                )
            );


        $questionText =
            trim(
                (string) (
                    $_POST[
                        'question_text'
                    ] ?? ''
                )
            );


        $optionA =
            trim(
                (string) (
                    $_POST[
                        'option_a'
                    ] ?? ''
                )
            );


        $optionB =
            trim(
                (string) (
                    $_POST[
                        'option_b'
                    ] ?? ''
                )
            );


        $optionC =
            trim(
                (string) (
                    $_POST[
                        'option_c'
                    ] ?? ''
                )
            );


        $optionD =
            trim(
                (string) (
                    $_POST[
                        'option_d'
                    ] ?? ''
                )
            );


        $correctAnswer =
            strtoupper(
                trim(
                    (string) (
                        $_POST[
                            'correct_answer'
                        ] ?? ''
                    )
                )
            );


        $marks =
            trim(
                (string) (
                    $_POST[
                        'marks'
                    ] ?? '1'
                )
            );


        $negativeMarks =
            trim(
                (string) (
                    $_POST[
                        'negative_marks'
                    ] ?? '0'
                )
            );


        $difficulty =
            trim(
                (string) (
                    $_POST[
                        'difficulty'
                    ] ?? 'Medium'
                )
            );


        $status =
            trim(
                (string) (
                    $_POST[
                        'status'
                    ] ?? 'Active'
                )
            );


        $position =
            (int) (
                $_POST[
                    'position'
                ] ?? 1
            );


        /*
         * ----------------------------------------------
         * Validation
         * ----------------------------------------------
         */

        if (
            !in_array(
                $questionType,
                [
                    'MCQ',
                    'TrueFalse'
                ],
                true
            )
        ) {

            $error =
                "Invalid question type.";

        } elseif (
            $questionText === ''
        ) {

            $error =
                "Question text is required.";

        } elseif (
            $optionA === '' ||
            $optionB === ''
        ) {

            $error =
                "Option A and Option B are required.";

        } elseif (
            $questionType === 'MCQ' &&
            (
                $optionC === '' ||
                $optionD === ''
            )
        ) {

            $error =
                "MCQ requires all four options.";

        } elseif (
            !in_array(
                $correctAnswer,
                [
                    'A',
                    'B',
                    'C',
                    'D'
                ],
                true
            )
        ) {

            $error =
                "Please select a valid correct answer.";

        } elseif (
            $questionType === 'TrueFalse' &&
            !in_array(
                $correctAnswer,
                [
                    'A',
                    'B'
                ],
                true
            )
        ) {

            $error =
                "True/False questions can only use A or B as the correct answer.";

        } elseif (
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

            $error =
                "Invalid difficulty.";

        } elseif (
            !in_array(
                $status,
                [
                    'Active',
                    'Inactive'
                ],
                true
            )
        ) {

            $error =
                "Invalid question status.";

        } elseif (
            $position <= 0
        ) {

            $error =
                "Question position must be greater than zero.";

        } else {

            /*
             * Numeric validation.
             */
            $marksValue =
                filter_var(
                    $marks,
                    FILTER_VALIDATE_FLOAT
                );


            $negativeMarksValue =
                filter_var(
                    $negativeMarks,
                    FILTER_VALIDATE_FLOAT
                );


            if (
                $marksValue === false ||
                $marksValue <= 0
            ) {

                $error =
                    "Marks must be greater than zero.";

            } elseif (
                $negativeMarksValue === false ||
                $negativeMarksValue < 0
            ) {

                $error =
                    "Negative marks cannot be negative.";

            } elseif (
                $position >
                (int) $question[
                    'required_question_count'
                ]
            ) {

                $error =
                    "Question position cannot be greater than the required question count.";
            }


            /*
             * True / False normalization.
             */
            if (
                $error === '' &&
                $questionType === 'TrueFalse'
            ) {

                $optionA =
                    'True';

                $optionB =
                    'False';

                $optionC =
                    '';

                $optionD =
                    '';


                if (
                    !in_array(
                        $correctAnswer,
                        [
                            'A',
                            'B'
                        ],
                        true
                    )
                ) {

                    $error =
                        "True/False correct answer must be A or B.";
                }
            }
        }


        /*
         * ----------------------------------------------
         * Save
         * ----------------------------------------------
         */

        if (
            $error === ''
        ) {

            try {

                $conn->beginTransaction();


                /*
                 * Lock current relationship.
                 */
                $currentRelationshipStatement =
                    $conn->prepare("
                        SELECT

                            eq.exam_id,
                            eq.position,

                            e.subject_id,
                            e.required_question_count,
                            e.total_marks,
                            e.status AS exam_status,

                            s.status AS subject_status

                        FROM exam_questions eq

                        INNER JOIN exams e
                            ON e.id = eq.exam_id

                        LEFT JOIN subjects s
                            ON s.id = e.subject_id

                        WHERE
                            eq.question_id = ?

                        LIMIT 1

                        FOR UPDATE
                    ");


                $currentRelationshipStatement->execute([
                    $questionId
                ]);


                $currentRelationship =
                    $currentRelationshipStatement->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (
                    !$currentRelationship
                ) {

                    throw new RuntimeException(
                        "Question is not assigned to an examination."
                    );
                }


                $examId =
                    (int) $currentRelationship[
                        'exam_id'
                    ];


                /*
                 * A question's subject is controlled by its
                 * examination. Do not allow it to drift into
                 * another subject.
                 */
                if (
                    ($currentRelationship[
                        'subject_status'
                    ] ?? '') !== 'Active'
                ) {

                    throw new RuntimeException(
                        "The examination subject is inactive."
                    );
                }


                /*
                 * Exam cannot be cancelled.
                 */
                if (
                    ($currentRelationship[
                        'exam_status'
                    ] ?? '') === 'Cancelled'
                ) {

                    throw new RuntimeException(
                        "Cancelled examinations cannot be edited."
                    );
                }


                /*
                 * Verify position collision.
                 */
                $positionStatement =
                    $conn->prepare("
                        SELECT
                            eq.question_id

                        FROM exam_questions eq

                        WHERE
                            eq.exam_id = ?
                            AND eq.position = ?
                            AND eq.question_id <> ?

                        LIMIT 1

                        FOR UPDATE
                    ");


                $positionStatement->execute([

                    $examId,

                    $position,

                    $questionId
                ]);


                $positionOwner =
                    $positionStatement->fetchColumn();


                if (
                    $positionOwner
                ) {

                    throw new RuntimeException(
                        "Position " .
                        $position .
                        " is already used by another question in this exam."
                    );
                }


                /*
                 * Calculate subject from exam.
                 */
                $examSubjectId =
                    (int) $currentRelationship[
                        'subject_id'
                    ];


                /*
                 * Update the actual question.
                 *
                 * created_by_teacher_id is deliberately NOT
                 * changed.
                 */
                $updateQuestion =
                    $conn->prepare("
                        UPDATE questions

                        SET

                            subject_id = ?,

                            question_type = ?,
                            question_text = ?,

                            option_a = ?,
                            option_b = ?,
                            option_c = ?,
                            option_d = ?,

                            correct_answer = ?,

                            marks = ?,
                            negative_marks = ?,

                            difficulty = ?,
                            status = ?

                        WHERE
                            id = ?
                    ");


                $updateQuestion->execute([

                    $examSubjectId,

                    $questionType,

                    $questionText,

                    $optionA,

                    $optionB,

                    $optionC !== ''
                        ? $optionC
                        : null,

                    $optionD !== ''
                        ? $optionD
                        : null,

                    $correctAnswer,

                    $marksValue,

                    $negativeMarksValue,

                    $difficulty,

                    $status,

                    $questionId
                ]);


                /*
                 * Update exam ordering.
                 */
                $updatePosition =
                    $conn->prepare("
                        UPDATE exam_questions

                        SET
                            position = ?

                        WHERE
                            exam_id = ?
                            AND question_id = ?
                    ");


                $updatePosition->execute([

                    $position,

                    $examId,

                    $questionId
                ]);


                /*
                 * Final verification.
                 */
                $verifyStatement =
                    $conn->prepare("
                        SELECT
                            q.id,
                            q.subject_id,
                            q.question_type,
                            q.question_text,
                            q.correct_answer,
                            q.marks,
                            q.negative_marks,
                            q.difficulty,
                            q.status,

                            eq.position

                        FROM questions q

                        INNER JOIN exam_questions eq
                            ON eq.question_id = q.id

                        WHERE
                            q.id = ?
                            AND eq.exam_id = ?

                        LIMIT 1
                    ");


                $verifyStatement->execute([
                    $questionId,
                    $examId
                ]);


                $updatedQuestion =
                    $verifyStatement->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (
                    !$updatedQuestion
                ) {

                    throw new RuntimeException(
                        "Question update verification failed."
                    );
                }


                $conn->commit();


                $_SESSION['success'] =
                    "Question updated successfully.";


                header(
                    "Location: questions.php?exam_id=" .
                    $examId
                );

                exit;


            } catch (Throwable $exception) {

                if (
                    $conn->inTransaction()
                ) {

                    $conn->rollBack();
                }


                error_log(
                    "Edit question save failed: " .
                    $exception->getMessage()
                );


                $error =
                    $exception->getMessage();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Current progress
|--------------------------------------------------------------------------
*/

try {

    $progressStatement =
        $conn->prepare("
            SELECT

                COUNT(*) AS assigned_count,

                COALESCE(
                    SUM(
                        CASE
                            WHEN q.status = 'Active'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS active_count

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE eq.exam_id = ?
        ");


    $progressStatement->execute([
        $examId
    ]);


    $progress =
        $progressStatement->fetch(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    $progress = [
        'assigned_count' => 0,
        'active_count' => 0
    ];
}


$assignedCount =
    (int) $progress[
        'assigned_count'
    ];


$activeCount =
    (int) $progress[
        'active_count'
    ];


$requiredCount =
    (int) $question[
        'required_question_count'
    ];


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

include "../includes/header.php";

?>

<div class="dashboard-wrapper">

<?php include "../includes/sidebar.php"; ?>

<div class="main-content">

<?php include "../includes/navbar.php"; ?>

<div class="dashboard-content">


<!-- =====================================================
     HEADER
====================================================== -->

<div class="page-header">

    <div>

        <h1>
            Edit Question
        </h1>


        <p>

            Update the question while keeping it synchronized
            with the selected examination.

        </p>

    </div>


    <div
        style="
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        "
    >

        <a
            href="questions.php?exam_id=<?= $examId; ?>"
            class="btn-back"
        >

            <i
                class="fa-solid fa-arrow-left"
            ></i>

            Back to Questions

        </a>

    </div>

</div>


<!-- =====================================================
     ALERT
====================================================== -->

<?php if (
    $error !== ''
): ?>

    <div class="alert alert-danger">

        <?= edit_question_escape(
            $error
        ); ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     EXAM INFO
====================================================== -->

<div class="stats-grid">


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon exams">

                <i
                    class="fa-solid fa-file-lines"
                ></i>

            </div>


            <div>

                <h2
                    style="
                        font-size:18px;
                        line-height:1.35;
                    "
                >

                    <?= edit_question_escape(
                        $examTitle
                    ); ?>

                </h2>


                <p>
                    Examination
                </p>

            </div>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon students">

                <i
                    class="fa-solid fa-book-open"
                ></i>

            </div>


            <div>

                <h2
                    style="
                        font-size:16px;
                    "
                >

                    <?= edit_question_escape(
                        $subjectName
                    ); ?>

                </h2>


                <p>

                    <?= edit_question_escape(
                        $subjectCode
                    ); ?>

                </p>

            </div>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon teachers">

                <i
                    class="fa-solid fa-list-check"
                ></i>

            </div>


            <div>

                <h2>

                    <?= $activeCount; ?>

                    /

                    <?= $requiredCount; ?>

                </h2>


                <p>
                    Active Questions
                </p>

            </div>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon results">

                <i
                    class="fa-solid fa-arrow-down-1-9"
                ></i>

            </div>


            <div>

                <h2>
                    #<?= $position; ?>
                </h2>


                <p>
                    Question Position
                </p>

            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     FORM
====================================================== -->

<div class="form-card">

<form
    method="POST"
    autocomplete="off"
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= edit_question_escape(
        $csrfToken
    ); ?>"
>


<input
    type="hidden"
    name="exam_id"
    value="<?= $examId; ?>"
>


<div class="form-grid">


    <!-- =================================================
         EXAM
    ================================================== -->

    <div class="form-group">

        <label>
            Examination
        </label>


        <input
            type="text"
            class="form-control"
            value="<?= edit_question_escape(
                $examTitle
            ); ?>"
            readonly
        >

    </div>


    <!-- =================================================
         SUBJECT
    ================================================== -->

    <div class="form-group">

        <label>
            Subject
        </label>


        <input
            type="text"
            class="form-control"
            value="<?= edit_question_escape(
                $subjectName
                . (
                    $subjectCode !== ''
                        ? ' (' .
                          $subjectCode .
                          ')'
                        : ''
                )
            ); ?>"
            readonly
        >

    </div>


    <!-- =================================================
         QUESTION TYPE
    ================================================== -->

    <div class="form-group">

        <label>

            Question Type

            <span class="text-danger">
                *
            </span>

        </label>


        <select
            name="question_type"
            id="questionType"
            required
        >

            <option
                value="MCQ"
                <?= $questionType === 'MCQ'
                    ? 'selected'
                    : ''
                ?>
            >

                MCQ

            </option>


            <option
                value="TrueFalse"
                <?= $questionType === 'TrueFalse'
                    ? 'selected'
                    : ''
                ?>
            >

                True / False

            </option>

        </select>

    </div>


    <!-- =================================================
         DIFFICULTY
    ================================================== -->

    <div class="form-group">

        <label>

            Difficulty

            <span class="text-danger">
                *
            </span>

        </label>


        <select
            name="difficulty"
            required
        >

            <option
                value="Easy"
                <?= $difficulty === 'Easy'
                    ? 'selected'
                    : ''
                ?>
            >
                Easy
            </option>


            <option
                value="Medium"
                <?= $difficulty === 'Medium'
                    ? 'selected'
                    : ''
                ?>
            >
                Medium
            </option>


            <option
                value="Hard"
                <?= $difficulty === 'Hard'
                    ? 'selected'
                    : ''
                ?>
            >
                Hard
            </option>

        </select>

    </div>


    <!-- =================================================
         QUESTION
    ================================================== -->

    <div class="form-group full-width">

        <label>

            Question Text

            <span class="text-danger">
                *
            </span>

        </label>


        <textarea
            name="question_text"
            rows="6"
            maxlength="10000"
            required
            placeholder="Enter the question..."
        ><?= edit_question_escape(
            $questionText
        ); ?></textarea>

    </div>


    <!-- =================================================
         OPTION A
    ================================================== -->

    <div class="form-group">

        <label>

            Option A

            <span class="text-danger">
                *
            </span>

        </label>


        <input
            type="text"
            name="option_a"
            id="optionA"
            value="<?= edit_question_escape(
                $optionA
            ); ?>"
            maxlength="500"
            required
        >

    </div>


    <!-- =================================================
         OPTION B
    ================================================== -->

    <div class="form-group">

        <label>

            Option B

            <span class="text-danger">
                *
            </span>

        </label>


        <input
            type="text"
            name="option_b"
            id="optionB"
            value="<?= edit_question_escape(
                $optionB
            ); ?>"
            maxlength="500"
            required
        >

    </div>


    <!-- =================================================
         OPTION C
    ================================================== -->

    <div
        class="form-group"
        id="optionCGroup"
    >

        <label>

            Option C

            <span class="text-danger">
                *
            </span>

        </label>


        <input
            type="text"
            name="option_c"
            id="optionC"
            value="<?= edit_question_escape(
                $optionC
            ); ?>"
            maxlength="500"
        >

    </div>


    <!-- =================================================
         OPTION D
    ================================================== -->

    <div
        class="form-group"
        id="optionDGroup"
    >

        <label>

            Option D

            <span class="text-danger">
                *
            </span>

        </label>


        <input
            type="text"
            name="option_d"
            id="optionD"
            value="<?= edit_question_escape(
                $optionD
            ); ?>"
            maxlength="500"
        >

    </div>


    <!-- =================================================
         CORRECT ANSWER
    ================================================== -->

    <div class="form-group">

        <label>

            Correct Answer

            <span class="text-danger">
                *
            </span>

        </label>


        <select
            name="correct_answer"
            id="correctAnswer"
            required
        >

            <option value="">
                Select correct answer
            </option>


            <option
                value="A"
                <?= $correctAnswer === 'A'
                    ? 'selected'
                    : ''
                ?>
            >
                Option A
            </option>


            <option
                value="B"
                <?= $correctAnswer === 'B'
                    ? 'selected'
                    : ''
                ?>
            >
                Option B
            </option>


            <option
                value="C"
                <?= $correctAnswer === 'C'
                    ? 'selected'
                    : ''
                ?>
            >
                Option C
            </option>


            <option
                value="D"
                <?= $correctAnswer === 'D'
                    ? 'selected'
                    : ''
                ?>
            >
                Option D
            </option>

        </select>

    </div>


    <!-- =================================================
         MARKS
    ================================================== -->

    <div class="form-group">

        <label>

            Marks

            <span class="text-danger">
                *
            </span>

        </label>


        <input
            type="number"
            name="marks"
            min="0.01"
            step="0.01"
            value="<?= edit_question_escape(
                $marks
            ); ?>"
            required
        >

    </div>


    <!-- =================================================
         NEGATIVE MARKS
    ================================================== -->

    <div class="form-group">

        <label>
            Negative Marks
        </label>


        <input
            type="number"
            name="negative_marks"
            min="0"
            step="0.01"
            value="<?= edit_question_escape(
                $negativeMarks
            ); ?>"
        >

    </div>


    <!-- =================================================
         POSITION
    ================================================== -->

    <div class="form-group">

        <label>

            Question Position

            <span class="text-danger">
                *
            </span>

        </label>


        <input
            type="number"
            name="position"
            min="1"
            max="<?= $requiredCount; ?>"
            value="<?= $position; ?>"
            required
        >


        <small
            style="
                display:block;
                margin-top:6px;
                color:#817970;
                font-size:11px;
            "
        >

            Position must be between 1 and
            <?= $requiredCount; ?>.

        </small>

    </div>


    <!-- =================================================
         STATUS
    ================================================== -->

    <div class="form-group">

        <label>

            Status

            <span class="text-danger">
                *
            </span>

        </label>


        <select
            name="status"
            required
        >

            <option
                value="Active"
                <?= $status === 'Active'
                    ? 'selected'
                    : ''
                ?>
            >

                Active

            </option>


            <option
                value="Inactive"
                <?= $status === 'Inactive'
                    ? 'selected'
                    : ''
                ?>
            >

                Inactive

            </option>

        </select>

    </div>


</div>


<!-- =====================================================
     INFORMATION BOX
====================================================== -->

<div
    style="
        margin-top:24px;
        padding:15px 17px;
        border:1px solid #dde5cf;
        border-radius:12px;
        background:#f1f5e9;
        color:#617248;
        font-size:12px;
        line-height:1.7;
    "
>

    <strong>

        <i
            class="
                fa-solid
                fa-circle-info
            "
        ></i>

        Question synchronization

    </strong>


    <div
        style="
            margin-top:4px;
        "
    >

        This question remains assigned to

        <strong>

            <?= edit_question_escape(
                $examTitle
            ); ?>

        </strong>

        and keeps the examination's subject.
        Changing the position only changes its order inside
        the examination.

    </div>

</div>


<!-- =====================================================
     ACTIONS
====================================================== -->

<div
    class="form-actions"
    style="
        margin-top:22px;
    "
>

    <button
        type="submit"
        class="btn-save"
    >

        <i
            class="
                fa-solid
                fa-floppy-disk
            "
        ></i>

        Update Question

    </button>


    <a
        href="questions.php?exam_id=<?= $examId; ?>"
        class="btn-back"
    >

        <i
            class="
                fa-solid
                fa-arrow-left
            "
        ></i>

        Cancel

    </a>

</div>


</form>

</div>


</div>

</div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const questionType =
            document.getElementById(
                'questionType'
            );


        const optionA =
            document.getElementById(
                'optionA'
            );


        const optionB =
            document.getElementById(
                'optionB'
            );


        const optionC =
            document.getElementById(
                'optionC'
            );


        const optionD =
            document.getElementById(
                'optionD'
            );


        const optionCGroup =
            document.getElementById(
                'optionCGroup'
            );


        const optionDGroup =
            document.getElementById(
                'optionDGroup'
            );


        const correctAnswer =
            document.getElementById(
                'correctAnswer'
            );


        function updateQuestionType() {

            const isTrueFalse =
                questionType.value ===
                'TrueFalse';


            if (
                isTrueFalse
            ) {

                optionA.value =
                    'True';

                optionB.value =
                    'False';

                optionC.value =
                    '';

                optionD.value =
                    '';


                optionA.readOnly =
                    true;

                optionB.readOnly =
                    true;

                optionC.disabled =
                    true;

                optionD.disabled =
                    true;


                optionCGroup.style.display =
                    'none';

                optionDGroup.style.display =
                    'none';


                Array.from(
                    correctAnswer.options
                ).forEach(
                    function (
                        option
                    ) {

                        option.hidden =
                            (
                                option.value ===
                                'C' ||
                                option.value ===
                                'D'
                            );
                    }
                );


                if (
                    correctAnswer.value ===
                    'C' ||
                    correctAnswer.value ===
                    'D'
                ) {

                    correctAnswer.value =
                        '';
                }


            } else {

                optionA.readOnly =
                    false;

                optionB.readOnly =
                    false;

                optionC.disabled =
                    false;

                optionD.disabled =
                    false;


                optionCGroup.style.display =
                    '';

                optionDGroup.style.display =
                    '';


                Array.from(
                    correctAnswer.options
                ).forEach(
                    function (
                        option
                    ) {

                        option.hidden =
                            false;
                    }
                );
            }

        }


        questionType.addEventListener(
            'change',
            updateQuestionType
        );


        updateQuestionType();

    }
);

</script>


<?php include "../includes/footer.php"; ?>