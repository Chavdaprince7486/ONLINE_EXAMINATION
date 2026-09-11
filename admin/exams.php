<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ExamSphere - Exam Management Entry Point
|--------------------------------------------------------------------------
|
| The canonical Exam Management interface is:
|
| /admin/exams/index.php
|
| This file remains as the existing sidebar entry point and redirects
| administrators to the canonical interface.
|
|--------------------------------------------------------------------------
*/

require_once '../config/session.php';

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header(
        'Location: ../auth/login.php'
    );

    exit;
}

header(
    'Location: exams/index.php'
);

exit;