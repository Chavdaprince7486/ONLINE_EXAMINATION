<?php

$homepageMaterials = [];

try {

    /*
     * Check whether subjects.category_id exists.
     *
     * The homepage should remain functional even if the
     * category migration has not been applied.
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
     * Build the query according to the actual database
     * available on the current installation.
     *
     * IMPORTANT:
     * file_path is intentionally not selected for public output.
     */
    if ($hasCategoryRelation) {

        $materialSql = "
            SELECT
                m.id,
                m.title,
                m.description,
                m.access_type,
                m.status,
                m.uploaded_at,

                s.id AS subject_id,
                s.name AS subject_name,

                c.id AS category_id,
                c.category_name

            FROM study_materials m

            LEFT JOIN subjects s
                ON s.id = m.subject_id

            LEFT JOIN categories c
                ON c.id = s.category_id

            WHERE m.status = 'Active'

            ORDER BY
                m.uploaded_at DESC,
                m.id DESC

            LIMIT 6
        ";

    } else {

        $materialSql = "
            SELECT
                m.id,
                m.title,
                m.description,
                m.access_type,
                m.status,
                m.uploaded_at,

                s.id AS subject_id,
                s.name AS subject_name,

                NULL AS category_id,
                NULL AS category_name

            FROM study_materials m

            LEFT JOIN subjects s
                ON s.id = m.subject_id

            WHERE m.status = 'Active'

            ORDER BY
                m.uploaded_at DESC,
                m.id DESC

            LIMIT 6
        ";
    }


    $materialStatement = $conn->prepare(
        $materialSql
    );

    $materialStatement->execute();

    $homepageMaterials =
        $materialStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $exception) {

    error_log(
        'ExamSphere homepage materials query failed: ' .
        $exception->getMessage()
    );

    $homepageMaterials = [];
}

?>


<section
    class="landing-section homepage-materials-section"
    id="materials"
>

    <div class="container">

        <div class="homepage-materials-heading reveal">

            <div>

                <span class="homepage-materials-kicker">

                    <i class="fa-solid fa-book-open"></i>

                    STUDY MATERIALS

                </span>


                <h2>
                    Learn beyond the exam
                    <em>with the right resources.</em>
                </h2>


                <p>
                    Explore recently published study materials.
                    Public resources are available to students,
                    while protected resources require an active subscription.
                </p>

            </div>


            <a
                href="auth/login.php"
                class="homepage-materials-view-all"
            >

                Browse materials

                <i class="fa-solid fa-arrow-right"></i>

            </a>

        </div>


        <?php if (!empty($homepageMaterials)): ?>

            <div class="homepage-materials-grid">

                <?php foreach (
                    $homepageMaterials
                    as $index => $material
                ): ?>

                    <?php

                    $materialId =
                        (int) $material['id'];


                    $title =
                        trim(
                            (string) $material['title']
                        );


                    $description =
                        trim(
                            (string) (
                                $material['description'] ?? ''
                            )
                        );


                    $accessType =
                        trim(
                            (string) (
                                $material['access_type'] ?? 'Public'
                            )
                        );


                    $subjectName =
                        trim(
                            (string) (
                                $material['subject_name'] ?? ''
                            )
                        );


                    $categoryName =
                        trim(
                            (string) (
                                $material['category_name'] ?? ''
                            )
                        );


                    $uploadedAt =
                        $material['uploaded_at'] ?? null;


                    $isPremium =
                        $accessType === 'Subscription Only';


                    if ($description === '') {

                        $description =
                            'Preparation resource published on ExamSphere.';
                    }


                    $formattedDate =
                        'Recently added';


                    if (!empty($uploadedAt)) {

                        try {

                            $uploadedDate =
                                new DateTimeImmutable(
                                    $uploadedAt
                                );

                            $formattedDate =
                                $uploadedDate->format(
                                    'd M Y'
                                );

                        } catch (Throwable $exception) {

                            $formattedDate =
                                'Recently added';
                        }
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
                        class="homepage-material-card reveal"
                    >

                        <div
                            class="homepage-material-top"
                        >

                            <span
                                class="homepage-material-number"
                            >

                                <?= $number ?>

                            </span>


                            <?php if ($isPremium): ?>

                                <span
                                    class="homepage-material-access premium"
                                >

                                    <i
                                        class="fa-solid fa-crown"
                                    ></i>

                                    Premium

                                </span>

                            <?php else: ?>

                                <span
                                    class="homepage-material-access public"
                                >

                                    <i
                                        class="fa-solid fa-lock-open"
                                    ></i>

                                    Public

                                </span>

                            <?php endif; ?>

                        </div>


                        <div
                            class="homepage-material-icon"
                        >

                            <?php if ($isPremium): ?>

                                <i
                                    class="fa-solid fa-file-shield"
                                ></i>

                            <?php else: ?>

                                <i
                                    class="fa-solid fa-file-lines"
                                ></i>

                            <?php endif; ?>

                        </div>


                        <div
                            class="homepage-material-content"
                        >

                            <?php if ($categoryName !== ''): ?>

                                <span
                                    class="homepage-material-category"
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

                                <div
                                    class="homepage-material-subject"
                                >

                                    <i
                                        class="fa-solid fa-book-open"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $subjectName,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                            <?php else: ?>

                                <div
                                    class="homepage-material-subject"
                                >

                                    <i
                                        class="fa-solid fa-folder-open"
                                    ></i>

                                    General Resource

                                </div>

                            <?php endif; ?>


                            <p>

                                <?= htmlspecialchars(
                                    $description,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </p>

                        </div>


                        <div
                            class="homepage-material-meta"
                        >

                            <span>

                                <i
                                    class="fa-regular fa-calendar"
                                ></i>

                                <?= htmlspecialchars(
                                    $formattedDate,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>


                            <span>

                                <i
                                    class="fa-solid fa-file-lines"
                                ></i>

                                Study material

                            </span>

                        </div>


                        <a
                            href="auth/login.php?redirect=materials&material_id=<?= $materialId ?>"
                            class="homepage-material-action"
                        >

                            <?= $isPremium
                                ? 'View access'
                                : 'View material'
                            ?>

                            <i
                                class="fa-solid fa-arrow-right"
                            ></i>

                        </a>

                    </article>

                <?php endforeach; ?>

            </div>


            <div
                class="homepage-materials-footer reveal"
            >

                <div>

                    <span>

                        <i
                            class="fa-solid fa-lightbulb"
                        ></i>

                        SMART PREPARATION

                    </span>


                    <strong>

                        Keep your preparation resources
                        organized in one place.

                    </strong>

                </div>


                <a
                    href="auth/login.php"
                    class="landing-btn primary"
                >

                    Open materials

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>


        <?php else: ?>

            <div
                class="homepage-materials-empty reveal"
            >

                <div
                    class="homepage-materials-empty-icon"
                >

                    <i
                        class="fa-solid fa-book-open"
                    ></i>

                </div>


                <h3>
                    No study materials available yet.
                </h3>


                <p>
                    Published and active study materials will
                    automatically appear here.
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>