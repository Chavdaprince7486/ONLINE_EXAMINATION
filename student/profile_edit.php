<?php
declare(strict_types=1);
require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    header('Location: ../auth/login.php');
    exit;
}

$studentId = (int)$_SESSION['user_id'];
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

try {
    $stmt = $conn->prepare('SELECT full_name,email,mobile,gender,dob,address,city,state,pincode,student_code FROM students WHERE id = ? AND status = \'Active\' LIMIT 1');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        session_destroy();
        header('Location: ../auth/login.php');
        exit;
    }
} catch (Throwable $exception) {
    error_log('Student profile edit load failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load your profile.');
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Profile | ExamSphere</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="assets/css/student-nav.css">
<style>
:root{--cream:#f5f1e8;--paper:#fffdf9;--brown:#5d4037;--brown-dark:#3e2723;--olive:#556b2f;--olive-soft:#eaf0df;--muted:#7b726b;--line:#e7dfd3;--shadow:0 18px 50px rgba(62,39,35,.10)}
*{box-sizing:border-box}body{margin:0;background:linear-gradient(180deg,#faf8f3 0%,#f3eee5 100%);font-family:Poppins,sans-serif;color:#333;min-height:100vh}.edit-page{width:min(1450px,calc(100% - 28px));margin:0 auto;padding:8px 0 28px;position:relative;z-index:1}.edit-hero{display:flex;justify-content:space-between;align-items:flex-end;gap:24px;margin-bottom:20px;padding:28px 30px;background:#fffdfa;border:1px solid var(--line);border-radius:24px;box-shadow:var(--shadow)}.kicker{display:inline-flex;align-items:center;gap:8px;color:var(--olive);font-size:.66rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}.edit-hero h1{margin:9px 0 7px;color:var(--brown-dark);font-size:clamp(2rem,4vw,3.25rem);letter-spacing:-.055em;line-height:1.04}.edit-hero p{margin:0;color:var(--muted);font-size:.86rem;line-height:1.7}.back-btn{display:inline-flex;align-items:center;gap:8px;min-height:46px;padding:0 17px;border:1px solid var(--line);border-radius:13px;background:#fff;color:var(--brown);text-decoration:none;font-size:.78rem;font-weight:800;white-space:nowrap}.edit-card{background:rgba(255,255,255,.92);border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow);overflow:hidden}.card-head{padding:22px 24px 18px;border-bottom:1px solid #eee7de}.card-head h2{margin:6px 0 0;color:var(--brown-dark);font-size:1.3rem}.form-body{padding:24px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px}.field{display:flex;flex-direction:column;gap:7px}.field.full{grid-column:1/-1}.field label{color:#7a7068;font-size:.67rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.field input,.field select,.field textarea{width:100%;border:1px solid #ddd4c9;border-radius:13px;background:#fffdfa;color:var(--brown-dark);outline:none;font:inherit;font-size:.82rem;padding:12px 14px}.field input,.field select{min-height:50px}.field textarea{resize:vertical;min-height:120px;line-height:1.55}.field input:focus,.field select:focus,.field textarea:focus{border-color:rgba(85,107,47,.65);box-shadow:0 0 0 4px rgba(85,107,47,.10)}.field input[readonly]{background:#f6f2eb;color:#81776f}.actions{display:flex;justify-content:flex-end;gap:10px;padding-top:21px;margin-top:5px;border-top:1px solid #eee7de}.btn{min-height:46px;padding:0 17px;border-radius:13px;text-decoration:none;font:800 .78rem Poppins;display:inline-flex;align-items:center;justify-content:center;gap:8px}.btn-cancel{border:1px solid var(--line);background:#fff;color:var(--brown)}.btn-save{border:0;background:linear-gradient(135deg,#5d4037,#805537);color:#fff;box-shadow:0 12px 25px rgba(93,64,55,.15);cursor:pointer}.helper{margin-top:18px;padding:14px 15px;border-radius:15px;background:#f4f0e9;color:var(--muted);font-size:.73rem;line-height:1.6}.helper i{color:var(--olive);margin-right:5px}.student-footer{width:min(1480px,calc(100% - 48px));margin:0 auto 30px;padding:18px 22px;border:1px solid #e8e3d8;border-radius:16px;color:#716961;background:rgba(255,255,255,.82);display:flex;align-items:center;justify-content:space-between;gap:18px;font-size:.73rem}.student-footer div{display:flex;align-items:center;gap:9px}.student-footer strong{color:#4a2b18;font-size:.92rem}.student-footer span{color:#716961}.student-footer p{margin:0}.student-footer a{color:#587130;font-weight:700;text-decoration:none}.student-footer a:hover{text-decoration:underline}.student-footer *{box-sizing:border-box}
@media(max-width:700px){.edit-page{width:calc(100% - 20px)}.edit-hero{display:block;padding:22px 20px}.back-btn{margin-top:17px}.form-body{padding:18px}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.actions{display:grid;grid-template-columns:1fr}.btn{width:100%}}
</style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<main class="edit-page">
    <section class="edit-hero">
        <div>
            <span class="kicker"><i class="fa-solid fa-user-pen"></i> Account</span>
            <h1>Edit profile.</h1>
            <p>Update your personal information. Your email and student code stay read-only.</p>
        </div>
        <a class="back-btn" href="profile.php"><i class="fa-solid fa-arrow-left"></i> Back to profile</a>
    </section>

    <section class="edit-card">
        <div class="card-head"><span class="kicker">Personal information</span><h2>Your details</h2></div>
        <div class="form-body">
            <form id="profileForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $escape($csrf) ?>">
                <div class="form-grid">
                    <div class="field"><label for="fullName">Full name</label><input id="fullName" name="full_name" maxlength="100" required value="<?= $escape($student['full_name']) ?>"></div>
                    <div class="field"><label for="mobile">Mobile</label><input id="mobile" name="mobile" maxlength="15" inputmode="numeric" required value="<?= $escape($student['mobile']) ?>"></div>
                    <div class="field"><label for="email">Email</label><input id="email" value="<?= $escape($student['email']) ?>" readonly></div>
                    <div class="field"><label for="studentCode">Student code</label><input id="studentCode" value="<?= $escape($student['student_code']) ?>" readonly></div>
                    <div class="field"><label for="gender">Gender</label><select id="gender" name="gender"><option value="">Prefer not to say</option><?php foreach (['Male','Female','Other'] as $gender): ?><option value="<?= $escape($gender) ?>" <?= (string)$student['gender'] === $gender ? 'selected' : '' ?>><?= $escape($gender) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="dob">Date of birth</label><input id="dob" type="date" name="dob" value="<?= $escape($student['dob'] ?? '') ?>"></div>
                    <div class="field"><label for="city">City</label><input id="city" name="city" maxlength="80" value="<?= $escape($student['city'] ?? '') ?>"></div>
                    <div class="field"><label for="state">State</label><input id="state" name="state" maxlength="80" value="<?= $escape($student['state'] ?? '') ?>"></div>
                    <div class="field"><label for="pincode">Pincode</label><input id="pincode" name="pincode" maxlength="10" inputmode="numeric" value="<?= $escape($student['pincode'] ?? '') ?>"></div>
                    <div class="field full"><label for="address">Address</label><textarea id="address" name="address" maxlength="1000" rows="5"><?= $escape($student['address'] ?? '') ?></textarea></div>
                </div>
                <div class="helper"><i class="fa-solid fa-circle-info"></i> Keep your contact details accurate so your ExamSphere account information stays up to date.</div>
                <div class="actions"><a class="btn btn-cancel" href="profile.php">Cancel</a><button class="btn btn-save" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save profile</button></div>
            </form>
        </div>
    </section>
</main>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/profile.js" defer></script>
</body>
</html>
