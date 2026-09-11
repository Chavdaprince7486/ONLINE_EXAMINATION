<?php

declare(strict_types=1);

function sendNotification(
    PDO $conn,
    string $recipientType,
    int $recipientId,
    string $title,
    string $message,
    string $notificationType = 'System',
    ?string $referenceType = null,
    ?int $referenceId = null
): bool {

    $allowedRecipients = [
        'Admin',
        'Teacher',
        'Student'
    ];

    if (!in_array($recipientType, $allowedRecipients, true)) {
        return false;
    }

    if (
        $recipientId <= 0 ||
        trim($title) === '' ||
        trim($message) === ''
    ) {
        return false;
    }

    $stmt = $conn->prepare(
        "INSERT INTO notifications
        (
            recipient_type,
            recipient_id,
            title,
            message,
            notification_type,
            reference_type,
            reference_id
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    return $stmt->execute([
        $recipientType,
        $recipientId,
        trim($title),
        trim($message),
        trim($notificationType),
        $referenceType !== null
            ? trim($referenceType)
            : null,
        $referenceId !== null && $referenceId > 0
            ? $referenceId
            : null
    ]);
}