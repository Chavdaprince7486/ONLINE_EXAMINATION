<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/auth.php';

require_login('admin');

$adminId = current_user_id();
$userName = trim((string)($_SESSION['user_name'] ?? 'Administrator'));
$userEmail = trim((string)($_SESSION['user_email'] ?? ''));
$profilePhoto = '';

/*
 * Backward-compatible schema bootstrap.
 * The original admins table does not contain profile_photo, so create the
 * nullable column once when needed. This keeps the feature dynamic.
 */
try {
    $columnCheck = $conn->query("SHOW COLUMNS FROM admins LIKE 'profile_photo'");
    if (!$columnCheck->fetch()) {
        $conn->exec("ALTER TABLE admins ADD COLUMN profile_photo VARCHAR(255) NULL DEFAULT NULL AFTER password");
    }

    $photoStmt = $conn->prepare("SELECT full_name, email, profile_photo FROM admins WHERE id = ? LIMIT 1");
    $photoStmt->execute([$adminId]);
    $admin = $photoStmt->fetch() ?: [];

    if (!empty($admin['full_name'])) {
        $userName = trim((string)$admin['full_name']);
        $_SESSION['user_name'] = $userName;
    }
    if (!empty($admin['email'])) {
        $userEmail = trim((string)$admin['email']);
        $_SESSION['user_email'] = $userEmail;
    }
    $profilePhoto = trim((string)($admin['profile_photo'] ?? ''));
} catch (Throwable $exception) {
    $profilePhoto = trim((string)($_SESSION['admin_profile_photo'] ?? ''));
}

$baseUrl = rtrim((string)BASE_URL, '/') . '/';
$photoUrl = $baseUrl . 'admin/assets/images/default-admin.png';

if ($profilePhoto !== '') {
    $fileName = basename($profilePhoto);
    $filePath = dirname(__DIR__, 2) . '/uploads/admins/' . $fileName;
    if (is_file($filePath)) {
        $photoUrl = $baseUrl . 'uploads/admins/' . rawurlencode($fileName) . '?v=' . (string)@filemtime($filePath);
    }
}

$initials = 'AD';
$parts = preg_split('/\s+/', $userName, -1, PREG_SPLIT_NO_EMPTY);
if ($parts) {
    $initials = strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $initials .= strtoupper(mb_substr($parts[count($parts)-1], 0, 1));
    }
}
?>

<header class="topbar">
    <button id="toggleSidebar" class="toggle-btn" type="button" aria-label="Toggle sidebar">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" placeholder="Search anything..." autocomplete="off">
    </div>

    <div class="top-right">
        <?php require __DIR__ . '/../../includes/notification-widget.php'; ?>

        <details class="admin-profile-menu">
            <summary class="profile" aria-label="Admin account">
                <img
                    id="adminTopbarPhoto"
                    src="<?= htmlspecialchars($photoUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    alt="Admin profile"
                    class="avatar admin-avatar-image"
                    data-admin-photo
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                >
                <div class="avatar admin-avatar-fallback" style="display:none">
                    <?= htmlspecialchars($initials, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </div>
                <div class="profile-text">
                    <h5><?= htmlspecialchars($userName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h5>
                    <span>Administrator</span>
                </div>
                <i class="fa-solid fa-angle-down admin-profile-chevron"></i>
            </summary>

            <div class="admin-profile-dropdown">
                <a href="<?= htmlspecialchars($baseUrl . 'admin/profile.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <i class="fa-regular fa-user"></i>
                    <span>My Profile</span>
                </a>
                <a href="<?= htmlspecialchars($baseUrl . 'admin/settings.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <i class="fa-solid fa-gear"></i>
                    <span>Settings</span>
                </a>
                <a href="<?= htmlspecialchars($baseUrl . 'auth/logout.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </div>
        </details>
    </div>
</header>

<style>
.admin-profile-menu{position:relative;}
.admin-profile-menu>summary{list-style:none;cursor:pointer;}
.admin-profile-menu>summary::-webkit-details-marker{display:none;}
.admin-avatar-image,.admin-avatar-fallback{width:52px;height:52px;border-radius:50%;object-fit:cover;flex:0 0 52px;background:#6B3E1E;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;border:2px solid #eee7d8;}
.admin-profile-chevron{color:#7d746c;font-size:12px;margin-left:2px;transition:transform .2s ease;}
.admin-profile-menu[open] .admin-profile-chevron{transform:rotate(180deg);}
.admin-profile-dropdown{position:absolute;right:0;top:calc(100% + 10px);width:220px;padding:8px;background:#fff;border:1px solid #ece5da;border-radius:16px;box-shadow:0 18px 45px rgba(47,36,30,.14);z-index:1200;}
.admin-profile-dropdown a{display:flex;align-items:center;gap:11px;padding:11px 12px;border-radius:11px;text-decoration:none;color:#5b3a22;font-weight:600;font-size:14px;}
.admin-profile-dropdown a:hover{background:#f7f2e8;color:#5d4037;}
.admin-profile-dropdown a i{width:18px;text-align:center;}
@media(max-width:800px){.search-box{display:none}.topbar{padding:0 16px}.admin-profile-dropdown{right:-5px;}}
</style>

