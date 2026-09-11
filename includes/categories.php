<?php

$homepageCategories = [];

try {

    /*
     * Check whether the Phase 0.3 category relationship
     * has been applied to the subjects table.
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
     * Category cards can still be displayed even when
     * subjects have not yet been connected.
     *
     * This is important because the category itself is
     * independent data.
     */
    $categoryStatement = $conn->query("
        SELECT
            c.id,
            c.category_name,
            c.description,
            c.icon
        FROM categories c
        WHERE c.status = 'Active'
        ORDER BY c.category_name ASC
        LIMIT 8
    ");

    $homepageCategories = $categoryStatement->fetchAll(
        PDO::FETCH_ASSOC
    );


    /*
     * Once subjects.category_id exists, calculate
     * real subject/exam/question counts.
     */
    if ($hasCategoryRelation && !empty($homepageCategories)) {

        $countStatement = $conn->prepare("
            SELECT
                COUNT(DISTINCT s.id) AS subject_count,

                COUNT(DISTINCT e.id) AS exam_count,

                COUNT(DISTINCT
                    CASE
                        WHEN q.status = 'Active'
                        THEN q.id
                    END
                ) AS question_count

            FROM categories c

            LEFT JOIN subjects s
                ON s.category_id = c.id
                AND s.status = 'Active'

            LEFT JOIN exams e
                ON e.subject_id = s.id
                AND e.exam_type = 'Practice'
                AND e.status = 'Active'

            LEFT JOIN exam_questions eq
                ON eq.exam_id = e.id

            LEFT JOIN questions q
                ON q.id = eq.question_id

            WHERE c.id = ?
        ");

        foreach ($homepageCategories as &$category) {

            $countStatement->execute([
                (int) $category['id']
            ]);

            $counts = $countStatement->fetch(
                PDO::FETCH_ASSOC
            );

            $category['subject_count'] =
                (int) ($counts['subject_count'] ?? 0);

            $category['exam_count'] =
                (int) ($counts['exam_count'] ?? 0);

            $category['question_count'] =
                (int) ($counts['question_count'] ?? 0);
        }

        unset($category);

    } else {

        foreach ($homepageCategories as &$category) {

            $category['subject_count'] = 0;
            $category['exam_count'] = 0;
            $category['question_count'] = 0;
        }

        unset($category);
    }

} catch (Throwable $exception) {

    error_log(
        'ExamSphere homepage category error: ' .
        $exception->getMessage()
    );

    $homepageCategories = [];
}

?>


<section
    class="landing-section dynamic-categories-section"
    id="categories"
>

    <div class="container">

        <div class="dynamic-category-heading reveal">

            <div>

                <span class="dynamic-category-kicker">

                    <i class="fa-solid fa-layer-group"></i>

                    EXPLORE CATEGORIES

                </span>


                <h2>
                    Choose your exam.
                    <em>Build your future.</em>
                </h2>


                <p>
                    Explore ExamSphere categories and find
                    the right path for your examination preparation.
                </p>

            </div>


            <div>

                <span class="dynamic-category-note">

                    <i class="fa-solid fa-shield-halved"></i>

                    ExamSphere

                </span>

            </div>

        </div>


        <?php if (!empty($homepageCategories)): ?>

            <div class="dynamic-category-grid">

                <?php foreach ($homepageCategories as $index => $category): ?>

                    <?php

                    $categoryId =
                        (int) $category['id'];

                    $categoryName =
                        trim(
                            (string) $category['category_name']
                        );

                    $description =
                        trim(
                            (string) (
                                $category['description'] ?? ''
                            )
                        );

                    $icon =
                        trim(
                            (string) (
                                $category['icon'] ?? ''
                            )
                        );

                    if ($icon === '') {
                        $icon =
                            'fa-solid fa-book-open';
                    }

                    $subjectCount =
                        (int) (
                            $category['subject_count'] ?? 0
                        );

                    $examCount =
                        (int) (
                            $category['exam_count'] ?? 0
                        );

                    $questionCount =
                        (int) (
                            $category['question_count'] ?? 0
                        );

                    ?>

                    <article
                        class="dynamic-category-card reveal"
                    >

                        <div
                            class="dynamic-category-card-top"
                        >

                            <span
                                class="dynamic-category-index"
                            >

                                <?= str_pad(
                                    (string) ($index + 1),
                                    2,
                                    '0',
                                    STR_PAD_LEFT
                                ) ?>

                            </span>


                            <span
                                class="dynamic-category-icon"
                            >

                                <i
                                    class="<?= htmlspecialchars(
                                        $icon,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                ></i>

                            </span>

                        </div>


                        <div
                            class="dynamic-category-content"
                        >

                            <h3>

                                <?= htmlspecialchars(
                                    $categoryName,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </h3>


                            <p>

                                <?= htmlspecialchars(
                                    $description !== ''
                                        ? $description
                                        : 'Explore preparation resources for this category.',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </p>

                        </div>


                        <div
                            class="dynamic-category-stats"
                        >

                            <span>

                                <i
                                    class="fa-solid fa-book-open"
                                ></i>

                                <strong>
                                    <?= $subjectCount ?>
                                </strong>

                                Subjects

                            </span>


                            <span>

                                <i
                                    class="fa-solid fa-file-circle-check"
                                ></i>

                                <strong>
                                    <?= $examCount ?>
                                </strong>

                                Exams

                            </span>


                            <span>

                                <i
                                    class="fa-solid fa-circle-question"
                                ></i>

                                <strong>
                                    <?= $questionCount ?>
                                </strong>

                                Questions

                            </span>

                        </div>


                        <a
                            href="auth/login.php"
                            class="dynamic-category-action"
                        >

                            Login to practice

                            <i
                                class="fa-solid fa-arrow-right"
                            ></i>

                        </a>

                    </article>

                <?php endforeach; ?>

            </div>


            <div
                class="dynamic-category-footer reveal"
            >

                <div>

                    <span>

                        <i
                            class="fa-solid fa-sparkles"
                        ></i>

                        EXAMSPHERE CATEGORIES

                    </span>


                    <strong>
                        Choose your target examination and start preparing.
                    </strong>

                </div>


                <a
                    href="auth/login.php"
                    class="landing-btn primary"
                >

                    Start practising

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>


        <?php else: ?>

            <div
                class="dynamic-category-empty reveal"
            >

                <div
                    class="dynamic-category-empty-icon"
                >

                    <i
                        class="fa-solid fa-layer-group"
                    ></i>

                </div>


                <h3>
                    No active categories found.
                </h3>


                <p>
                    Add an active category from the Admin Panel
                    and it will automatically appear here.
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>