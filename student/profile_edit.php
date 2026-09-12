<?php

declare(strict_types=1);

require_once '../config/session.php';
require_once '../config/config.php';
require_once '../config/auth.php';

require_role('student');

header(
    'Location: profile.php#personalSection'
);

exit;