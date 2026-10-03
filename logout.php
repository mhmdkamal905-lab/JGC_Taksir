<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

logoutUser();

header(
    'Location: login.php?logout=1'
);

exit;