<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

require_login('teacher');

$teacherId = current_user_id();

$message = '';
$error = '';

$subjects = [];
$topics = [];
$items = [];

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

$perPage = 12;

$search = trim(
    (string)($_GET['search'] ?? '')
);

$subjectFilter = filter_input(
    INPUT_GET,
    'subject_id',
    FILTER_VALIDATE_INT
);

if (
    $subjectFilter === false ||
    $subjectFilter === null ||
    $subjectFilter <= 0
) {
    $subjectFilter = null;
}

$topicFilter = filter_input(
    INPUT_GET,
    'topic_id',
    FILTER_VALIDATE_INT
);

if (
    $topicFilter === false ||
    $topicFilter === null ||
    $topicFilter <= 0
) {
    $topicFilter = null;
}

$statusFilter = trim(
    (string)($_GET['status'] ?? '')
);

if (
    !in_array(
        $statusFilter,
        ['', 'Active', 'Inactive'],
        true
    )
) {
    $statusFilter = '';
}

$difficultyFilter = trim(
    (string)($_GET['difficulty'] ?? '')
);

if (
    !in_array(
        $difficultyFilter,
        ['', 'Easy', 'Medium', 'Hard'],
        true
    )
) {
    $difficultyFilter = '';
}

function teacher_q_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function teacher_q_url(
    int $page,
    string $search,
    ?int $subjectId,
    ?int $topicId,
    string $status,
    string $difficulty
): string {
    $params = [
        'page' => $page
    ];

    if ($search !== '') {
        $params['search'] = $search;
    }

    if ($subjectId !== null) {
        $params['subject_id'] = $subjectId;
    }

    if ($topicId !== null) {
        $params['topic_id'] = $topicId;
    }

    if ($status !== '') {
        $params['status'] = $status;
    }

    if ($difficulty !== '') {
        $params['difficulty'] = $difficulty;
    }

    return '?' . http_build_query($params);
}

function teacher_q_upload_image(
    array $file
): array {
    if (
        !isset($file['error']) ||
        (int)$file['error'] !== UPLOAD_ERR_OK
    ) {
        throw new RuntimeException(
            'Invalid question image upload.'
        );
    }

    if (
        (int)($file['size'] ?? 0) <= 0 ||
        (int)$file['size'] > 3 * 1024 * 1024
    ) {
        throw new RuntimeException(
            'Question image must be between 1 byte and 3 MB.'
        );
    }

    $tmpName =
        (string)(
            $file['tmp_name'] ?? ''
        );

    if (
        $tmpName === '' ||
        !is_uploaded_file($tmpName)
    ) {
        throw new RuntimeException(
            'Invalid question image.'
        );
    }

    $finfo = new finfo(
        FILEINFO_MIME_TYPE
    );

    $mime =
        $finfo->file(
            $tmpName
        );

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (
        !isset(
            $allowed[$mime]
        )
    ) {
        throw new RuntimeException(
            'Only JPG, PNG and WEBP question images are allowed.'
        );
    }

    $directory =
        dirname(__DIR__) .
        DIRECTORY_SEPARATOR .
        'uploads' .
        DIRECTORY_SEPARATOR .
        'questions';

    if (
        !is_dir($directory) &&
        !mkdir(
            $directory,
            0755,
            true
        )
    ) {
        throw new RuntimeException(
            'Unable to prepare the question image folder.'
        );
    }

    if (
        !is_writable($directory)
    ) {
        throw new RuntimeException(
            'Question image folder is not writable.'
        );
    }

    $filename =
        'question_' .
        bin2hex(
            random_bytes(16)
        ) .
        '.' .
        $allowed[$mime];

    $target =
        $directory .
        DIRECTORY_SEPARATOR .
        $filename;

    if (
        !move_uploaded_file(
            $tmpName,
            $target
        )
    ) {
        throw new RuntimeException(
            'Unable to save question image.'
        );
    }

    return [
        'filename' => $filename,
        'target' => $target
    ];
}

function teacher_q_delete_image(
    string $filename
): void {
    if ($filename === '') {
        return;
    }

    $directory =
        dirname(__DIR__) .
        DIRECTORY_SEPARATOR .
        'uploads' .
        DIRECTORY_SEPARATOR .
        'questions';

    $root =
        realpath(
            $directory
        );

    if ($root === false) {
        return;
    }

    $candidate =
        realpath(
            $directory .
            DIRECTORY_SEPARATOR .
            basename($filename)
        );

    if (
        $candidate !== false &&
        str_starts_with(
            $candidate,
            $root . DIRECTORY_SEPARATOR
        ) &&
        is_file($candidate)
    ) {
        @unlink($candidate);
    }
}

function teacher_q_question_has_assignment(
    PDO $conn,
    int $questionId
): bool {
    $stmt = $conn->prepare(
        "
        SELECT COUNT(*)
        FROM exam_questions
        WHERE question_id = ?
        "
    );

    $stmt->execute([
        $questionId
    ]);

    return (int)(
        $stmt->fetchColumn() ?: 0
    ) > 0;
}

function teacher_q_question_has_answers(
    PDO $conn,
    int $questionId
): bool {
    $stmt = $conn->prepare(
        "
        SELECT COUNT(*)
        FROM answers
        WHERE question_id = ?
        "
    );

    $stmt->execute([
        $questionId
    ]);

    return (int)(
        $stmt->fetchColumn() ?: 0
    ) > 0;
}

