<?php

declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {

    header(
        "Location: ../../auth/login.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function admin_import_escape(
    mixed $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Load exams for optional information
|--------------------------------------------------------------------------
*/

$exams = [];

try {

    $examStatement = $conn->query("
        SELECT

            e.id,
            e.title,
            e.required_question_count,
            e.status,

            s.name AS subject_name

        FROM exams e

        LEFT JOIN subjects s
            ON s.id = e.subject_id

        ORDER BY

            e.created_at DESC,
            e.id DESC
    ");

    $exams =
        $examStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $exception) {

    error_log(
        "Admin import exam loading failed: " .
        $exception->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| Flash messages
|--------------------------------------------------------------------------
*/

$successMessage = '';

$errorMessage = '';


if (
    !empty($_SESSION['success'])
) {

    $successMessage =
        (string)$_SESSION['success'];

    unset(
        $_SESSION['success']
    );
}


if (
    !empty($_SESSION['error'])
) {

    $errorMessage =
        (string)$_SESSION['error'];

    unset(
        $_SESSION['error']
    );
}


$page_title =
    "Bulk Import Questions";


include "../includes/header.php";

?>

<style>

    .import-page {
        max-width: 1450px;
        margin: 0 auto;
    }

    .import-hero {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 20px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .import-kicker {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: #556b2f;
        font-size: .72rem;
        font-weight: 900;
        letter-spacing: .11em;
        text-transform: uppercase;
    }

    .import-hero h1 {
        margin: 7px 0 0;
        color: #5d4037;
        font-weight: 950;
        letter-spacing: -.035em;
    }

    .import-hero p {
        margin: 7px 0 0;
        color: #746d68;
        max-width: 760px;
        line-height: 1.65;
    }

    .import-actions {
        display: flex;
        gap: 9px;
        flex-wrap: wrap;
    }

    .import-action {
        min-height: 44px;
        padding: 0 15px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 850;
    }

    .import-action.primary {
        color: #fff;
        background: #5d4037;
    }

    .import-action.secondary {
        color: #5d4037;
        background: #eee9df;
    }

    .import-flash {
        margin-bottom: 18px;
        padding: 15px 17px;
        border-radius: 14px;
        font-weight: 700;
        line-height: 1.55;
    }

    .import-flash.success {
        color: #486022;
        background: rgba(85,107,47,.10);
        border: 1px solid rgba(85,107,47,.15);
    }

    .import-flash.error {
        color: #783030;
        background: rgba(168,50,50,.08);
        border: 1px solid rgba(168,50,50,.14);
    }

    .import-stat-grid {
        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .import-stat {
        padding: 19px;
        border-radius: 18px;
        background: rgba(255,255,255,.82);
        border: 1px solid rgba(93,64,55,.08);
        box-shadow:
            0 14px 36px rgba(62,45,37,.07);
    }

    .import-stat-label {
        color: #746d68;
        font-size: .77rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .import-stat-number {
        margin-top: 6px;
        color: #5d4037;
        font-size: 1.7rem;
        line-height: 1;
        font-weight: 950;
    }

    .import-layout {
        display: grid;
        grid-template-columns:
            minmax(340px, .95fr)
            minmax(500px, 1.4fr);
        gap: 20px;
        align-items: start;
    }

    .import-card {
        overflow: hidden;
        border-radius: 20px;
        background: rgba(255,255,255,.83);
        border: 1px solid rgba(93,64,55,.08);
        box-shadow:
            0 18px 45px rgba(62,45,37,.08);
    }

    .import-card-header {
        padding: 20px 22px;
        border-bottom: 1px solid #eee7df;
    }

    .import-card-header h2 {
        margin: 0;
        color: #5d4037;
        font-size: 1.05rem;
        font-weight: 900;
    }

    .import-card-header p {
        margin: 5px 0 0;
        color: #746d68;
        font-size: .83rem;
        line-height: 1.55;
    }

    .import-card-body {
        padding: 22px;
    }

    .import-label {
        display: block;
        margin-bottom: 7px;
        color: #5d4037;
        font-size: .82rem;
        font-weight: 850;
    }

    .import-control {
        min-height: 46px;
        border-radius: 11px;
        border-color: #ddd3ca;
    }

    .import-control:focus {
        border-color: #556b2f;
        box-shadow:
            0 0 0 .2rem rgba(85,107,47,.10);
    }

    .upload-zone {
        padding: 24px;
        text-align: center;
        border: 1.5px dashed #cfc1b6;
        border-radius: 16px;
        background: #faf7f0;
        margin-bottom: 18px;
    }

    .upload-icon {
        width: 60px;
        height: 60px;
        margin: 0 auto 12px;
        display: grid;
        place-items: center;
        border-radius: 17px;
        background: rgba(93,64,55,.09);
        color: #5d4037;
        font-size: 24px;
    }

    .upload-zone strong {
        display: block;
        color: #5d4037;
        margin-bottom: 5px;
    }

    .upload-zone span {
        color: #746d68;
        font-size: .82rem;
    }

    .import-submit {
        min-height: 48px;
        width: 100%;
        border: 0;
        border-radius: 12px;
        background: #5d4037;
        color: #fff;
        font-weight: 900;
        box-shadow:
            0 12px 26px rgba(93,64,55,.16);
    }

    .import-submit:hover {
        background: #4f3630;
        color: #fff;
    }

    .rule-grid {
        display: grid;
        gap: 11px;
    }

    .rule {
        display: flex;
        gap: 11px;
        align-items: flex-start;
    }

    .rule-icon {
        flex: 0 0 30px;
        width: 30px;
        height: 30px;
        display: grid;
        place-items: center;
        border-radius: 9px;
        background: rgba(85,107,47,.10);
        color: #556b2f;
        font-size: .76rem;
    }

    .rule-text {
        color: #6e6761;
        font-size: .83rem;
        line-height: 1.55;
    }

    .rule-text strong {
        color: #5d4037;
    }

    .format-box {
        overflow-x: auto;
        padding: 17px;
        border-radius: 14px;
        background: #faf7f0;
        border: 1px solid #ebe1d8;
    }

    .format-box code {
        display: block;
        min-width: 1000px;
        color: #5d4037;
        font-size: .76rem;
        white-space: nowrap;
    }

    .example-table-wrap {
        overflow-x: auto;
        margin-top: 16px;
    }

    .example-table {
        width: 100%;
        min-width: 1000px;
        border-collapse: collapse;
    }

    .example-table th,
    .example-table td {
        padding: 11px 12px;
        border-bottom: 1px solid #eee7df;
        text-align: left;
        font-size: .76rem;
        vertical-align: top;
    }

    .example-table th {
        color: #5d4037;
        background: #faf7f0;
        white-space: nowrap;
        font-weight: 900;
    }

    .example-table td {
        color: #655e59;
    }

    .exam-list {
        margin-top: 18px;
    }

    .exam-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 14px 0;
        border-bottom: 1px solid #eee7df;
    }

    .exam-row:last-child {
        border-bottom: 0;
    }

    .exam-title {
        color: #5d4037;
        font-weight: 850;
    }

    .exam-meta {
        margin-top: 3px;
        color: #746d68;
        font-size: .76rem;
    }

    .exam-count {
        flex: 0 0 auto;
        padding: 6px 9px;
        border-radius: 999px;
        background: #eee9df;
        color: #5d4037;
        font-size: .72rem;
        font-weight: 850;
    }

    .exam-empty {
        padding: 20px 0;
        color: #746d68;
        font-size: .84rem;
    }

    .download-row {
        display: flex;
        gap: 9px;
        flex-wrap: wrap;
        margin-top: 17px;
    }

    .download-btn {
        min-height: 42px;
        padding: 0 14px;
        border-radius: 11px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #5d4037;
        background: #eee9df;
        font-size: .82rem;
        font-weight: 850;
    }

    @media (max-width: 1100px) {

        .import-layout {
            grid-template-columns: 1fr;
        }

    }

    @media (max-width: 850px) {

        .import-stat-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

    }

    @media (max-width: 560px) {

        .import-stat-grid {
            grid-template-columns: 1fr;
        }

    }

</style>


<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content">

            <div class="import-page">

                <header class="import-hero">

                    <div>

                        <span class="import-kicker">

                            <i
                                class="fa-solid fa-database"
                            ></i>

                            Question Management

                        </span>

                        <h1>
                            Bulk Import Questions
                        </h1>

                        <p>
                            Import multiple questions into an examination
                            using the current ExamSphere CSV structure.
                        </p>

                    </div>

                    <div class="import-actions">

                        <a
                            href="index.php"
                            class="import-action secondary"
                        >

                            <i
                                class="fa-solid fa-arrow-left"
                            ></i>

                            Question Bank

                        </a>

                        <a
                            href="sample.csv"
                            class="import-action primary"
                            download
                        >

                            <i
                                class="fa-solid fa-download"
                            ></i>

                            Download Sample CSV

                        </a>

                    </div>

                </header>


                <?php if ($successMessage !== ''): ?>

                    <div class="import-flash success">

                        <i
                            class="fa-solid fa-circle-check me-1"
                        ></i>

                        <?= admin_import_escape(
                            $successMessage
                        ) ?>

                    </div>

                <?php endif; ?>


                <?php if ($errorMessage !== ''): ?>

                    <div class="import-flash error">

                        <i
                            class="fa-solid fa-circle-exclamation me-1"
                        ></i>

                        <?= admin_import_escape(
                            $errorMessage
                        ) ?>

                    </div>

                <?php endif; ?>


                <section class="import-stat-grid">

                    <div class="import-stat">

                        <div class="import-stat-label">
                            Available Exams
                        </div>

                        <div class="import-stat-number">
                            <?= count($exams) ?>
                        </div>

                    </div>


                    <div class="import-stat">

                        <div class="import-stat-label">
                            CSV Columns
                        </div>

                        <div class="import-stat-number">
                            16
                        </div>

                    </div>


                    <div class="import-stat">

                        <div class="import-stat-label">
                            Max File Size
                        </div>

                        <div class="import-stat-number">
                            5 MB
                        </div>

                    </div>


                    <div class="import-stat">

                        <div class="import-stat-label">
                            Question Types
                        </div>

                        <div class="import-stat-number">
                            2
                        </div>

                    </div>

                </section>


                <div class="import-layout">


                    <!-- ==================================================
                         UPLOAD
                    =================================================== -->

                    <section class="import-card">

                        <div class="import-card-header">

                            <h2>
                                Upload Question CSV
                            </h2>

                            <p>
                                Questions are assigned to the exam using
                                <strong>exam_id</strong> from the CSV file.
                            </p>

                        </div>


                        <div class="import-card-body">

                            <form
                                action="process_import.php"
                                method="post"
                                enctype="multipart/form-data"
                            >

                                <?= csrf_field() ?>


                                <div class="upload-zone">

                                    <div class="upload-icon">

                                        <i
                                            class="fa-solid fa-file-csv"
                                        ></i>

                                    </div>

                                    <strong>
                                        Select your CSV file
                                    </strong>

                                    <span>
                                        CSV only · Maximum 5 MB
                                    </span>

                                </div>


                                <div class="mb-3">

                                    <label
                                        class="import-label"
                                        for="import_file"
                                    >

                                        CSV File
                                        <span class="text-danger">
                                            *
                                        </span>

                                    </label>

                                    <input
                                        id="import_file"
                                        type="file"
                                        name="import_file"
                                        class="form-control import-control"
                                        accept=".csv,text/csv"
                                        required
                                    >

                                </div>


                                <button
                                    type="submit"
                                    class="import-submit"
                                >

                                    <i
                                        class="fa-solid fa-file-import me-1"
                                    ></i>

                                    Import Questions

                                </button>

                            </form>


                            <div class="download-row">

                                <a
                                    href="sample.csv"
                                    class="download-btn"
                                    download
                                >

                                    <i
                                        class="fa-solid fa-file-csv"
                                    ></i>

                                    Sample CSV

                                </a>

                                <a
                                    href="export.php"
                                    class="download-btn"
                                >

                                    <i
                                        class="fa-solid fa-file-export"
                                    ></i>

                                    Export Existing Questions

                                </a>

                            </div>

                        </div>

                    </section>


                    <!-- ==================================================
                         RULES
                    =================================================== -->

                    <section class="import-card">

                        <div class="import-card-header">

                            <h2>
                                Import Rules
                            </h2>

                            <p>
                                The importer validates every row before
                                inserting it into the database.
                            </p>

                        </div>


                        <div class="import-card-body">

                            <div class="rule-grid">

                                <div class="rule">

                                    <div class="rule-icon">
                                        1
                                    </div>

                                    <div class="rule-text">

                                        <strong>
                                            Exam ID
                                        </strong>

                                        must point to an existing exam.

                                    </div>

                                </div>


                                <div class="rule">

                                    <div class="rule-icon">
                                        2
                                    </div>

                                    <div class="rule-text">

                                        <strong>
                                            Topic
                                        </strong>

                                        must belong to the same subject
                                        as the selected examination.

                                    </div>

                                </div>


                                <div class="rule">

                                    <div class="rule-icon">
                                        3
                                    </div>

                                    <div class="rule-text">

                                        <strong>
                                            Question type
                                        </strong>

                                        supports MCQ and TrueFalse.

                                    </div>

                                </div>


                                <div class="rule">

                                    <div class="rule-icon">
                                        4
                                    </div>

                                    <div class="rule-text">

                                        <strong>
                                            Active questions
                                        </strong>

                                        count toward the examination's
                                        configured question limit.

                                    </div>

                                </div>


                                <div class="rule">

                                    <div class="rule-icon">
                                        5
                                    </div>

                                    <div class="rule-text">

                                        <strong>
                                            Position
                                        </strong>

                                        must be unique inside each exam
                                        and cannot exceed its question limit.

                                    </div>

                                </div>


                                <div class="rule">

                                    <div class="rule-icon">
                                        6
                                    </div>

                                    <div class="rule-text">

                                        <strong>
                                            Duplicate questions
                                        </strong>

                                        are rejected both against the
                                        database and within the CSV.

                                    </div>

                                </div>


                                <div class="rule">

                                    <div class="rule-icon">
                                        7
                                    </div>

                                    <div class="rule-text">

                                        <strong>
                                            Failed import
                                        </strong>

                                        rolls back database changes.

                                    </div>

                                </div>

                            </div>

                        </div>

                    </section>


                    <!-- ==================================================
                         FORMAT
                    =================================================== -->

                    <section class="import-card">

                        <div class="import-card-header">

                            <h2>
                                Official CSV Format
                            </h2>

                            <p>
                                Column names and order must match exactly.
                            </p>

                        </div>


                        <div class="import-card-body">

                            <div class="format-box">

                                <code>
exam_id,topic_id,question_text,question_type,option_a,option_b,option_c,option_d,correct_answer,explanation,difficulty,marks,negative_marks,estimated_time_seconds,status,position
                                </code>

                            </div>


                            <div class="example-table-wrap">

                                <table class="example-table">

                                    <thead>

                                        <tr>

                                            <th>
                                                exam_id
                                            </th>

                                            <th>
                                                topic_id
                                            </th>

                                            <th>
                                                question_text
                                            </th>

                                            <th>
                                                question_type
                                            </th>

                                            <th>
                                                option_a
                                            </th>

                                            <th>
                                                option_b
                                            </th>

                                            <th>
                                                option_c
                                            </th>

                                            <th>
                                                option_d
                                            </th>

                                            <th>
                                                correct_answer
                                            </th>

                                            <th>
                                                explanation
                                            </th>

                                            <th>
                                                difficulty
                                            </th>

                                            <th>
                                                marks
                                            </th>

                                            <th>
                                                negative_marks
                                            </th>

                                            <th>
                                                estimated_time_seconds
                                            </th>

                                            <th>
                                                status
                                            </th>

                                            <th>
                                                position
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                        <tr>

                                            <td>
                                                1
                                            </td>

                                            <td>
                                                5
                                            </td>

                                            <td>
                                                What is PHP?
                                            </td>

                                            <td>
                                                MCQ
                                            </td>

                                            <td>
                                                Programming Language
                                            </td>

                                            <td>
                                                Database
                                            </td>

                                            <td>
                                                Browser
                                            </td>

                                            <td>
                                                Operating System
                                            </td>

                                            <td>
                                                A
                                            </td>

                                            <td>
                                                PHP is a server-side scripting language.
                                            </td>

                                            <td>
                                                Easy
                                            </td>

                                            <td>
                                                1
                                            </td>

                                            <td>
                                                0.25
                                            </td>

                                            <td>
                                                45
                                            </td>

                                            <td>
                                                Active
                                            </td>

                                            <td>
                                                1
                                            </td>

                                        </tr>

                                        <tr>

                                            <td>
                                                1
                                            </td>

                                            <td>
                                                5
                                            </td>

                                            <td>
                                                PHP is a server-side language.
                                            </td>

                                            <td>
                                                TrueFalse
                                            </td>

                                            <td>
                                                True
                                            </td>

                                            <td>
                                                False
                                            </td>

                                            <td>
                                            </td>

                                            <td>
                                            </td>

                                            <td>
                                                A
                                            </td>

                                            <td>
                                                PHP can execute on the server.
                                            </td>

                                            <td>
                                                Easy
                                            </td>

                                            <td>
                                                1
                                            </td>

                                            <td>
                                                0
                                            </td>

                                            <td>
                                                30
                                            </td>

                                            <td>
                                                Active
                                            </td>

                                            <td>
                                                2
                                            </td>

                                        </tr>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </section>


                    <!-- ==================================================
                         AVAILABLE EXAMS
                    =================================================== -->

                    <section class="import-card">

                        <div class="import-card-header">

                            <h2>
                                Available Exams
                            </h2>

                            <p>
                                Use these IDs in the CSV's
                                <strong>exam_id</strong> column.
                            </p>

                        </div>


                        <div class="import-card-body">

                            <?php if (!$exams): ?>

                                <div class="exam-empty">

                                    <i
                                        class="fa-solid fa-circle-info me-1"
                                    ></i>

                                    No exams are currently available.

                                </div>

                            <?php else: ?>

                                <div class="exam-list">

                                    <?php foreach (
                                        $exams
                                        as $exam
                                    ): ?>

                                        <div class="exam-row">

                                            <div>

                                                <div class="exam-title">

                                                    #<?= (int)$exam['id'] ?>

                                                    ·

                                                    <?= admin_import_escape(
                                                        $exam['title']
                                                    ) ?>

                                                </div>

                                                <div class="exam-meta">

                                                    <?= admin_import_escape(
                                                        $exam['subject_name']
                                                        ?: 'No Subject'
                                                    ) ?>

                                                    ·

                                                    Status:
                                                    <?= admin_import_escape(
                                                        $exam['status']
                                                    ) ?>

                                                </div>

                                            </div>

                                            <div class="exam-count">

                                                Max
                                                <?= (int)(
                                                    $exam[
                                                        'required_question_count'
                                                    ] ?? 0
                                                ) ?>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

                        </div>

                    </section>

                </div>

            </div>

        </main>

    </div>

</div>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const input =
            document.getElementById(
                'import_file'
            );

        const uploadZone =
            document.querySelector(
                '.upload-zone'
            );

        if (
            !input ||
            !uploadZone
        ) {
            return;
        }

        input.addEventListener(
            'change',
            function () {

                const file =
                    this.files &&
                    this.files.length
                        ? this.files[0]
                        : null;

                if (!file) {
                    return;
                }

                if (
                    file.size >
                    5 * 1024 * 1024
                ) {

                    alert(
                        'Maximum CSV file size is 5 MB.'
                    );

                    this.value = '';

                    return;
                }

                if (
                    !file.name
                        .toLowerCase()
                        .endsWith('.csv')
                ) {

                    alert(
                        'Please select a CSV file.'
                    );

                    this.value = '';

                    return;
                }

                const fileName =
                    uploadZone.querySelector(
                        'strong'
                    );

                const fileInfo =
                    uploadZone.querySelector(
                        'span'
                    );

                if (fileName) {

                    fileName.textContent =
                        file.name;
                }

                if (fileInfo) {

                    fileInfo.textContent =
                        (
                            file.size /
                            1024
                        ).toFixed(1) +
                        ' KB selected';
                }
            }
        );
    }
);

</script>

<?php include "../includes/footer.php"; ?>