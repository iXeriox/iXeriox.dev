<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

const DISCORD_DM_ENDPOINT =
    "https://api.infini9.net:4012/api/dm/375368296347729921";

const MESSAGE_RATE_LIMIT_MAX = 1;
const MESSAGE_RATE_LIMIT_WINDOW = 300; // 5 minutes

const PROJECT_RATE_LIMIT_MAX = 1;
const PROJECT_RATE_LIMIT_WINDOW = 600; // 10 minutes

const MAX_NAME_LENGTH = 80;
const MAX_CONTACT_LENGTH = 120;
const MAX_PROJECT_NAME_LENGTH = 120;
const MAX_DESCRIPTION_LENGTH = 4000;
const MAX_FEATURE_LENGTH = 100;
const MAX_FEATURES = 30;

const RATE_LIMIT_DIR = __DIR__ . "/data/contact-ratelimit";


function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


function jsonFail(string $error, int $status = 400): never
{
    jsonResponse([
        "success" => false,
        "error" => $error
    ], $status);
}


function validIpFromHeader(
    string $header,
    int $filterFlag
): ?string {
    $value = trim($_SERVER[$header] ?? "");

    if (
        $value !== "" &&
        filter_var(
            $value,
            FILTER_VALIDATE_IP,
            $filterFlag
        ) !== false
    ) {
        return $value;
    }

    return null;
}


function clientIps(): array
{
    /*
     * Standard Cloudflare visitor address.
     *
     * This may be IPv4 or IPv6 depending on how the visitor connected.
     * When Cloudflare Pseudo IPv4 overwrite mode is enabled, this can
     * contain Cloudflare's generated pseudo-IPv4 address.
     */
    $connectingIp = trim(
        $_SERVER["HTTP_CF_CONNECTING_IP"] ?? ""
    );

    $ipv4 = null;
    $ipv6 = null;

    if (
        filter_var(
            $connectingIp,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4
        ) !== false
    ) {
        $ipv4 = $connectingIp;
    }

    if (
        filter_var(
            $connectingIp,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV6
        ) !== false
    ) {
        $ipv6 = $connectingIp;
    }

    /*
     * Cloudflare provides CF-Connecting-IPv6 when Pseudo IPv4 overwrite
     * mode replaces CF-Connecting-IP with a generated IPv4 address.
     */
    $cloudflareIpv6 = validIpFromHeader(
        "HTTP_CF_CONNECTING_IPV6",
        FILTER_FLAG_IPV6
    );

    if ($cloudflareIpv6 !== null) {
        $ipv6 = $cloudflareIpv6;
    }

    /*
     * REMOTE_ADDR is retained separately because it should normally be a
     * Cloudflare edge address, not the visitor address.
     */
    $cloudflareEdge = trim(
        $_SERVER["REMOTE_ADDR"] ?? ""
    );

    if (
        filter_var(
            $cloudflareEdge,
            FILTER_VALIDATE_IP
        ) === false
    ) {
        $cloudflareEdge = "Unknown";
    }

    $primary = $connectingIp;

    if (
        filter_var(
            $primary,
            FILTER_VALIDATE_IP
        ) === false
    ) {
        $primary = $ipv6 ?? $ipv4 ?? "unknown";
    }

    return [
        "primary" => $primary,
        "ipv4" => $ipv4,
        "ipv6" => $ipv6,
        "cloudflareEdge" => $cloudflareEdge
    ];
}


function cleanText(string $value): string
{
    $value = trim($value);

    return preg_replace(
        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
        "",
        $value
    ) ?? "";
}


function textLength(string $value): int
{
    return function_exists("mb_strlen")
        ? mb_strlen($value, "UTF-8")
        : strlen($value);
}


