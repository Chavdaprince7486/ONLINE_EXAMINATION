<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

$page_title = "Exam Questions";


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
| Exam ID
|--------------------------------------------------------------------------
*/

$exam_id = filter_input(
    INPUT_GET,
    'exam_id',
    FILTER_VALIDATE_INT
);


if (
    $exam_id === false ||
    $exam_id === null ||
    $exam_id <= 0
) {

    $_SESSION['error'] =
        "Invalid examination.";

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search =
    trim(
        (string)(
            $_GET['search'] ?? ''
        )
    );


$difficultyFilter =
    trim(
        (string)(
            $_GET['difficulty'] ?? ''
        )
    );


$statusFilter =
    trim(
        (string)(
            $_GET['status'] ?? ''
        )
    );


$allowedDifficulties = [
    '',
    'Easy',
    'Medium',
    'Hard'
];


$allowedStatuses = [
    '',
    'Active',
    'Inactive'
];


if (
    !in_array(
        $difficultyFilter,
        $allowedDifficulties,
        true
    )
) {

    $difficultyFilter = '';
}


if (
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {

    $statusFilter = '';
}


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$perPage = 10;


$page =
    filter_input(
        INPUT_GET,
        'page',
        FILTER_VALIDATE_INT
    );


if (
    $page === false ||
    $page === null ||
    $page <= 0
) {

    $page = 1;
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function question_list_escape(
    ?string $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function question_list_number(
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
| Load Exam
|--------------------------------------------------------------------------
*/

try {

    $examStmt =
        $conn->prepare("
            SELECT

                e.id,
                e.subject_id,
                e.teacher_id,

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

                e.starts_at,
                e.ends_at,
                e.status,

                e.created_at,
                e.updated_at,

                s.name AS subject_name,
                s.code AS subject_code,
                s.status AS subject_status,

                t.full_name AS teacher_name

            FROM exams e

            LEFT JOIN subjects s
                ON s.id = e.subject_id

            LEFT JOIN teachers t
                ON t.id = e.teacher_id

            WHERE e.id = ?

            LIMIT 1
        ");


    $examStmt->execute([
        $exam_id
    ]);


    $exam =
        $examStmt->fetch(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        "Question list exam query failed: " .
        $exception->getMessage()
    );

    exit(
        "Unable to load examination."
    );
}


if (
    !$exam
) {

    $_SESSION['error'] =
        "Examination not found.";

    header(
        "Location: index.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Question counts
|--------------------------------------------------------------------------
|
| Total assigned questions
| Active assigned questions
| Inactive assigned questions
|
*/

try {

    $questionCountStmt =
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
                ) AS inactive_count,

                COALESCE(
                    SUM(q.marks),
                    0
                ) AS total_marks

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE eq.exam_id = ?
        ");


    $questionCountStmt->execute([
        $exam_id
    ]);


    $questionCounts =
        $questionCountStmt->fetch(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $exception) {

    error_log(
        "Question count query failed: " .
        $exception->getMessage()
    );


    $questionCounts = [
        'assigned_count' => 0,
        'active_count' => 0,
        'inactive_count' => 0,
        'total_marks' => 0
    ];
}


$assignedQuestions =
    (int)$questionCounts[
        'assigned_count'
    ];


$activeQuestions =
    (int)$questionCounts[
        'active_count'
    ];


$inactiveQuestions =
    (int)$questionCounts[
        'inactive_count'
    ];


$assignedTotalMarks =
    (float)$questionCounts[
        'total_marks'
    ];


$requiredQuestions =
    (int)$exam[
        'required_question_count'
    ];


$remainingQuestions =
    max(
        0,
        $requiredQuestions -
        $activeQuestions
    );


$progress =
    $requiredQuestions > 0
        ? min(
            100,
            round(
                (
                    $activeQuestions /
                    $requiredQuestions
                ) * 100
            )
        )
        : 0;


$examReady =
    (
        $requiredQuestions > 0 &&
        $activeQuestions ===
        $requiredQuestions
    );


/*
|--------------------------------------------------------------------------
| Difficulty statistics
|--------------------------------------------------------------------------
*/

try {

    $difficultyStmt =
        $conn->prepare("
            SELECT

                COALESCE(
                    SUM(
                        CASE
                            WHEN q.difficulty = 'Easy'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS easy_count,

                COALESCE(
                    SUM(
                        CASE
                            WHEN q.difficulty = 'Medium'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS medium_count,

                COALESCE(
                    SUM(
                        CASE
                            WHEN q.difficulty = 'Hard'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS hard_count

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE
                eq.exam_id = ?
                AND q.status = 'Active'
        ");


    $difficultyStmt->execute([
        $exam_id
    ]);


    $difficultyStats =
        $difficultyStmt->fetch(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        "Question difficulty query failed: " .
        $exception->getMessage()
    );


    $difficultyStats = [
        'easy_count' => 0,
        'medium_count' => 0,
        'hard_count' => 0
    ];
}


$easyCount =
    (int)$difficultyStats[
        'easy_count'
    ];


$mediumCount =
    (int)$difficultyStats[
        'medium_count'
    ];


$hardCount =
    (int)$difficultyStats[
        'hard_count'
    ];


/*
|--------------------------------------------------------------------------
| Build filtered count query
|--------------------------------------------------------------------------
*/

$where = [
    "eq.exam_id = ?"
];


$countParams = [
    $exam_id
];


if (
    $search !== ''
) {

    $where[] = "
        q.question_text LIKE ?
    ";


    $countParams[] =
        '%' .
        $search .
        '%';
}


if (
    $difficultyFilter !== ''
) {

    $where[] = "
        q.difficulty = ?
    ";


    $countParams[] =
        $difficultyFilter;
}


if (
    $statusFilter !== ''
) {

    $where[] = "
        q.status = ?
    ";


    $countParams[] =
        $statusFilter;
}


$whereSql =
    implode(
        "\n AND ",
        $where
    );


/*
|--------------------------------------------------------------------------
| Filtered total
|--------------------------------------------------------------------------
*/

try {

    $filteredCountStmt =
        $conn->prepare("
            SELECT COUNT(*)

            FROM exam_questions eq

            INNER JOIN questions q
                ON q.id = eq.question_id

            WHERE
                {$whereSql}
        ");


    $filteredCountStmt->execute(
        $countParams
    );


    $filteredTotal =
        (int)$filteredCountStmt->fetchColumn();

} catch (Throwable $exception) {

    error_log(
        "Question filtered count failed: " .
        $exception->getMessage()
    );


    $filteredTotal =
        0;
}


/*
|--------------------------------------------------------------------------
| Pagination calculations
|--------------------------------------------------------------------------
*/

$totalPages =
    max(
        1,
        (int)ceil(
            $filteredTotal /
            $perPage
        )
    );


if (
    $page > $totalPages
) {

    $page =
        $totalPages;
}


$offset =
    (
        $page - 1
    ) *
    $perPage;


/*
|--------------------------------------------------------------------------
| Load questions
|--------------------------------------------------------------------------
*/

$questions =
    [];


try {

    $listSql = "
        SELECT

            q.id,
            q.question_text,
            q.question_type,

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

            eq.position

        FROM exam_questions eq

        INNER JOIN questions q
            ON q.id = eq.question_id

        WHERE
            {$whereSql}

        ORDER BY
            eq.position ASC,
            q.id ASC

        LIMIT
            {$offset},
            {$perPage}
    ";


    $listStmt =
        $conn->prepare(
            $listSql
        );


    $listStmt->execute(
        $countParams
    );


    $questions =
        $listStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        "Question list query failed: " .
        $exception->getMessage()
    );


    $questions =
        [];
}


/*
|--------------------------------------------------------------------------
| Pagination URL
|--------------------------------------------------------------------------
*/

function question_page_url(
    int $page
): string {

    global
        $exam_id,
        $search,
        $difficultyFilter,
        $statusFilter;


    $params = [
        'exam_id' =>
            $exam_id,

        'page' =>
            $page
    ];


    if (
        $search !== ''
    ) {

        $params['search'] =
            $search;
    }


    if (
        $difficultyFilter !== ''
    ) {

        $params['difficulty'] =
            $difficultyFilter;
    }


    if (
        $statusFilter !== ''
    ) {

        $params['status'] =
            $statusFilter;
    }


    return
        'questions.php?' .
        http_build_query(
            $params
        );
}


/*
|--------------------------------------------------------------------------
| Success/error message
|--------------------------------------------------------------------------
*/

$successMessage =
    $_SESSION['success']
    ?? '';

$errorMessage =
    $_SESSION['error']
    ?? '';


unset(
    $_SESSION['success'],
    $_SESSION['error']
);


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

            <?= question_list_escape(
                $exam['title']
            ); ?>

        </h1>


        <p>

            Subject:

            <strong>

                <?= question_list_escape(
                    $exam['subject_name']
                    ?: 'No subject'
                ); ?>

            </strong>


            <?php if (
                !empty(
                    $exam['subject_code']
                )
            ): ?>

                <span>
                    •
                </span>

                <?= question_list_escape(
                    $exam['subject_code']
                ); ?>

            <?php endif; ?>


            <span>
                •
            </span>


            <?= question_list_escape(
                $exam['exam_type']
            ); ?>

        </p>

    </div>


    <div
        style="
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        "
    >

        <a
            href="index.php"
            class="btn-back"
        >

            <i
                class="fa-solid fa-arrow-left"
            ></i>

            Back

        </a>


        <?php if (
            !$examReady
        ): ?>

            <a
                href="add.php?exam_id=<?= $exam_id; ?>"
                class="btn-add"
            >

                <i
                    class="fa-solid fa-plus"
                ></i>

                Add Question

            </a>

        <?php else: ?>

            <button
                type="button"
                class="btn-add"
                disabled
                style="
                    opacity:.65;
                    cursor:not-allowed;
                "
            >

                <i
                    class="fa-solid fa-lock"
                ></i>

                Question Limit Reached

            </button>

        <?php endif; ?>

    </div>

</div>


<!-- =====================================================
     MESSAGES
====================================================== -->

<?php if (
    $successMessage !== ''
): ?>

    <div class="alert alert-success">

        <?= question_list_escape(
            $successMessage
        ); ?>

    </div>

<?php endif; ?>


<?php if (
    $errorMessage !== ''
): ?>

    <div class="alert alert-danger">

        <?= question_list_escape(
            $errorMessage
        ); ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     EXAM SUMMARY
====================================================== -->

<div class="stats-grid">


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon exams">

                <i
                    class="fa-solid fa-circle-question"
                ></i>

            </div>


            <div>

                <h2>

                    <?= $activeQuestions; ?>

                </h2>

                <p>
                    Active Questions
                </p>

            </div>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon students">

                <i
                    class="fa-solid fa-list-check"
                ></i>

            </div>


            <div>

                <h2>

                    <?= $requiredQuestions; ?>

                </h2>

                <p>
                    Required Questions
                </p>

            </div>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon teachers">

                <i
                    class="fa-solid fa-hourglass-half"
                ></i>

            </div>


            <div>

                <h2>

                    <?= $remainingQuestions; ?>

                </h2>

                <p>
                    Remaining
                </p>

            </div>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon results">

                <i
                    class="
                        fa-solid
                        <?= $examReady
                            ? 'fa-circle-check'
                            : 'fa-triangle-exclamation'
                        ?>
                    "
                ></i>

            </div>


            <div>

                <h2>

                    <?= $examReady
                        ? 'Ready'
                        : $progress . '%'
                    ?>

                </h2>

                <p>
                    Exam Status
                </p>

            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     EXAM PROGRESS
====================================================== -->

<div class="performance-card">

    <div
        style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:15px;
            flex-wrap:wrap;
        "
    >

        <div>

            <h3>
                Question Progress
            </h3>

            <p
                style="
                    margin:5px 0 0;
                    color:#777;
                    font-size:13px;
                "
            >

                Active questions assigned to this examination.

            </p>

        </div>


        <div
            style="
                font-weight:700;
                color:#556B2F;
            "
        >

            <?= $activeQuestions; ?>

            /

            <?= $requiredQuestions; ?>

        </div>

    </div>


    <div class="progress-item">

        <div class="progress-title">

            <span>

                <?= $activeQuestions; ?>

                of

                <?= $requiredQuestions; ?>

                active questions

            </span>


            <span>

                <?= $progress; ?>%

            </span>

        </div>


        <div class="progress">

            <div
                class="progress-bar"
                style="
                    width:<?= $progress; ?>%;
                    background:#6D7E20;
                "
            ></div>

        </div>

    </div>


    <?php if (
        $examReady
    ): ?>

        <div
            style="
                margin-top:15px;
                padding:12px 14px;
                border-radius:10px;
                background:#EFF5E7;
                color:#567038;
                font-size:13px;
                font-weight:600;
            "
        >

            <i
                class="fa-solid fa-circle-check"
            ></i>

            This examination has the exact required
            number of active questions and is ready.

        </div>

    <?php else: ?>

        <div
            style="
                margin-top:15px;
                padding:12px 14px;
                border-radius:10px;
                background:#FBF4E5;
                color:#8A672B;
                font-size:13px;
                font-weight:600;
            "
        >

            <i
                class="
                    fa-solid
                    fa-triangle-exclamation
                "
            ></i>

            Add

            <?= $remainingQuestions; ?>

            more active question
            <?= $remainingQuestions === 1
                ? ''
                : 's'
            ?>

            before this examination is ready.

        </div>

    <?php endif; ?>

</div>


<br>


<!-- =====================================================
     DIFFICULTY STATISTICS
====================================================== -->

<div class="stats-grid">


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon students">

                <i
                    class="fa-solid fa-face-smile"
                ></i>

            </div>


            <div>

                <h2>
                    <?= $easyCount; ?>
                </h2>

                <p>
                    Easy
                </p>

            </div>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon exams">

                <i
                    class="fa-solid fa-layer-group"
                ></i>

            </div>


            <div>

                <h2>
                    <?= $mediumCount; ?>
                </h2>

                <p>
                    Medium
                </p>

            </div>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon teachers">

                <i
                    class="fa-solid fa-fire"
                ></i>

            </div>


            <div>

                <h2>
                    <?= $hardCount; ?>
                </h2>

                <p>
                    Hard
                </p>

            </div>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-icon results">

                <i
                    class="fa-solid fa-award"
                ></i>

            </div>


            <div>

                <h2>

                    <?= question_list_number(
                        $assignedTotalMarks
                    ); ?>

                </h2>

                <p>
                    Assigned Marks
                </p>

            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     QUESTION TABLE
====================================================== -->

<div class="table-card">

    <div
        class="table-header"
        style="
            align-items:flex-start;
            gap:15px;
            flex-wrap:wrap;
        "
    >

        <div>

            <h2>
                Question List
            </h2>

            <p
                style="
                    margin:4px 0 0;
                    color:#817970;
                    font-size:12px;
                "
            >

                Showing

                <?= $filteredTotal; ?>

                matching questions.

            </p>

        </div>


        <form
            method="GET"
            style="
                display:flex;
                gap:8px;
                flex-wrap:wrap;
                justify-content:flex-end;
            "
        >

            <input
                type="hidden"
                name="exam_id"
                value="<?= $exam_id; ?>"
            >


            <input
                type="text"
                name="search"
                placeholder="Search question..."
                value="<?= question_list_escape(
                    $search
                ); ?>"
            >


            <select
                name="difficulty"
            >

                <option value="">
                    All Difficulty
                </option>


                <option
                    value="Easy"
                    <?= $difficultyFilter === 'Easy'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Easy
                </option>


                <option
                    value="Medium"
                    <?= $difficultyFilter === 'Medium'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Medium
                </option>


                <option
                    value="Hard"
                    <?= $difficultyFilter === 'Hard'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Hard
                </option>

            </select>


            <select
                name="status"
            >

                <option value="">
                    All Status
                </option>


                <option
                    value="Active"
                    <?= $statusFilter === 'Active'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Active
                </option>


                <option
                    value="Inactive"
                    <?= $statusFilter === 'Inactive'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Inactive
                </option>

            </select>


            <button
                class="btn-save"
                type="submit"
            >

                <i
                    class="fa-solid fa-filter"
                ></i>

                Filter

            </button>


            <?php if (
                $search !== '' ||
                $difficultyFilter !== '' ||
                $statusFilter !== ''
            ): ?>

                <a
                    href="questions.php?exam_id=<?= $exam_id; ?>"
                    class="btn-back"
                >

                    Clear

                </a>

            <?php endif; ?>

        </form>

    </div>


    <div
        style="
            overflow-x:auto;
        "
    >

        <table class="student-table">

            <thead>

                <tr>

                    <th>
                        Position
                    </th>

                    <th>
                        Question
                    </th>

                    <th>
                        Type
                    </th>

                    <th>
                        Difficulty
                    </th>

                    <th>
                        Marks
                    </th>

                    <th>
                        Negative
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if (
                !empty($questions)
            ): ?>


                <?php foreach (
                    $questions
                    as $question
                ): ?>

                    <?php

                    $statusClass =
                        $question['status'] ===
                        'Active'
                            ? 'active'
                            : 'inactive';


                    $difficultyClass =
                        strtolower(
                            (string)$question[
                                'difficulty'
                            ]
                        );

                    ?>


                    <tr>


                        <!-- POSITION -->

                        <td>

                            <span
                                class="status active"
                            >

                                #

                                <?= (int)$question[
                                    'position'
                                ]; ?>

                            </span>

                        </td>


                        <!-- QUESTION -->

                        <td>

                            <div>

                                <strong>

                                    Q
                                    <?= (int)$question[
                                        'position'
                                    ]; ?>

                                </strong>


                                <p
                                    style="
                                        margin:4px 0 0;
                                        max-width:520px;
                                        color:#655e57;
                                        line-height:1.55;
                                    "
                                >

                                    <?= question_list_escape(
                                        mb_strimwidth(
                                            (string)$question[
                                                'question_text'
                                            ],
                                            0,
                                            180,
                                            '...'
                                        )
                                    ); ?>

                                </p>

                            </div>

                        </td>


                        <!-- TYPE -->

                        <td>

                            <span
                                class="status"
                            >

                                <?= question_list_escape(
                                    $question[
                                        'question_type'
                                    ]
                                ); ?>

                            </span>

                        </td>


                        <!-- DIFFICULTY -->

                        <td>

                            <span
                                class="
                                    status
                                    <?= question_list_escape(
                                        $difficultyClass
                                    ); ?>
                                "
                            >

                                <?= question_list_escape(
                                    $question[
                                        'difficulty'
                                    ]
                                ); ?>

                            </span>

                        </td>


                        <!-- MARKS -->

                        <td>

                            <?= question_list_number(
                                (float)$question[
                                    'marks'
                                ]
                            ); ?>

                        </td>


                        <!-- NEGATIVE -->

                        <td>

                            <?php if (
                                (float)$question[
                                    'negative_marks'
                                ] > 0
                            ): ?>

                                <span
                                    style="
                                        color:#9A5F2D;
                                        font-weight:700;
                                    "
                                >

                                    -
                                    <?= question_list_number(
                                        (float)$question[
                                            'negative_marks'
                                        ]
                                    ); ?>

                                </span>

                            <?php else: ?>

                                <span
                                    style="
                                        color:#66775A;
                                    "
                                >
                                    None
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <span
                                class="
                                    status
                                    <?= $statusClass; ?>
                                "
                            >

                                <?= question_list_escape(
                                    $question[
                                        'status'
                                    ]
                                ); ?>

                            </span>

                        </td>


                        <!-- ACTION -->

                        <td>

                            <div
                                class="action-buttons"
                            >

                                <a
                                    href="
                                        view.php?id=<?= (int)$question['id']; ?>
                                    "
                                    class="btn-view"
                                    title="View"
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-eye
                                        "
                                    ></i>

                                </a>


                                <a
                                    href="
                                        edit.php?id=<?= (int)$question['id']; ?>
                                    "
                                    class="btn-edit"
                                    title="Edit"
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-pen
                                        "
                                    ></i>

                                </a>


                                <a
                                    href="
                                        delete.php?id=<?= (int)$question['id']; ?>
                                    "
                                    class="btn-delete"
                                    title="Delete"
                                    onclick="
                                        return confirm(
                                            'Delete this question? This cannot be undone.'
                                        );
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-trash
                                        "
                                    ></i>

                                </a>

                            </div>

                        </td>


                    </tr>

                <?php endforeach; ?>


            <?php else: ?>


                <tr>

                    <td colspan="8">

                        <div
                            style="
                                padding:50px 20px;
                                text-align:center;
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-circle-question
                                "
                                style="
                                    font-size:45px;
                                    color:#6D7E20;
                                "
                            ></i>


                            <h3>
                                No Questions Found
                            </h3>


                            <p>

                                <?php if (
                                    $search !== '' ||
                                    $difficultyFilter !== '' ||
                                    $statusFilter !== ''
                                ): ?>

                                    No questions match your
                                    selected filters.

                                <?php else: ?>

                                    Add the first question to
                                    this examination.

                                <?php endif; ?>

                            </p>


                            <?php if (
                                !$examReady
                            ): ?>

                                <a
                                    href="add.php?exam_id=<?= $exam_id; ?>"
                                    class="btn-add"
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-plus
                                        "
                                    ></i>

                                    Add Question

                                </a>

                            <?php endif; ?>

                        </div>

                    </td>

                </tr>


            <?php endif; ?>


            </tbody>

        </table>

    </div>


    <!-- =================================================
         PAGINATION
    ================================================== -->

    <?php if (
        $totalPages > 1
    ): ?>

        <div
            style="
                display:flex;
                justify-content:center;
                align-items:center;
                gap:6px;
                flex-wrap:wrap;
                margin-top:20px;
            "
        >

            <?php if (
                $page > 1
            ): ?>

                <a
                    href="<?= question_list_escape(
                        question_page_url(
                            $page - 1
                        )
                    ); ?>"
                    class="btn-back"
                >

                    <i
                        class="
                            fa-solid
                            fa-chevron-left
                        "
                    ></i>

                </a>

            <?php endif; ?>


            <?php

            $startPage =
                max(
                    1,
                    $page - 2
                );


            $endPage =
                min(
                    $totalPages,
                    $page + 2
                );

            ?>


            <?php for (
                $paginationPage = $startPage;
                $paginationPage <= $endPage;
                $paginationPage++
            ): ?>

                <a
                    href="<?= question_list_escape(
                        question_page_url(
                            $paginationPage
                        )
                    ); ?>"
                    style="
                        display:inline-flex;
                        align-items:center;
                        justify-content:center;
                        min-width:38px;
                        height:38px;
                        padding:0 10px;
                        border-radius:9px;
                        text-decoration:none;
                        font-size:13px;
                        font-weight:700;
                        border:1px solid #DED8CE;
                        background:
                            <?= $paginationPage === $page
                                ? '#556B2F'
                                : '#FFFFFF'
                            ?>;
                        color:
                            <?= $paginationPage === $page
                                ? '#FFFFFF'
                                : '#6D655D'
                            ?>;
                    "
                >

                    <?= $paginationPage; ?>

                </a>

            <?php endfor; ?>


            <?php if (
                $page < $totalPages
            ): ?>

                <a
                    href="<?= question_list_escape(
                        question_page_url(
                            $page + 1
                        )
                    ); ?>"
                    class="btn-back"
                >

                    <i
                        class="
                            fa-solid
                            fa-chevron-right
                        "
                    ></i>

                </a>

            <?php endif; ?>

        </div>

    <?php endif; ?>


</div>


</div>

</div>

</div>

<?php include "../includes/footer.php"; ?>