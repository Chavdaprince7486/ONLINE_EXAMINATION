<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

$role = strtolower(
    trim(
        (string) (
            $_SESSION['user_role']
            ?? $_SESSION['role']
            ?? ''
        )
    )
);

if ($role !== 'admin') {
    http_response_code(403);
    exit('Access denied.');
}

$csrfToken = '';

if (function_exists('csrf_token')) {
    $csrfToken = (string) csrf_token();
} elseif (!empty($_SESSION['csrf_token'])) {
    $csrfToken = (string) $_SESSION['csrf_token'];
}

$baseUrl = defined('BASE_URL')
    ? rtrim((string) BASE_URL, '/') . '/'
    : '/ONLINE_EXAMINATION/';

$pageTitle = 'Notifications';

$successMessage =
    $_SESSION['notification_success'] ?? '';

$errorMessage =
    $_SESSION['notification_error'] ?? '';

unset(
    $_SESSION['notification_success'],
    $_SESSION['notification_error']
);
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
        Notifications | ExamSphere Admin
    </title>

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(
            $baseUrl . 'assets/css/notifications.css',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
    >

    <style>

        .notification-page {
            max-width: 1150px;
            margin: 0 auto;
            padding: 30px;
        }

        .notification-page-header {
            margin-bottom: 24px;
        }

        .notification-page-header span {
            display: block;
            margin-bottom: 7px;

            color: #556b2f;

            font-size: 10px;
            font-weight: 800;

            letter-spacing: 1.8px;
        }

        .notification-page-header h1 {
            margin: 0;

            color: #3e2723;

            font-size: 28px;
            font-weight: 850;
        }

        .notification-page-header p {
            margin: 8px 0 0;

            max-width: 730px;

            color: #766d65;

            font-size: 13px;

            line-height: 1.7;
        }


        .notification-alert {
            display: flex;
            align-items: flex-start;

            gap: 12px;

            padding: 14px 16px;

            margin-bottom: 20px;

            border-radius: 13px;

            font-size: 12px;

            line-height: 1.5;
        }

        .notification-alert.success {
            color: #315d39;
            background: #eaf5eb;
            border: 1px solid #cde5d0;
        }

        .notification-alert.error {
            color: #813b32;
            background: #f9ece9;
            border: 1px solid #ecd0cb;
        }


        .notification-compose-card {
            padding: 28px;

            border: 1px solid #e4dbd1;

            border-radius: 22px;

            background: #fff;

            box-shadow:
                0 18px 50px rgba(
                    62,
                    39,
                    35,
                    0.08
                );
        }


        .notification-section-title {
            margin-bottom: 22px;
        }

        .notification-section-title h2 {
            margin: 0;

            color: #3e2723;

            font-size: 17px;
            font-weight: 800;
        }

        .notification-section-title p {
            margin: 6px 0 0;

            color: #8b8178;

            font-size: 11px;
        }


        .notification-grid {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 18px;
        }


        .notification-field {
            display: flex;
            flex-direction: column;

            gap: 8px;
        }

        .notification-field.full {
            grid-column: 1 / -1;
        }

        .notification-field label {
            color: #564b43;

            font-size: 10px;
            font-weight: 800;
        }

        .notification-field label span {
            color: #a84538;
        }


        .notification-field input,
        .notification-field select,
        .notification-field textarea {
            width: 100%;

            box-sizing: border-box;

            border: 1px solid #ddd2c7;

            border-radius: 12px;

            background: #fffdfa;

            color: #3e2723;

            font-family: inherit;

            font-size: 12px;

            outline: none;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        .notification-field input,
        .notification-field select {
            height: 46px;

            padding: 0 13px;
        }

        .notification-field textarea {
            min-height: 150px;

            padding: 13px;

            resize: vertical;
        }

        .notification-field input:focus,
        .notification-field select:focus,
        .notification-field textarea:focus {
            border-color: #95a56f;

            box-shadow:
                0 0 0 4px rgba(
                    85,
                    107,
                    47,
                    0.09
                );
        }


        .notification-helper {
            color: #958b82;

            font-size: 9px;
            line-height: 1.6;
        }


        .notification-options {
            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 10px;
        }

        .notification-audience-option {
            position: relative;
        }

        .notification-audience-option input {
            position: absolute;

            opacity: 0;
            pointer-events: none;
        }

        .notification-audience-option label {
            display: flex;

            align-items: center;
            gap: 10px;

            min-height: 58px;

            padding: 10px 12px;

            border: 1px solid #dfd6cb;

            border-radius: 13px;

            background: #fffdfa;

            cursor: pointer;

            transition:
                border-color 0.2s ease,
                background 0.2s ease,
                transform 0.2s ease;
        }

        .notification-audience-option label:hover {
            transform: translateY(-1px);

            border-color: #c9bcae;
        }

        .notification-audience-option input:checked + label {
            border-color: #95a56f;

            background: #f2f6e9;

            box-shadow:
                0 8px 18px rgba(
                    85,
                    107,
                    47,
                    0.08
                );
        }

        .notification-audience-icon {
            display: flex;

            align-items: center;
            justify-content: center;

            width: 34px;
            height: 34px;

            border-radius: 10px;

            background: #eee7df;

            color: #5d4037;

            flex: 0 0 auto;
        }

        .notification-audience-option
        input:checked + label
        .notification-audience-icon {
            background: #e0e9d0;

            color: #556b2f;
        }

        .notification-audience-text {
            display: flex;
            flex-direction: column;

            gap: 3px;
        }

        .notification-audience-text strong {
            color: #3e2723;

            font-size: 10px;
            font-weight: 800;
        }

        .notification-audience-text small {
            color: #91877e;

            font-size: 8px;
        }


        .notification-footer {
            display: flex;

            align-items: center;
            justify-content: space-between;

            gap: 20px;

            margin-top: 25px;

            padding-top: 22px;

            border-top: 1px solid #eee6dc;
        }

        .notification-live-preview {
            flex: 1;

            color: #80766d;

            font-size: 10px;

            line-height: 1.6;
        }

        .notification-submit {
            min-width: 170px;

            height: 46px;

            border: 0;
            border-radius: 12px;

            background: #556b2f;
            color: #fff;

            font-family: inherit;

            font-size: 11px;
            font-weight: 800;

            cursor: pointer;

            box-shadow:
                0 10px 24px rgba(
                    85,
                    107,
                    47,
                    0.18
                );

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                opacity 0.2s ease;
        }

        .notification-submit:hover {
            transform: translateY(-1px);

            box-shadow:
                0 14px 30px rgba(
                    85,
                    107,
                    47,
                    0.24
                );
        }

        .notification-submit:disabled {
            opacity: 0.65;

            cursor: not-allowed;

            transform: none;
        }


        @media (max-width: 800px) {

            .notification-page {
                padding: 20px;
            }

            .notification-grid {
                grid-template-columns: 1fr;
            }

            .notification-field.full {
                grid-column: auto;
            }

            .notification-options {
                grid-template-columns: 1fr;
            }

            .notification-footer {
                align-items: stretch;
                flex-direction: column;
            }

            .notification-submit {
                width: 100%;
            }

        }

    </style>

</head>


<body>

    <?php
    /*
     * Your existing admin sidebar/header can remain here.
     * If navbar is already included by the project layout,
     * do not duplicate it.
     */
    ?>

    <main class="notification-page">

        <div class="notification-page-header">

            <span>
                ADMIN COMMUNICATION CENTER
            </span>

            <h1>
                Send Notifications
            </h1>

            <p>
                Send an announcement or important message
                directly to students, teachers, or both.
                Messages are stored in the notification database
                and appear in their notification bell.
            </p>

        </div>


        <?php if ($successMessage !== ''): ?>

            <div class="notification-alert success">

                <i class="fa-solid fa-circle-check"></i>

                <div>
                    <?= htmlspecialchars(
                        (string) $successMessage,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?>
                </div>

            </div>

        <?php endif; ?>


        <?php if ($errorMessage !== ''): ?>

            <div class="notification-alert error">

                <i class="fa-solid fa-circle-exclamation"></i>

                <div>
                    <?= htmlspecialchars(
                        (string) $errorMessage,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?>
                </div>

            </div>

        <?php endif; ?>


        <form
            class="notification-compose-card"
            id="adminNotificationForm"
            method="POST"
            action="send.php"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrfToken,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>"
            >


            <div class="notification-section-title">

                <h2>
                    Create New Notification
                </h2>

                <p>
                    Choose the audience and write your message.
                </p>

            </div>


            <div class="notification-grid">


                <div class="notification-field full">

                    <label>
                        Send To <span>*</span>
                    </label>

                    <div class="notification-options">


                        <div
                            class="notification-audience-option"
                        >

                            <input
                                type="radio"
                                id="audienceStudents"
                                name="audience"
                                value="students"
                                checked
                            >

                            <label
                                for="audienceStudents"
                            >

                                <span
                                    class="notification-audience-icon"
                                >
                                    <i class="fa-solid fa-user-graduate"></i>
                                </span>

                                <span
                                    class="notification-audience-text"
                                >
                                    <strong>
                                        Students
                                    </strong>

                                    <small>
                                        Send to all active students
                                    </small>
                                </span>

                            </label>

                        </div>


                        <div
                            class="notification-audience-option"
                        >

                            <input
                                type="radio"
                                id="audienceTeachers"
                                name="audience"
                                value="teachers"
                            >

                            <label
                                for="audienceTeachers"
                            >

                                <span
                                    class="notification-audience-icon"
                                >
                                    <i class="fa-solid fa-chalkboard-user"></i>
                                </span>

                                <span
                                    class="notification-audience-text"
                                >
                                    <strong>
                                        Teachers
                                    </strong>

                                    <small>
                                        Send to all active teachers
                                    </small>
                                </span>

                            </label>

                        </div>


                        <div
                            class="notification-audience-option"
                        >

                            <input
                                type="radio"
                                id="audienceBoth"
                                name="audience"
                                value="both"
                            >

                            <label
                                for="audienceBoth"
                            >

                                <span
                                    class="notification-audience-icon"
                                >
                                    <i class="fa-solid fa-users"></i>
                                </span>

                                <span
                                    class="notification-audience-text"
                                >
                                    <strong>
                                        Students + Teachers
                                    </strong>

                                    <small>
                                        Send to both groups
                                    </small>
                                </span>

                            </label>

                        </div>

                    </div>

                </div>


                <div class="notification-field">

                    <label
                        for="notificationType"
                    >
                        Notification Type <span>*</span>
                    </label>

                    <select
                        id="notificationType"
                        name="notification_type"
                        required
                    >

                        <option value="announcement">
                            Announcement
                        </option>

                        <option value="important">
                            Important
                        </option>

                        <option value="exam">
                            Exam
                        </option>

                        <option value="material">
                            Study Material
                        </option>

                        <option value="system">
                            System
                        </option>

                    </select>

                </div>


                <div class="notification-field">

                    <label
                        for="notificationTitle"
                    >
                        Notification Title <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="notificationTitle"
                        name="title"
                        maxlength="180"
                        required
                        placeholder="Enter notification title"
                    >

                </div>


                <div class="notification-field full">

                    <label
                        for="notificationMessage"
                    >
                        Message <span>*</span>
                    </label>

                    <textarea
                        id="notificationMessage"
                        name="message"
                        maxlength="10000"
                        required
                        placeholder="Write your message here..."
                    ></textarea>

                    <div
                        class="notification-helper"
                        id="notificationCharacterCount"
                    >
                        0 / 10000 characters
                    </div>

                </div>


                <div class="notification-field">

                    <label
                        for="referenceType"
                    >
                        Optional Reference
                    </label>

                    <select
                        id="referenceType"
                        name="reference_type"
                    >

                        <option value="">
                            No reference
                        </option>

                        <option value="exam">
                            Exam
                        </option>

                        <option value="material">
                            Material
                        </option>

                        <option value="subject">
                            Subject
                        </option>

                        <option value="category">
                            Category
                        </option>

                        <option value="topic">
                            Topic
                        </option>

                    </select>

                </div>


                <div class="notification-field">

                    <label
                        for="referenceId"
                    >
                        Reference ID
                    </label>

                    <input
                        type="number"
                        id="referenceId"
                        name="reference_id"
                        min="1"
                        step="1"
                        placeholder="Optional ID"
                    >

                </div>

            </div>


            <div class="notification-footer">

                <div
                    class="notification-live-preview"
                    id="notificationAudiencePreview"
                >
                    This message will be sent to all active students.
                </div>

                <button
                    type="submit"
                    class="notification-submit"
                    id="notificationSubmitButton"
                >

                    <i class="fa-solid fa-paper-plane"></i>

                    Send Notification

                </button>

            </div>

        </form>

    </main>


    <script>
    (function () {

        'use strict';

        const form =
            document.getElementById(
                'adminNotificationForm'
            );

        const message =
            document.getElementById(
                'notificationMessage'
            );

        const count =
            document.getElementById(
                'notificationCharacterCount'
            );

        const preview =
            document.getElementById(
                'notificationAudiencePreview'
            );

        const button =
            document.getElementById(
                'notificationSubmitButton'
            );

        const audienceInputs =
            document.querySelectorAll(
                'input[name="audience"]'
            );


        function updateCount() {

            if (!message || !count) {
                return;
            }

            count.textContent =
                message.value.length +
                ' / 10000 characters';
        }


        function updatePreview() {

            if (!preview) {
                return;
            }

            const selected =
                document.querySelector(
                    'input[name="audience"]:checked'
                );

            if (!selected) {
                return;
            }

            const messages = {

                students:
                    'This message will be sent to all active students.',

                teachers:
                    'This message will be sent to all active teachers.',

                both:
                    'This message will be sent to all active students and teachers.'
            };

            preview.textContent =
                messages[selected.value]
                || '';
        }


        if (message) {
            message.addEventListener(
                'input',
                updateCount
            );

            updateCount();
        }


        audienceInputs.forEach(
            function (input) {

                input.addEventListener(
                    'change',
                    updatePreview
                );

            }
        );


        updatePreview();


        if (form) {

            form.addEventListener(
                'submit',
                function (event) {

                    const title =
                        document.getElementById(
                            'notificationTitle'
                        );

                    if (
                        !title ||
                        title.value.trim() === ''
                    ) {

                        event.preventDefault();

                        title.focus();

                        alert(
                            'Please enter a notification title.'
                        );

                        return;
                    }


                    if (
                        !message ||
                        message.value.trim() === ''
                    ) {

                        event.preventDefault();

                        if (message) {
                            message.focus();
                        }

                        alert(
                            'Please enter a notification message.'
                        );

                        return;
                    }


                    const selected =
                        document.querySelector(
                            'input[name="audience"]:checked'
                        );

                    if (!selected) {

                        event.preventDefault();

                        alert(
                            'Please select an audience.'
                        );

                        return;
                    }


                    const confirmation =
                        window.confirm(
                            'Are you sure you want to send this notification?'
                        );

                    if (!confirmation) {

                        event.preventDefault();

                        return;
                    }


                    if (button) {

                        button.disabled = true;

                        button.innerHTML =
                            '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';

                    }

                }
            );

        }

    })();
    </script>

</body>
</html>