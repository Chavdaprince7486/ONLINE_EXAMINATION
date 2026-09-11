<?php
declare(strict_types=1);

require_once "../../config/session.php";
require_once "../../config/config.php";

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: ../../auth/login.php');
    exit;
}

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $id === false ||
    $id === null ||
    $id <= 0
) {
    $_SESSION['error'] = 'Invalid topic.';
    header('Location: index.php');
    exit;
}

$page_title = 'View Topic';

try {

    $statement = $conn->prepare("
        SELECT

            t.id,
            t.subject_id,
            t.name,
            t.description,
            t.status,
            t.created_at,
            t.updated_at,

            s.name AS subject_name,
            s.status AS subject_status,

            (
                SELECT COUNT(*)
                FROM questions q
                WHERE q.topic_id = t.id
            ) AS question_count

        FROM topics t

        INNER JOIN subjects s
            ON s.id = t.subject_id

        WHERE
            t.id = ?

        LIMIT 1
    ");

    $statement->execute([
        $id
    ]);

    $topic = $statement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$topic) {

        $_SESSION['error'] =
            'Topic not found.';

        header('Location: index.php');
        exit;
    }

} catch (Throwable $exception) {

    error_log(
        'View topic failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to load topic.';

    header('Location: index.php');
    exit;
}

function topic_view_e(
    mixed $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

include "../includes/header.php";
?>

<style>

    .topic-view-page {
        max-width: 1000px;
        margin: 0 auto;
    }

    .topic-view-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .topic-view-header h1 {
        margin: 0;
        color: #5d4037;
        font-weight: 900;
    }

    .topic-view-actions {
        display: flex;
        gap: 9px;
        flex-wrap: wrap;
    }

    .topic-view-btn {
        min-height: 43px;
        padding: 0 15px;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        font-weight: 800;
    }

    .topic-view-btn.edit {
        background: #5d4037;
        color: #fff;
    }

    .topic-view-btn.back {
        background: #eee9df;
        color: #5d4037;
    }

    .topic-view-card {
        padding: 30px;
        border-radius: 22px;
        background: rgba(255,255,255,.82);
        border: 1px solid rgba(93,64,55,.08);
        box-shadow: 0 20px 50px rgba(62,45,37,.09);
    }

    .topic-view-grid {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .topic-view-item {
        padding: 18px;
        border-radius: 15px;
        background: #faf7f0;
    }

    .topic-view-label {
        color: #746d68;
        font-size: .78rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-bottom: 6px;
    }

    .topic-view-value {
        color: #5d4037;
        font-size: 1.05rem;
        font-weight: 800;
    }

    .topic-view-full {
        grid-column: 1 / -1;
    }

    .topic-view-description {
        line-height: 1.75;
        white-space: pre-wrap;
        color: #4a4542;
    }

    .topic-status {
        display: inline-flex;
        padding: 6px 11px;
        border-radius: 999px;
        font-size: .78rem;
        font-weight: 850;
    }

    .topic-status.active {
        background: rgba(85,107,47,.12);
        color: #556b2f;
    }

    .topic-status.inactive {
        background: rgba(168,50,50,.10);
        color: #a83232;
    }

    @media (max-width: 650px) {

        .topic-view-grid {
            grid-template-columns: 1fr;
        }

        .topic-view-full {
            grid-column: auto;
        }

    }

</style>

<div class="topic-view-page">

    <div class="topic-view-header">

        <h1>
            <?= topic_view_e($topic['name']) ?>
        </h1>

        <div class="topic-view-actions">

            <a
                href="index.php"
                class="topic-view-btn back"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Back
            </a>

            <a
                href="edit.php?id=<?= (int)$topic['id'] ?>"
                class="topic-view-btn edit"
            >
                <i class="fa-solid fa-pen"></i>
                Edit
            </a>

        </div>

    </div>

    <?php if (!empty($_SESSION['success'])): ?>

        <div class="alert alert-success">
            <?= topic_view_e($_SESSION['success']) ?>
        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>

    <div class="topic-view-card">

        <div class="topic-view-grid">

            <div class="topic-view-item">

                <div class="topic-view-label">
                    Subject
                </div>

                <div class="topic-view-value">
                    <?= topic_view_e(
                        $topic['subject_name']
                    ) ?>
                </div>

            </div>

            <div class="topic-view-item">

                <div class="topic-view-label">
                    Status
                </div>

                <div class="topic-view-value">

                    <span
                        class="topic-status <?= strtolower(
                            (string)$topic['status']
                        ) ?>"
                    >
                        <?= topic_view_e(
                            $topic['status']
                        ) ?>
                    </span>

                </div>

            </div>

            <div class="topic-view-item">

                <div class="topic-view-label">
                    Linked Questions
                </div>

                <div class="topic-view-value">
                    <?= (int)$topic['question_count'] ?>
                </div>

            </div>

            <div class="topic-view-item">

                <div class="topic-view-label">
                    Subject Status
                </div>

                <div class="topic-view-value">
                    <?= topic_view_e(
                        $topic['subject_status']
                    ) ?>
                </div>

            </div>

            <div class="topic-view-item">

                <div class="topic-view-label">
                    Created At
                </div>

                <div class="topic-view-value">
                    <?= topic_view_e(
                        $topic['created_at']
                    ) ?>
                </div>

            </div>

            <div class="topic-view-item">

                <div class="topic-view-label">
                    Updated At
                </div>

                <div class="topic-view-value">
                    <?= topic_view_e(
                        $topic['updated_at']
                    ) ?>
                </div>

            </div>

            <div class="topic-view-item topic-view-full">

                <div class="topic-view-label">
                    Description
                </div>

                <div class="topic-view-description">

                    <?php
                    $description = trim(
                        (string)(
                            $topic['description'] ?? ''
                        )
                    );
                    ?>

                    <?= $description !== ''
                        ? topic_view_e($description)
                        : 'No description added.' ?>

                </div>

            </div>

        </div>

    </div>

</div>

<?php include "../includes/footer.php"; ?>