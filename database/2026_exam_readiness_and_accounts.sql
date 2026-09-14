USE online_examination;

-- An exam is available only when this number matches its assigned question count.
ALTER TABLE exams
  ADD COLUMN required_question_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER duration_minutes;

-- Project demonstration accounts. Passwords are stored using PHP password_hash().
INSERT INTO students
  (student_code, full_name, email, mobile, password, email_verified, status)
VALUES
  ('STU00001', 'Chavda Prince', 'chavdaprince7487@gmail.com', '0000000000', '$2y$10$.cZNlxYetFBvxG/hiEq6xeeG/rjD.gvPbD3Y6bmCF3jfPktzmUGUa', 'Yes', 'Active')
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name), mobile = VALUES(mobile), password = VALUES(password),
  email_verified = 'Yes', status = 'Active';

INSERT INTO admins
  (full_name, email, password, status)
VALUES
  ('Chavda Prince', 'chavdaprince7486@gmail.com', '$2y$10$0gSgO3KaWwqgRToPSQm6E.qtn5ZfiJ7lEY0gpQ/hLu8Jo6RcMoMBy', 'Active')
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name), password = VALUES(password), status = 'Active';
