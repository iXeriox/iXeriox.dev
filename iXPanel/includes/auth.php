<?php

declare(strict_types=1);

function authRecord(): array
{
    ensureDataDirectories();

    if (!is_file(IXPANEL_AUTH_FILE)) {
        $initialPassword = getenv('IXPANEL_INITIAL_PASSWORD') ?: 'admin';
        $initialIp = getenv('IXPANEL_ALLOWED_IP') ?: '127.0.0.1';
        if (!filter_var($initialIp, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('IXPANEL_ALLOWED_IP is not a valid IP address.');
        }

        $hash = password_hash($initialPassword, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new RuntimeException('PHP could not create the initial password hash.');
        }

        atomicJsonWrite(IXPANEL_AUTH_FILE, [
            'username' => 'admin',
            'passwordHash' => $hash,
            'mustChangePassword' => true,
            'allowedIp' => $initialIp,
            'createdAt' => gmdate('c'),
        ]);
    }

    $auth = readJsonFile(IXPANEL_AUTH_FILE);
    if (
        !isset($auth['username'], $auth['passwordHash'])
        || !is_string($auth['username'])
        || !is_string($auth['passwordHash'])
        || $auth['username'] === ''
        || $auth['passwordHash'] === ''
    ) {
        throw new RuntimeException('ixpanel-auth.json is missing a valid username or passwordHash.');
    }

    return $auth;
}

function isAuthenticated(): bool
{
    return ($_SESSION['ixpanel_authenticated'] ?? false) === true;
}

function csrfToken(): string
{
    if (empty($_SESSION['ixpanel_csrf'])) {
        $_SESSION['ixpanel_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['ixpanel_csrf'];
}

function enforcePanelAccess(): string
{
    try {
        $auth = authRecord();
    } catch (Throwable $error) {
        error_log('[iXPanel Bootstrap] ' . $error->getMessage());
        http_response_code(500);
        require dirname(__DIR__) . '/views/storage-error.php';
        exit;
    }

    $allowedIp = filter_var($auth['allowedIp'] ?? '', FILTER_VALIDATE_IP)
        ? (string) $auth['allowedIp']
        : '';

    if ($allowedIp === '') {
        error_log('[iXPanel Bootstrap] ixpanel-auth.json is missing a valid allowedIp.');
        http_response_code(500);
        require dirname(__DIR__) . '/views/storage-error.php';
        exit;
    }
    if (isset($_GET['setIp']) && $_GET['setIp'] === 'true') {
        $token = (string) ($_GET['token'] ?? '');
        $expectedToken = (string) (getenv('IXPANEL_IP_UPDATE_TOKEN') ?: '');

//         if ($expectedToken === '' || !hash_equals($expectedToken, $token)) {
//             error_log('[iXPanel Access] Rejected IP-update request from ' . clientIp());
//             http_response_code(403);
//             exit('Invalid IP update token.');
//         }

        $visitorIp = clientIp();

        if (!filter_var($visitorIp, FILTER_VALIDATE_IP)) {
            error_log('[iXPanel Access] Could not determine visitor IP for update.');
            http_response_code(400);
            exit('Could not determine a valid visitor IP.');
        }

        $previousIp = $allowedIp;
        $auth['allowedIp'] = $visitorIp;
        $auth['updatedAt'] = gmdate('c');

        atomicJsonWrite(IXPANEL_AUTH_FILE, $auth);

        error_log(
            '[iXPanel Access] Allowed IP updated from '
            . $previousIp . ' to ' . $visitorIp
        );

        header('Content-Type: text/plain; charset=utf-8');
        echo "Allowed IP updated to {$visitorIp}.";
        exit;
    }

if (!hash_equals($allowedIp, clientIp())) {
    error_log(
        '[iXPanel Access] expected=' . $allowedIp
        . ' detected=' . clientIp()
        . ' remote=' . ($_SERVER['REMOTE_ADDR'] ?? '')
        . ' cloudflare=' . ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '')
    );
        http_response_code(403);
        require dirname(__DIR__) . '/views/access-denied.php';
        exit;
    }

    if (isset($_GET['logout'])) {
        logoutPanel();
    }

    if (isAuthenticated() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !isset($_POST['ixpanel_login'])) {
        return '';
    }

    $usernameOk = hash_equals((string) $auth['username'], (string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordOk = password_verify($password, (string) $auth['passwordHash']);

    if (!$usernameOk || !$passwordOk) {
        usleep(350000);
        return 'The administrator credentials were not accepted.';
    }

    if (password_needs_rehash((string) $auth['passwordHash'], PASSWORD_DEFAULT)) {
        $auth['passwordHash'] = password_hash($password, PASSWORD_DEFAULT);
        $auth['updatedAt'] = gmdate('c');
        atomicJsonWrite(IXPANEL_AUTH_FILE, $auth);
    }

    session_regenerate_id(true);
    $_SESSION['ixpanel_authenticated'] = true;
    $_SESSION['ixpanel_csrf'] = bin2hex(random_bytes(32));
    header('Location: ' . currentPanelUrl());
    exit;
}

function logoutPanel(): never
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parameters['path'],
            $parameters['domain'],
            $parameters['secure'],
            $parameters['httponly']
        );
    }

    session_destroy();
    header('Location: ' . currentPanelUrl());
    exit;
}
