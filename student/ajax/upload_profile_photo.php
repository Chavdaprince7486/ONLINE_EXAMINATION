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
        CSRF CHECK
==================================================*/

$csrfToken =
    trim(
        (string) (
            $_POST['csrf_token']
            ?? ''
        )
    );

if (!verify_csrf_token($csrfToken)) {
    echo json_encode([
        "status"  => "error",
        "title"   => "Security Check Failed",
        "message" => "Your session security token is invalid. Please refresh the page."
    ]);
    exit();
}

/*==================================================
        FILE CHECK
==================================================*/

if (
    !isset($_FILES['profile_photo'])
) {

    echo json_encode([

        "status"  => "error",
        "title"   => "No File",
        "message" => "Please select an image."

    ]);

    exit();

}

$file = $_FILES['profile_photo'];

/*==================================================
        UPLOAD ERROR
==================================================*/

if (
    $file['error'] !== UPLOAD_ERR_OK
) {

    echo json_encode([

        "status"  => "error",
        "title"   => "Upload Failed",
        "message" => "Unable to upload image."

    ]);

    exit();

}

/*==================================================
        SIZE CHECK
==================================================*/

$maxSize = 2 * 1024 * 1024;

if (
    $file['size'] > $maxSize
) {

    echo json_encode([

        "status"  => "error",
        "title"   => "Large File",
        "message" => "Maximum size is 2 MB."

    ]);

    exit();

}

/*==================================================
        MIME TYPE VALIDATION
==================================================*/

$finfo = finfo_open(FILEINFO_MIME_TYPE);

$mimeType = finfo_file(
    $finfo,
    $file['tmp_name']
);

finfo_close($finfo);

$allowedMimeTypes = [

    "image/jpeg",
    "image/jpg",
    "image/png"

];

if (
    !in_array(
        $mimeType,
        $allowedMimeTypes
    )
) {

    echo json_encode([

        "status"  => "error",
        "title"   => "Invalid Image",
        "message" => "Only JPG, JPEG and PNG images are allowed."

    ]);

    exit();

}

/*==================================================
        EXTENSION VALIDATION
==================================================*/

$extension = strtolower(

    pathinfo(
        $file['name'],
        PATHINFO_EXTENSION
    )

);

$allowedExtensions = [

    "jpg",
    "jpeg",
    "png"

];

if (
    !in_array(
        $extension,
        $allowedExtensions
    )
) {

    echo json_encode([

        "status"  => "error",
        "title"   => "Invalid Extension",
        "message" => "Unsupported image format."

    ]);

    exit();

}

/*==================================================
        GET STUDENT DETAILS
==================================================*/

$studentId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
SELECT
student_code,
profile_photo
FROM students
WHERE id=?
LIMIT 1
");

$stmt->execute([$studentId]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {

    echo json_encode([

        "status"  => "error",
        "title"   => "Student Not Found",
        "message" => "Unable to find your account."

    ]);

    exit();

}

/*==================================================
        UPLOAD DIRECTORY
==================================================*/

$uploadDir = "../../uploads/students/";

if (!is_dir($uploadDir)) {

    mkdir(
        $uploadDir,
        0777,
        true
    );

}

/*==================================================
        AUTO FILE NAME
==================================================*/

$newFileName =

$student['student_code'] .

"_" .

time() .

"." .

$extension;

$destination =

$uploadDir .

$newFileName;

/*==================================================
        MOVE FILE
==================================================*/

if (

!move_uploaded_file(

$file['tmp_name'],

$destination

)

) {

echo json_encode([

"status"=>"error",

"title"=>"Upload Failed",

"message"=>"Unable to save image."

]);

exit();

}

/*==================================================
        DELETE OLD PHOTO AFTER NEW FILE EXISTS
==================================================*/

if (!empty($student['profile_photo'])) {

$oldPhotoName = basename((string) $student['profile_photo']);
$oldPhoto = $uploadDir . $oldPhotoName;

if ($oldPhoto !== $destination && file_exists($oldPhoto) && is_file($oldPhoto)) {
    unlink($oldPhoto);
}

}


/*==================================================
        UPDATE DATABASE
==================================================*/


$update = $conn->prepare("

UPDATE students

SET profile_photo=?

WHERE id=?

");

$update->execute([

$newFileName,

$studentId

]);

/*==================================================
        RETURN IMAGE PATH
==================================================*/

$imagePath =

"../uploads/students/" .

$newFileName;

/*==================================================
        SUCCESS RESPONSE
==================================================*/

echo json_encode([

"status"=>"success",

"title"=>"Success",

"message"=>"Profile photo updated successfully.",

"photo"=>$imagePath

]);

exit();

/*==================================================
        END
==================================================*/
?>