<?php
declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    header('Location: ../auth/login.php');
    exit;
}

$studentId = (int) $_SESSION['user_id'];

function profile_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function profile_date(?string $value, string $format = 'd M Y'): string
{
    if (!$value) {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format($format);
    } catch (Throwable) {
        return '—';
    }
}

$student = null;
$stats = [
    'completed' => 0,
    'average_score' => 0,
    'best_score' => 0,
    'correct_answers' => 0,
    'wrong_answers' => 0,
    'attempted_questions' => 0,
];
$pageError = '';

try {
    $studentStatement = $conn->prepare(
        "SELECT
            id,
            student_code,
            full_name,
            email,
            mobile,
            gender,
            dob,
            address,
            city,
            state,
            pincode,
            profile_photo,
            email_verified,
            status,
            created_at,
            last_login
         FROM students
         WHERE id = ?
           AND status = 'Active'
         LIMIT 1"
    );
    $studentStatement->execute([$studentId]);
    $student = $studentStatement->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
        }
        session_destroy();
        header('Location: ../auth/login.php');
        exit;
    }

    $statsStatement = $conn->prepare(
        "SELECT
            COUNT(*) AS completed,
            COALESCE(AVG(percentage), 0) AS average_score,
            COALESCE(MAX(percentage), 0) AS best_score,
            COALESCE(SUM(correct_answers), 0) AS correct_answers,
            COALESCE(SUM(wrong_answers), 0) AS wrong_answers,
            COALESCE(SUM(attempted_questions), 0) AS attempted_questions
         FROM results
         WHERE student_id = ?"
    );
    $statsStatement->execute([$studentId]);
    $loadedStats = $statsStatement->fetch(PDO::FETCH_ASSOC);
    if (is_array($loadedStats)) {
        $stats = array_merge($stats, $loadedStats);
    }
} catch (Throwable $exception) {
    error_log('Student profile load failed: ' . $exception->getMessage());
    $pageError = 'Your profile could not be loaded right now. Please try again.';
}

$photo = '../assets/images/default-user.png';
if (
    is_array($student) &&
    !empty($student['profile_photo'])
) {
    $photoName = basename((string) $student['profile_photo']);
    $photoPath = __DIR__ . '/../uploads/students/' . $photoName;
    if ($photoName !== '' && is_file($photoPath)) {
        $photo = '../uploads/students/' . rawurlencode($photoName);
    }
}

