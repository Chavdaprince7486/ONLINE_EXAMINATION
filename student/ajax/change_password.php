<?php

session_start();

header("Content-Type: application/json");

require_once "../../config/config.php";

/*==================================================
        LOGIN CHECK
==================================================*/

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

/*==================================================
        READ JSON DATA
==================================================*/

$data = json_decode(

    file_get_contents("php://input"),

    true

);

$csrfToken =
    trim(
        (string) (
            $data['csrf_token']
            ?? ''
        )
    );

if (!verify_csrf_token($csrfToken)) {
    echo json_encode([
        "status"=>"error",
        "title"=>"Security Check Failed",
        "message"=>"Your session security token is invalid. Please refresh the page."
    ]);
    exit();
}

$currentPassword = trim($data['current_password'] ?? "");

$newPassword = trim($data['new_password'] ?? "");

$confirmPassword = trim($data['confirm_password'] ?? "");

/*==================================================
        VALIDATION
==================================================*/

if (

$currentPassword=="" ||

$newPassword=="" ||

$confirmPassword==""

){

echo json_encode([

"status"=>"error",

"title"=>"Validation Error",

"message"=>"All password fields are required."

]);

exit();

}

if(strlen($newPassword)<8){

echo json_encode([

"status"=>"error",

"title"=>"Weak Password",

"message"=>"Password must be at least 8 characters."

]);

exit();

}

if($newPassword!==$confirmPassword){

echo json_encode([

"status"=>"error",

"title"=>"Password Mismatch",

"message"=>"New password and confirm password do not match."

]);

exit();

}

$studentId=(int)$_SESSION['user_id'];

try{

/*==================================================
        GET STUDENT PASSWORD
==================================================*/

$stmt = $conn->prepare("
SELECT
password
FROM students
WHERE id=?
LIMIT 1
");

$stmt->execute([$studentId]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$student){

    echo json_encode([

        "status"=>"error",

        "title"=>"Student Not Found",

        "message"=>"Unable to find your account."

    ]);

    exit();

}

/*==================================================
        VERIFY CURRENT PASSWORD
==================================================*/

if(

!password_verify(

$currentPassword,

$student['password']

)

){

    echo json_encode([

        "status"=>"error",

        "title"=>"Incorrect Password",

        "message"=>"Current password is incorrect."

    ]);

    exit();

}

/*==================================================
        CHECK SAME PASSWORD
==================================================*/

if(

password_verify(

$newPassword,

$student['password']

)

){

    echo json_encode([

        "status"=>"error",

        "title"=>"Same Password",

        "message"=>"New password must be different from the current password."

    ]);

    exit();

}

/*==================================================
        HASH NEW PASSWORD
==================================================*/

$hashedPassword = password_hash(

    $newPassword,

    PASSWORD_DEFAULT

);

/*==================================================
        UPDATE PASSWORD
==================================================*/

$update = $conn->prepare("
UPDATE students
SET password=?
WHERE id=?
");

$result = $update->execute([

    $hashedPassword,

    $studentId

]);

/*==================================================
        RESPONSE
==================================================*/

if($result){

    echo json_encode([

        "status"=>"success",

        "title"=>"Password Updated",

        "message"=>"Your password has been changed successfully."

    ]);

}else{

    echo json_encode([

        "status"=>"error",

        "title"=>"Update Failed",

        "message"=>"Unable to update your password."

    ]);

}

}catch(PDOException $e){

    echo json_encode([

        "status"=>"error",

        "title"=>"Database Error",

        "message"=>"Something went wrong while updating the password."

    ]);

}

exit();