/*
|--------------------------------------------------------------------------
| Subjects
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->query(
        "
        SELECT
            id,
            name,
            code
        FROM subjects
        WHERE status = 'Active'
        ORDER BY
            name ASC,
            id ASC
        "
    );

    $subjects =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Teacher question subjects failed: ' .
        $exception->getMessage()
    );

    $error =
        'Unable to load subjects.';
}

/*
|--------------------------------------------------------------------------
| POST actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (
            !verify_csrf_token(
                $_POST['csrf_token'] ?? null
            )
        ) {
            throw new RuntimeException(
                'Security verification failed. Refresh the page and try again.'
            );
        }

        $action =
            trim(
                (string)(
                    $_POST['action'] ?? ''
                )
            );

        /*
        |--------------------------------------------------------------------------
        | ADD / EDIT
        |--------------------------------------------------------------------------
        */

        if (
            $action === 'add' ||
            $action === 'edit'
        ) {

            $questionId =
                filter_var(
                    $_POST['question_id'] ?? '',
                    FILTER_VALIDATE_INT
                );

            if (
                $action === 'edit' &&
                (
                    $questionId === false ||
                    $questionId <= 0
                )
            ) {
                throw new RuntimeException(
                    'Invalid question.'
                );
            }

            $subjectId =
                filter_var(
                    $_POST['subject_id'] ?? '',
                    FILTER_VALIDATE_INT
                );

            $topicIdRaw =
                filter_var(
                    $_POST['topic_id'] ?? '',
                    FILTER_VALIDATE_INT
                );

            $topicId =
                (
                    $topicIdRaw !== false &&
                    $topicIdRaw !== null &&
                    $topicIdRaw > 0
                )
                    ? (int)$topicIdRaw
                    : null;

            $questionType =
                trim(
                    (string)(
                        $_POST['question_type'] ?? 'MCQ'
                    )
                );

            $questionText =
                trim(
                    (string)(
                        $_POST['question_text'] ?? ''
                    )
                );

            $optionA =
                trim(
                    (string)(
                        $_POST['option_a'] ?? ''
                    )
                );

            $optionB =
                trim(
                    (string)(
                        $_POST['option_b'] ?? ''
                    )
                );

            $optionC =
                trim(
                    (string)(
                        $_POST['option_c'] ?? ''
                    )
                );

            $optionD =
                trim(
                    (string)(
                        $_POST['option_d'] ?? ''
                    )
                );

            $correctAnswer =
                strtoupper(
                    trim(
                        (string)(
                            $_POST['correct_answer'] ?? ''
                        )
                    )
                );

            $explanation =
                trim(
                    (string)(
                        $_POST['explanation'] ?? ''
                    )
                );

            $marks =
                filter_var(
                    $_POST['marks'] ?? '',
                    FILTER_VALIDATE_FLOAT
                );

            $negativeMarks =
                filter_var(
                    $_POST['negative_marks'] ?? '0',
                    FILTER_VALIDATE_FLOAT
                );

            $estimatedTime =
                filter_var(
                    $_POST['estimated_time_seconds'] ?? '',
                    FILTER_VALIDATE_INT
                );

            $difficulty =
                trim(
                    (string)(
                        $_POST['difficulty'] ?? ''
                    )
                );

            $status =
                trim(
                    (string)(
                        $_POST['status'] ?? ''
                    )
                );

            if (
                $subjectId === false ||
                $subjectId <= 0
            ) {
                throw new RuntimeException(
                    'Please select a valid subject.'
                );
            }

            if (
                $questionText === '' ||
                mb_strlen($questionText) > 65535
            ) {
                throw new RuntimeException(
                    'Question text is required and must not exceed 65535 characters.'
                );
            }

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
                throw new RuntimeException(
                    'Invalid question type.'
                );
            }

            if (
                $questionType === 'TrueFalse'
            ) {
                $optionA = 'True';
                $optionB = 'False';
                $optionC = null;
                $optionD = null;

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
                    throw new RuntimeException(
                        'True/False questions must use A or B as the correct answer.'
                    );
                }
            } else {

                if (
                    $optionA === '' ||
                    $optionB === '' ||
                    $optionC === '' ||
                    $optionD === ''
                ) {
                    throw new RuntimeException(
                        'MCQ requires options A, B, C and D.'
                    );
                }

                if (
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
                    throw new RuntimeException(
                        'Correct answer must be A, B, C or D.'
                    );
                }
            }

            if (
                $marks === false ||
                $marks === null ||
                $marks <= 0
            ) {
                throw new RuntimeException(
                    'Marks must be greater than zero.'
                );
            }

            if (
                $negativeMarks === false ||
                $negativeMarks === null ||
                $negativeMarks < 0 ||
                $negativeMarks > $marks
            ) {
                throw new RuntimeException(
                    'Negative marks must be between 0 and the question marks.'
                );
            }

            if (
                $estimatedTime !== false &&
                $estimatedTime !== null &&
                $estimatedTime < 0
            ) {
                throw new RuntimeException(
                    'Estimated time cannot be negative.'
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
                    'Invalid difficulty.'
                );
            }

            if (
                !in_array(
                    $status,
                    [
                        'Active',
                        'Inactive'
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Invalid question status.'
                );
            }

            $subjectCheck =
                $conn->prepare(
                    "
                    SELECT id
                    FROM subjects
                    WHERE
                        id = ?
                        AND status = 'Active'
                    LIMIT 1
                    "
                );

            $subjectCheck->execute([
                (int)$subjectId
            ]);

            if (!$subjectCheck->fetchColumn()) {
                throw new RuntimeException(
                    'Selected subject is not available.'
                );
            }

            if ($topicId !== null) {

                $topicCheck =
                    $conn->prepare(
                        "
                        SELECT
                            id,
                            subject_id,
                            status
                        FROM topics
                        WHERE id = ?
                        LIMIT 1
                        "
                    );

                $topicCheck->execute([
                    $topicId
                ]);

                $topic =
                    $topicCheck->fetch(
                        PDO::FETCH_ASSOC
                    );

                if (!$topic) {
                    throw new RuntimeException(
                        'Selected topic does not exist.'
                    );
                }

                if (
                    (int)$topic['subject_id'] !==
                    (int)$subjectId
                ) {
                    throw new RuntimeException(
                        'Selected topic does not belong to the selected subject.'
                    );
                }

                if (
                    (string)$topic['status'] !==
                    'Active'
                ) {
                    throw new RuntimeException(
                        'Selected topic is inactive.'
                    );
                }
            }

            if ($action === 'edit') {

                $existingStmt =
                    $conn->prepare(
                        "
                        SELECT
                            id,
                            subject_id,
                            topic_id,
                            question_type,
                            question_text,
                            question_image
                        FROM questions
                        WHERE
                            id = ?
                            AND created_by_teacher_id = ?
                        LIMIT 1
                        "
                    );

                $existingStmt->execute([
                    (int)$questionId,
                    $teacherId
                ]);

                $existing =
                    $existingStmt->fetch(
                        PDO::FETCH_ASSOC
                    );

                if (!$existing) {
                    throw new RuntimeException(
                        'Question not found or access denied.'
                    );
                }

                if (
                    teacher_q_question_has_answers(
                        $conn,
                        (int)$questionId
                    )
                ) {
                    throw new RuntimeException(
                        'This question cannot be edited because it has already been used in a student attempt.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Duplicate protection excluding current question.
                |--------------------------------------------------------------------------
                */

                $duplicateCheck =
                    $conn->prepare(
                        "
                        SELECT id
                        FROM questions
                        WHERE
                            created_by_teacher_id = ?
                            AND question_text = ?
                            AND id <> ?
                        LIMIT 1
                        "
                    );

                $duplicateCheck->execute([
                    $teacherId,
                    $questionText,
                    (int)$questionId
                ]);

                if ($duplicateCheck->fetchColumn()) {
                    throw new RuntimeException(
                        'Another question with the same text already exists in your question bank.'
                    );
                }

            } else {

                $duplicateCheck =
                    $conn->prepare(
                        "
                        SELECT id
                        FROM questions
                        WHERE
                            created_by_teacher_id = ?
                            AND question_text = ?
                        LIMIT 1
                        "
                    );

                $duplicateCheck->execute([
                    $teacherId,
                    $questionText
                ]);

                if ($duplicateCheck->fetchColumn()) {
                    throw new RuntimeException(
                        'A question with the same text already exists in your question bank.'
                    );
                }
            }

            $newImageFilename =
                null;

            $oldImageFilename =
                null;

            $uploadedImagePath =
                null;

            if (
                isset(
                    $_FILES['question_image']
                ) &&
                (int)(
                    $_FILES[
                        'question_image'
                    ]['error']
                    ??
                    UPLOAD_ERR_NO_FILE
                ) !== UPLOAD_ERR_NO_FILE
            ) {

                $upload =
                    teacher_q_upload_image(
                        $_FILES[
                            'question_image'
                        ]
                    );

                $newImageFilename =
                    $upload['filename'];

                $uploadedImagePath =
                    $upload['target'];
            }

            try {

                if ($action === 'add') {

                    $insert =
                        $conn->prepare(
                            "
                            INSERT INTO questions
                            (
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
                            VALUES
                            (
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
                            )
                            "
                        );

                    $insert->execute([
                        (int)$subjectId,
                        $topicId,
                        $teacherId,
                        $questionType,
                        $questionText,
                        $newImageFilename,
                        $optionA,
                        $optionB,
                        $optionC,
                        $optionD,
                        $correctAnswer,
                        $explanation !== ''
                            ? $explanation
                            : null,
                        (float)$marks,
                        (float)$negativeMarks,
                        (
                            $estimatedTime !== false &&
                            $estimatedTime !== null &&
                            $estimatedTime > 0
                        )
                            ? (int)$estimatedTime
                            : null,
                        $difficulty,
                        $status
                    ]);

                    $message =
                        'Question added successfully.';

                } else {

                    $oldImageFilename =
                        (string)(
                            $existing[
                                'question_image'
                            ] ?? ''
                        );

                    $finalImage =
                        $newImageFilename !== null
                            ? $newImageFilename
                            : (
                                $oldImageFilename !== ''
                                    ? $oldImageFilename
                                    : null
                              );

                    $update =
                        $conn->prepare(
                            "
                            UPDATE questions
                            SET
                                subject_id = ?,
                                topic_id = ?,
                                question_type = ?,
                                question_text = ?,
                                question_image = ?,
                                option_a = ?,
                                option_b = ?,
                                option_c = ?,
                                option_d = ?,
                                correct_answer = ?,
                                explanation = ?,
                                marks = ?,
                                negative_marks = ?,
                                estimated_time_seconds = ?,
                                difficulty = ?,
                                status = ?
                            WHERE
                                id = ?
                                AND created_by_teacher_id = ?
                            "
                        );

                    $update->execute([
                        (int)$subjectId,
                        $topicId,
                        $questionType,
                        $questionText,
                        $finalImage,
                        $optionA,
                        $optionB,
                        $optionC,
                        $optionD,
                        $correctAnswer,
                        $explanation !== ''
                            ? $explanation
                            : null,
                        (float)$marks,
                        (float)$negativeMarks,
                        (
                            $estimatedTime !== false &&
                            $estimatedTime !== null &&
                            $estimatedTime > 0
                        )
                            ? (int)$estimatedTime
                            : null,
                        $difficulty,
                        $status,
                        (int)$questionId,
                        $teacherId
                    ]);

                    if (
                        $newImageFilename !== null &&
                        $oldImageFilename !== ''
                    ) {
                        teacher_q_delete_image(
                            $oldImageFilename
                        );
                    }

                    $message =
                        'Question updated successfully.';
                }

            } catch (Throwable $databaseException) {

                if (
                    $uploadedImagePath !== null &&
                    is_file(
                        $uploadedImagePath
                    )
                ) {
                    @unlink(
                        $uploadedImagePath
                    );
                }

                throw $databaseException;
            }

        /*
        |--------------------------------------------------------------------------
        | TOGGLE STATUS
        |--------------------------------------------------------------------------
        */

        } elseif (
            $action === 'toggle_status'
        ) {

            $questionId =
                filter_var(
                    $_POST['question_id'] ?? '',
                    FILTER_VALIDATE_INT
                );

            if (
                $questionId === false ||
                $questionId <= 0
            ) {
                throw new RuntimeException(
                    'Invalid question.'
                );
            }

            $stmt =
                $conn->prepare(
                    "
                    SELECT
                        status
                    FROM questions
                    WHERE
                        id = ?
                        AND created_by_teacher_id = ?
                    LIMIT 1
                    "
                );

            $stmt->execute([
                (int)$questionId,
                $teacherId
            ]);

            $currentStatus =
                $stmt->fetchColumn();

            if ($currentStatus === false) {
                throw new RuntimeException(
                    'Question not found or access denied.'
                );
            }

            $nextStatus =
                (string)$currentStatus ===
                'Active'
                    ? 'Inactive'
                    : 'Active';

            $update =
                $conn->prepare(
                    "
                    UPDATE questions
                    SET status = ?
                    WHERE
                        id = ?
                        AND created_by_teacher_id = ?
                    "
                );

            $update->execute([
                $nextStatus,
                (int)$questionId,
                $teacherId
            ]);

            $message =
                'Question status changed to ' .
                $nextStatus .
                '.';

        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        } elseif (
            $action === 'delete'
        ) {

            $questionId =
                filter_var(
                    $_POST['question_id'] ?? '',
                    FILTER_VALIDATE_INT
                );

            if (
                $questionId === false ||
                $questionId <= 0
            ) {
                throw new RuntimeException(
                    'Invalid question.'
                );
            }

            $stmt =
                $conn->prepare(
                    "
                    SELECT
                        question_image
                    FROM questions
                    WHERE
                        id = ?
                        AND created_by_teacher_id = ?
                    LIMIT 1
                    "
                );

            $stmt->execute([
                (int)$questionId,
                $teacherId
            ]);

            $question =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!$question) {
                throw new RuntimeException(
                    'Question not found or access denied.'
                );
            }

            if (
                teacher_q_question_has_assignment(
                    $conn,
                    (int)$questionId
                )
            ) {
                throw new RuntimeException(
                    'This question cannot be deleted because it is already assigned to an examination.'
                );
            }

            $delete =
                $conn->prepare(
                    "
                    DELETE FROM questions
                    WHERE
                        id = ?
                        AND created_by_teacher_id = ?
                    "
                );

            $delete->execute([
                (int)$questionId,
                $teacherId
            ]);

            if (
                $delete->rowCount() !== 1
            ) {
                throw new RuntimeException(
                    'Question could not be deleted.'
                );
            }

            teacher_q_delete_image(
                (string)(
                    $question[
                        'question_image'
                    ] ?? ''
                )
            );

            $message =
                'Question deleted successfully.';

        } else {

            throw new RuntimeException(
                'Invalid question-management action.'
            );
        }

    } catch (Throwable $exception) {

        error_log(
            'ExamSphere teacher question action failed: ' .
            $exception->getMessage()
        );

        $error =
            $exception->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Topics for selected filter
|--------------------------------------------------------------------------
*/

if (
    $subjectFilter !== null
) {

    try {

        $topicStmt =
            $conn->prepare(
                "
                SELECT
                    id,
                    name
                FROM topics
                WHERE
                    subject_id = ?
                    AND status = 'Active'
                ORDER BY
                    name ASC,
                    id ASC
                "
            );

        $topicStmt->execute([
            $subjectFilter
        ]);

        $topics =
            $topicStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    } catch (Throwable $exception) {

        error_log(
            'Teacher question filter topics failed: ' .
            $exception->getMessage()
        );
    }
}

/*
|--------------------------------------------------------------------------
| List + pagination
|--------------------------------------------------------------------------
*/

$where = [
    'q.created_by_teacher_id = ?'
];

$params = [
    $teacherId
];

if ($search !== '') {

    $where[] =
        "
        (
            q.question_text LIKE ?
            OR q.explanation LIKE ?
        )
        ";

    $searchValue =
        '%' .
        $search .
        '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($subjectFilter !== null) {

    $where[] =
        'q.subject_id = ?';

    $params[] =
        $subjectFilter;
}

if ($topicFilter !== null) {

    $where[] =
        'q.topic_id = ?';

    $params[] =
        $topicFilter;
}

if ($statusFilter !== '') {

    $where[] =
        'q.status = ?';

    $params[] =
        $statusFilter;
}

if ($difficultyFilter !== '') {

    $where[] =
        'q.difficulty = ?';

    $params[] =
        $difficultyFilter;
}

$whereSql =
    'WHERE ' .
    implode(
        ' AND ',
        $where
    );

$totalRows = 0;
$totalPages = 1;

try {

    $countStmt =
        $conn->prepare(
            "
            SELECT
                COUNT(*)
            FROM questions q
            $whereSql
            "
        );

    $countStmt->execute(
        $params
    );

    $totalRows =
        (int)(
            $countStmt->fetchColumn()
            ?: 0
        );

    $totalPages =
        max(
            1,
            (int)ceil(
                $totalRows /
                $perPage
            )
        );

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset =
        (
            $page - 1
        ) *
        $perPage;

    $listStmt =
        $conn->prepare(
            "
            SELECT

                q.id,
                q.subject_id,
                q.topic_id,
                q.question_type,
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
                q.status,
                q.created_at,

                s.name AS subject_name,
                t.name AS topic_name,

                (
                    SELECT COUNT(*)
                    FROM exam_questions eq
                    WHERE eq.question_id = q.id
                ) AS assignment_count,

                (
                    SELECT COUNT(*)
                    FROM answers a
                    WHERE a.question_id = q.id
                ) AS answer_count

            FROM questions q

            LEFT JOIN subjects s
                ON s.id = q.subject_id

            LEFT JOIN topics t
                ON t.id = q.topic_id

            $whereSql

            ORDER BY
                q.created_at DESC,
                q.id DESC

            LIMIT $perPage
            OFFSET $offset
            "
        );

    $listStmt->execute(
        $params
    );

    $items =
        $listStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'ExamSphere teacher question list failed: ' .
        $exception->getMessage()
    );

    $items = [];
    $totalRows = 0;
    $totalPages = 1;

    if ($error === '') {
        $error =
            'Unable to load your question bank.';
    }
}

/*
|--------------------------------------------------------------------------
| Page metrics (based on all matching records only for total;
| active/inactive count for visible page)
|--------------------------------------------------------------------------
*/

$activeOnPage = 0;
$inactiveOnPage = 0;

foreach ($items as $item) {

    if (
        (string)$item['status'] ===
        'Active'
    ) {
        $activeOnPage++;
    } else {
        $inactiveOnPage++;
    }
}

?>
<!doctype html>
<html lang="en">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>
    Question Bank | ExamSphere
</title>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="../assets/css/portal.css"
>

<style>

:root{
    --earth:#5d4037;
    --earth-dark:#422d26;
    --olive:#556b2f;
    --page:#f6f3eb;
    --text:#352c27;
    --muted:#867a72;
    --line:#e7dfd5;
    --soft:#fbfaf6;
    --green:#4f7040;
    --red:#995048;
    --amber:#99743a;
}

*{
    box-sizing:border-box;
}

body.portal-body{
    margin:0;
    font-family:'Poppins',sans-serif;
    color:var(--text);
    background:
        radial-gradient(
            circle at 8% 0%,
            rgba(85,107,47,.08),
            transparent 26%
        ),
        linear-gradient(
            135deg,
            #faf8f2,
            #efebe4
        );
}

.page{
    width:min(
        1450px,
        calc(100vw - 22px)
    );
    margin:0 auto;
    padding:20px 0 60px;
}

.page-head{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:20px;
    margin-bottom:18px;
}

.kicker{
    color:var(--olive);
    font-size:.72rem;
    font-weight:900;
    letter-spacing:.14em;
}

.page-head h1{
    margin:7px 0 4px;
    color:var(--earth);
    font-size:2.15rem;
    font-weight:900;
    letter-spacing:-.035em;
}

.page-head p{
    margin:0;
    color:var(--muted);
    font-size:.72rem;
}

.add-button{
    border:0;
    border-radius:11px;
    min-height:42px;
    padding:0 15px;
    background:var(--earth);
    color:#fff;
    font-size:.61rem;
    font-weight:850;
}

.add-button:hover{
    background:var(--earth-dark);
    color:#fff;
}

.alert{
    border-radius:12px;
    font-size:.59rem;
}

.metrics{
    display:grid;
    grid-template-columns:
        repeat(3,minmax(0,1fr));
    gap:11px;
    margin-bottom:18px;
}

.metric{
    min-height:84px;
    padding:14px;
    border:1px solid var(--line);
    border-radius:15px;
    background:rgba(255,255,255,.88);
    box-shadow:
        0 10px 24px rgba(62,45,37,.045);
}

.metric small{
    display:block;
    color:var(--muted);
    font-size:.61rem;
    font-weight:750;
}

.metric strong{
    display:block;
    margin-top:4px;
    color:var(--earth);
    font-size:1.18rem;
    font-weight:900;
}

.metric.active strong{
    color:var(--green);
}

.metric.inactive strong{
    color:var(--red);
}

.card{
    overflow:hidden;
    border:1px solid rgba(93,64,55,.08);
    border-radius:20px;
    background:rgba(255,255,255,.90);
    box-shadow:
        0 18px 44px rgba(62,45,37,.07);
}

.filter{
    display:grid;
    grid-template-columns:
        minmax(230px,1.4fr)
        minmax(160px,1fr)
        minmax(150px,1fr)
        minmax(150px,1fr)
        minmax(140px,1fr)
        90px;
    gap:8px;
    padding:15px;
    border-bottom:1px solid var(--line);
    background:#fcfaf6;
    align-items:end;
}

.label{
    display:block;
    margin-bottom:5px;
    color:var(--earth);
    font-size:.61rem;
    font-weight:850;
}

.control{
    width:100%;
    min-height:46px;
    border:1px solid #d8cec3;
    border-radius:10px;
    font-size:.66rem;
}

.filter-btn{
    width:100%;
    min-height:40px;
    border:0;
    border-radius:10px;
    background:var(--earth);
    color:#fff;
    font-size:.63rem;
    font-weight:850;
}

.clear-btn{
    width:100%;
    min-height:40px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:1px solid var(--line);
    border-radius:10px;
    background:#fff;
    color:var(--earth);
    text-decoration:none;
    font-size:.63rem;
}

.table-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    padding:17px 18px;
    border-bottom:1px solid var(--line);
}

