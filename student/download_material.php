<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'student'
) {
    header('Location: ../auth/login.php');
    exit;
}

$materialId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $materialId === false ||
    $materialId === null ||
    (int) $materialId <= 0
) {
    http_response_code(400);
    exit('Invalid material request.');
}

$studentId =
    (int) $_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT
        m.id,
        m.title,
        m.file_path,
        m.access_type,
        m.status
     FROM study_materials AS m
     WHERE m.id = ?
       AND m.status = 'Active'
     LIMIT 1"
);

$stmt->execute([
    (int) $materialId
]);

$material =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    http_response_code(404);
    exit('The requested material was not found.');
}

if (
    $material['access_type'] === 'Subscription Only' &&
    !has_active_subscription(
        $conn,
        $studentId
    )
) {
    http_response_code(403);
    exit(
        'Material access is not available.'
    );
}

/*
|--------------------------------------------------------------------------
| MATERIAL STORAGE
|--------------------------------------------------------------------------
|
| All new materials are stored under:
|
| /ONLINE_EXAMINATION/uploads/materials/
|
| Older records are also normalized below so that values such as:
|
| assets/uploads/materials/file.pdf
| uploads/materials/file.pdf
|
| do not allow traversal outside the approved directory.
|
*/

$storageRoot =
    realpath(
        dirname(__DIR__) .
        '/uploads/materials'
    );

if (
    $storageRoot === false ||
    !is_dir($storageRoot)
) {
    http_response_code(404);
    exit(
        'The material storage directory is unavailable.'
    );
}

$storedPath =
    str_replace(
        '\\',
        '/',
        (string) $material['file_path']
    );

$storedPath =
    ltrim(
        $storedPath,
        '/'
    );

$fileName =
    basename($storedPath);

if (
    $fileName === '' ||
    $fileName === '.' ||
    $fileName === '..'
) {
    http_response_code(404);
    exit(
        'The requested material file is invalid.'
    );
}

$filePath =
    realpath(
        $storageRoot .
        DIRECTORY_SEPARATOR .
        $fileName
    );

if (
    $filePath === false ||
    !is_file($filePath)
) {
    /*
     * Backward compatibility for older teacher uploads.
     *
     * Old path:
     * /ONLINE_EXAMINATION/assets/uploads/materials/
     */
    $legacyRoot =
        realpath(
            dirname(__DIR__) .
            '/assets/uploads/materials'
        );

    if (
        $legacyRoot !== false &&
        is_dir($legacyRoot)
    ) {
        $legacyFile =
            realpath(
                $legacyRoot .
                DIRECTORY_SEPARATOR .
                $fileName
            );

        if (
            $legacyFile !== false &&
            is_file($legacyFile)
        ) {
            $filePath =
                $legacyFile;
        }
    }
}

if (
    $filePath === false ||
    !is_file($filePath)
) {
    http_response_code(404);
    exit(
        'The requested material file was not found.'
    );
}

/*
|--------------------------------------------------------------------------
| Security Boundary
|--------------------------------------------------------------------------
*/

$approvedRoots = [
    $storageRoot
];

$legacyRoot =
    realpath(
        dirname(__DIR__) .
        '/assets/uploads/materials'
    );

if (
    $legacyRoot !== false &&
    is_dir($legacyRoot)
) {
    $approvedRoots[] =
        $legacyRoot;
}

$fileAllowed = false;

foreach ($approvedRoots as $approvedRoot) {

    $approvedRoot =
        rtrim(
            $approvedRoot,
            DIRECTORY_SEPARATOR
        );

    if (
        $filePath === $approvedRoot ||
        str_starts_with(
            $filePath,
            $approvedRoot .
            DIRECTORY_SEPARATOR
        )
    ) {
        $fileAllowed = true;
        break;
    }
}

if (!$fileAllowed) {
    http_response_code(403);
    exit(
        'The requested file is not accessible.'
    );
}

/*
|--------------------------------------------------------------------------
| Detect Content Type
|--------------------------------------------------------------------------
*/

$finfo = new finfo(
    FILEINFO_MIME_TYPE
);

$mimeType =
    $finfo->file(
        $filePath
    );

$allowedMimeTypes = [

    'application/pdf' =>
        'application/pdf',

    'application/msword' =>
        'application/msword',

    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' =>
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

    'application/vnd.ms-powerpoint' =>
        'application/vnd.ms-powerpoint',

    'application/vnd.openxmlformats-officedocument.presentationml.presentation' =>
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',

];

if (
    !isset(
        $allowedMimeTypes[$mimeType]
    )
) {
    http_response_code(415);
    exit(
        'This material file type is not supported.'
    );
}

$downloadName =
    preg_replace(
        '/[^A-Za-z0-9._-]/',
        '_',
        pathinfo(
            $material['title'],
            PATHINFO_FILENAME
        )
    );

if (
    $downloadName === null ||
    $downloadName === ''
) {
    $downloadName = 'ExamSphere_Material';
}

$extension =
    strtolower(
        (string)
        pathinfo(
            $filePath,
            PATHINFO_EXTENSION
        )
    );

$downloadName .=
    $extension !== ''
        ? '.' . $extension
        : '';

$fileSize =
    filesize(
        $filePath
    );

if ($fileSize === false) {
    http_response_code(500);
    exit(
        'Unable to read the material file.'
    );
}

/*
|--------------------------------------------------------------------------
| Download Response
|--------------------------------------------------------------------------
*/

while (
    ob_get_level() > 0
) {
    ob_end_clean();
}

header(
    'Content-Type: ' .
    $allowedMimeTypes[$mimeType]
);

header(
    'Content-Length: ' .
    (string) $fileSize
);

header(
    'Content-Disposition: attachment; filename="' .
    $downloadName .
    '"'
);

header(
    'Content-Transfer-Encoding: binary'
);

header(
    'Cache-Control: private, no-store, no-cache, must-revalidate'
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);

readfile(
    $filePath
);

exit;