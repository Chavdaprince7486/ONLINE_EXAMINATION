<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../config/notification_events.php';

require_login('teacher');

$teacherId = current_user_id();

$message = '';
$error = '';

$uploadDirectory =
    dirname(__DIR__) .
    DIRECTORY_SEPARATOR .
    'uploads' .
    DIRECTORY_SEPARATOR .
    'materials';

$storedDirectory =
    'uploads/materials';

$allowedMimeTypes = [
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-powerpoint' => 'ppt',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
];

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

$perPage = 10;

$search = trim(
    (string)($_GET['search'] ?? '')
);

$statusFilter = trim(
    (string)($_GET['status'] ?? '')
);

if (
    !in_array(
        $statusFilter,
        ['', 'Active', 'Inactive'],
        true
    )
) {
    $statusFilter = '';
}

$accessFilter = trim(
    (string)($_GET['access_type'] ?? '')
);

if (
    !in_array(
        $accessFilter,
        ['', 'Public', 'Subscription Only'],
        true
    )
) {
    $accessFilter = '';
}

$subjectFilter = filter_input(
    INPUT_GET,
    'subject_id',
    FILTER_VALIDATE_INT
);

if (
    $subjectFilter === false ||
    $subjectFilter === null ||
    $subjectFilter <= 0
) {
    $subjectFilter = null;
}

function teacher_material_e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function teacher_material_date(mixed $value): string
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

function teacher_material_url(
    int $page,
    string $search,
    ?int $subjectId,
    string $status,
    string $accessType
): string {
    $params = [
        'page' => $page
    ];

    if ($search !== '') {
        $params['search'] = $search;
    }

    if ($subjectId !== null) {
        $params['subject_id'] = $subjectId;
    }

    if ($status !== '') {
        $params['status'] = $status;
    }

    if ($accessType !== '') {
        $params['access_type'] = $accessType;
    }

    return '?' . http_build_query($params);
}

/*
|--------------------------------------------------------------------------
| Active subject list
|--------------------------------------------------------------------------
*/

$subjects = [];

try {

    $subjectStmt = $conn->query(
        "
        SELECT
            id,
            name,
            code
        FROM subjects
        WHERE status = 'Active'
        ORDER BY
            name ASC,
            id ASC
        "
    );

    $subjects =
        $subjectStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'ExamSphere teacher materials subject load failed: ' .
        $exception->getMessage()
    );

    $error =
        'Unable to load the subject list.';
}

/*
|--------------------------------------------------------------------------
| File helpers
|--------------------------------------------------------------------------
*/

function teacher_material_upload(
    array $file,
    array $allowedMimeTypes,
    string $uploadDirectory,
    string $storedDirectory
): array {

    if (
        !isset($file['error']) ||
        (int)$file['error'] !== UPLOAD_ERR_OK
    ) {
        throw new RuntimeException(
            'Please select a valid learning material file.'
        );
    }

    if (
        (int)($file['size'] ?? 0) <= 0
    ) {
        throw new RuntimeException(
            'The selected file is empty.'
        );
    }

    if (
        (int)$file['size'] >
        10 * 1024 * 1024
    ) {
        throw new RuntimeException(
            'The material file must be 10 MB or smaller.'
        );
    }

    $tmpName =
        (string)(
            $file['tmp_name'] ?? ''
        );

    if (
        $tmpName === '' ||
        !is_uploaded_file($tmpName)
    ) {
        throw new RuntimeException(
            'Invalid upload detected.'
        );
    }

    $finfo =
        new finfo(
            FILEINFO_MIME_TYPE
        );

    $mimeType =
        $finfo->file(
            $tmpName
        );

    if (
        !isset(
            $allowedMimeTypes[$mimeType]
        )
    ) {
        throw new RuntimeException(
            'Only PDF, DOC, DOCX, PPT and PPTX files are allowed.'
        );
    }

    if (
        !is_dir($uploadDirectory) &&
        !mkdir(
            $uploadDirectory,
            0755,
            true
        )
    ) {
        throw new RuntimeException(
            'Unable to prepare the materials upload folder.'
        );
    }

    if (
        !is_writable(
            $uploadDirectory
        )
    ) {
        throw new RuntimeException(
            'The materials upload folder is not writable.'
        );
    }

    $extension =
        $allowedMimeTypes[$mimeType];

    $storedFileName =
        'material_' .
        bin2hex(
            random_bytes(16)
        ) .
        '.' .
        $extension;

    $targetPath =
        $uploadDirectory .
        DIRECTORY_SEPARATOR .
        $storedFileName;

    if (
        !move_uploaded_file(
            $tmpName,
            $targetPath
        )
    ) {
        throw new RuntimeException(
            'The material file could not be saved.'
        );
    }

    return [
        'file_name' =>
            $storedFileName,

        'file_path' =>
            $storedDirectory .
            '/' .
            $storedFileName,

        'target_path' =>
            $targetPath,
    ];
}

function teacher_material_safe_delete_file(
    string $uploadDirectory,
    string $relativePath
): void {

    $fileName =
        basename(
            $relativePath
        );

    if ($fileName === '') {
        return;
    }

    $root =
        realpath(
            $uploadDirectory
        );

    if ($root === false) {
        return;
    }

    $candidate =
        realpath(
            $uploadDirectory .
            DIRECTORY_SEPARATOR .
            $fileName
        );

    if (
        $candidate !== false &&
        str_starts_with(
            $candidate,
            $root . DIRECTORY_SEPARATOR
        ) &&
        is_file($candidate)
    ) {
        @unlink($candidate);
    }
}

