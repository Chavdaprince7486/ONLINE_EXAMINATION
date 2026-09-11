<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

require_login();

$role =
    current_user_role();

header(
    'Location: ' .
    dashboard_url_for_role(
        $role
    )
);

exit;
