<?php

declare(strict_types=1);

/*
 * ExamSphere Dynamic Website Settings
 * Stores non-secret public website configuration in MySQL.
 */

function examsphere_default_settings(): array
{
    return [
        'site_name' => 'ExamSphere',
        'site_tagline' => 'Smart • Secure • Success',
        'site_logo' => 'assets/images/exam_logo.png',
        'site_favicon' => '',
        'seo_title' => 'ExamSphere | Smart Online Examination Platform',
        'seo_description' => 'ExamSphere is a modern online examination platform for practice exams, live exams, results and study materials.',
        'seo_keywords' => 'online exam, practice exam, live exam, study materials, ExamSphere',
        'primary_color' => '#5D4037',
        'accent_color' => '#556B2F',
        'background_color' => '#F5F5DC',
        'card_color' => '#FFFDF8',
        'hero_kicker' => 'SMART • SECURE • INSTANT',
        'hero_title_1' => 'Make every exam',
        'hero_title_2' => 'your next success.',
        'hero_description' => 'ExamSphere brings unlimited practice, scheduled live exams, instant results and study materials into one elegant learning platform.',
        'hero_primary_text' => 'Start practising',
        'hero_primary_url' => 'auth/register.php',
        'hero_secondary_text' => 'How it works',
        'hero_secondary_url' => '#how-it-works',
        'hero_trust_1' => 'Free practice access',
        'hero_trust_2' => 'Instant evaluation',
        'why_kicker' => 'WHY EXAMSPHERE',
        'why_title' => 'Everything you need to learn with confidence.',
        'why_description' => 'A focused platform for students, teachers and administrators—designed to stay simple, secure and easy to use.',
        'feature_1_title' => 'Unlimited practice',
        'feature_1_text' => 'Take practice exams as many times as you need, without a subscription.',
        'feature_2_title' => 'Live scheduled exams',
        'feature_2_text' => 'Join upcoming tests with clear schedule, eligibility and access details.',
        'feature_3_title' => 'Instant results',
        'feature_3_text' => 'Receive score, percentage, grade and pass/fail status immediately.',
        'feature_4_title' => 'Study materials',
        'feature_4_text' => 'Keep essential notes and preparation material within easy reach.',
        'feature_5_title' => 'Performance tracking',
        'feature_5_text' => 'See your history, subject performance and leaderboard progress.',
        'feature_6_title' => 'Secure experience',
        'feature_6_text' => 'Role-based access and carefully managed exam attempts protect your work.',
        'process_kicker' => 'HOW IT WORKS',
        'process_title' => 'A clear path from registration to result.',
        'process_description' => 'Get started in minutes, practise freely, then unlock additional learning benefits whenever you need them.',
        'process_step_1_title' => 'Register',
        'process_step_1_text' => 'Create your student account securely.',
        'process_step_2_title' => 'Practice',
        'process_step_2_text' => 'Build confidence with free practice exams.',
        'process_step_3_title' => 'Unlock access',
        'process_step_3_text' => 'Subscribe or pay only when required.',
        'process_step_4_title' => 'Get results',
        'process_step_4_text' => 'Review your instant result and progress.',
        'contact_kicker' => 'READY TO BEGIN?',
        'contact_title' => 'Your next achievement can start today.',
        'contact_description' => 'Create your student account, discover your target examination category and start building your preparation.',
        'contact_point_1' => 'Practice exams',
        'contact_point_2' => 'Live exams',
        'contact_point_3' => 'Study materials',
        'contact_primary_text' => 'Create account',
        'contact_primary_url' => 'auth/register.php',
        'contact_login_text' => 'Already have an account?',
        'contact_email' => 'support@examsphere.local',
        'contact_phone' => '+91 00000 00000',
        'contact_address' => 'Gujarat, India',
        'footer_description' => 'A modern online examination platform for focused learning and clear results.',
        'footer_copyright' => 'ExamSphere. All rights reserved.',
        'social_facebook' => '',
        'social_instagram' => '',
        'social_youtube' => '',
        'social_linkedin' => '',
        'social_whatsapp' => '',
        'show_how_it_works' => '1',
        'show_why_choose' => '1',
        'show_categories' => '1',
        'show_practice_exams' => '1',
        'show_live_exams' => '1',
        'show_plans' => '1',
        'show_materials' => '1',
        'show_faq' => '1',
        'show_contact' => '1',
        'show_footer' => '1',
        'faq_1_q' => 'Are practice exams free?',
        'faq_1_a' => 'Active practice exams can be taken by registered students without requiring a subscription. Access rules are always controlled by the actual exam.',
        'faq_2_q' => 'When do I need a subscription?',
        'faq_2_a' => 'A subscription is required only for features or resources configured as subscription-only, such as protected study materials or eligible live exams.',
        'faq_3_q' => 'Are all live exams free?',
        'faq_3_a' => 'No. Each live exam can have its own access rules. The exam may require an active subscription, an exam fee, or provide free access.',
        'faq_4_q' => 'How are exam results calculated?',
        'faq_4_a' => 'After submission, eligible objective questions are evaluated by the examination system and the resulting score and performance data are saved.',
        'faq_5_q' => 'Can I review my previous attempts?',
        'faq_5_a' => 'Completed examination attempts and available results are stored in your student account so you can review your previous performance.',
        'faq_6_q' => 'How do study materials work?',
        'faq_6_a' => 'Published materials can be public or subscription-only. Access is checked by the student system before protected resources are delivered.',
    ];
}

function examsphere_ensure_settings_table(PDO $conn): void
{
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS website_settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(120) NOT NULL,
            setting_value TEXT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_website_settings_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function examsphere_load_settings(PDO $conn): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $defaults = examsphere_default_settings();
    $cache = $defaults;

    try {
        examsphere_ensure_settings_table($conn);
        $rows = $conn->query('SELECT setting_key, setting_value FROM website_settings')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $key = (string)($row['setting_key'] ?? '');
            if (array_key_exists($key, $defaults)) {
                $cache[$key] = (string)($row['setting_value'] ?? '');
            }
        }
    } catch (Throwable $exception) {
        error_log('ExamSphere website settings load failed: ' . $exception->getMessage());
    }

    return $cache;
}

function site_setting(PDO $conn, string $key, ?string $fallback = null): string
{
    $settings = examsphere_load_settings($conn);
    if (array_key_exists($key, $settings)) {
        return (string)$settings[$key];
    }
    return (string)($fallback ?? '');
}

function site_setting_bool(PDO $conn, string $key, bool $fallback = true): bool
{
    $value = site_setting($conn, $key, $fallback ? '1' : '0');
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
}

function site_setting_url(PDO $conn, string $key, string $fallback = ''): string
{
    $value = trim(site_setting($conn, $key, $fallback));
    if ($value === '') {
        return '';
    }

    if (str_starts_with($value, '#')) {
        return $value;
    }

    return $value;
}
