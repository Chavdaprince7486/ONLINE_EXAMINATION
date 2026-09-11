<?php
require_once '../config/session.php';
require_once '../config/config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$error = '';
$uploadDirectory = dirname(__DIR__) . '/uploads/materials';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));

        if (!verify_csrf_token($csrfToken)) {
            throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'upload') {
            $title = trim($_POST['title'] ?? '');
            $subjectId = (int) ($_POST['subject_id'] ?? 0);
            $access = $_POST['access_type'] ?? 'Public';
            $file = $_FILES['material_file'] ?? null;
            if ($title === '' || !$file || $file['error'] !== UPLOAD_ERR_OK || !in_array($access, ['Public', 'Subscription Only'], true)) {
                throw new RuntimeException('Complete the material details and select a valid file.');
            }
            if ($file['size'] > 10 * 1024 * 1024) throw new RuntimeException('The material file must be 10 MB or smaller.');
            $allowed = ['application/pdf' => 'pdf', 'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'];
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!isset($allowed[$mime])) throw new RuntimeException('Only PDF, DOC and DOCX study materials are allowed.');
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) throw new RuntimeException('Unable to prepare the materials upload folder.');
            $storedName = 'material_' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . '/' . $storedName)) throw new RuntimeException('The material could not be uploaded.');
            $insert = $conn->prepare("INSERT INTO study_materials (subject_id, title, description, file_path, access_type, status) VALUES (?, ?, ?, ?, ?, 'Active')");
            $insert->execute([$subjectId ?: null, $title, trim($_POST['description'] ?? '') ?: null, $storedName, $access]);
            $message = 'Study material uploaded successfully.';
        }
        if ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $get = $conn->prepare('SELECT file_path FROM study_materials WHERE id = ?');
            $get->execute([$id]);
            $material = $get->fetch(PDO::FETCH_ASSOC);
            if (!$material) throw new RuntimeException('Study material was not found.');
            $conn->prepare('DELETE FROM study_materials WHERE id = ?')->execute([$id]);
            $path = realpath($uploadDirectory . '/' . basename($material['file_path']));
            $root = realpath($uploadDirectory);
            if ($path && $root && str_starts_with($path, $root . DIRECTORY_SEPARATOR)) unlink($path);
            $message = 'Study material deleted.';
        }
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$subjects = $conn->query("SELECT id, name FROM subjects WHERE status = 'Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$materials = $conn->query("SELECT m.*, s.name AS subject_name FROM study_materials m LEFT JOIN subjects s ON s.id = m.subject_id ORDER BY m.uploaded_at DESC")->fetchAll(PDO::FETCH_ASSOC);
function material_escape($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$page_title = 'Study Materials | ExamSphere';
$page_css = 'admin-subjects.css';
include 'includes/header.php';
?>
<div class="dashboard-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'includes/navbar.php'; ?>
        <main class="dashboard-content subject-page">
            <div class="subject-page-heading"><div><span><i class="fa-solid fa-book-open"></i> LEARNING RESOURCES</span><h1>Study Materials</h1><p>Upload approved PDF and document resources for your students.</p></div><a class="subject-anchor-button" href="#upload-material"><i class="fa-solid fa-upload"></i> Upload material</a></div>
            <?php if ($message): ?><div class="subject-alert success"><i class="fa-solid fa-circle-check"></i><?= material_escape($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="subject-alert error"><i class="fa-solid fa-circle-exclamation"></i><?= material_escape($error) ?></div><?php endif; ?>
            <section class="subject-stat-row"><article><i class="fa-solid fa-folder-open"></i><span><small>Total materials</small><b><?= count($materials) ?></b></span></article><article><i class="fa-solid fa-lock-open"></i><span><small>Public resources</small><b><?= count(array_filter($materials, fn($item) => $item['access_type'] === 'Public')) ?></b></span></article><article><i class="fa-solid fa-crown"></i><span><small>Subscription resources</small><b><?= count(array_filter($materials, fn($item) => $item['access_type'] === 'Subscription Only')) ?></b></span></article></section>
            <div class="subject-layout">
                <section class="subject-form-card" id="upload-material"><div class="subject-card-heading"><div><span class="mini-kicker">UPLOAD</span><h2>Add study material</h2></div><i class="fa-solid fa-cloud-arrow-up"></i></div>
                    <form method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="upload"><input type="hidden" name="csrf_token" value="<?= material_escape(csrf_token()) ?>"><label for="material-title">Title</label><div class="subject-input"><i class="fa-solid fa-heading"></i><input id="material-title" name="title" maxlength="180" placeholder="e.g. Unit 1 Important Notes" required></div><label for="material-subject">Subject <small>Optional</small></label><div class="subject-input"><i class="fa-solid fa-book"></i><select id="material-subject" name="subject_id"><option value="">General material</option><?php foreach ($subjects as $subject): ?><option value="<?= (int) $subject['id'] ?>"><?= material_escape($subject['name']) ?></option><?php endforeach; ?></select></div><label for="material-access">Student access</label><div class="subject-input"><i class="fa-solid fa-shield-halved"></i><select id="material-access" name="access_type"><option>Public</option><option>Subscription Only</option></select></div><label for="material-description">Description <small>Optional</small></label><div class="subject-input textarea"><i class="fa-solid fa-align-left"></i><textarea id="material-description" name="description" maxlength="1000" placeholder="What will this material help students learn?"></textarea></div><label for="material-file">Material file</label><div class="subject-input file-input"><i class="fa-solid fa-file-arrow-up"></i><input id="material-file" name="material_file" type="file" accept=".pdf,.doc,.docx" required></div><button class="save-subject" type="submit"><i class="fa-solid fa-upload"></i> Upload material</button></form>
                </section>
                <section class="subject-list-card"><div class="subject-card-heading list-heading"><div><span class="mini-kicker">LIBRARY</span><h2>Published materials</h2></div></div><div class="subject-table-wrap"><table class="subject-table"><thead><tr><th>Material</th><th>Subject</th><th>Access</th><th>Uploaded</th><th>Action</th></tr></thead><tbody><?php foreach ($materials as $material): ?><tr><td data-label="Material"><b><?= material_escape($material['title']) ?></b><small><?= material_escape($material['description'] ?: basename($material['file_path'])) ?></small></td><td data-label="Subject"><span class="code-pill"><?= material_escape($material['subject_name'] ?: 'General') ?></span></td><td data-label="Access"><span class="status-pill <?= $material['access_type'] === 'Public' ? 'active' : 'inactive' ?>"><?= material_escape($material['access_type']) ?></span></td><td data-label="Uploaded"><?= date('d M Y', strtotime($material['uploaded_at'])) ?></td><td data-label="Action"><form method="post" class="delete-confirm"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $material['id'] ?>"><input type="hidden" name="csrf_token" value="<?= material_escape(csrf_token()) ?>"><button class="delete-subject" type="submit" aria-label="Delete material"><i class="fa-solid fa-trash"></i></button></form></td></tr><?php endforeach; ?><?php if (!$materials): ?><tr class="subject-empty"><td colspan="5"><i class="fa-solid fa-book-open"></i>No materials uploaded yet.</td></tr><?php endif; ?></tbody></table></div></section>
            </div>
        </main>
    </div>
</div>
<script src="assets/js/admin-shell.js"></script>
<script src="assets/js/admin-materials.js"></script>
</body></html>
