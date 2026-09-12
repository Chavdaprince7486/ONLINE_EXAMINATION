<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/exam_validation.php';
require_once '../config/auth.php';

require_login('student');

$studentId =
    (int)(
        $_SESSION[
            'user_id'
        ] ?? 0
    );

function practice_e(
    mixed $value
): string {

    return htmlspecialchars(
        (string)(
            $value ?? ''
        ),
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function practice_num(
    mixed $value
): string {

    $number =
        (float)$value;

    if (
        floor($number) ===
        $number
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

$search =
    trim(
        (string)(
            $_GET[
                'search'
            ] ?? ''
        )
    );

$subjectId =
    filter_input(
        INPUT_GET,
        'subject_id',
        FILTER_VALIDATE_INT
    );

$subjectId =
    (
        $subjectId !== false
        &&
        $subjectId !== null
        &&
        $subjectId > 0
    )
        ? $subjectId
        : null;

$subjects =
    [];

$exams =
    [];

$latestResults =
    [];

$activeAttempts =
    [];

$hasSubscription =
    false;


/*
|--------------------------------------------------------------------------
| SUBJECTS
|--------------------------------------------------------------------------
*/

try {

    $subjectStmt =
        $conn->query(
            "
            SELECT
                id,
                name,
                code

            FROM subjects

            WHERE status = 'Active'

            ORDER BY
                name ASC,
                id ASC
            "
        );

    $subjects =
        $subjectStmt
            ->fetchAll(
                PDO::FETCH_ASSOC
            );

} catch (
    Throwable $e
) {

    error_log(
        'Practice subjects load failed: ' .
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| EXAMS
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT

            e.id,
            e.subject_id,

            e.title,
            e.description,

            e.exam_type,

            e.duration_minutes,

            e.required_question_count,

            e.total_marks,
            e.passing_marks,

            e.negative_marking,

            e.exam_fee,
            e.subscription_required,

            e.status,

            e.created_at,
            e.updated_at,

            s.name AS subject_name,
            s.code AS subject_code

        FROM exams e

        INNER JOIN subjects s

            ON s.id =
               e.subject_id

           AND s.status =
               'Active'

        WHERE

            e.exam_type =
                'Practice'

            AND e.status =
                'Active'

            AND e.required_question_count >
                0

            AND e.total_marks >
                0
    ";

    $params =
        [];

    if (
        $search !== ''
    ) {

        $sql .= "

            AND (

                e.title LIKE ?

                OR e.description LIKE ?

                OR s.name LIKE ?

                OR s.code LIKE ?

            )

        ";

        $like =
            '%' .
            $search .
            '%';

        $params = [

            $like,
            $like,
            $like,
            $like

        ];
    }

    if (
        $subjectId !== null
    ) {

        $sql .=
            ' AND e.subject_id = ? ';

        $params[] =
            $subjectId;
    }

    $sql .= "

        ORDER BY

            e.updated_at DESC,

            e.id DESC

    ";

    $stmt =
        $conn->prepare(
            $sql
        );

    $stmt->execute(
        $params
    );

    $candidateExams =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    foreach (
        $candidateExams
        as $exam
    ) {

        $examId =
            (int)$exam['id'];

        $validation =
            validate_exam_from_database(
                $conn,
                $examId
            );

        if (
            !(
                $validation[
                    'valid'
                ]
                ??
                false
            )
        ) {

            continue;
        }

        $validationData =
            $validation[
                'validation'
            ]
            ??
            [];

        $requiredQuestionCount =
            (int)(
                $validationData[
                    'required_question_count'
                ]
                ??
                $exam[
                    'required_question_count'
                ]
                ??
                0
            );

        $actualQuestionCount =
            (int)(
                $validationData[
                    'question_count'
                ]
                ??
                0
            );

        if (
            $requiredQuestionCount < 1
            ||
            $actualQuestionCount !==
            $requiredQuestionCount
        ) {

            continue;
        }

        if (
            abs(
                (
                    (float)(
                        $validationData[
                            'total_marks'
                        ]
                        ??
                        0
                    )
                )
                -
                (
                    (float)
                    $exam[
                        'total_marks'
                    ]
                )
            )
            >
            0.000001
        ) {

            continue;
        }

        $exam[
            'required_question_count'
        ] =
            $requiredQuestionCount;

        $exam[
            'active_question_count'
        ] =
            $actualQuestionCount;

        $exam[
            'active_question_marks'
        ] =
            (float)(
                $validationData[
                    'actual_marks'
                ]
                ??
                0
            );

        $exam[
            'validation_mode'
        ] =
            (string)(
                $validationData[
                    'mode'
                ]
                ??
                'dynamic'
            );

        $exam[
            'marks_per_question'
        ] =
            $validationData[
                'marks_per_question'
            ]
            ??
            null;

        $exams[] =
            $exam;
    }

} catch (
    Throwable $e
) {

    error_log(
        'Practice exams query failed: ' .
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| EXAM IDS
|--------------------------------------------------------------------------
*/

$examIds =
    array_values(
        array_unique(
            array_map(
                static fn(
                    array $exam
                ): int =>
                    (int)
                    $exam['id'],

                $exams
            )
        )
    );


/*
|--------------------------------------------------------------------------
| RESULTS + ATTEMPTS
|--------------------------------------------------------------------------
*/

if (
    $examIds
) {

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count(
                    $examIds
                ),
                '?'
            )
        );


    try {

        $stmt =
            $conn->prepare(
                "
                SELECT

                    r.id,
                    r.exam_id,

                    r.percentage,
                    r.grade,

                    r.result_status,

                    r.obtained_marks,
                    r.total_marks,

                    r.created_at

                FROM results r

                INNER JOIN (

                    SELECT

                        exam_id,

                        MAX(id)
                            AS latest_result_id

                    FROM results

                    WHERE

                        student_id = ?

                        AND exam_id IN (
                            $placeholders
                        )

                    GROUP BY
                        exam_id

                ) latest

                    ON latest.latest_result_id =
                       r.id

                WHERE

                    r.student_id = ?

                "
            );

        $stmt->execute(
            array_merge(
                [
                    $studentId
                ],
                $examIds,
                [
                    $studentId
                ]
            )
        );

        foreach (
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            as $row
        ) {

            $latestResults[
                (int)
                $row['exam_id']
            ] =
                $row;
        }

    } catch (
        Throwable $e
    ) {

        error_log(
            'Practice results lookup failed: ' .
            $e->getMessage()
        );
    }


    try {

        $stmt =
            $conn->prepare(
                "
                SELECT

                    ea.id,
                    ea.exam_id,

                    ea.started_at,
                    ea.server_deadline,

                    ea.status

                FROM exam_attempts ea

                INNER JOIN (

                    SELECT

                        exam_id,

                        MAX(id)
                            AS latest_attempt_id

                    FROM exam_attempts

                    WHERE

                        student_id = ?

                        AND exam_id IN (
                            $placeholders
                        )

                        AND status =
                            'Started'

                    GROUP BY
                        exam_id

                ) latest

                    ON latest.latest_attempt_id =
                       ea.id

                WHERE

                    ea.student_id = ?

                    AND ea.status =
                        'Started'

                "
            );

        $stmt->execute(
            array_merge(
                [
                    $studentId
                ],
                $examIds,
                [
                    $studentId
                ]
            )
        );

        foreach (
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            as $row
        ) {

            $activeAttempts[
                (int)
                $row['exam_id']
            ] =
                $row;
        }

    } catch (
        Throwable $e
    ) {

        error_log(
            'Practice active attempts lookup failed: ' .
            $e->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| SUBSCRIPTION
|--------------------------------------------------------------------------
*/

try {

    $hasSubscription =
        has_active_subscription(
            $conn,
            $studentId
        );

} catch (
    Throwable $e
) {

    error_log(
        'Practice subscription lookup failed: ' .
        $e->getMessage()
    );

    $hasSubscription =
        false;
}


/*
|--------------------------------------------------------------------------
| ACCESS
|--------------------------------------------------------------------------
*/

$freeExamCount =
    0;

$premiumExamCount =
    0;

$attemptedExamCount =
    0;

$accessibleExamCount =
    0;

foreach (
    $exams
    as &$exam
) {

    $examId =
        (int)
        $exam['id'];

    $latestResult =
        $latestResults[
            $examId
        ]
        ??
        null;

    $activeAttempt =
        $activeAttempts[
            $examId
        ]
        ??
        null;

    $requiresSubscription =
        (
            (int)(
                $exam[
                    'subscription_required'
                ]
            )
            ===
            1
        );

    $hasAccess =
        (
            !$requiresSubscription
            ||
            $hasSubscription
        );

    $exam[
        'latest_result'
    ] =
        $latestResult;

    $exam[
        'active_attempt'
    ] =
        $activeAttempt;

    $exam[
        'exam_access'
    ] =
        $hasAccess;

    $exam[
        'access_message'
    ] =
        $hasAccess
            ? ''
            : 'An active subscription is required for this practice exam.';

    if (
        $requiresSubscription
    ) {

        $premiumExamCount++;

    } else {

        $freeExamCount++;

    }

    if (
        $latestResult !== null
    ) {

        $attemptedExamCount++;
    }

    if (
        $hasAccess
    ) {

        $accessibleExamCount++;
    }
}

unset(
    $exam
);

$filterActive =
    $search !== ''
    ||
    $subjectId !== null;

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#f5f5dc"
    >

    <meta
        name="csrf-token"
        content="<?= practice_e(
            csrf_token()
        ) ?>"
    >

    <title>
        Practice Exams | ExamSphere
    </title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/student-nav.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/practice-exams-complete.css"
    >

</head>

<body class="practice-page">

<?php include 'includes/navbar.php'; ?>

<main class="practice-container">

    <section class="practice-hero">

        <div>

            <span class="practice-kicker">

                <i
                    class="
                        fa-solid
                        fa-pen-to-square
                    "
                ></i>

                STUDENT PRACTICE LIBRARY

            </span>

            <h1>

                Practice smarter.

                <br>

                <em>
                    Perform stronger.
                </em>

            </h1>

            <p>

                Only complete, active
                practice examinations are
                displayed here.

                When an administrator or
                teacher publishes a complete
                practice exam, it appears
                automatically.

            </p>

        </div>

        <div class="practice-hero-card">

            <div class="practice-hero-icon">

                <i
                    class="
                        fa-solid
                        fa-bolt
                    "
                ></i>

            </div>

            <div>

                <strong>
                    Fully dynamic
                </strong>

                <span>
                    Live database-driven exam library
                </span>

            </div>

        </div>

    </section>

    <section class="practice-stats">

        <article>

            <i
                class="
                    fa-solid
                    fa-circle-check
                "
            ></i>

            <div>

                <span>
                    Ready exams
                </span>

                <strong>
                    <?= count(
                        $exams
                    ) ?>
                </strong>

            </div>

        </article>

        <article>

            <i
                class="
                    fa-solid
                    fa-unlock
                "
            ></i>

            <div>

                <span>
                    Accessible now
                </span>

                <strong>
                    <?= $accessibleExamCount ?>
                </strong>

            </div>

        </article>

        <article>

            <i
                class="
                    fa-solid
                    fa-crown
                "
            ></i>

            <div>

                <span>
                    Premium practice
                </span>

                <strong>
                    <?= $premiumExamCount ?>
                </strong>

            </div>

        </article>

        <article>

            <i
                class="
                    fa-solid
                    fa-chart-column
                "
            ></i>

            <div>

                <span>
                    Attempted
                </span>

                <strong>
                    <?= $attemptedExamCount ?>
                </strong>

            </div>

        </article>

    </section>

    <section class="practice-filter-panel">

        <form
            method="GET"
            class="practice-filter-form"
            id="practiceFilterForm"
        >

            <div class="practice-input">

                <i
                    class="
                        fa-solid
                        fa-magnifying-glass
                    "
                ></i>

                <input
                    type="search"
                    name="search"
                    value="<?= practice_e(
                        $search
                    ) ?>"
                    placeholder="Search exam or subject..."
                    autocomplete="off"
                >

            </div>

            <div
                class="
                    practice-input
                    practice-select
                "
            >

                <i
                    class="
                        fa-solid
                        fa-book-open
                    "
                ></i>

                <select
                    name="subject_id"
                >

                    <option value="">
                        All subjects
                    </option>

                    <?php foreach (
                        $subjects
                        as $subject
                    ): ?>

                        <option
                            value="<?= (int)$subject['id'] ?>"
                            <?= $subjectId ===
                                (int)$subject['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= practice_e(
                                $subject['name']
                            ) ?>

                            <?php if (
                                !empty(
                                    $subject['code']
                                )
                            ): ?>

                                (
                                <?= practice_e(
                                    $subject['code']
                                ) ?>
                                )

                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <button
                type="submit"
                class="
                    practice-btn
                    practice-filter-btn
                "
            >

                <i
                    class="
                        fa-solid
                        fa-filter
                    "
                ></i>

                Filter

            </button>

            <?php if (
                $filterActive
            ): ?>

                <a
                    href="practice_exams.php"
                    class="
                        practice-clear-btn
                    "
                >
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </section>

    <section class="practice-section-head">

        <div>

            <span>
                READY EXAMINATIONS
            </span>

            <h2>
                Explore practice exams
            </h2>

        </div>

        <div class="practice-count">

            <strong
                id="practiceResultCount"
            >
                <?= count(
                    $exams
                ) ?>
            </strong>

            <span>
                visible
            </span>

        </div>

    </section>

    <?php if (
        $exams
    ): ?>

        <section
            class="practice-grid"
            id="practiceExamGrid"
        >

            <?php foreach (
                $exams
                as $index =>
                    $exam
            ): ?>

                <?php

                $examId =
                    (int)
                    $exam['id'];

                $requiresSubscription =
                    (
                        (int)(
                            $exam[
                                'subscription_required'
                            ]
                        )
                        ===
                        1
                    );

                $hasAccess =
                    (bool)
                    $exam[
                        'exam_access'
                    ];

                $latestResult =
                    $exam[
                        'latest_result'
                    ];

                $activeAttempt =
                    $exam[
                        'active_attempt'
                    ];

                $number =
                    str_pad(
                        (string)(
                            $index + 1
                        ),
                        2,
                        '0',
                        STR_PAD_LEFT
                    );

                $description =
                    trim(
                        (string)(
                            $exam[
                                'description'
                            ]
                            ??
                            ''
                        )
                    );

                if (
                    $description === ''
                ) {

                    $description =
                        'Complete this practice examination to measure and improve your preparation.';
                }

                ?>

                <article
                    class="
                        practice-card
                        <?= !$hasAccess
                            ? 'is-locked'
                            : ''
                        ?>"
                    data-search="<?= practice_e(
                        strtolower(
                            (string)
                            $exam[
                                'title'
                            ]
                            . ' ' .
                            (string)
                            $exam[
                                'subject_name'
                            ]
                            . ' ' .
                            (string)
                            $exam[
                                'subject_code'
                            ]
                        )
                    ?>"
                >

                    <div
                        class="
                            practice-card-top
                        "
                    >

                        <span>
                            <?= practice_e(
                                $number
                            ) ?>
                        </span>

                        <div
                            class="
                                practice-badges
                            "
                        >

                            <?php if (
                                $activeAttempt
                            ): ?>

                                <b
                                    class="
                                        badge
                                        progress
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-play
                                        "
                                    ></i>

                                    In progress

                                </b>

                            <?php elseif (
                                $latestResult
                            ): ?>

                                <b
                                    class="
                                        badge
                                        attempted
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-check
                                        "
                                    ></i>

                                    Attempted

                                </b>

                            <?php else: ?>

                                <b
                                    class="
                                        badge
                                        ready
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-circle-check
                                        "
                                    ></i>

                                    Ready

                                </b>

                            <?php endif; ?>

                            <?php if (
                                $requiresSubscription
                            ): ?>

                                <b
                                    class="
                                        badge
                                        premium
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-crown
                                        "
                                    ></i>

                                    Premium

                                </b>

                            <?php else: ?>

                                <b
                                    class="
                                        badge
                                        free
                                    "
                                >

                                    Free

                                </b>

                            <?php endif; ?>

                        </div>

                    </div>

                    <div
                        class="
                            practice-card-icon
                        "
                    >

                        <i
                            class="
                                fa-solid
                                <?= $requiresSubscription
                                    ? 'fa-crown'
                                    : 'fa-file-pen'
                                ?>
                            "
                        ></i>

                    </div>

                    <div
                        class="
                            practice-subject
                        "
                    >

                        <?= practice_e(
                            $exam[
                                'subject_name'
                            ]
                        ) ?>

                        <?php if (
                            !empty(
                                $exam[
                                    'subject_code'
                                ]
                            )
                        ): ?>

                            <small>

                                •

                                <?= practice_e(
                                    $exam[
                                        'subject_code'
                                    ]
                                ) ?>

                            </small>

                        <?php endif; ?>

                    </div>

                    <h3>

                        <?= practice_e(
                            $exam[
                                'title'
                            ]
                        ) ?>

                    </h3>

                    <p
                        class="
                            practice-description
                        "
                    >

                        <?= practice_e(
                            $description
                        ) ?>

                    </p>

                    <div
                        class="
                            practice-meta
                        "
                    >

                        <span>

                            <i
                                class="
                                    fa-regular
                                    fa-clock
                                "
                            ></i>

                            <?= (int)
                                $exam[
                                    'duration_minutes'
                                ] ?>

                            min

                        </span>

                        <span>

                            <i
                                class="
                                    fa-solid
                                    fa-list-check
                                "
                            ></i>

                            <?= (int)
                                $exam[
                                    'required_question_count'
                                ] ?>

                            questions

                        </span>

                        <span>

                            <i
                                class="
                                    fa-solid
                                    fa-star
                                "
                            ></i>

                            <?= practice_num(
                                $exam[
                                    'total_marks'
                                ]
                            ) ?>

                            marks

                        </span>

                        <span>

                            <i
                                class="
                                    fa-solid
                                    fa-bullseye
                                "
                            ></i>

                            Pass

                            <?= practice_num(
                                $exam[
                                    'passing_marks'
                                ]
                            ) ?>

                        </span>

                    </div>

                    <div
                        class="
                            practice-rule
                        "
                    >

                        <?php if (
                            (int)(
                                $exam[
                                    'negative_marking'
                                ]
                            )
                            ===
                            1
                        ): ?>

                            <span
                                class="
                                    negative
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-circle-minus
                                    "
                                ></i>

                                Negative marking

                            </span>

                        <?php else: ?>

                            <span
                                class="
                                    positive
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-circle-check
                                    "
                                ></i>

                                No negative marking

                            </span>

                        <?php endif; ?>

                        <span>

                            <i
                                class="
                                    fa-solid
                                    fa-check-double
                                "
                            ></i>

                            <?= (int)
                                $exam[
                                    'required_question_count'
                                ] ?>

                            /

                            <?= (int)
                                $exam[
                                    'required_question_count'
                                ] ?>

                            ready

                        </span>

                    </div>

                    <?php if (
                        !$hasAccess
                    ): ?>

                        <div
                            class="
                                practice-lock-message
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-lock
                                "
                            ></i>

                            <span>

                                <?= practice_e(
                                    $exam[
                                        'access_message'
                                    ]
                                ) ?>

                            </span>

                        </div>

                    <?php endif; ?>

                    <div
                        class="
                            practice-action
                        "
                    >

                        <?php if (
                            !$hasAccess
                        ): ?>

                            <a
                                href="subscriptions.php"
                                class="
                                    practice-btn
                                    full
                                    premium-btn
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-crown
                                    "
                                ></i>

                                Get subscription

                                <i
                                    class="
                                        fa-solid
                                        fa-arrow-right
                                    "
                                ></i>

                            </a>

                        <?php elseif (
                            $activeAttempt
                        ): ?>

                            <a
                                href="
                                    start_exam.php?id=
                                    <?= $examId ?>
                                "
                                class="
                                    practice-btn
                                    full
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-play
                                    "
                                ></i>

                                Resume practice

                                <i
                                    class="
                                        fa-solid
                                        fa-arrow-right
                                    "
                                ></i>

                            </a>

                        <?php elseif (
                            $latestResult
                        ): ?>

                            <a
                                href="
                                    start_exam.php?id=
                                    <?= $examId ?>
                                "
                                class="
                                    practice-btn
                                    full
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-rotate
                                    "
                                ></i>

                                Practice again

                                <i
                                    class="
                                        fa-solid
                                        fa-arrow-right
                                    "
                                ></i>

                            </a>

                            <a
                                href="
                                    result.php?id=
                                    <?= (int)(
                                        $latestResult[
                                            'id'
                                        ]
                                    ) ?>
                                "
                                class="
                                    practice-result-link
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-chart-column
                                    "
                                ></i>

                                Latest result:

                                <?= practice_num(
                                    $latestResult[
                                        'percentage'
                                    ]
                                ) ?>%

                            </a>

                        <?php else: ?>

                            <a
                                href="
                                    start_exam.php?id=
                                    <?= $examId ?>
                                "
                                class="
                                    practice-btn
                                    full
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-rocket
                                    "
                                ></i>

                                Start practice

                                <i
                                    class="
                                        fa-solid
                                        fa-arrow-right
                                    "
                                ></i>

                            </a>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        </section>

        <section
            class="
                practice-info-grid
            "
        >

            <div>

                <i
                    class="
                        fa-solid
                        fa-shield-halved
                    "
                ></i>

                <span>

                    <strong>
                        Verified exams only
                    </strong>

                    <small>
                        Incomplete or invalid
                        examinations stay hidden.
                    </small>

                </span>

            </div>

            <div>

                <i
                    class="
                        fa-solid
                        fa-database
                    "
                ></i>

                <span>

                    <strong>
                        Database driven
                    </strong>

                    <small>
                        Published exams appear
                        automatically without
                        editing this page.
                    </small>

                </span>

            </div>

            <div>

                <i
                    class="
                        fa-solid
                        fa-arrows-rotate
                    "
                ></i>

                <span>

                    <strong>
                        Always current
                    </strong>

                    <small>
                        Status and exam availability
                        are checked on every page load.
                    </small>

                </span>

            </div>

        </section>

    <?php else: ?>

        <section class="practice-empty">

            <div
                class="
                    practice-empty-icon
                "
            >

                <i
                    class="
                        fa-solid
                        fa-book-open
                    "
                ></i>

            </div>

            <span>

                <?= $filterActive
                    ? 'NO MATCHING EXAMS'
                    : 'NO PUBLISHED PRACTICE EXAMS'
                ?>

            </span>

            <h2>

                <?= $filterActive

                    ? 'We could not find a matching practice exam.'

                    : 'No practice exams are available right now.'

                ?>

            </h2>

            <p>

                <?= $filterActive

                    ? 'Try another search or clear the current filters.'

                    : 'As soon as an administrator or teacher publishes a complete active practice exam, it will appear automatically on this page.'

                ?>

            </p>

            <?php if (
                $filterActive
            ): ?>

                <a
                    href="practice_exams.php"
                    class="
                        practice-btn
                    "
                >

                    Show all exams

                </a>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</main>

<script
    src="assets/js/practice-exams.js"
></script>

</body>

</html>