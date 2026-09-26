<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak errors as HTML into a JSON API

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/Auth.php';

set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage());
    Response::error('Something went wrong on the server.', 500);
});

$config = require __DIR__ . '/../config.php';

// ---- CORS -----------------------------------------------------------
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $config['cors_allowed_origins'], true)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---- Parsed JSON request body, available to every endpoint ----------
function requestBody(): array
{
    static $parsed = null;
    if ($parsed === null) {
        $raw = file_get_contents('php://input');
        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) {
            $parsed = [];
        }
    }
    return $parsed;
}

/** Fetch a required field from the parsed JSON body, or abort with 422. */
function requireField(string $key)
{
    $body = requestBody();
    if (!isset($body[$key]) || $body[$key] === '') {
        Response::error("The field '$key' is required.", 422);
    }
    return $body[$key];
}
