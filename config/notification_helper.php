<?php
declare(strict_types=1);

require_once __DIR__ . '/notifications.php';

function sendNotification(
    PDO $conn,
    string $recipientType,
    int $recipientId,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): bool {

    return examsphere_send_notification(
        $conn,
        $recipientType,
        $recipientId,
        $title,
        $message,
        $notificationType,
        $referenceType,
        $referenceId
    );
}

function sendNotificationToStudents(
    PDO $conn,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): int {

    return examsphere_notify_students(
        $conn,
        $title,
        $message,
        $notificationType,
        $referenceType,
        $referenceId
    );
}

function sendNotificationToTeachers(
    PDO $conn,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): int {

    return examsphere_notify_teachers(
        $conn,
        $title,
        $message,
        $notificationType,
        $referenceType,
        $referenceId
    );
}

function sendNotificationToAdmins(
    PDO $conn,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): int {

    return examsphere_notify_admins(
        $conn,
        $title,
        $message,
        $notificationType,
        $referenceType,
        $referenceId
    );
}

function sendNotificationToStudentsAndTeachers(
    PDO $conn,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): int {

    return examsphere_notify_students_and_teachers(
        $conn,
        $title,
        $message,
        $notificationType,
        $referenceType,
        $referenceId
    );
}

function sendNotificationToStudent(
    PDO $conn,
    int $studentId,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): bool {

    return examsphere_notify_student(
        $conn,
        $studentId,
        $title,
        $message,
        $notificationType,
        $referenceType,
        $referenceId
    );
}

function sendNotificationToTeacher(
    PDO $conn,
    int $teacherId,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $referenceType = null,
    ?int $referenceId = null
): bool {

    return examsphere_notify_teacher(
        $conn,
        $teacherId,
        $title,
        $message,
        $notificationType,
        $referenceType,
        $referenceId
    );
}