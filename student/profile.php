<?php

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';

require_role('student');

$studentId = current_user_id();

$stmt = $conn->prepare(
    'SELECT
        id,
        student_code,
        full_name,
        email,
        mobile,
        gender,
        dob,
        address,
        city,
        state,
        pincode,
        profile_photo,
        email_verified,
        status,
        last_login,
        created_at
     FROM students
     WHERE id = ?
     LIMIT 1'
);

$stmt->execute([
    $studentId
]);

$student =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {

    clear_invalid_auth_session();

    header(
        'Location: ../auth/login.php'
    );

    exit;
}

$profileStats = [
    'attempted' => 0,
    'completed' => 0,
    'average_score' => 0.00,
    'best_score' => 0.00
];

try {

    $statsQuery =
        $conn->prepare(
            "SELECT
                COUNT(*) AS attempted,

                COALESCE(
                    SUM(
                        CASE
                            WHEN result_status IN ('Pass','Fail')
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS completed,

                COALESCE(
                    AVG(
                        CASE
                            WHEN result_status IN ('Pass','Fail')
                            THEN percentage
                        END
                    ),
                    0
                ) AS average_score,

                COALESCE(
                    MAX(
                        CASE
                            WHEN result_status IN ('Pass','Fail')
                            THEN percentage
                        END
                    ),
                    0
                ) AS best_score

            FROM results
            WHERE student_id = ?"
        );

    $statsQuery->execute([
        $studentId
    ]);

    $stats =
        $statsQuery->fetch(
            PDO::FETCH_ASSOC
        ) ?: [];

    $profileStats['attempted'] =
        (int)(
            $stats['attempted'] ?? 0
        );

    $profileStats['completed'] =
        (int)(
            $stats['completed'] ?? 0
        );

    $profileStats['average_score'] =
        round(
            (float)(
                $stats['average_score'] ?? 0
            ),
            2
        );

    $profileStats['best_score'] =
        round(
            (float)(
                $stats['best_score'] ?? 0
            ),
            2
        );

} catch (Throwable $e) {

    error_log(
        'Student profile stats query failed: ' .
        $e->getMessage()
    );

}

$subscription = null;

try {

    $subscriptionQuery =
        $conn->prepare(
            "SELECT
                sp.name,
                sp.duration_months,
                s.start_date,
                s.end_date,
                s.status

            FROM subscriptions s

            INNER JOIN subscription_plans sp
                ON sp.id = s.plan_id

            WHERE s.student_id = ?

            ORDER BY
                CASE
                    WHEN s.status = 'Active'
                    AND s.end_date >= CURDATE()
                    THEN 0
                    WHEN s.status = 'Active'
                    THEN 1
                    ELSE 2
                END,
                s.end_date DESC

            LIMIT 1"
        );

    $subscriptionQuery->execute([
        $studentId
    ]);

    $subscription =
        $subscriptionQuery->fetch(
            PDO::FETCH_ASSOC
        ) ?: null;

} catch (Throwable $e) {

    error_log(
        'Student subscription summary query failed: ' .
        $e->getMessage()
    );

}

$profilePhoto =
    '../assets/images/default-user.png';

if (!empty($student['profile_photo'])) {

    $photoFile =
        '../uploads/students/' .
        basename(
            (string)$student['profile_photo']
        );

    if (is_file($photoFile)) {

        $profilePhoto =
            $photoFile;

    }

}

function profile_e(mixed $value): string
{
    return htmlspecialchars(
        (string)(
            $value ?? ''
        ),
        ENT_QUOTES |
        ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$csrf =
    profile_e(
        csrf_token()
    );

$subscriptionLabel =
    'No active plan';

$subscriptionMeta =
    'Practice access is available according to your account eligibility.';

if ($subscription) {

    $isActive =
        ($subscription['status'] ?? '') === 'Active' &&
        !empty($subscription['end_date']) &&
        $subscription['end_date'] >= date('Y-m-d');

    $subscriptionLabel =
        $isActive
            ? (string)$subscription['name']
            : 'Expired plan';

    $subscriptionMeta =
        $isActive
            ? 'Valid until ' .
                date(
                    'd M Y',
                    strtotime(
                        (string)$subscription['end_date']
                    )
                )
            : 'Plan ended on ' .
                date(
                    'd M Y',
                    strtotime(
                        (string)$subscription['end_date']
                    )
                );

}

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
        name="csrf-token"
        content="<?= $csrf ?>"
    >

    <title>
        My Profile | ExamSphere
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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
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
        href="assets/css/profile.css"
    >

</head>

<body>

<?php include 'includes/navbar.php'; ?>

<main class="profile-page">

    <section class="profile-hero">

        <div class="hero-glow hero-glow-one"></div>
        <div class="hero-glow hero-glow-two"></div>

        <div class="profile-hero-inner">

            <div class="profile-identity">

                <div class="profile-avatar-wrap">

                    <img
                        id="profilePreview"
                        class="profile-avatar"
                        src="<?= profile_e($profilePhoto) ?>"
                        alt="Profile photo"
                    >

                    <label
                        class="upload-photo"
                        for="profilePhoto"
                        title="Change profile photo"
                    >

                        <i class="fa-solid fa-camera"></i>

                    </label>

                    <input
                        id="profilePhoto"
                        type="file"
                        accept="image/jpeg,image/png"
                        hidden
                    >

                </div>

                <div class="identity-copy">

                    <div class="identity-pill">

                        <i class="fa-solid fa-id-card"></i>

                        <?= profile_e(
                            $student['student_code']
                        ) ?>

                    </div>

                    <h1>
                        <?= profile_e(
                            $student['full_name']
                        ) ?>
                    </h1>

                    <p>

                        <i class="fa-regular fa-envelope"></i>

                        <?= profile_e(
                            $student['email']
                        ) ?>

                    </p>

                    <div class="identity-meta">

                        <span>

                            <i class="fa-solid fa-location-dot"></i>

                            <?= profile_e(
                                $student['city'] ?: 'City not added'
                            ) ?>

                        </span>

                        <span>

                            <i class="fa-solid fa-shield-halved"></i>

                            <?= $student['status'] === 'Active'
                                ? 'Active account'
                                : profile_e(
                                    $student['status']
                                ) ?>

                        </span>

                    </div>

                </div>

            </div>

            <div class="hero-actions">

                <button
                    type="button"
                    class="hero-btn edit-profile-btn"
                >

                    <i class="fa-solid fa-pen"></i>

                    <span>
                        Edit Profile
                    </span>

                </button>

                <a
                    class="hero-btn secondary"
                    href="dashboard.php"
                >

                    <i class="fa-solid fa-arrow-left"></i>

                    <span>
                        Dashboard
                    </span>

                </a>

            </div>

        </div>

    </section>

    <section class="stats-grid">

        <article class="stat-card">

            <div class="stat-icon brown">

                <i class="fa-solid fa-file-circle-check"></i>

            </div>

            <div>

                <strong>
                    <?= $profileStats['attempted'] ?>
                </strong>

                <span>
                    Exams Attempted
                </span>

            </div>

        </article>

        <article class="stat-card">

            <div class="stat-icon green">

                <i class="fa-solid fa-circle-check"></i>

            </div>

            <div>

                <strong>
                    <?= $profileStats['completed'] ?>
                </strong>

                <span>
                    Completed
                </span>

            </div>

        </article>

        <article class="stat-card">

            <div class="stat-icon gold">

                <i class="fa-solid fa-chart-line"></i>

            </div>

            <div>

                <strong>
                    <?= number_format(
                        $profileStats['average_score'],
                        2
                    ) ?>%
                </strong>

                <span>
                    Average Score
                </span>

            </div>

        </article>

        <article class="stat-card">

            <div class="stat-icon blue">

                <i class="fa-solid fa-ranking-star"></i>

            </div>

            <div>

                <strong>
                    <?= number_format(
                        $profileStats['best_score'],
                        2
                    ) ?>%
                </strong>

                <span>
                    Best Score
                </span>

            </div>

        </article>

    </section>

    <section class="profile-layout">

        <div class="main-column">

            <article
                class="profile-card"
                id="personalSection"
            >

                <div class="card-heading">

                    <div>

                        <span class="heading-icon">
                            <i class="fa-solid fa-user"></i>
                        </span>

                        <div>

                            <h2>
                                Personal Information
                            </h2>

                            <p>
                                Update the information
                                saved in your student account.
                            </p>

                        </div>

                    </div>

                    <span class="verified-badge">

                        <i class="fa-solid fa-circle-check"></i>

                        <?= $student['email_verified'] === 'Yes'
                            ? 'Email verified'
                            : 'Email pending' ?>

                    </span>

                </div>

                <form
                    id="profileForm"
                    novalidate
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= $csrf ?>"
                    >

                    <div class="form-grid">

                        <div class="form-field">

                            <label for="profile_full_name">
                                Full Name
                            </label>

                            <input
                                id="profile_full_name"
                                type="text"
                                name="full_name"
                                maxlength="100"
                                value="<?= profile_e(
                                    $student['full_name']
                                ) ?>"
                                required
                            >

                        </div>

                        <div class="form-field">

                            <label for="profile_student_code">
                                Student Code
                            </label>

                            <input
                                id="profile_student_code"
                                type="text"
                                value="<?= profile_e(
                                    $student['student_code']
                                ) ?>"
                                readonly
                            >

                        </div>

                        <div class="form-field">

                            <label for="profile_email">
                                Email Address
                            </label>

                            <input
                                id="profile_email"
                                type="email"
                                value="<?= profile_e(
                                    $student['email']
                                ) ?>"
                                readonly
                            >

                        </div>

                        <div class="form-field">

                            <label for="profile_mobile">
                                Mobile Number
                            </label>

                            <input
                                id="profile_mobile"
                                type="tel"
                                name="mobile"
                                inputmode="numeric"
                                maxlength="15"
                                value="<?= profile_e(
                                    $student['mobile']
                                ) ?>"
                                required
                            >

                        </div>

                        <div class="form-field">

                            <label for="profile_gender">
                                Gender
                            </label>

                            <select
                                id="profile_gender"
                                name="gender"
                            >

                                <option value="">
                                    Select gender
                                </option>

                                <option
                                    value="Male"
                                    <?= $student['gender'] === 'Male'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Male
                                </option>

                                <option
                                    value="Female"
                                    <?= $student['gender'] === 'Female'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Female
                                </option>

                                <option
                                    value="Other"
                                    <?= $student['gender'] === 'Other'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Other
                                </option>

                            </select>

                        </div>

                        <div class="form-field">

                            <label for="profile_dob">
                                Date of Birth
                            </label>

                            <input
                                id="profile_dob"
                                type="date"
                                name="dob"
                                max="<?= date('Y-m-d') ?>"
                                value="<?= profile_e(
                                    $student['dob']
                                ) ?>"
                            >

                        </div>

                        <div class="form-field full">

                            <label for="profile_address">
                                Address
                            </label>

                            <textarea
                                id="profile_address"
                                name="address"
                                maxlength="2000"
                                rows="4"
                            ><?= profile_e(
                                $student['address']
                            ) ?></textarea>

                        </div>

                        <div class="form-field">

                            <label for="profile_city">
                                City
                            </label>

                            <input
                                id="profile_city"
                                type="text"
                                name="city"
                                maxlength="80"
                                value="<?= profile_e(
                                    $student['city']
                                ) ?>"
                            >

                        </div>

                        <div class="form-field">

                            <label for="profile_state">
                                State
                            </label>

                            <input
                                id="profile_state"
                                type="text"
                                name="state"
                                maxlength="80"
                                value="<?= profile_e(
                                    $student['state']
                                ) ?>"
                            >

                        </div>

                        <div class="form-field">

                            <label for="profile_pincode">
                                Pincode
                            </label>

                            <input
                                id="profile_pincode"
                                type="text"
                                name="pincode"
                                inputmode="numeric"
                                maxlength="10"
                                value="<?= profile_e(
                                    $student['pincode']
                                ) ?>"
                            >

                        </div>

                    </div>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="primary-btn"
                        >

                            <i class="fa-solid fa-floppy-disk"></i>

                            <span>
                                Save Changes
                            </span>

                        </button>

                        <a
                            href="dashboard.php"
                            class="text-btn"
                        >
                            Cancel
                        </a>

                    </div>

                </form>

            </article>

            <article class="profile-card shortcut-card">

                <div class="card-heading">

                    <div>

                        <span class="heading-icon">
                            <i class="fa-solid fa-compass"></i>
                        </span>

                        <div>

                            <h2>
                                Student Portal
                            </h2>

                            <p>
                                Everything you commonly need
                                is one click away.
                            </p>

                        </div>

                    </div>

                </div>

                <div class="shortcut-grid">

                    <a href="practice_exams.php">

                        <i class="fa-solid fa-file-pen"></i>

                        <span>
                            <strong>
                                Practice Exams
                            </strong>

                            <small>
                                Start a practice test
                            </small>
                        </span>

                        <i class="fa-solid fa-arrow-up-right-from-square"></i>

                    </a>

                    <a href="my_exams.php">

                        <i class="fa-solid fa-clock-rotate-left"></i>

                        <span>
                            <strong>
                                My Exams
                            </strong>

                            <small>
                                Review attempted exams
                            </small>
                        </span>

                        <i class="fa-solid fa-arrow-up-right-from-square"></i>

                    </a>

                    <a href="results.php">

                        <i class="fa-solid fa-chart-column"></i>

                        <span>
                            <strong>
                                Results
                            </strong>

                            <small>
                                View your scores
                            </small>
                        </span>

                        <i class="fa-solid fa-arrow-up-right-from-square"></i>

                    </a>

                    <a href="performance.php">

                        <i class="fa-solid fa-gauge-high"></i>

                        <span>
                            <strong>
                                Performance
                            </strong>

                            <small>
                                Analyze your progress
                            </small>
                        </span>

                        <i class="fa-solid fa-arrow-up-right-from-square"></i>

                    </a>

                    <a href="live_exams.php">

                        <i class="fa-solid fa-tower-broadcast"></i>

                        <span>
                            <strong>
                                Live Exams
                            </strong>

                            <small>
                                See scheduled exams
                            </small>
                        </span>

                        <i class="fa-solid fa-arrow-up-right-from-square"></i>

                    </a>

                    <a href="materials.php">

                        <i class="fa-solid fa-book-open"></i>

                        <span>
                            <strong>
                                Materials
                            </strong>

                            <small>
                                Open study resources
                            </small>
                        </span>

                        <i class="fa-solid fa-arrow-up-right-from-square"></i>

                    </a>

                </div>

            </article>

        </div>

        <aside class="side-column">

            <article class="profile-card status-card">

                <div class="card-heading">

                    <div>

                        <span class="heading-icon">
                            <i class="fa-solid fa-user-shield"></i>
                        </span>

                        <div>

                            <h2>
                                Account Status
                            </h2>

                            <p>
                                Current account and
                                verification details.
                            </p>

                        </div>

                    </div>

                </div>

                <div class="status-list">

                    <div>

                        <span>
                            Student ID
                        </span>

                        <strong>
                            <?= profile_e(
                                $student['student_code']
                            ) ?>
                        </strong>

                    </div>

                    <div>

                        <span>
                            Account
                        </span>

                        <strong class="status-active">
                            <?= profile_e(
                                $student['status']
                            ) ?>
                        </strong>

                    </div>

                    <div>

                        <span>
                            Email
                        </span>

                        <strong>
                            <?= $student['email_verified'] === 'Yes'
                                ? 'Verified'
                                : 'Pending' ?>
                        </strong>

                    </div>

                    <div>

                        <span>
                            Last Login
                        </span>

                        <strong>

                            <?= !empty(
                                $student['last_login']
                            )
                                ? date(
                                    'd M Y, h:i A',
                                    strtotime(
                                        $student['last_login']
                                    )
                                )
                                : 'First Login' ?>

                        </strong>

                    </div>

                    <div>

                        <span>
                            Joined
                        </span>

                        <strong>

                            <?= !empty(
                                $student['created_at']
                            )
                                ? date(
                                    'd M Y',
                                    strtotime(
                                        $student['created_at']
                                    )
                                )
                                : '—' ?>

                        </strong>

                    </div>

                </div>

            </article>

            <article class="profile-card subscription-card">

                <div class="plan-top">

                    <span class="heading-icon">
                        <i class="fa-solid fa-gem"></i>
                    </span>

                    <span class="plan-tag">
                        SUBSCRIPTION
                    </span>

                </div>

                <h3>
                    <?= profile_e(
                        $subscriptionLabel
                    ) ?>
                </h3>

                <p>
                    <?= profile_e(
                        $subscriptionMeta
                    ) ?>
                </p>

                <a
                    href="subscriptions.php"
                    class="primary-btn full"
                >

                    <i class="fa-solid fa-arrow-right"></i>

                    Manage Plans

                </a>

            </article>

            <article class="profile-card security-card">

                <div class="card-heading">

                    <div>

                        <span class="heading-icon">

                            <i class="fa-solid fa-lock"></i>

                        </span>

                        <div>

                            <h2>
                                Security
                            </h2>

                            <p>
                                Keep your ExamSphere
                                account protected.
                            </p>

                        </div>

                    </div>

                </div>

                <button
                    type="button"
                    class="primary-btn full change-password-btn"
                >

                    <i class="fa-solid fa-key"></i>

                    Change Password

                </button>

                <p class="security-help">

                    <i class="fa-solid fa-circle-info"></i>

                    Use a strong password that
                    you do not reuse elsewhere.

                </p>

            </article>

        </aside>

    </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="assets/js/profile.js"></script>

</body>
</html>