<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';
require_once '../config/exam_validation.php';


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

require_once '../config/auth.php';

require_login('student');


$studentId =
    (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function practice_escape(
    mixed $value
): string {

    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function practice_number(
    float|int|string|null $value
): string {

    $number =
        (float) $value;


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


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search =
    trim(
        (string) (
            $_GET['search'] ?? ''
        )
    );


$subjectId =
    filter_input(
        INPUT_GET,
        'subject_id',
        FILTER_VALIDATE_INT
    );


if (
    $subjectId === false ||
    $subjectId === null ||
    $subjectId <= 0
) {

    $subjectId =
        null;
}


/*
|--------------------------------------------------------------------------
| LOAD SUBJECTS
|--------------------------------------------------------------------------
*/

$subjects = [];


try {

    $subjectStatement =
        $conn->query(
            "
            SELECT

                id,
                name,
                code

            FROM subjects

            WHERE

                status = 'Active'

            ORDER BY

                name ASC,
                id ASC
            "
        );


    $subjects =
        $subjectStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (
    Throwable $exception
) {

    error_log(
        'Practice subjects load failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| LOAD CANDIDATE EXAMS
|--------------------------------------------------------------------------
|
| Do NOT decide readiness in SQL alone.
|
| The central ExamSphere validation engine is the
| single source of truth.
|--------------------------------------------------------------------------
*/

$exams = [];


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

            ON s.id = e.subject_id

            AND s.status = 'Active'

        WHERE

            e.exam_type = 'Practice'

            AND e.status = 'Active'

            AND e.required_question_count > 0

            AND e.total_marks > 0
    ";


    $parameters = [];


    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

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


        $searchValue =
            '%' .
            $search .
            '%';


        $parameters[] =
            $searchValue;


        $parameters[] =
            $searchValue;


        $parameters[] =
            $searchValue;


        $parameters[] =
            $searchValue;
    }


    /*
    |--------------------------------------------------------------------------
    | SUBJECT FILTER
    |--------------------------------------------------------------------------
    */

    if (
        $subjectId !== null
    ) {

        $sql .= "
            AND e.subject_id = ?
        ";


        $parameters[] =
            $subjectId;
    }


    $sql .= "
        ORDER BY

            e.updated_at DESC,
            e.id DESC
    ";


    $statement =
        $conn->prepare(
            $sql
        );


    $statement->execute(
        $parameters
    );


    $candidateExams =
        $statement->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | CENTRAL READINESS VALIDATION
    |--------------------------------------------------------------------------
    */

    foreach (
        $candidateExams as $exam
    ) {

        $examId =
            (int) $exam['id'];


        $validation =
            validate_exam_from_database(
                $conn,
                $examId
            );


        if (
            !$validation['valid']
        ) {

            /*
            |--------------------------------------------------------------------------
            | Invalid exam stays hidden from students.
            |--------------------------------------------------------------------------
            */

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Ensure database exam and validated config agree.
        |--------------------------------------------------------------------------
        */

        $validationData =
            $validation['validation'];


        if (
            (int) $validationData[
                'required_question_count'
            ]
            !==
            (int) $exam[
                'required_question_count'
            ]
        ) {

            continue;
        }


        if (
            abs(
                (float) $validationData[
                    'total_marks'
                ]
                -
                (float) $exam[
                    'total_marks'
                ]
            ) > 0.000001
        ) {

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Add validated values
        |--------------------------------------------------------------------------
        */

        $exam[
            'active_question_count'
        ] =
            (int) $validationData[
                'question_count'
            ];


        $exam[
            'active_question_marks'
        ] =
            (float) $validationData[
                'actual_marks'
            ];


        $exam[
            'validation_mode'
        ] =
            (string) $validationData[
                'mode'
            ];


        $exam[
            'marks_per_question'
        ] =
            $validationData[
                'marks_per_question'
            ];


        $exam[
            'is_ready'
        ] =
            true;


        $exams[] =
            $exam;
    }

} catch (
    Throwable $exception
) {

    error_log(
        'Practice exams query failed: ' .
        $exception->getMessage()
    );

    $exams = [];
}


/*
|--------------------------------------------------------------------------
| EXAM IDS
|--------------------------------------------------------------------------
*/

$examIds = [];


foreach (
    $exams as $exam
) {

    $examIds[] =
        (int) $exam['id'];
}


$examIds =
    array_values(
        array_unique(
            $examIds
        )
    );


/*
|--------------------------------------------------------------------------
| LATEST RESULTS
|--------------------------------------------------------------------------
*/

$latestResults = [];


if (
    !empty($examIds)
) {

    try {

        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($examIds),
                    '?'
                )
            );


        $resultStatement =
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
                        MAX(id) AS latest_result_id

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


        $resultParameters =
            array_merge(
                [
                    $studentId
                ],
                $examIds,
                [
                    $studentId
                ]
            );


        $resultStatement->execute(
            $resultParameters
        );


        foreach (
            $resultStatement->fetchAll(
                PDO::FETCH_ASSOC
            ) as $result
        ) {

            $latestResults[
                (int) $result['exam_id']
            ] =
                $result;
        }

    } catch (
        Throwable $exception
    ) {

        error_log(
            'Practice results lookup failed: ' .
            $exception->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| ACTIVE ATTEMPTS
|--------------------------------------------------------------------------
*/

$activeAttempts = [];


if (
    !empty($examIds)
) {

    try {

        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($examIds),
                    '?'
                )
            );


        $attemptStatement =
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
                        MAX(id) AS latest_attempt_id

                    FROM exam_attempts

                    WHERE

                        student_id = ?

                        AND exam_id IN (
                            $placeholders
                        )

                        AND status = 'Started'

                    GROUP BY
                        exam_id

                ) latest

                    ON latest.latest_attempt_id =
                       ea.id

                WHERE

                    ea.student_id = ?

                    AND ea.status = 'Started'
                "
            );


        $attemptParameters =
            array_merge(
                [
                    $studentId
                ],
                $examIds,
                [
                    $studentId
                ]
            );


        $attemptStatement->execute(
            $attemptParameters
        );


        foreach (
            $attemptStatement->fetchAll(
                PDO::FETCH_ASSOC
            ) as $attempt
        ) {

            $activeAttempts[
                (int) $attempt['exam_id']
            ] =
                $attempt;
        }

    } catch (
        Throwable $exception
    ) {

        error_log(
            'Practice active attempts lookup failed: ' .
            $exception->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| SUBSCRIPTION
|--------------------------------------------------------------------------
*/

$hasSubscription =
    false;


try {

    $hasSubscription =
        has_active_subscription(
            $conn,
            $studentId
        );

} catch (
    Throwable $exception
) {

    error_log(
        'Practice subscription lookup failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| STUDENT ACCESS STATE
|--------------------------------------------------------------------------
*/

$filterActive =
    (
        $search !== '' ||
        $subjectId !== null
    );


$freeExamCount =
    0;


$subscriptionExamCount =
    0;


$attemptedExamCount =
    0;


$availableExamCount =
    0;


foreach (
    $exams as &$exam
) {

    $examId =
        (int) $exam['id'];


    $latestResult =
        $latestResults[
            $examId
        ] ?? null;


    $activeAttempt =
        $activeAttempts[
            $examId
        ] ?? null;


    $requiresSubscription =
        (int) $exam[
            'subscription_required'
        ] === 1;


    $examAccess =
        true;


    $accessMessage =
        '';


    if (
        $requiresSubscription &&
        !$hasSubscription
    ) {

        $examAccess =
            false;


        $accessMessage =
            'An active subscription is required for this practice exam.';
    }


    if (
        $requiresSubscription
    ) {

        $subscriptionExamCount++;

    } else {

        $freeExamCount++;
    }


    if (
        $latestResult !== null
    ) {

        $attemptedExamCount++;
    }


    if (
        $examAccess
    ) {

        $availableExamCount++;
    }


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
        $examAccess;


    $exam[
        'access_message'
    ] =
        $accessMessage;
}

unset($exam);


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
        content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
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
    >


    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
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
        href="assets/css/dashboard.css"
    >


    <link
        rel="stylesheet"
        href="assets/css/practice-exams-pro.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/practice-exams-complete.css"
    >

</head>


<body class="practice-exams-page-body">


<?php
include 'includes/navbar.php';
?>


<main class="practice-exams-pro">


    <div class="container">


        <!-- =====================================================
             HEADER
        ====================================================== -->


        <section class="practice-page-header">


            <div>


                <span
                    class="practice-page-kicker"
                >

                    <i
                        class="
                            fa-solid
                            fa-pen-to-square
                        "
                    ></i>

                    FREE PRACTICE

                </span>


                <h1>

                    Practice smarter.
                    <em>Perform stronger.</em>

                </h1>


                <p>

                    Take active practice examinations,
                    track your improvement and build confidence
                    before your next important exam.

                </p>

            </div>


            <div
                class="
                    practice-page-header-badge
                "
            >

                <i
                    class="
                        fa-solid
                        fa-infinity
                    "
                ></i>


                <span>

                    <strong>
                        Practice your way
                    </strong>


                    <small>

                        Free exams are available to registered students.
                        Premium practice can require an active subscription.

                    </small>

                </span>

            </div>


        </section>


        <!-- =====================================================
             SUMMARY
        ====================================================== -->


        <section
            class="
                practice-summary-strip
            "
        >


            <div
                class="
                    practice-summary-card
                "
            >

                <span>

                    <i
                        class="
                            fa-solid
                            fa-circle-check
                        "
                    ></i>

                </span>


                <div>

                    <small>
                        Ready exams
                    </small>


                    <strong>
                        <?= count($exams) ?>
                    </strong>

                </div>

            </div>


            <div
                class="
                    practice-summary-card
                "
            >

                <span>

                    <i
                        class="
                            fa-solid
                            fa-infinity
                        "
                    ></i>

                </span>


                <div>

                    <small>
                        Free access
                    </small>


                    <strong>
                        <?= $freeExamCount ?>
                    </strong>

                </div>

            </div>


            <div
                class="
                    practice-summary-card
                "
            >

                <span>

                    <i
                        class="
                            fa-solid
                            fa-crown
                        "
                    ></i>

                </span>


                <div>

                    <small>
                        Premium practice
                    </small>


                    <strong>
                        <?= $subscriptionExamCount ?>
                    </strong>

                </div>

            </div>


            <div
                class="
                    practice-summary-card
                "
            >

                <span>

                    <i
                        class="
                            fa-solid
                            fa-chart-column
                        "
                    ></i>

                </span>


                <div>

                    <small>
                        Attempted
                    </small>


                    <strong>
                        <?= $attemptedExamCount ?>
                    </strong>

                </div>

            </div>


        </section>


        <!-- =====================================================
             FILTERS
        ====================================================== -->


        <section
            class="
                practice-filter-panel
            "
        >

            <form
                method="GET"
                class="practice-filter-form"
            >


                <div class="practice-search">

                    <i
                        class="
                            fa-solid
                            fa-magnifying-glass
                        "
                    ></i>


                    <input
                        type="search"
                        name="search"
                        value="<?= practice_escape(
                            $search
                        ) ?>"
                        placeholder="Search exam, subject or topic..."
                    >

                </div>


                <div
                    class="
                        practice-subject-select
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


                        <?php
                        foreach (
                            $subjects as $subject
                        ):
                        ?>


                            <option
                                value="<?= (int) $subject['id'] ?>"
                                <?= (
                                    $subjectId !== null &&
                                    $subjectId ===
                                    (int) $subject['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= practice_escape(
                                    $subject['name']
                                ) ?>


                                <?php
                                if (
                                    !empty(
                                        $subject['code']
                                    )
                                ):
                                ?>

                                    (
                                    <?= practice_escape(
                                        $subject['code']
                                    ) ?>
                                    )

                                <?php
                                endif;
                                ?>

                            </option>


                        <?php
                        endforeach;
                        ?>

                    </select>

                </div>


                <button
                    type="submit"
                    class="
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


                <?php
                if (
                    $filterActive
                ):
                ?>


                    <a
                        href="practice_exams.php"
                        class="
                            practice-clear-btn
                        "
                    >

                        Clear

                    </a>


                <?php
                endif;
                ?>


            </form>

        </section>


        <!-- =====================================================
             RESULTS HEADER
        ====================================================== -->


        <section
            class="
                practice-results-heading
            "
        >


            <div>

                <span>
                    READY EXAMINATIONS
                </span>


                <h2>
                    Explore practice exams
                </h2>

            </div>


            <div
                class="
                    practice-results-count
                "
            >

                <strong>
                    <?= count($exams) ?>
                </strong>


                <span>

                    <?= count($exams) === 1
                        ? 'exam available'
                        : 'exams available'
                    ?>

                </span>

            </div>


        </section>


        <!-- =====================================================
             EXAMS
        ====================================================== -->


        <?php
        if (
            !empty($exams)
        ):
        ?>


            <section
                class="
                    practice-exam-grid
                "
            >


                <?php
                foreach (
                    $exams as $index => $exam
                ):
                ?>


                    <?php

                    $examId =
                        (int) $exam['id'];


                    $questionCount =
                        (int) $exam[
                            'active_question_count'
                        ];


                    $requiredCount =
                        (int) $exam[
                            'required_question_count'
                        ];


                    $duration =
                        (int) $exam[
                            'duration_minutes'
                        ];


                    $totalMarks =
                        (float) $exam[
                            'total_marks'
                        ];


                    $passingMarks =
                        (float) $exam[
                            'passing_marks'
                        ];


                    $isNegativeMarking =
                        (int) $exam[
                            'negative_marking'
                        ] === 1;


                    $requiresSubscription =
                        (int) $exam[
                            'subscription_required'
                        ] === 1;


                    $hasAccess =
                        (bool) $exam[
                            'exam_access'
                        ];


                    $accessMessage =
                        (string) $exam[
                            'access_message'
                        ];


                    $latestResult =
                        $exam[
                            'latest_result'
                        ] ?? null;


                    $activeAttempt =
                        $exam[
                            'active_attempt'
                        ] ?? null;


                    $number =
                        str_pad(
                            (string) (
                                $index + 1
                            ),
                            2,
                            '0',
                            STR_PAD_LEFT
                        );


                    $validationMode =
                        (string) (
                            $exam[
                                'validation_mode'
                            ] ?? ''
                        );


                    $marksPerQuestion =
                        $exam[
                            'marks_per_question'
                        ];


                    ?>


                    <article
                        class="
                            practice-exam-card
                            <?= !$hasAccess
                                ? 'locked'
                                : ''
                            ?>
                        "
                        tabindex="0"
                        data-exam-id="<?= $examId ?>"
                        data-access="<?= $hasAccess ? 'allowed' : 'locked' ?>"
                    >


                        <!-- TOP -->


                        <div
                            class="
                                practice-card-top
                            "
                        >


                            <span
                                class="
                                    practice-card-number
                                "
                            >

                                <?= practice_escape(
                                    $number
                                ) ?>

                            </span>


                            <div
                                class="
                                    practice-card-badges
                                "
                            >


                                <?php
                                if (
                                    $activeAttempt
                                ):
                                ?>


                                    <span
                                        class="
                                            practice-completed-badge
                                        "
                                    >

                                        <i
                                            class="
                                                fa-solid
                                                fa-play
                                            "
                                        ></i>

                                        In progress

                                    </span>


                                <?php
                                elseif (
                                    $latestResult
                                ):
                                ?>


                                    <span
                                        class="
                                            practice-completed-badge
                                        "
                                    >

                                        <i
                                            class="
                                                fa-solid
                                                fa-check
                                            "
                                        ></i>

                                        Attempted

                                    </span>


                                <?php
                                else:
                                ?>


                                    <span
                                        class="
                                            practice-ready-badge
                                        "
                                    >

                                        <i
                                            class="
                                                fa-solid
                                                fa-circle-check
                                            "
                                        ></i>

                                        Ready

                                    </span>


                                <?php
                                endif;
                                ?>


                                <?php
                                if (
                                    $requiresSubscription
                                ):
                                ?>


                                    <span
                                        class="
                                            practice-premium-badge
                                        "
                                    >

                                        <i
                                            class="
                                                fa-solid
                                                fa-crown
                                            "
                                        ></i>

                                        Subscription

                                    </span>


                                <?php
                                else:
                                ?>


                                    <span
                                        class="
                                            practice-free-badge
                                        "
                                    >

                                        Free

                                    </span>


                                <?php
                                endif;
                                ?>


                            </div>

                        </div>


                        <!-- ICON -->


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


                        <!-- CONTENT -->


                        <div
                            class="
                                practice-card-content
                            "
                        >


                            <span
                                class="
                                    practice-subject-label
                                "
                            >

                                <?= practice_escape(
                                    $exam['subject_name']
                                ) ?>


                                <?php
                                if (
                                    !empty(
                                        $exam['subject_code']
                                    )
                                ):
                                ?>


                                    <small>

                                        •

                                        <?= practice_escape(
                                            $exam[
                                                'subject_code'
                                            ]
                                        ) ?>

                                    </small>


                                <?php
                                endif;
                                ?>


                            </span>


                            <h3>

                                <?= practice_escape(
                                    $exam['title']
                                ) ?>

                            </h3>


                            <p>

                                <?= practice_escape(
                                    $exam['description']
                                    ?:
                                    'Build confidence with this practice examination.'
                                ) ?>

                            </p>


                        </div>


                        <!-- META -->


                        <div
                            class="
                                practice-card-meta
                            "
                        >


                            <span>

                                <i
                                    class="
                                        fa-regular
                                        fa-clock
                                    "
                                ></i>

                                <?= $duration ?>

                                min

                            </span>


                            <span>

                                <i
                                    class="
                                        fa-solid
                                        fa-list-check
                                    "
                                ></i>

                                <?= $questionCount ?>

                                questions

                            </span>


                            <span>

                                <i
                                    class="
                                        fa-solid
                                        fa-star
                                    "
                                ></i>

                                <?= practice_number(
                                    $totalMarks
                                ) ?>

                                marks

                            </span>


                        </div>


                        <!-- EXACT RULE INFORMATION -->


                        <div
                            class="
                                practice-marking-row
                            "
                        >


                            <span>

                                Passing:

                                <strong>

                                    <?= practice_number(
                                        $passingMarks
                                    ) ?>

                                </strong>

                            </span>


                            <span>


                                <?php
                                if (
                                    $validationMode ===
                                    'same_marks'
                                    &&
                                    $marksPerQuestion !== null
                                ):
                                ?>


                                    <i
                                        class="
                                            fa-solid
                                            fa-calculator
                                        "
                                    ></i>


                                    <?= practice_number(
                                        $marksPerQuestion
                                    ) ?>


                                    mark/question


                                <?php
                                else:
                                ?>


                                    <i
                                        class="
                                            fa-solid
                                            fa-layer-group
                                        "
                                    ></i>


                                    Mixed marks


                                <?php
                                endif;
                                ?>


                            </span>


                        </div>


                        <!-- NEGATIVE MARKING -->


                        <div
                            class="
                                practice-marking-row
                            "
                        >


                            <span>

                                <?php
                                if (
                                    $isNegativeMarking
                                ):
                                ?>


                                    <i
                                        class="
                                            fa-solid
                                            fa-triangle-exclamation
                                        "
                                    ></i>

                                    Negative marking


                                <?php
                                else:
                                ?>


                                    <i
                                        class="
                                            fa-solid
                                            fa-circle-check
                                        "
                                    ></i>

                                    No negative marking


                                <?php
                                endif;
                                ?>


                            </span>


                            <span>

                                Required:

                                <strong>

                                    <?= $requiredCount ?>

                                </strong>

                                questions

                            </span>


                        </div>


                        <!-- ACCESS MESSAGE -->


                        <?php
                        if (
                            !$hasAccess &&
                            $accessMessage !== ''
                        ):
                        ?>


                            <div
                                class="
                                    practice-access-message
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-lock
                                    "
                                ></i>


                                <span>

                                    <?= practice_escape(
                                        $accessMessage
                                    ) ?>

                                </span>

                            </div>


                        <?php
                        endif;
                        ?>


                        <!-- ACTION -->


                        <div
                            class="
                                practice-card-action
                            "
                        >


                            <?php
                            if (
                                !$hasAccess
                            ):
                            ?>


                                <a
                                    href="subscriptions.php"
                                    class="
                                        practice-start-btn
                                        premium
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


                            <?php
                            elseif (
                                $activeAttempt
                            ):
                            ?>


                                <a
                                    href="start_exam.php?id=<?= $examId ?>"
                                    class="
                                        practice-start-btn
                                        completed
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


                            <?php
                            elseif (
                                $latestResult
                            ):
                            ?>


                                <div
                                    class="
                                        practice-result-action
                                    "
                                >


                                    <a
                                        href="start_exam.php?id=<?= $examId ?>"
                                        class="
                                            practice-start-btn
                                            completed
                                        "
                                    >

                                        <i
                                            class="
                                                fa-solid
                                                fa-rotate
                                            "
                                        ></i>

                                        Practise again


                                        <i
                                            class="
                                                fa-solid
                                                fa-arrow-right
                                            "
                                        ></i>

                                    </a>


                                    <a
                                        href="result.php?id=<?= (int) $latestResult['id'] ?>"
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

                                        Latest result

                                    </a>

                                </div>


                            <?php
                            else:
                            ?>


                                <a
                                    href="start_exam.php?id=<?= $examId ?>"
                                    class="
                                        practice-start-btn
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


                            <?php
                            endif;
                            ?>


                        </div>


                    </article>


                <?php
                endforeach;
                ?>


            </section>


        <?php
        else:
        ?>


            <!-- =================================================
                 EMPTY STATE
            ================================================== -->


            <section
                class="
                    practice-empty-state
                "
            >


                <div
                    class="
                        practice-empty-icon
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-magnifying-glass
                        "
                    ></i>

                </div>


                <span>
                    NO MATCHING EXAMS
                </span>


                <h2>

                    No practice exams are available right now.

                </h2>


                <p>


                    <?php
                    if (
                        $filterActive
                    ):
                    ?>


                        Try removing your filters or searching
                        for a different subject.


                    <?php
                    else:
                    ?>


                        Once the administrator publishes a
                        complete active practice exam, it will
                        automatically appear here.


                    <?php
                    endif;
                    ?>


                </p>


                <?php
                if (
                    $filterActive
                ):
                ?>


                    <a
                        href="practice_exams.php"
                        class="
                            practice-empty-btn
                        "
                    >

                        Show all exams


                        <i
                            class="
                                fa-solid
                                fa-arrow-right
                            "
                        ></i>

                    </a>


                <?php
                endif;
                ?>


            </section>


        <?php
        endif;
        ?>


        <!-- =================================================
             INFORMATION
        ================================================== -->


        <section
            class="
                practice-info-strip
            "
        >


            <div>


                <span
                    class="
                        practice-info-icon
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-shield-halved
                        "
                    ></i>

                </span>


                <span>


                    <strong>

                        Only ready exams are shown

                    </strong>


                    <small>

                        An exam appears here only when its
                        active question count and total question
                        marks exactly match its configuration.

                    </small>


                </span>


            </div>


            <div>


                <span
                    class="
                        practice-info-icon
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-chart-line
                        "
                    ></i>

                </span>


                <span>


                    <strong>

                        Every result is tracked

                    </strong>


                    <small>

                        Completed attempts contribute to your
                        result history and performance analytics.

                    </small>


                </span>


            </div>


            <div>


                <span
                    class="
                        practice-info-icon
                    "
                >

                    <i
                        class="
                            fa-solid
                            fa-infinity
                        "
                    ></i>

                </span>


                <span>


                    <strong>

                        Keep practising

                    </strong>


                    <small>

                        Free practice remains available without
                        a subscription, while premium practice can
                        use subscription access.

                    </small>


                </span>


            </div>


        </section>


    </div>

</main>


<script
    src="assets/js/practice-exams.js"
    defer
></script>

</body>

</html>