/*
|--------------------------------------------------------------------------
| POST actions
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

        $action =
            trim(
                (string)(
                    $_POST['action'] ?? ''
                )
            );

        /*
        |--------------------------------------------------------------------------
        | UPLOAD
        |--------------------------------------------------------------------------
        */

        if ($action === 'upload') {

            $title =
                trim(
                    (string)(
                        $_POST['title'] ?? ''
                    )
                );

            $subjectId = filter_var(
                $_POST['subject_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            $description =
                trim(
                    (string)(
                        $_POST['description'] ?? ''
                    )
                );

            $accessType =
                trim(
                    (string)(
                        $_POST['access_type'] ?? 'Public'
                    )
                );

            if (
                $title === '' ||
                mb_strlen($title) > 180
            ) {
                throw new RuntimeException(
                    'Please enter a valid material title.'
                );
            }

            if (
                $subjectId === false ||
                $subjectId <= 0
            ) {
                throw new RuntimeException(
                    'Please select a valid subject.'
                );
            }

            if (
                mb_strlen($description) > 5000
            ) {
                throw new RuntimeException(
                    'Material description is too long.'
                );
            }

            if (
                !in_array(
                    $accessType,
                    [
                        'Public',
                        'Subscription Only'
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Please select a valid access level.'
                );
            }

            $subjectCheck =
                $conn->prepare(
                    "
                    SELECT
                        id
                    FROM subjects
                    WHERE
                        id = ?
                        AND status = 'Active'
                    LIMIT 1
                    "
                );

            $subjectCheck->execute([
                (int)$subjectId
            ]);

            if (!$subjectCheck->fetchColumn()) {
                throw new RuntimeException(
                    'The selected subject is not available.'
                );
            }

            $upload =
                teacher_material_upload(
                    $_FILES['material_file'] ?? [],
                    $allowedMimeTypes,
                    $uploadDirectory,
                    $storedDirectory
                );

            try {

                $insert =
                    $conn->prepare(
                        "
                        INSERT INTO study_materials
                        (
                            subject_id,
                            teacher_id,
                            title,
                            description,
                            file_path,
                            access_type,
                            status
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            'Active'
                        )
                        "
                    );

                $insert->execute([
                    (int)$subjectId,
                    $teacherId,
                    $title,
                    $description !== ''
                        ? $description
                        : null,
                    $upload['file_path'],
                    $accessType
                ]);

                $materialId = (int)$conn->lastInsertId();

            } catch (Throwable $databaseException) {

                if (
                    is_file(
                        $upload['target_path']
                    )
                ) {
                    @unlink(
                        $upload['target_path']
                    );
                }

                throw $databaseException;
            }

            examsphere_event_material(
                $conn,
                $materialId,
                $title,
                'added',
                'Active'
            );

            $message =
                'Study material published successfully.';

        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        } elseif ($action === 'update') {

            $materialId =
                filter_var(
                    $_POST['material_id'] ?? '',
                    FILTER_VALIDATE_INT
                );

            if (
                $materialId === false ||
                $materialId <= 0
            ) {
                throw new RuntimeException(
                    'Invalid material selected.'
                );
            }

            $get =
                $conn->prepare(
                    "
                    SELECT
                        id,
                        subject_id,
                        title,
                        description,
                        file_path,
                        access_type,
                        status
                    FROM study_materials
                    WHERE
                        id = ?
                        AND teacher_id = ?
                    LIMIT 1
                    "
                );

            $get->execute([
                (int)$materialId,
                $teacherId
            ]);

            $material =
                $get->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!$material) {
                throw new RuntimeException(
                    'Study material not found or access denied.'
                );
            }

            $title =
                trim(
                    (string)(
                        $_POST['title'] ?? ''
                    )
                );

            $subjectId =
                filter_var(
                    $_POST['subject_id'] ?? '',
                    FILTER_VALIDATE_INT
                );

            $description =
                trim(
                    (string)(
                        $_POST['description'] ?? ''
                    )
                );

            $accessType =
                trim(
                    (string)(
                        $_POST['access_type'] ?? ''
                    )
                );

            $statusValue =
                trim(
                    (string)(
                        $_POST['status'] ?? ''
                    )
                );

            if (
                $title === '' ||
                mb_strlen($title) > 180
            ) {
                throw new RuntimeException(
                    'Please enter a valid material title.'
                );
            }

            if (
                $subjectId === false ||
                $subjectId <= 0
            ) {
                throw new RuntimeException(
                    'Please select a valid subject.'
                );
            }

            if (
                mb_strlen($description) > 5000
            ) {
                throw new RuntimeException(
                    'Material description is too long.'
                );
            }

            if (
                !in_array(
                    $accessType,
                    [
                        'Public',
                        'Subscription Only'
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Please select a valid access level.'
                );
            }

            if (
                !in_array(
                    $statusValue,
                    [
                        'Active',
                        'Inactive'
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Please select a valid status.'
                );
            }

            $subjectCheck =
                $conn->prepare(
                    "
                    SELECT
                        id
                    FROM subjects
                    WHERE
                        id = ?
                        AND status = 'Active'
                    LIMIT 1
                    "
                );

            $subjectCheck->execute([
                (int)$subjectId
            ]);

            if (!$subjectCheck->fetchColumn()) {
                throw new RuntimeException(
                    'The selected subject is not available.'
                );
            }

            $replacement = null;

            $newFile =
                $_FILES['material_file']
                ?? [];

            if (
                isset($newFile['error']) &&
                (int)$newFile['error'] !==
                UPLOAD_ERR_NO_FILE
            ) {

                $replacement =
                    teacher_material_upload(
                        $newFile,
                        $allowedMimeTypes,
                        $uploadDirectory,
                        $storedDirectory
                    );
            }

            $newFilePath =
                $replacement !== null
                    ? $replacement['file_path']
                    : (string)$material[
                        'file_path'
                    ];

            try {

                $update =
                    $conn->prepare(
                        "
                        UPDATE study_materials
                        SET
                            subject_id = ?,
                            title = ?,
                            description = ?,
                            file_path = ?,
                            access_type = ?,
                            status = ?
                        WHERE
                            id = ?
                            AND teacher_id = ?
                        "
                    );

                $update->execute([
                    (int)$subjectId,
                    $title,
                    $description !== ''
                        ? $description
                        : null,
                    $newFilePath,
                    $accessType,
                    $statusValue,
                    (int)$materialId,
                    $teacherId
                ]);

            } catch (Throwable $databaseException) {

                if (
                    $replacement !== null &&
                    is_file(
                        $replacement['target_path']
                    )
                ) {
                    @unlink(
                        $replacement['target_path']
                    );
                }

                throw $databaseException;
            }

            if (
                $replacement !== null
            ) {
                teacher_material_safe_delete_file(
                    $uploadDirectory,
                    (string)$material['file_path']
                );
            }

            examsphere_event_material(
                $conn,
                (int)$materialId,
                $title,
                'updated',
                $statusValue
            );

            $message =
                'Study material updated successfully.';

        /*
        |--------------------------------------------------------------------------
        | TOGGLE STATUS
        |--------------------------------------------------------------------------
        */

        } elseif ($action === 'toggle_status') {

            $materialId =
                filter_var(
                    $_POST['material_id'] ?? '',
                    FILTER_VALIDATE_INT
                );

            if (
                $materialId === false ||
                $materialId <= 0
            ) {
                throw new RuntimeException(
                    'Invalid material selected.'
                );
            }

            $get =
                $conn->prepare(
                    "
                    SELECT
                        status
                    FROM study_materials
                    WHERE
                        id = ?
                        AND teacher_id = ?
                    LIMIT 1
                    "
                );

            $get->execute([
                (int)$materialId,
                $teacherId
            ]);

            $currentStatus =
                $get->fetchColumn();

            if ($currentStatus === false) {
                throw new RuntimeException(
                    'Study material not found or access denied.'
                );
            }

            $newStatus =
                (string)$currentStatus ===
                'Active'
                    ? 'Inactive'
                    : 'Active';

            $update =
                $conn->prepare(
                    "
                    UPDATE study_materials
                    SET status = ?
                    WHERE
                        id = ?
                        AND teacher_id = ?
                    "
                );

            $update->execute([
                $newStatus,
                (int)$materialId,
                $teacherId
            ]);

            if ((string)$newStatus === 'Active') {
                $materialTitleStatement = $conn->prepare(
                    'SELECT title FROM study_materials WHERE id = ? AND teacher_id = ? LIMIT 1'
                );
                $materialTitleStatement->execute([(int)$materialId, $teacherId]);
                $materialTitle = (string)($materialTitleStatement->fetchColumn() ?: 'Study material');

                examsphere_event_material(
                    $conn,
                    (int)$materialId,
                    $materialTitle,
                    'activated',
                    $newStatus
                );
            }

            $message =
                'Material status changed to ' .
                $newStatus .
                '.';

        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        } elseif ($action === 'delete') {

            $materialId =
                filter_var(
                    $_POST['material_id'] ?? '',
                    FILTER_VALIDATE_INT
                );

            if (
                $materialId === false ||
                $materialId <= 0
            ) {
                throw new RuntimeException(
                    'Invalid material selected.'
                );
            }

            $get =
                $conn->prepare(
                    "
                    SELECT
                        file_path
                    FROM study_materials
                    WHERE
                        id = ?
                        AND teacher_id = ?
                    LIMIT 1
                    "
                );

            $get->execute([
                (int)$materialId,
                $teacherId
            ]);

            $material =
                $get->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!$material) {
                throw new RuntimeException(
                    'Study material not found or access denied.'
                );
            }

            $delete =
                $conn->prepare(
                    "
                    DELETE FROM study_materials
                    WHERE
                        id = ?
                        AND teacher_id = ?
                    "
                );

            $delete->execute([
                (int)$materialId,
                $teacherId
            ]);

            if (
                $delete->rowCount() !== 1
            ) {
                throw new RuntimeException(
                    'The study material could not be deleted.'
                );
            }

            teacher_material_safe_delete_file(
                $uploadDirectory,
                (string)$material['file_path']
            );

            $message =
                'Study material deleted successfully.';

        } else {

            throw new RuntimeException(
                'Invalid material-management action.'
            );
        }

    } catch (Throwable $exception) {

        error_log(
            'ExamSphere teacher materials action failed: ' .
            $exception->getMessage()
        );

        $error =
            $exception->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Materials listing
|--------------------------------------------------------------------------
*/

$items = [];
$totalRows = 0;
$totalPages = 1;

$where = [
    'm.teacher_id = ?'
];

$params = [
    $teacherId
];

if ($search !== '') {

    $where[] = '
        (
            m.title LIKE ?
            OR m.description LIKE ?
        )
    ';

    $value =
        '%' .
        $search .
        '%';

    $params[] = $value;
    $params[] = $value;
}

if ($statusFilter !== '') {

    $where[] =
        'm.status = ?';

    $params[] =
        $statusFilter;
}

if ($accessFilter !== '') {

    $where[] =
        'm.access_type = ?';

    $params[] =
        $accessFilter;
}

if ($subjectFilter !== null) {

    $where[] =
        'm.subject_id = ?';

    $params[] =
        $subjectFilter;
}

$whereSql =
    'WHERE ' .
    implode(
        ' AND ',
        $where
    );

try {

    $countStmt =
        $conn->prepare(
            "
            SELECT
                COUNT(*)
            FROM study_materials m
            $whereSql
            "
        );

    $countStmt->execute(
        $params
    );

    $totalRows =
        (int)(
            $countStmt->fetchColumn()
            ?: 0
        );

    $totalPages =
        max(
            1,
            (int)ceil(
                $totalRows /
                $perPage
            )
        );

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset =
        (
            $page - 1
        ) *
        $perPage;

    $dataStmt =
        $conn->prepare(
            "
            SELECT
                m.id,
                m.subject_id,
                m.title,
                m.description,
                m.file_path,
                m.access_type,
                m.status,
                m.uploaded_at,
                s.name AS subject_name,
                s.code AS subject_code

            FROM study_materials m

            LEFT JOIN subjects s
                ON s.id = m.subject_id

            $whereSql

            ORDER BY
                m.uploaded_at DESC,
                m.id DESC

            LIMIT $perPage
            OFFSET $offset
            "
        );

    $dataStmt->execute(
        $params
    );

    $items =
        $dataStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        'ExamSphere teacher materials listing failed: ' .
        $exception->getMessage()
    );

    $items = [];
    $totalRows = 0;
    $totalPages = 1;
    $page = 1;

    if ($error === '') {
        $error =
            'Study materials could not be loaded right now.';
    }
}

/*
|--------------------------------------------------------------------------
| Metrics
|--------------------------------------------------------------------------
*/

$activeCount = 0;
$inactiveCount = 0;
$subscriptionCount = 0;

foreach ($items as $item) {

    if (
        (string)$item['status'] ===
        'Active'
    ) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }

    if (
        (string)$item['access_type'] ===
        'Subscription Only'
    ) {
        $subscriptionCount++;
    }
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

<title>
    Study Materials | ExamSphere
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
    --page:#f6f3eb;
    --text:#352c27;
    --muted:#867a72;
    --line:#e7dfd5;
    --soft:#fbfaf6;
    --white:#fff;
    --green:#4f7040;
    --red:#995048;
    --amber:#99743a;
}

*{
    box-sizing:border-box;
}

body.portal-body{
    margin:0;
    background:
        radial-gradient(
            circle at 8% 0%,
            rgba(85,107,47,.08),
            transparent 26%
        ),
        linear-gradient(
            135deg,
            #faf8f2,
            #efebe4
        );
    color:var(--text);
    font-family:'Poppins',sans-serif;
}

.material-page{
    width:min(
        1420px,
        calc(100vw - 22px)
    );
    margin:0 auto;
    padding:20px 0 55px;
}

.material-head{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:20px;
    margin-bottom:18px;
}

.kicker{
    color:var(--olive);
    font-size:.59rem;
    font-weight:900;
    letter-spacing:.14em;
}

.material-head h1{
    margin:7px 0 4px;
    color:var(--earth);
    font-size:1.65rem;
    font-weight:900;
    letter-spacing:-.035em;
}

.material-head p{
    margin:0;
    color:var(--muted);
    font-size:.62rem;
}

.metric-grid{
    display:grid;
    grid-template-columns:
        repeat(4,minmax(0,1fr));
    gap:11px;
    margin-bottom:18px;
}

.metric{
    padding:14px;
    border:1px solid var(--line);
    border-radius:15px;
    background:rgba(255,255,255,.88);
    box-shadow:
        0 10px 25px rgba(62,45,37,.05);
}

.metric small{
    display:block;
    color:var(--muted);
    font-size:.49rem;
    font-weight:750;
}

.metric strong{
    display:block;
    margin-top:4px;
    color:var(--earth);
    font-size:1rem;
    font-weight:900;
}

.metric.active strong{
    color:var(--green);
}

.metric.inactive strong{
    color:var(--red);
}

.metric.subscription strong{
    color:var(--olive);
}

.main-grid{
    display:grid;
    grid-template-columns:
        minmax(300px,360px)
        minmax(0,1fr);
    gap:18px;
    align-items:start;
}

.card{
    overflow:hidden;
    border:1px solid rgba(93,64,55,.08);
    border-radius:20px;
    background:rgba(255,255,255,.89);
    box-shadow:
        0 18px 44px rgba(62,45,37,.07);
}

.card-head{
    padding:17px 18px;
    border-bottom:1px solid var(--line);
}

.card-head h2{
    margin:0;
    color:var(--earth);
    font-size:.85rem;
    font-weight:900;
}

.card-head p{
    margin:4px 0 0;
    color:var(--muted);
    font-size:.55rem;
}

.form-body{
    padding:18px;
}

.field{
    margin-bottom:12px;
}

.field label{
    display:block;
    margin-bottom:5px;
    color:var(--earth);
    font-size:.53rem;
    font-weight:850;
}

.control{
    width:100%;
    min-height:40px;
    border:1px solid #ddd4cb;
    border-radius:10px;
    padding:8px 10px;
    background:#fff;
    color:var(--text);
    outline:none;
    font-size:.59rem;
}

textarea.control{
    min-height:85px;
    resize:vertical;
}

.control:focus{
    border-color:var(--olive);
    box-shadow:
        0 0 0 .2rem rgba(85,107,47,.10);
}

.file-note{
    margin-top:4px;
    color:var(--muted);
    font-size:.47rem;
    line-height:1.5;
}

.submit-btn{
    min-height:42px;
    border:0;
    border-radius:10px;
    background:var(--earth);
    color:#fff;
    font-size:.59rem;
    font-weight:850;
}

.submit-btn:hover{
    background:var(--earth-dark);
    color:#fff;
}

.filters{
    display:grid;
    grid-template-columns:
        minmax(220px,1.5fr)
        minmax(165px,1fr)
        minmax(145px,1fr)
        minmax(145px,1fr)
        90px;
    gap:9px;
    padding:15px 17px;
    border-bottom:1px solid var(--line);
    background:#fcfaf6;
    align-items:end;
}

.filter-label{
    display:block;
    margin-bottom:5px;
    color:var(--earth);
    font-size:.48rem;
    font-weight:850;
}

.filter-btn,
.clear-btn{
    min-height:40px;
    border-radius:10px;
    font-size:.54rem;
    font-weight:850;
}

.filter-btn{
    width:100%;
    border:0;
    background:var(--earth);
    color:#fff;
}

.clear-btn{
    width:100%;
    border:1px solid #ddd4cb;
    background:#fff;
    color:var(--earth);
    display:flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
}

.clear-btn:hover{
    background:#f5f0e9;
    color:var(--earth);
}

.table-wrap{
    overflow:auto;
}

.material-table{
    width:100%;
    min-width:930px;
    border-collapse:collapse;
}

.material-table th{
    padding:11px 13px;
    border-bottom:1px solid var(--line);
    background:#f8f4ec;
    color:#786c63;
    text-align:left;
    font-size:.49rem;
    font-weight:900;
    letter-spacing:.07em;
    text-transform:uppercase;
    white-space:nowrap;
}

.material-table td{
    padding:13px;
    border-bottom:1px solid #eee8e0;
    vertical-align:middle;
    font-size:.55rem;
}

.material-table tr:last-child td{
    border-bottom:0;
}

.material-title{
    display:block;
    max-width:245px;
    overflow:hidden;
    color:var(--earth);
    font-size:.61rem;
    font-weight:850;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.material-description{
    display:block;
    max-width:245px;
    margin-top:3px;
    overflow:hidden;
    color:var(--muted);
    font-size:.48rem;
    line-height:1.45;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.badge-soft{
    display:inline-flex;
    align-items:center;
    padding:5px 8px;
    border-radius:999px;
    font-size:.46rem;
    font-weight:850;
    white-space:nowrap;
}

.badge-active{
    background:#eaf3e7;
    color:var(--green);
}

.badge-inactive{
    background:#f9eae7;
    color:var(--red);
}

.badge-public{
    background:#eef3e9;
    color:var(--olive);
}

.badge-sub{
    background:#fff2d9;
    color:var(--amber);
}

.action-group{
    display:flex;
    align-items:center;
    gap:5px;
}

.icon-btn{
    width:31px;
    height:31px;
    border:0;
    border-radius:8px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    font-size:.50rem;
}

.view{
    background:#f0ebe3;
    color:var(--earth);
}

.edit{
    background:#edf3e9;
    color:var(--olive);
}

.delete{
    background:#f9e8e6;
    color:var(--red);
}

.toggle{
    background:#f4f0e8;
    color:#786a60;
}

.modal-content{
    border:0;
    border-radius:18px;
    overflow:hidden;
}

.modal-header{
    border:0;
    background:#f6f1e7;
}

.modal-title{
    color:var(--earth);
    font-size:.85rem;
    font-weight:900;
}

.modal-body{
    background:#fff;
}

.pagination-bar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:13px 16px;
    border-top:1px solid var(--line);
    background:#fcfaf6;
}

.page-info{
    color:var(--muted);
    font-size:.50rem;
    font-weight:700;
}

.pages{
    display:flex;
    gap:5px;
}

.page-link{
    width:29px;
    height:29px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:1px solid var(--line);
    border-radius:7px;
    background:#fff;
    color:var(--earth);
    text-decoration:none;
    font-size:.50rem;
    font-weight:850;
}

.page-link:hover,
.page-link.active{
    background:var(--earth);
    color:#fff;
    border-color:var(--earth);
}

.page-link.disabled{
    pointer-events:none;
    opacity:.4;
}

.alert{
    border-radius:11px;
    font-size:.58rem;
}

.empty{
    padding:48px 18px;
    text-align:center;
}

.empty i{
    color:#aa9e92;
    font-size:1.55rem;
}

.empty strong{
    display:block;
    margin-top:8px;
    color:var(--earth);
    font-size:.67rem;
}

.empty span{
    display:block;
    margin-top:4px;
    color:var(--muted);
    font-size:.51rem;
}

@media(max-width:1100px){

    .main-grid{
        grid-template-columns:1fr;
    }

    .metric-grid{
        grid-template-columns:
            repeat(2,minmax(0,1fr));
    }

}

@media(max-width:900px){

    .filters{
        grid-template-columns:
            1fr 1fr;
    }

}

@media(max-width:650px){

    .material-page{
        width:calc(100vw - 12px);
    }

    .material-head{
        align-items:flex-start;
        flex-direction:column;
    }

    .material-head h1{
        font-size:1.45rem;
    }

    .metric-grid{
        grid-template-columns:1fr;
    }

    .filters{
        grid-template-columns:1fr;
    }

    .pagination-bar{
        align-items:flex-start;
        flex-direction:column;
    }

}


/* ===== Readability Upgrade: same Materials layout, larger and bolder text ===== */
body.portal-body{font-size:16px;}
.subject-page-heading h1{font-size:2.35rem!important;font-weight:800!important;line-height:1.15!important;}
.subject-page-heading p{font-size:1rem!important;font-weight:500!important;line-height:1.6!important;}
.subject-page-heading>div>span{font-size:.78rem!important;font-weight:800!important;letter-spacing:.12em!important;}
.subject-anchor-button{font-size:.9rem!important;font-weight:800!important;padding:12px 18px!important;}
.subject-card-heading h2{font-size:1.35rem!important;font-weight:800!important;}
.subject-card-heading p,.subject-card-heading small{font-size:.9rem!important;font-weight:500!important;line-height:1.55!important;}
.mini-kicker{font-size:.72rem!important;font-weight:800!important;letter-spacing:.11em!important;}
.subject-form-card label,.subject-list-card th{font-size:.86rem!important;font-weight:800!important;}
.subject-form-card label small{font-size:.75rem!important;font-weight:700!important;}
.subject-input input,.subject-input select,.subject-input textarea{font-size:.95rem!important;font-weight:600!important;min-height:50px!important;}
.subject-input textarea{min-height:110px!important;line-height:1.55!important;}
.subject-input input::placeholder,.subject-input textarea::placeholder{font-size:.9rem!important;font-weight:500!important;}
.save-subject{font-size:.9rem!important;font-weight:800!important;min-height:50px!important;}
.stats-card small,.stats-card .small{font-size:.82rem!important;font-weight:700!important;}
.stats-card b,.stats-card strong{font-size:1.45rem!important;font-weight:800!important;}
.stats-card span,.stats-card p{font-size:.82rem!important;font-weight:600!important;}
.subject-table td{font-size:.86rem!important;font-weight:600!important;line-height:1.5!important;}
.subject-table td b{font-size:.95rem!important;font-weight:800!important;}
.subject-table td small{font-size:.78rem!important;font-weight:500!important;line-height:1.45!important;}
.code-pill,.status-pill{font-size:.76rem!important;font-weight:800!important;}
.subject-table .action-btn,.subject-table button{font-size:.85rem!important;font-weight:800!important;}
.subject-alert{font-size:.88rem!important;font-weight:700!important;}
@media(max-width:700px){
 .subject-page-heading h1{font-size:2rem!important;}
 .subject-page-heading p{font-size:.9rem!important;}
 .subject-card-heading h2{font-size:1.2rem!important;}
 .subject-table td{font-size:.84rem!important;}
}
</style>

</head>

<body class="portal-body">

<div class="portal-layout">

<?php include 'includes/sidebar.php'; ?>

<main class="portal-main">

<div class="material-page">

<header class="material-head">

<div>

<div class="kicker">

<i class="fa-solid fa-book-open me-1"></i>

LEARNING RESOURCES

</div>

<h1>
    Study Materials
</h1>

<p>
    Publish, update and manage learning resources for your students.
</p>

</div>

</header>


<?php if ($message !== ''): ?>

<div class="alert alert-success mb-3">

<i class="fa-solid fa-circle-check me-1"></i>

<?= teacher_material_e(
    $message
) ?>

</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div class="alert alert-danger mb-3">

<i class="fa-solid fa-circle-exclamation me-1"></i>

<?= teacher_material_e(
    $error
) ?>

</div>

<?php endif; ?>


<section class="metric-grid">

<div class="metric">

<small>
    Matching Resources
</small>

<strong>
    <?= (int)$totalRows ?>
</strong>

</div>


<div class="metric active">

<small>
    Active on Page
</small>

<strong>
    <?= (int)$activeCount ?>
</strong>

</div>


<div class="metric inactive">

<small>
    Inactive on Page
</small>

<strong>
    <?= (int)$inactiveCount ?>
</strong>

</div>


<div class="metric subscription">

<small>
    Subscription Only on Page
</small>

<strong>
    <?= (int)$subscriptionCount ?>
</strong>

</div>

</section>


<div class="main-grid">


<section class="card">

<div class="card-head">

<h2>
    <i class="fa-solid fa-cloud-arrow-up me-2"></i>
    Publish Material
</h2>

<p>
    Supported: PDF, DOC, DOCX, PPT and PPTX · Maximum 10 MB.
</p>

</div>


<div class="form-body">

<form
    method="post"
    enctype="multipart/form-data"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="action"
    value="upload"
>


<div class="field">

<label for="material-title">
    Material Title
</label>

<input
    id="material-title"
    class="control"
    type="text"
    name="title"
    maxlength="180"
    required
    placeholder="e.g. Cyber Security Unit 1 Notes"
>

</div>


<div class="field">

<label for="material-subject">
    Subject
</label>

<select
    id="material-subject"
    class="control"
    name="subject_id"
    required
>

<option value="">
    Select subject
</option>

<?php foreach (
    $subjects as $subject
): ?>

<option
    value="<?= (int)$subject['id'] ?>"
>

<?= teacher_material_e(
    $subject['name']
) ?>

<?php if (
    !empty($subject['code'])
): ?>

(
<?= teacher_material_e(
    $subject['code']
) ?>
)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label for="material-description">
    Description
</label>

<textarea
    id="material-description"
    class="control"
    name="description"
    maxlength="5000"
    placeholder="Describe this resource..."
></textarea>

</div>


<div class="field">

<label for="material-access">
    Access Level
</label>

<select
    id="material-access"
    class="control"
    name="access_type"
>

<option value="Public">
    Public
</option>

<option value="Subscription Only">
    Subscription Only
</option>

</select>

</div>


<div class="field">

<label for="material-file">
    File
</label>

<input
    id="material-file"
    class="control"
    type="file"
    name="material_file"
    accept=".pdf,.doc,.docx,.ppt,.pptx"
    required
>

<div class="file-note">
    Files are stored using generated names and validated by detected MIME type.
</div>

</div>


<button
    type="submit"
    class="btn submit-btn w-100"
>

<i class="fa-solid fa-upload me-1"></i>

Publish Material

</button>

</form>

</div>

</section>


<section class="card">

<div class="card-head">

<h2>
    My Published Resources
</h2>

<p>
    Only materials owned by the logged-in teacher are listed.
</p>

</div>


<form
    method="get"
    class="filters"
>

<div>

<label
    class="filter-label"
    for="material-search"
>
    Search
</label>

<input
    id="material-search"
    class="control"
    type="search"
    name="search"
    value="<?= teacher_material_e(
        $search
    ) ?>"
    placeholder="Title or description..."
>

</div>


<div>

<label
    class="filter-label"
    for="material-filter-subject"
>
    Subject
</label>

<select
    id="material-filter-subject"
    class="control"
    name="subject_id"
>

<option value="">
    All subjects
</option>

<?php foreach (
    $subjects as $subject
): ?>

<option
    value="<?= (int)$subject['id'] ?>"
    <?= $subjectFilter !== null &&
        $subjectFilter ===
        (int)$subject['id']
        ? 'selected'
        : ''
    ?>
>

<?= teacher_material_e(
    $subject['name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div>

<label
    class="filter-label"
    for="material-filter-access"
>
    Access
</label>

<select
    id="material-filter-access"
    class="control"
    name="access_type"
>

<option value="">
    All access
</option>

<option
    value="Public"
    <?= $accessFilter === 'Public'
        ? 'selected'
        : ''
    ?>
>
    Public
</option>

<option
    value="Subscription Only"
    <?= $accessFilter ===
        'Subscription Only'
        ? 'selected'
        : ''
    ?>
>
    Subscription Only
</option>

</select>

</div>


<div>

<label
    class="filter-label"
    for="material-filter-status"
>
    Status
</label>

<select
    id="material-filter-status"
    class="control"
    name="status"
>

<option value="">
    All status
</option>

<option
    value="Active"
    <?= $statusFilter === 'Active'
        ? 'selected'
        : ''
    ?>
>
    Active
</option>

<option
    value="Inactive"
    <?= $statusFilter === 'Inactive'
        ? 'selected'
        : ''
    ?>
>
    Inactive
</option>

</select>

</div>


<div>

<label class="filter-label">&nbsp;</label>

<div class="d-flex gap-1">

<button
    type="submit"
    class="btn filter-btn"
>

<i class="fa-solid fa-filter"></i>

</button>

<a
    href="materials.php"
    class="clear-btn"
    title="Clear filters"
>

<i class="fa-solid fa-xmark"></i>

</a>

</div>

</div>

</form>


<?php if ($items): ?>

<div class="table-wrap">

<table class="material-table">

<thead>

<tr>

<th>
    Material
</th>

<th>
    Subject
</th>

<th>
    Access
</th>

<th>
    Status
</th>

<th>
    Published
</th>

<th>
    Actions
</th>

</tr>

</thead>


<tbody>

<?php foreach (
    $items as $item
): ?>

<tr>

<td>

<span class="material-title">

<?= teacher_material_e(
    $item['title']
) ?>

</span>

<span class="material-description">

<?= teacher_material_e(
    $item['description']
        ?: 'No description'
) ?>

</span>

</td>


<td>

<?= teacher_material_e(
    $item['subject_name']
        ?: '—'
) ?>

<?php if (
    !empty(
        $item['subject_code']
    )
): ?>

<div class="material-description">

<?= teacher_material_e(
    $item['subject_code']
) ?>

</div>

<?php endif; ?>

</td>


<td>

<span
    class="badge-soft <?= $item['access_type'] === 'Public'
        ? 'badge-public'
        : 'badge-sub'
    ?>"
>

<i
    class="fa-solid <?= $item['access_type'] === 'Public'
        ? 'fa-globe'
        : 'fa-lock'
    ?> me-1"
></i>

<?= teacher_material_e(
    $item['access_type']
) ?>

</span>

</td>


<td>

<span
    class="badge-soft <?= $item['status'] === 'Active'
        ? 'badge-active'
        : 'badge-inactive'
    ?>"
>

<?= teacher_material_e(
    $item['status']
) ?>

</span>

</td>


<td>

<?= teacher_material_e(
    teacher_material_date(
        $item['uploaded_at']
    )
) ?>

</td>


<td>

<div class="action-group">

<a
    class="icon-btn view"
    href="../<?= teacher_material_e(
        ltrim(
            (string)$item['file_path'],
            '/'
        )
    ) ?>"
    target="_blank"
    rel="noopener noreferrer"
    title="View material"
>

<i class="fa-solid fa-eye"></i>

</a>


<button
    type="button"
    class="icon-btn edit"
    data-bs-toggle="modal"
    data-bs-target="#editMaterialModal"
    data-id="<?= (int)$item['id'] ?>"
    data-title="<?= teacher_material_e(
        $item['title']
    ) ?>"
    data-description="<?= teacher_material_e(
        $item['description']
    ) ?>"
    data-subject="<?= (int)$item['subject_id'] ?>"
    data-access="<?= teacher_material_e(
        $item['access_type']
    ) ?>"
    data-status="<?= teacher_material_e(
        $item['status']
    ) ?>"
    title="Edit material"
