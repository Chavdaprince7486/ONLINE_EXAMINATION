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

    header(
        "Location: ../../auth/login.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function delete_question_escape(
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
| CSRF
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['question_delete_csrf'])
) {

    $_SESSION['question_delete_csrf'] =
        bin2hex(
            random_bytes(32)
        );
}


$csrfToken =
    (string)$_SESSION[
        'question_delete_csrf'
    ];


/*
|--------------------------------------------------------------------------
| Load question relationship
|--------------------------------------------------------------------------
|
| We MUST use exam_questions because questions does not contain
| exam_id.
|
*/

try {

    $questionStatement =
        $conn->prepare("
            SELECT

                q.id,

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
                e.required_question_count,
                e.status AS exam_status,

                s.name AS subject_name,
                s.code AS subject_code

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
        "Delete question load failed: " .
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

    /*
     * A question may theoretically exist without an exam
     * relationship. Handle it safely.
     */
    try {

        $orphanStatement =
            $conn->prepare("
                SELECT

                    id,
                    question_type,
                    question_text,
                    status,
                    created_at

                FROM questions

                WHERE id = ?

                LIMIT 1
            ");


        $orphanStatement->execute([
            $questionId
        ]);


        $orphanQuestion =
            $orphanStatement->fetch(
                PDO::FETCH_ASSOC
            );

    } catch (Throwable $exception) {

        $orphanQuestion =
            null;
    }


    if (
        !$orphanQuestion
    ) {

        $_SESSION['error'] =
            "Question not found.";

        header(
            "Location: index.php"
        );

        exit;
    }


    /*
     * Without an exam relationship we cannot know where
     * to redirect, but we can safely inspect dependencies.
     */
    $question = [
        'id' =>
            (int)$orphanQuestion['id'],

        'question_type' =>
            $orphanQuestion[
                'question_type'
            ],

        'question_text' =>
            $orphanQuestion[
                'question_text'
            ],

        'status' =>
            $orphanQuestion[
                'status'
            ],

        'created_at' =>
            $orphanQuestion[
                'created_at'
            ],

        'exam_id' =>
            0,

        'position' =>
            0,

        'exam_title' =>
            'Unassigned question',

        'exam_type' =>
            '',

        'required_question_count' =>
            0,

        'exam_status' =>
            '',

        'subject_name' =>
            '',

        'subject_code' =>
            ''
    ];
}


$examId =
    (int)$question[
        'exam_id'
    ];


/*
|--------------------------------------------------------------------------
| Dependency checks
|--------------------------------------------------------------------------
*/

$answerCount =
    0;

$attemptCount =
    0;

$assignmentCount =
    0;


try {

    /*
     * Student answer history.
     */
    $answerCountStatement =
        $conn->prepare("
            SELECT COUNT(*)

            FROM answers

            WHERE question_id = ?
        ");


    $answerCountStatement->execute([
        $questionId
    ]);


    $answerCount =
        (int)$answerCountStatement->fetchColumn();


    /*
     * Exam assignment history.
     */
    $assignmentCountStatement =
        $conn->prepare("
            SELECT COUNT(*)

            FROM exam_questions

            WHERE question_id = ?
        ");


    $assignmentCountStatement->execute([
        $questionId
    ]);


    $assignmentCount =
        (int)$assignmentCountStatement->fetchColumn();


    /*
     * Number of exam attempts associated with exams that
     * currently contain this question.
     *
     * This is informational; answers are the authoritative
     * question-use history.
     */
    if (
        $examId > 0
    ) {

        $attemptCountStatement =
            $conn->prepare("
                SELECT COUNT(*)

                FROM exam_attempts

                WHERE exam_id = ?
            ");


        $attemptCountStatement->execute([
            $examId
        ]);


        $attemptCount =
            (int)$attemptCountStatement->fetchColumn();
    }

} catch (Throwable $exception) {

    error_log(
        "Delete question dependency check failed: " .
        $exception->getMessage()
    );


    $_SESSION['error'] =
        "Unable to verify question history.";

    if (
        $examId > 0
    ) {

        header(
            "Location: questions.php?exam_id=" .
            $examId
        );

    } else {

        header(
            "Location: index.php"
        );
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| Used / unused
|--------------------------------------------------------------------------
*/

$hasStudentHistory =
    $answerCount > 0;


$canHardDelete =
    (
        $answerCount === 0 &&
        $assignmentCount <= 1
    );


/*
|--------------------------------------------------------------------------
| POST action
|--------------------------------------------------------------------------
*/

$error =
    '';


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $postedCsrf =
        (string)(
            $_POST[
                'csrf_token'
            ] ?? ''
        );


    $action =
        trim(
            (string)(
                $_POST[
                    'action'
                ] ?? ''
            )
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

    } elseif (
        !in_array(
            $action,
            [
                'delete',
                'deactivate'
            ],
            true
        )
    ) {

        $error =
            "Invalid delete action.";

    } else {

        try {

            $conn->beginTransaction();


            /*
             * Lock question.
             */
            $lockQuestion =
                $conn->prepare("
                    SELECT
                        id,
                        status

                    FROM questions

                    WHERE id = ?

                    LIMIT 1

                    FOR UPDATE
                ");


            $lockQuestion->execute([
                $questionId
            ]);


            $lockedQuestion =
                $lockQuestion->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$lockedQuestion
            ) {

                throw new RuntimeException(
                    "Question no longer exists."
                );
            }


            /*
             * Refresh answer history inside transaction.
             */
            $historyCheck =
                $conn->prepare("
                    SELECT COUNT(*)

                    FROM answers

                    WHERE question_id = ?
                ");


            $historyCheck->execute([
                $questionId
            ]);


            $currentAnswerCount =
                (int)$historyCheck->fetchColumn();


            /*
             * ------------------------------------------
             * DEACTIVATE
             * ------------------------------------------
             *
             * Safe even when student history exists.
             */
            if (
                $action ===
                'deactivate'
            ) {

                $deactivate =
                    $conn->prepare("
                        UPDATE questions

                        SET status = 'Inactive'

                        WHERE id = ?
                    ");


                $deactivate->execute([
                    $questionId
                ]);


                $conn->commit();


                $_SESSION['success'] =
                    "Question has been moved to Inactive.";

                if (
                    $examId > 0
                ) {

                    header(
                        "Location: questions.php?exam_id=" .
                        $examId
                    );

                } else {

                    header(
                        "Location: index.php"
                    );
                }

                exit;
            }


            /*
             * ------------------------------------------
             * HARD DELETE
             * ------------------------------------------
             */
            if (
                $action ===
                'delete'
            ) {

                /*
                 * Never delete a question with student
                 * answer history.
                 */
                if (
                    $currentAnswerCount > 0
                ) {

                    throw new RuntimeException(
                        "This question has student answer history and cannot be permanently deleted. Deactivate it instead."
                    );
                }


                /*
                 * A question may only be physically removed
                 * when it has no student history.
                 *
                 * exam_questions must be removed first because
                 * its FK uses ON DELETE RESTRICT.
                 */
                $removeAssignments =
                    $conn->prepare("
                        DELETE FROM exam_questions

                        WHERE question_id = ?
                    ");


                $removeAssignments->execute([
                    $questionId
                ]);


                /*
                 * Now the question can be deleted safely.
                 */
                $removeQuestion =
                    $conn->prepare("
                        DELETE FROM questions

                        WHERE id = ?
                    ");


                $removeQuestion->execute([
                    $questionId
                ]);


                if (
                    $removeQuestion->rowCount() !== 1
                ) {

                    throw new RuntimeException(
                        "Question could not be deleted."
                    );
                }


                $conn->commit();


                $_SESSION['success'] =
                    "Question deleted successfully.";

                if (
                    $examId > 0
                ) {

                    header(
                        "Location: questions.php?exam_id=" .
                        $examId
                    );

                } else {

                    header(
                        "Location: index.php"
                    );
                }

                exit;
            }

        } catch (Throwable $exception) {

            if (
                $conn->inTransaction()
            ) {

                $conn->rollBack();
            }


            error_log(
                "Question delete failed: " .
                $exception->getMessage()
            );


            $error =
                $exception->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Page data
|--------------------------------------------------------------------------
*/

$displayQuestion =
    trim(
        (string)(
            $question[
                'question_text'
            ] ?? ''
        )
    );


if (
    mb_strlen(
        $displayQuestion
    ) > 220
) {

    $displayQuestion =
        mb_substr(
            $displayQuestion,
            0,
            220
        ) .
        '...';
}


include "../includes/header.php";

?>

<div class="dashboard-wrapper">

<?php include "../includes/sidebar.php"; ?>

<div class="main-content">

<?php include "../includes/navbar.php"; ?>

<div class="dashboard-content">


<!-- =====================================================
     PAGE HEADER
====================================================== -->

<div class="page-header">

    <div>

        <h1>
            Delete Question
        </h1>


        <p>

            Review the question's history before
            removing it from the question bank.

        </p>

    </div>


    <?php if (
        $examId > 0
    ): ?>

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

            Back to Questions

        </a>

    <?php else: ?>

        <a
            href="index.php"
            class="btn-back"
        >

            <i
                class="
                    fa-solid
                    fa-arrow-left
                "
            ></i>

            Back

        </a>

    <?php endif; ?>

</div>


<!-- =====================================================
     ERROR
====================================================== -->

<?php if (
    $error !== ''
): ?>

    <div class="alert alert-danger">

        <i
            class="
                fa-solid
                fa-circle-exclamation
            "
        ></i>

        <?= delete_question_escape(
            $error
        ); ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     WARNING / STATUS
====================================================== -->

<?php if (
    $hasStudentHistory
): ?>

    <div
        style="
            margin-bottom:18px;
            padding:17px;
            border:1px solid #E8C9C3;
            border-radius:13px;
            background:#FBEEEB;
            color:#8F463D;
        "
    >

        <div
            style="
                display:flex;
                align-items:flex-start;
                gap:10px;
            "
        >

            <i
                class="
                    fa-solid
                    fa-shield-halved
                "
                style="
                    margin-top:2px;
                "
            ></i>


            <div>

                <strong
                    style="
                        display:block;
                        margin-bottom:4px;
                    "
                >

                    This question is part of student history.

                </strong>


                <span
                    style="
                        font-size:12px;
                        line-height:1.6;
                    "
                >

                    It has
                    <strong>
                        <?= $answerCount; ?>
                    </strong>

                    stored student answer
                    <?= $answerCount === 1
                        ? 'record'
                        : 'records'
                    ?>.

                    Permanent deletion is disabled so historical
                    examination data remains valid.

                </span>

            </div>

        </div>

    </div>

<?php endif; ?>


<!-- =====================================================
     QUESTION PREVIEW
====================================================== -->

<div
    style="
        display:grid;
        grid-template-columns:minmax(0,1fr) 330px;
        gap:18px;
        align-items:start;
    "
>


<!-- QUESTION -->

<div class="form-card">

    <div
        style="
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:15px;
            margin-bottom:18px;
        "
    >

        <div>

            <small
                style="
                    display:block;
                    color:#8B837B;
                    font-size:10px;
                    font-weight:800;
                    letter-spacing:.08em;
                "
            >
                QUESTION PREVIEW
            </small>


            <h2
                style="
                    margin:6px 0 0;
                    color:#3E2723;
                    font-size:20px;
                "
            >

                Question

                <?php if (
                    (int)$question[
                        'position'
                    ] > 0
                ): ?>

                    #<?= (int)$question[
                        'position'
                    ]; ?>

                <?php endif; ?>

            </h2>

        </div>


        <span
            class="
                status
                <?= $question['status'] === 'Active'
                    ? 'active'
                    : 'inactive'
                ?>
            "
        >

            <?= delete_question_escape(
                $question['status']
            ); ?>

        </span>

    </div>


    <div
        style="
            padding:17px;
            border:1px solid #E7E2DA;
            border-radius:12px;
            background:#FBFAF7;
        "
    >

        <div
            style="
                color:#514A43;
                font-size:15px;
                line-height:1.75;
                white-space:pre-wrap;
                word-break:break-word;
            "
        >

            <?= delete_question_escape(
                $displayQuestion
            ); ?>

        </div>

    </div>


    <!-- Options -->

    <div
        style="
            margin-top:18px;
        "
    >

        <small
            style="
                display:block;
                margin-bottom:10px;
                color:#8B837B;
                font-size:10px;
                font-weight:800;
                letter-spacing:.08em;
            "
        >
            OPTIONS
        </small>


        <?php

        $options = [
            'A' =>
                $question['option_a'] ?? '',

            'B' =>
                $question['option_b'] ?? '',

            'C' =>
                $question['option_c'] ?? '',

            'D' =>
                $question['option_d'] ?? ''
        ];

        ?>


        <div
            style="
                display:grid;
                gap:8px;
            "
        >

            <?php foreach (
                $options
                as $letter => $option
            ): ?>

                <?php

                $option =
                    trim(
                        (string)$option
                    );


                if (
                    $option === ''
                ) {

                    continue;
                }


                $isCorrect =
                    (
                        $question[
                            'correct_answer'
                        ] ===
                        $letter
                    );

                ?>


                <div
                    style="
                        display:grid;
                        grid-template-columns:36px minmax(0,1fr) auto;
                        align-items:center;
                        gap:10px;
                        padding:10px;
                        border:1px solid
                            <?= $isCorrect
                                ? '#BCD09F'
                                : '#E8E3DB'
                            ?>;
                        border-radius:10px;
                        background:
                            <?= $isCorrect
                                ? '#F1F5E9'
                                : '#FFFFFF'
                            ?>;
                    "
                >

                    <span
                        style="
                            display:grid;
                            place-items:center;
                            width:32px;
                            height:32px;
                            border-radius:9px;
                            color:
                                <?= $isCorrect
                                    ? '#FFFFFF'
                                    : '#6E665F'
                                ?>;
                            background:
                                <?= $isCorrect
                                    ? '#556B2F'
                                    : '#EEEAE4'
                                ?>;
                            font-size:12px;
                            font-weight:800;
                        "
                    >

                        <?= $letter; ?>

                    </span>


                    <span
                        style="
                            color:#5D554E;
                            font-size:12px;
                            line-height:1.55;
                        "
                    >

                        <?= delete_question_escape(
                            $option
                        ); ?>

                    </span>


                    <?php if (
                        $isCorrect
                    ): ?>

                        <span
                            style="
                                display:inline-flex;
                                align-items:center;
                                gap:4px;
                                padding:4px 7px;
                                border-radius:99px;
                                background:#DDE9CE;
                                color:#5B713F;
                                font-size:9px;
                                font-weight:800;
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-check
                                "
                            ></i>

                            Correct

                        </span>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    </div>


</div>


<!-- =====================================================
     DETAILS
====================================================== -->

<div
    style="
        display:grid;
        gap:14px;
    "
>


    <!-- EXAM -->

    <div class="form-card">

        <small
            style="
                display:block;
                color:#8B837B;
                font-size:10px;
                font-weight:800;
                letter-spacing:.08em;
            "
        >
            EXAMINATION
        </small>


        <h3
            style="
                margin:6px 0 12px;
                color:#3E2723;
                font-size:15px;
                line-height:1.45;
            "
        >

            <?= delete_question_escape(
                $question['exam_title']
            ); ?>

        </h3>


        <div
            style="
                display:grid;
                gap:7px;
            "
        >

            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    gap:10px;
                "
            >

                <span
                    style="
                        color:#918981;
                        font-size:10px;
                    "
                >
                    Subject
                </span>


                <strong
                    style="
                        color:#554D46;
                        font-size:10px;
                        text-align:right;
                    "
                >

                    <?= delete_question_escape(
                        $question['subject_name']
                        ?: 'Not assigned'
                    ); ?>

                </strong>

            </div>


            <?php if (
                !empty(
                    $question['subject_code']
                )
            ): ?>

                <div
                    style="
                        display:flex;
                        justify-content:space-between;
                        gap:10px;
                    "
                >

                    <span
                        style="
                            color:#918981;
                            font-size:10px;
                        "
                    >
                        Subject code
                    </span>


                    <strong
                        style="
                            color:#554D46;
                            font-size:10px;
                        "
                    >

                        <?= delete_question_escape(
                            $question[
                                'subject_code'
                            ]
                        ); ?>

                    </strong>

                </div>

            <?php endif; ?>


            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    gap:10px;
                "
            >

                <span
                    style="
                        color:#918981;
                        font-size:10px;
                    "
                >
                    Position
                </span>


                <strong
                    style="
                        color:#556B2F;
                        font-size:10px;
                    "
                >

                    #<?= (int)$question[
                        'position'
                    ]; ?>

                </strong>

            </div>


            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    gap:10px;
                "
            >

                <span
                    style="
                        color:#918981;
                        font-size:10px;
                    "
                >
                    Exam type
                </span>


                <strong
                    style="
                        color:#554D46;
                        font-size:10px;
                    "
                >

                    <?= delete_question_escape(
                        $question['exam_type']
                    ); ?>

                </strong>

            </div>

        </div>

    </div>


    <!-- HISTORY -->

    <div class="form-card">

        <small
            style="
                display:block;
                color:#8B837B;
                font-size:10px;
                font-weight:800;
                letter-spacing:.08em;
            "
        >
            DATA HISTORY
        </small>


        <div
            style="
                display:grid;
                gap:10px;
                margin-top:12px;
            "
        >

            <div
                style="
                    padding:10px;
                    border-radius:9px;
                    background:#FAF9F6;
                "
            >

                <small
                    style="
                        display:block;
                        color:#908880;
                        font-size:9px;
                    "
                >
                    STUDENT ANSWERS
                </small>


                <strong
                    style="
                        display:block;
                        margin-top:3px;
                        color:
                            <?= $answerCount > 0
                                ? '#9A473C'
                                : '#556B2F'
                            ?>;
                        font-size:16px;
                    "
                >

                    <?= $answerCount; ?>

                </strong>

            </div>


            <div
                style="
                    padding:10px;
                    border-radius:9px;
                    background:#FAF9F6;
                "
            >

                <small
                    style="
                        display:block;
                        color:#908880;
                        font-size:9px;
                    "
                >
                    EXAM ASSIGNMENTS
                </small>


                <strong
                    style="
                        display:block;
                        margin-top:3px;
                        color:#3E2723;
                        font-size:16px;
                    "
                >

                    <?= $assignmentCount; ?>

                </strong>

            </div>


            <div
                style="
                    padding:10px;
                    border-radius:9px;
                    background:#FAF9F6;
                "
            >

                <small
                    style="
                        display:block;
                        color:#908880;
                        font-size:9px;
                    "
                >
                    EXAM ATTEMPTS
                </small>


                <strong
                    style="
                        display:block;
                        margin-top:3px;
                        color:#3E2723;
                        font-size:16px;
                    "
                >

                    <?= $attemptCount; ?>

                </strong>

            </div>

        </div>

    </div>


    <!-- ACTION -->

    <div class="form-card">

        <?php if (
            $canHardDelete
        ): ?>

            <div
                style="
                    padding:12px;
                    margin-bottom:12px;
                    border-radius:10px;
                    background:#F1F5E9;
                    color:#5D713F;
                    font-size:11px;
                    line-height:1.6;
                "
            >

                <i
                    class="
                        fa-solid
                        fa-circle-check
                    "
                ></i>

                This question has no student answer
                history and can be permanently deleted.

            </div>

        <?php else: ?>

            <div
                style="
                    padding:12px;
                    margin-bottom:12px;
                    border-radius:10px;
                    background:#FBF4E5;
                    color:#8A672B;
                    font-size:11px;
                    line-height:1.6;
                "
            >

                <i
                    class="
                        fa-solid
                        fa-shield-halved
                    "
                ></i>

                Permanent deletion is blocked when
                student history exists.

            </div>

        <?php endif; ?>


        <!-- Deactivate -->

        <?php if (
            $question['status'] ===
            'Active'
        ): ?>

            <form
                method="POST"
                style="
                    margin-bottom:8px;
                "
                onsubmit="
                    return confirm(
                        'Move this question to Inactive? It will remain available for historical records.'
                    );
                "
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= delete_question_escape(
                        $csrfToken
                    ); ?>"
                >


                <input
                    type="hidden"
                    name="action"
                    value="deactivate"
                >


                <button
                    type="submit"
                    class="btn-back"
                    style="
                        width:100%;
                        justify-content:center;
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-ban
                        "
                    ></i>

                    Make Inactive

                </button>

            </form>

        <?php endif; ?>


        <!-- Delete -->

        <?php if (
            $canHardDelete
        ): ?>

            <form
                method="POST"
                onsubmit="
                    return confirm(
                        'Permanently delete this question? This action cannot be undone.'
                    );
                "
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= delete_question_escape(
                        $csrfToken
                    ); ?>"
                >


                <input
                    type="hidden"
                    name="action"
                    value="delete"
                >


                <button
                    type="submit"
                    class="btn-delete"
                    style="
                        width:100%;
                        justify-content:center;
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-trash
                        "
                    ></i>

                    Permanently Delete

                </button>

            </form>

        <?php else: ?>

            <button
                type="button"
                class="btn-delete"
                disabled
                style="
                    width:100%;
                    justify-content:center;
                    opacity:.45;
                    cursor:not-allowed;
                "
                title="
                    Questions with student history cannot be permanently deleted.
                "
            >

                <i
                    class="
                        fa-solid
                        fa-lock
                    "
                ></i>

                Permanent Delete Locked

            </button>

        <?php endif; ?>

    </div>

</div>

</div>


</div>

</div>

</div>


<?php include "../includes/footer.php"; ?>