function formatWaitTime(int $seconds): string
{
    $minutes = intdiv($seconds, 60);
    $remainingSeconds = $seconds % 60;

    if ($minutes > 0 && $remainingSeconds > 0) {
        return sprintf(
            "%d minute%s and %d second%s",
            $minutes,
            $minutes === 1 ? "" : "s",
            $remainingSeconds,
            $remainingSeconds === 1 ? "" : "s"
        );
    }

    if ($minutes > 0) {
        return sprintf(
            "%d minute%s",
            $minutes,
            $minutes === 1 ? "" : "s"
        );
    }

    return sprintf(
        "%d second%s",
        $remainingSeconds,
        $remainingSeconds === 1 ? "" : "s"
    );
}


function checkRateLimit(
    string $ip,
    string $formType,
    int $maximumHits,
    int $window
): array {
    if (!is_dir(RATE_LIMIT_DIR)) {
        if (
            !mkdir(RATE_LIMIT_DIR, 0750, true) &&
            !is_dir(RATE_LIMIT_DIR)
        ) {
            jsonFail("Unable to initialise rate limiting.", 500);
        }
    }

    /*
     * Including the form type means the contact-message and project-request
     * limits are tracked independently.
     */
    $identifier = hash("sha256", $formType . "|" . $ip);
    $file = RATE_LIMIT_DIR . "/" . $identifier . ".json";
    $now = time();

    $handle = fopen($file, "c+");

    if ($handle === false) {
        jsonFail("Unable to check rate limit.", 500);
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            jsonFail("Unable to check rate limit.", 500);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        $hits = [];

        if (is_string($contents) && $contents !== "") {
            $decoded = json_decode($contents, true);

            if (is_array($decoded)) {
                $hits = array_values(array_filter(
                    $decoded,
                    static fn($timestamp): bool =>
                        is_int($timestamp) &&
                        ($now - $timestamp) < $window
                ));
            }
        }

        if (count($hits) >= $maximumHits) {
            $oldestHit = min($hits);

            $retryAfter = max(
                1,
                $window - ($now - $oldestHit)
            );

            flock($handle, LOCK_UN);
            fclose($handle);

            return [
                "allowed" => false,
                "retryAfter" => $retryAfter
            ];
        }

        /*
         * Do not record the hit yet. It will only be committed after the
         * Discord message has been sent successfully.
         */
        flock($handle, LOCK_UN);
        fclose($handle);

        return [
            "allowed" => true,
            "retryAfter" => 0
        ];
    } catch (Throwable $error) {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        error_log(
            "Contact rate-limit error: " . $error->getMessage()
        );

        jsonFail("Unable to check rate limit.", 500);
    }
}


function recordRateLimitHit(
    string $ip,
    string $formType,
    int $window
): void {
    if (!is_dir(RATE_LIMIT_DIR)) {
        if (
            !mkdir(RATE_LIMIT_DIR, 0750, true) &&
            !is_dir(RATE_LIMIT_DIR)
        ) {
            throw new RuntimeException(
                "Unable to create rate-limit directory."
            );
        }
    }

    $identifier = hash("sha256", $formType . "|" . $ip);
    $file = RATE_LIMIT_DIR . "/" . $identifier . ".json";
    $now = time();

    $handle = fopen($file, "c+");

    if ($handle === false) {
        throw new RuntimeException(
            "Unable to open rate-limit file."
        );
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException(
                "Unable to lock rate-limit file."
            );
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        $hits = [];

        if (is_string($contents) && $contents !== "") {
            $decoded = json_decode($contents, true);

            if (is_array($decoded)) {
                $hits = array_values(array_filter(
                    $decoded,
                    static fn($timestamp): bool =>
                        is_int($timestamp) &&
                        ($now - $timestamp) < $window
                ));
            }
        }

        $hits[] = $now;

        rewind($handle);
        ftruncate($handle, 0);

        $encodedHits = json_encode($hits);

        if (
            $encodedHits === false ||
            fwrite($handle, $encodedHits) === false
        ) {
            throw new RuntimeException(
                "Unable to write rate-limit file."
            );
        }

        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    } catch (Throwable $error) {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        throw $error;
    }
}


