<?php

$homepageStats = [
    'students' => 0,
    'practice_exams' => 0,
    'questions' => 0,
    'completed_attempts' => 0
];

try {

    /*
     * Registered students
     *
     * Only Active students are counted because inactive
     * accounts should not be presented as active learners.
     */
    $studentStatement = $conn->query("
        SELECT COUNT(*)
        FROM students
        WHERE status = 'Active'
    ");

    $homepageStats['students'] =
        (int) $studentStatement->fetchColumn();


    /*
     * Active Practice Exams
     *
     * Only Active Practice exams are counted.
     *
     * Additionally, the exam must have the configured
     * number of active assigned questions.
     */
    $practiceStatement = $conn->query("
        SELECT COUNT(*)
        FROM (
            SELECT
                e.id,
                e.required_question_count,

                COUNT(
                    DISTINCT CASE
                        WHEN q.status = 'Active'
                        THEN q.id
                    END
                ) AS active_question_count

            FROM exams e

            LEFT JOIN exam_questions eq
                ON eq.exam_id = e.id

            LEFT JOIN questions q
                ON q.id = eq.question_id

            WHERE e.exam_type = 'Practice'
                AND e.status = 'Active'
                AND e.required_question_count > 0

            GROUP BY
                e.id,
                e.required_question_count

            HAVING
                active_question_count =
                e.required_question_count

        ) ready_practice_exams
    ");

    $homepageStats['practice_exams'] =
        (int) $practiceStatement->fetchColumn();


    /*
     * Active questions
     */
    $questionStatement = $conn->query("
        SELECT COUNT(*)
        FROM questions
        WHERE status = 'Active'
    ");

    $homepageStats['questions'] =
        (int) $questionStatement->fetchColumn();


    /*
     * Completed exam attempts
     *
     * Both manually submitted and auto-submitted attempts
     * are treated as completed.
     */
    $attemptStatement = $conn->query("
        SELECT COUNT(*)
        FROM exam_attempts
        WHERE status IN (
            'Submitted',
            'Auto Submitted'
        )
    ");

    $homepageStats['completed_attempts'] =
        (int) $attemptStatement->fetchColumn();


} catch (Throwable $exception) {

    error_log(
        'ExamSphere homepage statistics error: ' .
        $exception->getMessage()
    );
}

?>


<section class="landing-stats">

    <div class="container">

        <div class="glass-stat-strip reveal">


            <!-- ==========================================
                 STUDENTS
            =========================================== -->

            <div class="homepage-stat-item">

                <i class="fa-solid fa-user-graduate"></i>

                <span>

                    <b
                        class="counter"
                        data-target="<?= $homepageStats['students'] ?>"
                    >
                        0
                    </b>

                    <small>
                        Active learners
                    </small>

                </span>

            </div>


            <!-- ==========================================
                 PRACTICE EXAMS
            =========================================== -->

            <div class="homepage-stat-item">

                <i class="fa-solid fa-file-circle-check"></i>

                <span>

                    <b
                        class="counter"
                        data-target="<?= $homepageStats['practice_exams'] ?>"
                    >
                        0
                    </b>

                    <small>
                        Ready practice exams
                    </small>

                </span>

            </div>


            <!-- ==========================================
                 QUESTIONS
            =========================================== -->

            <div class="homepage-stat-item">

                <i class="fa-solid fa-circle-question"></i>

                <span>

                    <b
                        class="counter"
                        data-target="<?= $homepageStats['questions'] ?>"
                    >
                        0
                    </b>

                    <small>
                        Active questions
                    </small>

                </span>

            </div>


            <!-- ==========================================
                 COMPLETED ATTEMPTS
            =========================================== -->

            <div class="homepage-stat-item">

                <i class="fa-solid fa-chart-line"></i>

                <span>

                    <b
                        class="counter"
                        data-target="<?= $homepageStats['completed_attempts'] ?>"
                    >
                        0
                    </b>

                    <small>
                        Completed attempts
                    </small>

                </span>

            </div>

        </div>

    </div>

</section>