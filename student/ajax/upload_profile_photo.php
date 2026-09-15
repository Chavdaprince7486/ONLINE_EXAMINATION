<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| SESSION + CONFIG + FUNCTIONS
|--------------------------------------------------------------------------
*/

require_once '../../config/session.php';
require_once '../../config/config.php';
require_once '../../config/functions.php';


/*
|--------------------------------------------------------------------------
| JSON HEADERS
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: application/json; charset=UTF-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Pragma: no-cache'
);


/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function profile_photo_response(
    bool $success,
    string $title,
    string $message,
    array $extra = [],
    int $statusCode = 200
): never {

    http_response_code(
        $statusCode
    );


    echo json_encode(
        array_merge(
            [
                'status' =>
                    $success
                        ? 'success'
                        : 'error',

                'title' =>
                    $title,

                'message' =>
                    $message
            ],
            $extra
        ),
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE |
        JSON_INVALID_UTF8_SUBSTITUTE
    );


    exit;
}


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    !==
    'POST'
) {

    profile_photo_response(
        false,
        'Invalid Request',
        'Only POST requests are allowed.',
        [],
        405
    );
}


/*
|--------------------------------------------------------------------------
| STUDENT AUTH
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['user_id']
    )
    ||
    (
        $_SESSION['user_role']
        ??
        ''
    ) !==
    'student'
) {

    profile_photo_response(
        false,
        'Access Denied',
        'Please sign in again.',
        [],
        401
    );
}


$studentId =
    (int)(
        $_SESSION['user_id']
    );


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$requestToken =
    trim(
        (string)(
            $_POST[
                'csrf_token'
            ]
            ??
            ''
        )
    );


if (
    !verify_csrf_token(
        $requestToken
    )
) {

    profile_photo_response(
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
    $_FILES[
        'profile_photo'
    ]
    ??
    null;


if (
    !is_array(
        $file
    )
) {

    profile_photo_response(
        false,
        'No Image',
        'Please choose a profile image.',
        [],
        422
    );
}


$fileError =
    (int)(
        $file[
            'error'
        ]
        ??
        UPLOAD_ERR_NO_FILE
    );


if (
    $fileError
    !==
    UPLOAD_ERR_OK
) {

    $message =
        match (
            $fileError
        ) {

            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE =>
                'The selected image is too large.',

            UPLOAD_ERR_PARTIAL =>
                'The image upload was incomplete.',

            UPLOAD_ERR_NO_FILE =>
                'Please choose an image first.',

            default =>
                'Unable to upload the image.'
        };


    profile_photo_response(
        false,
        'Upload Failed',
        $message,
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| SIZE
|--------------------------------------------------------------------------
*/

$maxFileSize =
    2 * 1024 * 1024;


$fileSize =
    (int)(
        $file[
            'size'
        ]
        ??
        0
    );


if (
    $fileSize <= 0
) {

    profile_photo_response(
        false,
        'Invalid Image',
        'The selected image is empty.',
        [],
        422
    );
}


