<?php

$homepagePracticeExams = [];

try {

    /*
     * The category relationship is supplied by the Phase 0.3 migration.
     * We check for it so the homepage remains compatible with a database
     * that has not yet received that migration.
     */
    $columnCheck = $conn->query("
        SHOW COLUMNS
        FROM subjects
        LIKE 'category_id'
    ");

    $hasCategoryRelation = (bool) $columnCheck->fetch(
        PDO::FETCH_ASSOC
    );


    /*
     * Load only complete, active Practice exams.
     *
     * An exam is considered homepage-ready only when:
     *
     * required_question_count > 0
     * AND
     * active assigned questions == required_question_count
     */
    if ($hasCategoryRelation) {

        $practiceSql = "
            SELECT
                e.id,
                e.title,
                e.description,
                e.exam_type,
                e.duration_minutes,
                e.required_question_count,
                e.total_marks,
                e.passing_marks,
                e.subscription_required,

                s.id AS subject_id,
                s.name AS subject_name,

                c.id AS category_id,
                c.category_name,

                COUNT(
                    CASE
                        WHEN q.status = 'Active'
                        THEN eq.question_id
                    END
                ) AS question_count

            FROM exams e

            INNER JOIN subjects s
                ON s.id = e.subject_id
                AND s.status = 'Active'

            LEFT JOIN categories c
                ON c.id = s.category_id
                AND c.status = 'Active'

            LEFT JOIN exam_questions eq
                ON eq.exam_id = e.id

            LEFT JOIN questions q
                ON q.id = eq.question_id

            WHERE e.exam_type = 'Practice'
                AND e.status = 'Active'
                AND e.required_question_count > 0

            GROUP BY
                e.id,
                e.title,
                e.description,
                e.exam_type,
                e.duration_minutes,
                e.required_question_count,
                e.total_marks,
                e.passing_marks,
                e.subscription_required,
                s.id,
                s.name,
                c.id,
                c.category_name

            HAVING COUNT(
                CASE
                    WHEN q.status = 'Active'
                    THEN eq.question_id
                END
            ) = e.required_question_count

            ORDER BY
                e.created_at DESC,
                e.id DESC

            LIMIT 6
        ";

    } else {

        $practiceSql = "
            SELECT
                e.id,
                e.title,
                e.description,
                e.exam_type,
                e.duration_minutes,
                e.required_question_count,
                e.total_marks,
                e.passing_marks,
                e.subscription_required,

                s.id AS subject_id,
                s.name AS subject_name,

                NULL AS category_id,
                NULL AS category_name,

                COUNT(
                    CASE
                        WHEN q.status = 'Active'
                        THEN eq.question_id
                    END
                ) AS question_count

            FROM exams e

            INNER JOIN subjects s
                ON s.id = e.subject_id
                AND s.status = 'Active'

            LEFT JOIN exam_questions eq
                ON eq.exam_id = e.id

            LEFT JOIN questions q
                ON q.id = eq.question_id

            WHERE e.exam_type = 'Practice'
                AND e.status = 'Active'
                AND e.required_question_count > 0

            GROUP BY
                e.id,
                e.title,
                e.description,
                e.exam_type,
                e.duration_minutes,
                e.required_question_count,
                e.total_marks,
                e.passing_marks,
                e.subscription_required,
                s.id,
                s.name

            HAVING COUNT(
                CASE
                    WHEN q.status = 'Active'
                    THEN eq.question_id
                END
            ) = e.required_question_count

            ORDER BY
                e.created_at DESC,
                e.id DESC

            LIMIT 6
        ";
    }


    $practiceStatement = $conn->prepare(
        $practiceSql
    );

    $practiceStatement->execute();

    $homepagePracticeExams =
        $practiceStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $exception) {

    error_log(
        'ExamSphere homepage practice exam query failed: ' .
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

        <div class="homepage-practice-heading reveal">

            <div>

                <span class="homepage-practice-kicker">

                    <i class="fa-solid fa-pen-to-square"></i>

                    FREE TO START

                </span>


                <h2>
                    Practice with exams
                    <em>that are ready to take.</em>
                </h2>


                <p>
                    Build confidence with active ExamSphere practice exams.
                    Only exams with their complete question set are shown here.
                </p>

            </div>


            <div>

                <a
                    href="auth/login.php"
                    class="homepage-practice-view-all"
                >

                    View all practice exams

                    <i class="fa-solid fa-arrow-right"></i>

                </a>

            </div>

        </div>


        <?php if (!empty($homepagePracticeExams)): ?>

            <div class="homepage-practice-grid">

                <?php foreach (
                    $homepagePracticeExams
                    as $index => $exam
                ): ?>

                    <?php

                    $examId =
                        (int) $exam['id'];

                    $title =
                        trim(
                            (string) $exam['title']
                        );

                    $description =
                        trim(
                            (string) (
                                $exam['description'] ?? ''
                            )
                        );

                    $subjectName =
                        trim(
                            (string) (
                                $exam['subject_name'] ?? ''
                            )
                        );

                    $categoryName =
                        trim(
                            (string) (
                                $exam['category_name'] ?? ''
                            )
                        );

                    $duration =
                        (int) (
                            $exam['duration_minutes'] ?? 0
                        );

                    $questionCount =
                        (int) (
                            $exam['question_count'] ?? 0
                        );

                    $requiredCount =
                        (int) (
                            $exam['required_question_count'] ?? 0
                        );

                    $totalMarks =
                        (float) (
                            $exam['total_marks'] ?? 0
                        );

                    $passingMarks =
                        (float) (
                            $exam['passing_marks'] ?? 0
                        );

                    $isSubscriptionRequired =
                        (int) (
                            $exam['subscription_required'] ?? 0
                        ) === 1;


                    if ($description === '') {

                        $description =
                            'Test your preparation with this active practice examination.';
                    }


                    $number =
                        str_pad(
                            (string) ($index + 1),
                            2,
                            '0',
                            STR_PAD_LEFT
                        );

                    ?>

                    <article
                        class="homepage-practice-card reveal"
                    >

                        <div
                            class="homepage-practice-card-top"
                        >

                            <span
                                class="homepage-practice-number"
                            >
                                <?= $number ?>
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


                        <div
                            class="homepage-practice-icon"
                        >

                            <i
                                class="fa-solid fa-file-circle-check"
                            ></i>

                        </div>


                        <div
                            class="homepage-practice-content"
                        >

                            <?php if ($categoryName !== ''): ?>

                                <span
                                    class="homepage-practice-category"
                                >

                                    <?= htmlspecialchars(
                                        $categoryName,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>

                            <?php endif; ?>


                            <h3>

                                <?= htmlspecialchars(
                                    $title,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </h3>


                            <?php if ($subjectName !== ''): ?>

                                <p
                                    class="homepage-practice-subject"
                                >

                                    <i
                                        class="fa-solid fa-book-open"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $subjectName,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </p>

                            <?php endif; ?>


                            <p
                                class="homepage-practice-description"
                            >

                                <?= htmlspecialchars(
                                    $description,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </p>

                        </div>


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

                                <?= rtrim(
                                    rtrim(
                                        number_format(
                                            $totalMarks,
                                            2,
                                            '.',
                                            ''
                                        ),
                                        '0'
                                    ),
                                    '.'
                                ) ?>

                                Marks

                            </span>

                        </div>


                        <div
                            class="homepage-practice-footer"
                        >

                            <div>

                                <small>
                                    Passing marks
                                </small>

                                <strong>

                                    <?= rtrim(
                                        rtrim(
                                            number_format(
                                                $passingMarks,
                                                2,
                                                '.',
                                                ''
                                            ),
                                            '0'
                                        ),
                                        '.'
                                    ) ?>

                                </strong>

                            </div>


                            <?php if (
                                $isSubscriptionRequired
                            ): ?>

                                <span
                                    class="homepage-practice-access premium"
                                >

                                    <i
                                        class="fa-solid fa-crown"
                                    ></i>

                                    Subscription

                                </span>

                            <?php else: ?>

                                <span
                                    class="homepage-practice-access"
                                >

                                    <i
                                        class="fa-solid fa-unlock"
                                    ></i>

                                    Free practice

                                </span>

                            <?php endif; ?>

                        </div>


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


            <div
                class="homepage-practice-bottom reveal"
            >

                <div>

                    <span>
                        <i class="fa-solid fa-bolt"></i>
                        INSTANT START
                    </span>

                    <strong>
                        More active exams are available after student login.
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
                    No complete practice exams are available yet.
                </h3>


                <p>
                    Practice exams will appear here automatically
                    after an administrator publishes an exam with
                    the required number of active questions.
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>