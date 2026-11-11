<?php
declare(strict_types=1);

/**
 * One-command first-run installer. Execute from a terminal:
 *   php eCrypt/setup.php
 *
 * Never exposes a setup endpoint on the public web.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (PHP_VERSION_ID < 80100 || !extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "Requires PHP 8.1+ and the pdo_sqlite extension.\n");
    exit(1);
}

$private = dirname(__DIR__, 2) . '/ecrypt-private';
$config = $private . '/config.json';
if (!is_dir($private) && !mkdir($private, 0700, true) && !is_dir($private)) {
    fwrite(STDERR, "Cannot create $private. Create it and allow the PHP web-server account to write there.\n");
    exit(1);
}
@chmod($private, 0700);
if (file_exists($config)) {
    fwrite(STDERR, "eCrypt already initialised at $config. Existing credentials and messages have not been changed.\n");
    exit(0);
}
$password = bin2hex(random_bytes(16));
$salt = base64_encode(random_bytes(16));
$dbPath = $private . '/messages.sqlite';
$settings = [
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'salt' => $salt,
    'db_path' => $dbPath,
];
try {
    $db = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('CREATE TABLE IF NOT EXISTS messages (
        id TEXT PRIMARY KEY, sender TEXT NOT NULL, sender_session TEXT NOT NULL,
        kind TEXT NOT NULL, ciphertext TEXT NOT NULL, iv TEXT NOT NULL,
        created INTEGER NOT NULL, expires INTEGER NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS messages_expires ON messages(expires)');
    $db = null;
    @chmod($dbPath, 0600);
    $payload = json_encode($settings, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $h = fopen($config, 'x');
    if ($h === false) throw new RuntimeException('Cannot create configuration');
    @chmod($config, 0600);
    if (fwrite($h, $payload . "\n") !== strlen($payload) + 1) {
        fclose($h);
        @unlink($config);
        throw new RuntimeException('Incomplete configuration write');
    }
    fclose($h);
} catch (Throwable $e) {
    fwrite(STDERR, "Installation failed: " . $e->getMessage() . "\n");
    exit(1);
}
echo "eCrypt configured.\nAccess password (shown only once): $password\n";
echo "Use a DIFFERENT shared encryption phrase with recipients.\n";
echo "Storage: $private\n";
echo "IMPORTANT: The PHP web-server user must be able to read config.json and write messages.sqlite + its containing directory.\n";
