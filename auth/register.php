<?php
require_once '../config/session.php';
require_once '../config/config.php';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    $destinations = ['student' => '../student/dashboard.php', 'teacher' => '../teacher/dashboard.php', 'admin' => '../admin/dashboard.php'];
    if (isset($destinations[$role])) {
        header('Location: ' . $destinations[$role]);
        exit;
    }
}

$error = $_SESSION['register_error'] ?? '';
$old = $_SESSION['register_old'] ?? [];
unset($_SESSION['register_error'], $_SESSION['register_old']);

function old_value($old, $key) {
    return htmlspecialchars($old[$key] ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | ExamSphere</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="stylesheet" href="../assets/css/register.css">
</head>
<body class="auth-page register-page">
<main class="auth-shell">
    <aside class="auth-showcase">
        <a class="auth-brand" href="../index.php">
            <img src="../assets/images/exam_logo.png" alt="ExamSphere logo">
            <span><b>Exam<span>Sphere</span></b><small>SMART ASSESSMENT, SEAMLESS LEARNING, REAL RESULTS.</small></span>
        </a>
        <div class="showcase-copy">
            <p class="eyebrow">START YOUR JOURNEY</p>
            <h1>Learn. Practice. <span>Achieve.</span></h1>
            <p>Create your free student account and take unlimited practice exams from one secure platform.</p>
        </div>
        <div class="benefit-list">
            <div><i class="fa-solid fa-pen-to-square"></i><span><b>Free Practice Exams</b><small>Improve with unlimited practice attempts.</small></span></div>
            <div><i class="fa-solid fa-bolt"></i><span><b>Instant Results</b><small>Get marks and feedback immediately.</small></span></div>
            <div><i class="fa-solid fa-book-open"></i><span><b>Study Materials</b><small>Unlock important learning resources.</small></span></div>
            <div><i class="fa-solid fa-shield-halved"></i><span><b>Safe Student Portal</b><small>Your information is protected with care.</small></span></div>
        </div>
        <a class="back-home" href="../index.php"><i class="fa-solid fa-arrow-left"></i> Back to home</a>
    </aside>

    <section class="auth-main">
        <div class="auth-card register-card">
            <div class="auth-card-logo"><img src="../assets/images/exam_logo.png" alt="ExamSphere"></div>
            <header class="auth-heading">
                <h2>Create your <span>account</span></h2>
                <p>Complete the form below to join ExamSphere.</p>
            </header>
            <?php if ($error): ?><div class="auth-alert error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

            <form
                id="registerForm"
                action="register_process.php"
                method="POST"
                autocomplete="off"
            >
    <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group"><label for="full_name">Full name</label><div class="input-box"><i class="fa-regular fa-user"></i><input id="full_name" type="text" name="full_name" placeholder="Your full name" value="<?= old_value($old, 'full_name') ?>" required></div></div>
                    <div class="form-group"><label for="email">Email address</label><div class="input-box"><i class="fa-regular fa-envelope"></i><input id="email" type="email" name="email" autocomplete="email" placeholder="you@example.com" value="<?= old_value($old, 'email') ?>" required></div></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="mobile">Mobile number</label><div class="input-box"><i class="fa-solid fa-phone"></i><input id="mobile" type="tel" name="mobile" pattern="[0-9]{10,15}" placeholder="10–15 digit number" value="<?= old_value($old, 'mobile') ?>" required></div></div>
                    <div class="form-group"><label for="gender">Gender</label><div class="input-box select-box"><i class="fa-solid fa-venus-mars"></i><select id="gender" name="gender" required><option value="" disabled <?= empty($old['gender']) ? 'selected' : '' ?>>Select gender</option><option value="Male" <?= ($old['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option><option value="Female" <?= ($old['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option><option value="Other" <?= ($old['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option></select></div></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="dob">Date of birth</label><div class="input-box"><i class="fa-regular fa-calendar"></i><input id="dob" type="date" name="dob" value="<?= old_value($old, 'dob') ?>" required></div></div>
                    <div class="form-group"><label for="city">City</label><div class="input-box"><i class="fa-solid fa-city"></i><input id="city" type="text" name="city" placeholder="Your city" value="<?= old_value($old, 'city') ?>" required></div></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="state">State</label><div class="input-box"><i class="fa-regular fa-map"></i><input id="state" type="text" name="state" placeholder="Your state" value="<?= old_value($old, 'state') ?>" required></div></div>
                    <div class="form-group"><label for="pincode">Pincode</label><div class="input-box"><i class="fa-solid fa-location-dot"></i><input id="pincode" type="text" name="pincode" pattern="[0-9]{4,10}" placeholder="Pincode" value="<?= old_value($old, 'pincode') ?>" required></div></div>
                </div>
                <div class="form-group"><label for="address">Address</label><div class="input-box textarea-box"><i class="fa-solid fa-location-dot"></i><textarea id="address" name="address" placeholder="Your full address" required><?= old_value($old, 'address') ?></textarea></div></div>
                <div class="form-row">
                    <div class="form-group"><label for="password">Password</label><div class="input-box"><i class="fa-solid fa-lock"></i><input id="password" type="password" name="password" autocomplete="new-password" placeholder="Minimum 6 characters" minlength="6" required><button class="toggle-password" type="button" data-target="password" aria-label="Show or hide password"><i class="fa-regular fa-eye"></i></button></div></div>
                    <div class="form-group"><label for="confirm_password">Confirm password</label><div class="input-box"><i class="fa-solid fa-lock"></i><input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" placeholder="Repeat your password" minlength="6" required><button class="toggle-password" type="button" data-target="confirm_password" aria-label="Show or hide password"><i class="fa-regular fa-eye"></i></button></div></div>
                </div>
                <button class="login-btn" type="submit"><i class="fa-solid fa-user-plus"></i> Create student account</button>
            </form>
            <p class="auth-switch">Already have an account? <a href="login.php">Login here</a></p>
            <p class="security-box"><i class="fa-solid fa-shield-halved"></i> Your registration is protected and secure.</p>
        </div>
    </section>
</main>
<script src="../assets/js/register.js"></script>
</body>
</html>
