<?php
declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';

require_login('admin');

$adminId = current_user_id();
$message = '';
$error = '';

function admin_profile_e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* Ensure the original database can support a persistent admin profile photo. */
try {
    $columnCheck = $conn->query("SHOW COLUMNS FROM admins LIKE 'profile_photo'");
    if (!$columnCheck->fetch()) {
        $conn->exec("ALTER TABLE admins ADD COLUMN profile_photo VARCHAR(255) NULL DEFAULT NULL AFTER password");
    }
} catch (Throwable $exception) {
    $error = 'Unable to prepare profile photo storage.';
}

$admin = [];
if ($error === '') {
    try {
        $stmt = $conn->prepare("SELECT id, full_name, email, status, last_login, created_at, profile_photo FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!$admin) {
            throw new RuntimeException('Admin account not found.');
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$page_title = 'My Profile | ExamSphere';
$page_css = 'admin-subjects.css';
include 'includes/header.php';
?>

<style>
.admin-profile-page{max-width:1180px;margin:0 auto;}
.admin-profile-hero{display:flex;justify-content:space-between;align-items:center;gap:24px;padding:32px;margin-bottom:24px;background:linear-gradient(135deg,#fff,#f8f5ed);border:1px solid #e8e2d6;border-radius:24px;box-shadow:0 12px 30px rgba(0,0,0,.05)}
.admin-profile-hero h1{font-size:42px;font-weight:800;color:#4b3023;margin:4px 0 6px}.admin-profile-hero p{color:#7b7068;margin:0}.admin-kicker{font-size:11px;font-weight:800;letter-spacing:.16em;color:#6d7e20}
.admin-profile-grid{display:grid;grid-template-columns:1fr 330px;gap:24px}
.admin-profile-card{background:#fff;border:1px solid #e8e2d6;border-radius:24px;box-shadow:0 12px 30px rgba(0,0,0,.05);overflow:hidden}
.admin-profile-card-head{padding:24px 26px;border-bottom:1px solid #eee7dc}.admin-profile-card-head h2{margin:0;color:#4b3023;font-size:22px;font-weight:800}.admin-profile-card-head p{margin:6px 0 0;color:#857a72;font-size:13px}
.admin-profile-body{padding:26px}
.admin-avatar-stage{display:flex;align-items:center;gap:22px;padding:20px;border:1px solid #eee7dc;border-radius:18px;background:#faf8f2;margin-bottom:22px}
.admin-large-photo{width:110px;height:110px;border-radius:50%;object-fit:cover;border:6px solid #fff;box-shadow:0 10px 25px rgba(0,0,0,.10);background:#6b3e1e}
.admin-upload-label{display:inline-flex;align-items:center;gap:8px;padding:11px 16px;border-radius:12px;background:#6d7e20;color:#fff;font-weight:700;cursor:pointer}.admin-upload-label:hover{background:#5d4037}.admin-help{margin:8px 0 0;color:#8a8179;font-size:12px}
.admin-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.admin-detail{padding:16px;border:1px solid #eee7dc;border-radius:15px;background:#fff}.admin-detail small{display:block;color:#8b8178;font-size:11px;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px}.admin-detail strong{color:#4b3023;font-size:15px}
.admin-status{display:inline-flex;padding:6px 11px;border-radius:999px;background:#e7f4df;color:#44702f;font-weight:700;font-size:12px}
@media(max-width:900px){.admin-profile-grid{grid-template-columns:1fr}.admin-profile-hero{align-items:flex-start;flex-direction:column}.admin-detail-grid{grid-template-columns:1fr}}
</style>

<div class="dashboard-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'includes/navbar.php'; ?>
        <main class="dashboard-content">
            <div class="admin-profile-page">
                <section class="admin-profile-hero">
                    <div>
                        <div class="admin-kicker"><i class="fa-solid fa-user-shield me-1"></i> ADMIN ACCOUNT</div>
                        <h1>My Profile</h1>
                        <p>Manage your administrator account and profile photo.</p>
                    </div>
                </section>

                <?php if ($message !== ''): ?><div class="alert alert-success"><?= admin_profile_e($message) ?></div><?php endif; ?>
                <?php if ($error !== ''): ?><div class="alert alert-danger"><?= admin_profile_e($error) ?></div><?php endif; ?>

                <div class="admin-profile-grid">
                    <section class="admin-profile-card">
                        <div class="admin-profile-card-head">
                            <h2>Profile Photo</h2>
                            <p>Upload your administrator photo. It will appear in the admin navbar automatically.</p>
                        </div>
                        <div class="admin-profile-body">
                            <div class="admin-avatar-stage">
                                <?php
                                $profilePhoto = trim((string)($admin['profile_photo'] ?? ''));
                                $photoUrl = BASE_URL . 'admin/assets/images/default-admin.png';
                                if ($profilePhoto !== '') {
                                    $fileName = basename($profilePhoto);
                                    $filePath = dirname(__DIR__) . '/uploads/admins/' . $fileName;
                                    if (is_file($filePath)) {
                                        $photoUrl = BASE_URL . 'uploads/admins/' . rawurlencode($fileName) . '?v=' . (string)@filemtime($filePath);
                                    }
                                }
                                ?>
                                <img id="adminProfilePreview" class="admin-large-photo" src="<?= admin_profile_e($photoUrl) ?>" alt="Admin profile photo" onerror="this.src='<?= admin_profile_e(BASE_URL . 'admin/assets/images/default-admin.png') ?>';">
                                <div>
                                    <label class="admin-upload-label" for="adminProfilePhoto">
                                        <i class="fa-solid fa-camera"></i> Change Photo
                                    </label>
                                    <input id="adminProfilePhoto" type="file" accept="image/jpeg,image/png,image/webp" hidden>
                                    <p class="admin-help">JPG, PNG or WebP · Maximum 2 MB</p>
                                </div>
                            </div>

                            <div class="admin-detail-grid">
                                <div class="admin-detail"><small>Full Name</small><strong><?= admin_profile_e($admin['full_name'] ?? 'Administrator') ?></strong></div>
                                <div class="admin-detail"><small>Email</small><strong><?= admin_profile_e($admin['email'] ?? '') ?></strong></div>
                                <div class="admin-detail"><small>Status</small><span class="admin-status"><?= admin_profile_e($admin['status'] ?? 'Active') ?></span></div>
                                <div class="admin-detail"><small>Last Login</small><strong><?= admin_profile_e($admin['last_login'] ?? '—') ?></strong></div>
                            </div>
                        </div>
                    </section>

                    <aside class="admin-profile-card">
                        <div class="admin-profile-card-head">
                            <h2>Account</h2>
                            <p>Quick access to your admin settings.</p>
                        </div>
                        <div class="admin-profile-body">
                            <a class="btn btn-outline-secondary w-100 mb-2" href="settings.php"><i class="fa-solid fa-gear me-1"></i> Settings</a>
                            <a class="btn btn-outline-secondary w-100" href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket me-1"></i> Logout</a>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
(() => {
    const input = document.getElementById('adminProfilePhoto');
    const preview = document.getElementById('adminProfilePreview');
    if (!input || !preview) return;

    input.addEventListener('change', async () => {
        const file = input.files && input.files[0];
        if (!file) return;
        const allowed = ['image/jpeg','image/png','image/webp'];
        if (!allowed.includes(file.type)) { alert('Please choose a JPG, PNG or WebP image.'); input.value=''; return; }
        if (file.size > 2 * 1024 * 1024) { alert('Maximum image size is 2 MB.'); input.value=''; return; }

        preview.src = URL.createObjectURL(file);
        const fd = new FormData();
        fd.append('profile_photo', file);
        fd.append('csrf_token', <?= json_encode(function_exists('csrf_token') ? csrf_token() : ($_SESSION['csrf_token'] ?? '')) ?>);

        try {
            const res = await fetch('ajax/upload_profile_photo.php', {method:'POST', body:fd, credentials:'same-origin', headers:{'Accept':'application/json'}});
            const data = await res.json();
            if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Upload failed.');
            if (data.photo_url) {
                const fresh = data.photo_url + (data.photo_url.includes('?') ? '&' : '?') + 't=' + Date.now();
                preview.src = fresh;
                document.querySelectorAll('[data-admin-photo]').forEach(img => { img.src = fresh; img.removeAttribute('srcset'); });
            }
        } catch (error) {
            alert(error.message || 'Unable to upload photo.');
        } finally {
            input.value = '';
        }
    });
})();
</script>

<script src="assets/js/admin-shell.js"></script>
</body>
</html>
