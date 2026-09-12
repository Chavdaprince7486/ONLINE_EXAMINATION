<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/auth.php';

header(
    'Content-Type: application/json; charset=UTF-8'
);

if (
    current_user_id() <= 0 ||
    current_user_role() !== 'student'
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Access Denied',

        'message' =>
            'Please login again.'

    ]);

    exit;
}

if (
    !verify_csrf_token(
        $_POST['csrf_token'] ?? null
    )
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Security Check Failed',

        'message' =>
            'Your session security token is invalid. Please refresh the page.'

    ]);

    exit;
}

if (
    !isset($_FILES['profile_photo']) ||
    !is_array($_FILES['profile_photo'])
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'No File',

        'message' =>
            'Please select an image.'

    ]);

    exit;
}

$file =
    $_FILES['profile_photo'];

if (
    ($file['error'] ??
        UPLOAD_ERR_NO_FILE
    ) !==
    UPLOAD_ERR_OK
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Upload Failed',

        'message' =>
            'Unable to upload the selected image.'

    ]);

    exit;
}

if (
    (int)$file['size'] >
    2 * 1024 * 1024
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Large File',

        'message' =>
            'Maximum image size is 2 MB.'

    ]);

    exit;
}

$finfo =
    finfo_open(
        FILEINFO_MIME_TYPE
    );

$mimeType =
    $finfo
        ? finfo_file(
            $finfo,
            (string)$file['tmp_name']
        )
        : false;

if ($finfo) {
    finfo_close($finfo);
}

$allowed = [

    'image/jpeg' =>
        'jpg',

    'image/png' =>
        'png'

];

if (
    !is_string($mimeType) ||
    !isset(
        $allowed[$mimeType]
    )
) {

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Invalid Image',

        'message' =>
            'Only JPG, JPEG and PNG images are allowed.'

    ]);

    exit;
}

$studentId =
    current_user_id();

try {

    $stmt =
        $conn->prepare(
            'SELECT
                student_code,
                profile_photo
             FROM students
             WHERE id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $studentId
    ]);

    $student =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$student) {

        echo json_encode([

            'status' =>
                'error',

            'title' =>
                'Student Not Found',

            'message' =>
                'Unable to find your account.'

        ]);

        exit;
    }

    $uploadDir =
        __DIR__ .
        '/../uploads/students/';

    if (
        !is_dir($uploadDir) &&
        !mkdir(
            $uploadDir,
            0755,
            true
        ) &&
        !is_dir($uploadDir)
    ) {

        throw new RuntimeException(
            'Unable to create upload directory.'
        );

    }

    $extension =
        $allowed[$mimeType];

    $newFileName =
        (string)$student['student_code'] .
        '_' .
        bin2hex(
            random_bytes(8)
        ) .
        '.' .
        $extension;

    $destination =
        $uploadDir .
        $newFileName;

    if (
        !move_uploaded_file(
            (string)$file['tmp_name'],
            $destination
        )
    ) {

        throw new RuntimeException(
            'Unable to save uploaded image.'
        );

    }

    $update =
        $conn->prepare(
            'UPDATE students
             SET profile_photo = ?
             WHERE id = ?'
        );

    $update->execute([

        $newFileName,

        $studentId

    ]);

    if (
        !empty(
            $student['profile_photo']
        )
    ) {

        $oldPath =
            $uploadDir .
            basename(
                (string)
                $student['profile_photo']
            );

        if (
            is_file($oldPath) &&
            $oldPath !==
            $destination
        ) {

            @unlink(
                $oldPath
            );

        }

    }

    echo json_encode([

        'status' =>
            'success',

        'title' =>
            'Photo Updated',

        'message' =>
            'Your profile photo has been updated successfully.',

        'photo' =>
            '../uploads/students/' .
            rawurlencode(
                $newFileName
            )

    ]);

} catch (
    Throwable $e
) {

    error_log(
        'Student profile photo upload failed: ' .
        $e->getMessage()
    );

    if (
        isset($destination) &&
        is_file($destination)
    ) {

        @unlink(
            $destination
        );

    }

    echo json_encode([

        'status' =>
            'error',

        'title' =>
            'Upload Failed',

        'message' =>
            'Unable to update your profile photo right now.'

    ]);

}