$csrf = csrf_token();
$completedExams = (int) ($stats['completed'] ?? 0);
$averageScore = (float) ($stats['average_score'] ?? 0);
$bestScore = (float) ($stats['best_score'] ?? 0);
$attemptedQuestions = (int) ($stats['attempted_questions'] ?? 0);
$correctAnswers = (int) ($stats['correct_answers'] ?? 0);
$accuracy = $attemptedQuestions > 0
    ? round(($correctAnswers / $attemptedQuestions) * 100, 2)
    : 0;

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#f5f5dc">
    <meta name="csrf-token" content="<?= profile_escape($csrf) ?>">
    <title>My Profile | ExamSphere</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">

    <style>
        :root{
            --cream:#f5f5dc;
            --cream-soft:#faf8ef;
            --brown:#5d4037;
            --brown-dark:#3e2723;
            --olive:#556b2f;
            --olive-soft:#e9eedf;
            --gold:#b99b63;
            --muted:#7a716b;
            --line:rgba(93,64,55,.12);
            --white:#fff;
            --shadow:0 18px 50px rgba(62,39,35,.09);
        }

        *{box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{
            margin:0;
            min-height:100vh;
            font-family:'Poppins',sans-serif;
            color:#333;
            background:
                radial-gradient(circle at 6% 12%, rgba(85,107,47,.075), transparent 23%),
                radial-gradient(circle at 94% 17%, rgba(93,64,55,.06), transparent 25%),
                linear-gradient(180deg,#f8f7e9 0%,#f5f5dc 100%);
        }

        .profile-page{
            width:min(1500px,calc(100% - 36px));
            margin:0 auto;
            padding:28px 0 60px;
        }

        .profile-hero{
            display:grid;
            grid-template-columns:minmax(0,1fr) auto;
            align-items:end;
            gap:28px;
            margin-bottom:24px;
        }

        .profile-kicker{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 13px;
            border-radius:999px;
            background:rgba(85,107,47,.10);
            color:var(--olive);
            font-size:11px;
            font-weight:800;
            letter-spacing:.13em;
            text-transform:uppercase;
        }

        .profile-hero h1{
            margin:12px 0 8px;
            color:var(--brown-dark);
            font-size:clamp(34px,4vw,54px);
            line-height:1.03;
            letter-spacing:-.045em;
        }

        .profile-hero h1 em{
            color:var(--olive);
            font-style:normal;
        }

        .profile-hero p{
            max-width:780px;
            margin:0;
            color:var(--muted);
            font-size:14px;
            line-height:1.8;
        }

        .profile-back{
            display:inline-flex;
            align-items:center;
            gap:9px;
            min-height:48px;
            padding:0 17px;
            border-radius:15px;
            color:var(--brown-dark);
            background:rgba(255,255,255,.84);
            border:1px solid var(--line);
            box-shadow:0 10px 28px rgba(62,39,35,.06);
            text-decoration:none;
            font-weight:700;
        }

        .profile-shell{
            display:grid;
            grid-template-columns:minmax(0,1.65fr) minmax(300px,.95fr);
            gap:18px;
            align-items:start;
        }

        .profile-card{
            background:rgba(255,255,255,.88);
            border:1px solid var(--line);
            border-radius:26px;
            box-shadow:var(--shadow);
            overflow:hidden;
            backdrop-filter:blur(14px);
        }

        .profile-main-card{min-height:100%}

        .profile-cover{
            height:124px;
            position:relative;
            background:
                radial-gradient(circle at 86% 22%, rgba(255,255,255,.36) 0 90px, transparent 91px),
                radial-gradient(circle at 4% 95%, rgba(85,107,47,.12) 0 90px, transparent 91px),
                linear-gradient(135deg,#6a493f 0%,#5d4037 45%,#556b2f 100%);
        }

        .profile-cover:after{
            content:"";
            position:absolute;
            inset:0;
            opacity:.22;
            background-image:radial-gradient(rgba(255,255,255,.45) 1px,transparent 1px);
            background-size:15px 15px;
        }

        .profile-main{
            display:flex;
            align-items:flex-end;
            gap:22px;
            padding:0 28px 26px;
            margin-top:-54px;
            position:relative;
            z-index:2;
        }

        .avatar-wrap{
            position:relative;
            flex:0 0 auto;
        }

        .avatar-wrap img{
            width:126px;
            height:126px;
            object-fit:cover;
            border-radius:50%;
            border:6px solid #fff;
            background:#f0ece2;
            box-shadow:0 16px 30px rgba(62,39,35,.18);
        }

        .avatar-upload{
            position:absolute;
            right:7px;
            bottom:7px;
            width:38px;
            height:38px;
            display:grid;
            place-items:center;
            border-radius:50%;
            color:#fff;
            background:var(--brown);
            border:3px solid #fff;
            cursor:pointer;
            box-shadow:0 8px 18px rgba(62,39,35,.18);
        }

        .profile-identity{padding-bottom:5px;min-width:0}
        .profile-identity h2{
            margin:0;
            color:var(--brown-dark);
            font-size:30px;
            line-height:1.15;
        }
        .profile-code{
            margin:6px 0 12px;
            color:var(--olive);
            font-size:12px;
            font-weight:800;
            letter-spacing:.10em;
            text-transform:uppercase;
        }
        .profile-chips{display:flex;flex-wrap:wrap;gap:8px}
        .profile-chip{
            display:inline-flex;
            align-items:center;
            gap:7px;
            padding:8px 11px;
            border-radius:999px;
            background:#f7f3ec;
            color:#6b625b;
            border:1px solid rgba(93,64,55,.08);
            font-size:12px;
            font-weight:600;
        }
        .profile-chip i{color:var(--olive)}

        .edit-btn{
            display:inline-flex;
            align-items:center;
            gap:8px;
            min-height:44px;
            margin-left:auto;
            padding:0 16px;
            border-radius:14px;
            color:#fff;
            background:linear-gradient(135deg,#5d4037,#76503e);
            text-decoration:none;
            font-size:13px;
            font-weight:800;
            white-space:nowrap;
            box-shadow:0 10px 22px rgba(93,64,55,.16);
        }

        .stats-grid{
            display:grid;
            grid-template-columns:repeat(4,minmax(0,1fr));
            gap:12px;
            padding:0 28px 28px;
        }

        .stat-card{
            display:flex;
            align-items:center;
            gap:12px;
            min-height:96px;
            padding:16px;
            border:1px solid var(--line);
            border-radius:18px;
            background:#fff;
        }

        .stat-icon{
            width:42px;
            height:42px;
            flex:0 0 auto;
            display:grid;
            place-items:center;
            border-radius:14px;
            font-size:16px;
        }
        .stat-icon.olive{background:#edf2e4;color:var(--olive)}
        .stat-icon.brown{background:#f2e9e3;color:var(--brown)}
        .stat-icon.gold{background:#f6efdc;color:#957941}
        .stat-icon.green{background:#e7efe7;color:#4f6b4a}
        .stat-card small{display:block;color:#817871;font-size:11px;font-weight:600}
        .stat-card strong{display:block;margin-top:3px;color:var(--brown-dark);font-size:22px;line-height:1}

        .info-grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:18px;
        }

        .info-card{padding:24px}

        .card-heading{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:18px;
            margin-bottom:20px;
        }
        .card-heading-kicker{
            color:var(--olive);
            font-size:10px;
            font-weight:800;
            letter-spacing:.13em;
            text-transform:uppercase;
        }
        .card-heading h3{margin:5px 0 0;color:var(--brown-dark);font-size:21px}
        .card-heading a{color:var(--olive);text-decoration:none;font-size:12px;font-weight:800}

        .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .detail-item{
            min-height:74px;
            padding:14px;
            border-radius:15px;
            background:#faf8f2;
            border:1px solid rgba(93,64,55,.08);
        }
        .detail-item.span-2{grid-column:span 2}
        .detail-item small{display:block;color:#8d837b;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em}
        .detail-item strong{display:block;margin-top:5px;color:var(--brown-dark);font-size:13px;line-height:1.55;word-break:break-word}

        .status-pill{
            display:inline-flex;
            align-items:center;
            gap:7px;
            padding:7px 10px;
            border-radius:999px;
            background:var(--olive-soft);
            color:#4f672d;
            font-size:11px;
            font-weight:800;
        }
        .status-pill i{font-size:7px}

        .account-list{display:grid;gap:10px}
        .account-row{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
            padding:13px 0;
            border-bottom:1px solid rgba(93,64,55,.08);
        }
        .account-row:last-child{border-bottom:0}
        .account-row span{color:#7d746d;font-size:12px}
        .account-row strong{color:var(--brown-dark);font-size:12px;text-align:right}

        .security-box{
            display:flex;
            gap:13px;
            align-items:flex-start;
            padding:15px;
            border-radius:16px;
            background:#f3f6ed;
            border:1px solid rgba(85,107,47,.10);
        }
        .security-icon{width:40px;height:40px;display:grid;place-items:center;flex:0 0 auto;border-radius:13px;background:#fff;color:var(--olive)}
        .security-box strong{display:block;color:var(--brown-dark);font-size:13px}
        .security-box p{margin:4px 0 0;color:#7b736c;font-size:11px;line-height:1.6}

        .security-link{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            width:100%;
            min-height:44px;
            margin-top:13px;
            border-radius:14px;
            color:var(--brown);
            background:#f8f2e9;
            border:1px solid rgba(93,64,55,.09);
            text-decoration:none;
            font-size:12px;
            font-weight:800;
        }

        .empty-error{
            padding:32px;
            margin-bottom:18px;
            border:1px solid rgba(155,67,57,.14);
            border-radius:20px;
            background:#fff8f6;
            color:#8c4037;
            text-align:center;
        }
        .empty-error i{font-size:26px;margin-bottom:10px}
        .empty-error p{margin:5px 0 0;font-size:12px;color:#9b7770}

        @media (max-width:1150px){
            .profile-shell{grid-template-columns:1fr}
            .profile-side{display:grid;grid-template-columns:1fr 1fr;gap:18px}
            .profile-side .profile-card{height:100%}
        }
        @media (max-width:850px){
            .profile-page{width:min(100% - 22px,1500px);padding:20px 0 42px}
            .profile-hero{grid-template-columns:1fr}
            .profile-back{width:max-content}
            .stats-grid{grid-template-columns:1fr 1fr;padding-left:18px;padding-right:18px}
            .profile-main{padding-left:18px;padding-right:18px}
            .info-grid{grid-template-columns:1fr}
            .profile-side{grid-template-columns:1fr}
        }
        @media (max-width:620px){
            .profile-cover{height:100px}
            .profile-main{display:grid;grid-template-columns:auto 1fr;align-items:end;gap:14px;margin-top:-44px}
            .avatar-wrap img{width:100px;height:100px}
            .edit-btn{grid-column:1 / -1;margin-left:0;width:100%;justify-content:center}
            .profile-identity h2{font-size:23px}
            .profile-chip{font-size:10px;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .stats-grid{grid-template-columns:1fr}
            .detail-grid{grid-template-columns:1fr}
            .detail-item.span-2{grid-column:span 1}
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<main class="profile-page">
    <section class="profile-hero">
        <div>
            <span class="profile-kicker"><i class="fa-solid fa-id-card"></i> Student Account</span>
            <h1>Your <em>profile.</em></h1>
            <p>Manage your personal details, review your account information and keep your ExamSphere profile up to date.</p>
        </div>
        <a href="dashboard.php" class="profile-back">
            <i class="fa-solid fa-arrow-left"></i>
            Dashboard
        </a>
    </section>

    <?php if ($pageError !== ''): ?>
        <section class="empty-error">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong><?= profile_escape($pageError) ?></strong>
            <p>Please refresh this page and try again.</p>
        </section>
    <?php endif; ?>

    <?php if (is_array($student)): ?>
        <section class="profile-shell">
            <div>
                <article class="profile-card profile-main-card">
                    <div class="profile-cover"></div>

                    <div class="profile-main">
                        <div class="avatar-wrap">
                            <img id="profilePreview" src="<?= profile_escape($photo) ?>" alt="Profile photo">
                            <label class="avatar-upload" for="profilePhoto" title="Change profile photo">
                                <i class="fa-solid fa-camera"></i>
                            </label>
                            <input id="profilePhoto" type="file" accept="image/jpeg,image/png,image/webp" hidden>
                        </div>

                        <div class="profile-identity">
                            <h2><?= profile_escape($student['full_name']) ?></h2>
                            <p class="profile-code"><?= profile_escape($student['student_code']) ?></p>
                            <div class="profile-chips">
                                <span class="profile-chip"><i class="fa-solid fa-envelope"></i><?= profile_escape($student['email']) ?></span>
                                <span class="profile-chip"><i class="fa-solid fa-phone"></i><?= profile_escape($student['mobile'] ?: 'Not added') ?></span>
                            </div>
                        </div>

                        <a href="profile_edit.php" class="edit-btn">
                            <i class="fa-solid fa-pen"></i>
                            Edit profile
                        </a>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <span class="stat-icon olive"><i class="fa-solid fa-file-circle-check"></i></span>
                            <div><small>Completed exams</small><strong><?= $completedExams ?></strong></div>
                        </div>
                        <div class="stat-card">
                            <span class="stat-icon brown"><i class="fa-solid fa-chart-line"></i></span>
                            <div><small>Average score</small><strong><?= number_format($averageScore,1) ?>%</strong></div>
                        </div>
                        <div class="stat-card">
                            <span class="stat-icon gold"><i class="fa-solid fa-trophy"></i></span>
                            <div><small>Best score</small><strong><?= number_format($bestScore,1) ?>%</strong></div>
                        </div>
                        <div class="stat-card">
                            <span class="stat-icon green"><i class="fa-solid fa-bullseye"></i></span>
                            <div><small>Accuracy</small><strong><?= number_format($accuracy,1) ?>%</strong></div>
                        </div>
                    </div>
                </article>

                <div class="info-grid" style="margin-top:18px;">
                    <article class="profile-card info-card">
                        <div class="card-heading">
                            <div>
                                <span class="card-heading-kicker">Personal information</span>
                                <h3>Your details</h3>
                            </div>
                            <a href="profile_edit.php">Edit</a>
                        </div>

                        <form id="profileForm" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= profile_escape($csrf) ?>">

                            <div class="detail-grid">
                                <div class="detail-item">
                                    <small>Full name</small>
                                    <strong><?= profile_escape($student['full_name']) ?></strong>
                                </div>
                                <div class="detail-item">
                                    <small>Mobile</small>
                                    <strong><?= profile_escape($student['mobile'] ?: 'Not added') ?></strong>
                                </div>
                                <div class="detail-item">
                                    <small>Email</small>
                                    <strong><?= profile_escape($student['email']) ?></strong>
                                </div>
                                <div class="detail-item">
                                    <small>Student code</small>
                                    <strong><?= profile_escape($student['student_code']) ?></strong>
                                </div>
                                <div class="detail-item">
                                    <small>Gender</small>
                                    <strong><?= profile_escape($student['gender'] ?: 'Not specified') ?></strong>
                                </div>
                                <div class="detail-item">
                                    <small>Date of birth</small>
                                    <strong><?= profile_date($student['dob'] ?? null) ?></strong>
                                </div>
                                <div class="detail-item">
                                    <small>City</small>
                                    <strong><?= profile_escape($student['city'] ?: 'Not added') ?></strong>
                                </div>
                                <div class="detail-item">
                                    <small>State</small>
                                    <strong><?= profile_escape($student['state'] ?: 'Not added') ?></strong>
                                </div>
                                <div class="detail-item">
                                    <small>Pincode</small>
                                    <strong><?= profile_escape($student['pincode'] ?: 'Not added') ?></strong>
                                </div>
                                <div class="detail-item span-2">
                                    <small>Address</small>
                                    <strong><?= profile_escape($student['address'] ?: 'No address added') ?></strong>
                                </div>
                            </div>
                        </form>
                    </article>

                    <article class="profile-card info-card">
                        <div class="card-heading">
                            <div>
                                <span class="card-heading-kicker">Preparation snapshot</span>
                                <h3>Your progress</h3>
                            </div>
                            <a href="performance.php">Analytics</a>
                        </div>

                        <div class="detail-grid">
                            <div class="detail-item">
                                <small>Questions attempted</small>
                                <strong><?= $attemptedQuestions ?></strong>
                            </div>
                            <div class="detail-item">
                                <small>Correct answers</small>
                                <strong><?= $correctAnswers ?></strong>
                            </div>
                            <div class="detail-item">
                                <small>Wrong answers</small>
                                <strong><?= (int) ($stats['wrong_answers'] ?? 0) ?></strong>
                            </div>
                            <div class="detail-item">
                                <small>Accuracy</small>
                                <strong><?= number_format($accuracy,1) ?>%</strong>
                            </div>
                            <div class="detail-item span-2">
                                <small>Account status</small>
                                <strong>
                                    <span class="status-pill">
                                        <i class="fa-solid fa-circle"></i>
                                        <?= profile_escape($student['status']) ?>
                                    </span>
                                </strong>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <aside class="profile-side">
                <article class="profile-card info-card">
                    <div class="card-heading">
                        <div>
                            <span class="card-heading-kicker">Account</span>
                            <h3>Account details</h3>
                        </div>
                    </div>

                    <div class="account-list">
                        <div class="account-row">
                            <span>Status</span>
                            <strong><?= profile_escape($student['status']) ?></strong>
                        </div>
                        <div class="account-row">
                            <span>Email verification</span>
                            <strong><?= $student['email_verified'] ? 'Verified' : 'Not verified' ?></strong>
                        </div>
                        <div class="account-row">
                            <span>Member since</span>
                            <strong><?= profile_date($student['created_at'] ?? null) ?></strong>
                        </div>
                        <div class="account-row">
                            <span>Last login</span>
                            <strong><?= profile_date($student['last_login'] ?? null, 'd M Y, h:i A') ?></strong>
                        </div>
                    </div>
                </article>

                <article class="profile-card info-card">
                    <div class="card-heading">
                        <div>
                            <span class="card-heading-kicker">Security</span>
                            <h3>Keep it protected</h3>
                        </div>
                    </div>

                    <div class="security-box">
                        <span class="security-icon"><i class="fa-solid fa-shield-halved"></i></span>
                        <div>
                            <strong>Your account stays yours.</strong>
                            <p>Use a strong password and never share your ExamSphere login credentials.</p>
                        </div>
                    </div>

                    <a href="settings.php" class="security-link">
                        <i class="fa-solid fa-gear"></i>
                        Security settings
                    </a>
                </article>

                <article class="profile-card info-card">
                    <div class="card-heading">
                        <div>
                            <span class="card-heading-kicker">Next step</span>
                            <h3>Keep improving</h3>
                        </div>
                    </div>

                    <p style="margin:0;color:#7b736c;font-size:12px;line-height:1.75;">
                        Use your performance analytics to identify weak areas and continue with focused practice.
                    </p>

                    <a href="practice_exams.php" class="security-link">
                        <i class="fa-solid fa-book-open"></i>
                        Start practice
                    </a>
                </article>
            </aside>
        </section>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/profile.js" defer></script>
</body>
</html>
