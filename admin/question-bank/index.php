<?php

require_once "../../config/session.php";
require_once "../../config/config.php";

$page_title = "Question Bank";

$search = "";

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

/* ==========================================
   TOTAL EXAMS
========================================== */

$totalExamStmt = $conn->query("
SELECT COUNT(*)
FROM exams
");

$totalExams = $totalExamStmt->fetchColumn();

/* ==========================================
   TOTAL QUESTIONS
========================================== */

$totalQuestionStmt = $conn->query("
SELECT COUNT(*)
FROM questions
");

$totalQuestions = $totalQuestionStmt->fetchColumn();

/* ==========================================
   EXAM LIST WITH QUESTION COUNT
========================================== */

$sql = "

SELECT

e.id,

e.title,

e.required_question_count,

e.status,

s.name AS subject_name,

COUNT(DISTINCT eq.question_id) AS added_questions

FROM exams e

LEFT JOIN exam_questions eq
ON eq.exam_id = e.id

LEFT JOIN subjects s
ON s.id = e.subject_id

WHERE

e.title LIKE :search

GROUP BY

e.id

ORDER BY

e.id DESC

";

$stmt = $conn->prepare($sql);

$stmt->execute([

":search"=>"%".$search."%"

]);

$examCards = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

Question Bank

</h1>

<p>

Manage all examination questions.

</p>

</div>

<a
href="add.php"
class="btn-add">

<i class="fa-solid fa-plus"></i>

Add Question

</a>

</div>

<!-- ===============================
     STATS CARD
================================ -->

<div class="stats-card">

<h3>

Total Questions

</h3>

<h2>

<?= $totalQuestions; ?>

</h2>

<p>

Manage all questions.

</p>

</div>

<!-- ===============================
     EXAM CARDS
================================ -->

<div class="table-card">

    <div class="table-header">

        <h2>Exam Question Bank</h2>

        <form method="GET">

            <input
                type="text"
                name="search"
                placeholder="Search Exam..."
                value="<?= htmlspecialchars($search); ?>">

        </form>

    </div>

    <div class="exam-grid">

<?php

if(!empty($examCards)){

foreach($examCards as $exam){

$added = (int)$exam['added_questions'];

$total = (int)$exam['required_question_count'];

$remaining = max(0,$total-$added);

$progress = ($total>0)
? round(($added/$total)*100)
:0;

?>

<div class="exam-card">

<div class="exam-top">

<h3>

<?= htmlspecialchars($exam['title']); ?>

</h3>

<span class="status">

<?= htmlspecialchars($exam['status']); ?>

</span>

</div>

<p>

<b>Subject :</b>

<?= htmlspecialchars($exam['subject_name']); ?>

</p>

<p>

<b>Total Questions :</b>

<?= $total; ?>

</p>

<p>

<b>Added :</b>

<?= $added; ?>

</p>

<p>

<b>Remaining :</b>

<?= $remaining; ?>

</p>

<div class="progress">

<div
class="progress-bar"
style="width:<?= $progress; ?>%;">

</div>

</div>

<div class="exam-actions">

<a
href="questions.php?exam_id=<?= $exam['id']; ?>"
class="btn-view">

<i class="fa-solid fa-eye"></i>

Open

</a>

</div>

</div>

<?php

}

}else{

?>

<div
style="
padding:40px;
text-align:center;
width:100%;
">

No Exams Found.

</div>

<?php

}

?>

</div>

</div>

</div>

</div>

</div>

<?php include "../includes/footer.php"; ?>