>

<i class="fa-solid fa-pen"></i>

</button>


<form
    method="post"
    class="d-inline"
    onsubmit="return confirm('Change this material status?');"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="action"
    value="toggle_status"
>

<input
    type="hidden"
    name="material_id"
    value="<?= (int)$item['id'] ?>"
>

<button
    type="submit"
    class="icon-btn toggle"
    title="Toggle status"
>

<i class="fa-solid fa-power-off"></i>

</button>

</form>


<form
    method="post"
    class="d-inline"
    onsubmit="return confirm('Delete this material permanently?');"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="action"
    value="delete"
>

<input
    type="hidden"
    name="material_id"
    value="<?= (int)$item['id'] ?>"
>

<button
    type="submit"
    class="icon-btn delete"
    title="Delete material"
>

<i class="fa-solid fa-trash"></i>

</button>

</form>

</div>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<?php if (
    $totalPages > 1
): ?>

<div class="pagination-bar">

<div class="page-info">

Page
<?= (int)$page ?>
of
<?= (int)$totalPages ?>

&nbsp; · &nbsp;

<?= (int)$totalRows ?>
resources

</div>


<div class="pages">

<a
    class="page-link <?= $page <= 1
        ? 'disabled'
        : ''
    ?>"
    href="<?= teacher_material_url(
        max(
            1,
            $page - 1
        ),
        $search,
        $subjectFilter,
        $statusFilter,
        $accessFilter
    ) ?>"
