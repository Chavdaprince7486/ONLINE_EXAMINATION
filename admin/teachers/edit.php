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

if ($id === false || $id === null || $id <= 0) {
    $_SESSION['error'] = 'Invalid teacher.';
    header('Location: index.php');
    exit;
}

$page_title = 'Edit Teacher';

$error = '';

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

    $stmt->execute([$id]);

    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        $_SESSION['error'] = 'Teacher not found.';
        header('Location: index.php');
        exit;
    }

} catch (Throwable $exception) {

    error_log(
        'Teacher edit fetch failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to load teacher details.';

    header('Location: index.php');
    exit;
}

$fullName = (string)$teacher['full_name'];
$email = (string)$teacher['email'];
$phone = (string)($teacher['phone'] ?? '');
$mobile = (string)($teacher['mobile'] ?? '');
$gender = (string)($teacher['gender'] ?? '');
$dob = (string)($teacher['dob'] ?? '');
$qualification = (string)($teacher['qualification'] ?? '');
$experience = (string)($teacher['experience'] ?? '');
$address = (string)($teacher['address'] ?? '');
$status = (string)$teacher['status'];

$oldProfilePhoto = trim(
    (string)($teacher['profile_photo'] ?? '')
);

$newProfilePhoto = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $error =
            'Security verification failed. Please refresh the page and try again.';

    } else {

        $fullName = trim(
            (string)($_POST['full_name'] ?? '')
        );

        $email = trim(
            (string)($_POST['email'] ?? '')
        );

        $phone = trim(
            (string)($_POST['phone'] ?? '')
        );

        $mobile = trim(
            (string)($_POST['mobile'] ?? '')
        );

        $gender = trim(
            (string)($_POST['gender'] ?? '')
        );

        $dob = trim(
            (string)($_POST['dob'] ?? '')
        );

        $qualification = trim(
            (string)($_POST['qualification'] ?? '')
        );

        $experience = trim(
            (string)($_POST['experience'] ?? '')
        );

        $address = trim(
            (string)($_POST['address'] ?? '')
        );

        $status = trim(
            (string)($_POST['status'] ?? '')
        );

        if ($fullName === '') {

            $error = 'Full name is required.';

        } elseif (mb_strlen($fullName) > 100) {

            $error =
                'Full name cannot exceed 100 characters.';

        } elseif ($email === '') {

            $error = 'Email address is required.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error =
                'Please enter a valid email address.';

        } elseif (mb_strlen($email) > 150) {

            $error =
                'Email address cannot exceed 150 characters.';

        } elseif (
            $phone !== '' &&
            mb_strlen($phone) > 20
        ) {

            $error =
                'Phone number cannot exceed 20 characters.';

        } elseif (
            $mobile !== '' &&
            mb_strlen($mobile) > 20
        ) {

            $error =
                'Mobile number cannot exceed 20 characters.';

        } elseif (
            $gender !== '' &&
            !in_array(
                $gender,
                ['Male', 'Female', 'Other'],
                true
            )
        ) {

            $error =
                'Invalid gender selected.';

        } elseif (
            $dob !== '' &&
            !preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $dob
            )
        ) {

            $error =
                'Please enter a valid date of birth.';

        } elseif (
            $qualification !== '' &&
            mb_strlen($qualification) > 255
        ) {

            $error =
                'Qualification cannot exceed 255 characters.';

        } elseif (
            $experience !== '' &&
            mb_strlen($experience) > 100
        ) {

            $error =
                'Experience cannot exceed 100 characters.';

        } elseif (!in_array(
            $status,
            ['Active', 'Inactive'],
            true
        )) {

            $error =
                'Invalid account status selected.';
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

                $error =
                    'Please enter a valid date of birth.';

            } elseif (
                $dobDate > new DateTime('today')
            ) {

                $error =
                    'Date of birth cannot be in the future.';
            }
        }

        if ($error === '') {

            try {

                $emailCheck = $conn->prepare("
                    SELECT id
                    FROM teachers
                    WHERE email = ?
                      AND id <> ?
                    LIMIT 1
                ");

                $emailCheck->execute([
                    $email,
                    $id
                ]);

                if ($emailCheck->fetch(PDO::FETCH_ASSOC)) {

                    $error =
                        'This email address is already registered to another teacher.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Teacher email check failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to validate the email address right now.';
            }
        }

        if (
            $error === '' &&
            isset($_FILES['profile_photo']) &&
            is_array($_FILES['profile_photo'])
        ) {

            $fileError = (int)(
                $_FILES['profile_photo']['error']
                ?? UPLOAD_ERR_NO_FILE
            );

            if ($fileError !== UPLOAD_ERR_NO_FILE) {

                if ($fileError !== UPLOAD_ERR_OK) {

                    $error =
                        'Unable to upload the profile photo.';

                } else {

                    $tmpName = (string)(
                        $_FILES['profile_photo']['tmp_name']
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

                        $imageInfo =
                            @getimagesize($tmpName);

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
                                !isset(
                                    $allowedMimes[$mime]
                                )
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

                                        $newProfilePhoto =
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
                                            $newProfilePhoto;

                                        if (
                                            !move_uploaded_file(
                                                $tmpName,
                                                $destination
                                            )
                                        ) {

                                            $newProfilePhoto = '';

                                            $error =
                                                'Unable to save the profile photo.';
                                        }

                                    } catch (Throwable $exception) {

                                        error_log(
                                            'Teacher photo processing failed: ' .
                                            $exception->getMessage()
                                        );

                                        $newProfilePhoto = '';

                                        $error =
                                            'Unable to process the profile photo.';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($error === '') {

            try {

                $profilePhotoForDatabase =
                    $newProfilePhoto !== ''
                        ? $newProfilePhoto
                        : $oldProfilePhoto;

                $update = $conn->prepare("
                    UPDATE teachers
                    SET
                        full_name = ?,
                        email = ?,
                        phone = ?,
                        mobile = ?,
                        gender = ?,
                        dob = ?,
                        qualification = ?,
                        experience = ?,
                        address = ?,
                        profile_photo = ?,
                        status = ?
                    WHERE id = ?
                ");

                $update->execute([
                    $fullName,
                    $email,
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
                    $profilePhotoForDatabase !== ''
                        ? $profilePhotoForDatabase
                        : null,
                    $status,
                    $id
                ]);

                /*
                 * Remove the old image only after the database
                 * update has succeeded.
                 */
                if (
                    $newProfilePhoto !== '' &&
                    $oldProfilePhoto !== '' &&
                    $oldProfilePhoto !== $newProfilePhoto
                ) {

                    $oldPath =
                        dirname(__DIR__, 2) .
                        '/uploads/teachers/' .
                        basename($oldProfilePhoto);

                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $_SESSION['success'] =
                    'Teacher "' .
                    $fullName .
                    '" updated successfully.';

                header(
                    'Location: view.php?id=' . $id
                );
                exit;

            } catch (PDOException $exception) {

                if ($newProfilePhoto !== '') {

                    $newPath =
                        dirname(__DIR__, 2) .
                        '/uploads/teachers/' .
                        basename($newProfilePhoto);

                    if (is_file($newPath)) {
                        @unlink($newPath);
                    }
                }

                error_log(
                    'Teacher update failed: ' .
                    $exception->getMessage()
                );

                if (
                    isset($exception->errorInfo[1]) &&
                    (int)$exception->errorInfo[1] === 1062
                ) {

                    $error =
                        'This email address is already registered to another teacher.';

                } else {

                    $error =
                        'Unable to update the teacher details.';
                }

            } catch (Throwable $exception) {

                if ($newProfilePhoto !== '') {

                    $newPath =
                        dirname(__DIR__, 2) .
                        '/uploads/teachers/' .
                        basename($newProfilePhoto);

                    if (is_file($newPath)) {
                        @unlink($newPath);
                    }
                }

                error_log(
                    'Teacher update failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to update the teacher details.';
            }
        }
    }
}

function edit_teacher_e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function edit_teacher_date(?string $value): string
{
    if (!$value) {
        return 'Not provided';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d M Y, h:i A', $timestamp)
        : 'Not provided';
}

$photoUrl = '';

if ($oldProfilePhoto !== '') {
    $photoUrl =
        '../../uploads/teachers/' .
        rawurlencode(basename($oldProfilePhoto));
}

include "../includes/header.php";
?>

<style>

    .teacher-edit-page {
        max-width: 1080px;
        margin: 0 auto;
    }

    .teacher-edit-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 23px;
    }

    .teacher-edit-heading .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #556b2f;
        font-size: .76rem;
        font-weight: 800;
        letter-spacing: .12em;
        margin-bottom: 8px;
    }

    .teacher-edit-heading h1 {
        margin: 0;
    }

    .teacher-edit-heading p {
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

    .teacher-edit-card {
        overflow: hidden;
        border-radius: 24px;
        background: rgba(255,255,255,.89);
        border: 1px solid rgba(93,64,55,.10);
        box-shadow: 0 20px 55px rgba(51,51,51,.08);
        backdrop-filter: blur(15px);
    }

    .teacher-edit-head {
        padding: 21px 23px;
        border-bottom: 1px solid rgba(93,64,55,.08);
        background: rgba(250,247,240,.65);
    }

    .teacher-edit-head span {
        display: block;
        color: #556b2f;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .10em;
        margin-bottom: 4px;
    }

    .teacher-edit-head h2 {
        margin: 0;
        color: #333;
        font-size: 1.08rem;
    }

    .teacher-edit-body {
        padding: 25px;
    }

    .teacher-edit-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .teacher-edit-field.full {
        grid-column: 1 / -1;
    }

    .teacher-edit-field label {
        display: block;
        margin-bottom: 7px;
        color: #504945;
        font-size: .82rem;
        font-weight: 800;
    }

    .teacher-edit-field input,
    .teacher-edit-field select,
    .teacher-edit-field textarea {
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

    .teacher-edit-field textarea {
        min-height: 115px;
        resize: vertical;
    }

    .teacher-edit-field input:focus,
    .teacher-edit-field select:focus,
    .teacher-edit-field textarea:focus {
        border-color: #556b2f;
        box-shadow: 0 0 0 3px rgba(85,107,47,.10);
    }

    .teacher-help {
        display: block;
        margin-top: 5px;
        color: #817973;
        font-size: .73rem;
    }

    .readonly-box {
        min-height: 44px;
        display: flex;
        align-items: center;
        padding: 10px 13px;
        border: 1px solid rgba(93,64,55,.09);
        border-radius: 12px;
        background: #faf8f4;
        color: #655d58;
        font-weight: 700;
        word-break: break-word;
    }

    .photo-panel {
        display: flex;
        align-items: center;
        gap: 17px;
        padding: 15px;
        border-radius: 15px;
        border: 1px dashed rgba(93,64,55,.20);
        background: #faf8f4;
    }

    .current-teacher-photo,
    .teacher-photo-placeholder {
        width: 82px;
        height: 82px;
        flex: 0 0 82px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-radius: 16px;
        background: #5d4037;
        color: #fff;
        font-size: 1.8rem;
        font-weight: 800;
    }

    .current-teacher-photo img {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: cover;
    }

    .photo-panel-content {
        flex: 1;
        min-width: 0;
    }

    .photo-panel-content input {
        background: #fff;
    }

    .photo-panel-content small {
        display: block;
        margin-top: 7px;
        color: #817973;
        font-size: .73rem;
    }

    .teacher-edit-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 23px;
        padding-top: 22px;
        border-top: 1px solid rgba(93,64,55,.08);
    }

    .teacher-save-btn,
    .teacher-cancel-btn {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 18px;
        border-radius: 12px;
        font-weight: 800;
        text-decoration: none;
    }

    .teacher-save-btn {
        border: 0;
        cursor: pointer;
        background: #556b2f;
        color: #fff;
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

        .teacher-edit-heading {
            align-items: flex-start;
            flex-direction: column;
        }

        .teacher-back {
            width: 100%;
        }

        .teacher-edit-grid {
            grid-template-columns: 1fr;
        }

        .teacher-edit-field.full {
            grid-column: auto;
        }

        .photo-panel {
            align-items: flex-start;
            flex-direction: column;
        }

        .teacher-edit-footer {
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

        <main class="dashboard-content teacher-edit-page">

            <section class="teacher-edit-heading">

                <div>

                    <span class="eyebrow">
                        <i class="fa-solid fa-user-pen"></i>
                        TEACHER MANAGEMENT
                    </span>

                    <h1>Edit Teacher</h1>

                    <p>
                        Update teacher profile and account information.
                    </p>

                </div>

                <a
                    href="view.php?id=<?= (int)$teacher['id'] ?>"
                    class="teacher-back"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Profile
                </a>

            </section>

            <section class="teacher-edit-card">

                <header class="teacher-edit-head">

                    <span>PROFILE INFORMATION</span>

                    <h2>
                        <?= edit_teacher_e(
                            $teacher['full_name']
                        ) ?>
                    </h2>

                </header>

                <div class="teacher-edit-body">

                    <?php if ($error !== ''): ?>

                        <div class="alert alert-danger border-0 rounded-4">

                            <i class="fa-solid fa-circle-exclamation me-2"></i>

                            <?= edit_teacher_e($error) ?>

                        </div>

                    <?php endif; ?>

                    <form
                        method="post"
                        enctype="multipart/form-data"
                        autocomplete="off"
                        novalidate
                        id="teacherEditForm"
                    >

                        <?= csrf_field() ?>

                        <div class="teacher-edit-grid">

                            <div class="teacher-edit-field">

                                <label>
                                    Teacher Code
                                </label>

                                <div class="readonly-box">
                                    <?= edit_teacher_e(
                                        $teacher['teacher_code']
                                        ?: 'Not assigned'
                                    ) ?>
                                </div>

                                <small class="teacher-help">
                                    Teacher code is not changed from this form.
                                </small>

                            </div>

                            <div class="teacher-edit-field">

                                <label for="full_name">
                                    Full Name
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    id="full_name"
                                    type="text"
                                    name="full_name"
                                    value="<?= edit_teacher_e(
                                        $fullName
                                    ) ?>"
                                    maxlength="100"
                                    required
                                >

                            </div>

                            <div class="teacher-edit-field">

                                <label for="email">
                                    Email Address
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="<?= edit_teacher_e(
                                        $email
                                    ) ?>"
                                    maxlength="150"
                                    required
                                >

                            </div>

                            <div class="teacher-edit-field">

                                <label for="phone">
                                    Phone
                                </label>

                                <input
                                    id="phone"
                                    type="text"
                                    name="phone"
                                    value="<?= edit_teacher_e(
                                        $phone
                                    ) ?>"
                                    maxlength="20"
                                >

                            </div>

                            <div class="teacher-edit-field">

                                <label for="mobile">
                                    Mobile
                                </label>

                                <input
                                    id="mobile"
                                    type="text"
                                    name="mobile"
                                    value="<?= edit_teacher_e(
                                        $mobile
                                    ) ?>"
                                    maxlength="20"
                                >

                            </div>

                            <div class="teacher-edit-field">

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

                            <div class="teacher-edit-field">

                                <label for="dob">
                                    Date of Birth
                                </label>

                                <input
                                    id="dob"
                                    type="date"
                                    name="dob"
                                    value="<?= edit_teacher_e(
                                        $dob
                                    ) ?>"
                                >

                            </div>

                            <div class="teacher-edit-field">

                                <label for="qualification">
                                    Qualification
                                </label>

                                <input
                                    id="qualification"
                                    type="text"
                                    name="qualification"
                                    value="<?= edit_teacher_e(
                                        $qualification
                                    ) ?>"
                                    maxlength="255"
                                >

                            </div>

                            <div class="teacher-edit-field">

                                <label for="experience">
                                    Experience
                                </label>

                                <input
                                    id="experience"
                                    type="text"
                                    name="experience"
                                    value="<?= edit_teacher_e(
                                        $experience
                                    ) ?>"
                                    maxlength="100"
                                >

                            </div>

                            <div class="teacher-edit-field full">

                                <label for="address">
                                    Address
                                </label>

                                <textarea
                                    id="address"
                                    name="address"
                                    maxlength="65535"
                                ><?= edit_teacher_e(
                                    $address
                                ) ?></textarea>

                            </div>

                            <div class="teacher-edit-field full">

                                <label>
                                    Profile Photo
                                </label>

                                <div class="photo-panel">

                                    <?php if ($photoUrl !== ''): ?>

                                        <div class="current-teacher-photo">

                                            <img
                                                src="<?= edit_teacher_e(
                                                    $photoUrl
                                                ) ?>"
                                                alt="<?= edit_teacher_e(
                                                    $teacher['full_name']
                                                ) ?>"
                                            >

                                        </div>

                                    <?php else: ?>

                                        <div class="teacher-photo-placeholder">

                                            <?= edit_teacher_e(
                                                function_exists('mb_substr')
                                                    ? mb_substr(
                                                        $teacher['full_name'],
                                                        0,
                                                        1,
                                                        'UTF-8'
                                                    )
                                                    : substr(
                                                        $teacher['full_name'],
                                                        0,
                                                        1
                                                    )
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                    <div class="photo-panel-content">

                                        <input
                                            id="profile_photo"
                                            type="file"
                                            name="profile_photo"
                                            class="form-control"
                                            accept="image/jpeg,image/png,image/webp"
                                        >

                                        <small>
                                            Upload JPG, PNG or WebP.
                                            Maximum size: 5 MB.
                                            Leave empty to keep the current photo.
                                        </small>

                                    </div>

                                </div>

                            </div>

                            <div class="teacher-edit-field">

                                <label>
                                    Last Login
                                </label>

                                <div class="readonly-box">
                                    <?= edit_teacher_e(
                                        edit_teacher_date(
                                            $teacher['last_login']
                                        )
                                    ) ?>
                                </div>

                            </div>

                            <div class="teacher-edit-field">

                                <label>
                                    Registered On
                                </label>

                                <div class="readonly-box">
                                    <?= edit_teacher_e(
                                        edit_teacher_date(
                                            $teacher['created_at']
                                        )
                                    ) ?>
                                </div>

                            </div>

                            <div class="teacher-edit-field">

                                <label for="status">
                                    Account Status
                                </label>

                                <select
                                    id="status"
                                    name="status"
                                    required
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

                        <div class="teacher-edit-footer">

                            <a
                                href="view.php?id=<?= (int)$teacher['id'] ?>"
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
                                <i class="fa-solid fa-floppy-disk"></i>
                                Save Changes
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

        const form =
            document.getElementById('teacherEditForm');

        const button =
            document.getElementById('teacherSaveButton');

        const photo =
            document.getElementById('profile_photo');

        if (photo) {

            photo.addEventListener('change', function () {

                const file = photo.files[0];

                if (!file) {
                    return;
                }

                const allowedTypes = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];

                if (!allowedTypes.includes(file.type)) {

                    alert(
                        'Only JPG, PNG and WebP images are allowed.'
                    );

                    photo.value = '';

                    return;
                }

                if (file.size > 5 * 1024 * 1024) {

                    alert(
                        'Profile photo must be 5 MB or smaller.'
                    );

                    photo.value = '';
                }

            });
        }

        if (form && button) {

            form.addEventListener('submit', function () {

                if (!form.checkValidity()) {
                    return;
                }

                button.disabled = true;

                button.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> ' +
                    'Saving Changes...';
            });
        }

    });

</script>

<?php include "../includes/footer.php"; ?>