-- ExamSphere: authoritative exam-level marks configuration

ALTER TABLE exams
    ADD COLUMN marks_per_question DECIMAL(6,2)
        NOT NULL DEFAULT 0
        AFTER required_question_count,

    ADD COLUMN negative_marks_per_question DECIMAL(6,2)
        NOT NULL DEFAULT 0
        AFTER marks_per_question;


-- Backfill legacy exams from their configured total/count
-- and existing question data.

UPDATE exams e

LEFT JOIN (
    SELECT
        eq.exam_id,
        ROUND(AVG(q.marks), 2) AS avg_marks,
        ROUND(AVG(q.negative_marks), 2) AS avg_negative_marks

    FROM exam_questions eq

    INNER JOIN questions q
        ON q.id = eq.question_id

    GROUP BY eq.exam_id

) x
    ON x.exam_id = e.id

SET
    e.marks_per_question =
        CASE

            WHEN e.required_question_count > 0
                THEN ROUND(
                    e.total_marks /
                    e.required_question_count,
                    2
                )

            ELSE COALESCE(
                x.avg_marks,
                0
            )

        END,

    e.negative_marks_per_question =
        COALESCE(
            x.avg_negative_marks,
            0
        )

WHERE
    e.marks_per_question = 0;