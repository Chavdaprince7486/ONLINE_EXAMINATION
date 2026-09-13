<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

require_login('teacher');

$teacherId = current_user_id();

$message = '';
$error = '';

function teacher_profile_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function teacher_profile_date(mixed $value): string
{
    if (empty($value)) {
        return '—';
    }

    try {
        return (
            new DateTimeImmutable(
                (string)$value
            )
        )->format(
            'd M Y'
        );
    } catch (Throwable) {
        return '—';
    }
}

/*
|--------------------------------------------------------------------------
| Load current profile
|--------------------------------------------------------------------------
*/

try {

    $teacherStmt = $conn->prepare(
        "
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
        "
    );

    $teacherStmt->execute([
        $teacherId
    ]);

    $teacher = $teacherStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$teacher) {
        session_destroy();

        header(
            'Location: ../auth/login.php'
        );

        exit;
    }

} catch (Throwable $exception) {

    error_log(
        'ExamSphere teacher profile load failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to load your profile.'
    );
}

/*
|--------------------------------------------------------------------------
| Profile update
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (
            !verify_csrf_token(
                $_POST['csrf_token'] ?? null
            )
        ) {
            throw new RuntimeException(
                'Security verification failed. Refresh the page and try again.'
            );
        }

        $fullName =
            trim(
                (string)(
                    $_POST['full_name'] ?? ''
                )
            );

        $mobile =
            trim(
                (string)(
                    $_POST['mobile'] ?? ''
                )
            );

        $gender =
            trim(
                (string)(
                    $_POST['gender'] ?? ''
                )
            );

        $dob =
            trim(
                (string)(
                    $_POST['dob'] ?? ''
                )
            );

        $qualification =
            trim(
                (string)(
                    $_POST['qualification'] ?? ''
                )
            );

        $experience =
            trim(
                (string)(
                    $_POST['experience'] ?? ''
                )
            );

        $address =
            trim(
                (string)(
                    $_POST['address'] ?? ''
                )
            );

        if (
            $fullName === '' ||
            mb_strlen($fullName) > 100
        ) {
            throw new RuntimeException(
                'Please enter a valid full name.'
            );
        }

        if (
            $mobile !== '' &&
            !preg_match(
                '/^[0-9+() -]{7,15}$/',
                $mobile
            )
        ) {
            throw new RuntimeException(
                'Please enter a valid mobile number.'
            );
        }

        if (
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
            throw new RuntimeException(
                'Please select a valid gender.'
            );
        }

        $dobValue = null;

        if ($dob !== '') {

            $date = DateTimeImmutable::createFromFormat(
                'Y-m-d',
                $dob
            );

            if (
                !$date ||
                $date->format('Y-m-d') !== $dob
            ) {
                throw new RuntimeException(
                    'Please enter a valid date of birth.'
                );
            }

            if (
                $date > new DateTimeImmutable('today')
            ) {
                throw new RuntimeException(
                    'Date of birth cannot be in the future.'
                );
            }

            $dobValue = $dob;
        }

        if (
            mb_strlen($qualification) > 100
        ) {
            throw new RuntimeException(
                'Qualification is too long.'
            );
        }

        if (
            mb_strlen($experience) > 100
        ) {
            throw new RuntimeException(
                'Experience is too long.'
            );
        }

        if (
            mb_strlen($address) > 5000
        ) {
            throw new RuntimeException(
                'Address is too long.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Optional profile photo
        |--------------------------------------------------------------------------
        */

        $newProfilePhoto =
            (string)(
                $teacher['profile_photo'] ?? ''
            );

        $photoUploaded = false;
        $newPhotoPath = null;
        $oldPhotoPath = null;

        $photo =
            $_FILES['profile_photo'] ?? null;

        if (
            is_array($photo) &&
            (int)(
                $photo['error']
                ?? UPLOAD_ERR_NO_FILE
            ) !== UPLOAD_ERR_NO_FILE
        ) {

            if (
                (int)(
                    $photo['error']
                    ?? UPLOAD_ERR_NO_FILE
                ) !== UPLOAD_ERR_OK
            ) {
                throw new RuntimeException(
                    'Profile photo upload failed.'
                );
            }

            if (
                (int)(
                    $photo['size'] ?? 0
                ) > 2 * 1024 * 1024
            ) {
                throw new RuntimeException(
                    'Profile photo must be 2 MB or smaller.'
                );
            }

            $tmpName =
                (string)(
                    $photo['tmp_name'] ?? ''
                );

            if (
                $tmpName === '' ||
                !is_uploaded_file($tmpName)
            ) {
                throw new RuntimeException(
                    'Invalid profile photo upload.'
                );
            }

            $finfo =
                new finfo(
                    FILEINFO_MIME_TYPE
                );

            $mime =
                $finfo->file(
                    $tmpName
                );

            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            if (
                !isset(
                    $allowed[$mime]
                )
            ) {
                throw new RuntimeException(
                    'Only JPG, PNG and WEBP profile photos are allowed.'
                );
            }

            $uploadDirectory =
                dirname(__DIR__) .
                DIRECTORY_SEPARATOR .
                'uploads' .
                DIRECTORY_SEPARATOR .
                'teachers';

            if (
                !is_dir($uploadDirectory) &&
                !mkdir(
                    $uploadDirectory,
                    0755,
                    true
                )
            ) {
                throw new RuntimeException(
                    'Unable to prepare the profile photo folder.'
                );
            }

            if (
                !is_writable(
                    $uploadDirectory
                )
            ) {
                throw new RuntimeException(
                    'The profile photo folder is not writable.'
                );
            }

            $storedName =
                'teacher_' .
                $teacherId .
                '_' .
                bin2hex(
                    random_bytes(8)
                ) .
                '.' .
                $allowed[$mime];

            $targetPath =
                $uploadDirectory .
                DIRECTORY_SEPARATOR .
                $storedName;

            if (
                !move_uploaded_file(
                    $tmpName,
                    $targetPath
                )
            ) {
                throw new RuntimeException(
                    'Unable to save profile photo.'
                );
            }

            $newProfilePhoto =
                $storedName;

            $photoUploaded = true;
            $newPhotoPath = $targetPath;

            if (
                !empty(
                    $teacher['profile_photo']
                )
            ) {
                $oldPhotoPath =
                    $uploadDirectory .
                    DIRECTORY_SEPARATOR .
                    basename(
                        (string)$teacher[
                            'profile_photo'
                        ]
                    );
            }
        }

        $save = $conn->prepare(
            "
            UPDATE teachers
            SET
                full_name = ?,
                phone = ?,
                mobile = ?,
                gender = ?,
                dob = ?,
                qualification = ?,
                experience = ?,
                address = ?,
                profile_photo = ?
            WHERE
                id = ?
            "
        );

        try {

            $save->execute([
                $fullName,
                $mobile !== ''
                    ? $mobile
                    : null,
                $mobile !== ''
                    ? $mobile
                    : null,
                $gender !== ''
                    ? $gender
                    : null,
                $dobValue,
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
                $teacherId
            ]);

        } catch (Throwable $databaseException) {

            if (
                $newPhotoPath &&
                is_file($newPhotoPath)
            ) {
                @unlink(
                    $newPhotoPath
                );
            }

            throw $databaseException;
        }

        if (
            $photoUploaded &&
            $oldPhotoPath &&
            is_file($oldPhotoPath)
        ) {
            @unlink(
                $oldPhotoPath
            );
        }

        $_SESSION['user_name'] =
            $fullName;

        $message =
            'Your faculty profile has been updated successfully.';

        /*
        |--------------------------------------------------------------------------
        | Reload saved profile
        |--------------------------------------------------------------------------
        */

        $teacherStmt->execute([
            $teacherId
        ]);

        $teacher =
            $teacherStmt->fetch(
                PDO::FETCH_ASSOC
            );

    } catch (Throwable $exception) {

        error_log(
            'ExamSphere teacher profile update failed: ' .
            $exception->getMessage()
        );

        $error =
            $exception->getMessage();
    }
}

