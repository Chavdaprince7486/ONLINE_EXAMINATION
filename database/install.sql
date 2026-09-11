CREATE DATABASE IF NOT EXISTS online_examination
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE online_examination;


/* =========================================================
   ADMINS
========================================================= */

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   TEACHERS
========================================================= */

CREATE TABLE IF NOT EXISTS teachers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_code VARCHAR(30) NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    mobile VARCHAR(20) NULL,
    gender ENUM('Male','Female','Other') NULL,
    dob DATE NULL,
    qualification VARCHAR(255) NULL,
    experience VARCHAR(100) NULL,
    address TEXT NULL,
    profile_photo VARCHAR(255) NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   CATEGORIES
========================================================= */

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(120) NOT NULL UNIQUE,
    description TEXT NULL,
    icon VARCHAR(100) NOT NULL DEFAULT 'fa-solid fa-book',
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   STUDENTS
========================================================= */

CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_code VARCHAR(30) NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    mobile VARCHAR(20) NOT NULL,
    gender ENUM('Male','Female','Other') NULL,
    dob DATE NULL,
    address TEXT NULL,
    city VARCHAR(80) NULL,
    state VARCHAR(80) NULL,
    pincode VARCHAR(10) NULL,
    password VARCHAR(255) NOT NULL,
    profile_photo VARCHAR(255) NULL,
    email_verified ENUM('Yes','No') NOT NULL DEFAULT 'No',
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   SUBJECTS
========================================================= */

