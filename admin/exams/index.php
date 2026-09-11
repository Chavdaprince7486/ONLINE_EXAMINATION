<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

$page_title = "Exam Management";

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header("Location: ../../auth/login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function exam_escape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function exam_status_class(string $status): string
{
    return match ($status) {
        'Running', 'Live', 'Active' => 'running',
        'Upcoming', 'Scheduled' => 'upcoming',
        'Completed' => 'completed',
        'Draft' => 'draft',
        'Cancelled' => 'inactive',
        default => 'inactive'
    };
}


/*
|--------------------------------------------------------------------------
| SEARCH / FILTERS
|--------------------------------------------------------------------------
*/

$search = trim(
    (string) ($_GET['search'] ?? '')
);

$categoryId = filter_input(
    INPUT_GET,
    'category_id',
    FILTER_VALIDATE_INT
);

$categoryId =
    ($categoryId !== false && $categoryId !== null)
        ? max(0, (int) $categoryId)
        : 0;

$subjectId = filter_input(
    INPUT_GET,
    'subject_id',
    FILTER_VALIDATE_INT
);

$subjectId =
    ($subjectId !== false && $subjectId !== null)
        ? max(0, (int) $subjectId)
        : 0;

$teacherId = filter_input(
    INPUT_GET,
    'teacher_id',
    FILTER_VALIDATE_INT
);

$teacherId =
    ($teacherId !== false && $teacherId !== null)
        ? max(0, (int) $teacherId)
        : 0;

$status = trim(
    (string) ($_GET['status'] ?? '')
);

$examType = trim(
    (string) ($_GET['exam_type'] ?? '')
);


/*
|--------------------------------------------------------------------------
| ALLOWED FILTER VALUES
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'Draft',
    'Scheduled',
    'Live',
    'Completed',
    'Cancelled',
    'Active',
    'Upcoming',
    'Running'
];

$allowedExamTypes = [
    'Practice',
    'Live'
];

if (
    $status !== '' &&
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {
    $status = '';
}

if (
    $examType !== '' &&
    !in_array(
        $examType,
        $allowedExamTypes,
        true
    )
) {
    $examType = '';
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$page = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT
);

$page =
    ($page !== false && $page !== null)
        ? max(1, (int) $page)
        : 1;

$limit = 10;

$offset =
    ($page - 1) * $limit;


/*
|--------------------------------------------------------------------------
| FILTER CONDITIONS
|--------------------------------------------------------------------------
|
| Category is connected through:
|
| exams.subject_id
|      ↓
| subjects.category_id
|      ↓
| categories.id
|
*/

$where = [];

$params = [];

if ($search !== '') {

    $where[] = "
        (
            e.title LIKE :search
            OR s.name LIKE :search
            OR c.category_name LIKE :search
            OR t.full_name LIKE :search
        )
    ";

    $params[':search'] =
        '%' . $search . '%';
}

if ($categoryId > 0) {

    $where[] =
        's.category_id = :category_id';

    $params[':category_id'] =
        $categoryId;
}

if ($subjectId > 0) {

    $where[] =
        'e.subject_id = :subject_id';

    $params[':subject_id'] =
        $subjectId;
}

if ($teacherId > 0) {

    $where[] =
        'e.teacher_id = :teacher_id';

    $params[':teacher_id'] =
        $teacherId;
}

if ($status !== '') {

    $where[] =
        'e.status = :status';

    $params[':status'] =
        $status;
}

if ($examType !== '') {

    $where[] =
        'e.exam_type = :exam_type';

    $params[':exam_type'] =
        $examType;
}

$whereSql = '';

if (!empty($where)) {

    $whereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $where
        );
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalExams = (int) $conn
    ->query(
        "SELECT COUNT(*)
         FROM exams"
    )
    ->fetchColumn();

$totalRunning = (int) $conn
    ->query(
        "SELECT COUNT(*)
         FROM exams
         WHERE status = 'Running'"
    )
    ->fetchColumn();

$totalUpcoming = (int) $conn
    ->query(
        "SELECT COUNT(*)
         FROM exams
         WHERE status = 'Upcoming'"
    )
    ->fetchColumn();

$totalDraft = (int) $conn
    ->query(
        "SELECT COUNT(*)
         FROM exams
         WHERE status = 'Draft'"
    )
    ->fetchColumn();

$totalCompleted = (int) $conn
    ->query(
        "SELECT COUNT(*)
         FROM exams
         WHERE status = 'Completed'"
    )
    ->fetchColumn();


/*
|--------------------------------------------------------------------------
| FILTERED RECORD COUNT
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)
    FROM exams AS e

    LEFT JOIN subjects AS s
        ON s.id = e.subject_id

    LEFT JOIN categories AS c
        ON c.id = s.category_id

    LEFT JOIN teachers AS t
        ON t.id = e.teacher_id

    $whereSql
";

$countStmt = $conn->prepare(
    $countSql
);

foreach ($params as $key => $value) {

    $countStmt->bindValue(
        $key,
        $value
    );
}

$countStmt->execute();

$totalRecords =
    (int) $countStmt->fetchColumn();

$totalPages =
    max(
        1,
        (int) ceil(
            $totalRecords / $limit
        )
    );

if ($page > $totalPages) {

    $page = $totalPages;

    $offset =
        ($page - 1) * $limit;
}


/*
|--------------------------------------------------------------------------
| EXAM LIST
|--------------------------------------------------------------------------
*/

$sql = "
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

        c.category_name,

        t.full_name AS teacher_name,

        COUNT(
            DISTINCT eq.question_id
        ) AS added_questions

    FROM exams AS e

    LEFT JOIN subjects AS s
        ON s.id = e.subject_id

    LEFT JOIN categories AS c
        ON c.id = s.category_id

    LEFT JOIN teachers AS t
        ON t.id = e.teacher_id

    LEFT JOIN exam_questions AS eq
        ON eq.exam_id = e.id

    $whereSql

    GROUP BY
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
        s.name,
        c.category_name,
        t.full_name

    ORDER BY
        e.id DESC

    LIMIT :limit
    OFFSET :offset
";

$stmt = $conn->prepare(
    $sql
);

foreach ($params as $key => $value) {

    $stmt->bindValue(
        $key,
        $value
    );
}

$stmt->bindValue(
    ':limit',
    $limit,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

$exams =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| CATEGORY DROPDOWN
|--------------------------------------------------------------------------
*/

$categoryStmt = $conn->query(
    "SELECT
        id,
        category_name
     FROM categories
     WHERE status = 'Active'
     ORDER BY category_name ASC"
);

$categories =
    $categoryStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| SUBJECT DROPDOWN
|--------------------------------------------------------------------------
*/

$subjectSql = "
    SELECT
        id,
        name,
        category_id
    FROM subjects
    WHERE status = 'Active'
";

$subjectParams = [];

if ($categoryId > 0) {

    $subjectSql .=
        " AND category_id = :subject_category_id";

    $subjectParams[
        ':subject_category_id'
    ] = $categoryId;
}

$subjectSql .=
    " ORDER BY name ASC";

$subjectStmt =
    $conn->prepare(
        $subjectSql
    );

foreach (
    $subjectParams
    as $key => $value
) {

    $subjectStmt->bindValue(
        $key,
        $value,
        PDO::PARAM_INT
    );
}

$subjectStmt->execute();

$subjects =
    $subjectStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| TEACHER DROPDOWN
|--------------------------------------------------------------------------
*/

$teacherStmt = $conn->query(
    "SELECT
        id,
        full_name
     FROM teachers
     WHERE status = 'Active'
     ORDER BY full_name ASC"
);

$teachers =
    $teacherStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| QUERY STRING HELPER
|--------------------------------------------------------------------------
*/

function exam_query_url(
    array $overrides = []
): string {

    $query = $_GET;

    foreach ($overrides as $key => $value) {

        if (
            $value === null ||
            $value === ''
        ) {

            unset(
                $query[$key]
            );

            continue;
        }

        $query[$key] =
            $value;
    }

    return 'index.php?' .
        http_build_query(
            $query
        );
}


/*
|--------------------------------------------------------------------------
| PAGE START
|--------------------------------------------------------------------------
*/

include "../includes/header.php";

?>

<?php if (isset($_SESSION['success_message'])): ?>

<script>
document.addEventListener(
    "DOMContentLoaded",
    function () {

        if (typeof Swal !== "undefined") {

            Swal.fire({
                icon: "success",
                title: "Success",
                text: <?= json_encode(
                    (string) $_SESSION['success_message'],
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                ); ?>,
                confirmButtonColor: "#7A8B1E"
            });

        }
    }
);
</script>

<?php unset($_SESSION['success_message']); ?>

<?php endif; ?>


<?php if (isset($_GET['deleted'])): ?>

<script>
document.addEventListener(
    "DOMContentLoaded",
    function () {

        if (typeof Swal !== "undefined") {

            Swal.fire({
                icon: "success",
                title: "Exam Deleted",
                text: "The examination was deleted successfully.",
                confirmButtonColor: "#7A8B1E"
            });

        }
    }
);
</script>

<?php endif; ?>


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
                        Exam Management
                    </h1>

                    <p>
                        Manage all examinations.
                    </p>

                </div>

                <a
                    href="add.php"
                    class="btn-add"
                >

                    <i class="fa-solid fa-plus"></i>

                    Add Exam

                </a>

            </div>


            <!-- =====================================================
                 STATISTICS
            ====================================================== -->

            <div class="stats-grid">


                <a
                    href="index.php"
                    class="stat-card stat-link"
                >

                    <div class="stat-top">

                        <div class="stat-icon exams">

                            <i class="fa-solid fa-file-lines"></i>

                        </div>

                        <div>

                            <h2>
                                <?= $totalExams; ?>
                            </h2>

                            <p>
                                Total Exams
                            </p>

                        </div>

                    </div>

                </a>


                <a
                    href="index.php?status=Running"
                    class="stat-card stat-link"
                >

                    <div class="stat-top">

                        <div class="stat-icon students">

                            <i class="fa-solid fa-play"></i>

                        </div>

                        <div>

                            <h2>
                                <?= $totalRunning; ?>
                            </h2>

                            <p>
                                Running Exams
                            </p>

                        </div>

                    </div>

                </a>


                <a
                    href="index.php?status=Upcoming"
                    class="stat-card stat-link"
                >

                    <div class="stat-top">

                        <div class="stat-icon teachers">

                            <i class="fa-solid fa-clock"></i>

                        </div>

                        <div>

                            <h2>
                                <?= $totalUpcoming; ?>
                            </h2>

                            <p>
                                Upcoming Exams
                            </p>

                        </div>

                    </div>

                </a>


                <a
                    href="index.php?status=Draft"
                    class="stat-card stat-link"
                >

                    <div class="stat-top">

                        <div class="stat-icon results">

                            <i class="fa-solid fa-file-circle-xmark"></i>

                        </div>

                        <div>

                            <h2>
                                <?= $totalDraft; ?>
                            </h2>

                            <p>
                                Draft Exams
                            </p>

                        </div>

                    </div>

                </a>


            </div>


            <!-- =====================================================
                 FILTER + TABLE
            ====================================================== -->

            <div class="table-card">


                <div class="table-header">

                    <h2>
                        Exam List
                    </h2>


                    <form
                        method="GET"
                        class="filter-form"
                    >

                        <div class="form-grid">


                            <!-- SEARCH -->

                            <div class="form-group">

                                <input
                                    type="text"
                                    name="search"
                                    class="form-control"
                                    placeholder="Search Exam..."
                                    value="<?= exam_escape($search); ?>"
                                >

                            </div>


                            <!-- CATEGORY -->

                            <div class="form-group">

                                <select
                                    name="category_id"
                                    class="form-select"
                                >

                                    <option value="">
                                        All Categories
                                    </option>

                                    <?php foreach (
                                        $categories
                                        as $category
                                    ): ?>

                                        <option
                                            value="<?= (int) $category['id']; ?>"
                                            <?= (
                                                $categoryId ===
                                                (int) $category['id']
                                            )
                                                ? 'selected'
                                                : ''; ?>
                                        >

                                            <?= exam_escape(
                                                $category['category_name']
                                            ); ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- SUBJECT -->

                            <div class="form-group">

                                <select
                                    name="subject_id"
                                    class="form-select"
                                >

                                    <option value="">
                                        All Subjects
                                    </option>

                                    <?php foreach (
                                        $subjects
                                        as $subject
                                    ): ?>

                                        <option
                                            value="<?= (int) $subject['id']; ?>"
                                            <?= (
                                                $subjectId ===
                                                (int) $subject['id']
                                            )
                                                ? 'selected'
                                                : ''; ?>
                                        >

                                            <?= exam_escape(
                                                $subject['name']
                                            ); ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- TEACHER -->

                            <div class="form-group">

                                <select
                                    name="teacher_id"
                                    class="form-select"
                                >

                                    <option value="">
                                        All Teachers
                                    </option>

                                    <?php foreach (
                                        $teachers
                                        as $teacher
                                    ): ?>

                                        <option
                                            value="<?= (int) $teacher['id']; ?>"
                                            <?= (
                                                $teacherId ===
                                                (int) $teacher['id']
                                            )
                                                ? 'selected'
                                                : ''; ?>
                                        >

                                            <?= exam_escape(
                                                $teacher['full_name']
                                            ); ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- STATUS -->

                            <div class="form-group">

                                <select
                                    name="status"
                                    class="form-select"
                                >

                                    <option value="">
                                        All Status
                                    </option>

                                    <?php foreach (
                                        $allowedStatuses
                                        as $statusOption
                                    ): ?>

                                        <option
                                            value="<?= exam_escape($statusOption); ?>"
                                            <?= (
                                                $status ===
                                                $statusOption
                                            )
                                                ? 'selected'
                                                : ''; ?>
                                        >

                                            <?= exam_escape(
                                                $statusOption
                                            ); ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- EXAM TYPE -->

                            <div class="form-group">

                                <select
                                    name="exam_type"
                                    class="form-select"
                                >

                                    <option value="">
                                        All Types
                                    </option>

                                    <option
                                        value="Live"
                                        <?= $examType === 'Live'
                                            ? 'selected'
                                            : ''; ?>
                                    >
                                        Live
                                    </option>

                                    <option
                                        value="Practice"
                                        <?= $examType === 'Practice'
                                            ? 'selected'
                                            : ''; ?>
                                    >
                                        Practice
                                    </option>

                                </select>

                            </div>


                            <!-- ACTIONS -->

                            <div class="form-group form-actions">

                                <button
                                    type="submit"
                                    class="btn-save"
                                >

                                    <i class="fa-solid fa-magnifying-glass"></i>

                                    Search

                                </button>

                                <a
                                    href="index.php"
                                    class="btn-back"
                                >

                                    Reset

                                </a>

                            </div>

                        </div>

                    </form>

                </div>


                <!-- =================================================
                     TABLE
                ================================================== -->

                <div class="table-responsive">

                    <table class="student-table">

                        <thead>

                            <tr>

                                <th>
                                    ID
                                </th>

                                <th>
                                    Exam
                                </th>

                                <th>
                                    Category
                                </th>

                                <th>
                                    Subject
                                </th>

                                <th>
                                    Teacher
                                </th>

                                <th>
                                    Marks
                                </th>

                                <th>
                                    Duration
                                </th>

                                <th>
                                    Questions
                                </th>

                                <th>
                                    Type
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

                        <?php if (!empty($exams)): ?>


                            <?php foreach (
                                $exams
                                as $exam
                            ): ?>

                                <?php

                                $requiredQuestions =
                                    (int) (
                                        $exam[
                                            'required_question_count'
                                        ] ?? 0
                                    );

                                $addedQuestions =
                                    (int) (
                                        $exam[
                                            'added_questions'
                                        ] ?? 0
                                    );

                                $progress =
                                    0;

                                if (
                                    $requiredQuestions > 0
                                ) {

                                    $progress =
                                        (int) round(
                                            (
                                                $addedQuestions /
                                                $requiredQuestions
                                            ) * 100
                                        );

                                    $progress =
                                        min(
                                            100,
                                            max(
                                                0,
                                                $progress
                                            )
                                        );
                                }

                                $isReady =
                                    $requiredQuestions > 0 &&
                                    $addedQuestions ===
                                    $requiredQuestions;

                                $statusClass =
                                    exam_status_class(
                                        (string) $exam['status']
                                    );

                                ?>


                                <tr>


                                    <!-- ID -->

                                    <td>

                                        <?= (int) $exam['id']; ?>

                                    </td>


                                    <!-- EXAM -->

                                    <td>

                                        <strong>

                                            <?= exam_escape(
                                                $exam['title']
                                            ); ?>

                                        </strong>

                                        <small
                                            class="d-block text-muted"
                                        >

                                            #<?= (int) $exam['id']; ?>

                                        </small>

                                    </td>


                                    <!-- CATEGORY -->

                                    <td>

                                        <?= exam_escape(
                                            $exam['category_name']
                                                ?: '—'
                                        ); ?>

                                    </td>


                                    <!-- SUBJECT -->

                                    <td>

                                        <?= exam_escape(
                                            $exam['subject_name']
                                                ?: '—'
                                        ); ?>

                                    </td>


                                    <!-- TEACHER -->

                                    <td>

                                        <?= exam_escape(
                                            $exam['teacher_name']
                                                ?: '—'
                                        ); ?>

                                    </td>


                                    <!-- MARKS -->

                                    <td>

                                        <?= number_format(
                                            (float) $exam[
                                                'total_marks'
                                            ],
                                            2
                                        ); ?>

                                    </td>


                                    <!-- DURATION -->

                                    <td>

                                        <?= (int) $exam[
                                            'duration_minutes'
                                        ]; ?>

                                        Min

                                    </td>


                                    <!-- QUESTIONS -->

                                    <td>

                                        <div class="progress">

                                            <div
                                                class="progress-bar"
                                                style="width:<?= $progress; ?>%;"
                                            ></div>

                                        </div>

                                        <small>

                                            <?= $addedQuestions; ?>

                                            /

                                            <?= $requiredQuestions; ?>

                                            Questions

                                            (<?= $progress; ?>%)

                                        </small>


                                        <?php if (
                                            $isReady
                                        ): ?>

                                            <small
                                                class="d-block text-success mt-1"
                                            >

                                                <i
                                                    class="fa-solid fa-circle-check"
                                                ></i>

                                                Ready

                                            </small>

                                        <?php elseif (
                                            $requiredQuestions > 0
                                        ): ?>

                                            <small
                                                class="d-block text-warning mt-1"
                                            >

                                                <i
                                                    class="fa-solid fa-triangle-exclamation"
                                                ></i>

                                                <?= max(
                                                    0,
                                                    $requiredQuestions -
                                                    $addedQuestions
                                                ); ?>

                                                remaining

                                            </small>

                                        <?php else: ?>

                                            <small
                                                class="d-block text-danger mt-1"
                                            >

                                                Question count not configured

                                            </small>

                                        <?php endif; ?>

                                    </td>


                                    <!-- TYPE -->

                                    <td>

                                        <span
                                            class="status <?= $exam['exam_type'] === 'Live'
                                                ? 'running'
                                                : 'upcoming'; ?>"
                                        >

                                            <?= exam_escape(
                                                $exam['exam_type']
                                            ); ?>

                                        </span>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span
                                            class="status <?= exam_escape(
                                                $statusClass
                                            ); ?>"
                                        >

                                            <?= exam_escape(
                                                $exam['status']
                                            ); ?>

                                        </span>

                                    </td>


                                    <!-- ACTIONS -->

                                    <td>

                                        <div class="action-buttons">


                                            <a
                                                href="edit.php?id=<?= (int) $exam['id']; ?>"
                                                class="btn-edit"
                                                title="Edit"
                                            >

                                                <i
                                                    class="fa-solid fa-pen"
                                                ></i>

                                            </a>


                                            <a
                                                href="view.php?id=<?= (int) $exam['id']; ?>"
                                                class="btn-view"
                                                title="View"
                                            >

                                                <i
                                                    class="fa-solid fa-eye"
                                                ></i>

                                            </a>


                                            <a
                                                href="../exam_questions.php?exam_id=<?= (int) $exam['id']; ?>"
                                                class="btn-view"
                                                title="Manage Questions"
                                            >

                                                <i
                                                    class="fa-solid fa-list-check"
                                                ></i>

                                            </a>


                                            <a
                                                href="delete.php?id=<?= (int) $exam['id']; ?>"
                                                class="btn-delete"
                                                title="Delete"
                                                onclick="return confirm('Are you sure you want to delete this exam?');"
                                            >

                                                <i
                                                    class="fa-solid fa-trash"
                                                ></i>

                                            </a>


                                        </div>

                                    </td>


                                </tr>

                            <?php endforeach; ?>


                        <?php else: ?>


                            <tr>

                                <td
                                    colspan="11"
                                    style="text-align:center;"
                                >

                                    <div class="py-4">

                                        <i
                                            class="fa-solid fa-file-circle-xmark fa-2x mb-2"
                                        ></i>

                                        <p class="mb-0">

                                            No Exams Found.

                                        </p>

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
                        class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3"
                    >

                        <div class="text-muted">

                            Showing

                            <strong>
                                <?= $totalRecords > 0
                                    ? $offset + 1
                                    : 0; ?>
                            </strong>

                            to

                            <strong>
                                <?= min(
                                    $offset + $limit,
                                    $totalRecords
                                ); ?>
                            </strong>

                            of

                            <strong>
                                <?= $totalRecords; ?>
                            </strong>

                            exams

                        </div>


                        <div
                            class="d-flex align-items-center gap-2"
                        >

                            <?php if (
                                $page > 1
                            ): ?>

                                <a
                                    href="<?= exam_escape(
                                        exam_query_url([
                                            'page' => $page - 1
                                        ])
                                    ); ?>"
                                    class="btn-back"
                                >

                                    <i
                                        class="fa-solid fa-chevron-left"
                                    ></i>

                                    Previous

                                </a>

                            <?php endif; ?>


                            <span
                                class="px-2"
                            >

                                Page
                                <strong>
                                    <?= $page; ?>
                                </strong>
                                of
                                <strong>
                                    <?= $totalPages; ?>
                                </strong>

                            </span>


                            <?php if (
                                $page < $totalPages
                            ): ?>

                                <a
                                    href="<?= exam_escape(
                                        exam_query_url([
                                            'page' => $page + 1
                                        ])
                                    ); ?>"
                                    class="btn-back"
                                >

                                    Next

                                    <i
                                        class="fa-solid fa-chevron-right"
                                    ></i>

                                </a>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endif; ?>


            </div>

        </div>

    </div>

</div>