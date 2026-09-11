<?php

require_once "../../config/session.php";
require_once "../../config/config.php";

$page_title = "View Exam";

/* ===============================
   CHECK ID
================================ */

if (!isset($_GET['id']) || empty($_GET['id'])) {

    header("Location: index.php");
    exit;

}

$id = (int)$_GET['id'];

/* ===============================
   FETCH EXAM DETAILS
================================ */

$stmt = $conn->prepare("

SELECT

e.*,

c.category_name,

s.subject_name,

t.full_name

FROM exams e

INNER JOIN categories c
ON c.id = e.category_id

INNER JOIN subjects s
ON s.id = e.subject_id

INNER JOIN teachers t
ON t.id = e.teacher_id

WHERE e.id = ?

LIMIT 1

");

$stmt->execute([$id]);

$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {

    header("Location: index.php");
    exit;

}

include "../includes/header.php";

?>

<div class="dashboard-wrapper">

<?php include "../includes/sidebar.php"; ?>

<div class="main-content">

<?php include "../includes/navbar.php"; ?>

<div class="dashboard-content">

<!-- ===============================
     PAGE HEADER
================================ -->

<div class="page-header">

<div>

<h1>

View Exam

</h1>

<p>

View complete examination details.

</p>

</div>

<a
href="index.php"
class="btn-add">

<i class="fa-solid fa-arrow-left"></i>

Back

</a>

</div>

<div class="form-card">

<div class="form-grid">

<!-- ==========================
     EXAM CODE
========================== -->

<div class="form-group">

    <label>Exam Code</label>

    <input
        type="text"
        value="<?= htmlspecialchars($exam['exam_code']); ?>"
        readonly>

</div>

<!-- ==========================
     EXAM TITLE
========================== -->

<div class="form-group">

    <label>Exam Title</label>

    <input
        type="text"
        value="<?= htmlspecialchars($exam['exam_title']); ?>"
        readonly>

</div>

<!-- ==========================
     CATEGORY
========================== -->

<div class="form-group">

    <label>Category</label>

    <input
        type="text"
        value="<?= htmlspecialchars($exam['category_name']); ?>"
        readonly>

</div>

<!-- ==========================
     SUBJECT
========================== -->

<div class="form-group">

    <label>Subject</label>

    <input
        type="text"
        value="<?= htmlspecialchars($exam['subject_name']); ?>"
        readonly>

</div>

<!-- ==========================
     TEACHER
========================== -->

<div class="form-group">

    <label>Teacher</label>

    <input
        type="text"
        value="<?= htmlspecialchars($exam['full_name']); ?>"
        readonly>

</div>

<!-- ==========================
     EXAM TYPE
========================== -->

<div class="form-group">

    <label>Exam Type</label>

    <input
        type="text"
        value="<?= htmlspecialchars($exam['exam_type']); ?>"
        readonly>

</div>

<!-- ==========================
     DESCRIPTION
========================== -->

<div class="form-group full-width">

    <label>Exam Description</label>

    <textarea
        rows="4"
        readonly><?= htmlspecialchars($exam['exam_description']); ?></textarea>

</div>

<!-- ==========================
     TOTAL QUESTIONS
========================== -->

<div class="form-group">

    <label>Total Questions</label>

    <input
        type="text"
        value="<?= htmlspecialchars($exam['total_questions']); ?>"
        readonly>

</div>

<!-- ==========================
     TOTAL MARKS
========================== -->

<div class="form-group">

    <label>Total Marks</label>

    <input
        type="text"
        value="<?= htmlspecialchars($exam['total_marks']); ?>"
        readonly>

</div>

<!-- ==========================
     PASSING MARKS
========================== -->

<div class="form-group">

    <label>Passing Marks</label>

    <input
        type="text"
        value="<?= htmlspecialchars($exam['passing_marks']); ?>"
        readonly>

</div>

<!-- ==========================
     DURATION
========================== -->

<div class="form-group">

    <label>Duration</label>

    <input
        type="text"
        value="<?= htmlspecialchars($exam['duration']); ?> Minutes"
        readonly>

</div>

<!-- ==========================
     NEGATIVE MARKING
========================== -->

<div class="form-group">

    <label>Negative Marking</label>

    <input
        type="text"
        value="<?= ($exam['negative_marking']) ? 'Enabled' : 'Disabled'; ?>"
        readonly>

</div>

<!-- ==========================
     NEGATIVE MARKS
========================== -->

<div class="form-group">

    <label>Negative Marks</label>

    <input
        type="text"
        value="<?= htmlspecialchars($exam['negative_marks']); ?>"
        readonly>

</div>

<!-- ==========================
     START DATE
========================== -->

<div class="form-group">

    <label>Start Date & Time</label>

    <input
        type="text"
        value="<?= !empty($exam['start_datetime']) ? date('d M Y h:i A', strtotime($exam['start_datetime'])) : '-'; ?>"
        readonly>

</div>

<!-- ==========================
     END DATE
========================== -->

<div class="form-group">

    <label>End Date & Time</label>

    <input
        type="text"
        value="<?= !empty($exam['end_datetime']) ? date('d M Y h:i A', strtotime($exam['end_datetime'])) : '-'; ?>"
        readonly>

</div>

<!-- ==========================
     INSTRUCTIONS
========================== -->

<div class="form-group full-width">

    <label>Instructions</label>

    <textarea
        rows="5"
        readonly><?= htmlspecialchars($exam['instructions']); ?></textarea>

</div>

<!-- ==========================
     STATUS
========================== -->

<div class="form-group">

    <label>Status</label>

    <input
        type="text"
        value="<?= htmlspecialchars($exam['status']); ?>"
        readonly>

</div>

<!-- ==========================
     CREATED AT
========================== -->

<div class="form-group">

    <label>Created At</label>

    <input
        type="text"
        value="<?= !empty($exam['created_at']) ? date('d M Y h:i A', strtotime($exam['created_at'])) : '-'; ?>"
        readonly>

</div>

<!-- ==========================
     UPDATED AT
========================== -->

<div class="form-group">

    <label>Updated At</label>

    <input
        type="text"
        value="<?= !empty($exam['updated_at']) ? date('d M Y h:i A', strtotime($exam['updated_at'])) : '-'; ?>"
        readonly>

</div>

</div>

<div class="form-actions">

    <a
        href="edit.php?id=<?= $exam['id']; ?>"
        class="btn-save">

        <i class="fa-solid fa-pen"></i>

        Edit Exam

    </a>

    <a
        href="index.php"
        class="btn-back">

        Back

    </a>

</div>

</div>

</div>

</div>

</div>

<?php include "../includes/footer.php"; ?>