function sendDiscordMessage(string $message): void
{
    if (!function_exists("curl_init")) {
        error_log("PHP cURL extension is not installed.");

        jsonFail(
            "Message service is not configured correctly.",
            500
        );
    }

    $payload = json_encode(
        ["message" => $message],
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

    if ($payload === false) {
        jsonFail("Unable to prepare your request.", 500);
    }

    $ch = curl_init(DISCORD_DM_ENDPOINT);

    if ($ch === false) {
        jsonFail("Unable to initialise message service.", 500);
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_POSTFIELDS => $payload
    ]);

    $result = curl_exec($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($result === false || $curlError !== "") {
        error_log(
            "Contact Discord cURL error: " . $curlError
        );

        jsonFail(
            "Message service unavailable. Please try again later.",
            502
        );
    }

    $decoded = json_decode($result, true);

    if (
        $statusCode < 200 ||
        $statusCode >= 300 ||
        !is_array($decoded) ||
        empty($decoded["success"])
    ) {
        error_log(
            "Contact Discord response: HTTP " .
            $statusCode .
            " " .
            $result
        );

        $errorMessage = "Failed to send your request.";

        if (
            is_array($decoded) &&
            isset($decoded["error"]) &&
            is_string($decoded["error"]) &&
            $decoded["error"] !== ""
        ) {
            $errorMessage = $decoded["error"];
        }

        jsonFail($errorMessage, 502);
    }
}


/*
|--------------------------------------------------------------------------
| Request handling
|--------------------------------------------------------------------------
*/

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    header("Allow: POST");
    jsonFail("Method not allowed.", 405);
}

$rawBody = file_get_contents("php://input");

if ($rawBody === false || trim($rawBody) === "") {
    jsonFail("Request body is empty.");
}

$data = json_decode($rawBody, true);

if (!is_array($data)) {
    jsonFail("Invalid JSON request body.");
}

$formType = cleanText((string)($data["formType"] ?? ""));
$name = cleanText((string)($data["name"] ?? ""));
$email = cleanText((string)($data["email"] ?? ""));

if ($formType === "") {
    jsonFail("Form type was not provided.");
}

if ($name === "" || $email === "") {
    jsonFail("Please provide your name and contact details.");
}

if (
    textLength($name) > MAX_NAME_LENGTH ||
    textLength($email) > MAX_CONTACT_LENGTH
) {
    jsonFail("Your name or contact details are too long.");
}

$clientIps = clientIps();

/*
 * Use all available visitor addresses in the rate-limit identifier.
 * Do not use the Cloudflare edge address because many visitors share it.
 */
$clientIp = implode("|", array_filter([
    $clientIps["ipv4"],
    $clientIps["ipv6"],
    $clientIps["primary"]
]));
$message = "";
$rateLimitMaximum = 1;
$rateLimitWindow = 0;

