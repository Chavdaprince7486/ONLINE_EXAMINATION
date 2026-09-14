<?php

declare(strict_types=1);

$homepagePracticeExams = [];


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function homepage_practice_escape(
    mixed $value
): string {

    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function homepage_practice_number(
    mixed $value
): string {

    $number =
        round(
            (float)$value,
            2
        );

    if (
        floor($number) === $number
    ) {

        return number_format(
            $number,
            0,
            '.',
            ''
        );
    }

    return rtrim(
        rtrim(
            number_format(
                $number,
                2,
                '.',
                ''
            ),
            '0'
        ),
        '.'
    );
}


try {

    /*
    |--------------------------------------------------------------------------
    | DYNAMIC PRACTICE EXAMS
    |--------------------------------------------------------------------------
    |
    | An exam appears automatically when:
    |
    | 1. exam_type = Practice
    | 2. status = Active
    | 3. required_question_count > 0
    | 4. active assigned question count matches required count
    | 5. every assigned question has valid marks
    | 6. total marks = question count × marks per question
    |
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT

            e.id,
            e.title,
            e.description,
            e.exam_type,

            e.duration_minutes,

            e.required_question_count,

            e.passing_marks,

            e.subscription_required,

            e.exam_fee,

            e.created_at,
            e.updated_at,

            s.id AS subject_id,
            s.name AS subject_name,
            s.code AS subject_code,

            COUNT(
                DISTINCT
                CASE
                    WHEN q.status = 'Active'
                    THEN q.id
                END
            ) AS question_count,

            MIN(
                CASE
                    WHEN q.status = 'Active'
                    THEN q.marks
                END
            ) AS marks_per_question,

            MAX(
                CASE
                    WHEN q.status = 'Active'
                    THEN q.marks
                END
            ) AS max_question_marks,

            SUM(
                CASE
                    WHEN q.status = 'Active'
                    THEN q.marks
                    ELSE 0
                END
            ) AS calculated_total_marks

        FROM exams e


        INNER JOIN subjects s
            ON s.id = e.subject_id
            AND s.status = 'Active'


        INNER JOIN exam_questions eq
            ON eq.exam_id = e.id


        INNER JOIN questions q
            ON q.id = eq.question_id
            AND q.status = 'Active'


        WHERE

            e.exam_type = 'Practice'

            AND e.status = 'Active'

            AND e.required_question_count > 0


        GROUP BY

            e.id,
            e.title,
            e.description,
            e.exam_type,
            e.duration_minutes,
            e.required_question_count,
            e.passing_marks,
            e.subscription_required,
            e.exam_fee,
            e.created_at,
            e.updated_at,
            s.id,
            s.name,
            s.code


        HAVING

            question_count =
                e.required_question_count

            AND marks_per_question IS NOT NULL

            AND max_question_marks =
                marks_per_question

            AND calculated_total_marks > 0


        ORDER BY

            e.created_at DESC,
            e.id DESC


        LIMIT 6
    ";


    $statement =
        $conn->prepare(
            $sql
        );


    $statement->execute();


    $homepagePracticeExams =
        $statement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (
    Throwable $exception
) {

    error_log(
        'Homepage practice exams failed: ' .
        $exception->getMessage()
    );

    $homepagePracticeExams = [];

}

?>


<section
    class="landing-section homepage-practice-section"
    id="practice-exams"
>

    <div class="container">


        <!-- =========================================================
             HEADER
             ========================================================= -->


        <div
            class="homepage-practice-heading reveal"
        >


            <div>

                <span
                    class="homepage-practice-kicker"
                >

                    <i
                        class="fa-solid fa-pen-to-square"
                    ></i>

                    FREE TO START

                </span>


                <h2>

                    Practice with exams
                    <em>
                        that are ready to take.
                    </em>

                </h2>


                <p>

                    Build confidence with free
                    ExamSphere practice exams.
                    Every published Practice Exam
                    appears here automatically.

                </p>

            </div>


            <div>

                <a
                    href="auth/login.php"
                    class="homepage-practice-view-all"
                >

                    View all practice exams

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>


        </div>


        <!-- =========================================================
             EXAMS
             ========================================================= -->


        <?php if (
            !empty(
                $homepagePracticeExams
            )
        ): ?>


            <div
                class="homepage-practice-grid"
            >


                <?php foreach (
                    $homepagePracticeExams
                    as $index => $exam
                ): ?>


                    <?php

                    $examId =
                        (int)$exam['id'];


                    $title =
                        trim(
                            (string)(
                                $exam['title']
                                ?? ''
                            )
                        );


                    $description =
                        trim(
                            (string)(
                                $exam['description']
                                ?? ''
                            )
                        );


                    $subjectName =
                        trim(
                            (string)(
                                $exam['subject_name']
                                ?? ''
                            )
                        );


                    $subjectCode =
                        trim(
                            (string)(
                                $exam['subject_code']
                                ?? ''
                            )
                        );


                    $duration =
                        (int)(
                            $exam[
                                'duration_minutes'
                            ] ?? 0
                        );


                    $questionCount =
                        (int)(
                            $exam[
                                'question_count'
                            ] ?? 0
                        );


                    $marksPerQuestion =
                        (float)(
                            $exam[
                                'marks_per_question'
                            ] ?? 0
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | TOTAL MARKS = QUESTIONS × MARKS PER QUESTION
                    |--------------------------------------------------------------------------
                    */

                    $totalMarks =
                        round(
                            $questionCount *
                            $marksPerQuestion,
                            2
                        );


                    $passingMarks =
                        (float)(
                            $exam[
                                'passing_marks'
                            ] ?? 0
                        );


                    $number =
                        str_pad(
                            (string)(
                                $index + 1
                            ),
                            2,
                            '0',
                            STR_PAD_LEFT
                        );


                    if (
                        $description === ''
                    ) {

                        $description =
                            'Test your preparation with this free practice examination.';
                    }

                    ?>


                    <article
                        class="homepage-practice-card reveal"
                    >


                        <!-- TOP -->


                        <div
                            class="homepage-practice-card-top"
                        >


                            <span
                                class="homepage-practice-number"
                            >

                                <?= homepage_practice_escape(
                                    $number
                                ) ?>

                            </span>


                            <span
                                class="homepage-practice-status"
                            >

                                <i
                                    class="fa-solid fa-circle-check"
                                ></i>

                                Ready

                            </span>


                        </div>


                        <!-- ICON -->


                        <div
                            class="homepage-practice-icon"
                        >

                            <i
                                class="fa-solid fa-file-circle-check"
                            ></i>

                        </div>


                        <!-- CONTENT -->


                        <div
                            class="homepage-practice-content"
                        >


                            <span
                                class="homepage-practice-category"
                            >

                                FREE PRACTICE

                            </span>


                            <h3>

                                <?= homepage_practice_escape(
                                    $title
                                ) ?>

                            </h3>


                            <?php if (
                                $subjectName !== ''
                            ): ?>


                                <p
                                    class="homepage-practice-subject"
                                >

                                    <i
                                        class="fa-solid fa-book-open"
                                    ></i>

                                    <?= homepage_practice_escape(
                                        $subjectName
                                    ) ?>


                                    <?php if (
                                        $subjectCode !== ''
                                    ): ?>

                                        <span
                                            style="
                                                opacity:.65;
                                                margin-left:4px;
                                            "
                                        >

                                            (
                                            <?= homepage_practice_escape(
                                                $subjectCode
                                            ) ?>
                                            )

                                        </span>

                                    <?php endif; ?>


                                </p>


                            <?php endif; ?>


                            <p
                                class="homepage-practice-description"
                            >

                                <?= homepage_practice_escape(
                                    $description
                                ) ?>

                            </p>


                        </div>


                        <!-- META -->


                        <div
                            class="homepage-practice-meta"
                        >


                            <span>

                                <i
                                    class="fa-regular fa-clock"
                                ></i>

                                <?= $duration ?>

                                min

                            </span>


                            <span>

                                <i
                                    class="fa-solid fa-circle-question"
                                ></i>

                                <?= $questionCount ?>

                                Questions

                            </span>


                            <span>

                                <i
                                    class="fa-solid fa-star"
                                ></i>

                                <?= homepage_practice_number(
                                    $totalMarks
                                ) ?>

                                Marks

                            </span>


                        </div>


                        <!-- FOOTER -->


                        <div
                            class="homepage-practice-footer"
                        >


                            <div>

                                <small>
                                    Passing marks
                                </small>


                                <strong>

                                    <?= homepage_practice_number(
                                        $passingMarks
                                    ) ?>

                                </strong>

                            </div>


                            <span
                                class="homepage-practice-access"
                            >

                                <i
                                    class="fa-solid fa-unlock"
                                ></i>

                                Free practice

                            </span>


                        </div>


                        <!-- ACTION -->


                        <a
                            href="auth/login.php?redirect=practice_exam&exam_id=<?= $examId ?>"
                            class="homepage-practice-action"
                        >

                            Login to start

                            <i
                                class="fa-solid fa-arrow-right"
                            ></i>

                        </a>


                    </article>


                <?php endforeach; ?>


            </div>


            <!-- =====================================================
                 BOTTOM
                 ===================================================== -->


            <div
                class="homepage-practice-bottom reveal"
            >


                <div>

                    <span>

                        <i
                            class="fa-solid fa-bolt"
                        ></i>

                        INSTANT START

                    </span>


                    <strong>

                        New published Practice Exams
                        automatically appear here.

                    </strong>

                </div>


                <a
                    href="auth/login.php"
                    class="landing-btn primary"
                >

                    Explore practice

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>


            </div>


        <?php else: ?>


            <!-- =====================================================
                 EMPTY
                 ===================================================== -->


            <div
                class="homepage-practice-empty reveal"
            >


                <div
                    class="homepage-practice-empty-icon"
                >

                    <i
                        class="fa-solid fa-file-circle-question"
                    ></i>

                </div>


                <h3>

                    No practice exams are available yet.

                </h3>


                <p>

                    Published Practice Exams will
                    automatically appear here as soon as
                    their configured questions are ready.

                </p>


            </div>


        <?php endif; ?>


    </div>

</section>