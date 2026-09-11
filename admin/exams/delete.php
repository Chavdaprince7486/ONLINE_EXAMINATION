<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

$page_title = "Delete Exam";
$error = "";
$canDelete = false;


/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header("Location: ../../auth/login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDATE EXAM ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $id === false ||
    $id === null ||
    (int) $id <= 0
) {
    header("Location: index.php");
    exit;
}

$id = (int) $id;


/*
|--------------------------------------------------------------------------
| FETCH EXAM
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        e.id,
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
        s.name AS subject_name,
        c.category_name,
        t.full_name AS teacher_name
     FROM exams AS e

     LEFT JOIN subjects AS s
        ON s.id = e.subject_id

     LEFT JOIN categories AS c
        ON c.id = s.category_id

     LEFT JOIN teachers AS t
        ON t.id = e.teacher_id

     WHERE e.id = ?

     LIMIT 1"
);

$stmt->execute([
    $id
]);

$exam = $stmt->fetch(
    PDO::FETCH_ASSOC
);

if (!$exam) {
    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| ASSIGNED QUESTION COUNT
|--------------------------------------------------------------------------
*/

$questionCountStmt = $conn->prepare(
    "SELECT COUNT(*)
     FROM exam_questions
     WHERE exam_id = ?"
);

$questionCountStmt->execute([
    $id
]);

$assignedQuestionCount =
    (int) $questionCountStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| ATTEMPT COUNT
|--------------------------------------------------------------------------
*/

$attemptCountStmt = $conn->prepare(
    "SELECT COUNT(*)
     FROM exam_attempts
     WHERE exam_id = ?"
);

$attemptCountStmt->execute([
    $id
]);

$attemptCount =
    (int) $attemptCountStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| RESULT COUNT
|--------------------------------------------------------------------------
*/

$resultCountStmt = $conn->prepare(
    "SELECT COUNT(*)
     FROM results
     WHERE exam_id = ?"
);

$resultCountStmt->execute([
    $id
]);

$resultCount =
    (int) $resultCountStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| DELETE PERMISSION
|--------------------------------------------------------------------------
|
| Historical exam data must be preserved.
|
*/

$canDelete =
    $attemptCount === 0 &&
    $resultCount === 0;