.table-head h2{
    margin:0;
    color:var(--earth);
    font-size:1.02rem;
    font-weight:900;
}

.table-head p{
    margin:4px 0 0;
    color:var(--muted);
    font-size:.70rem;
}

.count{
    padding:7px 9px;
    border-radius:999px;
    background:#f3eee6;
    color:var(--earth);
    font-size:.61rem;
    font-weight:850;
}

.table-wrap{
    overflow:auto;
}

.question-table{
    width:100%;
    min-width:1180px;
    border-collapse:collapse;
}

.question-table th{
    padding:13px 14px;
    border-bottom:1px solid var(--line);
    background:#f7f3eb;
    color:#75695f;
    text-align:left;
    font-size:.56rem;
    font-weight:900;
    letter-spacing:.06em;
    text-transform:uppercase;
    white-space:nowrap;
}

.question-table td{
    padding:15px 14px;
    border-bottom:1px solid #eee8e0;
    vertical-align:middle;
    font-size:.63rem;
}

.question-table tr:last-child td{
    border-bottom:0;
}

.question-table tbody tr:nth-child(even) td{
    background:#fdfbf7;
}

.question-table tbody tr:hover td{
    background:#f7f3eb;
}

.question-table th:first-child{
    width:38%;
}

.question-table th:last-child{
    width:12%;
}

