<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {

    header(
        'Location: ../../auth/login.php'
    );

    exit;
}


$page_title = "Teacher Management";


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search =
    trim(
        (string)(
            $_GET['search']
            ?? ''
        )
    );


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

$status =
    trim(
        (string)(
            $_GET['status']
            ?? ''
        )
    );


$allowedStatuses = [
    'Active',
    'Inactive'
];


if (
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {

    $status = '';
}


/*
|--------------------------------------------------------------------------
| BUILD CONDITIONS
|--------------------------------------------------------------------------
*/

$conditions = [];

$params = [];


if (
    $search !== ''
) {

    $conditions[] = "
        (
            t.teacher_code LIKE ?
            OR t.full_name LIKE ?
            OR t.email LIKE ?
            OR t.mobile LIKE ?
            OR t.phone LIKE ?
            OR t.qualification LIKE ?
            OR t.experience LIKE ?
        )
    ";

    $searchValue =
        '%' .
        $search .
        '%';


    for (
        $i = 0;
        $i < 7;
        $i++
    ) {

        $params[] =
            $searchValue;
    }
}


if (
    $status !== ''
) {

    $conditions[] =
        "t.status = ?";

    $params[] =
        $status;
}


/*
|--------------------------------------------------------------------------
| WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSql = '';


if (
    !empty($conditions)
) {

    $whereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $conditions
        );
}


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$stats = [
    'total_teachers' =>
        0,

    'active_teachers' =>
        0,

    'inactive_teachers' =>
        0
];


$teachers = [];


$databaseError = '';


/*
|--------------------------------------------------------------------------
| LOAD DATA
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | STATISTICS
    |--------------------------------------------------------------------------
    */

    $statsStmt =
        $conn->query(
            "
            SELECT

                COUNT(*) AS total_teachers,

                COALESCE(
                    SUM(
                        status = 'Active'
                    ),
                    0
                ) AS active_teachers,

                COALESCE(
                    SUM(
                        status = 'Inactive'
                    ),
                    0
                ) AS inactive_teachers

            FROM teachers
            "
        );


    $loadedStats =
        $statsStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        is_array(
            $loadedStats
        )
    ) {

        $stats =
            $loadedStats;
    }


    /*
    |--------------------------------------------------------------------------
    | TEACHERS
    |--------------------------------------------------------------------------
    */

    $teacherSql = "
        SELECT

            t.id,
            t.teacher_code,
            t.full_name,
            t.email,
            t.phone,
            t.mobile,
            t.gender,
            t.dob,
            t.qualification,
            t.experience,
            t.address,
            t.profile_photo,
            t.status,
            t.last_login,
            t.created_at

        FROM teachers t

        $whereSql

        ORDER BY
            t.created_at DESC,
            t.id DESC
    ";


    $teacherStmt =
        $conn->prepare(
            $teacherSql
        );


    $teacherStmt->execute(
        $params
    );


    $teachers =
        $teacherStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (
    Throwable $exception
) {

    error_log(
        'Teacher management listing failed: ' .
        $exception->getMessage()
    );


    $databaseError =
        $exception->getMessage();


    $stats = [
        'total_teachers' =>
            0,

        'active_teachers' =>
            0,

        'inactive_teachers' =>
            0
    ];


    $teachers = [];
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function teacher_index_e(
    mixed $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function teacher_index_date(
    ?string $value
): string {

    if (
        !$value
    ) {

        return '—';
    }


    $timestamp =
        strtotime(
            $value
        );


    if (
        $timestamp === false
    ) {

        return '—';
    }


    return date(
        'd M Y',
        $timestamp
    );
}


function teacher_index_datetime(
    ?string $value
): string {

    if (
        !$value
    ) {

        return 'Never';
    }


    $timestamp =
        strtotime(
            $value
        );


    if (
        $timestamp === false
    ) {

        return 'Never';
    }


    return date(
        'd M Y, h:i A',
        $timestamp
    );
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

include "../includes/header.php";

?>

<style>

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.teacher-management-page {
    position: relative;
}


/*
|--------------------------------------------------------------------------
| HEADING
|--------------------------------------------------------------------------
*/

.teacher-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 20px;
    margin-bottom: 24px;
}


.teacher-heading .eyebrow {

    display: inline-flex;
    align-items: center;
    gap: 8px;

    color: #556b2f;

    font-size: .76rem;

    font-weight: 800;

    letter-spacing: .12em;

    margin-bottom: 8px;
}


.teacher-heading h1 {
    margin: 0;
}


.teacher-heading p {

    margin: 6px 0 0;

    color: #746d68;

    max-width: 750px;
}


/*
|--------------------------------------------------------------------------
| ADD BUTTON
|--------------------------------------------------------------------------
*/

.teacher-add-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    min-height: 43px;

    padding: 0 17px;

    border-radius: 12px;

    background: #556b2f;

    color: #fff;

    text-decoration: none;

    font-weight: 800;

    box-shadow:
        0 10px 25px
        rgba(
            85,
            107,
            47,
            .18
        );

    transition: .2s ease;

    white-space: nowrap;
}


.teacher-add-btn:hover {

    background: #465b27;

    color: #fff;

    transform:
        translateY(-2px);
}


/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

.teacher-stats {

    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(
                0,
                1fr
            )
        );

    gap: 16px;

    margin-bottom: 24px;
}


