<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'teacher'
) {
    header('Location: ../auth/login.php');
    exit;
}

$teacherId = (int) $_SESSION['user_id'];

$message = '';
$error = '';

$uploadDirectory = dirname(__DIR__) . '/uploads/materials';
$storedDirectory = 'uploads/materials';

$subjects = $conn
    ->query(
        "SELECT id, name
         FROM subjects
         WHERE status = 'Active'
         ORDER BY name ASC"
    )
    ->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? 'upload';

    if ($action === 'upload') {

        $title = trim($_POST['title'] ?? '');

        $subjectId = filter_input(
            INPUT_POST,
            'subject_id',
            FILTER_VALIDATE_INT
        );

        $description = trim(
            $_POST['description'] ?? ''
        );

        $accessType =
            ($_POST['access_type'] ?? 'Public')
            === 'Subscription Only'
                ? 'Subscription Only'
                : 'Public';

        $file = $_FILES['material_file'] ?? null;

        try {

            if ($title === '') {
                throw new RuntimeException(
                    'Material title is required.'
                );
            }

            if (
                $subjectId === false ||
                $subjectId === null ||
                (int) $subjectId <= 0
            ) {
                throw new RuntimeException(
                    'Please select a valid subject.'
                );
            }

            $subjectId = (int) $subjectId;

            $subjectCheck = $conn->prepare(
                "SELECT id
                 FROM subjects
                 WHERE id = ?
                   AND status = 'Active'
                 LIMIT 1"
            );

            $subjectCheck->execute([
                $subjectId
            ]);

            if (!$subjectCheck->fetchColumn()) {
                throw new RuntimeException(
                    'The selected subject is not available.'
                );
            }

            if (!$file || !isset($file['error'])) {
                throw new RuntimeException(
                    'Please select a learning material file.'
                );
            }

            if (
                (int) $file['error']
                !== UPLOAD_ERR_OK
            ) {
                switch ((int) $file['error']) {

                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        throw new RuntimeException(
                            'The uploaded file is too large.'
                        );

                    case UPLOAD_ERR_PARTIAL:
                        throw new RuntimeException(
                            'The file upload was incomplete. Please try again.'
                        );

                    case UPLOAD_ERR_NO_FILE:
                        throw new RuntimeException(
                            'Please select a learning material file.'
                        );

                    default:
                        throw new RuntimeException(
                            'The file could not be uploaded.'
                        );
                }
            }

            $maxFileSize =
                10 * 1024 * 1024;

            if (
                (int) $file['size']
                <= 0
            ) {
                throw new RuntimeException(
                    'The selected file is empty.'
                );
            }

            if (
                (int) $file['size']
                > $maxFileSize
            ) {
                throw new RuntimeException(
                    'The material file must be 10 MB or smaller.'
                );
            }

            if (
                empty($file['tmp_name']) ||
                !is_uploaded_file($file['tmp_name'])
            ) {
                throw new RuntimeException(
                    'Invalid upload detected.'
                );
            }

            $finfo = new finfo(
                FILEINFO_MIME_TYPE
            );

            $mimeType =
                $finfo->file(
                    $file['tmp_name']
                );

            $allowedMimeTypes = [

                'application/pdf' =>
                    'pdf',

                'application/msword' =>
                    'doc',

                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' =>
                    'docx',

                'application/vnd.ms-powerpoint' =>
                    'ppt',

                'application/vnd.openxmlformats-officedocument.presentationml.presentation' =>
                    'pptx',

            ];

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
                !is_dir($uploadDirectory)
                &&
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
                !is_writable($uploadDirectory)
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

            $databasePath =
                $storedDirectory .
                '/' .
                $storedFileName;

            if (
                !move_uploaded_file(
                    $file['tmp_name'],
                    $targetPath
                )
            ) {
                throw new RuntimeException(
                    'The file could not be saved. Please try again.'
                );
            }

            try {

                $insert = $conn->prepare(
                    "INSERT INTO study_materials
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
                    )"
                );

                $insert->execute([
                    $subjectId,
                    $teacherId,
                    $title,
                    $description !== ''
                        ? $description
                        : null,
                    $databasePath,
                    $accessType
                ]);

            } catch (Throwable $databaseException) {

                if (is_file($targetPath)) {
                    @unlink($targetPath);
                }

                throw $databaseException;
            }

            $message =
                'Study material published successfully.';

        } catch (Throwable $exception) {

            error_log(
                'Teacher material upload error: ' .
                $exception->getMessage()
            );

            $error =
                $exception->getMessage();
        }
    }
}

$materialsQuery = $conn->prepare(
    "SELECT
        m.id,
        m.title,
        m.description,
        m.file_path,
        m.access_type,
        m.status,
        m.uploaded_at,
        s.name AS subject_name
     FROM study_materials AS m
     LEFT JOIN subjects AS s
        ON s.id = m.subject_id
     WHERE m.teacher_id = ?
     ORDER BY m.uploaded_at DESC"
);