.table-head h2{
    font-size:1.05rem;
}

.table-head p{
    font-size:.62rem;
}

.label{
    font-size:.61rem;
}

.control{
    font-size:.65rem;
}

.modal-label{
    font-size:.62rem;
}

.modal-control{
    min-height:46px;
    font-size:.65rem;
}

.q-title{
    display:block;
    max-width:480px;
    color:var(--earth);
    font-size:.70rem;
    font-weight:850;
    line-height:1.7;
}

.q-meta{
    margin-top:4px;
    color:var(--muted);
    font-size:.56rem;
}

.badge{
    display:inline-flex;
    align-items:center;
    gap:4px;
    padding:5px 7px;
    border-radius:999px;
    font-size:.54rem;
    font-weight:850;
    white-space:nowrap;
}

.badge-active{
    background:#eaf3e7;
    color:var(--green);
}

.badge-inactive{
    background:#f9eae7;
    color:var(--red);
}

.badge-easy{
    background:#edf5e9;
    color:var(--green);
}

.badge-medium{
    background:#fff2d9;
    color:var(--amber);
}

.badge-hard{
    background:#fae8e6;
    color:var(--red);
}

.action-group{
    display:flex;
    flex-wrap:wrap;
    gap:5px;
}

.action{
    width:36px;
    height:36px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:0;
    border-radius:8px;
    text-decoration:none;
    font-size:.61rem;
}

