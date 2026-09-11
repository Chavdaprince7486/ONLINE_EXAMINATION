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

$page_title = 'Add Teacher';

$error = '';

$teacherCode = '';
$fullName = '';
$email = '';
$password = '';
$phone = '';
$mobile = '';
$gender = '';
$dob = '';
$qualification = '';
$experience = '';
$address = '';
$status = 'Active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $error =
            'Security verification failed. Please refresh the page and try again.';

    } else {

        $teacherCode = trim((string)($_POST['teacher_code'] ?? ''));
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $phone = trim((string)($_POST['phone'] ?? ''));
        $mobile = trim((string)($_POST['mobile'] ?? ''));
        $gender = trim((string)($_POST['gender'] ?? ''));
        $dob = trim((string)($_POST['dob'] ?? ''));
        $qualification = trim((string)($_POST['qualification'] ?? ''));
        $experience = trim((string)($_POST['experience'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'Active'));

        if ($teacherCode === '') {

            $error = 'Teacher code is required.';

        } elseif (mb_strlen($teacherCode) > 30) {

            $error = 'Teacher code cannot exceed 30 characters.';

        } elseif ($fullName === '') {

            $error = 'Full name is required.';

        } elseif (mb_strlen($fullName) > 100) {

            $error = 'Full name cannot exceed 100 characters.';

        } elseif ($email === '') {

            $error = 'Email address is required.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = 'Please enter a valid email address.';

        } elseif (mb_strlen($email) > 150) {

            $error = 'Email address cannot exceed 150 characters.';

        } elseif ($password === '') {

            $error = 'Password is required.';

        } elseif (strlen($password) < 8) {

            $error = 'Password must contain at least 8 characters.';

        } elseif (strlen($password) > 255) {

            $error = 'Password is too long.';

        } elseif (
            $phone !== '' &&
            mb_strlen($phone) > 20
        ) {

            $error = 'Phone number cannot exceed 20 characters.';

        } elseif (
            $mobile !== '' &&
            mb_strlen($mobile) > 20
        ) {

            $error = 'Mobile number cannot exceed 20 characters.';

        } elseif (
            $gender !== '' &&
            !in_array(
                $gender,
                ['Male', 'Female', 'Other'],
                true
            )
        ) {

            $error = 'Invalid gender selected.';

        } elseif (
            $dob !== '' &&
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)
        ) {

            $error = 'Please enter a valid date of birth.';

        } elseif (
            $qualification !== '' &&
            mb_strlen($qualification) > 255
        ) {

            $error = 'Qualification cannot exceed 255 characters.';

        } elseif (
            $experience !== '' &&
            mb_strlen($experience) > 100
        ) {

            $error = 'Experience cannot exceed 100 characters.';

        } elseif (
            $status !== '' &&
            !in_array(
                $status,
                ['Active', 'Inactive'],
                true
            )
        ) {

            $error = 'Invalid account status selected.';
        }

        if (
            $error === '' &&
            $dob !== ''
        ) {

            $dobDate = DateTime::createFromFormat(
                'Y-m-d',
                $dob
            );

            if (
                !$dobDate ||
                $dobDate->format('Y-m-d') !== $dob
            ) {

                $error = 'Please enter a valid date of birth.';

            } elseif (
                $dobDate > new DateTime('today')
            ) {

                $error = 'Date of birth cannot be in the future.';
            }
        }

        if ($error === '') {

            try {

                $check = $conn->prepare("
                    SELECT
                        teacher_code,
                        email
                    FROM teachers
                    WHERE teacher_code = ?
                       OR email = ?
                    LIMIT 1
                ");

                $check->execute([
                    $teacherCode,
                    $email
                ]);

                $existing = $check->fetch(PDO::FETCH_ASSOC);

                if ($existing) {

                    if (
                        isset($existing['teacher_code']) &&
                        (string)$existing['teacher_code'] === $teacherCode
                    ) {

                        $error =
                            'This teacher code is already registered.';

                    } else {

                        $error =
                            'This email address is already registered.';
                    }
                }

            } catch (Throwable $exception) {

                error_log(
                    'Teacher duplicate check failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to validate the teacher details right now.';
            }
        }

        $uploadedFilename = '';

        if (
            $error === '' &&
            isset($_FILES['profile_photo']) &&
            is_array($_FILES['profile_photo']) &&
            ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE)
                !== UPLOAD_ERR_NO_FILE
        ) {

            $fileError = (int)(
                $_FILES['profile_photo']['error']
                ?? UPLOAD_ERR_NO_FILE
            );

            if ($fileError !== UPLOAD_ERR_OK) {

                $error =
                    'Unable to upload the profile photo.';

            } else {

                $tmpName = (string)(
                    $_FILES['profile_photo']['tmp_name']
                    ?? ''
                );

                $originalName = (string)(
                    $_FILES['profile_photo']['name']
                    ?? ''
                );

                $fileSize = (int)(
                    $_FILES['profile_photo']['size']
                    ?? 0
                );

                if (
                    $tmpName === '' ||
                    !is_uploaded_file($tmpName)
                ) {

                    $error =
                        'Invalid profile photo upload.';

                } elseif ($fileSize <= 0) {

                    $error =
                        'The uploaded profile photo is empty.';

                } elseif ($fileSize > 5 * 1024 * 1024) {

                    $error =
                        'Profile photo must be 5 MB or smaller.';

                } else {

                    $imageInfo = @getimagesize($tmpName);

                    if ($imageInfo === false) {

                        $error =
                            'Profile photo must be a valid image.';

                    } else {

                        $mime = (string)(
                            $imageInfo['mime'] ?? ''
                        );

                        $allowedMimes = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/webp' => 'webp'
                        ];

                        if (
                            !isset($allowedMimes[$mime])
                        ) {

                            $error =
                                'Only JPG, PNG and WebP profile photos are allowed.';

                        } else {

                            $uploadDirectory =
                                dirname(__DIR__, 2) .
                                '/uploads/teachers';

                            if (
                                !is_dir($uploadDirectory) &&
                                !mkdir(
                                    $uploadDirectory,
                                    0755,
                                    true
                                )
                            ) {

                                $error =
                                    'Unable to prepare the teacher upload directory.';

                            } else {

                                try {

                                    $uploadedFilename =
                                        time() .
                                        '_' .
                                        bin2hex(
                                            random_bytes(5)
                                        ) .
                                        '.' .
                                        $allowedMimes[$mime];

                                    $destination =
                                        $uploadDirectory .
                                        '/' .
                                        $uploadedFilename;

                                    if (
                                        !move_uploaded_file(
                                            $tmpName,
                                            $destination
                                        )
                                    ) {

                                        $uploadedFilename = '';

                                        $error =
                                            'Unable to save the profile photo.';
                                    }

                                } catch (Throwable $exception) {

                                    error_log(
                                        'Teacher photo upload failed: ' .
                                        $exception->getMessage()
                                    );

                                    $uploadedFilename = '';

                                    $error =
                                        'Unable to process the profile photo.';
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($error === '') {

            try {

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($hashedPassword === false) {
                    throw new RuntimeException(
                        'Password hashing failed.'
                    );
                }

                $stmt = $conn->prepare("
                    INSERT INTO teachers
                    (
                        teacher_code,
                        full_name,
                        email,
                        password,
                        phone,
                        mobile,
                        gender,
                        dob,
                        qualification,
                        experience,
                        address,
                        profile_photo,
                        status
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )
                ");

                $stmt->execute([
                    $teacherCode,
                    $fullName,
                    $email,
                    $hashedPassword,
                    $phone !== '' ? $phone : null,
                    $mobile !== '' ? $mobile : null,
                    $gender !== '' ? $gender : null,
                    $dob !== '' ? $dob : null,
                    $qualification !== ''
                        ? $qualification
                        : null,
                    $experience !== ''
                        ? $experience
                        : null,
                    $address !== ''
                        ? $address
                        : null,
                    $uploadedFilename !== ''
                        ? $uploadedFilename
                        : null,
                    $status
                ]);

                $_SESSION['success'] =
                    'Teacher "' .
                    $fullName .
                    '" added successfully.';

                header('Location: index.php');
                exit;

            } catch (PDOException $exception) {

                if ($uploadedFilename !== '') {

                    $uploadedPath =
                        dirname(__DIR__, 2) .
                        '/uploads/teachers/' .
                        $uploadedFilename;

                    if (is_file($uploadedPath)) {
                        @unlink($uploadedPath);
                    }
                }

                error_log(
                    'Teacher creation failed: ' .
                    $exception->getMessage()
                );

                if (
                    isset($exception->errorInfo[1]) &&
                    (int)$exception->errorInfo[1] === 1062
                ) {

                    $error =
                        'Teacher code or email already exists.';

                } else {

                    $error =
                        'Unable to create the teacher account.';
                }

            } catch (Throwable $exception) {

                if ($uploadedFilename !== '') {

                    $uploadedPath =
                        dirname(__DIR__, 2) .
                        '/uploads/teachers/' .
                        $uploadedFilename;

                    if (is_file($uploadedPath)) {
                        @unlink($uploadedPath);
                    }
                }

                error_log(
                    'Teacher creation failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to create the teacher account.';
            }
        }
    }
}

function add_teacher_e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

include "../includes/header.php";
?>

<style>

    .teacher-add-page {
        max-width: 1080px;
        margin: 0 auto;
    }

    .teacher-add-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 23px;
    }

    .teacher-add-heading .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #556b2f;
        font-size: .76rem;
        font-weight: 800;
        letter-spacing: .12em;
        margin-bottom: 8px;
    }

    .teacher-add-heading h1 {
        margin: 0;
    }

    .teacher-add-heading p {
        color: #746d68;
        margin: 6px 0 0;
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

    .teacher-form-card {
        overflow: hidden;
        border-radius: 24px;
        background: rgba(255,255,255,.89);
        border: 1px solid rgba(93,64,55,.10);
        box-shadow: 0 20px 55px rgba(51,51,51,.08);
        backdrop-filter: blur(15px);
    }

    .teacher-form-head {
        padding: 21px 23px;
        border-bottom: 1px solid rgba(93,64,55,.08);
        background: rgba(250,247,240,.65);
    }

    .teacher-form-head span {
        display: block;
        color: #556b2f;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .10em;
        margin-bottom: 4px;
    }

    .teacher-form-head h2 {
        margin: 0;
        font-size: 1.08rem;
        color: #333;
    }

    .teacher-form-body {
        padding: 25px;
    }

    .teacher-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .teacher-form-field.full {
        grid-column: 1 / -1;
    }

    .teacher-form-field label {
        display: block;
        margin-bottom: 7px;
        color: #504945;
        font-size: .82rem;
        font-weight: 800;
    }

    .teacher-form-field input,
    .teacher-form-field select,
    .teacher-form-field textarea {
        width: 100%;
        border: 1px solid rgba(93,64,55,.15);
        border-radius: 12px;
        background: #fff;
        color: #34302e;
        padding: 11px 13px;
        outline: none;
        font: inherit;
        transition: .2s ease;
    }

    .teacher-form-field textarea {
        min-height: 115px;
        resize: vertical;
    }

    .teacher-form-field input:focus,
    .teacher-form-field select:focus,
    .teacher-form-field textarea:focus {
        border-color: #556b2f;
        box-shadow: 0 0 0 3px rgba(85,107,47,.10);
    }

    .teacher-form-field small {
        display: block;
        margin-top: 5px;
        color: #817973;
        font-size: .73rem;
    }

    .required-star {
        color: #a33a32;
    }

    .password-wrap {
        position: relative;
    }

    .password-wrap input {
        padding-right: 47px;
    }

    .password-toggle {
        position: absolute;
        top: 50%;
        right: 7px;
        width: 35px;
        height: 35px;
        border: 0;
        border-radius: 9px;
        background: transparent;
        color: #766d68;
        transform: translateY(-50%);
    }

    .password-toggle:hover {
        background: rgba(93,64,55,.07);
        color: #5d4037;
    }

    .photo-upload {
        padding: 15px;
        border: 1px dashed rgba(93,64,55,.22);
        border-radius: 14px;
        background: #faf8f4;
    }

    .photo-upload input {
        background: #fff;
    }

    .photo-help {
        margin-top: 8px !important;
        line-height: 1.45;
    }

    .teacher-form-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding-top: 22px;
        margin-top: 23px;
        border-top: 1px solid rgba(93,64,55,.08);
    }

    .teacher-save-btn,
    .teacher-cancel-btn {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border-radius: 12px;
        padding: 0 18px;
        text-decoration: none;
        font-weight: 800;
    }

    .teacher-save-btn {
        border: 0;
        background: #556b2f;
        color: #fff;
        cursor: pointer;
        box-shadow: 0 10px 25px rgba(85,107,47,.18);
    }

    .teacher-save-btn:hover {
        background: #465b27;
        color: #fff;
    }

    .teacher-cancel-btn {
        background: rgba(93,64,55,.08);
        color: #5d4037;
    }

    .teacher-cancel-btn:hover {
        background: rgba(93,64,55,.14);
        color: #5d4037;
    }

    @media (max-width: 760px) {

        .teacher-add-heading {
            align-items: flex-start;
            flex-direction: column;
        }

        .teacher-back {
            width: 100%;
        }

        .teacher-form-grid {
            grid-template-columns: 1fr;
        }

        .teacher-form-field.full {
            grid-column: auto;
        }

        .teacher-form-footer {
            align-items: stretch;
            flex-direction: column-reverse;
        }

        .teacher-save-btn,
        .teacher-cancel-btn {
            width: 100%;
        }

    }

</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content teacher-add-page">

            <section class="teacher-add-heading">

                <div>

                    <span class="eyebrow">
                        <i class="fa-solid fa-user-plus"></i>
                        TEACHER MANAGEMENT
                    </span>

                    <h1>Add Teacher</h1>

                    <p>
                        Create a new teacher account for ExamSphere.
                    </p>

                </div>

                <a
                    href="index.php"
                    class="teacher-back"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Teachers
                </a>

            </section>

            <section class="teacher-form-card">

                <header class="teacher-form-head">

                    <span>ACCOUNT & PROFILE</span>

                    <h2>Teacher Registration</h2>

                </header>

                <div class="teacher-form-body">

                    <?php if ($error !== ''): ?>

                        <div class="alert alert-danger rounded-4 border-0">

                            <i class="fa-solid fa-circle-exclamation me-2"></i>

                            <?= add_teacher_e($error) ?>

                        </div>

                    <?php endif; ?>

                    <form
                        method="post"
                        enctype="multipart/form-data"
                        autocomplete="off"
                        novalidate
                        id="teacherAddForm"
                    >

                        <?= csrf_field() ?>

                        <div class="teacher-form-grid">

                            <div class="teacher-form-field">

                                <label for="teacher_code">
                                    Teacher Code
                                    <span class="required-star">*</span>
                                </label>

                                <input
                                    id="teacher_code"
                                    type="text"
                                    name="teacher_code"
                                    value="<?= add_teacher_e($teacherCode) ?>"
                                    maxlength="30"
                                    placeholder="e.g. TCH001"
                                    required
                                >

                            </div>

                            <div class="teacher-form-field">

                                <label for="full_name">
                                    Full Name
                                    <span class="required-star">*</span>
                                </label>

                                <input
                                    id="full_name"
                                    type="text"
                                    name="full_name"
                                    value="<?= add_teacher_e($fullName) ?>"
                                    maxlength="100"
                                    placeholder="Enter teacher name"
                                    required
                                >

                            </div>

                            <div class="teacher-form-field">

                                <label for="email">
                                    Email Address
                                    <span class="required-star">*</span>
                                </label>

                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="<?= add_teacher_e($email) ?>"
                                    maxlength="150"
                                    placeholder="teacher@example.com"
                                    required
                                >

                            </div>

                            <div class="teacher-form-field">

                                <label for="password">
                                    Password
                                    <span class="required-star">*</span>
                                </label>

                                <div class="password-wrap">

                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        minlength="8"
                                        maxlength="255"
                                        placeholder="Minimum 8 characters"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        id="passwordToggle"
                                        aria-label="Show password"
                                    >
                                        <i class="fa-regular fa-eye"></i>
                                    </button>

                                </div>

                            </div>

                            <div class="teacher-form-field">

                                <label for="phone">
                                    Phone
                                </label>

                                <input
                                    id="phone"
                                    type="text"
                                    name="phone"
                                    value="<?= add_teacher_e($phone) ?>"
                                    maxlength="20"
                                    placeholder="Enter phone number"
                                >

                            </div>

                            <div class="teacher-form-field">

                                <label for="mobile">
                                    Mobile
                                </label>

                                <input
                                    id="mobile"
                                    type="text"
                                    name="mobile"
                                    value="<?= add_teacher_e($mobile) ?>"
                                    maxlength="20"
                                    placeholder="Enter mobile number"
                                >

                            </div>

                            <div class="teacher-form-field">

                                <label for="gender">
                                    Gender
                                </label>

                                <select
                                    id="gender"
                                    name="gender"
                                >

                                    <option value="">
                                        Select gender
                                    </option>

                                    <option
                                        value="Male"
                                        <?= $gender === 'Male'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Male
                                    </option>

                                    <option
                                        value="Female"
                                        <?= $gender === 'Female'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Female
                                    </option>

                                    <option
                                        value="Other"
                                        <?= $gender === 'Other'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Other
                                    </option>

                                </select>

                            </div>

                            <div class="teacher-form-field">

                                <label for="dob">
                                    Date of Birth
                                </label>

                                <input
                                    id="dob"
                                    type="date"
                                    name="dob"
                                    value="<?= add_teacher_e($dob) ?>"
                                >

                            </div>

                            <div class="teacher-form-field">

                                <label for="qualification">
                                    Qualification
                                </label>

                                <input
                                    id="qualification"
                                    type="text"
                                    name="qualification"
                                    value="<?= add_teacher_e(
                                        $qualification
                                    ) ?>"
                                    maxlength="255"
                                    placeholder="e.g. M.Sc., B.Ed."
                                >

                            </div>

                            <div class="teacher-form-field">

                                <label for="experience">
                                    Experience
                                </label>

                                <input
                                    id="experience"
                                    type="text"
                                    name="experience"
                                    value="<?= add_teacher_e(
                                        $experience
                                    ) ?>"
                                    maxlength="100"
                                    placeholder="e.g. 5 Years"
                                >

                            </div>

                            <div class="teacher-form-field full">

                                <label for="address">
                                    Address
                                </label>

                                <textarea
                                    id="address"
                                    name="address"
                                    placeholder="Enter teacher address"
                                ><?= add_teacher_e($address) ?></textarea>

                            </div>

                            <div class="teacher-form-field">

                                <label for="profile_photo">
                                    Profile Photo
                                </label>

                                <div class="photo-upload">

                                    <input
                                        id="profile_photo"
                                        type="file"
                                        name="profile_photo"
                                        class="form-control"
                                        accept="image/jpeg,image/png,image/webp"
                                    >

                                    <small class="photo-help">
                                        JPG, PNG or WebP · Maximum 5 MB.
                                    </small>

                                </div>

                            </div>

                            <div class="teacher-form-field">

                                <label for="status">
                                    Account Status
                                </label>

                                <select
                                    id="status"
                                    name="status"
                                >

                                    <option
                                        value="Active"
                                        <?= $status === 'Active'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Active
                                    </option>

                                    <option
                                        value="Inactive"
                                        <?= $status === 'Inactive'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Inactive
                                    </option>

                                </select>

                            </div>

                        </div>

                        <div class="teacher-form-footer">

                            <a
                                href="index.php"
                                class="teacher-cancel-btn"
                            >
                                <i class="fa-solid fa-xmark"></i>
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="teacher-save-btn"
                                id="teacherSaveButton"
                            >
                                <i class="fa-solid fa-user-plus"></i>
                                Create Teacher
                            </button>

                        </div>

                    </form>

                </div>

            </section>

        </main>

    </div>

</div>

<script>

    document.addEventListener('DOMContentLoaded', function () {

        const password =
            document.getElementById('password');

        const toggle =
            document.getElementById('passwordToggle');

        const form =
            document.getElementById('teacherAddForm');

        const saveButton =
            document.getElementById('teacherSaveButton');

        if (toggle && password) {

            toggle.addEventListener('click', function () {

                const showing =
                    password.type === 'text';

                password.type =
                    showing ? 'password' : 'text';

                toggle.innerHTML = showing
                    ? '<i class="fa-regular fa-eye"></i>'
                    : '<i class="fa-regular fa-eye-slash"></i>';

                toggle.setAttribute(
                    'aria-label',
                    showing
                        ? 'Show password'
                        : 'Hide password'
                );
            });
        }

        if (form && saveButton) {

            form.addEventListener('submit', function (event) {

                if (!form.checkValidity()) {
                    return;
                }

                saveButton.disabled = true;

                saveButton.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> ' +
                    'Creating Teacher...';
            });
        }

    });

</script>

<?php include "../includes/footer.php"; ?>