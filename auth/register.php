<?php
require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';

if (isset($_SESSION['user_id'])) {
    $role = normalize_user_role($_SESSION['user_role'] ?? '');
    $destinations = [
        'student' => '../student/dashboard.php',
        'teacher' => '../teacher/dashboard.php',
        'admin' => '../admin/dashboard.php'
    ];
    if (isset($destinations[$role])) {
        header('Location: ' . $destinations[$role]);
        exit;
    }
}

$error = (string)($_SESSION['register_error'] ?? '');
$old = is_array($_SESSION['register_old'] ?? null) ? $_SESSION['register_old'] : [];
unset($_SESSION['register_error'], $_SESSION['register_old']);

function register_old_value(array $old, string $key): string
{
    return htmlspecialchars((string)($old[$key] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$minimumDob = date('Y-m-d', strtotime('-5 years'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <title>Create Student Account | ExamSphere</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="stylesheet" href="../assets/css/register.css">
</head>

<body class="auth-page register-page">

<main class="auth-shell">

    <aside class="auth-showcase">

        <a class="auth-brand" href="../index.php">

            <img src="../assets/images/exam_logo.png"
                 alt="ExamSphere logo">

            <span>
                <b>Exam<span>Sphere</span></b>
                <small>
                    SMART ASSESSMENT, SEAMLESS LEARNING, REAL RESULTS.
                </small>
            </span>

        </a>

        <div class="showcase-copy">

            <p class="eyebrow">
                START YOUR JOURNEY
            </p>

            <h1>
                Learn. Practice.
                <span>Achieve.</span>
            </h1>

            <p>
                Create your secure student account and access
                your complete ExamSphere learning journey.
            </p>

        </div>

        <div class="benefit-list">

            <div>
                <i class="fa-solid fa-pen-to-square"></i>

                <span>
                    <b>Practice Exams</b>
                    <small>
                        Prepare with structured online practice tests.
                    </small>
                </span>
            </div>

            <div>
                <i class="fa-solid fa-chart-line"></i>

                <span>
                    <b>Performance Tracking</b>
                    <small>
                        Monitor results and improve your preparation.
                    </small>
                </span>
            </div>

            <div>
                <i class="fa-solid fa-book-open"></i>

                <span>
                    <b>Study Materials</b>
                    <small>
                        Access available learning resources in one place.
                    </small>
                </span>
            </div>

            <div>
                <i class="fa-solid fa-shield-halved"></i>

                <span>
                    <b>Verified Account</b>
                    <small>
                        Email verification keeps your account protected.
                    </small>
                </span>
            </div>

        </div>

        <a class="back-home" href="../index.php">
            <i class="fa-solid fa-arrow-left"></i>
            Back to home
        </a>

    </aside>

    <section class="auth-main">

        <div class="auth-card register-card">

            <div class="auth-card-logo">
                <img src="../assets/images/exam_logo.png"
                     alt="ExamSphere">
            </div>

            <header class="auth-heading">

                <div class="register-badge">
                    <i class="fa-solid fa-user-graduate"></i>
                    Student Registration
                </div>

                <h2>
                    Create your
                    <span>account</span>
                </h2>

                <p>
                    Enter your details carefully.
                    After submission, a verification OTP
                    will be sent to your email.
                </p>

            </header>

            <?php if ($error !== ''): ?>

                <div class="auth-alert error" role="alert">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <span>
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </span>

                </div>

            <?php endif; ?>

            <form
                id="registerForm"
                action="register_process.php"
                method="POST"
                autocomplete="off"
                novalidate
            >

                <?= csrf_field() ?>

                <div class="form-section-title">
                    <span>01</span>
                    <div>
                        <strong>Personal details</strong>
                        <small>Tell us about yourself</small>
                    </div>
                </div>

                <div class="form-row">

                    <div class="form-group">

                        <label for="full_name">
                            Full name <span>*</span>
                        </label>

                        <div class="input-box">

                            <i class="fa-regular fa-user"></i>

                            <input
                                id="full_name"
                                type="text"
                                name="full_name"
                                maxlength="100"
                                autocomplete="name"
                                placeholder="Enter your full name"
                                value="<?= register_old_value(
                                    $old,
                                    'full_name'
                                ) ?>"
                                required
                            >

                        </div>

                        <small class="field-hint">
                            Use your real name as it should appear
                            on your student account.
                        </small>

                    </div>

                    <div class="form-group">

                        <label for="email">
                            Email address <span>*</span>
                        </label>

                        <div class="input-box">

                            <i class="fa-regular fa-envelope"></i>

                            <input
                                id="email"
                                type="email"
                                name="email"
                                maxlength="150"
                                autocomplete="email"
                                placeholder="you@example.com"
                                value="<?= register_old_value(
                                    $old,
                                    'email'
                                ) ?>"
                                required
                            >

                        </div>

                        <small class="field-hint">
                            You will receive the verification OTP
                            at this address.
                        </small>

                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group">

                        <label for="mobile">
                            Mobile number <span>*</span>
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-phone"></i>

                            <input
                                id="mobile"
                                type="tel"
                                name="mobile"
                                inputmode="numeric"
                                maxlength="15"
                                pattern="[0-9]{10,15}"
                                autocomplete="tel"
                                placeholder="10–15 digit number"
                                value="<?= register_old_value(
                                    $old,
                                    'mobile'
                                ) ?>"
                                required
                            >

                        </div>

                    </div>

                    <div class="form-group">

                        <label for="gender">
                            Gender <span>*</span>
                        </label>

                        <div class="input-box select-box">

                            <i class="fa-solid fa-venus-mars"></i>

                            <select
                                id="gender"
                                name="gender"
                                required
                            >

                                <option
                                    value=""
                                    disabled
                                    <?= empty($old['gender'])
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Select gender
                                </option>

                                <option
                                    value="Male"
                                    <?= (($old['gender'] ?? '') === 'Male')
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Male
                                </option>

                                <option
                                    value="Female"
                                    <?= (($old['gender'] ?? '') === 'Female')
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Female
                                </option>

                                <option
                                    value="Other"
                                    <?= (($old['gender'] ?? '') === 'Other')
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Other
                                </option>

                            </select>

                        </div>

                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group">

                        <label for="dob">
                            Date of birth <span>*</span>
                        </label>

                        <div class="input-box">

                            <i class="fa-regular fa-calendar"></i>

                            <input
                                id="dob"
                                type="date"
                                name="dob"
                                max="<?= $minimumDob ?>"
                                value="<?= register_old_value(
                                    $old,
                                    'dob'
                                ) ?>"
                                required
                            >

                        </div>

                    </div>

                    <div class="form-group">

                        <label for="pincode">
                            Pincode <span>*</span>
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-location-dot"></i>

                            <input
                                id="pincode"
                                type="text"
                                name="pincode"
                                inputmode="numeric"
                                maxlength="10"
                                pattern="[0-9]{4,10}"
                                autocomplete="postal-code"
                                placeholder="Enter pincode"
                                value="<?= register_old_value(
                                    $old,
                                    'pincode'
                                ) ?>"
                                required
                            >

                        </div>

                    </div>

                </div>

                <div class="form-section-title">

                    <span>02</span>

                    <div>
                        <strong>Location details</strong>
                        <small>Your current address</small>
                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group">

                        <label for="city">
                            City <span>*</span>
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-city"></i>

                            <input
                                id="city"
                                type="text"
                                name="city"
                                maxlength="80"
                                placeholder="Enter your city"
                                value="<?= register_old_value(
                                    $old,
                                    'city'
                                ) ?>"
                                required
                            >

                        </div>

                    </div>

                    <div class="form-group">

                        <label for="state">
                            State <span>*</span>
                        </label>

                        <div class="input-box">

                            <i class="fa-regular fa-map"></i>

                            <input
                                id="state"
                                type="text"
                                name="state"
                                maxlength="80"
                                placeholder="Enter your state"
                                value="<?= register_old_value(
                                    $old,
                                    'state'
                                ) ?>"
                                required
                            >

                        </div>

                    </div>

                </div>

                <div class="form-group">

                    <label for="address">
                        Full address <span>*</span>
                    </label>

                    <div class="input-box textarea-box">

                        <i class="fa-solid fa-location-dot"></i>

                        <textarea
                            id="address"
                            name="address"
                            maxlength="2000"
                            placeholder="Enter your full address"
                            required
                        ><?= register_old_value(
                            $old,
                            'address'
                        ) ?></textarea>

                    </div>

                </div>

                <div class="form-section-title">

                    <span>03</span>

                    <div>
                        <strong>Account security</strong>
                        <small>Create your login password</small>
                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group">

                        <label for="password">
                            Password <span>*</span>
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-lock"></i>

                            <input
                                id="password"
                                type="password"
                                name="password"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                placeholder="Minimum 8 characters"
                                required
                            >

                            <button
                                class="toggle-password"
                                type="button"
                                data-target="password"
                                aria-label="Show or hide password"
                            >
                                <i class="fa-regular fa-eye"></i>
                            </button>

                        </div>

                        <div
                            class="password-strength"
                            aria-live="polite"
                        >

                            <span class="strength-bar">
                                <i></i>
                            </span>

                            <span class="strength-text">
                                Use letters and numbers.
                            </span>

                        </div>

                    </div>

                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm password <span>*</span>
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-lock"></i>

                            <input
                                id="confirm_password"
                                type="password"
                                name="confirm_password"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                placeholder="Repeat your password"
                                required
                            >

                            <button
                                class="toggle-password"
                                type="button"
                                data-target="confirm_password"
                                aria-label="Show or hide password"
                            >
                                <i class="fa-regular fa-eye"></i>
                            </button>

                        </div>

                        <small
                            id="passwordMatchHint"
                            class="field-hint"
                        >
                            Passwords must match.
                        </small>

                    </div>

                </div>

                <div class="verification-note">

                    <i class="fa-solid fa-circle-info"></i>

                    <div>

                        <strong>
                            Email verification is required
                        </strong>

                        <span>
                            After you submit this form,
                            ExamSphere will send a 6-digit OTP
                            valid for 5 minutes.
                        </span>

                    </div>

                </div>

                <button
                    id="registerSubmit"
                    class="login-btn"
                    type="submit"
                >

                    <i class="fa-solid fa-user-plus"></i>

                    <span>
                        Create student account
                    </span>

                </button>

            </form>

            <p class="auth-switch">

                Already have an account?

                <a href="login.php">
                    Login here
                </a>

            </p>

            <p class="security-box">

                <i class="fa-solid fa-shield-halved"></i>

                Registration data is validated
                server-side and your password is securely hashed.

            </p>

        </div>

    </section>

</main>

<script src="../assets/js/register.js"></script>

</body>
</html>