.action-view{
    background:#f0ebe3;
    color:var(--earth);
}

.action-edit{
    background:#edf3e9;
    color:var(--olive);
}

.action-status{
    background:#f4efe6;
    color:#77685d;
}

.action-delete{
    background:#fae8e6;
    color:var(--red);
}

.pagination-bar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:13px 16px;
    border-top:1px solid var(--line);
    background:#fcfaf6;
}

.page-info{
    color:var(--muted);
    font-size:.61rem;
    font-weight:700;
}

.pages{
    display:flex;
    gap:5px;
}

.page-link{
    width:29px;
    height:29px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:1px solid var(--line);
    border-radius:7px;
    background:#fff;
    color:var(--earth);
    text-decoration:none;
    font-size:.58rem;
    font-weight:850;
}

.page-link:hover,
.page-link.active{
    border-color:var(--earth);
    background:var(--earth);
    color:#fff;
}

.page-link.disabled{
    pointer-events:none;
    opacity:.4;
}

.modal-content{
    border:0;
    border-radius:18px;
    overflow:hidden;
    box-shadow:
        0 25px 70px rgba(49,36,29,.20);
}

.modal-header{
    border-bottom:1px solid var(--line);
    background:#f7f2e9;
}

.modal-title{
    color:var(--earth);
    font-size:.92rem;
    font-weight:900;
}

.modal-sub{
    margin-top:3px;
    color:var(--muted);
    font-size:.51rem;
}

.modal-body{
    padding:20px;
}

.modal-grid{
    display:grid;
    grid-template-columns:
        repeat(2,minmax(0,1fr));
    gap:13px;
}

.modal-label{
    display:block;
    margin-bottom:5px;
    color:var(--earth);
    font-size:.70rem;
    font-weight:850;
}

.modal-control{
    width:100%;
    min-height:43px;
    border:1px solid #ddd4cb;
    border-radius:10px;
    padding:8px 10px;
    font-size:.72rem;
}

.modal-control:focus{
    border-color:var(--olive);
    box-shadow:
        0 0 0 .2rem rgba(85,107,47,.10);
}

.modal-full{
    grid-column:1 / -1;
}

textarea.modal-control{
    min-height:88px;
    resize:vertical;
}

.modal-actions{
    display:flex;
    justify-content:flex-end;
    gap:8px;
    margin-top:17px;
    padding-top:15px;
    border-top:1px solid var(--line);
}

.btn-save{
    border:0;
    border-radius:10px;
    min-height:41px;
    padding:0 15px;
    background:var(--earth);
    color:#fff;
    font-size:.59rem;
    font-weight:850;
}

.btn-save:hover{
    background:var(--earth-dark);
    color:#fff;
}

@media(max-width:1100px){

    .filter{
        grid-template-columns:
            repeat(3,minmax(0,1fr));
    }

}

@media(max-width:700px){

    .page{
        width:calc(100vw - 12px);
    }

    .page-head{
        align-items:flex-start;
        flex-direction:column;
    }

    .page-head h1{
        font-size:1.75rem;
    }

    .metrics{
        grid-template-columns:1fr;
    }

    .filter{
        grid-template-columns:1fr;
    }

    .modal-grid{
        grid-template-columns:1fr;
    }

    .modal-full{
        grid-column:auto;
    }

    .pagination-bar{
        align-items:flex-start;
        flex-direction:column;
    }

}


/* =========================================================
   READABILITY UPGRADE — KEEP EXISTING DESIGN
   ========================================================= */
