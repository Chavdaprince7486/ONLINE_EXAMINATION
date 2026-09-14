<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {
    header('Location: ../auth/login.php');
    exit;
}

$studentId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| ATTEMPT ID
|--------------------------------------------------------------------------
*/

$attemptId = filter_input(
    INPUT_GET,
    'attempt_id',
    FILTER_VALIDATE_INT
);

if (
    $attemptId === false ||
    $attemptId === null ||
    $attemptId <= 0
) {
    http_response_code(400);
    exit('Invalid examination attempt.');
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function take_exam_escape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| LOAD ATTEMPT
|--------------------------------------------------------------------------
*/

try {

    $attemptStatement = $conn->prepare("
        SELECT

            ea.id AS attempt_id,
            ea.student_id,
            ea.exam_id,

            ea.started_at,
            ea.server_deadline,
            ea.submitted_at,
            ea.last_activity_at,

            ea.status,

            ea.obtained_marks,
            ea.percentage,

            e.title AS exam_title,
            e.description AS exam_description,

            e.exam_type,
            e.status AS exam_status,

            e.duration_minutes,

            e.required_question_count,

            e.total_marks,
            e.passing_marks,

            e.negative_marking,

            e.exam_fee,
            e.subscription_required,

            e.starts_at,
            e.ends_at

        FROM exam_attempts ea

        INNER JOIN exams e
            ON e.id = ea.exam_id

        WHERE
            ea.id = ?
            AND ea.student_id = ?

        LIMIT 1
    ");

    $attemptStatement->execute([
        $attemptId,
        $studentId
    ]);

    $attempt = $attemptStatement->fetch(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $exception) {

    error_log(
        'Take exam attempt query failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load the examination.'
    );
}


if (!$attempt) {

    http_response_code(404);

    exit(
        'Examination attempt not found.'
    );
}


/*
|--------------------------------------------------------------------------
| NON-ACTIVE ATTEMPT
|--------------------------------------------------------------------------
*/

if (
    (string) $attempt['status'] !== 'Started'
) {

    try {

        $resultStatement = $conn->prepare("
            SELECT id

            FROM results

            WHERE
                attempt_id = ?

            LIMIT 1
        ");

        $resultStatement->execute([
            $attemptId
        ]);

        $resultId =
            $resultStatement->fetchColumn();

        if (
            $resultId !== false &&
            $resultId !== null
        ) {

            header(
                'Location: result.php?id=' .
                (int) $resultId
            );

            exit;
        }

    } catch (Throwable) {
    }


    exit(
        'This examination attempt is no longer active.'
    );
}


/*
|--------------------------------------------------------------------------
| SERVER DEADLINE
|--------------------------------------------------------------------------
*/

$deadline = null;

if (
    !empty(
        $attempt['server_deadline']
    )
) {

    try {

        $deadline =
            new DateTimeImmutable(
                (string) $attempt['server_deadline']
            );

    } catch (Throwable) {

        $deadline = null;
    }
}


if (
    $deadline === null
) {

    try {

        $startedAt =
            new DateTimeImmutable(
                (string) $attempt['started_at']
            );

        $durationMinutes =
            (int) (
                $attempt['duration_minutes']
                ?? 0
            );

        if (
            $durationMinutes <= 0
        ) {

            throw new RuntimeException(
                'Invalid examination duration.'
            );
        }

        $deadline =
            $startedAt->modify(
                '+' .
                $durationMinutes .
                ' minutes'
            );

    } catch (Throwable $exception) {

        http_response_code(500);

        exit(
            'Invalid examination timing configuration.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| TIME EXPIRED
|--------------------------------------------------------------------------
*/

if (
    new DateTimeImmutable() >= $deadline
) {

    if (
        empty(
            $_SESSION['exam_csrf_token']
        )
    ) {

        $_SESSION['exam_csrf_token'] =
            bin2hex(
                random_bytes(32)
            );
    }

    ?>

    <!doctype html>

    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>
            Time Over | ExamSphere
        </title>

        <link
            href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Noto+Sans+Gujarati:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        >

        <style>

            * {
                box-sizing: border-box;
            }

            body {

                margin: 0;

                min-height: 100vh;

                display: grid;

                place-items: center;

                padding: 20px;

                background: #F5F5DC;

                color: #3E2723;

                font-family:
                    Poppins,
                    sans-serif;
            }

            .take-timeout {

                width:
                    min(
                        520px,
                        100%
                    );

                padding: 38px;

                text-align: center;

                border:
                    1px solid
                    #E3DDD2;

                border-radius:
                    26px;

                background:
                    #FFFFFF;

                box-shadow:
                    0 30px 80px
                    rgba(
                        62,
                        39,
                        35,
                        .14
                    );
            }

            .take-timeout-icon {

                width: 72px;
                height: 72px;

                display: grid;

                place-items: center;

                margin:
                    0 auto 18px;

                border-radius: 20px;

                background:
                    #F0E9E2;

                color:
                    #5D4037;

                font-size: 27px;
            }

            .take-timeout h1 {

                margin:
                    0 0 8px;

                color:
                    #3E2723;

                font-size: 25px;

                font-weight: 900;
            }

            .take-timeout p {

                margin: 0;

                color:
                    #786F68;

                font-size: 11px;

                line-height: 1.8;
            }

            .take-timeout button {

                margin-top: 22px;

                min-height: 47px;

                padding:
                    0 22px;

                border: 0;

                border-radius: 12px;

                color: #FFFFFF;

                background: #5D4037;

                font-family:
                    inherit;

                font-size: 10px;

                font-weight: 800;

                cursor: pointer;
            }

        </style>

    </head>

    <body>

        <div class="take-timeout">

            <div class="take-timeout-icon">
                ⏱
            </div>

            <h1>
                Time is Over
            </h1>

            <p>
                Your examination time has expired.
                Your attempt will now be submitted
                automatically.
            </p>

            <form
                method="post"
                action="ajax/submit_exam.php"
            >

                <input
                    type="hidden"
                    name="attempt_id"
                    value="<?= (int) $attemptId ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= take_exam_escape(
                        $_SESSION['exam_csrf_token']
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="auto_submit"
                    value="1"
                >

                <button
                    type="submit"
                >
                    Submit & View Result
                </button>

            </form>

        </div>

    </body>

    </html>

    <?php

    exit;
}


/*
|--------------------------------------------------------------------------
| REQUIRED QUESTION COUNT
|--------------------------------------------------------------------------
*/

$requiredQuestionCount =
    (int) (
        $attempt[
            'required_question_count'
        ] ?? 0
    );

if (
    $requiredQuestionCount <= 0
) {

    http_response_code(409);

    exit(
        'Invalid examination question configuration.'
    );
}


/*
|--------------------------------------------------------------------------
| LOAD ACTIVE QUESTIONS
|--------------------------------------------------------------------------
*/

try {

    $questionStatement = $conn->prepare("
        SELECT

            eq.question_id,
            eq.position,

            q.question_type,

            q.question_text,
            q.question_image,

            q.option_a,
            q.option_b,
            q.option_c,
            q.option_d,

            q.difficulty,

            q.marks

        FROM exam_questions eq

        INNER JOIN questions q
            ON q.id = eq.question_id

        WHERE

            eq.exam_id = ?

            AND q.status = 'Active'

        ORDER BY

            eq.position ASC,
            q.id ASC
    ");

    $questionStatement->execute([
        (int) $attempt[
            'exam_id'
        ]
    ]);

    $questions =
        $questionStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'Take exam questions query failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load examination questions.'
    );
}


/*
|--------------------------------------------------------------------------
| EXACT QUESTION COUNT
|--------------------------------------------------------------------------
*/

if (
    count($questions) !==
    $requiredQuestionCount
) {

    http_response_code(409);

    exit(
        'The examination does not contain the configured number of active questions.'
    );
}


/*
|--------------------------------------------------------------------------
| MARKS PER QUESTION
|--------------------------------------------------------------------------
*/

$marksPerQuestion = null;

foreach (
    $questions as $question
) {

    $marks =
        round(
            (float) (
                $question[
                    'marks'
                ] ?? 0
            ),
            2
        );

    if (
        $marks <= 0
    ) {

        http_response_code(409);

        exit(
            'Question marks are not configured correctly.'
        );
    }

    if (
        $marksPerQuestion === null
    ) {

        $marksPerQuestion =
            $marks;

    } elseif (
        abs(
            $marksPerQuestion -
            $marks
        ) > 0.00001
    ) {

        http_response_code(409);

        exit(
            'All questions must use the same marks value.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| DYNAMIC TOTAL MARKS
|--------------------------------------------------------------------------
*/

$totalMarks =
    round(
        $requiredQuestionCount *
        $marksPerQuestion,
        2
    );


/*
|--------------------------------------------------------------------------
| SAVED ANSWERS
|--------------------------------------------------------------------------
*/

$savedAnswers = [];

$savedStatuses = [];


try {

    $answerStatement = $conn->prepare("
        SELECT

            question_id,
            selected_answer,
            question_status

        FROM answers

        WHERE
            attempt_id = ?

        ORDER BY
            id ASC
    ");

    $answerStatement->execute([
        $attemptId
    ]);


    while (
        $answer =
            $answerStatement->fetch(
                PDO::FETCH_ASSOC
            )
    ) {

        $questionId =
            (int) (
                $answer[
                    'question_id'
                ] ?? 0
            );


        $selectedAnswer =
            strtoupper(
                trim(
                    (string) (
                        $answer[
                            'selected_answer'
                        ] ?? ''
                    )
                )
            );


        if (
            in_array(
                $selectedAnswer,
                [
                    'A',
                    'B',
                    'C',
                    'D'
                ],
                true
            )
        ) {

            $savedAnswers[
                $questionId
            ] =
                $selectedAnswer;
        }


        $savedStatuses[
            $questionId
        ] =
            (string) (
                $answer[
                    'question_status'
                ] ??
                (
                    $selectedAnswer !== ''
                        ? 'Answered'
                        : 'Not Answered'
                )
            );
    }

} catch (Throwable $exception) {

    error_log(
        'Saved answer state load failed: ' .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| FRONTEND QUESTIONS
|--------------------------------------------------------------------------
*/

$frontendQuestions = [];


foreach (
    $questions as $index => $question
) {

    $questionId =
        (int) (
            $question[
                'question_id'
            ] ?? 0
        );


    $options = [];


    $optionMap = [

        'A' =>
            $question[
                'option_a'
            ],

        'B' =>
            $question[
                'option_b'
            ],

        'C' =>
            $question[
                'option_c'
            ],

        'D' =>
            $question[
                'option_d'
            ]

    ];


    foreach (
        $optionMap as
        $label => $text
    ) {

        $text =
            trim(
                (string) (
                    $text ?? ''
                )
            );


        if (
            $text === ''
        ) {
            continue;
        }


        $options[] = [

            'label' =>
                $label,

            'text' =>
                $text

        ];
    }


    if (
        count($options) !== 4
    ) {

        http_response_code(409);

        exit(
            'Question options are not configured correctly.'
        );
    }


    $answer =
        strtoupper(
            trim(
                (string) (
                    $savedAnswers[
                        $questionId
                    ] ?? ''
                )
            )
        );


    $status =
        trim(
            (string) (
                $savedStatuses[
                    $questionId
                ] ??
                (
                    $answer !== ''
                        ? 'Answered'
                        : 'Not Visited'
                )
            )
        );


    $allowedStatuses = [

        'Not Visited',

        'Not Answered',

        'Answered',

        'Marked for Review',

        'Answered & Marked for Review'

    ];


    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {

        $status =
            $answer !== ''
                ? 'Answered'
                : 'Not Visited';
    }


    $frontendQuestions[] = [

        'id' =>
            $questionId,

        'number' =>
            $index + 1,

        'type' =>
            (string) (
                $question[
                    'question_type'
                ] ?? 'MCQ'
            ),

        'text' =>
            (string) (
                $question[
                    'question_text'
                ] ?? ''
            ),

        'image' =>
            (string) (
                $question[
                    'question_image'
                ] ?? ''
            ),

        'difficulty' =>
            (string) (
                $question[
                    'difficulty'
                ] ?? 'General'
            ),

        'marks' =>
            (float) (
                $question[
                    'marks'
                ] ?? 0
            ),

        'options' =>
            $options,

        'answer' =>
            $answer,

        'status' =>
            $status

    ];
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION[
            'exam_csrf_token'
        ]
    )
) {

    $_SESSION[
        'exam_csrf_token'
    ] =
        bin2hex(
            random_bytes(32)
        );
}


$csrfToken =
    (string) $_SESSION[
        'exam_csrf_token'
    ];


$deadlineMs =
    $deadline->getTimestamp() * 1000;


/*
|--------------------------------------------------------------------------
| DISPLAY
|--------------------------------------------------------------------------
*/

$examType =
    trim(
        (string) (
            $attempt[
                'exam_type'
            ] ?? 'Practice'
        )
    );


$subjectName =
    trim(
        (string) (
            $attempt[
                'subject_name'
            ] ?? ''
        )
    );


$subjectCode =
    trim(
        (string) (
            $attempt[
                'subject_code'
            ] ?? ''
        )
    );


$studentName =
    trim(
        (string) (
            $_SESSION[
                'student_name'
            ] ??
            $_SESSION[
                'name'
            ] ??
            $_SESSION[
                'user_name'
            ] ??
            'Student'
        )
    );


$negativeMarking =
    (int) (
        $attempt[
            'negative_marking'
        ] ?? 0
    ) === 1;


?>

<!doctype html>

<html
    lang="en"
>

<head>

    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >


    <meta
        name="theme-color"
        content="#F5F5DC"
    >


    <meta
        name="csrf-token"
        content="<?= take_exam_escape(
            $csrfToken
        ) ?>"
    >


    <title>

        <?= take_exam_escape(
            $attempt[
                'exam_title'
            ]
        ) ?>

        | ExamSphere

    </title>


    <!-- =====================================================
         BOOTSTRAP
    ====================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         FONT AWESOME
    ====================================================== -->

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         FONTS
    ====================================================== -->

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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Noto+Sans+Gujarati:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- =====================================================
         MASTER EXAM CSS
    ====================================================== -->

    <link
        rel="stylesheet"
        href="assets/css/exam.css?v=20260914-final"
    >

</head>


<body>


<div
    class="exm-page"
>


    <!-- =====================================================
         TOP NAVIGATION
    ====================================================== -->

    <header
        class="exm-top-nav"
    >


        <div
            class="exm-logo-area"
        >

            <div
                class="exm-logo"
            >

                <i
                    class="
                        fa-solid
                        fa-book-open
                    "
                ></i>

            </div>


            <div
                class="exm-logo-copy"
            >

                <strong>
                    ExamSphere
                </strong>


                <span>
                    Smart • Secure • Success
                </span>

            </div>

        </div>


        <div
            class="exm-tools"
        >


            <button
                type="button"
                class="exm-tool"
                id="examShortcuts"
            >

                <i
                    class="
                        fa-regular
                        fa-keyboard
                    "
                ></i>

                Shortcuts

            </button>


            <button
                type="button"
                class="exm-tool"
                id="examInstructions"
            >

                <i
                    class="
                        fa-regular
                        fa-circle-question
                    "
                ></i>

                Instructions

            </button>


            <button
                type="button"
                class="exm-tool"
                id="examPaper"
            >

                <i
                    class="
                        fa-regular
                        fa-file-lines
                    "
                ></i>

                Question Paper

            </button>


        </div>


        <div
            class="exm-student-top"
        >


            <div
                class="exm-avatar"
            >

                <i
                    class="
                        fa-solid
                        fa-user
                    "
                ></i>

            </div>


            <div
                class="exm-student-copy"
            >

                <strong>

                    <?= take_exam_escape(
                        $studentName
                    ) ?>

                </strong>


                <span>
                    Student
                </span>

            </div>


            <i
                class="
                    fa-solid
                    fa-chevron-down
                "
                style="
                    color:#7D746C;
                    font-size:8px;
                "
            ></i>


        </div>


    </header>


    <!-- =====================================================
         EXAM BAR
    ====================================================== -->

    <div
        class="exm-dark-bar"
    >


        <div
            class="exm-dark-left"
        >


            <span
                class="exm-dark-type"
            >

                <?= take_exam_escape(
                    $examType
                ) ?>

            </span>


            <span
                class="exm-dark-divider"
            >
                |
            </span>


            <span
                class="exm-dark-name"
            >

                <?= take_exam_escape(
                    $attempt[
                        'exam_title'
                    ]
                ) ?>

            </span>


        </div>


        <div
            class="exm-dark-timer"
        >


            <i
                class="
                    fa-regular
                    fa-clock
                "
            ></i>


            <span>
                Time Left :
            </span>


            <strong
                id="examTimer"
            >
                00:00
            </strong>


        </div>


    </div>


    <!-- =====================================================
         MAIN CONTAINER
    ====================================================== -->

    <main
        class="exm-container"
    >


        <!-- =================================================
             SECTIONS
        ================================================== -->

        <section
            class="exm-sections"
        >


            <div
                class="exm-sections-title"
            >
                Sections
            </div>


            <div
                class="exm-section-scroll"
            >


                <div
                    class="
                        exm-section
                        active
                    "
                >

                    <i
                        class="
                            fa-regular
                            fa-circle
                        "
                    ></i>


                    <?= take_exam_escape(
                        $subjectName !== ''
                            ? $subjectName
                            : 'Practice Examination'
                    ) ?>


                    <?php if (
                        $subjectCode !== ''
                    ): ?>

                        <span>
                            <?= take_exam_escape(
                                $subjectCode
                            ) ?>
                        </span>

                    <?php endif; ?>


                </div>


            </div>


        </section>


        <!-- =================================================
             META
        ================================================== -->

        <div
            class="exm-meta-bar"
        >


            <span>
                Marks for correct answer:
            </span>


            <strong>

                <?= take_exam_escape(
                    $marksPerQuestion
                ) ?>

            </strong>


            <span
                class="exm-meta-sep"
            >
                |
            </span>


            <span>
                Negative Marks:
            </span>


            <strong>

                <?= $negativeMarking
                    ? 'Enabled'
                    : '0'
                ?>

            </strong>


        </div>


        <!-- =================================================
             MAIN GRID
        ================================================== -->

        <div
            class="exm-main"
        >


            <!-- =================================================
                 QUESTION PANEL
            ================================================== -->

            <section
                class="exm-question-panel"
            >


                <div
                    class="exm-question-tools"
                >


                    <span
                        class="exm-select-note"
                    >

                        <i
                            class="
                                fa-solid
                                fa-circle-info
                            "
                        ></i>

                        Select one correct answer

                    </span>


                    <select
                        class="exm-language"
                        id="examLanguage"
                    >

                        <option value="default">
                            English
                        </option>

                    </select>


                </div>


                <div
                    class="exm-question-body"
                    id="questionContainer"
                ></div>


                <footer
                    class="exm-question-footer"
                >


                    <div
                        class="exm-footer-left"
                    >


                        <button
                            type="button"
                            class="
                                exm-action
                                review
                            "
                            id="markNextButton"
                        >

                            <i
                                class="
                                    fa-regular
                                    fa-bookmark
                                "
                            ></i>

                            Mark for Review & Next

                        </button>


                        <button
                            type="button"
                            class="
                                exm-action
                                clear
                            "
                            id="clearButton"
                        >

                            <i
                                class="
                                    fa-regular
                                    fa-circle-xmark
                                "
                            ></i>

                            Clear Response

                        </button>


                    </div>


                    <div
                        class="exm-footer-right"
                    >


                        <button
                            type="button"
                            class="exm-action"
                            id="previousButton"
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-arrow-left
                                "
                            ></i>

                            Previous

                        </button>


                        <button
                            type="button"
                            class="
                                exm-action
                                primary
                            "
                            id="nextButton"
                        >

                            Save & Next

                            <i
                                class="
                                    fa-solid
                                    fa-arrow-right
                                "
                            ></i>

                        </button>


                    </div>


                </footer>


            </section>


            <!-- =================================================
                 SIDEBAR
            ================================================== -->

            <aside
                class="exm-sidebar"
            >


                <div
                    class="exm-sidebar-card"
                >


                    <!-- PROFILE -->

                    <section
                        class="exm-side-block"
                    >


                        <div
                            class="exm-profile"
                        >


                            <div
                                class="
                                    exm-profile-avatar
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-user
                                    "
                                ></i>

                            </div>


                            <div
                                class="
                                    exm-profile-copy
                                "
                            >

                                <strong>

                                    <?= take_exam_escape(
                                        $studentName
                                    ) ?>

                                </strong>


                                <span>
                                    Student
                                </span>

                            </div>


                        </div>


                        <!-- STATUS -->

                        <div
                            class="
                                exm-status-grid
                            "
                        >


                            <div
                                class="exm-status"
                            >

                                <span
                                    class="
                                        exm-status-number
                                        answered
                                    "
                                    id="statusAnswered"
                                >
                                    0
                                </span>

                                <span>
                                    Answered
                                </span>

                            </div>


                            <div
                                class="exm-status"
                            >

                                <span
                                    class="
                                        exm-status-number
                                        notanswered
                                    "
                                    id="statusNotAnswered"
                                >
                                    0
                                </span>

                                <span>
                                    Not Answered
                                </span>

                            </div>


                            <div
                                class="exm-status"
                            >

                                <span
                                    class="
                                        exm-status-number
                                        notvisited
                                    "
                                    id="statusNotVisited"
                                >

                                    <?= $requiredQuestionCount ?>

                                </span>

                                <span>
                                    Not Visited
                                </span>

                            </div>


                            <div
                                class="exm-status"
                            >

                                <span
                                    class="
                                        exm-status-number
                                        review
                                    "
                                    id="statusReviewed"
                                >
                                    0
                                </span>

                                <span>
                                    Marked for Review
                                </span>

                            </div>


                            <div
                                class="
                                    exm-status
                                    exm-status-full
                                "
                            >

                                <span
                                    class="
                                        exm-status-number
                                        answerreview
                                    "
                                    id="statusAnswerReview"
                                >
                                    0
                                </span>

                                <span>
                                    Answered & Marked for Review
                                </span>

                            </div>


                        </div>


                    </section>


                    <!-- QUESTION PALETTE -->

                    <section
                        class="exm-side-block"
                    >


                        <h3
                            class="exm-side-title"
                        >
                            Choose a Question
                        </h3>


                        <div
                            class="exm-palette"
                            id="questionPalette"
                        ></div>


                    </section>


                    <!-- DETAILS -->

                    <section
                        class="exm-side-block"
                    >


                        <div
                            class="exm-detail-row"
                        >

                            <span>
                                Total Questions
                            </span>


                            <strong>

                                <?= $requiredQuestionCount ?>

                            </strong>

                        </div>


                        <div
                            class="exm-detail-row"
                        >

                            <span>
                                Marks / Question
                            </span>


                            <strong>

                                <?= take_exam_escape(
                                    $marksPerQuestion
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="exm-detail-row"
                        >

                            <span>
                                Total Marks
                            </span>


                            <strong>

                                <?= take_exam_escape(
                                    $totalMarks
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="exm-detail-row"
                        >

                            <span>
                                Duration
                            </span>


                            <strong>

                                <?= (int) (
                                    $attempt[
                                        'duration_minutes'
                                    ] ?? 0
                                ) ?>

                                min

                            </strong>

                        </div>


                        <div
                            class="exm-detail-row"
                        >

                            <span>
                                Passing Marks
                            </span>


                            <strong>

                                <?= take_exam_escape(
                                    $attempt[
                                        'passing_marks'
                                    ] ?? 0
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="exm-detail-row"
                        >

                            <span>
                                Negative Marking
                            </span>


                            <strong>

                                <?= $negativeMarking
                                    ? 'Enabled'
                                    : 'None'
                                ?>

                            </strong>

                        </div>


                        <div
                            class="exm-formula"
                        >

                            <span>
                                Dynamic Total Marks
                            </span>


                            <strong>

                                <?= $requiredQuestionCount ?>

                                ×

                                <?= take_exam_escape(
                                    $marksPerQuestion
                                ) ?>

                                =

                                <?= take_exam_escape(
                                    $totalMarks
                                ) ?>

                            </strong>

                        </div>


                    </section>


                    <!-- SUBMIT -->

                    <section
                        class="exm-side-block"
                    >


                        <div
                            class="exm-submit"
                        >


                            <p>

                                Review your answers before
                                final submission. Once submitted,
                                your result will be calculated
                                securely by the server.

                            </p>


                            <button
                                type="button"
                                class="
                                    exm-submit-button
                                "
                                id="submitButton"
                            >

                                <i
                                    class="
                                        fa-solid
                                        fa-paper-plane
                                    "
                                ></i>

                                Submit

                            </button>


                        </div>


                    </section>


                </div>


            </aside>


        </div>


    </main>


    <!-- =====================================================
         TOAST
    ====================================================== -->

    <div
        class="exm-toast"
        id="examToast"
    ></div>


</div>


<!-- =========================================================
     INSTRUCTIONS MODAL
========================================================== -->

<div
    class="modal fade"
    id="instructionsModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="
            modal-dialog
            modal-dialog-centered
        "
    >

        <div
            class="modal-content"
        >


            <div
                class="modal-header"
            >


                <h5
                    class="modal-title"
                >

                    <i
                        class="
                            fa-solid
                            fa-circle-info
                            me-2
                        "
                    ></i>

                    Examination Instructions

                </h5>


                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>


            </div>


            <div
                class="modal-body"
                style="
                    padding:22px;
                    color:#6E665F;
                    font-size:10px;
                    line-height:1.85;
                "
            >


                <p>
                    Select one correct answer for each question.
                </p>


                <p>
                    Use <strong>Mark for Review & Next</strong>
                    when you want to revisit a question later.
                </p>


                <p>
                    Use <strong>Clear Response</strong>
                    to remove the currently selected answer.
                </p>


                <p>
                    Your answers are continuously synchronized
                    with the examination server.
                </p>


                <p class="mb-0">
                    When the timer reaches zero, the examination
                    is automatically submitted.
                </p>


            </div>


        </div>

    </div>

</div>


<!-- =========================================================
     SHORTCUT MODAL
========================================================== -->

<div
    class="modal fade"
    id="shortcutsModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="
            modal-dialog
            modal-dialog-centered
            modal-sm
        "
    >

        <div
            class="modal-content"
        >


            <div
                class="modal-header"
                style="
                    background:#F4EFE8;
                    color:#3E2723;
                "
            >


                <h5
                    class="modal-title"
                >
                    Keyboard Shortcuts
                </h5>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>


            </div>


            <div
                class="modal-body"
                style="
                    padding:18px;
                    font-size:9px;
                "
            >


                <div
                    class="
                        d-flex
                        justify-content-between
                        py-2
                        border-bottom
                    "
                >

                    <span>
                        Previous
                    </span>

                    <strong>
                        ←
                    </strong>

                </div>


                <div
                    class="
                        d-flex
                        justify-content-between
                        py-2
                        border-bottom
                    "
                >

                    <span>
                        Save & Next
                    </span>

                    <strong>
                        →
                    </strong>

                </div>


                <div
                    class="
                        d-flex
                        justify-content-between
                        py-2
                        border-bottom
                    "
                >

                    <span>
                        Mark Review
                    </span>

                    <strong>
                        R
                    </strong>

                </div>


                <div
                    class="
                        d-flex
                        justify-content-between
                        py-2
                    "
                >

                    <span>
                        Clear
                    </span>

                    <strong>
                        C
                    </strong>

                </div>


            </div>


        </div>

    </div>

</div>


<!-- =========================================================
     QUESTION PAPER MODAL
========================================================== -->

<div
    class="modal fade"
    id="paperModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="
            modal-dialog
            modal-dialog-centered
            modal-lg
        "
    >

        <div
            class="modal-content"
        >


            <div
                class="modal-header"
            >


                <h5
                    class="modal-title"
                >

                    <i
                        class="
                            fa-solid
                            fa-file-lines
                            me-2
                        "
                    ></i>

                    Question Paper

                </h5>


                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>


            </div>


            <div
                class="modal-body"
                style="
                    padding:20px;
                "
            >


                <p
                    style="
                        color:#776E66;
                        font-size:9px;
                    "
                >

                    Select a question number to jump directly
                    to that question.

                </p>


                <div
                    id="paperPalette"
                    style="
                        display:grid;
                        grid-template-columns:
                            repeat(10,1fr);
                        gap:7px;
                    "
                ></div>


            </div>


        </div>

    </div>

</div>


<!-- =========================================================
     SUBMIT MODAL
========================================================== -->

<div
    class="modal fade"
    id="submitModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="
            modal-dialog
            modal-dialog-centered
        "
    >

        <div
            class="modal-content"
        >


            <div
                class="modal-header"
            >


                <h5
                    class="modal-title"
                >

                    <i
                        class="
                            fa-solid
                            fa-paper-plane
                            me-2
                        "
                    ></i>

                    Submit Examination

                </h5>


                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>


            </div>


            <div
                class="modal-body"
                style="
                    padding:22px;
                "
            >


                <div
                    id="submitMessage"
                    style="
                        color:#6E665F;
                        font-size:9px;
                        line-height:1.75;
                    "
                ></div>


                <div
                    style="
                        display:grid;
                        grid-template-columns:
                            repeat(3,1fr);
                        gap:7px;
                        margin-top:14px;
                    "
                >


                    <div
                        style="
                            padding:12px 8px;
                            border:1px solid #E4DED3;
                            border-radius:10px;
                            background:#FAF8F3;
                            text-align:center;
                        "
                    >

                        <small
                            style="
                                display:block;
                                color:#7E756D;
                                font-size:6px;
                            "
                        >
                            Answered
                        </small>


                        <strong
                            id="submitAnswered"
                            style="
                                display:block;
                                margin-top:3px;
                                color:#556B2F;
                                font-size:17px;
                            "
                        >
                            0
                        </strong>

                    </div>


                    <div
                        style="
                            padding:12px 8px;
                            border:1px solid #E4DED3;
                            border-radius:10px;
                            background:#FAF8F3;
                            text-align:center;
                        "
                    >

                        <small
                            style="
                                display:block;
                                color:#7E756D;
                                font-size:6px;
                            "
                        >
                            Unanswered
                        </small>


                        <strong
                            id="submitUnanswered"
                            style="
                                display:block;
                                margin-top:3px;
                                color:#A84B42;
                                font-size:17px;
                            "
                        >
                            0
                        </strong>

                    </div>


                    <div
                        style="
                            padding:12px 8px;
                            border:1px solid #E4DED3;
                            border-radius:10px;
                            background:#FAF8F3;
                            text-align:center;
                        "
                    >

                        <small
                            style="
                                display:block;
                                color:#7E756D;
                                font-size:6px;
                            "
                        >
                            Review
                        </small>


                        <strong
                            id="submitReview"
                            style="
                                display:block;
                                margin-top:3px;
                                color:#73549A;
                                font-size:17px;
                            "
                        >
                            0
                        </strong>

                    </div>


                </div>


            </div>


            <div
                class="
                    modal-footer
                    border-0
                    p-3
                "
            >


                <button
                    type="button"
                    class="exm-action"
                    data-bs-dismiss="modal"
                >
                    Continue Exam
                </button>


                <button
                    type="button"
                    class="
                        exm-action
                        primary
                    "
                    id="confirmSubmit"
                >

                    Submit Now

                </button>


            </div>


        </div>

    </div>

</div>


<!-- =========================================================
     SUBMIT FORM
========================================================== -->

<form
    id="submitForm"
    method="post"
    action="ajax/submit_exam.php"
    style="display:none;"
>


    <input
        type="hidden"
        name="attempt_id"
        value="<?= (int) $attemptId ?>"
    >


    <input
        type="hidden"
        name="csrf_token"
        value="<?= take_exam_escape(
            $csrfToken
        ) ?>"
    >


    <input
        type="hidden"
        name="auto_submit"
        value="0"
        id="autoSubmit"
    >


</form>


<!-- =========================================================
     BOOTSTRAP JS
========================================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
></script>


<!-- =========================================================
     EXAM CONFIG
========================================================== -->

<script>

window.EXAMSPHERE_EXAM = {

    attemptId:
        <?= (int) $attemptId ?>,

    examId:
        <?= (int) (
            $attempt['exam_id']
        ) ?>,

    csrfToken:
        <?= json_encode(
            $csrfToken,
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        ) ?>,

    deadline:
        <?= (int) $deadlineMs ?>,

    durationMinutes:
        <?= (int) (
            $attempt[
                'duration_minutes'
            ] ?? 0
        ) ?>,

    requiredQuestionCount:
        <?= $requiredQuestionCount ?>,

    totalMarks:
        <?= json_encode(
            $totalMarks
        ) ?>,

    marksPerQuestion:
        <?= json_encode(
            $marksPerQuestion
        ) ?>,

    negativeMarking:
        <?= $negativeMarking
            ? 'true'
            : 'false'
        ?>,

    questions:
        <?= json_encode(
            $frontendQuestions,
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>

};

</script>


<!-- =========================================================
     EXAM JAVASCRIPT
========================================================== -->

<script
    src="assets/js/exam.js?v=20260914-final"
    defer
></script>


</body>

</html>