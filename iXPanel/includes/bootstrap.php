<?php

declare(strict_types=1);

const IXPANEL_MAX_PEN_SIZE = 512000;

$documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2)), "/\\");

define('IXPANEL_DATA_DIR', $documentRoot . DIRECTORY_SEPARATOR . 'data');
define('IXPANEL_PENS_DIR', IXPANEL_DATA_DIR . DIRECTORY_SEPARATOR . 'pens');
define('IXPANEL_AUTH_FILE', IXPANEL_DATA_DIR . DIRECTORY_SEPARATOR . 'ixpanel-auth.json');
define('IXPANEL_SITE_INFO_FILE', IXPANEL_DATA_DIR . DIRECTORY_SEPARATOR . 'siteInfo.json');

require __DIR__ . '/http.php';

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure' => isHttpsRequest(),
    'use_strict_mode' => true,
]);

require __DIR__ . '/storage.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/stats.php';
