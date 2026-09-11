<?php

session_start();

header("Content-Type: application/json");

require_once "../../config/config.php";

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['user_role'] !== "student"
) {
    echo json_encode([
        "status"  => "error",
        "title"   => "Access Denied",
        "message" => "Please login again."
    ]);
    exit();
}

$csrfToken =
    trim(
        (string) (
            $_POST['csrf_token']
            ?? ''
        )
    );

if (!verify_csrf_token($csrfToken)) {
    echo json_encode([
        "status" => "error",
        "title" => "Security Check Failed",
        "message" => "Your session security token is invalid. Please refresh the page."
    ]);
    exit();
}

$studentId = (int)$_SESSION['user_id'];

$fullName = trim($_POST['full_name'] ?? "");
$mobile   = trim($_POST['mobile'] ?? "");
$gender   = trim($_POST['gender'] ?? "");
$dob      = trim($_POST['dob'] ?? "");
$address  = trim($_POST['address'] ?? "");
$city     = trim($_POST['city'] ?? "");
$state    = trim($_POST['state'] ?? "");
$pincode  = trim($_POST['pincode'] ?? "");

if ($fullName == "") {
    echo json_encode([
        "status"  => "error",
        "title"   => "Validation Error",
        "message" => "Full Name is required."
    ]);
    exit();
}

if (
    $mobile != "" &&
    !preg_match('/^[0-9]{10}$/', $mobile)
) {
    echo json_encode([
        "status"  => "error",
        "title"   => "Invalid Mobile",
        "message" => "Enter a valid 10 digit mobile number."
    ]);
    exit();
}

if (
    $pincode != "" &&
    !preg_match('/^[0-9]{6}$/', $pincode)
) {
    echo json_encode([
        "status"  => "error",
        "title"   => "Invalid Pincode",
        "message" => "Enter a valid 6 digit pincode."
    ]);
    exit();
}

try {

    /* Student Exists */

    $check = $conn->prepare("
        SELECT id
        FROM students
        WHERE id=?
        LIMIT 1
    ");

    $check->execute([$studentId]);

    if ($check->rowCount() == 0) {

        echo json_encode([
            "status"  => "error",
            "title"   => "Student Not Found",
            "message" => "Unable to find your account."
        ]);

        exit();
    }

    /* Duplicate Mobile */

    if ($mobile != "") {

        $mobileCheck = $conn->prepare("
            SELECT id
            FROM students
            WHERE mobile=?
            AND id!=?
            LIMIT 1
        ");

        $mobileCheck->execute([
            $mobile,
            $studentId
        ]);

        if ($mobileCheck->rowCount() > 0) {

            echo json_encode([
                "status"  => "error",
                "title"   => "Duplicate Mobile",
                "message" => "This mobile number is already registered."
            ]);

            exit();
        }
    }

    /* Update */

    $update = $conn->prepare("
        UPDATE students
        SET
            full_name=?,
            mobile=?,
            gender=?,
            dob=?,
            address=?,
            city=?,
            state=?,
            pincode=?
        WHERE id=?
    ");

    $result = $update->execute([
        $fullName,
        $mobile,
        $gender,
        $dob,
        $address,
        $city,
        $state,
        $pincode,
        $studentId
    ]);

    if ($result) {

        echo json_encode([
            "status"  => "success",
            "title"   => "Profile Updated",
            "message" => "Your profile has been updated successfully."
        ]);

    } else {

        echo json_encode([
            "status"  => "error",
            "title"   => "Update Failed",
            "message" => "Unable to update profile."
        ]);

    }

} catch (PDOException $e) {

    echo json_encode([
        "status"  => "error",
        "title"   => "Database Error",
        "message" => "Something went wrong while updating your profile."
    ]);

}

exit();