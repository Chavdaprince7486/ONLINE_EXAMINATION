<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');


function save_answer_response(
    bool $success,
    string $message,
    array $data = [],
    int $httpCode = 200
): never {

    http_response_code($httpCode);

    echo json_encode(
        array_merge(
            [
                'status' => $success,
                'message' => $message
            ],
            $data
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    save_answer_response(
        false,
        'Invalid request method.',
        [],
        405
    );
}


if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {

    save_answer_response(
        false,
        'Unauthorized access.',
        [],
        401
    );
}


$studentId = (int) $_SESSION['user_id'];


$requestToken = trim(
    (string) (
        $_POST['csrf_token'] ?? ''
    )
);


if ($requestToken === '') {

    save_answer_response(
        false,
        'Invalid security token.',
        [],
        419
    );
}


$csrfValid = false;


$examCsrfToken = (string) (
    $_SESSION['exam_csrf_token'] ?? ''
);


if ($examCsrfToken !== '') {

    $csrfValid = hash_equals(
        $examCsrfToken,
        $requestToken
    );
}


if (!$csrfValid) {

    $globalCsrfToken = (string) (
        $_SESSION['csrf_token'] ?? ''
    );

    if ($globalCsrfToken !== '') {

        $csrfValid = hash_equals(
            $globalCsrfToken,
            $requestToken
        );
    }
}


if (!$csrfValid) {

    save_answer_response(
        false,
        'Invalid security token.',
        [],
        419
    );
}


$attemptId = filter_var(
    $_POST['attempt_id'] ?? null,
    FILTER_VALIDATE_INT
);


$questionId = filter_var(
    $_POST['question_id'] ?? null,
    FILTER_VALIDATE_INT
);


$selectedAnswer = strtoupper(
    trim(
        (string) (
            $_POST['selected_answer']
            ??
            $_POST['answer']
            ??
            ''
        )
    )
);


if (
    $attemptId === false ||
    $attemptId === null ||
    $attemptId <= 0
) {

    save_answer_response(
        false,
        'Invalid examination attempt.',
        [],
        422
    );
}


if (
    $questionId === false ||
    $questionId === null ||
    $questionId <= 0
) {

    save_answer_response(
        false,
        'Invalid question ID.',
        [],
        422
    );
}


if (
    !in_array(
        $selectedAnswer,
        [
            'A',
            'B',
            'C',
            'D'
        ],
        true
    )
) {

    save_answer_response(
        false,
        'Invalid answer option.',
        [],
        422
    );
}


try {

    $conn->beginTransaction();


    $attemptStatement = $conn->prepare("
        SELECT
            ea.id,
            ea.student_id,
            ea.exam_id,
            ea.status,
            ea.server_deadline,
            e.status AS exam_status,
            e.exam_type,
            e.required_question_count
        FROM exam_attempts ea
        INNER JOIN exams e
            ON e.id = ea.exam_id
        WHERE
            ea.id = ?
            AND ea.student_id = ?
        LIMIT 1
        FOR UPDATE
    ");


    $attemptStatement->execute(
        [
            (int) $attemptId,
            $studentId
        ]
    );


    $attempt = $attemptStatement->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$attempt) {

        throw new RuntimeException(
            'Examination attempt not found.'
        );
    }


    if (
        (int) $attempt['student_id']
        !==
        $studentId
    ) {

        throw new RuntimeException(
            'You are not allowed to modify this examination attempt.'
        );
    }


    if (
        (string) $attempt['status']
        !==
        'Started'
    ) {

        throw new RuntimeException(
            'This examination attempt is no longer active.'
        );
    }


    if (
        !in_array(
            (string) $attempt['exam_status'],
            [
                'Active',
                'Live'
            ],
            true
        )
    ) {

        throw new RuntimeException(
            'This examination is no longer available.'
        );
    }


    $requiredQuestionCount = (int) (
        $attempt['required_question_count'] ?? 0
    );


    if (
        $requiredQuestionCount <= 0
    ) {

        throw new RuntimeException(
            'The examination question configuration is invalid.'
        );
    }


    if (
        empty($attempt['server_deadline'])
    ) {

        throw new RuntimeException(
            'Examination deadline is missing.'
        );
    }


    try {

        $deadline = new DateTimeImmutable(
            (string) $attempt['server_deadline']
        );

    } catch (Throwable) {

        throw new RuntimeException(
            'Examination deadline is invalid.'
        );
    }


    $serverNow = new DateTimeImmutable();


    /*
     * Do not change the attempt status here when time expires.
     * The canonical submit endpoint will perform the final submission
     * and grading transaction.
     */
    if (
        $serverNow >= $deadline
    ) {

        $conn->rollBack();

        save_answer_response(
            false,
            'The examination time has expired.',
            [
                'expired' => true,
                'attempt_id' => (int) $attemptId,
                'question_id' => (int) $questionId,
                'server_time' =>
                    $serverNow->format(
                        DateTimeInterface::ATOM
                    ),
                'server_deadline' =>
                    $deadline->format(
                        DateTimeInterface::ATOM
                    ),
                'remaining_seconds' => 0
            ],
            409
        );
    }


    $questionCountStatement = $conn->prepare("
        SELECT
            COUNT(DISTINCT eq.question_id)
        FROM exam_questions eq
        INNER JOIN questions q
            ON q.id = eq.question_id
        WHERE
            eq.exam_id = ?
            AND q.status = 'Active'
    ");


    $questionCountStatement->execute(
        [
            (int) $attempt['exam_id']
        ]
    );


    $activeQuestionCount = (int) (
        $questionCountStatement->fetchColumn()
    );


    if (
        $activeQuestionCount
        !==
        $requiredQuestionCount
    ) {

        throw new RuntimeException(
            'The examination question set is no longer valid.'
        );
    }


    $questionStatement = $conn->prepare("
        SELECT
            q.id,
            q.status,
            q.marks
        FROM exam_questions eq
        INNER JOIN questions q
            ON q.id = eq.question_id
        WHERE
            eq.exam_id = ?
            AND eq.question_id = ?
        LIMIT 1
        FOR UPDATE
    ");


    $questionStatement->execute(
        [
            (int) $attempt['exam_id'],
            (int) $questionId
        ]
    );


    $question = $questionStatement->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$question) {

        throw new RuntimeException(
            'This question does not belong to the current examination.'
        );
    }


    if (
        (string) $question['status']
        !==
        'Active'
    ) {

        throw new RuntimeException(
            'This question is no longer active.'
        );
    }


    $questionMarks = (float) (
        $question['marks'] ?? 0
    );


    if ($questionMarks <= 0) {

        throw new RuntimeException(
            'This question has an invalid marks configuration.'
        );
    }


    $answerStatement = $conn->prepare("
        SELECT
            id,
            selected_answer,
            question_status
        FROM answers
        WHERE
            attempt_id = ?
            AND question_id = ?
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");


    $answerStatement->execute(
        [
            (int) $attemptId,
            (int) $questionId
        ]
    );


    $answer = $answerStatement->fetch(
        PDO::FETCH_ASSOC
    );


    $currentStatus = $answer
        ? (string) (
            $answer['question_status'] ?? ''
        )
        : 'Not Answered';


    $markedForReview = in_array(
        $currentStatus,
        [
            'Marked for Review',
            'Answered & Marked for Review'
        ],
        true
    );


    $newStatus = $markedForReview
        ? 'Answered & Marked for Review'
        : 'Answered';


    if ($answer) {

        $updateStatement = $conn->prepare("
            UPDATE answers
            SET
                selected_answer = ?,
                question_status = ?,
                answered_at = NOW(),
                is_correct = 0,
                marks_awarded = 0
            WHERE
                id = ?
                AND attempt_id = ?
                AND question_id = ?
        ");


        $updateStatement->execute(
            [
                $selectedAnswer,
                $newStatus,
                (int) $answer['id'],
                (int) $attemptId,
                (int) $questionId
            ]
        );

    } else {

        $insertStatement = $conn->prepare("
            INSERT INTO answers
            (
                attempt_id,
                question_id,
                selected_answer,
                question_status,
                answered_at,
                is_correct,
                marks_awarded
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                NOW(),
                0,
                0
            )
        ");


        $insertStatement->execute(
            [
                (int) $attemptId,
                (int) $questionId,
                $selectedAnswer,
                $newStatus
            ]
        );
    }


    $activityStatement = $conn->prepare("
        UPDATE exam_attempts
        SET
            last_activity_at = NOW()
        WHERE
            id = ?
            AND student_id = ?
            AND status = 'Started'
    ");


    $activityStatement->execute(
        [
            (int) $attemptId,
            $studentId
        ]
    );


    $conn->commit();


    save_answer_response(
        true,
        'Answer saved successfully.',
        [
            'attempt_id' =>
                (int) $attemptId,

            'question_id' =>
                (int) $questionId,

            'selected_answer' =>
                $selectedAnswer,

            'question_status' =>
                $newStatus,

            'has_answer' =>
                true,

            'question_marks' =>
                $questionMarks,

            'server_time' =>
                $serverNow->format(
                    DateTimeInterface::ATOM
                ),

            'server_deadline' =>
                $deadline->format(
                    DateTimeInterface::ATOM
                ),

            'remaining_seconds' =>
                max(
                    0,
                    $deadline->getTimestamp()
                    -
                    $serverNow->getTimestamp()
                )
        ]
    );


} catch (Throwable $exception) {

    if (
        $conn->inTransaction()
    ) {

        $conn->rollBack();
    }


    error_log(
        'ExamSphere save answer failed: ' .
        $exception->getMessage()
    );


    $safeMessages = [

        'Examination attempt not found.',

        'You are not allowed to modify this examination attempt.',

        'This examination attempt is no longer active.',

        'This examination is no longer available.',

        'The examination question configuration is invalid.',

        'Examination deadline is missing.',

        'Examination deadline is invalid.',

        'The examination time has expired.',

        'The examination question set is no longer valid.',

        'This question does not belong to the current examination.',

        'This question is no longer active.',

        'This question has an invalid marks configuration.'

    ];


    $message = in_array(
        $exception->getMessage(),
        $safeMessages,
        true
    )
        ? $exception->getMessage()
        : 'Unable to save your answer. Please try again.';


    $httpCode =
        $exception->getMessage()
        ===
        'The examination time has expired.'
            ? 409
            : 400;


    save_answer_response(
        false,
        $message,
        [],
        $httpCode
    );
}