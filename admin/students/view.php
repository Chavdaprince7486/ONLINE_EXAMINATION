<?php
declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header("Location: ../../auth/login.php");
    exit;
}

if (!isset($_GET['id']) || !ctype_digit((string)$_GET['id'])) {
    header("Location: index.php");
    exit;
}

$studentId = (int)$_GET['id'];

try {

    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.student_code,
            s.full_name,
            s.email,
            s.mobile,
            s.gender,
            s.dob,
            s.city,
            s.state,
            s.email_verified,
            s.status,
            s.last_login,
            s.created_at,

            (
                SELECT p.name
                FROM subscriptions sub
                INNER JOIN subscription_plans p
                    ON p.id = sub.plan_id
                WHERE sub.student_id = s.id
                  AND sub.status = 'Active'
                  AND sub.start_date <= CURDATE()
                  AND sub.end_date >= CURDATE()
                ORDER BY sub.end_date DESC, sub.id DESC
                LIMIT 1
            ) AS active_plan,

            (
                SELECT sub.start_date
                FROM subscriptions sub
                WHERE sub.student_id = s.id
                  AND sub.status = 'Active'
                  AND sub.start_date <= CURDATE()
                  AND sub.end_date >= CURDATE()
                ORDER BY sub.end_date DESC, sub.id DESC
                LIMIT 1
            ) AS active_plan_start,

            (
                SELECT sub.end_date
                FROM subscriptions sub
                WHERE sub.student_id = s.id
                  AND sub.status = 'Active'
                  AND sub.start_date <= CURDATE()
                  AND sub.end_date >= CURDATE()
                ORDER BY sub.end_date DESC, sub.id DESC
                LIMIT 1
            ) AS active_plan_end

        FROM students s
        WHERE s.id = ?
        LIMIT 1
    ");

    $stmt->execute([$studentId]);

    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        header("Location: index.php");
        exit;
    }

} catch (Throwable $e) {

    error_log($e->getMessage());

    header("Location: index.php");
    exit;
}

