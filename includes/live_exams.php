<?php

$homepageLiveExams = [];

try {

    /*
     * Check whether the category relationship exists.
     * The homepage can still work without it.
     */
    $categoryColumnCheck = $conn->query("
        SHOW COLUMNS
        FROM subjects
        LIKE 'category_id'
    ");

    $hasCategoryRelation = (bool) $categoryColumnCheck->fetch(
        PDO::FETCH_ASSOC
    );


    /*
     * We only show:
     *
     * Live exams
     * Scheduled / Live / Upcoming / Running
     * Complete question sets
     *
     * Old/completed/cancelled exams are not shown.
     */
    if ($hasCategoryRelation) {

        $liveSql = "
            SELECT
                e.id,
                e.title,
                e.description,
                e.exam_type,
                e.duration_minutes,
                e.required_question_count,
                e.total_marks,
                e.passing_marks,
                e.exam_fee,
                e.subscription_required,
                e.starts_at,
                e.ends_at,
                e.status,

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

            WHERE e.exam_type = 'Live'

                AND e.status IN (
                    'Scheduled',
                    'Live',
                    'Upcoming',
                    'Running'
                )

                AND e.required_question_count > 0

                AND e.starts_at IS NOT NULL

            GROUP BY
                e.id,
                e.title,
                e.description,
                e.exam_type,
                e.duration_minutes,
                e.required_question_count,
                e.total_marks,
                e.passing_marks,
                e.exam_fee,
                e.subscription_required,
                e.starts_at,
                e.ends_at,
                e.status,

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
                e.starts_at ASC,
                e.id ASC

            LIMIT 3
        ";

    } else {

        $liveSql = "
            SELECT
                e.id,
                e.title,
                e.description,
                e.exam_type,
                e.duration_minutes,
                e.required_question_count,
                e.total_marks,
                e.passing_marks,
                e.exam_fee,
                e.subscription_required,
                e.starts_at,
                e.ends_at,
                e.status,

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

            WHERE e.exam_type = 'Live'

                AND e.status IN (
                    'Scheduled',
                    'Live',
                    'Upcoming',
                    'Running'
                )

                AND e.required_question_count > 0

                AND e.starts_at IS NOT NULL

            GROUP BY
                e.id,
                e.title,
                e.description,
                e.exam_type,
                e.duration_minutes,
                e.required_question_count,
                e.total_marks,
                e.passing_marks,
                e.exam_fee,
                e.subscription_required,
                e.starts_at,
                e.ends_at,
                e.status,

                s.id,
                s.name

            HAVING COUNT(
                CASE
                    WHEN q.status = 'Active'
                    THEN eq.question_id
                END
            ) = e.required_question_count

            ORDER BY
                e.starts_at ASC,
                e.id ASC

            LIMIT 3
        ";
    }


    $liveStatement = $conn->prepare(
        $liveSql
    );

    $liveStatement->execute();

    $homepageLiveExams =
        $liveStatement->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
     * Calculate presentation status using the real schedule.
     */
    $now = new DateTimeImmutable(
        'now',
        new DateTimeZone(
            date_default_timezone_get()
        )
    );


    foreach ($homepageLiveExams as &$exam) {

        $start = null;
        $end = null;

        if (!empty($exam['starts_at'])) {

            try {

                $start = new DateTimeImmutable(
                    $exam['starts_at'],
                    $now->getTimezone()
                );

            } catch (Throwable $exception) {

                $start = null;
            }
        }


        if (!empty($exam['ends_at'])) {

            try {

                $end = new DateTimeImmutable(
                    $exam['ends_at'],
                    $now->getTimezone()
                );

            } catch (Throwable $exception) {

                $end = null;
            }
        }


        if (
            $start &&
            $end &&
            $now >= $start &&
            $now <= $end
        ) {

            $exam['display_status'] = 'LIVE';

        } elseif (
            $start &&
            $now < $start
        ) {

            $exam['display_status'] = 'UPCOMING';

        } elseif (
            $end &&
            $now > $end
        ) {

            $exam['display_status'] = 'ENDED';

        } else {

            $exam['display_status'] =
                strtoupper(
                    (string) $exam['status']
                );
        }
    }

    unset($exam);


} catch (Throwable $exception) {

    error_log(
        'ExamSphere homepage live exam query failed: ' .
        $exception->getMessage()
    );

    $homepageLiveExams = [];
}

?>


<section
    class="landing-section dynamic-live-section"
    id="live-exams"
>

    <div class="container">

        <div class="dynamic-live-heading reveal">

            <div>

                <span class="dynamic-live-kicker">

                    <i class="fa-solid fa-tower-broadcast"></i>

                    LIVE EXAMS

                </span>


                <h2>
                    Be ready when
                    <em>your exam goes live.</em>
                </h2>


                <p>
                    See upcoming live examinations with their real
                    schedule, duration, question count and access requirements.
                </p>

            </div>


            <a
                href="auth/login.php"
                class="dynamic-live-view-all"
            >

                View live exams

                <i class="fa-solid fa-arrow-right"></i>

            </a>

        </div>


        <?php if (!empty($homepageLiveExams)): ?>

            <div class="dynamic-live-grid">

                <?php foreach ($homepageLiveExams as $index => $exam): ?>

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

                    $totalMarks =
                        (float) (
                            $exam['total_marks'] ?? 0
                        );

                    $examFee =
                        (float) (
                            $exam['exam_fee'] ?? 0
                        );

                    $requiresSubscription =
                        (int) (
                            $exam['subscription_required'] ?? 0
                        ) === 1;

                    $displayStatus =
                        (string) (
                            $exam['display_status'] ?? 'UPCOMING'
                        );


                    if ($description === '') {

                        $description =
                            'Scheduled live examination on ExamSphere.';
                    }


                    $formattedDate =
                        'Schedule pending';

                    $formattedTime =
                        '';

                    if (!empty($exam['starts_at'])) {

                        try {

                            $startDate = new DateTimeImmutable(
                                $exam['starts_at']
                            );

                            $formattedDate =
                                $startDate->format(
                                    'd M Y'
                                );

                            $formattedTime =
                                $startDate->format(
                                    'h:i A'
                                );

                        } catch (Throwable $exception) {

                            $formattedDate =
                                'Schedule unavailable';
                        }
                    }


                    $endTime =
                        '';

                    if (!empty($exam['ends_at'])) {

                        try {

                            $endDate = new DateTimeImmutable(
                                $exam['ends_at']
                            );

                            $endTime =
                                $endDate->format(
                                    'h:i A'
                                );

                        } catch (Throwable $exception) {

                            $endTime = '';
                        }
                    }


                    ?>

                    <article
                        class="dynamic-live-card reveal"
                    >

                        <div
                            class="dynamic-live-card-top"
                        >

                            <span
                                class="dynamic-live-index"
                            >

                                <?= str_pad(
                                    (string) ($index + 1),
                                    2,
                                    '0',
                                    STR_PAD_LEFT
                                ) ?>

                            </span>


                            <?php if (
                                $displayStatus === 'LIVE'
                            ): ?>

                                <span
                                    class="dynamic-live-status live"
                                >

                                    <i
                                        class="fa-solid fa-circle"
                                    ></i>

                                    LIVE NOW

                                </span>

                            <?php else: ?>

                                <span
                                    class="dynamic-live-status upcoming"
                                >

                                    <i
                                        class="fa-regular fa-clock"
                                    ></i>

                                    UPCOMING

                                </span>

                            <?php endif; ?>

                        </div>


                        <div
                            class="dynamic-live-icon"
                        >

                            <i
                                class="fa-solid fa-trophy"
                            ></i>

                        </div>


                        <div
                            class="dynamic-live-content"
                        >

                            <?php if ($categoryName !== ''): ?>

                                <span
                                    class="dynamic-live-category"
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
                                    class="dynamic-live-subject"
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
                                class="dynamic-live-description"
                            >

                                <?= htmlspecialchars(
                                    $description,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </p>

                        </div>


                        <div
                            class="dynamic-live-schedule"
                        >

                            <div>

                                <i
                                    class="fa-regular fa-calendar-days"
                                ></i>

                                <span>

                                    <small>
                                        DATE
                                    </small>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $formattedDate,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                </span>

                            </div>


                            <div>

                                <i
                                    class="fa-regular fa-clock"
                                ></i>

                                <span>

                                    <small>
                                        START
                                    </small>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $formattedTime,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </strong>

                                </span>

                            </div>

                        </div>


                        <div
                            class="dynamic-live-meta"
                        >

                            <span>

                                <i
                                    class="fa-regular fa-hourglass-half"
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
                            class="dynamic-live-access"
                        >

                            <?php if (
                                $requiresSubscription
                            ): ?>

                                <span>

                                    <i
                                        class="fa-solid fa-crown"
                                    ></i>

                                    Subscription required

                                </span>

                            <?php elseif ($examFee > 0): ?>

                                <span>

                                    <i
                                        class="fa-solid fa-credit-card"
                                    ></i>

                                    ₹<?= number_format(
                                        $examFee,
                                        2
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span>

                                    <i
                                        class="fa-solid fa-unlock"
                                    ></i>

                                    Free access

                                </span>

                            <?php endif; ?>


                            <?php if ($endTime !== ''): ?>

                                <small>

                                    Ends at
                                    <?= htmlspecialchars(
                                        $endTime,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </small>

                            <?php endif; ?>

                        </div>


                        <a
                            href="auth/login.php?redirect=live_exam&exam_id=<?= $examId ?>"
                            class="dynamic-live-action"
                        >

                            Login to view access

                            <i
                                class="fa-solid fa-arrow-right"
                            ></i>

                        </a>

                    </article>

                <?php endforeach; ?>

            </div>


            <div
                class="dynamic-live-footer reveal"
            >

                <div>

                    <span>

                        <i
                            class="fa-solid fa-bell"
                        ></i>

                        STAY READY

                    </span>

                    <strong>
                        Live exam schedules are controlled from the Admin panel.
                    </strong>

                </div>


                <a
                    href="auth/login.php"
                    class="landing-btn primary"
                >

                    Check live exams

                    <i
                        class="fa-solid fa-arrow-right"
                    ></i>

                </a>

            </div>


        <?php else: ?>

            <div
                class="dynamic-live-empty reveal"
            >

                <div
                    class="dynamic-live-empty-icon"
                >

                    <i
                        class="fa-solid fa-calendar-xmark"
                    ></i>

                </div>


                <h3>
                    No live exams are scheduled.
                </h3>


                <p>
                    Upcoming live examinations will appear here automatically
                    after they are scheduled and their complete question set is ready.
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>