if (
    $fileSize >
    $maxFileSize
) {

    profile_photo_response(
        false,
        'Image Too Large',
        'Maximum image size is 2 MB.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| TEMP FILE
|--------------------------------------------------------------------------
*/

$tmpName =
    (string)(
        $file[
            'tmp_name'
        ]
        ??
        ''
    );


if (
    $tmpName === ''
    ||
    !is_uploaded_file(
        $tmpName
    )
) {

    profile_photo_response(
        false,
        'Invalid Upload',
        'The uploaded file could not be verified.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| MIME TYPE
|--------------------------------------------------------------------------
*/

$finfo =
    new finfo(
        FILEINFO_MIME_TYPE
    );


$mime =
    (string)(
        $finfo->file(
            $tmpName
        )
        ?: ''
    );


$allowedMimeTypes = [

    'image/jpeg' =>
        'jpg',

    'image/png' =>
        'png',

    'image/webp' =>
        'webp'

];


if (
    !isset(
        $allowedMimeTypes[
            $mime
        ]
    )
) {

    profile_photo_response(
        false,
        'Invalid Image',
        'Only JPG, PNG and WebP images are allowed.',
        [],
        422
    );
}


$extension =
    $allowedMimeTypes[
        $mime
    ];


/*
|--------------------------------------------------------------------------
| CHECK REAL IMAGE
|--------------------------------------------------------------------------
*/

$imageInfo =
    @getimagesize(
        $tmpName
    );


if (
    $imageInfo === false
) {

    profile_photo_response(
        false,
        'Invalid Image',
        'The selected file is not a valid image.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| STUDENT
|--------------------------------------------------------------------------
*/

try {

    $studentStatement =
        $conn->prepare(
            "
            SELECT
                id,
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

        profile_photo_response(
            false,
            'Student Not Found',
            'Unable to find your account.',
            [],
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPLOAD DIRECTORY
    |--------------------------------------------------------------------------
    */

    $uploadDirectory =
        dirname(
            __DIR__,
            2
        )
        .
        DIRECTORY_SEPARATOR .
        'uploads'
        .
        DIRECTORY_SEPARATOR .
        'students';


    if (
        !is_dir(
            $uploadDirectory
        )
    ) {

        if (
            !mkdir(
                $uploadDirectory,
                0755,
                true
            )
            &&
            !is_dir(
                $uploadDirectory
            )
        ) {

            profile_photo_response(
                false,
                'Upload Failed',
                'Unable to create the profile image directory.',
                [],
                500
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FILE NAME
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
                (
                    'student_' .
                    $studentId
                )
            )
        );


    if (
        !is_string(
            $studentCode
        )
        ||
        $studentCode === ''
    ) {

        $studentCode =
            'student_' .
            $studentId;
    }


    $newFileName =
        $studentCode
        .
        '_'
        .
        $studentId
        .
        '_'
        .
        bin2hex(
            random_bytes(
                8
            )
        )
        .
        '.'
        .
        $extension;


    $destination =
        $uploadDirectory
        .
        DIRECTORY_SEPARATOR
        .
        $newFileName;


    /*
    |--------------------------------------------------------------------------
    | SAVE NEW FILE
    |--------------------------------------------------------------------------
    */

    if (
        !move_uploaded_file(
            $tmpName,
            $destination
        )
    ) {

        profile_photo_response(
            false,
            'Upload Failed',
            'Unable to save the profile image.',
            [],
            500
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DATABASE UPDATE
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


    $update->execute(
        [
            $newFileName,
            $studentId
        ]
    );

    // Keep the current authenticated session synchronized with the database.
    $_SESSION['profile_photo'] = $newFileName;


    /*
    |--------------------------------------------------------------------------
    | DELETE OLD PHOTO
    |--------------------------------------------------------------------------
    |
    | New file is already stored and DB is already updated.
    |
    */

    $oldPhoto =
        basename(
            trim(
                (string)(
                    $student[
                        'profile_photo'
                    ]
                    ??
                    ''
                )
            )
        );


    if (
        $oldPhoto !== ''
        &&
        $oldPhoto !==
        $newFileName
    ) {

        $oldFile =
            $uploadDirectory
            .
            DIRECTORY_SEPARATOR
            .
            $oldPhoto;


        if (
            is_file(
                $oldFile
            )
        ) {

            @unlink(
                $oldFile
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CACHE-BUSTED PUBLIC PATH
    |--------------------------------------------------------------------------
    */

    $baseUrl = defined('BASE_URL')
        ? rtrim((string) BASE_URL, '/') . '/'
        : '/ONLINE_EXAMINATION/';

    $publicPath =
        $baseUrl .
        'uploads/students/' .
        rawurlencode($newFileName);


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    profile_photo_response(
        true,
        'Photo Updated',
        'Your profile photo has been updated successfully.',
        [
            'photo' =>
                $publicPath,

            'photo_url' =>
                $publicPath .
                '?v=' .
                time()
        ]
    );


} catch (
    Throwable $exception
) {

    error_log(
        'Student profile photo upload failed: ' .
        $exception->getMessage()
    );


    if (
        isset(
            $destination
        )
        &&
        is_file(
            $destination
        )
    ) {

        @unlink(
            $destination
        );
    }


    profile_photo_response(
        false,
        'Upload Failed',
        'Unable to update your profile photo right now.',
        [],
        500
    );
}