/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        !verify_csrf_token(
            $_POST['csrf_token']
            ?? null
        )
    ) {

        $error =
            'Invalid security token. Please refresh the page and try again.';

    } elseif (
        !$canDelete
    ) {

        $error =
            'This exam cannot be deleted because it already has examination history. Keep it for audit and reporting, or change its status instead.';

    } elseif (
        trim(
            (string) (
                $_POST['confirmation']
                ?? ''
            )
        ) !== 'DELETE'
    ) {

        $error =
            'Please type DELETE to confirm permanent deletion.';

    } else {

        try {

            $conn->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | LOCK EXAM
            |--------------------------------------------------------------------------
            */

            $lockStmt =
                $conn->prepare(
                    "SELECT id
                     FROM exams
                     WHERE id = ?
                     FOR UPDATE"
                );

            $lockStmt->execute([
                $id
            ]);

            if (
                !$lockStmt->fetchColumn()
            ) {

                throw new RuntimeException(
                    'The exam no longer exists.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | RECHECK ATTEMPTS
            |--------------------------------------------------------------------------
            */

            $recheckAttempts =
                $conn->prepare(
                    "SELECT COUNT(*)
                     FROM exam_attempts
                     WHERE exam_id = ?"
                );

            $recheckAttempts->execute([
                $id
            ]);

            $recheckAttemptCount =
                (int)
                $recheckAttempts->fetchColumn();


            /*
            |--------------------------------------------------------------------------
            | RECHECK RESULTS
            |--------------------------------------------------------------------------
            */

            $recheckResults =
                $conn->prepare(
                    "SELECT COUNT(*)
                     FROM results
                     WHERE exam_id = ?"
                );

            $recheckResults->execute([
                $id
            ]);

            $recheckResultCount =
                (int)
                $recheckResults->fetchColumn();


            /*
            |--------------------------------------------------------------------------
            | FINAL SAFETY CHECK
            |--------------------------------------------------------------------------
            */

            if (
                $recheckAttemptCount > 0 ||
                $recheckResultCount > 0
            ) {

                throw new RuntimeException(
                    'Deletion was blocked because examination history already exists for this exam.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | DELETE EXAM
            |--------------------------------------------------------------------------
            |
            | exam_questions are removed automatically by:
            |
            | ON DELETE CASCADE
            |
            | Question-bank records are NOT deleted.
            |
            */

            $delete =
                $conn->prepare(
                    "DELETE FROM exams
                     WHERE id = ?"
                );

            $delete->execute([
                $id
            ]);


            if (
                $delete->rowCount() !== 1
            ) {

                throw new RuntimeException(
                    'The exam could not be deleted.'
                );
            }


            $conn->commit();


            $_SESSION[
                'success_message'
            ] =
                'Exam deleted successfully.';


            header(
                "Location: index.php"
            );

            exit;

        } catch (
            Throwable $exception
        ) {

            if (
                $conn->inTransaction()
            ) {

                $conn->rollBack();
            }


            error_log(
                'Exam deletion error: ' .
                $exception->getMessage()
            );


            $error =
                $exception->getMessage();


            if (
                $error === ''
            ) {

                $error =
                    'Unable to delete the exam right now. Please try again.';
            }
        }
    }
}


include "../includes/header.php";

?>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <div class="dashboard-content">


            <div class="page-header">

                <div>

                    <h1>
                        Delete Exam
                    </h1>

                    <p>
                        Review the exam before permanent deletion.
                    </p>

                </div>

                <a
                    href="index.php"
                    class="btn-add"
                >

                    <i class="fa-solid fa-arrow-left"></i>

                    Back

                </a>

            </div>


            <?php if (
                $error !== ''
            ): ?>

                <div
                    class="alert alert-danger"
                    role="alert"
                >

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>

                </div>

            <?php endif; ?>


            <div class="form-card">


                <div class="mb-4">

                    <div
                        class="alert <?= $canDelete
                            ? 'alert-warning'
                            : 'alert-info'; ?>"
                        role="alert"
                    >

                        <?php if (
                            $canDelete
                        ): ?>

                            <strong>
                                Permanent deletion.
                            </strong>

                            This action cannot be undone.

                            Questions linked through
                            <code>exam_questions</code>
                            will be removed automatically by
                            the database foreign-key cascade.

                            The original question-bank records
                            will remain.

                        <?php else: ?>

                            <strong>
                                Deletion blocked for data safety.
                            </strong>

                            This exam already contains student
                            examination history.

                            Because
                            <code>exam_attempts</code>
                            and
                            <code>results</code>
                            intentionally protect historical
                            records, this exam must be retained.

                            You can change its status instead.

                        <?php endif; ?>

                    </div>

                </div>


                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Exam Title
                        </label>

                        <input
                            type="text"
                            value="<?= htmlspecialchars(
                                (string) $exam['title'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Category
                        </label>

                        <input
                            type="text"
                            value="<?= htmlspecialchars(
                                (string) (
                                    $exam['category_name']
                                    ?? '—'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Subject
                        </label>

                        <input
                            type="text"
                            value="<?= htmlspecialchars(
                                (string) (
                                    $exam['subject_name']
                                    ?? '—'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Teacher
                        </label>

                        <input
                            type="text"
                            value="<?= htmlspecialchars(
                                (string) (
                                    $exam['teacher_name']
                                    ?? '—'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Exam Type
                        </label>

                        <input
                            type="text"
                            value="<?= htmlspecialchars(
                                (string)
                                $exam['exam_type'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Status
                        </label>

                        <input
                            type="text"
                            value="<?= htmlspecialchars(
                                (string)
                                $exam['status'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Required Questions
                        </label>

                        <input
                            type="text"
                            value="<?= (int)
                                $exam[
                                    'required_question_count'
                                ]; ?>"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Assigned Questions
                        </label>

                        <input
                            type="text"
                            value="<?= $assignedQuestionCount; ?>"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Attempts
                        </label>

                        <input
                            type="text"
                            value="<?= $attemptCount; ?>"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Results
                        </label>

                        <input
                            type="text"
                            value="<?= $resultCount; ?>"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Total Marks
                        </label>

                        <input
                            type="text"
                            value="<?= number_format(
                                (float)
                                $exam['total_marks'],
                                2
                            ); ?>"
                            readonly
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Duration
                        </label>

                        <input
                            type="text"
                            value="<?= (int)
                                $exam[
                                    'duration_minutes'
                                ]; ?> minutes"
                            readonly
                        >

                    </div>


                </div>


                <div class="form-actions">


                    <?php if (
                        $canDelete
                    ): ?>

                        <form
                            method="POST"
                            id="deleteExamForm"
                            class="d-flex flex-wrap gap-2 align-items-center"
                        >

                            <?= csrf_field(); ?>


                            <input
                                type="text"
                                name="confirmation"
                                class="form-control"
                                placeholder="Type DELETE"
                                autocomplete="off"
                                required
                                maxlength="6"
                                style="max-width:180px;"
                            >


                            <button
                                type="submit"
                                class="btn-delete"
                                id="deleteExamButton"
                            >

                                <i
                                    class="fa-solid fa-trash"
                                ></i>

                                Delete Exam Permanently

                            </button>

                        </form>

                    <?php else: ?>

                        <span class="text-muted">

                            Permanent deletion is disabled because
                            this exam has history.

                        </span>

                    <?php endif; ?>


                    <a
                        href="edit.php?id=<?= $id; ?>"
                        class="btn-back"
                    >

                        <i
                            class="fa-solid fa-pen"
                        ></i>

                        Edit Exam

                    </a>


                    <a
                        href="index.php"
                        class="btn-back"
                    >

                        Cancel

                    </a>


                </div>


            </div>

        </div>

    </div>

</div>


<script>

(function () {

    const form =
        document.getElementById(
            'deleteExamForm'
        );

    const button =
        document.getElementById(
            'deleteExamButton'
        );


    if (
        !form ||
        !button
    ) {

        return;

    }


    form.addEventListener(
        'submit',
        function (event) {

            const confirmation =
                form.querySelector(
                    'input[name="confirmation"]'
                );


            if (
                !confirmation ||
                confirmation.value.trim() !==
                    'DELETE'
            ) {

                event.preventDefault();

                confirmation?.focus();

                return;

            }


            const confirmed =
                window.confirm(
                    'This will permanently delete the exam. Continue?'
                );


            if (!confirmed) {

                event.preventDefault();

                return;

            }


            button.disabled =
                true;


            button.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';

        }
    );

})();

</script>


<?php include "../includes/footer.php"; ?>