<?php
declare(strict_types=1);
require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    header('Location: ../auth/login.php');
    exit;
}

$csrf = csrf_token();
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings | ExamSphere</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="assets/css/student-nav.css">
<style>
:root{--cream:#f5f1e8;--paper:#fffdf9;--brown:#5d4037;--brown-dark:#3e2723;--olive:#556b2f;--olive-soft:#eaf0df;--muted:#7b726b;--line:#e7dfd3;--shadow:0 18px 50px rgba(62,39,35,.10)}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:linear-gradient(180deg,#faf8f3 0%,#f3eee5 100%);font-family:Poppins,sans-serif;color:#333;min-height:100vh}.settings-page{width:min(1450px,calc(100% - 28px));margin:0 auto;padding:8px 0 26px;position:relative;z-index:1}.settings-hero{display:flex;justify-content:space-between;align-items:flex-end;gap:24px;margin:0 0 20px;padding:28px 30px;border-radius:24px;background:#fffdfa;border:1px solid rgba(231,223,211,.95);box-shadow:var(--shadow)}.kicker{display:inline-flex;align-items:center;gap:8px;color:var(--olive);font-size:.66rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}.settings-hero h1{margin:9px 0 7px;color:var(--brown-dark);font-size:clamp(2rem,4vw,3.3rem);line-height:1.05;letter-spacing:-.055em}.settings-hero p{margin:0;color:var(--muted);font-size:.86rem;line-height:1.7}.back-btn{display:inline-flex;align-items:center;gap:8px;min-height:46px;padding:0 17px;border-radius:13px;background:#fff;border:1px solid var(--line);color:var(--brown);text-decoration:none;font-size:.78rem;font-weight:800;white-space:nowrap;box-shadow:0 10px 22px rgba(62,39,35,.06)}.settings-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);gap:18px}.card{background:rgba(255,255,255,.9);border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow);overflow:hidden}.card-head{display:flex;align-items:center;justify-content:space-between;padding:23px 24px 18px;border-bottom:1px solid #efe8df}.card-head h2{margin:6px 0 0;color:var(--brown-dark);font-size:1.35rem}.card-body{padding:22px 24px}.security-form{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:17px}.field{display:flex;flex-direction:column;gap:7px}.field label{color:#7a7068;font-size:.67rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.field input{width:100%;min-height:50px;padding:0 14px;border:1px solid #ddd4c9;border-radius:13px;background:#fffdfa;color:#3e2723;outline:none;font:inherit;font-size:.82rem}.field input:focus{border-color:rgba(85,107,47,.65);box-shadow:0 0 0 4px rgba(85,107,47,.10)}.form-note{grid-column:1/-1;margin:0;color:var(--muted);font-size:.76rem;line-height:1.6}.save-row{grid-column:1/-1;display:flex;justify-content:flex-end;padding-top:4px}.btn-primary{min-height:46px;padding:0 18px;border:0;border-radius:13px;background:linear-gradient(135deg,#5d4037,#805537);color:#fff;font:800 .78rem Poppins;cursor:pointer;box-shadow:0 12px 26px rgba(93,64,55,.15)}.habit-list{display:grid;gap:12px;padding:22px 24px}.habit{display:flex;gap:12px;padding:14px 15px;border:1px solid #e6dfd6;border-radius:16px;background:#fcfaf5}.habit-icon{width:38px;height:38px;display:grid;place-items:center;flex:none;border-radius:12px;background:var(--olive-soft);color:var(--olive)}.habit strong{display:block;color:var(--brown-dark);font-size:.8rem;margin-bottom:4px}.habit small{display:block;color:var(--muted);font-size:.7rem;line-height:1.55}.tips-card{margin-top:18px}.tips-card .card-body{padding:22px 24px}.tip{display:flex;gap:12px;align-items:flex-start}.tip-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:13px;background:#f2e8dc;color:var(--brown);flex:none}.tip strong{display:block;color:var(--brown-dark);font-size:.8rem;margin-bottom:4px}.tip p{margin:0;color:var(--muted);font-size:.73rem;line-height:1.65}.footer-space{height:4px}.student-footer{width:min(1480px,calc(100% - 48px));margin:0 auto 30px;padding:18px 22px;border:1px solid #e8e3d8;border-radius:16px;color:#716961;background:rgba(255,255,255,.82);display:flex;align-items:center;justify-content:space-between;gap:18px;font-size:.73rem}.student-footer div{display:flex;align-items:center;gap:9px}.student-footer strong{color:#4a2b18;font-size:.92rem}.student-footer span{color:#716961}.student-footer p{margin:0}.student-footer a{color:#587130;font-weight:700;text-decoration:none}.student-footer a:hover{text-decoration:underline}.student-footer *{box-sizing:border-box}
@media(max-width:1050px){.settings-grid{grid-template-columns:1fr}.security-form{grid-template-columns:1fr 1fr}}@media(max-width:680px){.settings-page{width:calc(100% - 20px);padding:6px 0 20px}.settings-hero{padding:22px 20px;display:block}.back-btn{margin-top:17px}.card-head,.card-body,.habit-list,.tips-card .card-body{padding-left:18px;padding-right:18px}.security-form{grid-template-columns:1fr}.save-row{justify-content:stretch}.btn-primary{width:100%}}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<main class="settings-page">
    <section class="settings-hero">
        <div>
            <span class="kicker"><i class="fa-solid fa-gear"></i> Account settings</span>
            <h1>Security &amp; settings.</h1>
            <p>Protect your ExamSphere account and keep your login credentials secure.</p>
        </div>
        <a class="back-btn" href="profile.php"><i class="fa-solid fa-arrow-left"></i> Back to profile</a>
    </section>

    <section class="settings-grid">
        <article class="card">
            <div class="card-head">
                <div>
                    <span class="kicker">Security</span>
                    <h2>Change password</h2>
                </div>
                <i class="fa-solid fa-shield-halved" style="color:#556b2f;font-size:23px"></i>
            </div>
            <div class="card-body">
                <form id="passwordForm" class="security-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                    <div class="field"><label for="currentPassword">Current password</label><input id="currentPassword" type="password" name="current_password" autocomplete="current-password" required></div>
                    <div class="field"><label for="newPassword">New password</label><input id="newPassword" type="password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
                    <div class="field"><label for="confirmPassword">Confirm new password</label><input id="confirmPassword" type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
                    <p class="form-note"><i class="fa-solid fa-circle-info"></i> Use at least 8 characters. A longer passphrase is stronger.</p>
                    <div class="save-row"><button class="btn-primary" type="submit"><i class="fa-solid fa-key"></i> Update password</button></div>
                </form>
            </div>
        </article>

        <aside>
            <article class="card">
                <div class="card-head"><div><span class="kicker">Account protection</span><h2>Good security habits</h2></div></div>
                <div class="habit-list">
                    <div class="habit"><span class="habit-icon"><i class="fa-solid fa-lock"></i></span><div><strong>Never share your password</strong><small>ExamSphere staff will never need your password.</small></div></div>
                    <div class="habit"><span class="habit-icon"><i class="fa-solid fa-right-from-bracket"></i></span><div><strong>Log out on shared devices</strong><small>Especially on lab, library and college computers.</small></div></div>
                    <div class="habit"><span class="habit-icon"><i class="fa-solid fa-user-shield"></i></span><div><strong>Keep your profile current</strong><small>Accurate contact details help with account recovery.</small></div></div>
                </div>
            </article>
            <article class="card tips-card">
                <div class="card-body"><div class="tip"><span class="tip-icon"><i class="fa-solid fa-lightbulb"></i></span><div><strong>Keep your account protected.</strong><p>Use a unique password for ExamSphere and avoid reusing passwords from other accounts.</p></div></div></div>
            </article>
        </aside>
    </section>
    <div class="footer-space"></div>
</main>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/profile.js" defer></script>
</body>
</html>
