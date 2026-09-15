<?php
declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";
require_once "../../config/notification_events.php";

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: ../../auth/login.php');
    exit;
}

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $id === false ||
    $id === null ||
    $id <= 0
) {
    $_SESSION['error'] = 'Invalid subject.';
    header('Location: index.php');
    exit;
}

try {

    $subjectStatement = $conn->prepare("
        SELECT
            id,
            name,
            status
        FROM subjects
        WHERE id = ?
        LIMIT 1
    ");

    $subjectStatement->execute([$id]);

    $subject = $subjectStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$subject) {
        $_SESSION['error'] = 'Subject not found.';
        header('Location: index.php');
        exit;
    }

    /*
     * Count all records that depend directly on the subject.
     *
     * exams.subject_id      -> ON DELETE SET NULL
     * questions.subject_id  -> ON DELETE SET NULL
     * topics.subject_id     -> ON DELETE CASCADE
     *
     * Topics are therefore especially important: deleting a subject
     * would otherwise remove the linked topic records automatically.
     */
    $dependencyStatement = $conn->prepare("
        SELECT

            (
                SELECT COUNT(*)
                FROM exams
                WHERE subject_id = ?
            ) AS exam_count,

            (
                SELECT COUNT(*)
                FROM questions
                WHERE subject_id = ?
            ) AS question_count,

            (
                SELECT COUNT(*)
                FROM topics
                WHERE subject_id = ?
            ) AS topic_count
    ");

    $dependencyStatement->execute([
        $id,
        $id,
        $id
    ]);

    $dependencies =
        $dependencyStatement->fetch(
            PDO::FETCH_ASSOC
        ) ?: [
            'exam_count' => 0,
            'question_count' => 0,
            'topic_count' => 0
        ];

    $examCount =
        (int)$dependencies['exam_count'];

    $questionCount =
        (int)$dependencies['question_count'];

    $topicCount =
        (int)$dependencies['topic_count'];

    if (
        $examCount > 0 ||
        $questionCount > 0 ||
        $topicCount > 0
    ) {

        $reasons = [];

        if ($questionCount > 0) {
            $reasons[] =
                $questionCount .
                ' linked question(s)';
        }

        if ($examCount > 0) {
            $reasons[] =
                $examCount .
                ' linked exam(s)';
        }

        if ($topicCount > 0) {
            $reasons[] =
                $topicCount .
                ' linked topic(s)';
        }

        $_SESSION['error'] =
            'Subject "' .
            (string)$subject['name'] .
            '" cannot be deleted because it has ' .
            implode(', ', $reasons) .
            '. Deactivate it instead.';

        header('Location: index.php');
        exit;
    }

    /*
     * Lock the subject before deletion.
     *
     * This protects against a race where another request adds a
     * dependent record between the dependency check and DELETE.
     */
    $conn->beginTransaction();

    $lockStatement = $conn->prepare("
        SELECT
            id,
            name,
            status
        FROM subjects
        WHERE id = ?
        FOR UPDATE
    ");

    $lockStatement->execute([$id]);

    $lockedSubject =
        $lockStatement->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$lockedSubject) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Subject no longer exists.';

        header('Location: index.php');
        exit;
    }

    /*
     * Re-check dependent records inside the transaction.
     */
    $finalDependencyStatement =
        $conn->prepare("
            SELECT

                (
                    SELECT COUNT(*)
                    FROM exams
                    WHERE subject_id = ?
                ) AS exam_count,

                (
                    SELECT COUNT(*)
                    FROM questions
                    WHERE subject_id = ?
                ) AS question_count,

                (
                    SELECT COUNT(*)
                    FROM topics
                    WHERE subject_id = ?
                ) AS topic_count
        ");

    $finalDependencyStatement->execute([
        $id,
        $id,
        $id
    ]);

    $finalDependencies =
        $finalDependencyStatement->fetch(
            PDO::FETCH_ASSOC
        ) ?: [
            'exam_count' => 0,
            'question_count' => 0,
            'topic_count' => 0
        ];

    $finalExamCount =
        (int)$finalDependencies['exam_count'];

    $finalQuestionCount =
        (int)$finalDependencies['question_count'];

    $finalTopicCount =
        (int)$finalDependencies['topic_count'];

    if (
        $finalExamCount > 0 ||
        $finalQuestionCount > 0 ||
        $finalTopicCount > 0
    ) {

        $conn->rollBack();

        $reasons = [];

        if ($finalQuestionCount > 0) {
            $reasons[] =
                $finalQuestionCount .
                ' linked question(s)';
        }

        if ($finalExamCount > 0) {
            $reasons[] =
                $finalExamCount .
                ' linked exam(s)';
        }

        if ($finalTopicCount > 0) {
            $reasons[] =
                $finalTopicCount .
                ' linked topic(s)';
        }

        $_SESSION['error'] =
            'Subject "' .
            (string)$lockedSubject['name'] .
            '" cannot be deleted because it has ' .
            implode(', ', $reasons) .
            '. Deactivate it instead.';

        header('Location: index.php');
        exit;
    }

    $deleteStatement = $conn->prepare("
        DELETE FROM subjects
        WHERE id = ?
    ");

    $deleteStatement->execute([$id]);

    if ($deleteStatement->rowCount() !== 1) {

        $conn->rollBack();

        $_SESSION['error'] =
            'Subject was not deleted. Please try again.';

        header('Location: index.php');
        exit;
    }

    $conn->commit();

    examsphere_event_admin_academic(
        $conn,
        'subject',
        (int)$id,
        (string)$lockedSubject['name'],
        'deleted'
    );

    $_SESSION['success'] =
        'Subject "' .
        (string)$lockedSubject['name'] .
        '" deleted successfully.';

    header('Location: index.php');
    exit;

} catch (PDOException $exception) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log(
        'Delete subject failed: ' .
        $exception->getMessage()
    );

    /*
     * Foreign-key protection remains a second safety layer.
     */
    if (
        isset($exception->errorInfo[1]) &&
        (int)$exception->errorInfo[1] === 1451
    ) {

        $_SESSION['error'] =
            'This subject cannot be deleted because related records exist. ' .
            'Deactivate it instead.';

    } else {

        $_SESSION['error'] =
            'Unable to delete this subject.';
    }

    header('Location: index.php');
    exit;

} catch (Throwable $exception) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log(
        'Delete subject failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'An unexpected error occurred while deleting the subject.';

    header('Location: index.php');
    exit;
}