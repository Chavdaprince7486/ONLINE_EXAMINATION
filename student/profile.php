<?php
session_start();

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['user_role'] !== 'student'
){
    header("Location: ../auth/login.php");
    exit();
}

require_once "../config/config.php";

$studentId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
SELECT *
FROM students
WHERE id = ?
LIMIT 1
");

$stmt->execute([$studentId]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$student){

    session_destroy();

    header("Location: ../auth/login.php");

    exit();

}

$profileStats = [
    'attempted' => 0,
    'completed' => 0,
    'average_score' => 0.00,
    'best_score' => 0.00
];

try {

    $statsQuery = $conn->prepare(
        "SELECT
            COUNT(*) AS attempted,
            COALESCE(SUM(CASE WHEN result_status IN ('Pass','Fail') THEN 1 ELSE 0 END),0) AS completed,
            COALESCE(AVG(CASE WHEN result_status IN ('Pass','Fail') THEN percentage ELSE NULL END),0) AS average_score,
            COALESCE(MAX(CASE WHEN result_status IN ('Pass','Fail') THEN percentage ELSE NULL END),0) AS best_score
        FROM results
        WHERE student_id = ?"
    );

    $statsQuery->execute([$studentId]);
    $statsRow = $statsQuery->fetch(PDO::FETCH_ASSOC);

    if (is_array($statsRow)) {
        $profileStats['attempted'] = (int)($statsRow['attempted'] ?? 0);
        $profileStats['completed'] = (int)($statsRow['completed'] ?? 0);
        $profileStats['average_score'] = round((float)($statsRow['average_score'] ?? 0), 2);
        $profileStats['best_score'] = round((float)($statsRow['best_score'] ?? 0), 2);
    }

} catch (Throwable $exception) {
    error_log('Student profile stats query failed: ' . $exception->getMessage());
}

$profilePhoto = "../assets/images/default-user.png";

if(
!empty($student['profile_photo']) &&
file_exists("../uploads/students/".$student['profile_photo'])
){

    $profilePhoto =
    "../uploads/students/".
    $student['profile_photo'];

}
?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<meta
name="csrf-token"
content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

<title>

My Profile | ExamSphere

</title>

<link
href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
rel="stylesheet">

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

<link
rel="stylesheet"
href="assets/css/profile.css">

<link rel="stylesheet" href="assets/css/dashboard.css">

</head>

<body>

<?php include "includes/navbar.php"; ?>

<div class="main-wrapper">

<div class="profile-page">

<!-- ============================= -->

<div class="profile-banner">

<div class="banner-overlay"></div>

<div class="profile-header">

<div class="profile-left">

<div class="profile-image">

<img
src="<?php echo $profilePhoto; ?>"
id="profilePreview">

<label
for="profilePhoto"
class="upload-photo">

<i class="fa-solid fa-camera"></i>

</label>

<input
type="file"
id="profilePhoto"
hidden>

</div>

<div class="profile-user">

<h2>

<?php

echo htmlspecialchars(
$student['full_name']
);

?>

</h2>

<span class="student-id">

<?php

echo htmlspecialchars(
$student['student_code']
);

?>

</span>

<div class="profile-meta">

<span>

<i class="fa-solid fa-envelope"></i>

<?php

echo htmlspecialchars(
$student['email']
);

?>

</span>

<span>

<i class="fa-solid fa-phone"></i>

<?php

echo htmlspecialchars(
$student['mobile']
);

?>

</span>

<span>

<i class="fa-solid fa-location-dot"></i>

<?php

echo htmlspecialchars(
$student['city']
);

?>

</span>

</div>

</div>

</div>

<div class="profile-right">

<button
class="edit-profile-btn">

<i class="fa-solid fa-pen"></i>

Edit Profile

</button>

</div>

</div>

</div>

<!-- ==========================================
        QUICK STATS
========================================== -->

<div class="stats-section">

    <div class="stat-card">

        <div class="stat-icon brown">

            <i class="fa-solid fa-file-circle-check"></i>

        </div>

        <div class="stat-content">

            <h3><?php echo (int) $profileStats['attempted']; ?></h3>

            <p>Exams Attempted</p>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon green">

            <i class="fa-solid fa-circle-check"></i>

        </div>

        <div class="stat-content">

            <h3><?php echo (int) $profileStats['completed']; ?></h3>

            <p>Completed</p>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon gold">

            <i class="fa-solid fa-chart-line"></i>

        </div>

        <div class="stat-content">

            <h3><?php echo number_format((float) $profileStats['average_score'], 2); ?>%</h3>

            <p>Average Score</p>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon blue">

            <i class="fa-solid fa-ranking-star"></i>

        </div>

        <div class="stat-content">

            <h3><?php echo number_format((float) $profileStats['best_score'], 2); ?>%</h3>

            <p>Best Score</p>

        </div>

    </div>

</div>

