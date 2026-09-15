<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ExamSphere Central Notification Engine
|--------------------------------------------------------------------------
*/

function examsphere_notification_recipient_type(string $type): string
{
    $normalized = ucfirst(strtolower(trim($type)));

    if (
        !in_array(
            $normalized,
            ['Admin', 'Teacher', 'Student'],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Invalid notification recipient type.'
        );
    }

    return $normalized;
}

function examsphere_notification_text(
    string $value,
    int $limit
): string {
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    return mb_strlen($value) > $limit
        ? mb_substr($value, 0, $limit)
        : $value;
}

function examsphere_send_notification(
    PDO $conn,
    string $recipientType,
    int $recipientId,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): bool {

    try {

        $recipientType =
            examsphere_notification_recipient_type(
                $recipientType
            );

        $recipientId =
            (int)$recipientId;

        $title =
            examsphere_notification_text(
                $title,
                180
            );

        $message =
            examsphere_notification_text(
                $message,
                65535
            );

        $notificationType =
            examsphere_notification_text(
                $notificationType ?: 'system',
                60
            );

        $referenceType =
            $referenceType !== null
                ? examsphere_notification_text(
                    $referenceType,
                    60
                )
                : null;

        $referenceId =
            $referenceId !== null &&
            (int)$referenceId > 0
                ? (int)$referenceId
                : null;

        if (
            $recipientId <= 0 ||
            $title === '' ||
            $message === ''
        ) {
            return false;
        }

        /*
         * Prevent duplicate automatic notices
         * for the same reference.
         */
        if (
            $referenceType !== null &&
            $referenceId !== null
        ) {

            $check =
                $conn->prepare(
                    'SELECT id
                     FROM notifications
                     WHERE recipient_type = ?
                       AND recipient_id = ?
                       AND reference_type = ?
                       AND reference_id = ?
                       AND title = ?
                     LIMIT 1'
                );

            $check->execute(
                [
                    $recipientType,
                    $recipientId,
                    $referenceType,
                    $referenceId,
                    $title
                ]
            );

            if (
                $check->fetchColumn()
            ) {
                return true;
            }
        }

        $stmt =
            $conn->prepare(
                'INSERT INTO notifications
                (
                    recipient_type,
                    recipient_id,
                    title,
                    message,
                    notification_type,
                    reference_type,
                    reference_id,
                    is_read,
                    created_at
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
                    0,
                    NOW()
                )'
            );

        return $stmt->execute(
            [
                $recipientType,
                $recipientId,
                $title,
                $message,
                $notificationType,
                $referenceType,
                $referenceId
            ]
        );

    } catch (Throwable $e) {

        error_log(
            'ExamSphere notification insert failed: ' .
            $e->getMessage()
        );

        return false;
    }
}

function examsphere_notify_recipients(
    PDO $conn,
    string $recipientType,
    array $recipientIds,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): int {

    $sent = 0;

    foreach (
        array_unique(
            array_map(
                'intval',
                $recipientIds
            )
        ) as $id
    ) {

        if (
            $id > 0 &&
            examsphere_send_notification(
                $conn,
                $recipientType,
                $id,
                $title,
                $message,
                $notificationType,
                $referenceType,
                $referenceId
            )
        ) {
            $sent++;
        }
    }

    return $sent;
}

function examsphere_notify_students(
    PDO $conn,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): int {

    try {

        $ids =
            $conn
                ->query(
                    "SELECT id
                     FROM students
                     WHERE status = 'Active'
                     ORDER BY id ASC"
                )
                ->fetchAll(
                    PDO::FETCH_COLUMN
                );

        return examsphere_notify_recipients(
            $conn,
            'Student',
            $ids,
            $title,
            $message,
            $notificationType,
            $referenceType,
            $referenceId
        );

    } catch (Throwable $e) {

        error_log(
            'ExamSphere student notifications failed: ' .
            $e->getMessage()
        );

        return 0;
    }
}

function examsphere_notify_teachers(
    PDO $conn,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): int {

    try {

        $ids =
            $conn
                ->query(
                    "SELECT id
                     FROM teachers
                     WHERE status = 'Active'
                     ORDER BY id ASC"
                )
                ->fetchAll(
                    PDO::FETCH_COLUMN
                );

        return examsphere_notify_recipients(
            $conn,
            'Teacher',
            $ids,
            $title,
            $message,
            $notificationType,
            $referenceType,
            $referenceId
        );

    } catch (Throwable $e) {

        error_log(
            'ExamSphere teacher notifications failed: ' .
            $e->getMessage()
        );

        return 0;
    }
}

function examsphere_notify_admins(
    PDO $conn,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): int {

    try {

        $ids =
            $conn
                ->query(
                    "SELECT id
                     FROM admins
                     WHERE status = 'Active'
                     ORDER BY id ASC"
                )
                ->fetchAll(
                    PDO::FETCH_COLUMN
                );

        return examsphere_notify_recipients(
            $conn,
            'Admin',
            $ids,
            $title,
            $message,
            $notificationType,
            $referenceType,
            $referenceId
        );

    } catch (Throwable $e) {

        error_log(
            'ExamSphere admin notifications failed: ' .
            $e->getMessage()
        );

        return 0;
    }
}

function examsphere_notify_students_and_teachers(
    PDO $conn,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): int {

    return
        examsphere_notify_students(
            $conn,
            $title,
            $message,
            $notificationType,
            $referenceType,
            $referenceId
        )
        +
        examsphere_notify_teachers(
            $conn,
            $title,
            $message,
            $notificationType,
            $referenceType,
            $referenceId
        );
}

function examsphere_notify_student(
    PDO $conn,
    int $studentId,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): bool {

    return examsphere_send_notification(
        $conn,
        'Student',
        $studentId,
        $title,
        $message,
        $notificationType,
        $referenceType,
        $referenceId
    );
}

function examsphere_notify_teacher(
    PDO $conn,
    int $teacherId,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): bool {

    return examsphere_send_notification(
        $conn,
        'Teacher',
        $teacherId,
        $title,
        $message,
        $notificationType,
        $referenceType,
        $referenceId
    );
}