if ($formType === "message") {
    $contactMessage = cleanText(
        (string)($data["message"] ?? "")
    );

    if ($contactMessage === "") {
        jsonFail("Please enter a message.");
    }

    if (textLength($contactMessage) > MAX_DESCRIPTION_LENGTH) {
        jsonFail(
            "Your message must be " .
            MAX_DESCRIPTION_LENGTH .
            " characters or fewer."
        );
    }

    $message =
        "New message from iXeriox.dev\n\n" .
        "Name: {$name}\n" .
        "Contact: {$email}\n" .
"Visitor IPv4: " .
    ($clientIps["ipv4"] ?? "Not available") .
"\n" .
"Visitor IPv6: " .
    ($clientIps["ipv6"] ?? "Not available") .
"\n" .
"Cloudflare connecting IP: " .
    $clientIps["primary"] .
"\n" .
"Cloudflare edge IP: " .
    $clientIps["cloudflareEdge"] .
"\n" .
        "Message:\n{$contactMessage}";

    $rateLimitMaximum = MESSAGE_RATE_LIMIT_MAX;
    $rateLimitWindow = MESSAGE_RATE_LIMIT_WINDOW;
} elseif ($formType === "project") {
    $projectName = cleanText(
        (string)($data["projectName"] ?? "")
    );

    $projectType = cleanText(
        (string)($data["projectType"] ?? "")
    );

    $budget = cleanText(
        (string)($data["budget"] ?? "")
    );

    $timeline = cleanText(
        (string)($data["timeline"] ?? "")
    );

    $description = cleanText(
        (string)($data["description"] ?? "")
    );

    $estimatedPrice = cleanText(
        (string)($data["estimatedPrice"] ?? "")
    );

    $selectedFeatures = $data["selectedFeatures"] ?? [];

    if (!is_array($selectedFeatures)) {
        $selectedFeatures = [];
    }

    $selectedFeatures = array_slice(
        array_values(array_filter(array_map(
            static function ($feature): string {
                $feature = cleanText((string)$feature);

                if (
                    textLength($feature) >
                    MAX_FEATURE_LENGTH
                ) {
                    return "";
                }

                return $feature;
            },
            $selectedFeatures
        ))),
        0,
        MAX_FEATURES
    );

    if (
        $projectName === "" ||
        $projectType === "" ||
        $description === ""
    ) {
        jsonFail(
            "Please complete all required project details."
        );
    }

    if (
        textLength($projectName) >
        MAX_PROJECT_NAME_LENGTH
    ) {
        jsonFail(
            "The project name must be " .
            MAX_PROJECT_NAME_LENGTH .
            " characters or fewer."
        );
    }

    if (
        textLength($description) >
        MAX_DESCRIPTION_LENGTH
    ) {
        jsonFail(
            "The project description must be " .
            MAX_DESCRIPTION_LENGTH .
            " characters or fewer."
        );
    }

    $featureText = count($selectedFeatures) > 0
        ? implode(", ", $selectedFeatures)
        : "None selected";

    $message =
        "New project request from iXeriox.dev\n\n" .
        "Name: {$name}\n" .
        "Contact: {$email}\n" .
"Visitor IPv4: " .
    ($clientIps["ipv4"] ?? "Not available") .
"\n" .
"Visitor IPv6: " .
    ($clientIps["ipv6"] ?? "Not available") .
"\n" .
"Cloudflare connecting IP: " .
    $clientIps["primary"] .
"\n" .
"Cloudflare edge IP: " .
    $clientIps["cloudflareEdge"] .
"\n" .
        "Project: {$projectName}\n" .
        "Project type: {$projectType}\n" .
        "Budget: " .
            ($budget !== "" ? $budget : "Not specified") .
        "\n" .
        "Timeline: " .
            ($timeline !== "" ? $timeline : "Not specified") .
        "\n" .
        "Features: {$featureText}\n" .
        "Displayed estimate: " .
            (
                $estimatedPrice !== ""
                    ? $estimatedPrice
                    : "Not calculated"
            ) .
        "\n\n" .
        "Description:\n{$description}";

    $rateLimitMaximum = PROJECT_RATE_LIMIT_MAX;
    $rateLimitWindow = PROJECT_RATE_LIMIT_WINDOW;
} else {
    jsonFail("Invalid form type.");
}


/*
|--------------------------------------------------------------------------
| Rate limit and delivery
|--------------------------------------------------------------------------
*/

$rateLimit = checkRateLimit(
    $clientIp,
    $formType,
    $rateLimitMaximum,
    $rateLimitWindow
);

if (!$rateLimit["allowed"]) {
    $requestName = $formType === "project"
        ? "project requests"
        : "messages";

    jsonFail(
        "Too many {$requestName}. Please try again in " .
        formatWaitTime((int)$rateLimit["retryAfter"]) .
        ".",
        429
    );
}

sendDiscordMessage($message);

try {
    recordRateLimitHit(
        $clientIp,
        $formType,
        $rateLimitWindow
    );
} catch (Throwable $error) {
    /*
     * The Discord message has already been delivered, so do not tell the
     * visitor that submission failed solely because rate-limit recording
     * encountered a problem.
     */
    error_log(
        "Unable to record contact rate-limit hit: " .
        $error->getMessage()
    );
}

jsonResponse([
    "success" => true,
    "formType" => $formType
]);