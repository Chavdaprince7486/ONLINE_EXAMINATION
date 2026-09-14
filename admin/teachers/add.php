<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";
require_once "../../config/functions.php";

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: ../../auth/login.php');
    exit;
}

$page_title = 'Add Teacher';

$error = '';

$fullName = '';
$email = '';
$phone = '';
$mobile = '';
$gender = '';
$dob = '';
$qualification = '';
$experience = '';
$address = '';
$password = '';
$status = 'Active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $error =
            'Security verification failed. Please refresh the page and try again.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | FORM INPUT
        |--------------------------------------------------------------------------
        */

        $fullName = trim(
            (string)($_POST['full_name'] ?? '')
        );

        $email = strtolower(
            trim(
                (string)($_POST['email'] ?? '')
            )
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

        $password = (string)(
            $_POST['password'] ?? ''
        );

        $passwordConfirm = (string)(
            $_POST['password_confirmation'] ?? ''
        );

        $status = trim(
            (string)($_POST['status'] ?? 'Active')
        );

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($fullName === '') {

            $error =
                'Full name is required.';

        } elseif (mb_strlen($fullName) > 100) {

            $error =
                'Full name cannot exceed 100 characters.';

        } elseif ($email === '') {

            $error =
                'Email address is required.';

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
                [
                    'Male',
                    'Female',
                    'Other'
                ],
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

        } elseif ($password === '') {

            $error =
                'Password is required.';

        } elseif (strlen($password) < 8) {

            $error =
                'Password must be at least 8 characters long.';

        } elseif (strlen($password) > 255) {

            $error =
                'Password cannot exceed 255 characters.';

        } elseif ($password !== $passwordConfirm) {

            $error =
                'Password and confirm password do not match.';

        } elseif (
            !in_array(
                $status,
                [
                    'Active',
                    'Inactive'
                ],
                true
            )
        ) {

            $error =
                'Invalid account status selected.';
        }

        /*
        |--------------------------------------------------------------------------
        | DOB VALIDATION
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | PROFILE PHOTO
        |--------------------------------------------------------------------------
        */

        $newProfilePhoto = '';

        if (
            $error === '' &&
            isset($_FILES['profile_photo']) &&
            is_array($_FILES['profile_photo'])
        ) {

            $fileError = (int)(
                $_FILES['profile_photo']['error']
                ??
                UPLOAD_ERR_NO_FILE
            );

            if (
                $fileError !== UPLOAD_ERR_NO_FILE
            ) {

                if (
                    $fileError !== UPLOAD_ERR_OK
                ) {

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

                    } elseif (
                        $fileSize <= 0
                    ) {

                        $error =
                            'The uploaded profile photo is empty.';

                    } elseif (
                        $fileSize > 5 * 1024 * 1024
                    ) {

                        $error =
                            'Profile photo must be 5 MB or smaller.';

                    } else {

                        $imageInfo =
                            @getimagesize($tmpName);

                        if (
                            $imageInfo === false
                        ) {

                            $error =
                                'Profile photo must be a valid image.';

                        } else {

                            $mime = (string)(
                                $imageInfo['mime']
                                ?? ''
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

        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE EMAIL
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            try {

                $emailCheck = $conn->prepare("
                    SELECT id
                    FROM teachers
                    WHERE email = ?
                    LIMIT 1
                ");

                $emailCheck->execute([
                    $email
                ]);

                if (
                    $emailCheck->fetch(PDO::FETCH_ASSOC)
                ) {

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

        /*
        |--------------------------------------------------------------------------
        | CREATE TEACHER
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            try {

                $conn->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | PASSWORD HASH
                |--------------------------------------------------------------------------
                */

                $hashedPassword =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                if (
                    $hashedPassword === false
                ) {

                    throw new RuntimeException(
                        'Unable to secure teacher password.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | INSERT TEACHER
                |--------------------------------------------------------------------------
                |
                | teacher_code is NULL during the first insert because
                | the actual numeric ID is required to generate the code.
                |
                */

                $insert = $conn->prepare("
                    INSERT INTO teachers (
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
                        password,
                        status
                    ) VALUES (
                        NULL,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");

                $insert->execute([
                    $fullName,
                    $email,
                    $phone !== ''
                        ? $phone
                        : null,
                    $mobile !== ''
                        ? $mobile
                        : null,
                    $gender !== ''
                        ? $gender
                        : null,
                    $dob !== ''
                        ? $dob
                        : null,
                    $qualification !== ''
                        ? $qualification
                        : null,
                    $experience !== ''
                        ? $experience
                        : null,
                    $address !== ''
                        ? $address
                        : null,
                    $newProfilePhoto !== ''
                        ? $newProfilePhoto
                        : null,
                    $hashedPassword,
                    $status
                ]);

                /*
                |--------------------------------------------------------------------------
                | GET NEW TEACHER ID
                |--------------------------------------------------------------------------
                */

                $teacherId =
                    (int)$conn->lastInsertId();

                if (
                    $teacherId <= 0
                ) {

                    throw new RuntimeException(
                        'Unable to determine the new teacher ID.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | GENERATE TEACHER CODE
                |--------------------------------------------------------------------------
                */

                $teacherCode =
                    'TCH' .
                    str_pad(
                        (string)$teacherId,
                        5,
                        '0',
                        STR_PAD_LEFT
                    );

                /*
                |--------------------------------------------------------------------------
                | UPDATE TEACHER CODE
                |--------------------------------------------------------------------------
                */

                $codeUpdate = $conn->prepare("
                    UPDATE teachers
                    SET teacher_code = ?
                    WHERE id = ?
                    LIMIT 1
                ");

                $codeUpdate->execute([
                    $teacherCode,
                    $teacherId
                ]);

                /*
                |--------------------------------------------------------------------------
                | COMMIT
                |--------------------------------------------------------------------------
                */

                $conn->commit();

                $_SESSION['success'] =
                    'Teacher "' .
                    $fullName .
                    '" created successfully. Teacher Code: ' .
                    $teacherCode;

                header(
                    'Location: view.php?id=' .
                    $teacherId
                );

                exit;

            } catch (PDOException $exception) {

                if (
                    $conn->inTransaction()
                ) {

                    $conn->rollBack();
                }

                /*
                |--------------------------------------------------------------------------
                | REMOVE UPLOADED PHOTO WHEN DATABASE INSERT FAILS
                |--------------------------------------------------------------------------
                */

                if (
                    $newProfilePhoto !== ''
                ) {

                    $newPath =
                        dirname(__DIR__, 2) .
                        '/uploads/teachers/' .
                        basename(
                            $newProfilePhoto
                        );

                    if (
                        is_file($newPath)
                    ) {

                        @unlink($newPath);
                    }
                }

                error_log(
                    'Teacher creation failed: ' .
                    $exception->getMessage()
                );

                if (
                    isset(
                        $exception->errorInfo[1]
                    ) &&
                    (int)$exception->errorInfo[1] === 1062
                ) {

                    $error =
                        'This email address or teacher code is already registered.';

                } else {

                    $error =
                        'Unable to create the teacher account.';
                }

            } catch (Throwable $exception) {

                if (
                    $conn->inTransaction()
                ) {

                    $conn->rollBack();
                }

                if (
                    $newProfilePhoto !== ''
                ) {

                    $newPath =
                        dirname(__DIR__, 2) .
                        '/uploads/teachers/' .
                        basename(
                            $newProfilePhoto
                        );

                    if (
                        is_file($newPath)
                    ) {

                        @unlink($newPath);
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


/*
|--------------------------------------------------------------------------
| ESCAPE
|--------------------------------------------------------------------------
*/

function add_teacher_e(
    mixed $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
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

    .photo-panel {
        display: flex;
        align-items: center;
        gap: 17px;
        padding: 15px;
        border-radius: 15px;
        border: 1px dashed rgba(93,64,55,.20);
        background: #faf8f4;
    }

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

    .teacher-save-btn:disabled {
        opacity: .75;
        cursor: not-allowed;
    }

    .teacher-cancel-btn {
        background: rgba(93,64,55,.08);
        color: #5d4037;
    }

    .teacher-cancel-btn:hover {
        background: rgba(93,64,55,.14);
        color: #5d4037;
    }

    .password-wrap {
        position: relative;
    }

    .password-wrap input {
        padding-right: 48px;
    }

    .password-toggle {
        position: absolute;
        top: 50%;
        right: 12px;
        transform: translateY(-50%);
        border: 0;
        background: transparent;
        color: #766e69;
        cursor: pointer;
        padding: 6px;
    }

    .password-toggle:hover {
        color: #556b2f;
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
                        <i class="fa-solid fa-user-plus"></i>
                        TEACHER MANAGEMENT
                    </span>

                    <h1>Add Teacher</h1>

                    <p>
                        Create a new teacher account and profile.
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

            <section class="teacher-edit-card">

                <header class="teacher-edit-head">

                    <span>CREATE TEACHER ACCOUNT</span>

                    <h2>
                        Teacher Information
                    </h2>

                </header>

                <div class="teacher-edit-body">

                    <?php if ($error !== ''): ?>

                        <div class="alert alert-danger border-0 rounded-4">

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

                        <div class="teacher-edit-grid">

                            <div class="teacher-edit-field">

                                <label for="full_name">

                                    Full Name

                                    <span class="text-danger">*</span>

                                </label>

                                <input
                                    id="full_name"
                                    type="text"
                                    name="full_name"
                                    value="<?= add_teacher_e($fullName) ?>"
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
                                    value="<?= add_teacher_e($email) ?>"
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
                                    value="<?= add_teacher_e($phone) ?>"
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
                                    value="<?= add_teacher_e($mobile) ?>"
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
                                    value="<?= add_teacher_e($dob) ?>"
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
                                    value="<?= add_teacher_e($qualification) ?>"
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
                                    value="<?= add_teacher_e($experience) ?>"
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
                                ><?= add_teacher_e($address) ?></textarea>

                            </div>


                            <div class="teacher-edit-field">

                                <label for="password">

                                    Password

                                    <span class="text-danger">*</span>

                                </label>

                                <div class="password-wrap">

                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        minlength="8"
                                        maxlength="255"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        id="togglePassword"
                                        aria-label="Show password"
                                    >

                                        <i class="fa-solid fa-eye"></i>

                                    </button>

                                </div>

                                <small class="teacher-help">
                                    Minimum 8 characters.
                                </small>

                            </div>


                            <div class="teacher-edit-field">

                                <label for="password_confirmation">

                                    Confirm Password

                                    <span class="text-danger">*</span>

                                </label>

                                <div class="password-wrap">

                                    <input
                                        id="password_confirmation"
                                        type="password"
                                        name="password_confirmation"
                                        minlength="8"
                                        maxlength="255"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        id="toggleConfirmPassword"
                                        aria-label="Show confirm password"
                                    >

                                        <i class="fa-solid fa-eye"></i>

                                    </button>

                                </div>

                            </div>


                            <div class="teacher-edit-field full">

                                <label>
                                    Profile Photo
                                </label>

                                <div class="photo-panel">

                                    <div
                                        class="teacher-photo-placeholder"
                                        id="teacherPhotoPreview"
                                    >

                                        <i class="fa-solid fa-user"></i>

                                    </div>

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
                                        </small>

                                    </div>

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

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const form =
            document.getElementById(
                'teacherAddForm'
            );

        const button =
            document.getElementById(
                'teacherSaveButton'
            );

        const photo =
            document.getElementById(
                'profile_photo'
            );

        const preview =
            document.getElementById(
                'teacherPhotoPreview'
            );

        const password =
            document.getElementById(
                'password'
            );

        const passwordConfirmation =
            document.getElementById(
                'password_confirmation'
            );

        const togglePassword =
            document.getElementById(
                'togglePassword'
            );

        const toggleConfirmPassword =
            document.getElementById(
                'toggleConfirmPassword'
            );


        /*
        |--------------------------------------------------------------------------
        | PASSWORD TOGGLE
        |--------------------------------------------------------------------------
        */

        if (
            togglePassword &&
            password
        ) {

            togglePassword.addEventListener(
                'click',
                function () {

                    if (
                        password.type === 'password'
                    ) {

                        password.type = 'text';

                        togglePassword.innerHTML =
                            '<i class="fa-solid fa-eye-slash"></i>';

                    } else {

                        password.type = 'password';

                        togglePassword.innerHTML =
                            '<i class="fa-solid fa-eye"></i>';
                    }
                }
            );
        }


        if (
            toggleConfirmPassword &&
            passwordConfirmation
        ) {

            toggleConfirmPassword.addEventListener(
                'click',
                function () {

                    if (
                        passwordConfirmation.type === 'password'
                    ) {

                        passwordConfirmation.type =
                            'text';

                        toggleConfirmPassword.innerHTML =
                            '<i class="fa-solid fa-eye-slash"></i>';

                    } else {

                        passwordConfirmation.type =
                            'password';

                        toggleConfirmPassword.innerHTML =
                            '<i class="fa-solid fa-eye"></i>';
                    }
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PHOTO VALIDATION + PREVIEW
        |--------------------------------------------------------------------------
        */

        if (photo) {

            photo.addEventListener(
                'change',
                function () {

                    const file =
                        photo.files[0];

                    if (!file) {

                        preview.innerHTML =
                            '<i class="fa-solid fa-user"></i>';

                        return;
                    }

                    const allowedTypes = [
                        'image/jpeg',
                        'image/png',
                        'image/webp'
                    ];

                    if (
                        !allowedTypes.includes(
                            file.type
                        )
                    ) {

                        alert(
                            'Only JPG, PNG and WebP images are allowed.'
                        );

                        photo.value = '';

                        preview.innerHTML =
                            '<i class="fa-solid fa-user"></i>';

                        return;
                    }

                    if (
                        file.size >
                        5 * 1024 * 1024
                    ) {

                        alert(
                            'Profile photo must be 5 MB or smaller.'
                        );

                        photo.value = '';

                        preview.innerHTML =
                            '<i class="fa-solid fa-user"></i>';

                        return;
                    }

                    const reader =
                        new FileReader();

                    reader.onload =
                        function (event) {

                            preview.innerHTML =
                                '<img src="' +
                                event.target.result +
                                '" alt="Teacher Photo Preview" ' +
                                'style="width:100%;height:100%;object-fit:cover;">';
                        };

                    reader.readAsDataURL(file);
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | FORM SUBMIT
        |--------------------------------------------------------------------------
        */

        if (
            form &&
            button
        ) {

            form.addEventListener(
                'submit',
                function (event) {

                    if (!form.checkValidity()) {

                        return;
                    }

                    if (
                        password &&
                        passwordConfirmation &&
                        password.value !==
                        passwordConfirmation.value
                    ) {

                        event.preventDefault();

                        alert(
                            'Password and confirm password do not match.'
                        );

                        passwordConfirmation.focus();

                        return;
                    }

                    button.disabled = true;

                    button.innerHTML =
                        '<i class="fa-solid fa-spinner fa-spin"></i> ' +
                        'Creating Teacher...';
                }
            );
        }

    }
);

</script>


<?php

include "../includes/footer.php";

?>