<?php
declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: ../../auth/login.php');
    exit;
}

$redirectUrl = 'index.php';

$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null) {
    $id = filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );
}

if ($id === false || $id === null || $id <= 0) {
    $_SESSION['error'] = 'Invalid teacher.';
    header('Location: ' . $redirectUrl);
    exit;
}

function teacher_delete_escape(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

try {

    $teacherStmt = $conn->prepare("
        SELECT
            id,
            full_name,
            teacher_code,
            email,
            profile_photo,
            status
        FROM teachers
        WHERE id = ?
        LIMIT 1
    ");

    $teacherStmt->execute([$id]);

    $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        $_SESSION['error'] = 'Teacher not found.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    /*
     * GET:
     * Show confirmation page only.
     *
     * No deletion is performed from GET.
     */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        $usageStmt = $conn->prepare("
            SELECT
                (
                    SELECT COUNT(*)
                    FROM exams
                    WHERE teacher_id = ?
                ) AS exam_count,

                (
                    SELECT COUNT(*)
                    FROM questions
                    WHERE created_by_teacher_id = ?
                ) AS question_count,

                (
                    SELECT COUNT(*)
                    FROM study_materials
                    WHERE teacher_id = ?
                ) AS material_count
        ");

        $usageStmt->execute([
            $id,
            $id,
            $id
        ]);

        $usage = $usageStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'exam_count' => 0,
            'question_count' => 0,
            'material_count' => 0
        ];

        $linkedRecords =
            (int)$usage['exam_count'] +
            (int)$usage['question_count'] +
            (int)$usage['material_count'];
        ?>

        <!DOCTYPE html>
        <html lang="en">

        <head>

            <meta charset="UTF-8">

            <meta
                name="viewport"
                content="width=device-width, initial-scale=1.0"
            >

            <title>
                Confirm Teacher Deletion -
                <?= teacher_delete_escape(SITE_NAME) ?>
            </title>

            <link
                rel="stylesheet"
                href="../../admin/assets/css/admin-dashboard.css"
            >

            <link
                rel="stylesheet"
                href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
            >

            <style>

                :root {
                    --cream: #f5f5dc;
                    --brown: #5d4037;
                    --olive: #556b2f;
                    --text: #333333;
                    --muted: #746d68;
                    --white: #ffffff;
                }

                * {
                    box-sizing: border-box;
                }

                body {
                    margin: 0;
                    min-height: 100vh;
                    display: grid;
                    place-items: center;
                    padding: 24px;
                    background:
                        radial-gradient(
                            circle at top left,
                            rgba(85,107,47,.13),
                            transparent 35%
                        ),
                        radial-gradient(
                            circle at bottom right,
                            rgba(93,64,55,.14),
                            transparent 35%
                        ),
                        var(--cream);
                    font-family:
                        Inter,
                        system-ui,
                        -apple-system,
                        BlinkMacSystemFont,
                        "Segoe UI",
                        sans-serif;
                    color: var(--text);
                }

                .delete-card {
                    width: min(680px, 100%);
                    padding: 34px;
                    border: 1px solid rgba(93,64,55,.10);
                    border-radius: 24px;
                    background: rgba(255,255,255,.82);
                    box-shadow:
                        0 28px 70px rgba(62,45,37,.16);
                    backdrop-filter: blur(18px);
                }

                .delete-icon {
                    width: 64px;
                    height: 64px;
                    display: grid;
                    place-items: center;
                    margin-bottom: 20px;
                    border-radius: 18px;
                    background: rgba(176, 54, 54, .10);
                    color: #a83232;
                    font-size: 28px;
                }

                h1 {
                    margin: 0 0 8px;
                    color: var(--brown);
                    font-size:
                        clamp(
                            1.5rem,
                            4vw,
                            2.1rem
                        );
                }

                .lead {
                    margin: 0 0 24px;
                    color: var(--muted);
                    line-height: 1.65;
                }

                .teacher-box {
                    padding: 18px;
                    border: 1px solid rgba(93,64,55,.09);
                    border-radius: 16px;
                    background: rgba(245,245,220,.55);
                    margin-bottom: 20px;
                }

                .teacher-name {
                    font-weight: 800;
                    font-size: 1.15rem;
                    margin-bottom: 4px;
                }

                .teacher-meta {
                    color: var(--muted);
                    font-size: .92rem;
                }

                .warning {
                    padding: 15px 16px;
                    border-radius: 14px;
                    background: rgba(168,50,50,.07);
                    color: #783030;
                    line-height: 1.55;
                    margin-bottom: 24px;
                }

                .buttons {
                    display: flex;
                    justify-content: flex-end;
                    gap: 12px;
                    flex-wrap: wrap;
                }

                .btn {
                    min-height: 46px;
                    padding: 0 18px;
                    border: 0;
                    border-radius: 12px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    text-decoration: none;
                    font-weight: 800;
                    cursor: pointer;
                }

                .btn-secondary {
                    background: #eee9df;
                    color: var(--brown);
                }

                .btn-danger {
                    background: #a83232;
                    color: #fff;
                }

                .btn-danger:hover {
                    background: #8e2929;
                }

            </style>

        </head>

        <body>

            <main class="delete-card">

                <div class="delete-icon">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>

                <h1>
                    Delete this teacher?
                </h1>

                <p class="lead">
                    This will permanently remove the teacher account.
                    Existing exams, questions and study materials are
                    preserved and their teacher reference will be cleared
                    by the database relationship.
                </p>

                <section class="teacher-box">

                    <div class="teacher-name">

                        <?= teacher_delete_escape(
                            $teacher['full_name']
                        ) ?>

                    </div>

                    <div class="teacher-meta">

                        <?= teacher_delete_escape(
                            $teacher['teacher_code']
                        ) ?>

                        ·

                        <?= teacher_delete_escape(
                            $teacher['email']
                        ) ?>

                    </div>

                </section>

                <?php if ($linkedRecords > 0): ?>

                    <div class="warning">

                        <strong>
                            Linked records found:
                        </strong>

                        <?= (int)$usage['exam_count'] ?>
                        exam(s),

                        <?= (int)$usage['question_count'] ?>
                        question(s),

                        <?= (int)$usage['material_count'] ?>
                        material(s).

                        These records will remain in the system.

                    </div>

                <?php else: ?>

                    <div class="warning">

                        This teacher has no linked exams,
                        questions or study materials.

                    </div>

                <?php endif; ?>

                <form
                    method="post"
                    action="delete.php"
                    class="buttons"
                >

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int)$teacher['id'] ?>"
                    >

                    <a
                        href="index.php"
                        class="btn btn-secondary"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="btn btn-danger"
                    >
                        <i class="fa-solid fa-trash"></i>
                        Delete Teacher
                    </button>

                </form>

            </main>

        </body>

        </html>

        <?php

        exit;
    }

    /*
     * POST:
     * Actual deletion begins here.
     */

    if (!verify_csrf_token(
        $_POST['csrf_token'] ?? null
    )) {

        $_SESSION['error'] =
            'Security verification failed. Please try again.';

        header('Location: ' . $redirectUrl);
        exit;
    }

    $postedId = filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    );

    if (
        $postedId === false ||
        $postedId === null ||
        $postedId <= 0 ||
        $postedId !== (int)$teacher['id']
    ) {

        $_SESSION['error'] =
            'Invalid teacher deletion request.';

        header('Location: ' . $redirectUrl);
        exit;
    }

    /*
     * Capture current relationships before deletion.
     */
    $usageStmt = $conn->prepare("
        SELECT

            (
                SELECT COUNT(*)
                FROM exams
                WHERE teacher_id = ?
            ) AS exam_count,

            (
                SELECT COUNT(*)
                FROM questions
                WHERE created_by_teacher_id = ?
            ) AS question_count,

            (
                SELECT COUNT(*)
                FROM study_materials
                WHERE teacher_id = ?
            ) AS material_count

    ");

    $usageStmt->execute([
        (int)$teacher['id'],
        (int)$teacher['id'],
        (int)$teacher['id']
    ]);

    $usage = $usageStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'exam_count' => 0,
        'question_count' => 0,
        'material_count' => 0
    ];

    /*
     * Lock and delete safely.
     */
    $conn->beginTransaction();

    $lockStmt = $conn->prepare("
        SELECT
            id,
            full_name,
            profile_photo
        FROM teachers
        WHERE id = ?
        FOR UPDATE
    ");

    $lockStmt->execute([
        (int)$teacher['id']
    ]);

    $lockedTeacher =
        $lockStmt->fetch(PDO::FETCH_ASSOC);

    if (!$lockedTeacher) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Teacher no longer exists.';

        header('Location: ' . $redirectUrl);
        exit;
    }

    $deleteStmt = $conn->prepare("
        DELETE FROM teachers
        WHERE id = ?
    ");

    $deleteStmt->execute([
        (int)$lockedTeacher['id']
    ]);

    if ($deleteStmt->rowCount() !== 1) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Teacher was not deleted. Please try again.';

        header('Location: ' . $redirectUrl);
        exit;
    }

    $conn->commit();

    /*
     * Delete profile photo only after
     * successful database deletion.
     */
    $profilePhoto = trim(
        (string)(
            $lockedTeacher['profile_photo']
            ?? ''
        )
    );

    if ($profilePhoto !== '') {

        $photoPath =
            dirname(__DIR__, 2) .
            '/uploads/teachers/' .
            basename($profilePhoto);

        if (is_file($photoPath)) {
            @unlink($photoPath);
        }
    }

    /*
     * Prepare useful success information.
     */
    $linkedSummary = [];

    if (
        (int)$usage['exam_count'] > 0
    ) {

        $linkedSummary[] =
            (int)$usage['exam_count'] .
            ' exam(s)';
    }

    if (
        (int)$usage['question_count'] > 0
    ) {

        $linkedSummary[] =
            (int)$usage['question_count'] .
            ' question(s)';
    }

    if (
        (int)$usage['material_count'] > 0
    ) {

        $linkedSummary[] =
            (int)$usage['material_count'] .
            ' material(s)';
    }

    $_SESSION['success'] =
        'Teacher "' .
        (string)$lockedTeacher['full_name'] .
        '" was deleted successfully.' .
        (
            $linkedSummary
                ? ' Linked records preserved: ' .
                    implode(
                        ', ',
                        $linkedSummary
                    ) .
                    '.'
                : ''
        );

    header('Location: ' . $redirectUrl);
    exit;

} catch (PDOException $exception) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log(
        'Teacher deletion failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to delete the teacher. Please try again.';

    header('Location: ' . $redirectUrl);
    exit;

} catch (Throwable $exception) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log(
        'Teacher deletion failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'An unexpected error occurred while deleting the teacher.';

    header('Location: ' . $redirectUrl);
    exit;
}