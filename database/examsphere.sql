CREATE DATABASE IF NOT EXISTS online_examination CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE online_examination;

CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  last_login DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE teachers (
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
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(120) NOT NULL UNIQUE,
  description TEXT NULL,
  icon VARCHAR(100) NOT NULL DEFAULT 'fa-solid fa-book',
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE students (
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
) ENGINE=InnoDB;

CREATE TABLE subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  code VARCHAR(30) NULL UNIQUE,
  description TEXT NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE exams (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_id INT UNSIGNED NULL,
  teacher_id INT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  exam_type ENUM('Practice','Live') NOT NULL DEFAULT 'Practice',
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  required_question_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total_marks DECIMAL(8,2) NOT NULL DEFAULT 0,
  passing_marks DECIMAL(8,2) NOT NULL DEFAULT 0,
  negative_marking TINYINT(1) NOT NULL DEFAULT 0,
  exam_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  subscription_required TINYINT(1) NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  status ENUM('Draft','Scheduled','Live','Completed','Cancelled','Active','Upcoming','Running') NOT NULL DEFAULT 'Draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_exam_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
  CONSTRAINT fk_exam_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
  INDEX idx_exam_type_status (exam_type, status), INDEX idx_exam_schedule (starts_at)
) ENGINE=InnoDB;

CREATE TABLE questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_id INT UNSIGNED NULL,
  created_by_teacher_id INT UNSIGNED NULL,
  question_type ENUM('MCQ','TrueFalse') NOT NULL DEFAULT 'MCQ',
  question_text TEXT NOT NULL,
  option_a VARCHAR(500) NOT NULL,
  option_b VARCHAR(500) NOT NULL,
  option_c VARCHAR(500) NULL,
  option_d VARCHAR(500) NULL,
  correct_answer ENUM('A','B','C','D') NOT NULL,
  marks DECIMAL(6,2) NOT NULL DEFAULT 1,
  negative_marks DECIMAL(6,2) NOT NULL DEFAULT 0,
  difficulty ENUM('Easy','Medium','Hard') NOT NULL DEFAULT 'Medium',
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_question_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
  CONSTRAINT fk_question_teacher FOREIGN KEY (created_by_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE exam_questions (
  exam_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (exam_id, question_id),
  CONSTRAINT fk_eq_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  CONSTRAINT fk_eq_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE exam_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  exam_id INT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_at DATETIME NULL,
  status ENUM('Started','Submitted','Auto Submitted') NOT NULL DEFAULT 'Started',
  obtained_marks DECIMAL(8,2) NOT NULL DEFAULT 0,
  percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_attempt_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
  CONSTRAINT fk_attempt_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE RESTRICT,
  INDEX idx_attempt_student (student_id, submitted_at),
  INDEX idx_attempt_exam (student_id, exam_id, status)
) ENGINE=InnoDB;

CREATE TABLE answers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  selected_answer ENUM('A','B','C','D') NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  marks_awarded DECIMAL(6,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_answer (attempt_id, question_id),
  CONSTRAINT fk_answer_attempt FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_answer_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE results (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT UNSIGNED NOT NULL UNIQUE,
  student_id INT UNSIGNED NOT NULL,
  exam_id INT UNSIGNED NOT NULL,
  total_questions SMALLINT UNSIGNED NOT NULL,
  attempted_questions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  correct_answers SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  wrong_answers SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  unanswered_questions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total_marks DECIMAL(8,2) NOT NULL,
  obtained_marks DECIMAL(8,2) NOT NULL,
  percentage DECIMAL(5,2) NOT NULL,
  grade VARCHAR(4) NOT NULL,
  result_status ENUM('Pass','Fail') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_result_attempt FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_result_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
  CONSTRAINT fk_result_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE subscription_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  duration_months TINYINT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  description TEXT NULL,
  benefits TEXT NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE subscriptions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status ENUM('Active','Expired','Cancelled') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_subscription_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
  CONSTRAINT fk_subscription_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT,
  INDEX idx_subscription_access (student_id, status, end_date)
) ENGINE=InnoDB;

CREATE TABLE subscription_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  subscription_id INT UNSIGNED NULL,
  amount DECIMAL(10,2) NOT NULL,
  reference_no VARCHAR(50) NOT NULL UNIQUE,
  payment_status ENUM('Pending','Paid','Failed','Cancelled') NOT NULL DEFAULT 'Pending',
  payment_method ENUM('Credit Card','Debit Card','UPI') NULL,
  paid_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sp_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sp_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sp_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE live_exam_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  exam_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  reference_no VARCHAR(50) NOT NULL UNIQUE,
  payment_status ENUM('Pending','Paid','Failed','Cancelled') NOT NULL DEFAULT 'Pending',
  payment_method ENUM('Credit Card','Debit Card','UPI') NULL,
  paid_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lep_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
  CONSTRAINT fk_lep_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_exam_payment (student_id, exam_id)
) ENGINE=InnoDB;

CREATE TABLE study_materials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_id INT UNSIGNED NULL,
  teacher_id INT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  file_path VARCHAR(255) NOT NULL,
  access_type ENUM('Public','Subscription Only') NOT NULL DEFAULT 'Public',
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_material_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
  CONSTRAINT fk_material_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO subscription_plans (name, duration_months, price, description, benefits) VALUES
('1 Month', 1, 99.00, 'Flexible one-month access.', 'Important study materials; subscription-enabled live exams'),
('3 Months', 3, 249.00, 'A practical semester preparation plan.', 'Important study materials; subscription-enabled live exams'),
('6 Months', 6, 449.00, 'Best value for continuous preparation.', 'Important study materials; subscription-enabled live exams');

-- Initial demonstration administrator. Change this password immediately after first login.
-- Email: admin@examsphere.local | Password: Admin@123
INSERT INTO admins (full_name, email, password, status) VALUES
('ExamSphere Administrator', 'admin@examsphere.local', '$2y$10$5PEqVwHsGYP4QvlNuR87N.4zbUc.IH5RukGQhLY31FiRBDK.PROne', 'Active');

-- Demonstration accounts for the project presentation.
-- Student email: chavdaprince7487@gmail.com | Password: studentprince
INSERT INTO students (student_code, full_name, email, mobile, password, email_verified, status) VALUES
('STU00001', 'Chavda Prince', 'chavdaprince7487@gmail.com', '0000000000', '$2y$10$.cZNlxYetFBvxG/hiEq6xeeG/rjD.gvPbD3Y6bmCF3jfPktzmUGUa', 'Yes', 'Active');

-- Admin email: chavdaprince7486@gmail.com | Password: adminprince
INSERT INTO admins (full_name, email, password, status) VALUES
('Chavda Prince', 'chavdaprince7486@gmail.com', '$2y$10$0gSgO3KaWwqgRToPSQm6E.qtn5ZfiJ7lEY0gpQ/hLu8Jo6RcMoMBy', 'Active');