body.portal-body{font-size:16px;}
.page-head h1{font-size:2.35rem;font-weight:900;}
.page-head p{font-size:.92rem;font-weight:600;color:#6f625a;}
.kicker{font-size:.82rem;font-weight:900;}
.add-button{font-size:.78rem;font-weight:900;min-height:46px;padding:0 18px;}
.alert{font-size:.78rem;font-weight:700;padding:13px 15px;}
.metric small{font-size:.78rem;font-weight:800;color:#6f625a;}
.metric strong{font-size:1.55rem;font-weight:900;}
.label{font-size:.78rem!important;font-weight:850!important;color:#493b33!important;}
.control,.form-control,.form-select{font-size:.82rem!important;font-weight:600!important;min-height:46px;}
.control::placeholder,.form-control::placeholder{font-size:.82rem;font-weight:500;}
.table-head h2{font-size:1.15rem!important;font-weight:900!important;}
.table-head p{font-size:.78rem!important;font-weight:600!important;}
.count{font-size:.74rem!important;font-weight:850!important;}
.question-table thead th{font-size:.72rem!important;font-weight:900!important;letter-spacing:.04em;}
.question-table tbody td{font-size:.82rem!important;font-weight:600!important;line-height:1.55;}
.question-table tbody td:first-child{font-weight:800!important;}
.question-table tbody td strong{font-weight:850!important;}
.status,.badge{font-size:.72rem!important;font-weight:850!important;}
.action-btn,.btn,.filter-btn,.clear-btn{font-size:.76rem!important;font-weight:850!important;}
.pagination a,.pagination span{font-size:.76rem!important;font-weight:800!important;}
.empty-state h3{font-size:1.05rem!important;font-weight:900!important;}
.empty-state p{font-size:.78rem!important;font-weight:600!important;line-height:1.65;}

@media(max-width:700px){
 .page-head h1{font-size:1.9rem;}
 .page-head p{font-size:.84rem;}
 .question-table tbody td{font-size:.78rem!important;}
}

</style>

</head>

<body class="portal-body">

<div class="portal-layout">

<?php include 'includes/sidebar.php'; ?>

<main class="portal-main">

<div class="page">

<header class="page-head">

<div>

<div class="kicker">

<i class="fa-solid fa-circle-question me-1"></i>

QUESTION BANK

</div>

<h1>
    Manage Questions
</h1>

<p>
    Create, edit, activate, deactivate and remove your own questions.
</p>

</div>


<button
    type="button"
    class="btn add-button"
    data-bs-toggle="modal"
    data-bs-target="#questionModal"
    id="openAddQuestion"
>

<i class="fa-solid fa-plus me-1"></i>

Add Question

</button>

</header>


<?php if ($message !== ''): ?>

<div class="alert alert-success mb-3">

<i class="fa-solid fa-circle-check me-1"></i>

<?= teacher_q_e(
    $message
) ?>

</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div class="alert alert-danger mb-3">

<i class="fa-solid fa-circle-exclamation me-1"></i>

<?= teacher_q_e(
    $error
) ?>

</div>

<?php endif; ?>


<section class="metrics">

<div class="metric">

<small>
    Matching Questions
</small>

<strong>
    <?= (int)$totalRows ?>
</strong>

</div>


<div class="metric active">

<small>
    Active on Page
</small>

<strong>
    <?= (int)$activeOnPage ?>
</strong>

</div>


<div class="metric inactive">

<small>
    Inactive on Page
</small>

<strong>
    <?= (int)$inactiveOnPage ?>
</strong>

</div>

</section>


<section class="card">


<form
    method="get"
    class="filter"
>

<div>

<label
    class="label"
    for="search"
>
    Search
</label>

<input
    id="search"
    type="search"
    name="search"
    class="form-control control"
    value="<?= teacher_q_e(
        $search
    ) ?>"
    placeholder="Question text or explanation..."
>

</div>


<div>

<label
    class="label"
    for="subject_id"
>
    Subject
</label>

<select
    id="subject_id"
    name="subject_id"
    class="form-select control"
>

<option value="">
    All subjects
</option>

<?php foreach (
    $subjects as $subject
): ?>

<option
    value="<?= (int)$subject['id'] ?>"
    <?= $subjectFilter !== null &&
        $subjectFilter ===
        (int)$subject['id']
        ? 'selected'
        : ''
    ?>
>

<?= teacher_q_e(
    $subject['name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div>

<label
    class="label"
    for="topic_id"
>
    Topic
</label>

<select
    id="topic_id"
    name="topic_id"
    class="form-select control"
>

<option value="">
    All topics
</option>

<?php foreach (
    $topics as $topic
): ?>

<option
    value="<?= (int)$topic['id'] ?>"
    <?= $topicFilter !== null &&
        $topicFilter ===
        (int)$topic['id']
        ? 'selected'
        : ''
    ?>
>

<?= teacher_q_e(
    $topic['name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div>

<label
    class="label"
    for="difficulty"
>
    Difficulty
</label>

<select
    id="difficulty"
    name="difficulty"
    class="form-select control"
>

<option value="">
    All difficulty
</option>

<?php foreach (
    [
        'Easy',
        'Medium',
        'Hard'
    ] as $difficulty
): ?>

<option
    value="<?= teacher_q_e(
        $difficulty
    ) ?>"
    <?= $difficultyFilter ===
        $difficulty
        ? 'selected'
        : ''
    ?>
>

<?= teacher_q_e(
    $difficulty
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div>

<label
    class="label"
    for="status"
>
    Status
</label>

<select
    id="status"
    name="status"
    class="form-select control"
>

<option value="">
    All status
</option>

<option
    value="Active"
    <?= $statusFilter === 'Active'
        ? 'selected'
        : ''
    ?>
>
    Active
</option>

<option
    value="Inactive"
    <?= $statusFilter === 'Inactive'
        ? 'selected'
        : ''
    ?>
>
    Inactive
</option>

</select>

</div>


<div>

<label class="label">
    &nbsp;
</label>

<div class="d-flex gap-1">

<button
    type="submit"
    class="btn filter-btn"
>

<i class="fa-solid fa-filter"></i>

</button>

<a
    href="questions.php"
    class="clear-btn"
    title="Clear filters"
>

<i class="fa-solid fa-xmark"></i>

</a>

</div>

</div>

</form>


<header class="table-head">

<div>

<h2>
    Your Questions
</h2>

<p>
    Only questions created by the logged-in teacher are displayed.
</p>

</div>

<span class="count">

<?= (int)$totalRows ?>

question<?= $totalRows === 1 ? '' : 's' ?>

</span>

</header>


<?php if ($items): ?>

<div class="table-wrap">

<table class="question-table">

<thead>

<tr>

<th>
    Question
</th>

<th>
    Subject
</th>

<th>
    Difficulty
</th>

<th>
    Marks
</th>

<th>
    Usage
</th>

<th>
    Status
</th>

<th>
    Actions
</th>

</tr>

</thead>


<tbody>

<?php foreach (
    $items as $item
): ?>

<tr>

<td>

<span class="q-title">

<?= teacher_q_e(
    $item['question_text']
) ?>

</span>

<span class="q-meta">

<?= teacher_q_e(
    $item['question_type']
) ?>

&nbsp; · &nbsp;

Answer:
<?= teacher_q_e(
    $item['correct_answer']
) ?>

<?php if (
    !empty(
        $item['topic_name']
    )
): ?>

&nbsp; · &nbsp;

<?= teacher_q_e(
    $item['topic_name']
) ?>

<?php endif; ?>

</span>

</td>


<td>

<?= teacher_q_e(
    $item['subject_name']
        ?? '—'
) ?>

</td>


<td>

<?php
$difficultyClass =
    match (
        (string)$item['difficulty']
    ) {
        'Easy' => 'badge-easy',
        'Hard' => 'badge-hard',
        default => 'badge-medium'
    };
?>

<span
    class="badge <?= $difficultyClass ?>"
>

<?= teacher_q_e(
    $item['difficulty']
) ?>

</span>

</td>


<td>

<strong>

<?= number_format(
    (float)$item['marks'],
    2
) ?>

</strong>

<div class="q-meta">

Negative:
<?= number_format(
    (float)$item['negative_marks'],
    2
) ?>

</div>

</td>


<td>

<div>

<?= (int)$item['assignment_count'] ?>

exam<?= (int)$item['assignment_count'] === 1 ? '' : 's' ?>

</div>

<div class="q-meta">

<?= (int)$item['answer_count'] ?>

answer<?= (int)$item['answer_count'] === 1 ? '' : 's' ?>

</div>

</td>


<td>

<span
    class="badge <?= $item['status'] === 'Active'
        ? 'badge-active'
        : 'badge-inactive'
    ?>"
>

<?= teacher_q_e(
    $item['status']
) ?>

</span>

</td>


<td>

<div class="action-group">


<?php if (
    !empty(
        $item['question_image']
    )
): ?>

<a
    class="action action-view"
    href="../uploads/questions/<?= teacher_q_e(
        basename(
            (string)$item['question_image']
        )
    ) ?>"
    target="_blank"
    rel="noopener noreferrer"
    title="View image"
>

<i class="fa-solid fa-image"></i>

</a>

<?php endif; ?>


<button
    type="button"
    class="action action-edit edit-question-btn"
    data-bs-toggle="modal"
    data-bs-target="#questionModal"
    data-id="<?= (int)$item['id'] ?>"
    data-subject="<?= (int)$item['subject_id'] ?>"
    data-topic="<?= (int)$item['topic_id'] ?>"
    data-type="<?= teacher_q_e(
        $item['question_type']
    ) ?>"
    data-text="<?= teacher_q_e(
        $item['question_text']
    ) ?>"
    data-option-a="<?= teacher_q_e(
        $item['option_a']
    ) ?>"
    data-option-b="<?= teacher_q_e(
        $item['option_b']
    ) ?>"
    data-option-c="<?= teacher_q_e(
        $item['option_c']
    ) ?>"
    data-option-d="<?= teacher_q_e(
        $item['option_d']
    ) ?>"
    data-correct="<?= teacher_q_e(
        $item['correct_answer']
    ) ?>"
    data-explanation="<?= teacher_q_e(
        $item['explanation']
    ) ?>"
    data-marks="<?= teacher_q_e(
        $item['marks']
    ) ?>"
    data-negative="<?= teacher_q_e(
        $item['negative_marks']
    ) ?>"
    data-time="<?= teacher_q_e(
        $item['estimated_time_seconds']
    ) ?>"
    data-difficulty="<?= teacher_q_e(
        $item['difficulty']
    ) ?>"
    data-status="<?= teacher_q_e(
        $item['status']
    ) ?>"
    title="Edit question"
>

<i class="fa-solid fa-pen"></i>

</button>


<form
    method="post"
    class="d-inline"
    onsubmit="return confirm('Change this question status?');"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="action"
    value="toggle_status"
>

<input
    type="hidden"
    name="question_id"
    value="<?= (int)$item['id'] ?>"
>

<button
    type="submit"
    class="action action-status"
    title="Toggle status"
>

<i class="fa-solid fa-power-off"></i>

</button>

</form>


<form
    method="post"
    class="d-inline"
    onsubmit="return confirm('Delete this question permanently?');"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="action"
    value="delete"
>

<input
    type="hidden"
    name="question_id"
    value="<?= (int)$item['id'] ?>"
>