$materialsQuery->execute([
    $teacherId
]);

$items =
    $materialsQuery->fetchAll(
        PDO::FETCH_ASSOC
    );

?>
<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Study Materials | ExamSphere</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/portal.css"
    >

</head>

<body class="portal-body">

<div class="portal-layout">

    <?php include 'includes/sidebar.php'; ?>

    <main class="portal-main">

        <header class="portal-topbar">

            <div>

                <h1>Study materials</h1>

                <p class="portal-subtitle">
                    Publish safe learning resources for your students.
                </p>

            </div>

        </header>

        <?php if ($message !== ''): ?>

            <div class="alert alert-success">
                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

        <?php if ($error !== ''): ?>

            <div class="alert alert-danger">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

        <div class="row g-4">

            <div class="col-xl-4">

                <section class="portal-panel mt-0">

                    <h2 class="h5 mb-3">

                        <i class="fa-solid fa-cloud-arrow-up me-2"></i>

                        Publish material

                    </h2>

                    <form
                        method="post"
                        enctype="multipart/form-data"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="upload"
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"
                        >

                        <label class="form-label">
                            Material title
                        </label>

                        <input
                            class="form-control mb-3"
                            type="text"
                            name="title"
                            maxlength="180"
                            required
                            placeholder="e.g. Unit 1 preparation notes"
                        >

                        <label class="form-label">
                            Subject
                        </label>

                        <select
                            class="form-select mb-3"
                            name="subject_id"
                            required
                        >

                            <option value="">
                                Select subject
                            </option>

                            <?php foreach ($subjects as $subject): ?>

                                <option
                                    value="<?= (int) $subject['id'] ?>"
                                >
                                    <?= htmlspecialchars(
                                        $subject['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <label class="form-label">
                            Description
                        </label>

                        <textarea
                            class="form-control mb-3"
                            name="description"
                            rows="3"
                            maxlength="1000"
                            placeholder="What will students learn?"
                        ></textarea>

                        <label class="form-label">
                            Access level
                        </label>

                        <select
                            class="form-select mb-3"
                            name="access_type"
                        >

                            <option value="Public">
                                Public
                            </option>

                            <option value="Subscription Only">
                                Subscription Only
                            </option>

                        </select>

                        <label class="form-label">
                            File
                            <span class="text-muted">
                                (PDF, DOC, DOCX, PPT or PPTX — max 10 MB)
                            </span>
                        </label>

                        <input
                            class="form-control mb-3"
                            type="file"
                            name="material_file"
                            accept=".pdf,.doc,.docx,.ppt,.pptx"
                            required
                        >

                        <button
                            type="submit"
                            class="btn btn-success w-100"
                        >

                            <i class="fa-solid fa-upload me-1"></i>

                            Publish material

                        </button>

                    </form>

                </section>

            </div>

            <div class="col-xl-8">

                <section class="portal-panel mt-0">

                    <div
                        class="d-flex justify-content-between align-items-center mb-3"
                    >

                        <h2 class="h5 mb-0">
                            My published resources
                        </h2>

                        <span class="badge text-bg-light">
                            <?= count($items) ?>
                            total
                        </span>

                    </div>

                    <div class="table-responsive">

                        <table class="table align-middle">

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
                                        Published
                                    </th>

                                    <th></th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($items as $item): ?>

                                <?php
                                $safeTitle =
                                    htmlspecialchars(
                                        $item['title'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                $safeDescription =
                                    htmlspecialchars(
                                        $item['description'] ?: 'No description',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                $safeSubject =
                                    htmlspecialchars(
                                        $item['subject_name'] ?: '—',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                $accessType =
                                    htmlspecialchars(
                                        $item['access_type'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                $viewPath =
                                    '../' .
                                    ltrim(
                                        (string) $item['file_path'],
                                        '/'
                                    );
                                ?>

                                <tr>

                                    <td>

                                        <strong>
                                            <?= $safeTitle ?>
                                        </strong>

                                        <small class="d-block text-muted">
                                            <?= $safeDescription ?>
                                        </small>

                                    </td>

                                    <td>
                                        <?= $safeSubject ?>
                                    </td>

                                    <td>

                                        <span
                                            class="badge text-bg-<?= $item['access_type'] === 'Public'
                                                ? 'success'
                                                : 'warning' ?>"
                                        >
                                            <?= $accessType ?>
                                        </span>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    (string) $item['uploaded_at']
                                                )
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>

                                    <td>

                                        <a
                                            class="btn btn-sm btn-outline-secondary"
                                            href="<?= htmlspecialchars(
                                                $viewPath,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            aria-label="Open material"
                                        >

                                            <i class="fa-solid fa-eye"></i>

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            <?php if (!$items): ?>

                                <tr>

                                    <td
                                        colspan="5"
                                        class="text-center text-muted py-4"
                                    >
                                        No resources published yet.
                                        Add the first one using this form.
                                    </td>

                                </tr>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </section>

            </div>

        </div>

    </main>

</div>

</body>
</html>