CREATE TABLE IF NOT EXISTS subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL UNIQUE,
    code VARCHAR(30) NULL UNIQUE,
    description TEXT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_subject_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON DELETE SET NULL,

    INDEX idx_subject_category (
        category_id
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   TOPICS
========================================================= */

CREATE TABLE IF NOT EXISTS topics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_topic_subject_name
        UNIQUE (subject_id, name),

    CONSTRAINT fk_topic_subject
        FOREIGN KEY (subject_id)
        REFERENCES subjects(id)
        ON DELETE CASCADE,

    INDEX idx_topic_subject_status (
        subject_id,
        status
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   EXAMS
========================================================= */

CREATE TABLE IF NOT EXISTS exams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    subject_id INT UNSIGNED NULL,

    teacher_id INT UNSIGNED NULL,

    title VARCHAR(180) NOT NULL,

    description TEXT NULL,

    exam_type
        ENUM('Practice','Live')
        NOT NULL DEFAULT 'Practice',

    duration_minutes
        SMALLINT UNSIGNED
        NOT NULL DEFAULT 30,

    required_question_count
        SMALLINT UNSIGNED
        NOT NULL DEFAULT 0,

    total_marks
        DECIMAL(8,2)
        NOT NULL DEFAULT 0,

    passing_marks
        DECIMAL(8,2)
        NOT NULL DEFAULT 0,

    negative_marking
        TINYINT(1)
        NOT NULL DEFAULT 0,

    exam_fee
        DECIMAL(10,2)
        NOT NULL DEFAULT 0,

    subscription_required
        TINYINT(1)
        NOT NULL DEFAULT 0,

    starts_at DATETIME NULL,

    ends_at DATETIME NULL,

    status
        ENUM(
            'Draft',
            'Scheduled',
            'Live',
            'Completed',
            'Cancelled',
            'Active',
            'Upcoming',
            'Running'
        )
        NOT NULL DEFAULT 'Draft',

    created_at
        TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    updated_at
        TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_exam_subject
        FOREIGN KEY (subject_id)
        REFERENCES subjects(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_exam_teacher
        FOREIGN KEY (teacher_id)
        REFERENCES teachers(id)
        ON DELETE SET NULL,

    INDEX idx_exam_type_status (
        exam_type,
        status
    ),

    INDEX idx_exam_schedule (
        starts_at
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   QUESTIONS
========================================================= */

CREATE TABLE IF NOT EXISTS questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    subject_id INT UNSIGNED NULL,

    topic_id INT UNSIGNED NULL,

    created_by_teacher_id
        INT UNSIGNED NULL,

    question_type
        ENUM('MCQ','TrueFalse')
        NOT NULL DEFAULT 'MCQ',

    question_text TEXT NOT NULL,

    question_image
        VARCHAR(255)
        NULL,

    option_a
        VARCHAR(500)
        NOT NULL,

    option_b
        VARCHAR(500)
        NOT NULL,

    option_c
        VARCHAR(500)
        NULL,

    option_d
        VARCHAR(500)
        NULL,

    correct_answer
        ENUM('A','B','C','D')
        NOT NULL,

    explanation TEXT NULL,

    marks
        DECIMAL(6,2)
        NOT NULL DEFAULT 1,

    negative_marks
        DECIMAL(6,2)
        NOT NULL DEFAULT 0,

    estimated_time_seconds
        SMALLINT UNSIGNED
        NULL,

    difficulty
        ENUM('Easy','Medium','Hard')
        NOT NULL DEFAULT 'Medium',

    status
        ENUM('Active','Inactive')
        NOT NULL DEFAULT 'Active',

    created_at
        TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    updated_at
        TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_question_subject
        FOREIGN KEY (subject_id)
        REFERENCES subjects(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_question_topic
        FOREIGN KEY (topic_id)
        REFERENCES topics(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_question_teacher
        FOREIGN KEY (created_by_teacher_id)
        REFERENCES teachers(id)
        ON DELETE SET NULL,

    INDEX idx_question_topic_status (
        topic_id,
        status
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   EXAM QUESTIONS
========================================================= */

CREATE TABLE IF NOT EXISTS exam_questions (

    exam_id
        INT UNSIGNED NOT NULL,

    question_id
        INT UNSIGNED NOT NULL,

    position
        SMALLINT UNSIGNED
        NOT NULL DEFAULT 1,

    PRIMARY KEY (
        exam_id,
        question_id
    ),

    CONSTRAINT fk_eq_exam
        FOREIGN KEY (exam_id)
        REFERENCES exams(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_eq_question
        FOREIGN KEY (question_id)
        REFERENCES questions(id)
        ON DELETE RESTRICT,

    INDEX idx_exam_question_position (
        exam_id,
        position
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   EXAM ATTEMPTS
========================================================= */

CREATE TABLE IF NOT EXISTS exam_attempts (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    student_id INT UNSIGNED NOT NULL,

    exam_id INT UNSIGNED NOT NULL,

    started_at
        DATETIME
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    server_deadline
        DATETIME NULL,

    submitted_at
        DATETIME NULL,

    last_activity_at
        DATETIME NULL,

    status
        ENUM(
            'Started',
            'Submitted',
            'Auto Submitted'
        )
        NOT NULL DEFAULT 'Started',

    obtained_marks
        DECIMAL(8,2)
        NOT NULL DEFAULT 0,

    percentage
        DECIMAL(5,2)
        NOT NULL DEFAULT 0,

    CONSTRAINT fk_attempt_student
        FOREIGN KEY (student_id)
        REFERENCES students(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_attempt_exam
        FOREIGN KEY (exam_id)
        REFERENCES exams(id)
        ON DELETE RESTRICT,

    INDEX idx_attempt_student (
        student_id,
        submitted_at
    ),

    INDEX idx_attempt_exam (
        student_id,
        exam_id,
        status
    ),

    INDEX idx_attempt_deadline (
        status,
        server_deadline
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   ANSWERS
========================================================= */

CREATE TABLE IF NOT EXISTS answers (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    attempt_id INT UNSIGNED NOT NULL,

    question_id INT UNSIGNED NOT NULL,

    selected_answer
        ENUM('A','B','C','D')
        NULL,

    question_status
        ENUM(
            'Not Visited',
            'Not Answered',
            'Answered',
            'Marked for Review',
            'Answered & Marked for Review'
        )
        NOT NULL DEFAULT 'Not Answered',

    answered_at
        DATETIME NULL,

    is_correct
        TINYINT(1)
        NOT NULL DEFAULT 0,

    marks_awarded
        DECIMAL(6,2)
        NOT NULL DEFAULT 0,

    updated_at
        TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_answer (
        attempt_id,
        question_id
    ),

    CONSTRAINT fk_answer_attempt
        FOREIGN KEY (attempt_id)
        REFERENCES exam_attempts(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_answer_question
        FOREIGN KEY (question_id)
        REFERENCES questions(id)
        ON DELETE RESTRICT,

    INDEX idx_answer_attempt_status (
        attempt_id,
        question_status
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   RESULTS
========================================================= */

CREATE TABLE IF NOT EXISTS results (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    attempt_id
        INT UNSIGNED NOT NULL UNIQUE,

    student_id
        INT UNSIGNED NOT NULL,

    exam_id
        INT UNSIGNED NOT NULL,

    total_questions
        SMALLINT UNSIGNED NOT NULL,

    attempted_questions
        SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    correct_answers
        SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    wrong_answers
        SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    unanswered_questions
        SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    total_marks
        DECIMAL(8,2)
        NOT NULL,

    obtained_marks
        DECIMAL(8,2)
        NOT NULL,

    percentage
        DECIMAL(5,2)
        NOT NULL,

    grade
        VARCHAR(4)
        NOT NULL,

    result_status
        ENUM('Pass','Fail')
        NOT NULL,

    created_at
        TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_result_attempt
        FOREIGN KEY (attempt_id)
        REFERENCES exam_attempts(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_result_student
        FOREIGN KEY (student_id)
        REFERENCES students(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_result_exam
        FOREIGN KEY (exam_id)
        REFERENCES exams(id)
        ON DELETE RESTRICT

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   SUBSCRIPTION PLANS
========================================================= */

CREATE TABLE IF NOT EXISTS subscription_plans (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name
        VARCHAR(50)
        NOT NULL UNIQUE,

    duration_months
        TINYINT UNSIGNED
        NOT NULL,

    price
        DECIMAL(10,2)
        NOT NULL,

    description
        TEXT NULL,

    benefits
        TEXT NULL,

    status
        ENUM('Active','Inactive')
        NOT NULL DEFAULT 'Active',

    created_at
        TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   SUBSCRIPTIONS
========================================================= */

CREATE TABLE IF NOT EXISTS subscriptions (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    student_id
        INT UNSIGNED NOT NULL,

    plan_id
        INT UNSIGNED NOT NULL,

    start_date
        DATE NOT NULL,

    end_date
        DATE NOT NULL,

    status
        ENUM(
            'Active',
            'Expired',
            'Cancelled'
        )
        NOT NULL DEFAULT 'Active',

    created_at
        TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_subscription_student
        FOREIGN KEY (student_id)
        REFERENCES students(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_subscription_plan
        FOREIGN KEY (plan_id)
        REFERENCES subscription_plans(id)
        ON DELETE RESTRICT,

    INDEX idx_subscription_access (
        student_id,
        status,
        end_date
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   SUBSCRIPTION PAYMENTS
========================================================= */

CREATE TABLE IF NOT EXISTS subscription_payments (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    student_id
        INT UNSIGNED NOT NULL,

    plan_id
        INT UNSIGNED NOT NULL,

    subscription_id
        INT UNSIGNED NULL,

    amount
        DECIMAL(10,2)
        NOT NULL,

    reference_no
        VARCHAR(50)
        NOT NULL UNIQUE,

    gateway_order_id
        VARCHAR(100)
        NULL UNIQUE,

    gateway_payment_id
        VARCHAR(100)
        NULL UNIQUE,

    gateway_signature
        VARCHAR(128)
        NULL,

    gateway_status
        VARCHAR(30)
        NULL,

    gateway_method
        VARCHAR(30)
        NULL,

    gateway_currency
        CHAR(3)
        NOT NULL DEFAULT 'INR',

    payment_status
        ENUM(
            'Pending',
            'Paid',
            'Failed',
            'Cancelled'
        )
        NOT NULL DEFAULT 'Pending',

    payment_method
        VARCHAR(30)
        NULL,

    paid_at
        DATETIME NULL,

    created_at
        TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_sp_student
        FOREIGN KEY (student_id)
        REFERENCES students(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_sp_plan
        FOREIGN KEY (plan_id)
        REFERENCES subscription_plans(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_sp_subscription
        FOREIGN KEY (subscription_id)
        REFERENCES subscriptions(id)
        ON DELETE SET NULL,

    INDEX idx_subscription_gateway_status (gateway_status),

    INDEX idx_subscription_payment_status (
        student_id,
        payment_status,
        created_at
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   LIVE EXAM PAYMENTS
========================================================= */

CREATE TABLE IF NOT EXISTS live_exam_payments (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    student_id
        INT UNSIGNED NOT NULL,

    exam_id
        INT UNSIGNED NOT NULL,

    amount
        DECIMAL(10,2)
        NOT NULL,

    reference_no
        VARCHAR(50)
        NOT NULL UNIQUE,

    payment_status
        ENUM(
            'Pending',
            'Paid',
            'Failed',
            'Cancelled'
        )
        NOT NULL DEFAULT 'Pending',

    payment_method
        ENUM(
            'Credit Card',
            'Debit Card',
            'UPI'
        )
        NULL,

    paid_at
        DATETIME NULL,

    created_at
        TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_lep_student
        FOREIGN KEY (student_id)
        REFERENCES students(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_lep_exam
        FOREIGN KEY (exam_id)
        REFERENCES exams(id)
        ON DELETE RESTRICT,

    UNIQUE KEY uq_exam_payment (
        student_id,
        exam_id
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   STUDY MATERIALS
========================================================= */

CREATE TABLE IF NOT EXISTS study_materials (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    subject_id
        INT UNSIGNED NULL,

    teacher_id
        INT UNSIGNED NULL,

    title
        VARCHAR(180)
        NOT NULL,

    description
        TEXT NULL,

    file_path
        VARCHAR(255)
        NOT NULL,

    access_type
        ENUM(
            'Public',
            'Subscription Only'
        )
        NOT NULL DEFAULT 'Public',

    status
        ENUM(
            'Active',
            'Inactive'
        )
        NOT NULL DEFAULT 'Active',

    uploaded_at
        TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_material_subject
        FOREIGN KEY (subject_id)
        REFERENCES subjects(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_material_teacher
        FOREIGN KEY (teacher_id)
        REFERENCES teachers(id)
        ON DELETE SET NULL

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   NOTIFICATIONS
========================================================= */

CREATE TABLE IF NOT EXISTS notifications (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    recipient_type
        ENUM(
            'Admin',
            'Teacher',
            'Student'
        )
        NOT NULL,

    recipient_id
        INT UNSIGNED NOT NULL,

    title
        VARCHAR(180)
        NOT NULL,

    message
        TEXT NOT NULL,

    notification_type
        VARCHAR(60)
        NOT NULL,

    reference_type
        VARCHAR(60)
        NULL,

    reference_id
        INT UNSIGNED
        NULL,

    is_read
        TINYINT(1)
        NOT NULL DEFAULT 0,

    created_at
        TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    read_at
        DATETIME NULL,

    INDEX idx_notification_recipient (
        recipient_type,
        recipient_id,
        is_read,
        created_at
    ),

    INDEX idx_notification_reference (
        reference_type,
        reference_id
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   DEFAULT SUBSCRIPTION PLANS
========================================================= */

INSERT INTO subscription_plans
(
    name,
    duration_months,
    price,
    description,
    benefits
)

VALUES

(
    '1 Month',
    1,
    99.00,
    'Flexible one-month access.',
    'Important study materials; subscription-enabled live exams'
),

(
    '3 Months',
    3,
    249.00,
    'A practical semester preparation plan.',
    'Important study materials; subscription-enabled live exams'
),

(
    '6 Months',
    6,
    449.00,
    'Best value for continuous preparation.',
    'Important study materials; subscription-enabled live exams'
)

ON DUPLICATE KEY UPDATE

    duration_months =
        VALUES(duration_months),

    price =
        VALUES(price),

    description =
        VALUES(description),

    benefits =
        VALUES(benefits),

    status =
        'Active';


/* =========================================================
   DEFAULT CATEGORIES
========================================================= */

INSERT INTO categories
(
    category_name,
    description,
    icon,
    status
)

VALUES

(
    'UPSC',
    'Civil Services and UPSC preparation with focused practice exams.',
    'fa-solid fa-building-columns',
    'Active'
),

(
    'GPSC',
    'Gujarat Public Service Commission preparation and practice.',
    'fa-solid fa-landmark',
    'Active'
),

(
    'NEET',
    'Medical entrance preparation with structured practice questions.',
    'fa-solid fa-user-doctor',
    'Active'
),

(
    'JEE',
    'Engineering entrance preparation with focused practice exams.',
    'fa-solid fa-atom',
    'Active'
),

(
    'Forest',
    'Forest and environment related competitive examination preparation.',
    'fa-solid fa-tree',
    'Active'
),

(
    'Railway',
    'Railway recruitment examination practice and preparation.',
    'fa-solid fa-train',
    'Active'
),

(
    'SSC',
    'Staff Selection Commission examination preparation.',
    'fa-solid fa-file-lines',
    'Active'
),

(
    'Banking',
    'Banking and financial sector competitive exam preparation.',
    'fa-solid fa-building-columns',
    'Active'
),

(
    'Police',
    'Police recruitment and competitive examination preparation.',
    'fa-solid fa-shield-halved',
    'Active'
),

(
    'Talati',
    'Talati and Gujarat government recruitment exam preparation.',
    'fa-solid fa-file-signature',
    'Active'
),

(
    'University',
    'University and college examination preparation.',
    'fa-solid fa-graduation-cap',
    'Active'
),

(
    'School',
    'School-level examination and academic preparation.',
    'fa-solid fa-school',
    'Active'
)

ON DUPLICATE KEY UPDATE

    description =
        VALUES(description),

    icon =
        VALUES(icon),

    status =
        VALUES(status);