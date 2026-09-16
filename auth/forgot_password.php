<?php
declare(strict_types=1);
require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/functions.php';

function fp_e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$error = $_SESSION['forgot_error'] ?? '';
$success = $_SESSION['forgot_success'] ?? '';
unset($_SESSION['forgot_error'], $_SESSION['forgot_success']);
$selectedRole = strtolower(trim((string)($_POST['role'] ?? $_SESSION['forgot_password']['role'] ?? 'student')));
if (!in_array($selectedRole, ['student','teacher','admin'], true)) $selectedRole = 'student';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Forgot Password | ExamSphere</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<style>
:root{
  --cream:#F5F5DC;
  --white:#FFFFFF;
  --brown:#5D4037;
  --dark:#3E2723;
  --olive:#556B2F;
  --olive-dark:#465923;
  --gold:#A47B29;
  --muted:#756B63;
  --line:#E3DCD2;
  --soft:#FCFAF7;
  --red:#A84538;
}
*{box-sizing:border-box}
body{
  margin:0;
  min-height:100vh;
  font-family:Arial,Helvetica,sans-serif;
  color:var(--dark);
  background:
    radial-gradient(circle at 8% 8%, rgba(85,107,47,.10), transparent 30%),
    radial-gradient(circle at 92% 92%, rgba(93,64,55,.10), transparent 32%),
    linear-gradient(145deg,#FBFAF6 0%,var(--cream) 58%,#EEF2E8 100%);
  display:flex;
  align-items:center;
  justify-content:center;
  padding:28px 16px;
}
.auth-wrap{width:min(1080px,100%)}
.card{
  display:grid;
  grid-template-columns:1.02fr .98fr;
  background:rgba(255,255,255,.92);
  border:1px solid var(--line);
  border-radius:28px;
  overflow:hidden;
  box-shadow:0 24px 80px rgba(62,39,35,.12);
}
.brand-panel{
  padding:48px 44px;
  background:linear-gradient(150deg,#5D4037 0%,#4B342D 52%,#556B2F 130%);
  color:#fff;
  position:relative;
  overflow:hidden;
}
.brand-panel:after{
  content:"";
  position:absolute;
  width:320px;height:320px;
  right:-150px;top:-120px;
  border:1px solid rgba(255,255,255,.11);
  border-radius:50%;
}
.brand-panel:before{
  content:"";
  position:absolute;
  width:210px;height:210px;
  left:-120px;bottom:-100px;
  border:1px solid rgba(255,255,255,.08);
  border-radius:50%;
}
.logo{
  width:min(270px,80%);
  display:block;
  margin:0 auto 28px;
  background:#fff;
  border-radius:24px;
  padding:14px;
}
.brand-kicker{
  letter-spacing:2px;
  font-size:10px;
  font-weight:900;
  opacity:.75;
  text-transform:uppercase;
}
.brand-title{
  font-size:36px;
  line-height:1.05;
  font-weight:900;
  margin:8px 0 12px;
}
.brand-copy{
  color:rgba(255,255,255,.82);
  line-height:1.7;
  font-size:14px;
  max-width:430px;
}
.security-row{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:10px;
  margin-top:30px;
}
.security-chip{
  padding:13px 10px;
  text-align:center;
  border:1px solid rgba(255,255,255,.12);
  background:rgba(255,255,255,.06);
  border-radius:14px;
  font-size:11px;
  color:rgba(255,255,255,.88);
}
.security-chip i{display:block;margin-bottom:6px;font-size:15px}
.form-panel{padding:48px}
.form-panel h1{margin:0;color:var(--dark);font-size:32px;font-weight:900}
.form-panel .sub{margin:8px 0 24px;color:var(--muted);font-size:13px;line-height:1.65}
.alert{
  padding:12px 14px;
  border-radius:12px;
  margin-bottom:18px;
  font-size:13px;
  line-height:1.5;
}
.alert.error{background:#FFF1EE;color:#913E34;border:1px solid #F0CDC7}
.alert.success{background:#EEF5E8;color:#4F672C;border:1px solid #D7E4C8}
.label{display:block;font-size:12px;font-weight:800;color:var(--brown);margin-bottom:8px}
.input{
  width:100%;
  height:48px;
  border:1px solid #DAD2C7;
  border-radius:13px;
  padding:0 14px;
  font-size:14px;
  outline:none;
  background:#fff;
}
.input:focus{border-color:var(--olive);box-shadow:0 0 0 4px rgba(85,107,47,.10)}
.role-row{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:20px}
.role{
  border:1px solid var(--line);
  background:var(--soft);
  color:var(--brown);
  border-radius:12px;
  padding:12px 8px;
  cursor:pointer;
  font-weight:800;
  font-size:12px;
}
.role.active{background:var(--brown);color:#fff;border-color:var(--brown)}
.role i{display:block;margin-bottom:5px;font-size:16px}
.submit{
  width:100%;
  height:50px;
  border:0;
  border-radius:13px;
  background:var(--olive);
  color:#fff;
  font-weight:900;
  cursor:pointer;
  font-size:14px;
}
.submit:hover{background:var(--olive-dark)}
.back{
  display:inline-flex;
  align-items:center;
  gap:7px;
  margin-top:18px;
  color:var(--brown);
  text-decoration:none;
  font-size:12px;
  font-weight:800;
}
.helper{
  margin-top:17px;
  padding:12px 13px;
  border-radius:12px;
  background:#FBF8F0;
  border:1px solid var(--line);
  color:var(--muted);
  font-size:11px;
  line-height:1.6;
}
@media(max-width:820px){
  .card{grid-template-columns:1fr}
  .brand-panel{padding:34px 28px}
  .form-panel{padding:34px 28px}
}
@media(max-width:520px){
  .security-row{grid-template-columns:1fr}
  .form-panel h1{font-size:27px}
  .role-row{grid-template-columns:1fr}
  .logo{width:220px}
}
</style>
</head>
<body>
<div class="auth-wrap">
<div class="card">
<section class="brand-panel">
  <img class="logo" src="../assets/images/exam_logo.png" alt="ExamSphere Logo">
  <div class="brand-kicker">Secure Account Recovery</div>
  <div class="brand-title">Reset your ExamSphere password.</div>
  <div class="brand-copy">Recover your Student, Teacher or Administrator account using a secure one-time password.</div>
  <div class="security-row">
    <div class="security-chip"><i class="fa-solid fa-shield-halved"></i>Secure</div>
    <div class="security-chip"><i class="fa-solid fa-key"></i>OTP Protected</div>
    <div class="security-chip"><i class="fa-solid fa-envelope"></i>Email Verified</div>
  </div>
</section>
<section class="form-panel">

<h1>Forgot Password?</h1>
<p class="sub">Select your account type and enter your registered email. We will send a 6-digit OTP to your email.</p>
<?php if ($error !== ''): ?><div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> <?= fp_e($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= fp_e($success) ?></div><?php endif; ?>

<form action="forgot_password_process.php" method="POST" autocomplete="on">
  <label class="label">Login as</label>
  <input type="hidden" name="role" id="role" value="<?= fp_e($selectedRole) ?>">
  <div class="role-row">
    <button type="button" class="role <?= $selectedRole==='student'?'active':'' ?>" data-role="student"><i class="fa-solid fa-user-graduate"></i>Student</button>
    <button type="button" class="role <?= $selectedRole==='teacher'?'active':'' ?>" data-role="teacher"><i class="fa-solid fa-chalkboard-user"></i>Teacher</button>
    <button type="button" class="role <?= $selectedRole==='admin'?'active':'' ?>" data-role="admin"><i class="fa-solid fa-user-shield"></i>Admin</button>
  </div>

  <label class="label" for="email">Registered Email Address</label>
  <input class="input" type="email" id="email" name="email" placeholder="Enter your registered email" autocomplete="email" required>

  <button class="submit" type="submit"><i class="fa-solid fa-paper-plane"></i> &nbsp;Send OTP</button>
</form>

<div class="helper"><i class="fa-solid fa-clock"></i> OTP is valid for 5 minutes. Never share your OTP with anyone.</div>
<a class="back" href="login.php"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>

<script>
document.querySelectorAll('.role').forEach(btn => btn.addEventListener('click', () => {
  document.querySelectorAll('.role').forEach(x => x.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('role').value = btn.dataset.role;
}));
</script>

</section>
</div>
</div>
</body>
</html>
