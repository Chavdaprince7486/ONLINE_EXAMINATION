/* ExamSphere — Attempt Answer Integrity Migration */

/*
Keep the newest answer/state row for each attempt/question pair.
*/
DELETE a1
FROM answers a1
INNER JOIN answers a2
    ON a1.attempt_id = a2.attempt_id
    AND a1.question_id = a2.question_id
    AND a1.id < a2.id;

/*
Prevent duplicate answer/state rows.
*/
ALTER TABLE answers
    ADD UNIQUE KEY uq_answers_attempt_question (attempt_id, question_id);