$initials =
    strtoupper(
        implode(
            '',
            array_slice(
                preg_split(
                    '/\s+/',
                    trim(
                        (string)$teacher['full_name']
                    )
                ) ?: [],
                0,
                2
            )
        )
    );

if ($initials === '') {
    $initials = 'TE';
}

$photoUrl = '';

if (
    !empty(
        $teacher['profile_photo']
    )
) {
    $photoUrl =
        '../uploads/teachers/' .
        rawurlencode(
            basename(
                (string)$teacher['profile_photo']
            )
        );
}

?>
<!doctype html>
<html lang="en">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<meta
    name="theme-color"
    content="#f5f5dc"
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
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="../assets/css/portal.css"
>

<style>

:root{
    --earth:#5d4037;
    --earth-dark:#422d26;
    --olive:#556b2f;
    --cream:#f5f5dc;
    --page:#f6f3eb;
    --text:#352b26;
    --muted:#867a71;
    --line:#e7dfd5;
    --soft:#fbfaf6;
    --white:#fff;
    --green:#4f7040;
    --red:#985047;
}

*{
    box-sizing:border-box;
}

body.portal-body{
    margin:0;
    color:var(--text);
    background:
        radial-gradient(
            circle at 8% 5%,
            rgba(85,107,47,.08),
            transparent 25%
        ),
        linear-gradient(
            135deg,
            #faf8f2,
            #efebe4
        );
    font-family:'Poppins',sans-serif;
}

