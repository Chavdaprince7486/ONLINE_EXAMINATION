<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: ../../auth/login.php');
    exit;
}

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $id === false ||
    $id === null ||
    $id <= 0
) {
    $_SESSION['error'] = 'Invalid teacher.';
    header('Location: index.php');
    exit;
}

try {

    $stmt = $conn->prepare("
        SELECT
            id,
            teacher_code,
            full_name,
            email,
            phone,
            mobile,
            gender,
            dob,
            qualification,
            experience,
            address,
            profile_photo,
            status,
            last_login,
            created_at
        FROM teachers
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id
    ]);

    $teacher = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (
        !$teacher
    ) {
        $_SESSION['error'] =
            'Teacher not found.';

        header(
            'Location: index.php'
        );

        exit;
    }

} catch (
    Throwable $exception
) {

    error_log(
        'Teacher view failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to load teacher details.';

    header(
        'Location: index.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function teacher_view_e(
    mixed $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function teacher_view_date(
    ?string $value
): string {

    if (
        !$value
    ) {
        return 'Not provided';
    }

    $timestamp =
        strtotime(
            $value
        );

    if (
        $timestamp === false
    ) {
        return 'Not provided';
    }

    return date(
        'd M Y',
        $timestamp
    );
}


function teacher_view_datetime(
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
| PROFILE DATA
|--------------------------------------------------------------------------
*/

$name =
    trim(
        (string)(
            $teacher['full_name']
            ?? ''
        )
    );

$initial =
    function_exists(
        'mb_substr'
    )
    ? mb_substr(
        $name,
        0,
        1,
        'UTF-8'
    )
    : substr(
        $name,
        0,
        1
    );

$profilePhoto =
    trim(
        (string)(
            $teacher['profile_photo']
            ?? ''
        )
    );

$profilePhotoUrl = '';

if (
    $profilePhoto !== ''
) {

    $profilePhotoUrl =
        '../../uploads/teachers/' .
        rawurlencode(
            basename(
                $profilePhoto
            )
        );
}


$page_title =
    'View Teacher';


include "../includes/header.php";

?>

<style>

    .teacher-view-page {
        max-width: 1180px;
        margin: 0 auto;
    }

    .teacher-view-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
    }

    .teacher-view-heading .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #556b2f;
        font-size: .76rem;
        font-weight: 800;
        letter-spacing: .12em;
        margin-bottom: 8px;
    }

    .teacher-view-heading h1 {
        margin: 0;
    }

    .teacher-view-heading p {
        margin: 6px 0 0;
        color: #746d68;
    }

    .teacher-back {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 42px;
        padding: 0 15px;
        border-radius: 12px;
        border: 1px solid rgba(93,64,55,.12);
        background: rgba(255,255,255,.88);
        color: #5d4037;
        text-decoration: none;
        font-weight: 800;
        white-space: nowrap;
        transition: .2s ease;
    }

    .teacher-back:hover {
        background: #5d4037;
        border-color: #5d4037;
        color: #fff;
        transform: translateY(-2px);
    }

    .teacher-view-layout {
        display: grid;
        grid-template-columns: 330px minmax(0, 1fr);
        gap: 20px;
    }

    .teacher-profile-card,
    .teacher-details-card {
        border-radius: 24px;
        border: 1px solid rgba(93,64,55,.10);
        background: rgba(255,255,255,.89);
        box-shadow: 0 20px 55px rgba(51,51,51,.08);
        backdrop-filter: blur(15px);
    }

    .teacher-profile-card {
        padding: 28px 23px;
        text-align: center;
    }

    .teacher-profile-photo,
    .teacher-profile-initial {
        width: 112px;
        height: 112px;
        margin: 0 auto 17px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: linear-gradient(
            135deg,
            #5d4037,
            #806154
        );
        color: #fff;
        font-size: 2.6rem;
        font-weight: 800;
        box-shadow: 0 15px 35px rgba(93,64,55,.20);
    }

    .teacher-profile-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .teacher-profile-card h2 {
        margin: 0;
        color: #333;
        font-size: 1.45rem;
    }

    .teacher-code {
        display: inline-flex;
        margin-top: 8px;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(93,64,55,.08);
        color: #5d4037;
        font-size: .77rem;
        font-weight: 800;
    }

    .teacher-profile-email {
        margin-top: 12px;
        color: #746d68;
        font-size: .87rem;
        word-break: break-word;
    }

    .teacher-profile-status {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 17px;
    }

    .teacher-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 11px;
        border-radius: 999px;
        font-size: .75rem;
        font-weight: 800;
    }

    .teacher-status.active {
        color: #556b2f;
        background: rgba(85,107,47,.11);
    }

    .teacher-status.inactive {
        color: #96352f;
        background: rgba(163,58,50,.10);
    }

    .teacher-profile-actions {
        display: flex;
        gap: 8px;
        margin-top: 22px;
    }

    .teacher-delete-btn {
        width: 100%;
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border-radius: 11px;
        text-decoration: none;
        font-size: .82rem;
        font-weight: 800;
        transition: .2s ease;
        color: #9b3831;
        background: rgba(163,58,50,.08);
    }

    .teacher-delete-btn:hover {
        background: #a33a32;
        color: #fff;
        transform: translateY(-2px);
    }

    .teacher-details-card {
        overflow: hidden;
    }

    .teacher-details-head {
        padding: 21px 23px;
        border-bottom: 1px solid rgba(93,64,55,.08);
        background: rgba(250,247,240,.64);
    }

    .teacher-details-head span {
        display: block;
        margin-bottom: 4px;
        color: #556b2f;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .10em;
    }

    .teacher-details-head h3 {
        margin: 0;
        color: #333;
        font-size: 1.05rem;
    }

    .teacher-details-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .teacher-detail-item {
        padding: 19px 22px;
        border-bottom: 1px solid rgba(93,64,55,.07);
    }

    .teacher-detail-item:nth-child(odd) {
        border-right: 1px solid rgba(93,64,55,.07);
    }

    .teacher-detail-item small {
        display: block;
        margin-bottom: 5px;
        color: #7c746f;
        font-size: .73rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .teacher-detail-item strong {
        display: block;
        color: #373330;
        line-height: 1.5;
        word-break: break-word;
    }

    .teacher-address {
        white-space: normal;
        line-height: 1.6;
    }

    @media (max-width: 900px) {

        .teacher-view-layout {
            grid-template-columns: 1fr;
        }

        .teacher-profile-card {
            text-align: left;
        }

        .teacher-profile-photo,
        .teacher-profile-initial {
            margin-left: 0;
        }

        .teacher-profile-status {
            justify-content: flex-start;
        }

    }

    @media (max-width: 650px) {

        .teacher-view-heading {
            align-items: flex-start;
            flex-direction: column;
        }

        .teacher-back {
            width: 100%;
        }

        .teacher-details-grid {
            grid-template-columns: 1fr;
        }

        .teacher-detail-item:nth-child(odd) {
            border-right: 0;
        }

    }

</style>


<div class="dashboard-wrapper">


    <?php include "../includes/sidebar.php"; ?>


    <div class="main-content">


        <?php include "../includes/navbar.php"; ?>


        <main
            class="dashboard-content teacher-view-page"
        >


            <section
                class="teacher-view-heading"
            >

                <div>

                    <span
                        class="eyebrow"
                    >

                        <i
                            class="fa-solid fa-chalkboard-user"
                        ></i>

                        TEACHER MANAGEMENT

                    </span>


                    <h1>
                        Teacher Details
                    </h1>


                    <p>
                        Complete teacher profile and account information.
                    </p>

                </div>


                <a
                    href="index.php"
                    class="teacher-back"
                >

                    <i
                        class="fa-solid fa-arrow-left"
                    ></i>

                    Back to Teachers

                </a>

            </section>


            <div
                class="teacher-view-layout"
            >


                <aside
                    class="teacher-profile-card"
                >


                    <?php if (
                        $profilePhotoUrl !== ''
                    ): ?>


                        <div
                            class="teacher-profile-photo"
                        >

                            <img
                                src="<?= teacher_view_e(
                                    $profilePhotoUrl
                                ) ?>"
                                alt="<?= teacher_view_e(
                                    $name
                                ) ?>"
                            >

                        </div>


                    <?php else: ?>


                        <div
                            class="teacher-profile-initial"
                        >

                            <?= teacher_view_e(
                                $initial
                            ) ?>

                        </div>


                    <?php endif; ?>


                    <h2>

                        <?= teacher_view_e(
                            $name
                        ) ?>

                    </h2>


                    <div
                        class="teacher-code"
                    >

                        <?= teacher_view_e(
                            $teacher['teacher_code']
                        ) ?>

                    </div>


                    <div
                        class="teacher-profile-email"
                    >

                        <i
                            class="fa-regular fa-envelope me-1"
                        ></i>

                        <?= teacher_view_e(
                            $teacher['email']
                        ) ?>

                    </div>


                    <div
                        class="teacher-profile-status"
                    >


                        <?php if (
                            $teacher['status']
                            ===
                            'Active'
                        ): ?>


                            <span
                                class="teacher-status active"
                            >

                                <i
                                    class="fa-solid fa-circle-check"
                                ></i>

                                Active

                            </span>


                        <?php else: ?>


                            <span
                                class="teacher-status inactive"
                            >

                                <i
                                    class="fa-solid fa-circle-xmark"
                                ></i>

                                Inactive

                            </span>


                        <?php endif; ?>


                    </div>


                    <div
                        class="teacher-profile-actions"
                    >

                        <a
                            href="delete.php?id=<?= (int)$teacher['id'] ?>"
                            class="teacher-delete-btn"
                            title="Delete Teacher"
                            onclick="return confirm(
                                'Are you sure you want to delete this teacher? This action cannot be undone.'
                            );"
                        >

                            <i
                                class="fa-solid fa-trash"
                            ></i>

                            Delete

                        </a>

                    </div>


                </aside>


                <section
                    class="teacher-details-card"
                >


                    <header
                        class="teacher-details-head"
                    >

                        <span>
                            PROFILE INFORMATION
                        </span>


                        <h3>
                            Teacher Account & Professional Details
                        </h3>

                    </header>


                    <div
                        class="teacher-details-grid"
                    >


                        <div
                            class="teacher-detail-item"
                        >

                            <small>
                                Teacher Code
                            </small>

                            <strong>

                                <?= teacher_view_e(
                                    $teacher[
                                        'teacher_code'
                                    ]
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="teacher-detail-item"
                        >

                            <small>
                                Full Name
                            </small>

                            <strong>

                                <?= teacher_view_e(
                                    $teacher[
                                        'full_name'
                                    ]
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="teacher-detail-item"
                        >

                            <small>
                                Email
                            </small>

                            <strong>

                                <?= teacher_view_e(
                                    $teacher[
                                        'email'
                                    ]
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="teacher-detail-item"
                        >

                            <small>
                                Phone
                            </small>

                            <strong>

                                <?= teacher_view_e(
                                    $teacher[
                                        'phone'
                                    ]
                                    ?:
                                    'Not provided'
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="teacher-detail-item"
                        >

                            <small>
                                Mobile
                            </small>

                            <strong>

                                <?= teacher_view_e(
                                    $teacher[
                                        'mobile'
                                    ]
                                    ?:
                                    'Not provided'
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="teacher-detail-item"
                        >

                            <small>
                                Gender
                            </small>

                            <strong>

                                <?= teacher_view_e(
                                    $teacher[
                                        'gender'
                                    ]
                                    ?:
                                    'Not provided'
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="teacher-detail-item"
                        >

                            <small>
                                Date of Birth
                            </small>

                            <strong>

                                <?= teacher_view_e(
                                    teacher_view_date(
                                        $teacher[
                                            'dob'
                                        ]
                                    )
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="teacher-detail-item"
                        >

                            <small>
                                Qualification
                            </small>

                            <strong>

                                <?= teacher_view_e(
                                    $teacher[
                                        'qualification'
                                    ]
                                    ?:
                                    'Not provided'
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="teacher-detail-item"
                        >

                            <small>
                                Experience
                            </small>

                            <strong>

                                <?= teacher_view_e(
                                    $teacher[
                                        'experience'
                                    ]
                                    ?:
                                    'Not provided'
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="teacher-detail-item"
                        >

                            <small>
                                Account Status
                            </small>

                            <strong>

                                <?= teacher_view_e(
                                    $teacher[
                                        'status'
                                    ]
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="teacher-detail-item"
                        >

                            <small>
                                Last Login
                            </small>

                            <strong>

                                <?= teacher_view_e(
                                    teacher_view_datetime(
                                        $teacher[
                                            'last_login'
                                        ]
                                    )
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="teacher-detail-item"
                        >

                            <small>
                                Registered On
                            </small>

                            <strong>

                                <?= teacher_view_e(
                                    teacher_view_datetime(
                                        $teacher[
                                            'created_at'
                                        ]
                                    )
                                ) ?>

                            </strong>

                        </div>


                        <div
                            class="teacher-detail-item"
                            style="
                                grid-column: 1 / -1;
                                border-right: 0;
                            "
                        >

                            <small>
                                Address
                            </small>


                            <strong
                                class="teacher-address"
                            >


                                <?php if (
                                    trim(
                                        (string)(
                                            $teacher[
                                                'address'
                                            ]
                                        )
                                    ) !== ''
                                ): ?>


                                    <?= nl2br(
                                        teacher_view_e(
                                            $teacher[
                                                'address'
                                            ]
                                        )
                                    ) ?>


                                <?php else: ?>


                                    Not provided


                                <?php endif; ?>


                            </strong>

                        </div>


                    </div>


                </section>


            </div>


        </main>


    </div>


</div>


<?php

include "../includes/footer.php";

?>