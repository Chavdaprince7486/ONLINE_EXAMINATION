<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../config/site_settings.php';

require_login('admin');

$settings = examsphere_load_settings($conn);
$defaults = examsphere_default_settings();
$message = '';
$error = '';

function admin_settings_h(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_settings_save_upload(array $file, string $prefix, array $allowedMimeToExt, string $uploadDir, string $oldRelativePath = ''): string
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('Image upload failed. Please try again.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 3 * 1024 * 1024) {
        throw new RuntimeException('Uploaded image must be between 1 byte and 3 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file((string)$file['tmp_name']);
    if (!isset($allowedMimeToExt[$mime])) {
        throw new RuntimeException('Unsupported image type. Use PNG, JPG, JPEG, WEBP or ICO.');
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create the website upload directory.');
    }

    $filename = $prefix . '_' . bin2hex(random_bytes(12)) . '.' . $allowedMimeToExt[$mime];
    $destination = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
        throw new RuntimeException('Unable to save the uploaded image.');
    }

    if ($oldRelativePath !== '') {
        $oldFile = dirname(__DIR__) . '/' . ltrim($oldRelativePath, '/');
        $oldReal = realpath($oldFile);
        $dirReal = realpath($uploadDir);
        if ($oldReal !== false && $dirReal !== false && str_starts_with($oldReal, $dirReal . DIRECTORY_SEPARATOR) && is_file($oldReal)) {
            @unlink($oldReal);
        }
    }

    return 'uploads/site/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Security verification failed. Refresh the page and try again.');
        }

        $fields = array_keys($defaults);
        $payload = [];
        foreach ($fields as $key) {
            if (in_array($key, ['site_logo', 'site_favicon'], true)) {
                continue;
            }

            if (str_starts_with($key, 'show_')) {
                $payload[$key] = isset($_POST[$key]) ? '1' : '0';
                continue;
            }

            if (array_key_exists($key, $_POST)) {
                $payload[$key] = trim((string)$_POST[$key]);
            }
        }

        $colorKeys = ['primary_color', 'accent_color', 'background_color', 'card_color'];
        foreach ($colorKeys as $key) {
            if (isset($payload[$key]) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $payload[$key])) {
                throw new RuntimeException('Invalid color value for ' . $key . '.');
            }
        }

        foreach ($payload as $key => $value) {
            $stmt = $conn->prepare(
                'INSERT INTO website_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            $stmt->execute([$key, $value]);
        }

        $uploadDir = dirname(__DIR__) . '/uploads/site';
        $logo = admin_settings_save_upload(
            $_FILES['site_logo'] ?? [],
            'logo',
            ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/x-icon'=>'ico','image/vnd.microsoft.icon'=>'ico'],
            $uploadDir,
            trim((string)($settings['site_logo'] ?? ''))
        );
        if ($logo !== '') {
            $stmt = $conn->prepare(
                'INSERT INTO website_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            $stmt->execute(['site_logo', $logo]);
        }

        $favicon = admin_settings_save_upload(
            $_FILES['site_favicon'] ?? [],
            'favicon',
            ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/x-icon'=>'ico','image/vnd.microsoft.icon'=>'ico'],
            $uploadDir,
            trim((string)($settings['site_favicon'] ?? ''))
        );
        if ($favicon !== '') {
            $stmt = $conn->prepare(
                'INSERT INTO website_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            $stmt->execute(['site_favicon', $favicon]);
        }

        $message = 'Website settings saved successfully.';
        $settings = examsphere_load_settings($conn);
        // Reset helper cache by taking a fresh query below.
        $rows = $conn->query('SELECT setting_key, setting_value FROM website_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings = array_merge($defaults, array_map('strval', $rows));

    } catch (Throwable $exception) {
        error_log('ExamSphere admin website settings save failed: ' . $exception->getMessage());
        $error = $exception->getMessage();
    }
}

$page_title = 'Website Settings | ExamSphere';
$page_css = 'admin-subjects.css';
include 'includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/navbar.php'; ?>

        <main class="dashboard-content site-settings-page">
            <div class="site-settings-heading">
                <div>
                    <span class="settings-kicker"><i class="fa-solid fa-globe"></i> WEBSITE CONTROL CENTER</span>
                    <h1>Website Settings</h1>
                    <p>Change your public ExamSphere website content, branding, contact details, SEO and section visibility from one place.</p>
                </div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="settings-alert success"><i class="fa-solid fa-circle-check"></i><?= admin_settings_h($message) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="settings-alert danger"><i class="fa-solid fa-circle-exclamation"></i><?= admin_settings_h($error) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="settings-form">
                <?= csrf_field() ?>

                <section class="settings-panel">
                    <div class="panel-head"><div><span>BRANDING</span><h2>Website identity</h2></div><i class="fa-solid fa-palette"></i></div>
                    <div class="settings-grid two">
                        <label>Site name<input name="site_name" value="<?= admin_settings_h($settings['site_name']) ?>" required></label>
                        <label>Site tagline<input name="site_tagline" value="<?= admin_settings_h($settings['site_tagline']) ?>"></label>
                        <label>Upload logo<input type="file" name="site_logo" accept=".png,.jpg,.jpeg,.webp,.ico,image/*"><small>Leave empty to keep current logo.</small></label>
                        <label>Upload favicon<input type="file" name="site_favicon" accept=".png,.jpg,.jpeg,.webp,.ico,image/*"><small>Optional browser tab icon.</small></label>
                    </div>
                </section>

                <section class="settings-panel">
                    <div class="panel-head"><div><span>APPEARANCE</span><h2>Theme colors</h2></div><i class="fa-solid fa-droplet"></i></div>
                    <div class="settings-grid four">
                        <label>Primary<input type="color" name="primary_color" value="<?= admin_settings_h($settings['primary_color']) ?>"></label>
                        <label>Accent<input type="color" name="accent_color" value="<?= admin_settings_h($settings['accent_color']) ?>"></label>
                        <label>Page background<input type="color" name="background_color" value="<?= admin_settings_h($settings['background_color']) ?>"></label>
                        <label>Card background<input type="color" name="card_color" value="<?= admin_settings_h($settings['card_color']) ?>"></label>
                    </div>
                </section>

                <section class="settings-panel">
                    <div class="panel-head"><div><span>SEO</span><h2>Search & browser details</h2></div><i class="fa-solid fa-magnifying-glass-chart"></i></div>
                    <div class="settings-grid one">
                        <label>SEO title<input name="seo_title" value="<?= admin_settings_h($settings['seo_title']) ?>"></label>
                        <label>SEO description<textarea name="seo_description" rows="3"><?= admin_settings_h($settings['seo_description']) ?></textarea></label>
                        <label>SEO keywords<input name="seo_keywords" value="<?= admin_settings_h($settings['seo_keywords']) ?>"></label>
                    </div>
                </section>

                <section class="settings-panel">
                    <div class="panel-head"><div><span>HERO</span><h2>Homepage first screen</h2></div><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                    <div class="settings-grid two">
                        <label>Kicker<input name="hero_kicker" value="<?= admin_settings_h($settings['hero_kicker']) ?>"></label>
                        <label>Main title line<input name="hero_title_1" value="<?= admin_settings_h($settings['hero_title_1']) ?>"></label>
                        <label>Accent title line<input name="hero_title_2" value="<?= admin_settings_h($settings['hero_title_2']) ?>"></label>
                        <label>Primary button text<input name="hero_primary_text" value="<?= admin_settings_h($settings['hero_primary_text']) ?>"></label>
                        <label>Primary button URL<input name="hero_primary_url" value="<?= admin_settings_h($settings['hero_primary_url']) ?>"></label>
                        <label>Secondary button text<input name="hero_secondary_text" value="<?= admin_settings_h($settings['hero_secondary_text']) ?>"></label>
                        <label>Secondary button URL<input name="hero_secondary_url" value="<?= admin_settings_h($settings['hero_secondary_url']) ?>"></label>
                        <label>Trust line 1<input name="hero_trust_1" value="<?= admin_settings_h($settings['hero_trust_1']) ?>"></label>
                        <label>Trust line 2<input name="hero_trust_2" value="<?= admin_settings_h($settings['hero_trust_2']) ?>"></label>
                    </div>
                    <label class="block-label">Hero description<textarea name="hero_description" rows="4"><?= admin_settings_h($settings['hero_description']) ?></textarea></label>
                </section>

                <section class="settings-panel">
                    <div class="panel-head"><div><span>WHY EXAMSPHERE</span><h2>Feature section</h2></div><i class="fa-solid fa-layer-group"></i></div>
                    <div class="settings-grid one"><label>Kicker<input name="why_kicker" value="<?= admin_settings_h($settings['why_kicker']) ?>"></label><label>Title<textarea name="why_title" rows="2"><?= admin_settings_h($settings['why_title']) ?></textarea></label><label>Description<textarea name="why_description" rows="3"><?= admin_settings_h($settings['why_description']) ?></textarea></label></div>
                    <div class="feature-editor">
                        <?php for ($i=1; $i<=6; $i++): ?>
                        <div class="feature-editor-card"><b>Feature <?= $i ?></b><label>Title<input name="feature_<?= $i ?>_title" value="<?= admin_settings_h($settings["feature_{$i}_title"]) ?>"></label><label>Description<textarea name="feature_<?= $i ?>_text" rows="3"><?= admin_settings_h($settings["feature_{$i}_text"]) ?></textarea></label></div>
                        <?php endfor; ?>
                    </div>
                </section>

                <section class="settings-panel">
                    <div class="panel-head"><div><span>HOW IT WORKS</span><h2>Process section</h2></div><i class="fa-solid fa-shoe-prints"></i></div>
                    <div class="settings-grid one"><label>Kicker<input name="process_kicker" value="<?= admin_settings_h($settings['process_kicker']) ?>"></label><label>Title<input name="process_title" value="<?= admin_settings_h($settings['process_title']) ?>"></label><label>Description<textarea name="process_description" rows="3"><?= admin_settings_h($settings['process_description']) ?></textarea></label></div>
                    <div class="feature-editor">
                        <?php for ($i=1; $i<=4; $i++): ?>
                        <div class="feature-editor-card"><b>Step <?= $i ?></b><label>Title<input name="process_step_<?= $i ?>_title" value="<?= admin_settings_h($settings["process_step_{$i}_title"]) ?>"></label><label>Description<textarea name="process_step_<?= $i ?>_text" rows="3"><?= admin_settings_h($settings["process_step_{$i}_text"]) ?></textarea></label></div>
                        <?php endfor; ?>
                    </div>
                </section>

                <section class="settings-panel">
                    <div class="panel-head"><div><span>CONTACT & FOOTER</span><h2>Public contact details</h2></div><i class="fa-solid fa-address-book"></i></div>
                    <div class="settings-grid two">
                        <label>Contact kicker<input name="contact_kicker" value="<?= admin_settings_h($settings['contact_kicker']) ?>"></label>
                        <label>Contact title<input name="contact_title" value="<?= admin_settings_h($settings['contact_title']) ?>"></label>
                        <label>Contact email<input type="email" name="contact_email" value="<?= admin_settings_h($settings['contact_email']) ?>"></label>
                        <label>Contact phone<input name="contact_phone" value="<?= admin_settings_h($settings['contact_phone']) ?>"></label>
                        <label>Address<input name="contact_address" value="<?= admin_settings_h($settings['contact_address']) ?>"></label>
                        <label>Primary button text<input name="contact_primary_text" value="<?= admin_settings_h($settings['contact_primary_text']) ?>"></label>
                        <label>Primary button URL<input name="contact_primary_url" value="<?= admin_settings_h($settings['contact_primary_url']) ?>"></label>
                        <label>Login prompt<input name="contact_login_text" value="<?= admin_settings_h($settings['contact_login_text']) ?>"></label>
                    </div>
                    <label class="block-label">Contact description<textarea name="contact_description" rows="3"><?= admin_settings_h($settings['contact_description']) ?></textarea></label>
                    <div class="settings-grid three">
                        <label>Contact point 1<input name="contact_point_1" value="<?= admin_settings_h($settings['contact_point_1']) ?>"></label>
                        <label>Contact point 2<input name="contact_point_2" value="<?= admin_settings_h($settings['contact_point_2']) ?>"></label>
                        <label>Contact point 3<input name="contact_point_3" value="<?= admin_settings_h($settings['contact_point_3']) ?>"></label>
                    </div>
                    <div class="settings-grid one">
                        <label>Footer description<textarea name="footer_description" rows="3"><?= admin_settings_h($settings['footer_description']) ?></textarea></label>
                        <label>Footer copyright<input name="footer_copyright" value="<?= admin_settings_h($settings['footer_copyright']) ?>"></label>
                    </div>
                </section>

                <section class="settings-panel">
                    <div class="panel-head"><div><span>SOCIAL</span><h2>Social media links</h2></div><i class="fa-solid fa-share-nodes"></i></div>
                    <div class="settings-grid two">
                        <label>Facebook<input name="social_facebook" value="<?= admin_settings_h($settings['social_facebook']) ?>"></label>
                        <label>Instagram<input name="social_instagram" value="<?= admin_settings_h($settings['social_instagram']) ?>"></label>
                        <label>YouTube<input name="social_youtube" value="<?= admin_settings_h($settings['social_youtube']) ?>"></label>
                        <label>LinkedIn<input name="social_linkedin" value="<?= admin_settings_h($settings['social_linkedin']) ?>"></label>
                        <label>WhatsApp<input name="social_whatsapp" value="<?= admin_settings_h($settings['social_whatsapp']) ?>"></label>
                    </div>
                </section>

                <section class="settings-panel">
                    <div class="panel-head"><div><span>FAQ</span><h2>Frequently asked questions</h2></div><i class="fa-regular fa-circle-question"></i></div>
                    <div class="faq-editor">
                        <?php for ($i=1; $i<=6; $i++): ?>
                        <div class="faq-editor-card"><b>FAQ <?= $i ?></b><label>Question<input name="faq_<?= $i ?>_q" value="<?= admin_settings_h($settings["faq_{$i}_q"]) ?>"></label><label>Answer<textarea name="faq_<?= $i ?>_a" rows="4"><?= admin_settings_h($settings["faq_{$i}_a"]) ?></textarea></label></div>
                        <?php endfor; ?>
                    </div>
                </section>

                <section class="settings-panel">
                    <div class="panel-head"><div><span>SECTION CONTROL</span><h2>Show or hide homepage sections</h2></div><i class="fa-solid fa-toggle-on"></i></div>
                    <div class="toggle-grid">
                        <?php
                        $toggles = [
                            'show_how_it_works'=>'How It Works', 'show_why_choose'=>'Why ExamSphere', 'show_categories'=>'Categories',
                            'show_practice_exams'=>'Practice Exams', 'show_live_exams'=>'Live Exams', 'show_plans'=>'Subscription Plans',
                            'show_materials'=>'Study Materials', 'show_faq'=>'FAQ', 'show_contact'=>'Contact', 'show_footer'=>'Footer'
                        ];
                        foreach ($toggles as $key=>$label):
                        ?>
                        <label class="toggle-card"><input type="checkbox" name="<?= $key ?>" value="1" <?= site_setting_bool($conn, $key, true) ? 'checked' : '' ?>><span><?= $label ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <div class="settings-submit-row">
                    <button type="submit" class="btn btn-primary settings-save"><i class="fa-solid fa-floppy-disk me-2"></i>Save Website Settings</button>
                </div>
            </form>
        </main>
    </div>
</div>

<style>
.site-settings-page{padding:24px 28px 60px;background:#f7f5ef;min-height:calc(100vh - 80px)}
.site-settings-heading{display:flex;justify-content:space-between;gap:20px;margin-bottom:18px}.site-settings-heading h1{font-size:2rem;font-weight:900;color:#3e2723;margin:7px 0}.site-settings-heading p{margin:0;color:#81766d;max-width:850px}.settings-kicker{color:#556b2f;font-size:.68rem;font-weight:900;letter-spacing:.14em}
.settings-alert{padding:12px 16px;border-radius:12px;margin:0 0 16px;display:flex;gap:10px;align-items:center;font-weight:700;font-size:.8rem}.settings-alert.success{background:#edf5e8;color:#4c6f3c}.settings-alert.danger{background:#f9e9e7;color:#965044}
.settings-panel{background:#fff;border:1px solid #e8e0d5;border-radius:18px;padding:20px;margin-bottom:18px;box-shadow:0 10px 26px rgba(62,39,35,.05)}
.panel-head{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:14px;margin-bottom:16px;border-bottom:1px solid #eee7dd}.panel-head span{font-size:.64rem;font-weight:900;letter-spacing:.12em;color:#556b2f}.panel-head h2{margin:5px 0 0;color:#3e2723;font-size:1.15rem;font-weight:900}.panel-head>i{color:#556b2f;font-size:1.1rem;padding-top:4px}
.settings-grid{display:grid;gap:14px}.settings-grid.one{grid-template-columns:1fr}.settings-grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}.settings-grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}.settings-grid.four{grid-template-columns:repeat(4,minmax(0,1fr))}
.settings-grid label,.block-label,.feature-editor-card label,.faq-editor-card label{display:flex;flex-direction:column;gap:7px;color:#5e5149;font-size:.72rem;font-weight:800}.settings-grid input,.settings-grid textarea,.feature-editor-card input,.feature-editor-card textarea,.faq-editor-card input,.faq-editor-card textarea{width:100%;border:1px solid #ded5c9;border-radius:11px;padding:11px 12px;background:#fffdfa;color:#3f342e;font:inherit;font-size:.78rem;font-weight:600;outline:none}.settings-grid input:focus,.settings-grid textarea:focus,.feature-editor-card input:focus,.feature-editor-card textarea:focus,.faq-editor-card input:focus,.faq-editor-card textarea:focus{border-color:#9a8158;box-shadow:0 0 0 3px rgba(154,129,88,.12)}.settings-grid input[type=color]{padding:4px;height:46px}.settings-grid small{color:#988c82;font-weight:600;font-size:.64rem}
.block-label{margin-top:14px}.feature-editor{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:14px}.feature-editor-card,.faq-editor-card{padding:14px;background:#fcfaf6;border:1px solid #ece5db;border-radius:14px;display:grid;gap:10px}.feature-editor-card>b,.faq-editor-card>b{color:#556b2f;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em}.faq-editor{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.toggle-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.toggle-card{display:flex;align-items:center;gap:10px;padding:12px 13px;border:1px solid #e8dfd5;border-radius:12px;background:#fcfaf6;color:#4e433c;font-weight:800;font-size:.74rem}.toggle-card input{accent-color:#556b2f;width:18px;height:18px}
.settings-submit-row{display:flex;justify-content:flex-end;position:sticky;bottom:12px}.settings-save{padding:13px 20px;border-radius:12px;background:linear-gradient(120deg,#795548,#5d4037);border:0;color:#fff;font-weight:800;box-shadow:0 12px 24px rgba(93,64,55,.18)}
@media(max-width:1100px){.settings-grid.four{grid-template-columns:repeat(2,minmax(0,1fr))}.feature-editor{grid-template-columns:repeat(2,minmax(0,1fr))}.toggle-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:760px){.site-settings-page{padding:16px}.settings-grid.two,.settings-grid.three,.settings-grid.four,.feature-editor,.faq-editor,.toggle-grid{grid-template-columns:1fr}.settings-submit-row{position:static}.site-settings-heading h1{font-size:1.6rem}}
</style>

<script src="assets/js/admin-shell.js"></script>
</body>
</html>