<!-- ==========================================
        PROFILE TABS
========================================== -->

<div class="profile-tabs">

    <button class="tab-btn active">

        <i class="fa-solid fa-user"></i>

        Personal

    </button>

    <button class="tab-btn">

        <i class="fa-solid fa-graduation-cap"></i>

        Academic

    </button>

    <button class="tab-btn">

        <i class="fa-solid fa-chart-column"></i>

        Performance

    </button>

    <button class="tab-btn">

        <i class="fa-solid fa-lock"></i>

        Security

    </button>

    <button class="tab-btn">

        <i class="fa-solid fa-gear"></i>

        Settings

    </button>

</div>

<!-- ==========================================
        CONTENT GRID
========================================== -->

<div class="profile-content">

<div class="left-panel">

<div class="content-card">

<div class="card-title">

<i class="fa-solid fa-user"></i>

<h3>

Personal Information

</h3>

</div>

<form id="profileForm"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

<div class="form-grid">

<div class="form-group">

<label>

Full Name

</label>

<input
type="text"
name="full_name"
value="<?php echo htmlspecialchars($student['full_name']); ?>">

</div>

<div class="form-group">

<label>

Student Code

</label>

<input
type="text"
value="<?php echo htmlspecialchars($student['student_code']); ?>"
readonly>

</div>

<div class="form-group">

<label>

Email

</label>

<input
type="email"
value="<?php echo htmlspecialchars($student['email']); ?>"
readonly>

</div>

<div class="form-group">

<label>

Mobile

</label>

<input
type="text"
name="mobile"
value="<?php echo htmlspecialchars($student['mobile']); ?>">

</div>

<div class="form-group">

<label>

Gender

</label>

<select name="gender">

<option value="">Select</option>

<option value="Male"
<?= $student['gender']=="Male"?"selected":""; ?>>

Male

</option>

<option value="Female"
<?= $student['gender']=="Female"?"selected":""; ?>>

Female

</option>

<option value="Other"
<?= $student['gender']=="Other"?"selected":""; ?>>

Other

</option>

</select>

</div>

<div class="form-group">

<label>

Date Of Birth

</label>

<input
type="date"
name="dob"
value="<?php echo $student['dob']; ?>">

</div>

                <div class="form-group full-width">

                    <label>

                        Address

                    </label>

                    <textarea
                    name="address"
                    rows="4"><?php

                    echo htmlspecialchars(

                    $student['address']

                    );

                    ?></textarea>

                </div>

                <div class="form-group">

                    <label>

                        City

                    </label>

                    <input
                    type="text"
                    name="city"
                    value="<?php echo htmlspecialchars($student['city']); ?>">

                </div>

                <div class="form-group">

                    <label>

                        State

                    </label>

                    <input
                    type="text"
                    name="state"
                    value="<?php echo htmlspecialchars($student['state']); ?>">

                </div>

                <div class="form-group">

                    <label>

                        Pincode

                    </label>

                    <input
                    type="text"
                    name="pincode"
                    value="<?php echo htmlspecialchars($student['pincode']); ?>">

                </div>

            </div>

            <div class="form-actions">

                <button
                type="submit"
                class="primary-btn">

                    <i class="fa-solid fa-floppy-disk"></i>

                    Save Changes

                </button>

            </div>

            </form>

        </div>

    </div>

    <!-- ==============================
            RIGHT SIDEBAR
    =============================== -->

    <div class="right-panel">

        <div class="content-card">

            <div class="card-title">

                <i class="fa-solid fa-user-shield"></i>

                <h3>

                    Account Status

                </h3>

            </div>

            <div class="status-list">

                <div class="status-item">

                    <span>Student ID</span>

                    <strong>

                        <?php echo htmlspecialchars($student['student_code']); ?>

                    </strong>

                </div>

                <div class="status-item">

                    <span>Status</span>

                    <strong class="active">

                        <?php echo htmlspecialchars($student['status']); ?>

                    </strong>

                </div>

                <div class="status-item">

                    <span>Email</span>

                    <strong>

                        <?php

                        echo ($student['email_verified']=="Yes")

                        ?

                        "Verified"

                        :

                        "Pending";

                        ?>

                    </strong>

                </div>

                <div class="status-item">

                    <span>Last Login</span>

                    <strong>

                        <?php

                        if(!empty($student['last_login'])){

                            echo date(

                            "d M Y h:i A",

                            strtotime($student['last_login'])

                            );

                        }else{

                            echo "First Login";

                        }

                        ?>

                    </strong>

                </div>

            </div>

        </div>

        <div class="content-card">

            <div class="card-title">

                <i class="fa-solid fa-lock"></i>

                <h3>

                    Security

                </h3>

            </div>

            <button
            class="primary-btn full-btn">

                <i class="fa-solid fa-key"></i>

                Change Password

            </button>

        </div>

    </div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="assets/js/profile.js"></script>

</body>

</html>