function view_student_e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function view_student_date(?string $value): string
{
    if (!$value) {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d M Y', $timestamp)
        : '—';
}

function view_student_datetime(?string $value): string
{
    if (!$value) {
        return 'Never';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d M Y, h:i A', $timestamp)
        : '—';
}

$name = trim((string)$student['full_name']);

$initial = function_exists('mb_substr')
    ? mb_substr($name, 0, 1, 'UTF-8')
    : substr($name, 0, 1);

$page_title = "View Student | ExamSphere";

include "../includes/header.php";
?>

<style>

    .student-view-page {
        max-width: 1180px;
        margin: 0 auto;
    }

    .student-view-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 18px;
        margin-bottom: 24px;
    }

    .student-view-top .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #556b2f;
        font-size: .77rem;
        font-weight: 800;
        letter-spacing: .12em;
        margin-bottom: 7px;
    }

    .student-view-top h1 {
        margin: 0;
    }

    .student-view-top p {
        margin: 6px 0 0;
        color: #746c67;
    }

    .back-student-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 15px;
        border-radius: 12px;
        border: 1px solid rgba(93,64,55,.12);
        background: rgba(255,255,255,.85);
        color: #5d4037;
        text-decoration: none;
        font-weight: 700;
        transition: .2s ease;
        white-space: nowrap;
    }

    .back-student-btn:hover {
        transform: translateY(-2px);
        background: #5d4037;
        border-color: #5d4037;
        color: #fff;
    }

    .student-profile-layout {
        display: grid;
        grid-template-columns: 330px minmax(0, 1fr);
        gap: 20px;
    }

    .student-profile-card,
    .student-details-card,
    .student-membership-card {
        border-radius: 24px;
        border: 1px solid rgba(93,64,55,.10);
        background: rgba(255,255,255,.88);
        box-shadow: 0 20px 55px rgba(51,51,51,.08);
        backdrop-filter: blur(15px);
    }

    .student-profile-card {
        padding: 28px 22px;
        text-align: center;
    }

    .student-profile-avatar {
        width: 100px;
        height: 100px;
        margin: 0 auto 17px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: linear-gradient(
            135deg,
            #5d4037,
            #806154
        );
        color: #fff;
        font-size: 2.4rem;
        font-weight: 800;
        box-shadow: 0 15px 35px rgba(93,64,55,.20);
    }

    .student-profile-card h2 {
        margin: 0;
        font-size: 1.45rem;
        color: #333;
    }

    .student-code {
        display: inline-flex;
        margin-top: 7px;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(93,64,55,.08);
        color: #5d4037;
        font-size: .78rem;
        font-weight: 800;
    }

    .student-email {
        margin-top: 12px;
        color: #756e69;
        word-break: break-word;
        font-size: .88rem;
    }

    .student-status-row {
        display: flex;
        justify-content: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 17px;
    }

    .student-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 11px;
        border-radius: 999px;
        font-size: .75rem;
        font-weight: 800;
    }

    .student-badge.active {
        background: rgba(85,107,47,.11);
        color: #556b2f;
    }

    .student-badge.inactive {
        background: rgba(163,58,50,.10);
        color: #96352f;
    }

    .student-badge.verified {
        background: rgba(44,116,82,.10);
        color: #2d7552;
    }

    .student-badge.pending {
        background: rgba(154,107,22,.10);
        color: #8d6618;
    }

    .student-profile-action {
        display: flex;
        gap: 8px;
        margin-top: 22px;
    }

    .student-profile-action a {
        flex: 1;
        min-height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border-radius: 11px;
        text-decoration: none;
        font-size: .83rem;
        font-weight: 800;
        transition: .2s ease;
    }



    .student-delete-btn {
        background: rgba(163,58,50,.08);
        color: #9b3831;
    }

    .student-delete-btn:hover {
        background: #a33a32;
        color: #fff;
        transform: translateY(-2px);
    }

    .student-right-column {
        display: grid;
        gap: 20px;
    }

    .student-details-card,
    .student-membership-card {
        overflow: hidden;
    }

    .student-card-header {
        padding: 20px 22px;
        border-bottom: 1px solid rgba(93,64,55,.08);
        background: rgba(250,247,240,.55);
    }

    .student-card-header span {
        display: block;
        color: #556b2f;
        font-size: .73rem;
        letter-spacing: .10em;
        font-weight: 800;
        margin-bottom: 4px;
    }

    .student-card-header h3 {
        margin: 0;
        font-size: 1.03rem;
        color: #333;
    }

    .student-details-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .student-detail-item {
        padding: 19px 22px;
        border-bottom: 1px solid rgba(93,64,55,.07);
    }

    .student-detail-item:nth-child(odd) {
        border-right: 1px solid rgba(93,64,55,.07);
    }

    .student-detail-item:last-child,
    .student-detail-item:nth-last-child(2) {
        border-bottom: 0;
    }

    .student-detail-item small {
        display: block;
        color: #7c746f;
        margin-bottom: 5px;
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .student-detail-item strong {
        display: block;
        color: #383330;
        word-break: break-word;
    }

    .membership-content {
        padding: 22px;
    }

    .membership-active {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 18px;
        border-radius: 18px;
        background: rgba(85,107,47,.08);
        border: 1px solid rgba(85,107,47,.13);
    }

    .membership-active .plan-icon {
        width: 46px;
        height: 46px;
        border-radius: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #556b2f;
        color: #fff;
        font-size: 1rem;
        flex: 0 0 46px;
    }

    .membership-plan-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .membership-plan-info small {
        display: block;
        color: #70706b;
        margin-bottom: 3px;
    }

    .membership-plan-info strong {
        color: #3f4e23;
        font-size: 1rem;
    }

    .membership-duration {
        text-align: right;
        color: #556b2f;
        font-size: .83rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .no-membership {
        padding: 20px;
        border-radius: 17px;
        background: #faf8f4;
        border: 1px dashed rgba(93,64,55,.17);
        color: #756e69;
        text-align: center;
    }

    .no-membership i {
        display: block;
        margin-bottom: 8px;
        color: #8e857f;
        font-size: 1.5rem;
    }

    @media (max-width: 900px) {

        .student-profile-layout {
            grid-template-columns: 1fr;
        }

        .student-profile-card {
            text-align: left;
        }

        .student-profile-avatar {
            margin-left: 0;
        }

        .student-status-row {
            justify-content: flex-start;
        }

    }

    @media (max-width: 650px) {

        .student-view-top {
            align-items: flex-start;
            flex-direction: column;
        }

        .student-details-grid {
            grid-template-columns: 1fr;
        }

        .student-detail-item:nth-child(odd) {
            border-right: 0;
        }

        .student-detail-item:last-child,
        .student-detail-item:nth-last-child(2) {
            border-bottom: 1px solid rgba(93,64,55,.07);
        }

        .student-detail-item:last-child {
            border-bottom: 0;
        }

        .membership-active {
            align-items: flex-start;
            flex-direction: column;
        }

        .membership-duration {
            text-align: left;
        }

    }

</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content student-view-page">

            <section class="student-view-top">

                <div>

                    <span class="eyebrow">
                        <i class="fa-solid fa-user"></i>
                        STUDENT PROFILE
                    </span>

                    <h1>Student Details</h1>

                    <p>
                        Complete account information and current membership
                        status.
                    </p>

                </div>

                <a
                    href="index.php"
                    class="back-student-btn"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Students
                </a>

            </section>

            <div class="student-profile-layout">

                <aside class="student-profile-card">

                    <div class="student-profile-avatar">
                        <?= view_student_e($initial) ?>
                    </div>

                    <h2>
                        <?= view_student_e($student['full_name']) ?>
                    </h2>

                    <div class="student-code">
                        <?= view_student_e($student['student_code']) ?>
                    </div>

                    <div class="student-email">
                        <i class="fa-regular fa-envelope me-1"></i>
                        <?= view_student_e($student['email']) ?>
                    </div>

                    <div class="student-status-row">

                        <?php if ($student['status'] === 'Active'): ?>

                            <span class="student-badge active">
                                <i class="fa-solid fa-circle-check"></i>
                                Active
                            </span>

                        <?php else: ?>

                            <span class="student-badge inactive">
                                <i class="fa-solid fa-circle-xmark"></i>
                                Inactive
                            </span>

                        <?php endif; ?>

                        <?php if ($student['email_verified'] === 'Yes'): ?>

                            <span class="student-badge verified">
                                <i class="fa-solid fa-envelope-circle-check"></i>
                                Verified
                            </span>

                        <?php else: ?>

                            <span class="student-badge pending">
                                <i class="fa-solid fa-envelope"></i>
                                Unverified
                            </span>

                        <?php endif; ?>

                    </div>

                    <div class="student-profile-action">

                        
                        <a
                            href="delete.php?id=<?= (int)$student['id'] ?>"
                            class="student-delete-btn"
                            onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.');"
                        >
                            <i class="fa-solid fa-trash"></i>
                            Delete
                        </a>

                    </div>

                </aside>

                <div class="student-right-column">

                    <section class="student-details-card">

                        <header class="student-card-header">

                            <span>ACCOUNT INFORMATION</span>

                            <h3>Personal & Contact Details</h3>

                        </header>

                        <div class="student-details-grid">

                            <div class="student-detail-item">

                                <small>Full Name</small>

                                <strong>
                                    <?= view_student_e(
                                        $student['full_name']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="student-detail-item">

                                <small>Student Code</small>

                                <strong>
                                    <?= view_student_e(
                                        $student['student_code']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="student-detail-item">

                                <small>Email Address</small>

                                <strong>
                                    <?= view_student_e(
                                        $student['email']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="student-detail-item">

                                <small>Mobile Number</small>

                                <strong>
                                    <?= view_student_e(
                                        $student['mobile']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="student-detail-item">

                                <small>Gender</small>

                                <strong>
                                    <?= view_student_e(
                                        $student['gender']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="student-detail-item">

                                <small>Date of Birth</small>

                                <strong>
                                    <?= view_student_date(
                                        $student['dob']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="student-detail-item">

                                <small>City</small>

                                <strong>
                                    <?= view_student_e(
                                        $student['city']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="student-detail-item">

                                <small>State</small>

                                <strong>
                                    <?= view_student_e(
                                        $student['state']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="student-detail-item">

                                <small>Account Status</small>

                                <strong>
                                    <?= view_student_e(
                                        $student['status']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="student-detail-item">

                                <small>Email Verification</small>

                                <strong>
                                    <?= view_student_e(
                                        $student['email_verified']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="student-detail-item">

                                <small>Registered On</small>

                                <strong>
                                    <?= view_student_date(
                                        $student['created_at']
                                    ) ?>
                                </strong>

                            </div>

                            <div class="student-detail-item">

                                <small>Last Login</small>

                                <strong>
                                    <?= view_student_datetime(
                                        $student['last_login']
                                    ) ?>
                                </strong>

                            </div>

                        </div>

                    </section>

                    <section class="student-membership-card">

                        <header class="student-card-header">

                            <span>SUBSCRIPTION ACCESS</span>

                            <h3>Current Membership</h3>

                        </header>

                        <div class="membership-content">

                            <?php if (!empty($student['active_plan'])): ?>

                                <div class="membership-active">

                                    <div class="membership-plan-info">

                                        <div class="plan-icon">
                                            <i class="fa-solid fa-gem"></i>
                                        </div>

                                        <div>

                                            <small>Active plan</small>

                                            <strong>
                                                <?= view_student_e(
                                                    $student['active_plan']
                                                ) ?>
                                            </strong>

                                        </div>

                                    </div>

                                    <div class="membership-duration">

                                        <div>
                                            <?= view_student_date(
                                                $student['active_plan_start']
                                            ) ?>
                                            –
                                            <?= view_student_date(
                                                $student['active_plan_end']
                                            ) ?>
                                        </div>

                                        <small>
                                            Active access period
                                        </small>

                                    </div>

                                </div>

                            <?php else: ?>

                                <div class="no-membership">

                                    <i class="fa-regular fa-gem"></i>

                                    <strong>
                                        No active subscription
                                    </strong>

                                    <div class="small mt-1">
                                        This student currently has no active
                                        membership access.
                                    </div>

                                </div>

                            <?php endif; ?>

                        </div>

                    </section>

                </div>

            </div>

        </main>

    </div>

</div>

<?php include "../includes/footer.php"; ?>