.profile-page{
    width:min(
        1240px,
        calc(100vw - 22px)
    );
    margin:0 auto;
    padding:20px 0 58px;
}

.profile-header{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:20px;
    margin-bottom:20px;
}

.kicker{
    color:var(--olive);
    font-size:.68rem;
    letter-spacing:.14em;
    font-weight:900;
}

.profile-header h1{
    margin:7px 0 4px;
    color:var(--earth);
    font-size:2.15rem;
    font-weight:900;
    letter-spacing:-.035em;
}

.profile-header p{
    margin:0;
    color:var(--muted);
    font-size:.72rem;
}

.profile-card{
    overflow:hidden;
    border:1px solid rgba(93,64,55,.08);
    border-radius:20px;
    background:rgba(255,255,255,.90);
    box-shadow:
        0 18px 46px rgba(60,44,36,.08);
}

.profile-cover{
    padding:26px;
    background:
        linear-gradient(
            135deg,
            #f4efe5,
            #faf8f2
        );
    border-bottom:1px solid var(--line);
}

.profile-identity{
    display:flex;
    align-items:center;
    gap:16px;
}

.avatar{
    position:relative;
    width:100px;
    height:100px;
    flex:0 0 100px;
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:24px;
    background:var(--earth);
    color:#fff;
    font-size:1.75rem;
    font-weight:900;
    box-shadow:
        0 12px 25px rgba(93,64,55,.18);
}

.avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.identity h2{
    margin:0;
    color:var(--earth);
    font-size:1.32rem;
    font-weight:900;
}

.identity p{
    margin:4px 0 0;
    color:var(--muted);
    font-size:.76rem;
}

.status{
    margin-left:auto;
    padding:7px 10px;
    border-radius:999px;
    background:#eaf3e6;
    color:var(--green);
    font-size:.58rem;
    font-weight:900;
}

.profile-body{
    padding:26px;
}

.alert{
    border-radius:12px;
    font-size:.71rem;
}

.section-title{
    margin-bottom:13px;
}

.section-title span{
    color:var(--olive);
    font-size:.60rem;
    font-weight:900;
    letter-spacing:.12em;
}

.section-title h3{
    margin:4px 0 0;
    color:var(--earth);
    font-size:1.08rem;
    font-weight:900;
}

.form-grid{
    display:grid;
    grid-template-columns:
        repeat(2,minmax(0,1fr));
    gap:15px;
}