.teacher-stat {

    min-height: 108px;

    display: flex;

    align-items: center;

    gap: 15px;

    padding: 20px;

    border-radius: 20px;

    background:
        rgba(
            255,
            255,
            255,
            .86
        );

    border:
        1px solid
        rgba(
            93,
            64,
            55,
            .10
        );

    box-shadow:
        0 15px 40px
        rgba(
            51,
            51,
            51,
            .07
        );

    backdrop-filter:
        blur(14px);
}


.teacher-stat-icon {

    width: 48px;

    height: 48px;

    flex: 0 0 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    background:
        rgba(
            85,
            107,
            47,
            .10
        );

    color: #556b2f;
}


.teacher-stat small {

    display: block;

    color: #766e69;

    margin-bottom: 4px;
}


.teacher-stat strong {

    display: block;

    color: #333;

    font-size: 1.55rem;

    line-height: 1;
}


/*
|--------------------------------------------------------------------------
| MAIN CARD
|--------------------------------------------------------------------------
*/

.teacher-list-card {

    overflow: hidden;

    border-radius: 24px;

    background:
        rgba(
            255,
            255,
            255,
            .88
        );

    border:
        1px solid
        rgba(
            93,
            64,
            55,
            .10
        );

    box-shadow:
        0 20px 55px
        rgba(
            51,
            51,
            51,
            .08
        );

    backdrop-filter:
        blur(15px);
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.teacher-list-header {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    gap: 18px;

    flex-wrap: wrap;

    padding: 21px 22px;

    border-bottom:
        1px solid
        rgba(
            93,
            64,
            55,
            .08
        );
}


.teacher-list-header h2 {

    margin: 0;

    font-size: 1.08rem;

    color: #333;
}


.teacher-list-header p {

    margin: 5px 0 0;

    color: #77706b;

    font-size: .86rem;
}


/*
|--------------------------------------------------------------------------
| DATABASE ERROR
|--------------------------------------------------------------------------
*/

.teacher-database-error {

    margin: 18px 22px 0;

    padding: 14px 16px;

    border-radius: 13px;

    background:
        rgba(
            163,
            58,
            50,
            .08
        );

    border:
        1px solid
        rgba(
            163,
            58,
            50,
            .15
        );

    color: #8d302b;

    font-size: .86rem;
}


/*
|--------------------------------------------------------------------------
| TOOLBAR
|--------------------------------------------------------------------------
*/

.teacher-toolbar {

    display: flex;

    align-items: center;

    gap: 8px;

    flex-wrap: wrap;
}


.teacher-search {

    width:
        min(
            310px,
            100%
        );
}


.teacher-search .form-control,
.teacher-search .btn {

    min-height: 40px;
}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

.teacher-filter {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-height: 40px;

    padding: 0 13px;

    border-radius: 11px;

    border:
        1px solid
        rgba(
            93,
            64,
            55,
            .12
        );

    background: #fff;

    color: #655d58;

    font-size: .82rem;

    font-weight: 800;

    text-decoration: none;

    transition: .2s ease;
}


.teacher-filter:hover,
.teacher-filter.active {

    background: #556b2f;

    border-color:
        #556b2f;

    color: #fff;
}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.teacher-table-wrap {

    width: 100%;

    overflow-x: auto;
}


.teacher-table {

    width: 100%;

    min-width: 1100px;

    border-collapse:
        collapse;
}


.teacher-table th {

    padding:
        15px 18px;

    background: #faf7f0;

    border-bottom:
        1px solid
        rgba(
            93,
            64,
            55,
            .08
        );

    color: #655d58;

    text-align: left;

    font-size: .74rem;

    font-weight: 800;

    text-transform:
        uppercase;

    letter-spacing: .06em;

    white-space:
        nowrap;
}


.teacher-table td {

    padding:
        16px 18px;

    border-bottom:
        1px solid
        rgba(
            93,
            64,
            55,
            .07
        );

    vertical-align:
        middle;

    color: #373330;
}


.teacher-table tbody tr {

    transition: .2s ease;
}


.teacher-table tbody tr:hover {

    background:
        rgba(
            245,
            245,
            220,
            .45
        );
}


/*
|--------------------------------------------------------------------------
| ID
|--------------------------------------------------------------------------
*/

.teacher-id {

    color: #5d4037;

    font-weight: 800;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| IDENTITY
|--------------------------------------------------------------------------
*/

.teacher-identity {

    display: flex;

    align-items: center;

    gap: 11px;

    min-width: 210px;
}


.teacher-avatar {

    width: 42px;

    height: 42px;

    flex:
        0 0 42px;

    display: flex;

    align-items: center;

    justify-content: center;

    overflow: hidden;

    border-radius: 50%;

    background: #5d4037;

    color: #fff;

    font-weight: 800;

    text-transform:
        uppercase;
}


.teacher-avatar img {

    width: 100%;

    height: 100%;

    display: block;

    object-fit: cover;
}


.teacher-identity strong {

    display: block;
}


.teacher-identity small {

    display: block;

    margin-top: 5px;

    color: #77706b;

    font-size: .76rem;
}


.teacher-code {

    display: inline-flex;

    padding: 5px 9px;

    border-radius: 8px;

    background:
        rgba(
            93,
            64,
            55,
            .07
        );

    color: #5d4037;

    font-size: .75rem;

    font-weight: 800;

    white-space:
        nowrap;
}


/*
|--------------------------------------------------------------------------
| CELL
|--------------------------------------------------------------------------
*/

.teacher-cell strong {

    display: block;
}


.teacher-cell small {

    display: block;

    color: #77706b;

    margin-top: 3px;

    font-size: .78rem;
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.teacher-status {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding:
        6px 10px;

    border-radius: 999px;

    font-size: .75rem;

    font-weight: 800;
}


.teacher-status.active {

    background:
        rgba(
            85,
            107,
            47,
            .11
        );

    color: #556b2f;
}


.teacher-status.inactive {

    background:
        rgba(
            163,
            58,
            50,
            .10
        );

    color: #96352f;
}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.teacher-actions {

    display: flex;

    align-items: center;

    gap: 7px;
}


.teacher-action {

    width: 35px;

    height: 35px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border-radius: 10px;

    border:
        1px solid
        rgba(
            93,
            64,
            55,
            .10
        );

    background: #fff;

    text-decoration: none;

    transition: .2s ease;
}


.teacher-action:hover {

    transform:
        translateY(-2px);
}


.teacher-action.view {

    color: #556b2f;
}


.teacher-action.toggle {

    color: #6f642e;
}


.teacher-action.delete {

    color: #a33a32;
}


.teacher-action.view:hover {

    background:
        rgba(
            85,
            107,
            47,
            .08
        );
}


.teacher-action.toggle:hover {

    background:
        rgba(
            111,
            100,
            46,
            .08
        );
}


.teacher-action.delete:hover {

    background:
        rgba(
            163,
            58,
            50,
            .08
        );
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.teacher-empty {

    padding:
        55px 20px !important;

    text-align: center;

    color: #766e69 !important;
}


.teacher-empty i {

    display: block;

    margin-bottom: 12px;

    color: #556b2f;

    font-size: 2rem;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 900px
) {

    .teacher-stats {

        grid-template-columns:
            1fr;
    }
}


@media (
    max-width: 700px
) {

    .teacher-heading {

        align-items:
            flex-start;

        flex-direction:
            column;
    }


    .teacher-add-btn {

        width: 100%;
    }


    .teacher-toolbar {

        width: 100%;
    }


    .teacher-search {

        width: 100%;
    }
}

</style>


<div class="dashboard-wrapper">


    <?php include "../includes/sidebar.php"; ?>


    <div class="main-content">


        <?php include "../includes/navbar.php"; ?>


        <main
            class="dashboard-content teacher-management-page"
        >


            <!-- =====================================================
                 HEADING
                 ===================================================== -->

            <section
                class="teacher-heading"
            >

                <div>

                    <span
                        class="eyebrow"
                    >

                        <i
                            class="fa-solid fa-chalkboard-user"
                        ></i>

                        USER MANAGEMENT

                    </span>


                    <h1>
                        Teacher Management
                    </h1>


                    <p>

                        Manage teacher accounts,
                        view profiles,
                        change account status,
                        and delete teacher accounts.
                        Teacher profile details cannot be edited
                        after creation.

                    </p>

                </div>


                <a
                    href="add.php"
                    class="teacher-add-btn"
                >

                    <i
                        class="fa-solid fa-plus"
                    ></i>

                    Add Teacher

                </a>

            </section>


            <!-- =====================================================
                 STATS
                 ===================================================== -->

            <section
                class="teacher-stats"
            >


                <article
                    class="teacher-stat"
                >

                    <div
                        class="teacher-stat-icon"
                    >

                        <i
                            class="fa-solid fa-users"
                        ></i>

                    </div>


                    <div>

                        <small>
                            Total Teachers
                        </small>


                        <strong>

                            <?= (int)(
                                $stats[
                                    'total_teachers'
                                ] ?? 0
                            ) ?>

                        </strong>

                    </div>

                </article>


                <article
                    class="teacher-stat"
                >

                    <div
                        class="teacher-stat-icon"
                    >

                        <i
                            class="fa-solid fa-user-check"
                        ></i>

                    </div>


                    <div>

                        <small>
                            Active Teachers
                        </small>


                        <strong>

                            <?= (int)(
                                $stats[
                                    'active_teachers'
                                ] ?? 0
                            ) ?>

                        </strong>

                    </div>

                </article>


                <article
                    class="teacher-stat"
                >

                    <div
                        class="teacher-stat-icon"
                    >

                        <i
                            class="fa-solid fa-user-slash"
                        ></i>

                    </div>


                    <div>

                        <small>
                            Inactive Teachers
                        </small>


                        <strong>

                            <?= (int)(
                                $stats[
                                    'inactive_teachers'
                                ] ?? 0
                            ) ?>

                        </strong>

                    </div>

                </article>


            </section>


            <!-- =====================================================
                 LIST CARD
                 ===================================================== -->

            <section
                class="teacher-list-card"
            >


                <header
                    class="teacher-list-header"
                >


                    <div>

                        <h2>
                            Registered Teachers
                        </h2>


                        <p>

                            <?= count(
                                $teachers
                            ) ?>

                            record<?= count(
                                $teachers
                            ) === 1
                                ? ''
                                : 's'
                            ?>

                            shown

                        </p>

                    </div>


                    <!-- =================================================
                         SEARCH / FILTER
                         ================================================= -->

                    <form
                        method="get"
                        class="teacher-toolbar"
                        autocomplete="off"
                    >


                        <div
                            class="input-group teacher-search"
                        >

                            <input
                                type="search"
                                name="search"
                                class="form-control"
                                value="<?= teacher_index_e(
                                    $search
                                ) ?>"
                                placeholder="Search teacher, email, code..."
                            >


                            <button
                                type="submit"
                                class="btn btn-dark"
                            >

                                <i
                                    class="fa-solid fa-magnifying-glass"
                                ></i>

                            </button>

                        </div>


                        <!-- ALL -->

                        <a
                            href="index.php<?= $search !== ''
                                ? '?search=' .
                                  urlencode($search)
                                : '' ?>"
                            class="teacher-filter <?= $status === ''
                                ? 'active'
                                : '' ?>"
                        >

                            All

                        </a>


                        <!-- ACTIVE -->

                        <a
                            href="index.php?status=Active<?= $search !== ''
                                ? '&search=' .
                                  urlencode($search)
                                : '' ?>"
                            class="teacher-filter <?= $status === 'Active'
                                ? 'active'
                                : '' ?>"
                        >

                            Active

                        </a>


                        <!-- INACTIVE -->

                        <a
                            href="index.php?status=Inactive<?= $search !== ''
                                ? '&search=' .
                                  urlencode($search)
                                : '' ?>"
                            class="teacher-filter <?= $status === 'Inactive'
                                ? 'active'
                                : '' ?>"
                        >

                            Inactive

                        </a>


                    </form>

                </header>


                <?php if (
                    $databaseError !== ''
                ): ?>

                    <div
                        class="teacher-database-error"
                    >

                        <i
                            class="fa-solid fa-triangle-exclamation me-2"
                        ></i>

                        Unable to load teacher records.

                        Please check your
                        database connection and
                        table structure.

                    </div>

                <?php endif; ?>


                <!-- =====================================================
                     TABLE
                     ===================================================== -->

                <div
                    class="teacher-table-wrap"
                >


                    <table
                        class="teacher-table"
                    >


                        <thead>

                            <tr>

                                <th>
                                    ID
                                </th>

                                <th>
                                    Teacher
                                </th>

                                <th>
                                    Contact
                                </th>

                                <th>
                                    Qualification
                                </th>

                                <th>
                                    Experience
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Registered
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php if (
                            !empty($teachers)
                        ): ?>


                            <?php foreach (
                                $teachers
                                as $teacher
                            ): ?>


                                <?php

                                $teacherName =
                                    trim(
                                        (string)
                                        (
                                            $teacher[
                                                'full_name'
                                            ]
                                            ??
                                            ''
                                        )
                                    );


                                $initial =
                                    function_exists(
                                        'mb_substr'
                                    )
                                    ? mb_substr(
                                        $teacherName,
                                        0,
                                        1,
                                        'UTF-8'
                                    )
                                    : substr(
                                        $teacherName,
                                        0,
                                        1
                                    );


                                $photo =
                                    trim(
                                        (string)
                                        (
                                            $teacher[
                                                'profile_photo'
                                            ]
                                            ?? ''
                                        )
                                    );


                                $photoPath = '';


                                if (
                                    $photo !== ''
                                ) {

                                    $photoPath =
                                        '../../uploads/teachers/' .
                                        basename(
                                            $photo
                                        );
                                }

                                ?>


                                <tr>


                                    <!-- ID -->

                                    <td
                                        data-label="ID"
                                    >

                                        <span
                                            class="teacher-id"
                                        >

                                            #<?= (int)(
                                                $teacher[
                                                    'id'
                                                ]
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- TEACHER -->

                                    <td
                                        data-label="Teacher"
                                    >

                                        <div
                                            class="teacher-identity"
                                        >


                                            <div
                                                class="teacher-avatar"
                                            >

                                                <?php if (
                                                    $photoPath !== ''
                                                ): ?>


                                                    <img
                                                        src="<?= teacher_index_e(
                                                            $photoPath
                                                        ) ?>"
                                                        alt="<?= teacher_index_e(
                                                            $teacherName
                                                        ) ?>"
                                                        loading="lazy"
                                                    >


                                                <?php else: ?>


                                                    <?= teacher_index_e(
                                                        $initial
                                                    ) ?>


                                                <?php endif; ?>


                                            </div>


                                            <div>


                                                <strong>

                                                    <?= teacher_index_e(
                                                        $teacherName
                                                    ) ?>

                                                </strong>


                                                <small>

                                                    <span
                                                        class="teacher-code"
                                                    >

                                                        <?= teacher_index_e(
                                                            $teacher[
                                                                'teacher_code'
                                                            ]
                                                            ??
                                                            'No code'
                                                        ) ?>

                                                    </span>

                                                </small>


                                            </div>


                                        </div>

                                    </td>


                                    <!-- CONTACT -->

                                    <td
                                        data-label="Contact"
                                    >

                                        <div
                                            class="teacher-cell"
                                        >


                                            <strong>

                                                <?= teacher_index_e(
                                                    $teacher[
                                                        'email'
                                                    ]
                                                ) ?>

                                            </strong>


                                            <small>

                                                <?= teacher_index_e(
                                                    !empty(
                                                        $teacher[
                                                            'mobile'
                                                        ]
                                                    )
                                                        ? $teacher[
                                                            'mobile'
                                                        ]
                                                        : (
                                                            $teacher[
                                                                'phone'
                                                            ]
                                                            ??
                                                            '—'
                                                        )
                                                ) ?>

                                            </small>


                                            <small>

                                                Last login:

                                                <?= teacher_index_e(
                                                    teacher_index_datetime(
                                                        $teacher[
                                                            'last_login'
                                                        ]
                                                        ??
                                                        null
                                                    )
                                                ) ?>

                                            </small>


                                        </div>

                                    </td>


                                    <!-- QUALIFICATION -->

                                    <td
                                        data-label="Qualification"
                                    >

                                        <?= teacher_index_e(
                                            $teacher[
                                                'qualification'
                                            ]
                                            ?:
                                            'Not provided'
                                        ) ?>

                                    </td>


                                    <!-- EXPERIENCE -->

                                    <td
                                        data-label="Experience"
                                    >

                                        <?= teacher_index_e(
                                            $teacher[
                                                'experience'
                                            ]
                                            ?:
                                            'Not provided'
                                        ) ?>

                                    </td>


                                    <!-- STATUS -->

                                    <td
                                        data-label="Status"
                                    >


                                        <?php if (
                                            $teacher[
                                                'status'
                                            ] ===
                                            'Active'
                                        ): ?>


                                            <span
                                                class="teacher-status active"
                                            >

                                                <i
                                                    class="fa-solid fa-circle"
                                                ></i>

                                                Active

                                            </span>


                                        <?php else: ?>


                                            <span
                                                class="teacher-status inactive"
                                            >

                                                <i
                                                    class="fa-solid fa-circle"
                                                ></i>

                                                Inactive

                                            </span>


                                        <?php endif; ?>


                                    </td>


                                    <!-- REGISTERED -->

                                    <td
                                        data-label="Registered"
                                    >

                                        <?= teacher_index_e(
                                            teacher_index_date(
                                                $teacher[
                                                    'created_at'
                                                ]
                                                ??
                                                null
                                            )
                                        ) ?>

                                    </td>


                                    <!-- ACTIONS -->

                                    <td
                                        data-label="Actions"
                                    >


                                        <div
                                            class="teacher-actions"
                                        >


                                            <!-- VIEW -->

                                            <a
                                                href="view.php?id=<?= (int)(
                                                    $teacher[
                                                        'id'
                                                    ]
                                                ) ?>"
                                                class="teacher-action view"
                                                title="View Teacher"
                                            >

                                                <i
                                                    class="fa-solid fa-eye"
                                                ></i>

                                            </a>


                                            <!-- STATUS -->

                                            <a
                                                href="toggle-status.php?id=<?= (int)(
                                                    $teacher[
                                                        'id'
                                                    ]
                                                ) ?>"
                                                class="teacher-action toggle"
                                                title="<?= $teacher[
                                                    'status'
                                                ] === 'Active'
                                                    ? 'Deactivate Teacher'
                                                    : 'Activate Teacher' ?>"
                                            >

                                                <i
                                                    class="fa-solid <?= $teacher[
                                                        'status'
                                                    ] === 'Active'
                                                        ? 'fa-toggle-on'
                                                        : 'fa-toggle-off' ?>"
                                                ></i>

                                            </a>


                                            <!-- DELETE -->

                                            <a
                                                href="delete.php?id=<?= (int)(
                                                    $teacher[
                                                        'id'
                                                    ]
                                                ) ?>"
                                                class="teacher-action delete"
                                                title="Delete Teacher"
                                                onclick="return confirm(
                                                    'Are you sure you want to delete this teacher?'
                                                );"
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
                                    colspan="8"
                                    class="teacher-empty"
                                >


                                    <i
                                        class="fa-solid fa-chalkboard-user"
                                    ></i>


                                    <strong>

                                        No teachers found.

                                    </strong>


                                    <div
                                        class="small mt-1"
                                    >

                                        <?php if (
                                            $search !== '' ||
                                            $status !== ''
                                        ): ?>

                                            Try changing your
                                            search or status filter.

                                        <?php else: ?>

                                            No teacher has been
                                            added yet.

                                        <?php endif; ?>

                                    </div>


                                </td>

                            </tr>


                        <?php endif; ?>


                        </tbody>


                    </table>


                </div>


            </section>


        </main>


    </div>


</div>


<?php

include "../includes/footer.php";

?>