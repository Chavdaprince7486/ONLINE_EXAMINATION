-- ExamSphere Phase 0.3
-- Purpose: Align the database with the final exam/question architecture.
-- Base schema: database/examsphere.sql
-- IMPORTANT: Run on a backup/test database first.

USE online_examination;

START TRANSACTION;

/* =========================================================
   1. SUBJECT -> CATEGORY relationship
   ========================================================= */
ALTER TABLE subjects
    ADD COLUMN category_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_subject_category (category_id),
    ADD CONSTRAINT fk_subject_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE SET NULL;

/* =========================================================
   2. TOPICS
   ========================================================= */
CREATE TABLE topics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_topic_subject_name UNIQUE (subject_id, name),
    CONSTRAINT fk_topic_subject
        FOREIGN KEY (subject_id) REFERENCES subjects(id)
        ON DELETE CASCADE,
    INDEX idx_topic_subject_status (subject_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* =========================================================
   3. QUESTIONS - educational metadata
   ========================================================= */
ALTER TABLE questions
    ADD COLUMN topic_id INT UNSIGNED NULL AFTER subject_id,
    ADD COLUMN question_image VARCHAR(255) NULL AFTER question_text,
    ADD COLUMN explanation TEXT NULL AFTER correct_answer,
    ADD COLUMN estimated_time_seconds SMALLINT UNSIGNED NULL AFTER negative_marks,
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD INDEX idx_question_topic_status (topic_id, status),
    ADD CONSTRAINT fk_question_topic
        FOREIGN KEY (topic_id) REFERENCES topics(id)
        ON DELETE SET NULL;

/* =========================================================
   4. EXAM ATTEMPTS - server-side timing/security support
   ========================================================= */
ALTER TABLE exam_attempts
    ADD COLUMN server_deadline DATETIME NULL AFTER started_at,
    ADD COLUMN last_activity_at DATETIME NULL AFTER submitted_at,
    ADD INDEX idx_attempt_deadline (status, server_deadline);

/* =========================================================
   5. ANSWERS - question status tracking
   ========================================================= */
ALTER TABLE answers
    ADD COLUMN question_status ENUM(
        'Not Visited',
        'Not Answered',
        'Answered',
        'Marked for Review',
        'Answered & Marked for Review'
    ) NOT NULL DEFAULT 'Not Answered' AFTER selected_answer,
    ADD COLUMN answered_at DATETIME NULL AFTER question_status,
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER marks_awarded,
    ADD INDEX idx_answer_attempt_status (attempt_id, question_status);

/* =========================================================
   6. NOTIFICATIONS
   ========================================================= */
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('Admin','Teacher','Student') NOT NULL,
    recipient_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(60) NOT NULL,
    reference_type VARCHAR(60) NULL,
    reference_id INT UNSIGNED NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    INDEX idx_notification_recipient (recipient_type, recipient_id, is_read, created_at),
    INDEX idx_notification_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

/*
   Deferred decisions (intentionally NOT changed in this migration):
   - exams.status ENUM lifecycle
   - teacher phone/mobile cleanup
   - live payment gateway columns
   - production admin/student demo-account cleanup
   - deletion of obsolete PHP files

   Those require PHP dependency verification first.
*/