<button
    type="submit"
    class="action action-delete"
    title="Delete question"
    <?= (
        (int)$item['assignment_count'] > 0 ||
        (int)$item['answer_count'] > 0
    ) ? 'disabled' : '' ?>
>

<i class="fa-solid fa-trash"></i>

</button>

</form>


</div>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<?php if (
    $totalPages > 1
): ?>

<div class="pagination-bar">

<div class="page-info">

Page
<?= (int)$page ?>
of
<?= (int)$totalPages ?>

&nbsp; · &nbsp;

<?= (int)$totalRows ?>
matching questions

</div>


<div class="pages">

<a
    href="<?= teacher_q_url(
        max(
            1,
            $page - 1
        ),
        $search,
        $subjectFilter,
        $topicFilter,
        $statusFilter,
        $difficultyFilter
    ) ?>"
    class="page-link <?= $page <= 1
        ? 'disabled'
        : ''
    ?>"
>

<i class="fa-solid fa-angle-left"></i>

</a>


<?php

$startPage =
    max(
        1,
        $page - 2
    );

$endPage =
    min(
        $totalPages,
        $page + 2
    );

?>

<?php for (
    $number = $startPage;
    $number <= $endPage;
    $number++
): ?>

<a
    href="<?= teacher_q_url(
        $number,
        $search,
        $subjectFilter,
        $topicFilter,
        $statusFilter,
        $difficultyFilter
    ) ?>"
    class="page-link <?= $number === $page
        ? 'active'
        : ''
    ?>"
>

<?= (int)$number ?>

</a>

<?php endfor; ?>


<a
    href="<?= teacher_q_url(
        min(
            $totalPages,
            $page + 1
        ),
        $search,
        $subjectFilter,
        $topicFilter,
        $statusFilter,
        $difficultyFilter
    ) ?>"
    class="page-link <?= $page >= $totalPages
        ? 'disabled'
        : ''
    ?>"
>

<i class="fa-solid fa-angle-right"></i>

</a>

</div>

</div>

<?php endif; ?>


<?php else: ?>

<div class="empty">

<i class="fa-solid fa-circle-question"></i>

<strong>
    No matching questions found.
</strong>

<span>
    Create a question or clear the current filters.
</span>

</div>

<?php endif; ?>


</section>

</div>

</main>

</div>


<div
    class="modal fade"
    id="questionModal"
    tabindex="-1"
    aria-hidden="true"
>

<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

<div class="modal-content">

<div class="modal-header">

<div>

<h2
    class="modal-title"
    id="questionModalTitle"
>
    Add Question
</h2>

<div class="modal-sub">
    Build a clean, validated question for your Question Bank.
</div>

</div>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
    aria-label="Close"
></button>

</div>


<form
    method="post"
    enctype="multipart/form-data"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="action"
    id="question-action"
    value="add"
>

<input
    type="hidden"
    name="question_id"
    id="question-id"
    value=""
>


<div class="modal-body">

<div class="modal-grid">


<div>

<label
    class="modal-label"
    for="modal-subject"
>
    Subject *
</label>

<select
    class="form-select modal-control"
    id="modal-subject"
    name="subject_id"
    required
>

<option value="">
    Select subject
</option>

<?php foreach (
    $subjects as $subject
): ?>

<option
    value="<?= (int)$subject['id'] ?>"
>

