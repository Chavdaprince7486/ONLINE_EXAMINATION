<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

$page_title = "View Question";


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['admin_id'])
) {
    header("Location: ../../auth/login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function view_question_escape(
    ?string $value
): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function view_question_number(
    float $value
): string {

    return rtrim(
        rtrim(
            number_format(
                $value,
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
| Question ID
|--------------------------------------------------------------------------
*/

$questionId =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );


if (
    $questionId === false ||
    $questionId === null ||
    $questionId <= 0
) {

    $_SESSION['error'] =
        "Invalid question.";

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load question + exam relationship
|--------------------------------------------------------------------------
*/

try {

    $statement =
        $conn->prepare("
            SELECT

                q.id,
                q.subject_id,
                q.created_by_teacher_id,

                q.question_type,
                q.question_text,

                q.option_a,
                q.option_b,
                q.option_c,
                q.option_d,

                q.correct_answer,

                q.marks,
                q.negative_marks,

                q.difficulty,
                q.status,

                q.created_at,

                eq.exam_id,
                eq.position,

                e.title AS exam_title,
                e.description AS exam_description,
                e.exam_type,

                e.duration_minutes,
                e.required_question_count,
                e.total_marks,
                e.passing_marks,

                e.negative_marking,
                e.exam_fee,
                e.subscription_required,

                e.starts_at,
                e.ends_at,

                e.status AS exam_status,

                e.created_at AS exam_created_at,

                s.id AS subject_id_actual,
                s.name AS subject_name,
                s.code AS subject_code,
                s.status AS subject_status,

                t.full_name AS teacher_name

            FROM questions q

            INNER JOIN exam_questions eq
                ON eq.question_id = q.id

            INNER JOIN exams e
                ON e.id = eq.exam_id

            LEFT JOIN subjects s
                ON s.id = e.subject_id

            LEFT JOIN teachers t
                ON t.id = q.created_by_teacher_id

            WHERE
                q.id = ?

            LIMIT 1
        ");


    $statement->execute([
        $questionId
    ]);


    $question =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        "View question query failed: " .
        $exception->getMessage()
    );


    $_SESSION['error'] =
        "Unable to load question.";

    header(
        "Location: index.php"
    );

    exit;
}


if (
    !$question
) {

    $_SESSION['error'] =
        "Question not found.";

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Derived values
|--------------------------------------------------------------------------
*/

$examId =
    (int) $question[
        'exam_id'
    ];

$position =
    (int) $question[
        'position'
    ];

$requiredCount =
    (int) $question[
        'required_question_count'
    ];

$marks =
    (float) $question[
        'marks'
    ];

$negativeMarks =
    (float) $question[
        'negative_marks'
    ];

$isActive =
    $question['status'] ===
    'Active';

$isNegativeExam =
    (int) $question[
        'negative_marking'
    ] === 1;

$isSubscriptionRequired =
    (int) $question[
        'subscription_required'
    ] === 1;

$examFee =
    (float) $question[
        'exam_fee'
    ];

$isTrueFalse =
    $question['question_type'] ===
    'TrueFalse';


/*
|--------------------------------------------------------------------------
| Question type label
|--------------------------------------------------------------------------
*/

$typeLabel =
    $isTrueFalse
        ? 'True / False'
        : 'Multiple Choice';


/*
|--------------------------------------------------------------------------
| Status class
|--------------------------------------------------------------------------
*/

$statusClass =
    $isActive
        ? 'active'
        : 'inactive';


$difficultyClass =
    strtolower(
        (string) $question[
            'difficulty'
        ]
    );


/*
|--------------------------------------------------------------------------
| Created by
|--------------------------------------------------------------------------
*/

$createdBy =
    !empty(
        $question['teacher_name']
    )
        ? $question['teacher_name']
        : 'Administrator';


/*
|--------------------------------------------------------------------------
| Load neighboring questions
|--------------------------------------------------------------------------
|
| Useful for quickly moving through the exam question set.
|
*/

$previousQuestionId =
    null;

$nextQuestionId =
    null;


try {

    $previousStatement =
        $conn->prepare("
            SELECT
                eq.question_id

            FROM exam_questions eq

            WHERE
                eq.exam_id = ?
                AND eq.position < ?

            ORDER BY
                eq.position DESC

            LIMIT 1
        ");


    $previousStatement->execute([
        $examId,
        $position
    ]);


    $previousQuestionId =
        $previousStatement->fetchColumn();


    $nextStatement =
        $conn->prepare("
            SELECT
                eq.question_id

            FROM exam_questions eq

            WHERE
                eq.exam_id = ?
                AND eq.position > ?

            ORDER BY
                eq.position ASC

            LIMIT 1
        ");


    $nextStatement->execute([
        $examId,
        $position
    ]);


    $nextQuestionId =
        $nextStatement->fetchColumn();

} catch (Throwable $exception) {

    $previousQuestionId =
        null;

    $nextQuestionId =
        null;
}


/*
|--------------------------------------------------------------------------
| Load question counts
|--------------------------------------------------------------------------
*/

try {

    $countStatement =
        $conn->prepare("
            SELECT

                COUNT(*) AS assigned_count,

                COALESCE(
                    SUM(
                        CASE
                            WHEN q.status = 'Active'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS active_count,

                COALESCE(
                    SUM(
                        CASE
                            WHEN q.status = 'Inactive'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS inactive_count

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE
                eq.exam_id = ?
        ");


    $countStatement->execute([
        $examId
    ]);


    $counts =
        $countStatement->fetch(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    $counts = [
        'assigned_count' => 0,
        'active_count' => 0,
        'inactive_count' => 0
    ];
}


$activeCount =
    (int) $counts[
        'active_count'
    ];

$assignedCount =
    (int) $counts[
        'assigned_count'
    ];

$inactiveCount =
    (int) $counts[
        'inactive_count'
    ];


$examReady =
    (
        $requiredCount > 0 &&
        $activeCount ===
        $requiredCount
    );


/*
|--------------------------------------------------------------------------
| Position percentage
|--------------------------------------------------------------------------
*/

$positionPercentage =
    $requiredCount > 0
        ? round(
            (
                $position /
                $requiredCount
            ) * 100
        )
        : 0;


/*
|--------------------------------------------------------------------------
| Escape question text for display
|--------------------------------------------------------------------------
*/

$questionText =
    trim(
        (string) $question[
            'question_text'
        ]
    );


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

include "../includes/header.php";

?>

<div class="dashboard-wrapper">

<?php include "../includes/sidebar.php"; ?>

<div class="main-content">

<?php include "../includes/navbar.php"; ?>

<div class="dashboard-content">


<!-- =====================================================
     PAGE HEADER
====================================================== -->

<div class="page-header">

    <div>

        <h1>
            View Question
        </h1>

        <p>

            Question

            <strong>
                #<?= $position; ?>
            </strong>

            from

            <strong>

                <?= view_question_escape(
                    $question['exam_title']
                ); ?>

            </strong>

        </p>

    </div>


    <div
        style="
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        "
    >

        <?php if (
            $previousQuestionId
        ): ?>

            <a
                href="view.php?id=<?= (int)$previousQuestionId; ?>"
                class="btn-back"
                title="Previous question"
            >

                <i
                    class="
                        fa-solid
                        fa-chevron-left
                    "
                ></i>

            </a>

        <?php endif; ?>


        <?php if (
            $nextQuestionId
        ): ?>

            <a
                href="view.php?id=<?= (int)$nextQuestionId; ?>"
                class="btn-back"
                title="Next question"
            >

                <i
                    class="
                        fa-solid
                        fa-chevron-right
                    "
                ></i>

            </a>

        <?php endif; ?>


        <a
            href="edit.php?id=<?= $questionId; ?>"
            class="btn-add"
        >

            <i
                class="
                    fa-solid
                    fa-pen
                "
            ></i>

            Edit

        </a>


        <a
            href="questions.php?exam_id=<?= $examId; ?>"
            class="btn-back"
        >

            <i
                class="
                    fa-solid
                    fa-list
                "
            ></i>

            All Questions

        </a>

    </div>

</div>


<!-- =====================================================
     QUESTION HEADER CARD
====================================================== -->

<div
    class="performance-card"
    style="
        margin-bottom:18px;
    "
>

    <div
        style="
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:20px;
            flex-wrap:wrap;
        "
    >

        <div
            style="
                max-width:850px;
            "
        >

            <div
                style="
                    display:flex;
                    align-items:center;
                    gap:7px;
                    flex-wrap:wrap;
                    margin-bottom:10px;
                "
            >

                <span
                    class="
                        status
                        <?= $statusClass; ?>
                    "
                >

                    <?= view_question_escape(
                        $question['status']
                    ); ?>

                </span>


                <span
                    class="
                        status
                        <?= view_question_escape(
                            $difficultyClass
                        ); ?>
                    "
                >

                    <?= view_question_escape(
                        $question['difficulty']
                    ); ?>

                </span>


                <span class="status">

                    <?= view_question_escape(
                        $typeLabel
                    ); ?>

                </span>

            </div>


            <h2
                style="
                    margin:0;
                    color:#3e2723;
                    font-size:25px;
                    line-height:1.45;
                "
            >

                Question #<?= $position; ?>

            </h2>


            <p
                style="
                    margin:7px 0 0;
                    color:#756e66;
                    font-size:13px;
                "
            >

                Created by

                <strong>
                    <?= view_question_escape(
                        $createdBy
                    ); ?>
                </strong>

                •

                <?= view_question_escape(
                    $question['subject_name']
                    ?: 'No subject'
                ); ?>

            </p>

        </div>


        <div
            style="
                min-width:120px;
                text-align:right;
            "
        >

            <small
                style="
                    display:block;
                    color:#8a827a;
                    font-size:11px;
                "
            >
                POSITION
            </small>


            <strong
                style="
                    display:block;
                    margin-top:3px;
                    color:#556B2F;
                    font-size:25px;
                "
            >

                <?= $position; ?>

                <span
                    style="
                        color:#999;
                        font-size:14px;
                    "
                >
                    /
                    <?= $requiredCount; ?>
                </span>

            </strong>

        </div>

    </div>

</div>


<!-- =====================================================
     MAIN CONTENT
====================================================== -->

<div
    style="
        display:grid;
        grid-template-columns:minmax(0,1fr) 340px;
        gap:18px;
        align-items:start;
    "
>


<!-- =====================================================
     QUESTION PANEL
====================================================== -->

<div class="form-card">

    <div
        style="
            padding-bottom:16px;
            margin-bottom:18px;
            border-bottom:1px solid #ebe7df;
        "
    >

        <div
            style="
                color:#7c746d;
                font-size:11px;
                font-weight:700;
                letter-spacing:.08em;
                margin-bottom:8px;
            "
        >
            QUESTION TEXT
        </div>


        <div
            style="
                color:#3e2723;
                font-size:18px;
                line-height:1.85;
                white-space:pre-wrap;
                word-break:break-word;
            "
        >

            <?= view_question_escape(
                $questionText
            ); ?>

        </div>

    </div>


    <!-- =================================================
         OPTIONS
    ================================================== -->

    <div>

        <div
            style="
                color:#7c746d;
                font-size:11px;
                font-weight:700;
                letter-spacing:.08em;
                margin-bottom:10px;
            "
        >
            ANSWER OPTIONS
        </div>


        <?php

        $options = [
            'A' =>
                $question['option_a'],

            'B' =>
                $question['option_b'],

            'C' =>
                $question['option_c'],

            'D' =>
                $question['option_d']
        ];

        ?>


        <div
            style="
                display:grid;
                gap:10px;
            "
        >

        <?php foreach (
            $options
            as $letter => $option
        ): ?>

            <?php

            $optionText =
                trim(
                    (string)(
                        $option ?? ''
                    )
                );


            if (
                $optionText === ''
            ) {

                continue;
            }


            $isCorrect =
                $question[
                    'correct_answer'
                ] === $letter;

            ?>

            <div
                style="
                    display:grid;
                    grid-template-columns:46px minmax(0,1fr) auto;
                    gap:12px;
                    align-items:center;
                    padding:13px;
                    border:1px solid <?= $isCorrect
                        ? '#b9cc9c'
                        : '#e6e1d9'
                    ?>;
                    border-radius:12px;
                    background:<?= $isCorrect
                        ? '#F1F5E9'
                        : '#FBFAF7'
                    ?>;
                "
            >

                <span
                    style="
                        display:grid;
                        place-items:center;
                        width:38px;
                        height:38px;
                        border-radius:10px;
                        background:<?= $isCorrect
                            ? '#556B2F'
                            : '#EEEAE3'
                        ?>;
                        color:<?= $isCorrect
                            ? '#FFFFFF'
                            : '#6E665E'
                        ?>;
                        font-weight:800;
                        font-size:13px;
                    "
                >

                    <?= $letter; ?>

                </span>


                <div
                    style="
                        color:#564E47;
                        font-size:14px;
                        line-height:1.6;
                        word-break:break-word;
                    "
                >

                    <?= view_question_escape(
                        $optionText
                    ); ?>

                </div>


                <?php if (
                    $isCorrect
                ): ?>

                    <span
                        style="
                            display:inline-flex;
                            align-items:center;
                            gap:5px;
                            padding:5px 8px;
                            border-radius:999px;
                            background:#DDE8CC;
                            color:#566B3C;
                            font-size:10px;
                            font-weight:800;
                            white-space:nowrap;
                        "
                    >

                        <i
                            class="
                                fa-solid
                                fa-circle-check
                            "
                        ></i>

                        Correct

                    </span>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

        </div>

    </div>


    <!-- =================================================
         ANSWER SUMMARY
    ================================================== -->

    <div
        style="
            display:grid;
            grid-template-columns:
                repeat(3,minmax(0,1fr));
            gap:10px;
            margin-top:20px;
            padding-top:18px;
            border-top:1px solid #ebe7df;
        "
    >

        <div
            style="
                padding:12px;
                border:1px solid #e8e3db;
                border-radius:10px;
                background:#FAF9F5;
            "
        >

            <small
                style="
                    display:block;
                    color:#938B82;
                    font-size:10px;
                "
            >
                CORRECT ANSWER
            </small>


            <strong
                style="
                    display:block;
                    margin-top:3px;
                    color:#556B2F;
                    font-size:17px;
                "
            >

                Option
                <?= view_question_escape(
                    $question['correct_answer']
                ); ?>

            </strong>

        </div>


        <div
            style="
                padding:12px;
                border:1px solid #e8e3db;
                border-radius:10px;
                background:#FAF9F5;
            "
        >

            <small
                style="
                    display:block;
                    color:#938B82;
                    font-size:10px;
                "
            >
                POSITIVE MARKS
            </small>


            <strong
                style="
                    display:block;
                    margin-top:3px;
                    color:#3E2723;
                    font-size:17px;
                "
            >

                <?= view_question_number(
                    $marks
                ); ?>

            </strong>

        </div>


        <div
            style="
                padding:12px;
                border:1px solid #e8e3db;
                border-radius:10px;
                background:#FAF9F5;
            "
        >

            <small
                style="
                    display:block;
                    color:#938B82;
                    font-size:10px;
                "
            >
                NEGATIVE MARKS
            </small>


            <strong
                style="
                    display:block;
                    margin-top:3px;
                    color:#8C5D2E;
                    font-size:17px;
                "
            >

                <?= $negativeMarks > 0
                    ? '-' .
                      view_question_number(
                          $negativeMarks
                      )
                    : 'None'
                ?>

            </strong>

        </div>

    </div>

</div>


<!-- =====================================================
     SIDE INFORMATION
====================================================== -->

<div
    style="
        display:grid;
        gap:14px;
    "
>


    <!-- EXAM INFO -->

    <div class="form-card">

        <div
            style="
                display:flex;
                align-items:center;
                gap:9px;
                margin-bottom:15px;
            "
        >

            <div
                style="
                    display:grid;
                    place-items:center;
                    width:35px;
                    height:35px;
                    border-radius:10px;
                    background:#EDF3E5;
                    color:#556B2F;
                "
            >

                <i
                    class="
                        fa-solid
                        fa-file-lines
                    "
                ></i>

            </div>


            <div>

                <small
                    style="
                        display:block;
                        color:#918980;
                        font-size:10px;
                        font-weight:700;
                    "
                >
                    EXAMINATION
                </small>


                <strong
                    style="
                        color:#3E2723;
                        font-size:14px;
                    "
                >

                    <?= view_question_escape(
                        $question['exam_title']
                    ); ?>

                </strong>

            </div>

        </div>


        <div
            style="
                display:grid;
                gap:0;
            "
        >

            <div
                style="
                    padding:9px 0;
                    border-bottom:1px solid #eee9e1;
                "
            >

                <small
                    style="
                        color:#938B82;
                        font-size:10px;
                    "
                >
                    Subject
                </small>


                <strong
                    style="
                        display:block;
                        margin-top:2px;
                        color:#554E47;
                        font-size:12px;
                    "
                >

                    <?= view_question_escape(
                        $question['subject_name']
                        ?: 'Not assigned'
                    ); ?>

                    <?php if (
                        !empty(
                            $question[
                                'subject_code'
                            ]
                        )
                    ): ?>

                        (

                        <?= view_question_escape(
                            $question[
                                'subject_code'
                            ]
                        ); ?>

                        )

                    <?php endif; ?>

                </strong>

            </div>


            <div
                style="
                    padding:9px 0;
                    border-bottom:1px solid #eee9e1;
                "
            >

                <small
                    style="
                        color:#938B82;
                        font-size:10px;
                    "
                >
                    Exam Type
                </small>


                <strong
                    style="
                        display:block;
                        margin-top:2px;
                        color:#554E47;
                        font-size:12px;
                    "
                >

                    <?= view_question_escape(
                        $question['exam_type']
                    ); ?>

                </strong>

            </div>


            <div
                style="
                    padding:9px 0;
                    border-bottom:1px solid #eee9e1;
                "
            >

                <small
                    style="
                        color:#938B82;
                        font-size:10px;
                    "
                >
                    Question Position
                </small>


                <strong
                    style="
                        display:block;
                        margin-top:2px;
                        color:#556B2F;
                        font-size:12px;
                    "
                >

                    #<?= $position; ?>

                </strong>

            </div>


            <div
                style="
                    padding:9px 0;
                    border-bottom:1px solid #eee9e1;
                "
            >

                <small
                    style="
                        color:#938B82;
                        font-size:10px;
                    "
                >
                    Required Questions
                </small>


                <strong
                    style="
                        display:block;
                        margin-top:2px;
                        color:#554E47;
                        font-size:12px;
                    "
                >

                    <?= $requiredCount; ?>

                </strong>

            </div>


            <div
                style="
                    padding:9px 0;
                "
            >

                <small
                    style="
                        color:#938B82;
                        font-size:10px;
                    "
                >
                    Duration
                </small>


                <strong
                    style="
                        display:block;
                        margin-top:2px;
                        color:#554E47;
                        font-size:12px;
                    "
                >

                    <?= (int)$question[
                        'duration_minutes'
                    ]; ?>

                    minutes

                </strong>

            </div>

        </div>

    </div>


    <!-- MARKING -->

    <div class="form-card">

        <div
            style="
                display:flex;
                align-items:center;
                gap:9px;
                margin-bottom:15px;
            "
        >

            <div
                style="
                    display:grid;
                    place-items:center;
                    width:35px;
                    height:35px;
                    border-radius:10px;
                    background:#F6F0E4;
                    color:#8C672E;
                "
            >

                <i
                    class="
                        fa-solid
                        fa-calculator
                    "
                ></i>

            </div>


            <div>

                <small
                    style="
                        display:block;
                        color:#918980;
                        font-size:10px;
                        font-weight:700;
                    "
                >
                    MARKING
                </small>


                <strong
                    style="
                        color:#3E2723;
                        font-size:14px;
                    "
                >
                    Scoring rules
                </strong>

            </div>

        </div>


        <div
            style="
                display:grid;
                gap:8px;
            "
        >

            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    gap:10px;
                "
            >

                <span
                    style="
                        color:#8B837B;
                        font-size:11px;
                    "
                >
                    Positive
                </span>


                <strong
                    style="
                        color:#3E2723;
                        font-size:11px;
                    "
                >

                    <?= view_question_number(
                        $marks
                    ); ?>

                </strong>

            </div>


            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    gap:10px;
                "
            >

                <span
                    style="
                        color:#8B837B;
                        font-size:11px;
                    "
                >
                    Question negative
                </span>


                <strong
                    style="
                        color:#8C5D2E;
                        font-size:11px;
                    "
                >

                    <?= $negativeMarks > 0
                        ? '-' .
                          view_question_number(
                              $negativeMarks
                          )
                        : 'None'
                    ?>

                </strong>

            </div>


            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    gap:10px;
                "
            >

                <span
                    style="
                        color:#8B837B;
                        font-size:11px;
                    "
                >
                    Exam negative marking
                </span>


                <strong
                    style="
                        color:#556B2F;
                        font-size:11px;
                    "
                >

                    <?= $isNegativeExam
                        ? 'Enabled'
                        : 'Disabled'
                    ?>

                </strong>

            </div>

        </div>

    </div>


    <!-- READINESS -->

    <div class="form-card">

        <div
            style="
                padding:13px;
                border-radius:11px;
                background:<?= $examReady
                    ? '#EFF5E7'
                    : '#FBF4E5'
                ?>;
                color:<?= $examReady
                    ? '#5B713F'
                    : '#89672D'
                ?>;
                font-size:11px;
                line-height:1.6;
            "
        >

            <strong>

                <i
                    class="
                        fa-solid
                        <?= $examReady
                            ? 'fa-circle-check'
                            : 'fa-triangle-exclamation'
                        ?>
                    "
                ></i>


                <?= $examReady
                    ? 'Exam Ready'
                    : 'Exam Not Ready'
                ?>

            </strong>


            <p
                style="
                    margin:5px 0 0;
                "
            >

                Active questions:

                <strong>
                    <?= $activeCount; ?>
                </strong>

                /

                <?= $requiredCount; ?>

            </p>

        </div>

    </div>


    <!-- ACTIONS -->

    <div class="form-card">

        <div
            style="
                display:grid;
                gap:8px;
            "
        >

            <a
                href="edit.php?id=<?= $questionId; ?>"
                class="btn-add"
                style="
                    justify-content:center;
                "
            >

                <i
                    class="
                        fa-solid
                        fa-pen
                    "
                ></i>

                Edit Question

            </a>


            <a
                href="questions.php?exam_id=<?= $examId; ?>"
                class="btn-back"
                style="
                    justify-content:center;
                "
            >

                <i
                    class="
                        fa-solid
                        fa-list
                    "
                ></i>

                Question List

            </a>

        </div>

    </div>

</div>

</div>


<!-- =====================================================
     FOOTER QUESTION NAVIGATION
====================================================== -->

<div
    style="
        display:flex;
        justify-content:space-between;
        gap:10px;
        flex-wrap:wrap;
        margin-top:18px;
        padding:16px;
        border:1px solid #E5E0D8;
        border-radius:13px;
        background:#FFFFFF;
    "
>


    <?php if (
        $previousQuestionId
    ): ?>

        <a
            href="view.php?id=<?= (int)$previousQuestionId; ?>"
            class="btn-back"
        >

            <i
                class="
                    fa-solid
                    fa-arrow-left
                "
            ></i>

            Previous Question

        </a>

    <?php else: ?>

        <span></span>

    <?php endif; ?>


    <span
        style="
            align-self:center;
            color:#8B837B;
            font-size:11px;
        "
    >

        Question

        <strong
            style="
                color:#3E2723;
            "
        >
            <?= $position; ?>
        </strong>

        of

        <strong
            style="
                color:#3E2723;
            "
        >
            <?= $assignedCount; ?>
        </strong>

    </span>


    <?php if (
        $nextQuestionId
    ): ?>

        <a
            href="view.php?id=<?= (int)$nextQuestionId; ?>"
            class="btn-back"
        >

            Next Question

            <i
                class="
                    fa-solid
                    fa-arrow-right
                "
            ></i>

        </a>

    <?php else: ?>

        <span></span>

    <?php endif; ?>

</div>


</div>

</div>

</div>

<?php include "../includes/footer.php"; ?>