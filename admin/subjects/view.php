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

$page_title = 'View Subject';

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null || $id <= 0) {
    $_SESSION['error'] = 'Invalid subject.';
    header('Location: index.php');
    exit;
}

try {

    $statement = $conn->prepare("
        SELECT
            s.id,
            s.category_id,
            s.name,
            s.code,
            s.description,
            s.status,
            s.created_at,

            c.category_name,

            (
                SELECT COUNT(*)
                FROM questions q
                WHERE q.subject_id = s.id
            ) AS question_count,

            (
                SELECT COUNT(*)
                FROM exams e
                WHERE e.subject_id = s.id
            ) AS exam_count

        FROM subjects s

        LEFT JOIN categories c
            ON c.id = s.category_id

        WHERE s.id = ?

        LIMIT 1
    ");

    $statement->execute([$id]);

    $subject = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$subject) {
        $_SESSION['error'] = 'Subject not found.';
        header('Location: index.php');
        exit;
    }

} catch (Throwable $exception) {

    error_log(
        'View subject failed: ' .
        $exception->getMessage()
    );

    $_SESSION['error'] =
        'Unable to load subject details.';

    header('Location: index.php');
    exit;
}

function subject_view_e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function subject_view_datetime(?string $value): string
{
    if (!$value) {
        return 'Not available';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d M Y, h:i A', $timestamp)
        : 'Not available';
}

$status = (string)$subject['status'];

$statusClass = match ($status) {
    'Active' => 'active',
    'Inactive' => 'inactive',
    default => 'unknown'
};

$questionCount = (int)$subject['question_count'];
$examCount = (int)$subject['exam_count'];

include "../includes/header.php";
?>

<style>

    .subject-view-page {
        max-width: 1180px;
        margin: 0 auto;
    }

    .subject-view-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
    }

    .subject-view-heading .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #556b2f;
        font-size: .76rem;
        font-weight: 800;
        letter-spacing: .12em;
        margin-bottom: 8px;
    }

    .subject-view-heading h1 {
        margin: 0;
        color: #333;
    }

    .subject-view-heading p {
        margin: 6px 0 0;
        color: #746d68;
    }

    .subject-view-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .subject-view-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 42px;
        padding: 0 15px;
        border-radius: 12px;
        text-decoration: none;
        font-size: .83rem;
        font-weight: 800;
        transition: .2s ease;
        white-space: nowrap;
    }

    .subject-view-action.edit {
        background: #556b2f;
        color: #fff;
        box-shadow: 0 10px 24px rgba(85,107,47,.17);
    }

    .subject-view-action.edit:hover {
        background: #465b27;
        color: #fff;
        transform: translateY(-2px);
    }

    .subject-view-action.back {
        background: rgba(255,255,255,.88);
        border: 1px solid rgba(93,64,55,.12);
        color: #5d4037;
    }

    .subject-view-action.back:hover {
        background: #5d4037;
        border-color: #5d4037;
        color: #fff;
        transform: translateY(-2px);
    }

    .subject-overview {
        display: grid;
        grid-template-columns: 330px minmax(0,1fr);
        gap: 20px;
        margin-bottom: 20px;
    }

    .subject-identity-card,
    .subject-details-card {
        border-radius: 24px;
        border: 1px solid rgba(93,64,55,.10);
        background: rgba(255,255,255,.89);
        box-shadow: 0 20px 55px rgba(51,51,51,.08);
        backdrop-filter: blur(15px);
    }

    .subject-identity-card {
        padding: 28px 23px;
        text-align: center;
    }

    .subject-icon {
        width: 92px;
        height: 92px;
        margin: 0 auto 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 24px;
        background: linear-gradient(
            135deg,
            #5d4037,
            #806154
        );
        color: #fff;
        font-size: 2rem;
        box-shadow: 0 15px 35px rgba(93,64,55,.20);
    }

    .subject-identity-card h2 {
        margin: 0;
        color: #333;
        font-size: 1.42rem;
        line-height: 1.3;
    }

    .subject-code {
        display: inline-flex;
        margin-top: 9px;
        padding: 6px 11px;
        border-radius: 999px;
        background: rgba(93,64,55,.08);
        color: #5d4037;
        font-size: .76rem;
        font-weight: 800;
    }

    .subject-category {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 11px;
        padding: 7px 11px;
        border-radius: 999px;
        background: rgba(85,107,47,.10);
        color: #556b2f;
        font-size: .75rem;
        font-weight: 800;
    }

    .subject-status {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-top: 15px;
        padding: 7px 11px;
        border-radius: 999px;
        font-size: .75rem;
        font-weight: 800;
    }

    .subject-status.active {
        color: #556b2f;
        background: rgba(85,107,47,.11);
    }

    .subject-status.inactive {
        color: #96352f;
        background: rgba(163,58,50,.10);
    }

    .subject-status.unknown {
        color: #6e665f;
        background: rgba(93,64,55,.08);
    }

    .subject-created {
        margin-top: 18px;
        color: #77706b;
        font-size: .80rem;
    }

    .subject-details-card {
        overflow: hidden;
    }

    .subject-card-head {
        padding: 21px 23px;
        border-bottom: 1px solid rgba(93,64,55,.08);
        background: rgba(250,247,240,.65);
    }

    .subject-card-head span {
        display: block;
        margin-bottom: 4px;
        color: #556b2f;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .10em;
    }

    .subject-card-head h3 {
        margin: 0;
        color: #333;
        font-size: 1.06rem;
    }

    .subject-details-content {
        padding: 22px;
    }

    .subject-description {
        padding: 17px;
        min-height: 130px;
        border-radius: 16px;
        background: #faf8f4;
        border: 1px solid rgba(93,64,55,.08);
        color: #4c4743;
        line-height: 1.7;
    }

    .subject-description.empty {
        color: #827a75;
        font-style: italic;
    }

    .subject-metric-grid {
        display: grid;
        grid-template-columns: repeat(2,minmax(0,1fr));
        gap: 15px;
        margin-top: 18px;
    }

    .subject-metric {
        display: flex;
        align-items: center;
        gap: 13px;
        padding: 16px;
        border-radius: 17px;
        border: 1px solid rgba(93,64,55,.08);
        background: rgba(250,247,240,.72);
    }

    .subject-metric-icon {
        width: 43px;
        height: 43px;
        flex: 0 0 43px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: rgba(85,107,47,.10);
        color: #556b2f;
    }

    .subject-metric small {
        display: block;
        color: #7a726d;
        margin-bottom: 3px;
    }

    .subject-metric strong {
        display: block;
        color: #333;
        font-size: 1.15rem;
    }

    .subject-meta-card {
        overflow: hidden;
        border-radius: 24px;
        border: 1px solid rgba(93,64,55,.10);
        background: rgba(255,255,255,.89);
        box-shadow: 0 20px 55px rgba(51,51,51,.08);
        backdrop-filter: blur(15px);
    }

    .subject-meta-grid {
        display: grid;
        grid-template-columns: repeat(4,minmax(0,1fr));
    }

    .subject-meta-item {
        padding: 19px 22px;
        border-right: 1px solid rgba(93,64,55,.07);
    }

    .subject-meta-item:last-child {
        border-right: 0;
    }

    .subject-meta-item small {
        display: block;
        margin-bottom: 5px;
        color: #7c746f;
        font-size: .72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .subject-meta-item strong {
        display: block;
        color: #383330;
        word-break: break-word;
    }

    .subject-footer-note {
        margin-top: 20px;
        padding: 15px 17px;
        border-radius: 15px;
        background: rgba(85,107,47,.07);
        border: 1px solid rgba(85,107,47,.11);
        color: #556b2f;
        font-size: .83rem;
        line-height: 1.55;
    }

    @media (max-width: 900px) {

        .subject-overview {
            grid-template-columns: 1fr;
        }

        .subject-identity-card {
            text-align: left;
        }

        .subject-icon {
            margin-left: 0;
        }

        .subject-meta-grid {
            grid-template-columns: repeat(2,minmax(0,1fr));
        }

        .subject-meta-item:nth-child(2) {
            border-right: 0;
        }

    }

    @media (max-width: 650px) {

        .subject-view-heading {
            align-items: flex-start;
            flex-direction: column;
        }

        .subject-view-actions {
            width: 100%;
        }

        .subject-view-action {
            flex: 1;
        }

        .subject-metric-grid,
        .subject-meta-grid {
            grid-template-columns: 1fr;
        }

        .subject-meta-item,
        .subject-meta-item:nth-child(2) {
            border-right: 0;
            border-bottom: 1px solid rgba(93,64,55,.07);
        }

        .subject-meta-item:last-child {
            border-bottom: 0;
        }

    }

</style>

<div class="dashboard-wrapper">

    <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "../includes/navbar.php"; ?>

        <main class="dashboard-content subject-view-page">

            <section class="subject-view-heading">

                <div>

                    <span class="eyebrow">
                        <i class="fa-solid fa-book-open"></i>
                        SUBJECT DETAILS
                    </span>

                    <h1>
                        <?= subject_view_e(
                            $subject['name']
                        ) ?>
                    </h1>

                    <p>
                        Complete subject information and linked academic
                        content.
                    </p>

                </div>

                <div class="subject-view-actions">

                    <a
                        href="edit.php?id=<?= (int)$subject['id'] ?>"
                        class="subject-view-action edit"
                    >
                        <i class="fa-solid fa-pen"></i>
                        Edit
                    </a>

                    <a
                        href="index.php"
                        class="subject-view-action back"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                        Back
                    </a>

                </div>

            </section>

            <section class="subject-overview">

                <aside class="subject-identity-card">

                    <div class="subject-icon">
                        <i class="fa-solid fa-book-open-reader"></i>
                    </div>

                    <h2>
                        <?= subject_view_e(
                            $subject['name']
                        ) ?>
                    </h2>

                    <div class="subject-code">

                        <i class="fa-solid fa-hashtag"></i>

                        <?= subject_view_e(
                            $subject['code'] ?: 'No code'
                        ) ?>

                    </div>

                    <?php if (
                        !empty($subject['category_name'])
                    ): ?>

                        <div class="subject-category">

                            <i class="fa-solid fa-layer-group"></i>

                            <?= subject_view_e(
                                $subject['category_name']
                            ) ?>

                        </div>

                    <?php else: ?>

                        <div class="subject-category">

                            <i class="fa-regular fa-folder-open"></i>

                            No category

                        </div>

                    <?php endif; ?>

                    <div class="subject-status <?= subject_view_e(
                        $statusClass
                    ) ?>">

                        <i class="fa-solid fa-circle"></i>

                        <?= subject_view_e($status) ?>

                    </div>

                    <div class="subject-created">

                        <i class="fa-regular fa-calendar me-1"></i>

                        Created
                        <?= subject_view_e(
                            subject_view_datetime(
                                $subject['created_at']
                            )
                        ) ?>

                    </div>

                </aside>

                <section class="subject-details-card">

                    <header class="subject-card-head">

                        <span>ACADEMIC INFORMATION</span>

                        <h3>
                            Subject Overview
                        </h3>

                    </header>

                    <div class="subject-details-content">

                        <div>

                            <small
                                class="d-block text-uppercase fw-bold mb-2"
                                style="font-size:.72rem;letter-spacing:.06em;color:#7c746f;"
                            >
                                Description
                            </small>

                            <?php if (
                                trim(
                                    (string)$subject['description']
                                ) !== ''
                            ): ?>

                                <div class="subject-description">

                                    <?= nl2br(
                                        subject_view_e(
                                            $subject['description']
                                        )
                                    ) ?>

                                </div>

                            <?php else: ?>

                                <div class="subject-description empty">

                                    No description has been added for this
                                    subject.

                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="subject-metric-grid">

                            <div class="subject-metric">

                                <div class="subject-metric-icon">
                                    <i class="fa-solid fa-circle-question"></i>
                                </div>

                                <div>

                                    <small>
                                        Linked Questions
                                    </small>

                                    <strong>
                                        <?= $questionCount ?>
                                    </strong>

                                </div>

                            </div>

                            <div class="subject-metric">

                                <div class="subject-metric-icon">
                                    <i class="fa-solid fa-file-lines"></i>
                                </div>

                                <div>

                                    <small>
                                        Linked Exams
                                    </small>

                                    <strong>
                                        <?= $examCount ?>
                                    </strong>

                                </div>

                            </div>

                        </div>

                    </div>

                </section>

            </section>

            <section class="subject-meta-card">

                <header class="subject-card-head">

                    <span>RECORD INFORMATION</span>

                    <h3>
                        Database Record
                    </h3>

                </header>

                <div class="subject-meta-grid">

                    <div class="subject-meta-item">

                        <small>
                            Subject ID
                        </small>

                        <strong>
                            #<?= (int)$subject['id'] ?>
                        </strong>

                    </div>

                    <div class="subject-meta-item">

                        <small>
                            Category ID
                        </small>

                        <strong>
                            <?php if (
                                !empty($subject['category_id'])
                            ): ?>

                                #<?= (int)$subject['category_id'] ?>

                            <?php else: ?>

                                Not assigned

                            <?php endif; ?>
                        </strong>

                    </div>

                    <div class="subject-meta-item">

                        <small>
                            Subject Status
                        </small>

                        <strong>
                            <?= subject_view_e($status) ?>
                        </strong>

                    </div>

                    <div class="subject-meta-item">

                        <small>
                            Created
                        </small>

                        <strong>
                            <?= subject_view_e(
                                subject_view_datetime(
                                    $subject['created_at']
                                )
                            ) ?>
                        </strong>

                    </div>

                </div>

            </section>

            <div class="subject-footer-note">

                <i class="fa-solid fa-circle-info me-2"></i>

                This subject currently has
                <strong><?= $questionCount ?></strong>
                linked question<?= $questionCount === 1 ? '' : 's' ?>
                and
                <strong><?= $examCount ?></strong>
                linked exam<?= $examCount === 1 ? '' : 's' ?>.

                Existing linked content remains associated with this subject
                when its profile information is edited.

            </div>

        </main>

    </div>

</div>

<?php include "../includes/footer.php"; ?>