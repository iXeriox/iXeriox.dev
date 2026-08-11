<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$loginError = enforcePanelAccess();

if (!isAuthenticated()) {
    require __DIR__ . '/views/login.php';
    exit;
}

if (($_GET['api'] ?? '') === 'admin') {
    handleAdminApi();
}

if (($_GET['api'] ?? '') === 'stats') {
    jsonResponse(statPayload());
}

require __DIR__ . '/views/dashboard.php';