.field label{
    display:block;
    margin-bottom:6px;
    color:var(--earth);
    font-size:.64rem;
    font-weight:800;
}

.control{
    width:100%;
    min-height:50px;
    padding:11px 13px;
    border:1px solid #d8cec3;
    box-shadow:0 2px 8px rgba(62,45,37,.025);
    border-radius:11px;
    background:#fff;
    color:var(--text);
    outline:none;
    font-size:.72rem;
}

textarea.control{
    min-height:125px;
    resize:vertical;
}

.control:focus{
    border-color:var(--olive);
    box-shadow:
        0 0 0 .2rem rgba(85,107,47,.10);
}

.control[readonly],
.control[disabled]{
    background:#f6f2eb;
    color:#8c8177;
}

.full{
    grid-column:1 / -1;
}

.help{
    margin-top:5px;
    color:var(--muted);
    font-size:.57rem;
}

.photo-field{
    padding:14px;
    border:1px dashed #d9d0c6;
    border-radius:13px;
    background:#fcfaf6;
}

.photo-field input{
    font-size:.76rem;
}

.actions{
    display:flex;
    justify-content:flex-end;
    gap:9px;
    padding-top:18px;
    margin-top:19px;
    border-top:1px solid var(--line);
}

.btn-save{
    min-height:48px;
    border:0;
    border-radius:11px;
    padding:0 17px;
    background:var(--earth);
    color:#fff;
    font-size:.71rem;
    font-weight:850;
}

.btn-save:hover{
    background:var(--earth-dark);
    color:#fff;
}

.info-strip{
    display:grid;
    grid-template-columns:
        repeat(4,minmax(0,1fr));
    gap:11px;
    margin-top:20px;
}

.info{
    padding:14px;
    border:1px solid var(--line);
    border-radius:14px;
    background:rgba(255,255,255,.82);
}

.info small{
    display:block;
    color:var(--muted);
    font-size:.57rem;
}

.info strong{
    display:block;
    margin-top:4px;
    color:var(--earth);
    font-size:.71rem;
}

@media(max-width:800px){

    .profile-page{
        width:calc(100vw - 14px);
    }

    .profile-header{
        align-items:flex-start;
        flex-direction:column;
    }

    .profile-header h1{
        font-size:1.5rem;
    }

    .info-strip{
        grid-template-columns:1fr 1fr;
    }

}

@media(max-width:600px){

    .profile-identity{
        align-items:flex-start;
        flex-direction:column;
    }

    .status{
        margin-left:0;
    }

    .form-grid{
        grid-template-columns:1fr;
    }

    .full{
        grid-column:auto;
    }

    .info-strip{
        grid-template-columns:1fr;
    }

    .actions{
        justify-content:stretch;
    }

    .btn-save{
        width:100%;
    }

}

</style>


