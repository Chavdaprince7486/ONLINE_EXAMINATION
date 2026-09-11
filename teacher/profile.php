<?php
require_once '../config/session.php'; require_once '../config/config.php';
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'teacher') { header('Location: ../auth/login.php'); exit; }
$teacher_id=(int)$_SESSION['user_id']; $message=''; $error='';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken =
        trim((string) ($_POST['csrf_token'] ?? ''));

    if (!verify_csrf_token($csrfToken)) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {

        $name = trim((string) ($_POST['full_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $qualification = trim((string) ($_POST['qualification'] ?? ''));
        $experience = trim((string) ($_POST['experience'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));

        if (
            $name === '' ||
            mb_strlen($name) > 100 ||
            ($phone !== '' && !preg_match('/^[0-9+() -]{7,20}$/', $phone))
        ) {
            $error = 'Enter a valid name and phone number.';
        } elseif (
            mb_strlen($qualification) > 150 ||
            mb_strlen($experience) > 100 ||
            mb_strlen($address) > 1000
        ) {
            $error = 'One or more profile fields are too long.';
        } else {
            try {
                $save = $conn->prepare(
                    'UPDATE teachers
                     SET full_name=?, phone=?, mobile=?, qualification=?, experience=?, address=?
                     WHERE id=?'
                );
                $save->execute([
                    $name,
                    $phone !== '' ? $phone : null,
                    $phone !== '' ? $phone : null,
                    $qualification !== '' ? $qualification : null,
                    $experience !== '' ? $experience : null,
                    $address !== '' ? $address : null,
                    $teacher_id
                ]);
                $_SESSION['user_name'] = $name;
                $message = 'Your faculty profile has been updated.';
            } catch (Throwable $exception) {
                error_log('Teacher profile update failed: ' . $exception->getMessage());
                $error = 'Unable to update your profile right now.';
            }
        }
    }
}

$q=$conn->prepare('SELECT * FROM teachers WHERE id=?');$q->execute(array($teacher_id));$teacher=$q->fetch(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My Faculty Profile | ExamSphere</title><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"><link rel="stylesheet" href="../assets/css/portal.css"></head><body class="portal-body"><div class="portal-layout"><?php include 'includes/sidebar.php'; ?><main class="portal-main"><header class="portal-topbar"><div><h1>My faculty profile</h1><p class="portal-subtitle">Keep your academic contact details up to date.</p></div></header><?php if($message): ?><div class="alert alert-success"><?=htmlspecialchars($message)?></div><?php endif; ?><?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?><section class="portal-panel mt-0"><div class="row align-items-center g-4 mb-4"><div class="col-auto"><div class="teacher-avatar" style="width:90px;height:90px;font-size:1.7rem"><?=htmlspecialchars(strtoupper(substr($teacher['full_name'],0,2)))?></div></div><div class="col"><h2 class="mb-1"><?=htmlspecialchars($teacher['full_name'])?></h2><p class="text-muted mb-1"><i class="fa-solid fa-id-badge me-2"></i><?=htmlspecialchars($teacher['teacher_code'] ?: 'Faculty Account')?></p><p class="text-muted mb-0"><i class="fa-solid fa-envelope me-2"></i><?=htmlspecialchars($teacher['email'])?></p></div><div class="col-auto"><span class="badge text-bg-success px-3 py-2"><?=htmlspecialchars($teacher['status'])?></span></div></div><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')?>"><div class="row g-3"><div class="col-md-6"><label class="form-label">Full name</label><input class="form-control" name="full_name" value="<?=htmlspecialchars($teacher['full_name'])?>" required></div><div class="col-md-6"><label class="form-label">Email address</label><input class="form-control" value="<?=htmlspecialchars($teacher['email'])?>" disabled></div><div class="col-md-6"><label class="form-label">Phone number</label><input class="form-control" name="phone" value="<?=htmlspecialchars($teacher['phone'] ?: $teacher['mobile'])?>" placeholder="Your contact number"></div><div class="col-md-6"><label class="form-label">Qualification</label><input class="form-control" name="qualification" value="<?=htmlspecialchars($teacher['qualification'])?>" placeholder="e.g. MCA, M.Sc."></div><div class="col-md-6"><label class="form-label">Experience</label><input class="form-control" name="experience" value="<?=htmlspecialchars($teacher['experience'])?>" placeholder="e.g. 3 years"></div><div class="col-md-6"><label class="form-label">Joined</label><input class="form-control" value="<?=date('d M Y',strtotime($teacher['created_at']))?>" disabled></div><div class="col-12"><label class="form-label">Address</label><textarea class="form-control" rows="3" name="address" maxlength="1000"><?=htmlspecialchars($teacher['address'])?></textarea></div><div class="col-12"><button class="btn btn-success px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Save profile</button></div></div></form></section></main></div></body></html>