>

<i class="fa-solid fa-angle-left"></i>

</a>


<?php

$startPage =
    max(
        1,
        $page - 2
    );

$endPage =
    min(
        $totalPages,
        $page + 2
    );

?>

<?php for (
    $number = $startPage;
    $number <= $endPage;
    $number++
): ?>

<a
    class="page-link <?= $number === $page
        ? 'active'
        : ''
    ?>"
    href="<?= teacher_material_url(
        $number,
        $search,
        $subjectFilter,
        $statusFilter,
        $accessFilter
    ) ?>"
>

<?= (int)$number ?>

</a>

<?php endfor; ?>


<a
    class="page-link <?= $page >= $totalPages
        ? 'disabled'
        : ''
    ?>"
    href="<?= teacher_material_url(
        min(
            $totalPages,
            $page + 1
        ),
        $search,
        $subjectFilter,
        $statusFilter,
        $accessFilter
    ) ?>"
>

<i class="fa-solid fa-angle-right"></i>

</a>

</div>

</div>

<?php endif; ?>


<?php else: ?>

<div class="empty">

<i class="fa-solid fa-folder-open"></i>

<strong>
    No study materials found.
</strong>

<span>
    Publish your first resource or clear the current filters.
</span>

</div>

<?php endif; ?>

</section>

