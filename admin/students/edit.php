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

$page_title = 'Edit Student';
$error = '';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id <= 0) {
    $_SESSION['error'] = 'Invalid student.';
    header('Location: index.php');
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT
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
            email_verified,
            status,
            last_login,
            created_at
        FROM students
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $_SESSION['error'] = 'Student not found.';
        header('Location: index.php');
        exit;
    }
} catch (Throwable $exception) {
    error_log('Student edit fetch failed: ' . $exception->getMessage());
    $_SESSION['error'] = 'Unable to load student details.';
    header('Location: index.php');
    exit;
}

$fullName = (string)$student['full_name'];
$email = (string)$student['email'];
$mobile = (string)$student['mobile'];
$gender = (string)($student['gender'] ?? '');
$dob = (string)($student['dob'] ?? '');
$address = (string)($student['address'] ?? '');
$city = (string)($student['city'] ?? '');
$state = (string)($student['state'] ?? '');
$pincode = (string)($student['pincode'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security verification failed. Please refresh the page and try again.';
    } else {

        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $mobile = trim((string)($_POST['mobile'] ?? ''));
        $gender = trim((string)($_POST['gender'] ?? ''));
        $dob = trim((string)($_POST['dob'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $city = trim((string)($_POST['city'] ?? ''));
        $state = trim((string)($_POST['state'] ?? ''));
        $pincode = trim((string)($_POST['pincode'] ?? ''));

        if ($fullName === '') {
            $error = 'Full name is required.';
        } elseif (mb_strlen($fullName) > 100) {
            $error = 'Full name cannot exceed 100 characters.';
        } elseif ($email === '') {
            $error = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (mb_strlen($email) > 150) {
            $error = 'Email address cannot exceed 150 characters.';
        } elseif ($mobile === '') {
            $error = 'Mobile number is required.';
        } elseif (mb_strlen($mobile) > 20) {
            $error = 'Mobile number cannot exceed 20 characters.';
        } elseif (
            $gender !== '' &&
            !in_array($gender, ['Male', 'Female', 'Other'], true)
        ) {
            $error = 'Invalid gender selected.';
        } elseif (
            $dob !== '' &&
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)
        ) {
            $error = 'Please enter a valid date of birth.';
        } elseif (
            $address !== '' &&
            mb_strlen($address) > 10000
        ) {
            $error = 'Address is too long.';
        } elseif (
            $city !== '' &&
            mb_strlen($city) > 80
        ) {
            $error = 'City cannot exceed 80 characters.';
        } elseif (
            $state !== '' &&
            mb_strlen($state) > 80
        ) {
            $error = 'State cannot exceed 80 characters.';
        } elseif (
            $pincode !== '' &&
            !preg_match('/^[0-9]{1,10}$/', $pincode)
        ) {
            $error = 'Pincode must contain only numbers and be at most 10 digits.';
        }

        if ($error === '' && $dob !== '') {

            $dobDate = DateTime::createFromFormat('Y-m-d', $dob);

            if (
                !$dobDate ||
                $dobDate->format('Y-m-d') !== $dob
            ) {
                $error = 'Please enter a valid date of birth.';
            } elseif ($dobDate > new DateTime('today')) {
                $error = 'Date of birth cannot be in the future.';
            }
        }

        if ($error === '') {

            try {

                $emailCheck = $conn->prepare("
                    SELECT id
                    FROM students
                    WHERE email = ?
                      AND id <> ?
                    LIMIT 1
                ");

                $emailCheck->execute([
                    $email,
                    $id
                ]);

                if ($emailCheck->fetch(PDO::FETCH_ASSOC)) {
                    $error = 'This email address is already registered to another student.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Student email validation failed: ' .
                    $exception->getMessage()
                );

                $error = 'Unable to validate the email address right now.';
            }
        }

        if ($error === '') {

            try {

                $update = $conn->prepare("
                    UPDATE students
                    SET
                        full_name = ?,
                        email = ?,
                        mobile = ?,
                        gender = ?,
                        dob = ?,
                        address = ?,
                        city = ?,
                        state = ?,
                        pincode = ?
                    WHERE id = ?
                ");

                $update->execute([
                    $fullName,
                    $email,
                    $mobile,
                    $gender !== '' ? $gender : null,
                    $dob !== '' ? $dob : null,
                    $address !== '' ? $address : null,
                    $city !== '' ? $city : null,
                    $state !== '' ? $state : null,
                    $pincode !== '' ? $pincode : null,
                    $id
                ]);

                $_SESSION['success'] =
                    'Student details updated successfully.';

                header('Location: view.php?id=' . $id);
                exit;

            } catch (PDOException $exception) {

                error_log(
                    'Student edit update failed: ' .
                    $exception->getMessage()
                );

                if (
                    isset($exception->errorInfo[1]) &&
                    (int)$exception->errorInfo[1] === 1062
                ) {
                    $error =
                        'This email address is already registered to another student.';
                } else {
                    $error =
                        'Unable to update the student details. Please try again.';
                }

            } catch (Throwable $exception) {

                error_log(
                    'Student edit update failed: ' .
                    $exception->getMessage()
                );

                $error =
                    'Unable to update the student details. Please try again.';
            }
        }
    }
}

function edit_student_e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function edit_student_date(?string $value): string
{
    if (!$value) {
        return 'Never';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d M Y, h:i A', $timestamp)
        : 'Never';
}

include '../includes/header.php';
?>

<style>

    .student-edit-page {
        max-width: 1050px;
        margin: 0 auto;
    }

    .student-edit-heading {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        align-items: flex-end;
        margin-bottom: 22px;
    }

    .student-edit-heading .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #556b2f;
        font-size: .76rem;
        font-weight: 800;
        letter-spacing: .12em;
        margin-bottom: 7px;
    }

    .student-edit-heading h1 {
        margin: 0;
    }

    .student-edit-heading p {
        margin: 6px 0 0;
        color: #746d68;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 12px;
        color: #5d4037;
        border: 1px solid rgba(93,64,55,.12);
        background: rgba(255,255,255,.86);
        text-decoration: none;
        font-weight: 700;
        white-space: nowrap;
        transition: .2s ease;
    }

    .back-link:hover {
        color: #fff;
        background: #5d4037;
        border-color: #5d4037;
        transform: translateY(-1px);
    }

    .student-edit-card {
        border: 1px solid rgba(93,64,55,.10);
        border-radius: 24px;
        background: rgba(255,255,255,.90);
        box-shadow: 0 20px 55px rgba(51,51,51,.08);
        overflow: hidden;
        backdrop-filter: blur(15px);
    }

    .edit-card-head {
        padding: 21px 23px;
        background: rgba(250,247,240,.66);
        border-bottom: 1px solid rgba(93,64,55,.08);
    }

    .edit-card-head span {
        display: block;
        color: #556b2f;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .10em;
        margin-bottom: 4px;
    }

    .edit-card-head h2 {
        margin: 0;
        font-size: 1.08rem;
        color: #333;
    }

    .edit-card-body {
        padding: 25px;
    }

    .edit-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .edit-field.full {
        grid-column: 1 / -1;
    }

    .edit-field label {
        display: block;
        color: #504945;
        font-size: .82rem;
        font-weight: 800;
        margin-bottom: 7px;
    }

    .edit-field input,
    .edit-field select,
    .edit-field textarea {
        width: 100%;
        border: 1px solid rgba(93,64,55,.15);
        border-radius: 12px;
        background: #fff;
        color: #34302e;
        padding: 11px 13px;
        outline: none;
        transition: .2s ease;
        font: inherit;
    }

    .edit-field textarea {
        min-height: 110px;
        resize: vertical;
    }

    .edit-field input:focus,
    .edit-field select:focus,
    .edit-field textarea:focus {
        border-color: #556b2f;
        box-shadow: 0 0 0 3px rgba(85,107,47,.10);
    }

    .field-help {
        display: block;
        margin-top: 5px;
        color: #827a75;
        font-size: .74rem;
    }

    .readonly-box {
        min-height: 43px;
        display: flex;
        align-items: center;
        padding: 10px 13px;
        border: 1px solid rgba(93,64,55,.09);
        border-radius: 12px;
        background: #faf8f4;
        color: #625a55;
        font-weight: 700;
    }

    .edit-alert {
        margin-bottom: 20px;
        border: 0;
        border-radius: 14px;
    }

    .edit-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid rgba(93,64,55,.08);
    }

    .btn-cancel-edit,
    .btn-save-edit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 44px;
        padding: 0 18px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 800;
    }

    .btn-cancel-edit {
        background: rgba(93,64,55,.08);
        color: #5d4037;
    }

    .btn-cancel-edit:hover {
        background: rgba(93,64,55,.14);
        color: #5d4037;
    }

    .btn-save-edit {
        background: #556b2f;
        color: #fff;
        cursor: pointer;
        border: 0;
        box-shadow: 0 10px 25px rgba(85,107,47,.18);
    }

    .btn-save-edit:hover {
        background: #465b27;
        color: #fff;
    }

    @media (max-width: 760px) {

        .student-edit-heading {
            align-items: flex-start;
            flex-direction: column;
        }

        .edit-grid {
            grid-template-columns: 1fr;
        }

        .edit-field.full {
            grid-column: auto;
        }

        .edit-footer {
            align-items: stretch;
            flex-direction: column-reverse;
        }

        .btn-cancel-edit,
        .btn-save-edit {
            width: 100%;
        }

    }

</style>

<div class="dashboard-wrapper">

    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">

        <?php include '../includes/navbar.php'; ?>

        <main class="dashboard-content student-edit-page">

            <section class="student-edit-heading">

                <div>

                    <span class="eyebrow">
                        <i class="fa-solid fa-user-pen"></i>
                        STUDENT MANAGEMENT
                    </span>

                    <h1>Edit Student</h1>

                    <p>
                        Update the student's registered profile information.
                    </p>

                </div>

                <a
                    href="view.php?id=<?= (int)$student['id'] ?>"
                    class="back-link"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Profile
                </a>

            </section>

            <section class="student-edit-card">

                <header class="edit-card-head">

                    <span>PROFILE DETAILS</span>

                    <h2>
                        <?= edit_student_e($student['full_name']) ?>
                    </h2>

                </header>

                <div class="edit-card-body">

                    <?php if ($error !== ''): ?>

                        <div class="alert alert-danger edit-alert">

                            <i class="fa-solid fa-circle-exclamation me-2"></i>

                            <?= edit_student_e($error) ?>

                        </div>

                    <?php endif; ?>

                    <form
                        method="post"
                        autocomplete="off"
                        novalidate
                    >

                        <?= csrf_field() ?>

                        <div class="edit-grid">

                            <div class="edit-field">

                                <label for="full_name">
                                    Full Name
                                </label>

                                <input
                                    id="full_name"
                                    type="text"
                                    name="full_name"
                                    value="<?= edit_student_e($fullName) ?>"
                                    maxlength="100"
                                    required
                                >

                            </div>

                            <div class="edit-field">

                                <label>
                                    Student Code
                                </label>

                                <div class="readonly-box">
                                    <?= edit_student_e(
                                        $student['student_code'] ?? '—'
                                    ) ?>
                                </div>

                                <small class="field-help">
                                    Student code is not changed from this form.
                                </small>

                            </div>

                            <div class="edit-field">

                                <label for="email">
                                    Email Address
                                </label>

                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="<?= edit_student_e($email) ?>"
                                    maxlength="150"
                                    required
                                >

                            </div>

                            <div class="edit-field">

                                <label for="mobile">
                                    Mobile Number
                                </label>

                                <input
                                    id="mobile"
                                    type="text"
                                    name="mobile"
                                    value="<?= edit_student_e($mobile) ?>"
                                    maxlength="20"
                                    required
                                >

                            </div>

                            <div class="edit-field">

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
                                        <?= $gender === 'Male' ? 'selected' : '' ?>
                                    >
                                        Male
                                    </option>

                                    <option
                                        value="Female"
                                        <?= $gender === 'Female' ? 'selected' : '' ?>
                                    >
                                        Female
                                    </option>

                                    <option
                                        value="Other"
                                        <?= $gender === 'Other' ? 'selected' : '' ?>
                                    >
                                        Other
                                    </option>

                                </select>

                            </div>

                            <div class="edit-field">

                                <label for="dob">
                                    Date of Birth
                                </label>

                                <input
                                    id="dob"
                                    type="date"
                                    name="dob"
                                    value="<?= edit_student_e($dob) ?>"
                                >

                            </div>

                            <div class="edit-field">

                                <label for="city">
                                    City
                                </label>

                                <input
                                    id="city"
                                    type="text"
                                    name="city"
                                    value="<?= edit_student_e($city) ?>"
                                    maxlength="80"
                                >

                            </div>

                            <div class="edit-field">

                                <label for="state">
                                    State
                                </label>

                                <input
                                    id="state"
                                    type="text"
                                    name="state"
                                    value="<?= edit_student_e($state) ?>"
                                    maxlength="80"
                                >

                            </div>

                            <div class="edit-field">

                                <label for="pincode">
                                    Pincode
                                </label>

                                <input
                                    id="pincode"
                                    type="text"
                                    name="pincode"
                                    value="<?= edit_student_e($pincode) ?>"
                                    maxlength="10"
                                    inputmode="numeric"
                                >

                            </div>

                            <div class="edit-field full">

                                <label for="address">
                                    Address
                                </label>

                                <textarea
                                    id="address"
                                    name="address"
                                    maxlength="10000"
                                ><?= edit_student_e($address) ?></textarea>

                            </div>

                            <div class="edit-field">

                                <label>
                                    Email Verification
                                </label>

                                <div class="readonly-box">
                                    <?= edit_student_e(
                                        $student['email_verified']
                                    ) ?>
                                </div>

                                <small class="field-help">
                                    Verification is managed separately.
                                </small>

                            </div>

                            <div class="edit-field">

                                <label>
                                    Account Status
                                </label>

                                <div class="readonly-box">
                                    <?= edit_student_e(
                                        $student['status']
                                    ) ?>
                                </div>

                                <small class="field-help">
                                    Use the status control to activate or
                                    deactivate the account.
                                </small>

                            </div>

                            <div class="edit-field">

                                <label>
                                    Last Login
                                </label>

                                <div class="readonly-box">
                                    <?= edit_student_e(
                                        edit_student_date(
                                            $student['last_login']
                                        )
                                    ) ?>
                                </div>

                            </div>

                            <div class="edit-field">

                                <label>
                                    Registered On
                                </label>

                                <div class="readonly-box">
                                    <?= edit_student_e(
                                        edit_student_date(
                                            $student['created_at']
                                        )
                                    ) ?>
                                </div>

                            </div>

                        </div>

                        <div class="edit-footer">

                            <a
                                href="view.php?id=<?= (int)$student['id'] ?>"
                                class="btn-cancel-edit"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="btn-save-edit"
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

<?php include '../includes/footer.php'; ?>