<?= teacher_q_e(
    $subject['name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div>

<label
    class="modal-label"
    for="modal-topic"
>
    Topic
</label>

<select
    class="form-select modal-control"
    id="modal-topic"
    name="topic_id"
>

<option value="">
    Select topic
</option>

</select>

</div>


<div>

<label
    class="modal-label"
    for="modal-type"
>
    Question Type *
</label>

<select
    class="form-select modal-control"
    id="modal-type"
    name="question_type"
    required
>

<option value="MCQ">
    MCQ
</option>

<option value="TrueFalse">
    True / False
</option>

</select>

</div>


<div>

<label
    class="modal-label"
    for="modal-difficulty"
>
    Difficulty *
</label>

<select
    class="form-select modal-control"
    id="modal-difficulty"
    name="difficulty"
    required
>

<option value="Easy">
    Easy
</option>

<option value="Medium" selected>
    Medium
</option>

<option value="Hard">
    Hard
</option>

</select>

</div>


<div class="modal-full">

<label
    class="modal-label"
    for="modal-question"
>
    Question *
</label>

<textarea
    class="modal-control"
    id="modal-question"
    name="question_text"
    maxlength="65535"
    required
    placeholder="Write the complete question..."
></textarea>

</div>


<div>

<label
    class="modal-label"
    for="modal-option-a"
>
    Option A *
</label>

<input
    class="modal-control"
    id="modal-option-a"
    name="option_a"
    maxlength="500"
    required
>

</div>


<div>

<label
    class="modal-label"
    for="modal-option-b"
>
    Option B *
</label>

<input
    class="modal-control"
    id="modal-option-b"
    name="option_b"
    maxlength="500"
    required
>

</div>


<div>

<label
    class="modal-label"
    for="modal-option-c"
>
    Option C *
</label>

<input
    class="modal-control"
    id="modal-option-c"
    name="option_c"
    maxlength="500"
    required
>

</div>


<div>

<label
    class="modal-label"
    for="modal-option-d"
>
    Option D *
</label>

<input
    class="modal-control"
    id="modal-option-d"
    name="option_d"
    maxlength="500"
    required
>

</div>


<div>

<label
    class="modal-label"
    for="modal-correct"
>
    Correct Answer *
</label>

<select
    class="form-select modal-control"
    id="modal-correct"
    name="correct_answer"
    required
>

<option value="A">
    A
</option>

<option value="B">
    B
</option>

<option value="C">
    C
</option>

<option value="D">
    D
</option>

</select>

</div>


<div>

<label
    class="modal-label"
    for="modal-marks"
>
    Marks *
</label>

<input
    class="modal-control"
    id="modal-marks"
    name="marks"
    type="number"
    min="0.01"
    step="0.01"
    value="1"
    required
>

</div>


<div>

<label
    class="modal-label"
    for="modal-negative"
>
    Negative Marks
</label>

<input
    class="modal-control"
    id="modal-negative"
    name="negative_marks"
    type="number"
    min="0"
    step="0.01"
    value="0"
>

</div>


<div>

<label
    class="modal-label"
    for="modal-time"
>
    Estimated Time (seconds)
</label>

<input
    class="modal-control"
    id="modal-time"
    name="estimated_time_seconds"
    type="number"
    min="0"
    step="1"
    placeholder="Optional"
>

</div>


<div>

<label
    class="modal-label"
    for="modal-status"
>
    Status *
</label>

<select
    class="form-select modal-control"
    id="modal-status"
    name="status"
    required
>

<option value="Active">
    Active
</option>

<option value="Inactive">
    Inactive
</option>

</select>

</div>


<div class="modal-full">

<label
    class="modal-label"
    for="modal-explanation"
>
    Explanation
</label>

<textarea
    class="modal-control"
    id="modal-explanation"
    name="explanation"
    placeholder="Optional explanation shown in review/result contexts..."
></textarea>

</div>


<div class="modal-full">

<label
    class="modal-label"
    for="modal-image"
>
    Question Image
</label>

<input
    class="form-control modal-control"
    id="modal-image"
    type="file"
    name="question_image"
    accept=".jpg,.jpeg,.png,.webp"
>

<div class="q-meta mt-1">
    Optional · JPG, PNG or WEBP · Maximum 3 MB.
</div>

</div>


</div>


<div class="modal-actions">

<button
    type="button"
    class="btn btn-light"
    data-bs-dismiss="modal"
>

Cancel

</button>

<button
    type="submit"
    class="btn btn-save"
>

<i class="fa-solid fa-floppy-disk me-1"></i>

<span id="saveLabel">
    Save Question
</span>

</button>

</div>

</div>

</form>

</div>

</div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

<script>

(() => {

    const modal =
        document.getElementById(
            'questionModal'
        );

    const openAdd =
        document.getElementById(
            'openAddQuestion'
        );

    const title =
        document.getElementById(
            'questionModalTitle'
        );

    const action =
        document.getElementById(
            'question-action'
        );

    const questionId =
        document.getElementById(
            'question-id'
        );

    const saveLabel =
        document.getElementById(
            'saveLabel'
        );

    const subject =
        document.getElementById(
            'modal-subject'
        );

    const topic =
        document.getElementById(
            'modal-topic'
        );

    const type =
        document.getElementById(
            'modal-type'
        );

    const question =
        document.getElementById(
            'modal-question'
        );

    const optionA =
        document.getElementById(
            'modal-option-a'
        );

    const optionB =
        document.getElementById(
            'modal-option-b'
        );

    const optionC =
        document.getElementById(
            'modal-option-c'
        );

    const optionD =
        document.getElementById(
            'modal-option-d'
        );

    const correct =
        document.getElementById(
            'modal-correct'
        );

    const explanation =
        document.getElementById(
            'modal-explanation'
        );

    const marks =
        document.getElementById(
            'modal-marks'
        );

    const negative =
        document.getElementById(
            'modal-negative'
        );

    const estimatedTime =
        document.getElementById(
            'modal-time'
        );

    const difficulty =
        document.getElementById(
            'modal-difficulty'
        );

    const status =
        document.getElementById(
            'modal-status'
        );

    function resetForm(){

        action.value = 'add';
        questionId.value = '';

        title.textContent =
            'Add Question';

        saveLabel.textContent =
            'Save Question';

        question.value = '';
        optionA.value = '';
        optionB.value = '';
        optionC.value = '';
        optionD.value = '';

        correct.value = 'A';

        explanation.value = '';

        marks.value = '1';

        negative.value = '0';

        estimatedTime.value = '';

        type.value = 'MCQ';

        difficulty.value = 'Medium';

        status.value = 'Active';

        subject.value = '';

        topic.innerHTML =
            '<option value="">Select topic</option>';

        document
            .getElementById(
                'modal-image'
            )
            .value = '';

        applyTypeState();
    }

    function applyTypeState(){

        const isTrueFalse =
            type.value ===
            'TrueFalse';

        if (isTrueFalse) {

            optionA.value = 'True';
            optionB.value = 'False';
            optionC.value = '';
            optionD.value = '';

            optionA.readOnly = true;
            optionB.readOnly = true;
            optionC.disabled = true;
            optionD.disabled = true;

            correct.innerHTML = `
                <option value="A">A</option>
                <option value="B">B</option>
            `;

            if (
                correct.value !== 'A' &&
                correct.value !== 'B'
            ) {
                correct.value = 'A';
            }

        } else {

            optionA.readOnly = false;
            optionB.readOnly = false;
            optionC.disabled = false;
            optionD.disabled = false;

            correct.innerHTML = `
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
            `;

            optionC.required = true;
            optionD.required = true;
        }

    }

    async function loadTopics(
        subjectId,
        selectedTopic
    ){

        topic.innerHTML =
            '<option value="">Loading topics...</option>';

        if (!subjectId) {

            topic.innerHTML =
                '<option value="">Select topic</option>';

            return;
        }

        try {

            const response =
                await fetch(
                    'get_topics.php?subject_id=' +
                    encodeURIComponent(
                        subjectId
                    ),
                    {
                        headers:{
                            'Accept':
                                'application/json'
                        },
                        credentials:
                            'same-origin'
                    }
                );

            const data =
                await response.json();

            topic.innerHTML =
                '<option value="">Select topic</option>';

            if (
                Array.isArray(
                    data
                )
            ) {

                data.forEach(
                    row => {

                        const option =
                            document.createElement(
                                'option'
                            );

                        option.value =
                            row.id;

                        option.textContent =
                            row.name;

                        if (
                            String(row.id) ===
                            String(
                                selectedTopic
                            )
                        ) {
                            option.selected =
                                true;
                        }

                        topic.appendChild(
                            option
                        );
                    }
                );
            }

        } catch (error) {

            topic.innerHTML =
                '<option value="">Unable to load topics</option>';
        }
    }

    openAdd.addEventListener(
        'click',
        resetForm
    );

    document
        .querySelectorAll(
            '.edit-question-btn'
        )
        .forEach(
            button => {

                button.addEventListener(
                    'click',
                    () => {

                        action.value =
                            'edit';

                        questionId.value =
                            button.dataset.id || '';

                        title.textContent =
                            'Edit Question';

                        saveLabel.textContent =
                            'Update Question';

                        question.value =
                            button.dataset.text || '';

                        optionA.value =
                            button.dataset.optionA || '';

                        optionB.value =
                            button.dataset.optionB || '';

                        optionC.value =
                            button.dataset.optionC || '';

                        optionD.value =
                            button.dataset.optionD || '';

                        correct.value =
                            button.dataset.correct || 'A';

                        explanation.value =
                            button.dataset.explanation || '';

                        marks.value =
                            button.dataset.marks || '1';

                        negative.value =
                            button.dataset.negative || '0';

                        estimatedTime.value =
                            button.dataset.time || '';

                        type.value =
                            button.dataset.type || 'MCQ';

                        difficulty.value =
                            button.dataset.difficulty || 'Medium';

                        status.value =
                            button.dataset.status || 'Active';

                        subject.value =
                            button.dataset.subject || '';

                        applyTypeState();

                        correct.value =
                            button.dataset.correct || 'A';

                        loadTopics(
                            button.dataset.subject || '',
                            button.dataset.topic || ''
                        );
                    }
                );
            }
        );

    subject.addEventListener(
        'change',
        () => {
            loadTopics(
                subject.value,
                ''
            );
        }
    );

    type.addEventListener(
        'change',
        applyTypeState
    );

    /*
    |--------------------------------------------------------------------------
    | Filter topic dropdown: load topics after subject changes.
    |--------------------------------------------------------------------------
    */

    const filterSubject =
        document.getElementById(
            'subject_id'
        );

    const filterTopic =
        document.getElementById(
            'topic_id'
        );

    if (
        filterSubject &&
        filterTopic
    ) {

        filterSubject.addEventListener(
            'change',
            async () => {

                const value =
                    filterSubject.value;

                filterTopic.innerHTML =
                    '<option value="">Loading topics...</option>';

                if (!value) {

                    filterTopic.innerHTML =
                        '<option value="">All topics</option>';

                    return;
                }

                try {

                    const response =
                        await fetch(
                            'get_topics.php?subject_id=' +
                            encodeURIComponent(
                                value
                            ),
                            {
                                headers:{
                                    'Accept':
                                        'application/json'
                                },
                                credentials:
                                    'same-origin'
                            }
                        );

                    const data =
                        await response.json();

                    filterTopic.innerHTML =
                        '<option value="">All topics</option>';

                    if (
                        Array.isArray(data)
                    ) {

                        data.forEach(
                            row => {

                                const option =
                                    document.createElement(
                                        'option'
                                    );

                                option.value =
                                    row.id;

                                option.textContent =
                                    row.name;

                                filterTopic.appendChild(
                                    option
                                );
                            }
                        );
                    }

                } catch (
                    error
                ) {

                    filterTopic.innerHTML =
                        '<option value="">All topics</option>';
                }
            }
        );

    }

    applyTypeState();

})();

</script>

</body>

</html>