<style id="teacher-profile-typography-upgrade">
.profile-page{
    padding-top:24px;
}
.profile-header{
    margin-bottom:24px;
}
.kicker{
    font-size:.78rem !important;
    font-weight:900 !important;
    letter-spacing:.12em !important;
}
.profile-header h1{
    font-size:2.35rem !important;
    font-weight:900 !important;
    line-height:1.12 !important;
}
.profile-header p{
    font-size:.88rem !important;
    font-weight:600 !important;
    line-height:1.65 !important;
}
.profile-cover{
    padding:30px !important;
}
.avatar{
    width:112px !important;
    height:112px !important;
    flex-basis:112px !important;
    font-size:2rem !important;
}
.identity h2{
    font-size:1.5rem !important;
    font-weight:900 !important;
}
.identity p{
    font-size:.88rem !important;
    font-weight:600 !important;
    line-height:1.55 !important;
}
.status{
    padding:9px 14px !important;
    font-size:.72rem !important;
    font-weight:900 !important;
}
.profile-body{
    padding:30px !important;
}
.alert{
    font-size:.86rem !important;
    font-weight:700 !important;
    line-height:1.55 !important;
}
.section-title span{
    font-size:.72rem !important;
    font-weight:900 !important;
}
.section-title h3{
    font-size:1.26rem !important;
    font-weight:900 !important;
}
.field label{
    font-size:.82rem !important;
    font-weight:900 !important;
    margin-bottom:8px !important;
}
.control{
    min-height:56px !important;
    padding:13px 15px !important;
    font-size:.88rem !important;
    font-weight:600 !important;
    border-radius:12px !important;
}
textarea.control{
    min-height:145px !important;
}
.help{
    font-size:.73rem !important;
    font-weight:600 !important;
    line-height:1.55 !important;
}
.photo-field{
    padding:17px !important;
}
.photo-field input{
    font-size:.84rem !important;
    font-weight:600 !important;
}
.btn-save{
    min-height:54px !important;
    padding:0 21px !important;
    font-size:.86rem !important;
    font-weight:900 !important;
}
.info-strip{
    margin-top:24px !important;
    gap:14px !important;
}
.info{
    padding:17px !important;
}
.info small{
    font-size:.71rem !important;
    font-weight:800 !important;
}
.info strong{
    margin-top:5px !important;
    font-size:.86rem !important;
    font-weight:900 !important;
}
@media(max-width:800px){
    .profile-header h1{font-size:1.85rem !important;}
    .profile-header p{font-size:.82rem !important;}
    .profile-body{padding:24px !important;}
}
@media(max-width:600px){
    .profile-page{padding-top:15px;}
    .profile-cover{padding:22px !important;}
    .profile-body{padding:20px !important;}
    .profile-header h1{font-size:1.65rem !important;}
    .identity h2{font-size:1.28rem !important;}
    .identity p{font-size:.8rem !important;}
    .control{font-size:.84rem !important;}
}
</style>

</head>

<body class="portal-body">

<div class="portal-layout">

<?php include 'includes/sidebar.php'; ?>

<main class="portal-main">

<div class="profile-page">

<header class="profile-header">

<div>

<div class="kicker">

<i class="fa-solid fa-user-gear me-1"></i>

FACULTY ACCOUNT

</div>

<h1>
    My Profile
</h1>

<p>
    Manage your faculty information and profile identity.
</p>

</div>

</header>


<?php if ($message !== ''): ?>

<div class="alert alert-success mb-3">

<i class="fa-solid fa-circle-check me-1"></i>

<?= teacher_profile_e(
    $message
) ?>

</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div class="alert alert-danger mb-3">

<i class="fa-solid fa-circle-exclamation me-1"></i>

<?= teacher_profile_e(
    $error
) ?>

</div>

<?php endif; ?>


<section class="profile-card">

<div class="profile-cover">

<div class="profile-identity">

<div class="avatar">

<?php if ($photoUrl !== ''): ?>

<img
    src="<?= teacher_profile_e(
        $photoUrl
    ) ?>"
    alt="Faculty profile photo"
>

<?php else: ?>

<?= teacher_profile_e(
    $initials
) ?>

<?php endif; ?>

</div>


<div class="identity">

<h2>
    <?= teacher_profile_e(
        $teacher['full_name']
    ) ?>
</h2>

<p>

<i class="fa-solid fa-id-badge me-1"></i>

<?= teacher_profile_e(
    $teacher['teacher_code']
) ?>

&nbsp; · &nbsp;

<i class="fa-solid fa-envelope me-1"></i>

<?= teacher_profile_e(
    $teacher['email']
) ?>

</p>

</div>


<span class="status">

<i class="fa-solid fa-circle me-1"></i>

<?= teacher_profile_e(
    $teacher['status']
) ?>

</span>

</div>

</div>


<div class="profile-body">

<div class="section-title">

<span>
    PROFILE INFORMATION
</span>

<h3>
    Personal & Academic Details
</h3>

</div>


<form
    method="post"
    enctype="multipart/form-data"
>

<?= csrf_field() ?>


