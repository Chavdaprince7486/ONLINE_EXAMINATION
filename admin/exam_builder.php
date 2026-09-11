<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ExamSphere - Legacy Exam Builder Compatibility Page
|--------------------------------------------------------------------------
|
| The project now uses admin/exams.php as the canonical exam-management
| workflow.
|
| This file is intentionally kept so older bookmarks, links, or external
| references to exam_builder.php continue working.
|
| It prevents the old builder from creating incomplete exams that do not
| contain required_question_count.
|
|--------------------------------------------------------------------------
*/

require_once '../config/session.php';
require_once '../config/config.php';


/*
|--------------------------------------------------------------------------
| ADMIN AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {

    header(
        'Location: ../auth/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| REDIRECT TO CANONICAL EXAM MANAGEMENT
|--------------------------------------------------------------------------
*/

header(
    'Location: exams.php'
);

exit;