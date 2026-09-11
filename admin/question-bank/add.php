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

function question_add_escape(
    ?string $value
): string {

    return htmlspecialchars(
        (string)$value,
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
    empty($_SESSION['question_add_csrf'])
) {

    $_SESSION['question_add_csrf'] =
        bin2hex(
            random_bytes(32)
        );
}


$csrfToken =
    (string)$_SESSION['question_add_csrf'];


/*
|--------------------------------------------------------------------------
| Exam ID
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
            INPUT_POST,
            'exam_id',
            FILTER_VALIDATE_INT
        );
}


if (
    $examId === false ||
    $examId === null ||
    $examId <= 0
) {

    $_SESSION['error'] =
        "Invalid examination.";

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Form values
|--------------------------------------------------------------------------
*/

$questionText =
    '';

$questionType =
    'MCQ';

$optionA =
    '';

$optionB =
    '';

$optionC =
    '';

$optionD =
    '';

$correctAnswer =
    '';

$marks =
    '1';

$negativeMarks =
    '0';

$difficulty =
    'Medium';

$status =
    'Active';

$error =
    '';

$success =
    '';


/*
|--------------------------------------------------------------------------
| Load exam
|--------------------------------------------------------------------------
*/

try {

    $examStatement =
        $conn->prepare("
            SELECT

                e.id,
                e.title,
                e.description,
                e.exam_type,

                e.subject_id,
                e.required_question_count,
                e.total_marks,
                e.passing_marks,

                e.status,

                s.name AS subject_name,
                s.code AS subject_code,
                s.status AS subject_status

            FROM exams e

            LEFT JOIN subjects s
                ON s.id = e.subject_id

            WHERE e.id = ?

            LIMIT 1
        ");


    $examStatement->execute([
        $examId
    ]);


    $exam =
        $examStatement->fetch(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        "Question add exam query failed: " .
        $exception->getMessage()
    );

    exit(
        "Unable to load examination."
    );
}


if (
    !$exam
) {

    $_SESSION['error'] =
        "Examination not found.";

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Current assigned question count
|--------------------------------------------------------------------------
*/

try {

    $countStatement =
        $conn->prepare("
            SELECT
                COUNT(*)

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE
                eq.exam_id = ?

                AND q.status = 'Active'
        ");


    $countStatement->execute([
        $examId
    ]);


    $assignedQuestionCount =
        (int)$countStatement->fetchColumn();

} catch (Throwable $exception) {

    error_log(
        "Question add count query failed: " .
        $exception->getMessage()
    );

    $assignedQuestionCount =
        0;
}


$requiredQuestionCount =
    (int)$exam[
        'required_question_count'
    ];


$remainingQuestionCount =
    max(
        0,
        $requiredQuestionCount -
        $assignedQuestionCount
    );


$limitReached =
    (
        $requiredQuestionCount > 0 &&
        $assignedQuestionCount >=
        $requiredQuestionCount
    );


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
        (string)(
            $_POST['csrf_token']
            ?? ''
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
         * Read form.
         */
        $questionText =
            trim(
                (string)(
                    $_POST['question_text']
                    ?? ''
                )
            );


        $questionType =
            trim(
                (string)(
                    $_POST['question_type']
                    ?? 'MCQ'
                )
            );


        $optionA =
            trim(
                (string)(
                    $_POST['option_a']
                    ?? ''
                )
            );


        $optionB =
            trim(
                (string)(
                    $_POST['option_b']
                    ?? ''
                )
            );


        $optionC =
            trim(
                (string)(
                    $_POST['option_c']
                    ?? ''
                )
            );


        $optionD =
            trim(
                (string)(
                    $_POST['option_d']
                    ?? ''
                )
            );


        $correctAnswer =
            strtoupper(
                trim(
                    (string)(
                        $_POST['correct_answer']
                        ?? ''
                    )
                )
            );


        $marks =
            trim(
                (string)(
                    $_POST['marks']
                    ?? '1'
                )
            );


        $negativeMarks =
            trim(
                (string)(
                    $_POST['negative_marks']
                    ?? '0'
                )
            );


        $difficulty =
            trim(
                (string)(
                    $_POST['difficulty']
                    ?? 'Medium'
                )
            );


        $status =
            trim(
                (string)(
                    $_POST['status']
                    ?? 'Active'
                )
            );


        /*
         * -----------------------------------------------
         * Validation
         * -----------------------------------------------
         */

        if (
            $limitReached
        ) {

            $error =
                "Question limit reached. This exam requires " .
                $requiredQuestionCount .
                " questions.";

        } elseif (
            empty($exam['subject_id'])
        ) {

            $error =
                "This examination does not have a subject assigned.";

        } elseif (
            ($exam['subject_status'] ?? '') !== 'Active'
        ) {

            $error =
                "The subject assigned to this examination is inactive.";

        } elseif (
            $exam['status'] === 'Cancelled'
        ) {

            $error =
                "Cancelled examinations cannot receive questions.";

        } elseif (
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

        } else {

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
                $difficulty !== '' &&
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

            } else {

                /*
                 * True/False uses A/B internally.
                 */
                if (
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
             * -------------------------------------------
             * Save
             * -------------------------------------------
             */

            if (
                $error === ''
            ) {

                try {

                    $conn->beginTransaction();


                    /*
                     * Re-check the limit inside the transaction.
                     */
                    $finalCountStatement =
                        $conn->prepare("
                            SELECT
                                COUNT(*)

                            FROM exam_questions eq

                            INNER JOIN questions q
                                ON q.id = eq.question_id

                            WHERE
                                eq.exam_id = ?

                                AND q.status = 'Active'
                        ");


                    $finalCountStatement->execute([
                        $examId
                    ]);


                    $finalAssignedCount =
                        (int)$finalCountStatement->fetchColumn();


                    if (
                        $finalAssignedCount >=
                        $requiredQuestionCount
                    ) {

                        throw new RuntimeException(
                            "The question limit was reached before saving."
                        );
                    }


                    /*
                     * Duplicate question check.
                     *
                     * Prevent the same exact question text from being
                     * assigned twice to the same exam.
                     */
                    $duplicateStatement =
                        $conn->prepare("
                            SELECT
                                q.id

                            FROM exam_questions eq

                            INNER JOIN questions q
                                ON q.id = eq.question_id

                            WHERE
                                eq.exam_id = ?

                                AND q.question_text = ?

                            LIMIT 1
                        ");


                    $duplicateStatement->execute([
                        $examId,
                        $questionText
                    ]);


                    $duplicateQuestionId =
                        $duplicateStatement->fetchColumn();


                    if (
                        $duplicateQuestionId
                    ) {

                        throw new RuntimeException(
                            "This question already exists in this examination."
                        );
                    }


                    /*
                     * Determine next position.
                     */
                    $positionStatement =
                        $conn->prepare("
                            SELECT
                                COALESCE(
                                    MAX(position),
                                    0
                                ) + 1

                            FROM exam_questions

                            WHERE exam_id = ?
                        ");


                    $positionStatement->execute([
                        $examId
                    ]);


                    $position =
                        (int)$positionStatement->fetchColumn();


                    /*
                     * Question belongs to the same subject as the exam.
                     *
                     * Admin-created questions use NULL for
                     * created_by_teacher_id because the current
                     * questions table does not contain an admin
                     * creator column.
                     */
                    $questionInsert =
                        $conn->prepare("
                            INSERT INTO questions
                            (
                                subject_id,
                                created_by_teacher_id,

                                question_type,
                                question_text,

                                option_a,
                                option_b,
                                option_c,
                                option_d,

                                correct_answer,

                                marks,
                                negative_marks,

                                difficulty,
                                status
                            )
                            VALUES
                            (
                                ?,
                                NULL,

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
                            )
                        ");


                    $questionInsert->execute([

                        (int)$exam[
                            'subject_id'
                        ],

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

                        $status
                    ]);


                    $questionId =
                        (int)$conn->lastInsertId();


                    if (
                        $questionId <= 0
                    ) {

                        throw new RuntimeException(
                            "Unable to create the question."
                        );
                    }


                    /*
                     * Assign question to the exam.
                     */
                    $assignmentInsert =
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


                    $assignmentInsert->execute([

                        $examId,

                        $questionId,

                        $position
                    ]);


                    $conn->commit();


                    $_SESSION['success'] =
                        "Question added successfully.";


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
                        "Question add save failed: " .
                        $exception->getMessage()
                    );


                    $error =
                        $exception->getMessage();
                }
            }
        }
    }
}


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
                Add Question
            </h1>

            <p>
                Add a new question to this examination.
            </p>

        </div>


        <a
            href="questions.php?exam_id=<?= $examId; ?>"
            class="btn-add"
        >

            <i
                class="fa-solid fa-arrow-left"
            ></i>

            Back

        </a>

    </div>


    <!-- =====================================================
         ALERT
    ====================================================== -->

    <?php if (
        $error !== ''
    ): ?>

        <div class="alert alert-danger">

            <?= question_add_escape(
                $error
            ); ?>

        </div>

    <?php endif; ?>


    <?php if (
        $limitReached
    ): ?>

        <div class="alert alert-warning">

            <strong>
                Question limit reached.
            </strong>

            <br>

            This examination already has

            <?= $assignedQuestionCount; ?>

            active questions out of

            <?= $requiredQuestionCount; ?>

            required questions.

        </div>

    <?php endif; ?>


    <!-- =====================================================
         EXAM SUMMARY
    ====================================================== -->

    <div
        class="stats-grid"
        style="
            margin-bottom:20px;
        "
    >

        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-icon exams">

                    <i
                        class="fa-solid fa-file-lines"
                    ></i>

                </div>


                <div>

                    <h2>
                        <?= $assignedQuestionCount; ?>
                    </h2>

                    <p>
                        Active Questions
                    </p>

                </div>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-icon students">

                    <i
                        class="fa-solid fa-list-check"
                    ></i>

                </div>


                <div>

                    <h2>
                        <?= $requiredQuestionCount; ?>
                    </h2>

                    <p>
                        Required Questions
                    </p>

                </div>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-icon materials">

                    <i
                        class="fa-solid fa-hourglass-half"
                    ></i>

                </div>


                <div>

                    <h2>
                        <?= $remainingQuestionCount; ?>
                    </h2>

                    <p>
                        Remaining
                    </p>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         EXAM CARD
    ====================================================== -->

    <div class="form-card">

        <div
            style="
                margin-bottom:25px;
                padding:18px;
                border:1px solid #e5e0d8;
                border-radius:14px;
                background:#faf9f5;
            "
        >

            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    gap:20px;
                    flex-wrap:wrap;
                "
            >

                <div>

                    <small
                        style="
                            display:block;
                            color:#7c746d;
                            font-size:11px;
                            margin-bottom:5px;
                        "
                    >
                        EXAMINATION
                    </small>


                    <h2
                        style="
                            margin:0;
                            color:#3e2723;
                            font-size:22px;
                        "
                    >

                        <?= question_add_escape(
                            $exam['title']
                        ); ?>

                    </h2>


                    <p
                        style="
                            margin:7px 0 0;
                            color:#756e66;
                            font-size:13px;
                        "
                    >

                        Subject:

                        <strong>

                            <?= question_add_escape(
                                $exam['subject_name']
                                ?: 'No subject'
                            ); ?>

                        </strong>


                        <?php if (
                            !empty(
                                $exam['subject_code']
                            )
                        ): ?>

                            •
                            <?= question_add_escape(
                                $exam['subject_code']
                            ); ?>

                        <?php endif; ?>

                    </p>

                </div>


                <div
                    style="
                        text-align:right;
                    "
                >

                    <small
                        style="
                            display:block;
                            color:#7c746d;
                            font-size:11px;
                        "
                    >
                        PROGRESS
                    </small>


                    <strong
                        style="
                            display:block;
                            margin-top:4px;
                            color:#556b2f;
                            font-size:20px;
                        "
                    >

                        <?= $assignedQuestionCount; ?>

                        /

                        <?= $requiredQuestionCount; ?>

                    </strong>

                </div>

            </div>


            <?php

            $progressPercentage =
                $requiredQuestionCount > 0
                    ? min(
                        100,
                        round(
                            (
                                $assignedQuestionCount /
                                $requiredQuestionCount
                            ) * 100
                        )
                    )
                    : 0;

            ?>


            <div
                style="
                    height:7px;
                    margin-top:16px;
                    overflow:hidden;
                    border-radius:99px;
                    background:#e7e4dc;
                "
            >

                <div
                    style="
                        width:<?= $progressPercentage; ?>%;
                        height:100%;
                        border-radius:99px;
                        background:#556b2f;
                    "
                ></div>

            </div>

        </div>


        <!-- =================================================
             FORM
        ================================================== -->

        <form
            method="POST"
            autocomplete="off"
        >

            <input
                type="hidden"
                name="exam_id"
                value="<?= $examId; ?>"
            >


            <input
                type="hidden"
                name="csrf_token"
                value="<?= question_add_escape(
                    $csrfToken
                ); ?>"
            >


            <div class="form-grid">


                <!-- QUESTION TYPE -->

                <div class="form-group">

                    <label>
                        Question Type
                        <span class="text-danger">*</span>
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


                <!-- DIFFICULTY -->

                <div class="form-group">

                    <label>
                        Difficulty
                        <span class="text-danger">*</span>
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


                <!-- QUESTION -->

                <div class="form-group full-width">

                    <label>

                        Question

                        <span class="text-danger">*</span>

                    </label>


                    <textarea
                        name="question_text"
                        rows="5"
                        required
                        maxlength="10000"
                        placeholder="Enter the question here..."
                    ><?= question_add_escape(
                        $questionText
                    ); ?></textarea>

                </div>


                <!-- OPTION A -->

                <div class="form-group">

                    <label>

                        Option A

                        <span class="text-danger">*</span>

                    </label>


                    <input
                        type="text"
                        name="option_a"
                        id="optionA"
                        value="<?= question_add_escape(
                            $optionA
                        ); ?>"
                        maxlength="500"
                        required
                    >

                </div>


                <!-- OPTION B -->

                <div class="form-group">

                    <label>

                        Option B

                        <span class="text-danger">*</span>

                    </label>


                    <input
                        type="text"
                        name="option_b"
                        id="optionB"
                        value="<?= question_add_escape(
                            $optionB
                        ); ?>"
                        maxlength="500"
                        required
                    >

                </div>


                <!-- OPTION C -->

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
                        value="<?= question_add_escape(
                            $optionC
                        ); ?>"
                        maxlength="500"
                    >

                </div>


                <!-- OPTION D -->

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
                        value="<?= question_add_escape(
                            $optionD
                        ); ?>"
                        maxlength="500"
                    >

                </div>


                <!-- CORRECT -->

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
                            A
                        </option>


                        <option
                            value="B"
                            <?= $correctAnswer === 'B'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            B
                        </option>


                        <option
                            value="C"
                            <?= $correctAnswer === 'C'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            C
                        </option>


                        <option
                            value="D"
                            <?= $correctAnswer === 'D'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            D
                        </option>

                    </select>

                </div>


                <!-- MARKS -->

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
                        value="<?= question_add_escape(
                            $marks
                        ); ?>"
                        min="0.01"
                        step="0.01"
                        required
                    >

                </div>


                <!-- NEGATIVE -->

                <div class="form-group">

                    <label>
                        Negative Marks
                    </label>


                    <input
                        type="number"
                        name="negative_marks"
                        value="<?= question_add_escape(
                            $negativeMarks
                        ); ?>"
                        min="0"
                        step="0.01"
                    >

                </div>


                <!-- STATUS -->

                <div class="form-group">

                    <label>
                        Status
                    </label>


                    <select
                        name="status"
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


            <!-- =================================================
                 FORM ACTIONS
            ================================================== -->

            <div
                style="
                    display:flex;
                    justify-content:flex-end;
                    gap:10px;
                    flex-wrap:wrap;
                    margin-top:25px;
                    padding-top:20px;
                    border-top:1px solid #ebe7df;
                "
            >

                <a
                    href="questions.php?exam_id=<?= $examId; ?>"
                    class="btn-back"
                >

                    Cancel

                </a>


                <button
                    type="submit"
                    class="btn-add"
                    <?= $limitReached
                        ? 'disabled'
                        : ''
                    ?>
                >

                    <i
                        class="fa-solid fa-floppy-disk"
                    ></i>

                    Add Question

                </button>

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

        const type =
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
                type.value ===
                'TrueFalse';


            if (
                isTrueFalse
            ) {

                optionC.value =
                    '';

                optionD.value =
                    '';

                optionC.disabled =
                    true;

                optionD.disabled =
                    true;


                optionCGroup.style.display =
                    'none';

                optionDGroup.style.display =
                    'none';


                optionA.value =
                    'True';

                optionB.value =
                    'False';

                optionA.readOnly =
                    true;

                optionB.readOnly =
                    true;


                correctAnswer
                    .querySelectorAll(
                        'option'
                    )
                    .forEach(
                        function (
                            option
                        ) {

                            if (
                                option.value ===
                                'C' ||
                                option.value ===
                                'D'
                            ) {

                                option.hidden =
                                    true;
                            }
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

                optionC.disabled =
                    false;

                optionD.disabled =
                    false;

                optionA.readOnly =
                    false;

                optionB.readOnly =
                    false;


                optionCGroup.style.display =
                    '';

                optionDGroup.style.display =
                    '';


                correctAnswer
                    .querySelectorAll(
                        'option'
                    )
                    .forEach(
                        function (
                            option
                        ) {

                            option.hidden =
                                false;
                        }
                    );
            }

        }


        type.addEventListener(
            'change',
            updateQuestionType
        );


        updateQuestionType();

    }
);

</script>

<?php include "../includes/footer.php"; ?>