<div class="form-grid">


<div class="field">

<label for="full_name">
    Full name
</label>

<input
    id="full_name"
    class="control"
    type="text"
    name="full_name"
    maxlength="100"
    required
    value="<?= teacher_profile_e(
        $teacher['full_name']
    ) ?>"
>

</div>


<div class="field">

<label for="email">
    Email address
</label>

<input
    id="email"
    class="control"
    type="email"
    value="<?= teacher_profile_e(
        $teacher['email']
    ) ?>"
    readonly
>

<div class="help">
    Your login email is managed by the account system.
</div>

</div>


<div class="field">

<label for="mobile">
    Mobile number
</label>

<input
    id="mobile"
    class="control"
    type="text"
    name="mobile"
    maxlength="15"
    inputmode="tel"
    value="<?= teacher_profile_e(
        $teacher['mobile']
            ?: ($teacher['phone'] ?? '')
    ) ?>"
    placeholder="Enter mobile number"
>

</div>


<div class="field">

<label for="gender">
    Gender
</label>

<select
    id="gender"
    class="control"
    name="gender"
>

<option value="">
    Prefer not to say
</option>

<?php foreach (
    [
        'Male',
        'Female',
        'Other'
    ] as $gender
): ?>

<option
    value="<?= teacher_profile_e(
        $gender
    ) ?>"
    <?= (string)(
        $teacher['gender'] ?? ''
    ) ===
        $gender
        ? 'selected'
        : ''
    ?>
>

<?= teacher_profile_e(
    $gender
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label for="dob">
    Date of birth
</label>

<input
    id="dob"
    class="control"
    type="date"
    name="dob"
    value="<?= teacher_profile_e(
        $teacher['dob']
    ) ?>"
>

</div>


<div class="field">

<label for="qualification">
    Qualification
</label>

<input
    id="qualification"
    class="control"
    type="text"
    name="qualification"
    maxlength="100"
    value="<?= teacher_profile_e(
        $teacher['qualification']
    ) ?>"
    placeholder="e.g. MCA, M.Sc., B.Ed."
>

</div>


<div class="field">

<label for="experience">
    Experience
</label>

<input
    id="experience"
    class="control"
    type="text"
    name="experience"
    maxlength="100"
    value="<?= teacher_profile_e(
        $teacher['experience']
    ) ?>"
    placeholder="e.g. 5 years"
>

</div>


<div class="field">

<label for="profile_photo">
    Profile photo
</label>

<div class="photo-field">

<input
    id="profile_photo"
    class="form-control"
    type="file"
    name="profile_photo"
    accept=".jpg,.jpeg,.png,.webp"
>

<div class="help">
    JPG, PNG or WEBP · Maximum 2 MB.
</div>

</div>

</div>


<div class="field full">

<label for="address">
    Address
</label>

<textarea
    id="address"
    class="control"
    name="address"
    maxlength="5000"
    placeholder="Enter your address"
><?= teacher_profile_e(
    $teacher['address']
) ?></textarea>

</div>


</div>


<div class="actions">

<button
    type="submit"
    class="btn btn-save"
>

<i class="fa-solid fa-floppy-disk me-1"></i>

Save Profile

</button>

</div>

</form>

</div>

</section>


<section class="info-strip">


<div class="info">

<small>
    Teacher Code
</small>

<strong>
    <?= teacher_profile_e(
        $teacher['teacher_code']
    ) ?>
</strong>

</div>


<div class="info">

<small>
    Account Status
</small>

<strong>
    <?= teacher_profile_e(
        $teacher['status']
    ) ?>
</strong>

</div>


<div class="info">

<small>
    Last Login
</small>

<strong>
    <?= teacher_profile_date(
        $teacher['last_login']
    ) ?>
</strong>

</div>


<div class="info">

<small>
    Member Since
</small>

<strong>
    <?= teacher_profile_date(
        $teacher['created_at']
    ) ?>
</strong>

</div>


</section>

</div>

</main>

</div>

</body>

</html>
