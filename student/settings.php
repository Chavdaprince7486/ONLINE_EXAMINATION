<?php

declare(strict_types=1);

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';

header(
    'Content-Type: application/json; charset=UTF-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);


function photo_json(
    bool $success,
    string $title,
    string $message,
    array $data = [],
    int $code = 200
): never {

    http_response_code(
        $code
    );


    echo json_encode(
        array_merge(
            [
                'status':
                    $success
                        ? 'success'
                        : 'error',

                'title':
                    $title,

                'message':
                    $message
            ],
            $data
        ),
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE |
        JSON_INVALID_UTF8_SUBSTITUTE
    );


    exit;
}


/*
|--------------------------------------------------------------------------
| METHOD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    photo_json(
        false,
        'Invalid Request',
        'Only POST requests are allowed.',
        [],
        405
    );
}


/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['user_id']
    ) ||
    (
        $_SESSION['user_role']
        ??
        ''
    ) !== 'student'
) {

    photo_json(
        false,
        'Access Denied',
        'Please sign in again.',
        [],
        401
    );
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    !verify_csrf_token(
        $_POST['csrf_token']
        ??
        null
    )
) {

    photo_json(
        false,
        'Security Check Failed',
        'Your security token is invalid. Refresh the page and try again.',
        [],
        419
    );
}


/*
|--------------------------------------------------------------------------
| FILE
|--------------------------------------------------------------------------
*/

$file =
    $_FILES['profile_photo']
    ??
    null;


if (
    !is_array($file)
) {

    photo_json(
        false,
        'No Image',
        'Please choose a profile image.',
        [],
        422
    );
}


$fileError =
    (int)(
        $file['error']
        ??
        UPLOAD_ERR_NO_FILE
    );


if (
    $fileError !==
    UPLOAD_ERR_OK
) {

    photo_json(
        false,
        'Upload Failed',
        'Please choose a valid profile image.',
        [],
        422
    );
}


$tmpName =
    (string)(
        $file['tmp_name']
        ??
        ''
    );


$fileSize =
    (int)(
        $file['size']
        ??
        0
    );


if (
    $tmpName === ''
    ||
    !is_uploaded_file(
        $tmpName
    )
) {

    photo_json(
        false,
        'Invalid Upload',
        'The uploaded file could not be validated.',
        [],
        422
    );
}


if (
    $fileSize <= 0
) {

    photo_json(
        false,
        'Empty Image',
        'The selected image is empty.',
        [],
        422
    );
}


if (
    $fileSize >
    2 * 1024 * 1024
) {

    photo_json(
        false,
        'Image Too Large',
        'Maximum profile photo size is 2 MB.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| MIME
|--------------------------------------------------------------------------
*/

$finfo =
    new finfo(
        FILEINFO_MIME_TYPE
    );


$mime =
    (string)$finfo->file(
        $tmpName
    );


$allowed = [

    'image/jpeg' =>
        'jpg',

    'image/png' =>
        'png',

    'image/webp' =>
        'webp'

];


if (
    !isset(
        $allowed[$mime]
    )
) {

    photo_json(
        false,
        'Invalid Image',
        'Only JPG, PNG and WebP images are allowed.',
        [],
        422
    );
}


$extension =
    $allowed[$mime];


$studentId =
    (int)$_SESSION[
        'user_id'
    ];


$destination = null;


try {

    /*
    |--------------------------------------------------------------------------
    | LOAD STUDENT
    |--------------------------------------------------------------------------
    */

    $studentStatement =
        $conn->prepare(
            "
            SELECT

                student_code,
                profile_photo

            FROM students

            WHERE

                id = ?

                AND status = 'Active'

            LIMIT 1
            "
        );


    $studentStatement->execute([
        $studentId
    ]);


    $student =
        $studentStatement->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$student
    ) {

        photo_json(
            false,
            'Student Not Found',
            'Unable to find your student account.',
            [],
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DIRECTORY
    |--------------------------------------------------------------------------
    */

    $uploadDir =
        dirname(__DIR__, 2) .
        '/uploads/students';


    if (
        !is_dir(
            $uploadDir
        )
    ) {

        if (
            !mkdir(
                $uploadDir,
                0755,
                true
            )
            &&
            !is_dir(
                $uploadDir
            )
        ) {

            photo_json(
                false,
                'Upload Failed',
                'Unable to create the profile photo directory.',
                [],
                500
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SAFE NAME
    |--------------------------------------------------------------------------
    */

    $studentCode =
        preg_replace(
            '/[^A-Za-z0-9_-]/',
            '_',
            (string)(
                $student[
                    'student_code'
                ]
                ??
                'student_' .
                $studentId
            )
        );


    $newName =
        $studentCode .
        '_' .
        $studentId .
        '_' .
        bin2hex(
            random_bytes(
                10
            )
        ) .
        '.' .
        $extension;


    $destination =
        $uploadDir .
        '/' .
        $newName;


    /*
    |--------------------------------------------------------------------------
    | MOVE
    |--------------------------------------------------------------------------
    */

    if (
        !move_uploaded_file(
            $tmpName,
            $destination
        )
    ) {

        photo_json(
            false,
            'Upload Failed',
            'Unable to save your profile image.',
            [],
            500
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DATABASE
    |--------------------------------------------------------------------------
    */

    $update =
        $conn->prepare(
            "
            UPDATE students

            SET
                profile_photo = ?

            WHERE
                id = ?

            LIMIT 1
            "
        );


    $update->execute([
        $newName,
        $studentId
    ]);


    /*
    |--------------------------------------------------------------------------
    | DELETE OLD PHOTO
    |--------------------------------------------------------------------------
    */

    $oldPhoto =
        trim(
            (string)(
                $student[
                    'profile_photo'
                ]
                ??
                ''
            )
        );


    if (
        $oldPhoto !== ''
        &&
        basename($oldPhoto)
            ===
        $oldPhoto
        &&
        $oldPhoto !== $newName
    ) {

        $oldPath =
            $uploadDir .
            '/' .
            $oldPhoto;


        if (
            is_file(
                $oldPath
            )
        ) {

            @unlink(
                $oldPath
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    photo_json(
        true,
        'Photo Updated',
        'Your profile photo has been updated successfully.',
        [
            'photo' =>
                '../uploads/students/' .
                rawurlencode(
                    $newName
                )
        ]
    );


} catch (
    Throwable $exception
) {

    if (
        $destination !== null
        &&
        is_file(
            $destination
        )
    ) {

        @unlink(
            $destination
        );
    }


    error_log(
        'Student profile photo upload failed: ' .
        $exception->getMessage()
    );


    photo_json(
        false,
        'Upload Failed',
        'Unable to update your profile photo right now.',
        [],
        500
    );
}