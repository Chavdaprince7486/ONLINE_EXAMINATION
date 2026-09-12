<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';

require_role('student');

$studentId = current_user_id();

function profile_escape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function profile_date(?string $value, string $fallback = 'Not available'): string
{
    if (!$value) {
        return $fallback;
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d M Y', $timestamp)
        : $fallback;
}

function profile_datetime(?string $value, string $fallback = 'First login'): string
{
    if (!$value) {
        return $fallback;
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d M Y, h:i A', $timestamp)
        : $fallback;
}

$student = null;
$profileStats = [
    'attempted' => 0,
    'completed' => 0,
    'average_score' => 0.00,
    'best_score' => 0.00,
    'passed' => 0,
    'failed' => 0,
];
$recentResults = [];
$activeSubscription = null;

try {
    $studentStatement = $conn->prepare(
        "SELECT
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
           AND status = 'Active'
         LIMIT 1"
    );

    $studentStatement->execute([$studentId]);
    $student = $studentStatement->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        clear_invalid_auth_session();
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }

    $statsStatement = $conn->prepare(
        "SELECT
            (SELECT COUNT(*)
             FROM exam_attempts ea
             WHERE ea.student_id = ?) AS attempted,
            COUNT(*) AS completed,
            COALESCE(AVG(percentage), 0) AS average_score,
            COALESCE(MAX(percentage), 0) AS best_score,
            COALESCE(SUM(CASE WHEN result_status = 'Pass' THEN 1 ELSE 0 END), 0) AS passed,
            COALESCE(SUM(CASE WHEN result_status = 'Fail' THEN 1 ELSE 0 END), 0) AS failed
         FROM results
         WHERE student_id = ?"
    );
    $statsStatement->execute([$studentId, $studentId]);
    $stats = $statsStatement->fetch(PDO::FETCH_ASSOC);

    if (is_array($stats)) {
        $profileStats['attempted'] = (int) ($stats['attempted'] ?? 0);
        $profileStats['completed'] = (int) ($stats['completed'] ?? 0);
        $profileStats['average_score'] = round((float) ($stats['average_score'] ?? 0), 2);
        $profileStats['best_score'] = round((float) ($stats['best_score'] ?? 0), 2);
        $profileStats['passed'] = (int) ($stats['passed'] ?? 0);
        $profileStats['failed'] = (int) ($stats['failed'] ?? 0);
    }

    $resultsStatement = $conn->prepare(
        "SELECT
            r.id,
            r.exam_id,
            r.total_questions,
            r.attempted_questions,
            r.correct_answers,
            r.wrong_answers,
            r.unanswered_questions,
            r.total_marks,
            r.obtained_marks,
            r.percentage,
            r.grade,
            r.result_status,
            r.created_at,
            e.title AS exam_title,
            e.exam_type,
            sub.name AS subject_name
         FROM results r
         INNER JOIN exams e ON e.id = r.exam_id
         LEFT JOIN subjects sub ON sub.id = e.subject_id
         WHERE r.student_id = ?
         ORDER BY r.created_at DESC, r.id DESC
         LIMIT 5"
    );
    $resultsStatement->execute([$studentId]);
    $recentResults = $resultsStatement->fetchAll(PDO::FETCH_ASSOC);

    $subscriptionStatement = $conn->prepare(
        "SELECT
            s.id,
            s.start_date,
            s.end_date,
            s.status,
            p.name AS plan_name,
            p.duration_months,
            p.price
         FROM subscriptions s
         INNER JOIN subscription_plans p ON p.id = s.plan_id
         WHERE s.student_id = ?
           AND s.status = 'Active'
           AND s.start_date <= CURDATE()
           AND s.end_date >= CURDATE()
         ORDER BY s.end_date DESC, s.id DESC
         LIMIT 1"
    );
    $subscriptionStatement->execute([$studentId]);
    $activeSubscription = $subscriptionStatement->fetch(PDO::FETCH_ASSOC) ?: null;

} catch (Throwable $exception) {
    error_log('Student profile load failed: ' . $exception->getMessage());

    if (!$student) {
        http_response_code(500);
        exit('Unable to load your profile right now.');
    }
}

$profilePhoto = '../assets/images/default-user.png';

if (
    !empty($student['profile_photo']) &&
    file_exists(__DIR__ . '/../uploads/students/' . basename((string) $student['profile_photo']))
) {
    $profilePhoto = '../uploads/students/' . rawurlencode(basename((string) $student['profile_photo']));
}

$studentCode = (string) ($student['student_code'] ?? 'Student');
$emailVerified = (string) ($student['email_verified'] ?? 'No') === 'Yes';
$subscriptionDays = 0;

if ($activeSubscription) {
    $endTimestamp = strtotime((string) $activeSubscription['end_date']);
    if ($endTimestamp !== false) {
        $subscriptionDays = max(
            0,
            (int) ceil(($endTimestamp - strtotime(date('Y-m-d'))) / 86400) + 1
        );
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= profile_escape(csrf_token()) ?>">
    <meta name="description" content="Manage your ExamSphere student profile, performance, security and account information.">
    <title>My Profile | ExamSphere</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/profile.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main class="main-wrapper">
    <div class="profile-page">

        <section class="profile-banner">
            <div class="banner-overlay"></div>
            <div class="banner-orb banner-orb-one"></div>
            <div class="banner-orb banner-orb-two"></div>

            <div class="profile-header">
                <div class="profile-left">
                    <div class="profile-image">
                        <img src="<?= profile_escape($profilePhoto) ?>" id="profilePreview" alt="Profile photo of <?= profile_escape($student['full_name']) ?>">
                        <label for="profilePhoto" class="upload-photo" title="Change profile photo">
                            <i class="fa-solid fa-camera"></i>
                            <span class="sr-only">Change profile photo</span>
                        </label>
                        <input type="file" id="profilePhoto" name="profile_photo" accept="image/jpeg,image/png" hidden>
                    </div>

                    <div class="profile-user">
                        <span class="profile-kicker"><i class="fa-solid fa-circle-check"></i> Student account</span>
                        <h1><?= profile_escape($student['full_name']) ?></h1>
                        <span class="student-id"><?= profile_escape($studentCode) ?></span>
                        <div class="profile-meta">
                            <span><i class="fa-solid fa-envelope"></i><?= profile_escape($student['email']) ?></span>
                            <span><i class="fa-solid fa-phone"></i><?= profile_escape($student['mobile']) ?></span>
                            <?php if (!empty($student['city']) || !empty($student['state'])): ?>
                                <span><i class="fa-solid fa-location-dot"></i><?= profile_escape(trim((string) $student['city'] . ', ' . (string) $student['state'], ' ,')) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="profile-actions">
                    <button type="button" class="edit-profile-btn" data-scroll-to="personal-panel"><i class="fa-solid fa-pen"></i> Edit Profile</button>
                </div>
            </div>

            <div class="banner-footer">
                <span><i class="fa-solid fa-shield-halved"></i> Secure student profile</span>
                <span><i class="fa-regular fa-calendar"></i> Joined <?= profile_escape(profile_date((string) $student['created_at'])) ?></span>
                <?php if ($activeSubscription): ?>
                    <span><i class="fa-solid fa-gem"></i> <?= profile_escape($activeSubscription['plan_name']) ?> · <?= $subscriptionDays ?> day<?= $subscriptionDays === 1 ? '' : 's' ?> left</span>
                <?php endif; ?>
            </div>
        </section>

        <section class="stats-section" aria-label="Profile performance summary">
            <article class="stat-card"><div class="stat-icon brown"><i class="fa-solid fa-file-circle-check"></i></div><div class="stat-content"><h2><?= $profileStats['attempted'] ?></h2><p>Exams Attempted</p><small>Recorded attempts</small></div></article>
            <article class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div><div class="stat-content"><h2><?= $profileStats['completed'] ?></h2><p>Completed</p><small><?= $profileStats['passed'] ?> passed · <?= $profileStats['failed'] ?> failed</small></div></article>
            <article class="stat-card"><div class="stat-icon gold"><i class="fa-solid fa-chart-line"></i></div><div class="stat-content"><h2><?= number_format($profileStats['average_score'], 2) ?>%</h2><p>Average Score</p><small>Across completed results</small></div></article>
            <article class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-ranking-star"></i></div><div class="stat-content"><h2><?= number_format($profileStats['best_score'], 2) ?>%</h2><p>Best Score</p><small>Highest recorded result</small></div></article>
        </section>

        <nav class="profile-tabs" aria-label="Profile sections">
            <button type="button" class="tab-btn active" data-tab="personal-panel"><i class="fa-solid fa-user"></i>Personal</button>
            <button type="button" class="tab-btn" data-tab="academic-panel"><i class="fa-solid fa-graduation-cap"></i>Academic</button>
            <button type="button" class="tab-btn" data-tab="performance-panel"><i class="fa-solid fa-chart-column"></i>Performance</button>
            <button type="button" class="tab-btn" data-tab="security-panel"><i class="fa-solid fa-lock"></i>Security</button>
            <button type="button" class="tab-btn" data-tab="settings-panel"><i class="fa-solid fa-gear"></i>Settings</button>
        </nav>

        <section id="personal-panel" class="profile-tab-panel active-panel">
            <div class="profile-content">
                <div class="left-panel">
                    <div class="content-card profile-form-card">
                        <div class="section-heading"><div><span class="section-kicker">PROFILE DETAILS</span><h2>Personal Information</h2><p>Update the personal and contact information stored on your student account.</p></div><span class="heading-icon"><i class="fa-solid fa-user"></i></span></div>
                        <form id="profileForm" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= profile_escape(csrf_token()) ?>">
                            <div class="form-grid">
                                <div class="form-group"><label for="fullName">Full Name <span>*</span></label><div class="field-shell"><i class="fa-solid fa-user"></i><input id="fullName" type="text" name="full_name" maxlength="100" autocomplete="name" value="<?= profile_escape($student['full_name']) ?>" required></div></div>
                                <div class="form-group"><label for="studentCode">Student Code</label><div class="field-shell readonly-field"><i class="fa-solid fa-id-card"></i><input id="studentCode" type="text" value="<?= profile_escape($studentCode) ?>" readonly></div></div>
                                <div class="form-group"><label for="email">Email</label><div class="field-shell readonly-field"><i class="fa-solid fa-envelope"></i><input id="email" type="email" value="<?= profile_escape($student['email']) ?>" readonly></div></div>
                                <div class="form-group"><label for="mobile">Mobile <span>*</span></label><div class="field-shell"><i class="fa-solid fa-phone"></i><input id="mobile" type="tel" name="mobile" inputmode="numeric" maxlength="20" autocomplete="tel" value="<?= profile_escape($student['mobile']) ?>" required></div></div>
                                <div class="form-group"><label for="gender">Gender</label><div class="field-shell"><i class="fa-solid fa-venus-mars"></i><select id="gender" name="gender"><option value="">Select</option><option value="Male" <?= $student['gender'] === 'Male' ? 'selected' : '' ?>>Male</option><option value="Female" <?= $student['gender'] === 'Female' ? 'selected' : '' ?>>Female</option><option value="Other" <?= $student['gender'] === 'Other' ? 'selected' : '' ?>>Other</option></select></div></div>
                                <div class="form-group"><label for="dob">Date of Birth</label><div class="field-shell"><i class="fa-solid fa-calendar-days"></i><input id="dob" type="date" name="dob" value="<?= profile_escape((string) $student['dob']) ?>"></div></div>
                                <div class="form-group full-width"><label for="address">Address</label><div class="field-shell textarea-shell"><i class="fa-solid fa-location-dot"></i><textarea id="address" name="address" rows="4" maxlength="2000"><?= profile_escape((string) $student['address']) ?></textarea></div></div>
                                <div class="form-group"><label for="city">City</label><div class="field-shell"><i class="fa-solid fa-city"></i><input id="city" type="text" name="city" maxlength="80" autocomplete="address-level2" value="<?= profile_escape((string) $student['city']) ?>"></div></div>
                                <div class="form-group"><label for="state">State</label><div class="field-shell"><i class="fa-solid fa-map-location-dot"></i><input id="state" type="text" name="state" maxlength="80" autocomplete="address-level1" value="<?= profile_escape((string) $student['state']) ?>"></div></div>
                                <div class="form-group"><label for="pincode">Pincode</label><div class="field-shell"><i class="fa-solid fa-location-crosshairs"></i><input id="pincode" type="text" name="pincode" inputmode="numeric" maxlength="10" autocomplete="postal-code" value="<?= profile_escape((string) $student['pincode']) ?>"></div></div>
                            </div>
                            <div class="form-actions"><button type="submit" class="primary-btn" id="saveProfileButton"><i class="fa-solid fa-floppy-disk"></i>Save Changes</button><span class="form-hint"><i class="fa-solid fa-circle-info"></i> Email and student code cannot be changed here.</span></div>
                        </form>
                    </div>
                </div>

                <aside class="right-panel">
                    <div class="content-card info-card">
                        <div class="section-heading compact-heading"><div><span class="section-kicker">ACCOUNT</span><h2>Account Status</h2></div><span class="heading-icon"><i class="fa-solid fa-user-shield"></i></span></div>
                        <div class="status-list">
                            <div class="status-item"><span>Student ID</span><strong><?= profile_escape($studentCode) ?></strong></div>
                            <div class="status-item"><span>Account</span><strong class="status-success"><i class="fa-solid fa-circle-check"></i><?= profile_escape($student['status']) ?></strong></div>
                            <div class="status-item"><span>Email</span><strong class="<?= $emailVerified ? 'status-success' : 'status-warning' ?>"><i class="fa-solid <?= $emailVerified ? 'fa-circle-check' : 'fa-clock' ?>"></i><?= $emailVerified ? 'Verified' : 'Pending' ?></strong></div>
                            <div class="status-item"><span>Last Login</span><strong><?= profile_escape(profile_datetime($student['last_login'])) ?></strong></div>
                            <div class="status-item"><span>Member Since</span><strong><?= profile_escape(profile_date($student['created_at'])) ?></strong></div>
                        </div>
                    </div>
                    <div class="content-card info-card subscription-card">
                        <div class="section-heading compact-heading"><div><span class="section-kicker">MEMBERSHIP</span><h2>Subscription</h2></div><span class="heading-icon"><i class="fa-solid fa-gem"></i></span></div>
                        <?php if ($activeSubscription): ?>
                            <div class="subscription-active"><span class="subscription-badge"><i class="fa-solid fa-circle-check"></i> Active</span><h3><?= profile_escape($activeSubscription['plan_name']) ?></h3><p>Valid through <?= profile_escape(profile_date($activeSubscription['end_date'])) ?></p><div class="subscription-progress"><span style="width:<?= max(5, min(100, ($subscriptionDays / max(1, ((int) $activeSubscription['duration_months'] * 31))) * 100)) ?>%"></span></div><small><?= $subscriptionDays ?> day<?= $subscriptionDays === 1 ? '' : 's' ?> remaining</small></div>
                        <?php else: ?>
                            <div class="subscription-empty"><i class="fa-solid fa-gem"></i><h3>No active subscription</h3><p>Explore available plans to unlock subscription-based features.</p><a href="subscriptions.php" class="secondary-btn">View Plans <i class="fa-solid fa-arrow-right"></i></a></div>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </section>

        <section id="academic-panel" class="profile-tab-panel">
            <div class="profile-content single-column"><div class="content-card"><div class="section-heading"><div><span class="section-kicker">ACADEMIC PROFILE</span><h2>Learning Account</h2><p>Your current student record and learning context.</p></div><span class="heading-icon"><i class="fa-solid fa-graduation-cap"></i></span></div><div class="academic-grid"><article><span>Student Code</span><strong><?= profile_escape($studentCode) ?></strong><small>Unique identifier for your account</small></article><article><span>Registered Email</span><strong><?= profile_escape($student['email']) ?></strong><small><?= $emailVerified ? 'Email verification completed' : 'Email verification pending' ?></small></article><article><span>Primary Location</span><strong><?= profile_escape(trim((string) $student['city'] . ', ' . (string) $student['state'], ' ,') ?: 'Not provided') ?></strong><small>Stored in your profile</small></article><article><span>Profile Status</span><strong><?= profile_escape($student['status']) ?></strong><small>Access is controlled by your account status</small></article></div><div class="schema-note"><i class="fa-solid fa-circle-info"></i><div><strong>Academic fields</strong><p>The current student database record does not contain a separate institution, course or qualification field, so this profile does not invent or store those values.</p></div></div></div></div>
        </section>

        <section id="performance-panel" class="profile-tab-panel">
            <div class="performance-summary-grid"><article class="performance-summary-card"><span>Passed</span><strong><?= $profileStats['passed'] ?></strong><small>Completed results</small></article><article class="performance-summary-card"><span>Failed</span><strong><?= $profileStats['failed'] ?></strong><small>Completed results</small></article><article class="performance-summary-card"><span>Average</span><strong><?= number_format($profileStats['average_score'], 2) ?>%</strong><small>All recorded results</small></article><article class="performance-summary-card"><span>Best</span><strong><?= number_format($profileStats['best_score'], 2) ?>%</strong><small>Highest percentage</small></article></div>
            <div class="content-card results-card"><div class="section-heading"><div><span class="section-kicker">RECENT RESULTS</span><h2>Performance History</h2><p>Your five most recent completed results.</p></div><a href="results.php" class="secondary-btn">All Results <i class="fa-solid fa-arrow-right"></i></a></div>
                <?php if ($recentResults): ?><div class="result-list"><?php foreach ($recentResults as $result): ?><article class="result-row"><div class="result-leading"><span class="result-icon"><i class="fa-solid fa-file-lines"></i></span><div><h3><?= profile_escape($result['exam_title']) ?></h3><p><?= profile_escape($result['subject_name'] ?: 'General') ?> · <?= profile_escape($result['exam_type']) ?></p></div></div><div class="result-score"><strong><?= number_format((float) $result['percentage'], 2) ?>%</strong><span class="result-status <?= $result['result_status'] === 'Pass' ? 'pass' : 'fail' ?>"><?= profile_escape($result['result_status']) ?></span><small><?= profile_escape(profile_date((string) $result['created_at'])) ?></small></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><i class="fa-solid fa-chart-column"></i><h3>No results yet</h3><p>Complete an exam to see your performance history here.</p><a href="practice_exams.php" class="secondary-btn">Start Practice <i class="fa-solid fa-arrow-right"></i></a></div><?php endif; ?>
            </div>
        </section>

        <section id="security-panel" class="profile-tab-panel">
            <div class="profile-content single-column"><div class="content-card security-card"><div class="section-heading"><div><span class="section-kicker">ACCOUNT SECURITY</span><h2>Password & Security</h2><p>Protect your account with a strong password and secure session controls.</p></div><span class="heading-icon"><i class="fa-solid fa-shield-halved"></i></span></div><div class="security-grid"><article class="security-item"><span class="security-icon green"><i class="fa-solid fa-key"></i></span><div><h3>Password</h3><p>Your password is stored as a secure hash and can be changed at any time.</p></div><button type="button" class="primary-btn compact-btn change-password-trigger"><i class="fa-solid fa-key"></i>Change Password</button></article><article class="security-item"><span class="security-icon blue"><i class="fa-solid fa-lock"></i></span><div><h3>Session Protection</h3><p>Your account uses server-side authentication, secure session cookies and CSRF protection.</p></div><span class="security-state"><i class="fa-solid fa-circle-check"></i>Protected</span></article><article class="security-item"><span class="security-icon gold"><i class="fa-solid fa-envelope-circle-check"></i></span><div><h3>Email Verification</h3><p><?= $emailVerified ? 'Your registered email address is verified.' : 'Your registered email address is not yet verified.' ?></p></div><span class="security-state <?= $emailVerified ? '' : 'warning-state' ?>"><i class="fa-solid <?= $emailVerified ? 'fa-circle-check' : 'fa-clock' ?>"></i><?= $emailVerified ? 'Verified' : 'Pending' ?></span></article></div></div></div>
        </section>

        <section id="settings-panel" class="profile-tab-panel">
            <div class="profile-content"><div class="left-panel"><div class="content-card"><div class="section-heading"><div><span class="section-kicker">ACCOUNT SETTINGS</span><h2>Profile Preferences</h2><p>Manage available profile actions and account shortcuts.</p></div><span class="heading-icon"><i class="fa-solid fa-gear"></i></span></div><div class="settings-list"><div class="setting-row"><div><span class="setting-icon"><i class="fa-solid fa-user-pen"></i></span><div><h3>Personal details</h3><p>Edit your name, mobile number and location information.</p></div></div><button type="button" class="secondary-btn setting-action" data-scroll-to="personal-panel">Open <i class="fa-solid fa-arrow-right"></i></button></div><div class="setting-row"><div><span class="setting-icon"><i class="fa-solid fa-image"></i></span><div><h3>Profile photo</h3><p>Upload a JPG or PNG image up to 2 MB.</p></div></div><button type="button" class="secondary-btn setting-action" data-photo-trigger="true">Change <i class="fa-solid fa-camera"></i></button></div><div class="setting-row"><div><span class="setting-icon"><i class="fa-solid fa-lock"></i></span><div><h3>Password</h3><p>Change your password using the secure account flow.</p></div></div><button type="button" class="secondary-btn setting-action change-password-trigger">Change <i class="fa-solid fa-arrow-right"></i></button></div><div class="setting-row"><div><span class="setting-icon"><i class="fa-solid fa-right-from-bracket"></i></span><div><h3>Logout</h3><p>End your current ExamSphere session securely.</p></div></div><a href="../auth/logout.php" class="danger-btn">Logout <i class="fa-solid fa-arrow-right"></i></a></div></div></div></div><aside class="right-panel"><div class="content-card account-summary-card"><div class="section-heading compact-heading"><div><span class="section-kicker">SUMMARY</span><h2>Your Account</h2></div><span class="heading-icon"><i class="fa-solid fa-address-card"></i></span></div><div class="account-summary-list"><div><span>Account status</span><strong class="status-success"><?= profile_escape($student['status']) ?></strong></div><div><span>Email status</span><strong class="<?= $emailVerified ? 'status-success' : 'status-warning' ?>"><?= $emailVerified ? 'Verified' : 'Pending' ?></strong></div><div><span>Results</span><strong><?= $profileStats['completed'] ?></strong></div><div><span>Best score</span><strong><?= number_format($profileStats['best_score'], 2) ?>%</strong></div></div></div></aside></div>
        </section>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/profile.js"></script>
</body>
</html>