</div>


</div>

</main>

</div>


<div
    class="modal fade"
    id="editMaterialModal"
    tabindex="-1"
    aria-hidden="true"
>

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content">

<div class="modal-header">

<div>

<div class="kicker">
    EDIT RESOURCE
</div>

<h2 class="modal-title">
    Update Material
</h2>

</div>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
    aria-label="Close"
></button>

</div>


<form
    method="post"
    enctype="multipart/form-data"
>

<?= csrf_field() ?>

<input
    type="hidden"
    name="action"
    value="update"
>

<input
    type="hidden"
    id="edit-material-id"
    name="material_id"
    value=""
>


<div class="modal-body p-4">


<div class="field">

<label for="edit-title">
    Material Title
</label>

<input
    id="edit-title"
    class="control"
    type="text"
    name="title"
    maxlength="180"
    required
>

</div>


<div class="field">

<label for="edit-subject">
    Subject
</label>

<select
    id="edit-subject"
    class="control"
    name="subject_id"
    required
>

<?php foreach (
    $subjects as $subject
): ?>

<option
    value="<?= (int)$subject['id'] ?>"
>

<?= teacher_material_e(
    $subject['name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label for="edit-description">
    Description
</label>

<textarea
    id="edit-description"
    class="control"
    name="description"
    maxlength="5000"
></textarea>

</div>


<div class="field">

<label for="edit-access">
    Access Level
</label>

<select
    id="edit-access"
    class="control"
    name="access_type"
>

<option value="Public">
    Public
</option>

<option value="Subscription Only">
    Subscription Only
</option>

</select>

</div>


<div class="field">

<label for="edit-status">
    Status
</label>

<select
    id="edit-status"
    class="control"
    name="status"
>

<option value="Active">
    Active
</option>

<option value="Inactive">
    Inactive
</option>

</select>

</div>


<div class="field">

<label for="edit-file">
    Replace File
</label>

<input
    id="edit-file"
    class="control"
    type="file"
    name="material_file"
    accept=".pdf,.doc,.docx,.ppt,.pptx"
>

<div class="file-note">
    Leave blank to keep the current file.
</div>

</div>


<button
    type="submit"
    class="btn submit-btn w-100"
>

<i class="fa-solid fa-floppy-disk me-1"></i>

Save Changes

</button>

</div>

</form>

</div>

</div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

<script>

document
    .getElementById('editMaterialModal')
    .addEventListener(
        'show.bs.modal',
        function (event) {

            const button =
                event.relatedTarget;

            if (!button) {
                return;
            }

            document
                .getElementById(
                    'edit-material-id'
                )
                .value =
                    button.dataset.id || '';

            document
                .getElementById(
                    'edit-title'
                )
                .value =
                    button.dataset.title || '';

            document
                .getElementById(
                    'edit-description'
                )
                .value =
                    button.dataset.description || '';

            document
                .getElementById(
                    'edit-subject'
                )
                .value =
                    button.dataset.subject || '';

            document
                .getElementById(
                    'edit-access'
                )
                .value =
                    button.dataset.access || 'Public';

            document
                .getElementById(
                    'edit-status'
                )
                .value =
                    button.dataset.status || 'Active';
        }
    );

</script>

</body>

</html>
