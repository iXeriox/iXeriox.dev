<?php
declare(strict_types=1);

$url = is_string($_GET['url'] ?? null) ? $_GET['url'] : '';
$parts = parse_url($url);
$valid = filter_var($url, FILTER_VALIDATE_URL) !== false
    && is_array($parts)
    && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
    && !empty($parts['host'])
    && !isset($parts['user']) && !isset($parts['pass']);
if (!$valid) http_response_code(400);
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
function escapeLinkText(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $valid ? 'Leaving iXeriox.dev' : 'Invalid link' ?></title>
    <style>
        :root{color-scheme:dark;font-family:Inter,"Segoe UI",system-ui,sans-serif;background:#06080d;color:#edf6f7}
        *{box-sizing:border-box}body{margin:0;min-height:100dvh;display:grid;place-items:center;padding:24px}
        main{width:min(540px,100%);padding:32px;border:1px solid #1d2a38;border-radius:18px;background:#0d121b}
        h1{font-size:25px;margin:0 0 16px}p{color:#a4afbf;line-height:1.6}
        .destination{padding:16px;border:1px solid #1d2a38;border-radius:10px;background:#080c13;overflow-wrap:anywhere}
        .destination strong{color:#36e0d0;font-size:18px}.destination p{font-size:12px;margin-bottom:0}
        nav{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}a{display:inline-block;padding:12px 16px;border:1px solid #1d2a38;border-radius:9px;color:#edf6f7;text-decoration:none;font-weight:600}
        .continue{background:#36e0d0;color:#061312;border-color:#36e0d0}a:focus-visible{outline:2px solid #ffad55;outline-offset:4px}
    </style>
</head>
<body>
<main>
<?php if ($valid): ?>
    <h1>You’re leaving iXeriox.dev</h1>
    <p>This link was shared in chat. Continue to the destination below?</p>
    <div class="destination">
        <strong><?= escapeLinkText((string) $parts['host']) ?></strong>
        <p><?= escapeLinkText($url) ?></p>
    </div>
    <nav aria-label="External link choices">
        <a class="continue" href="<?= escapeLinkText($url) ?>" rel="noopener noreferrer">Continue to site</a>
        <a href="/chat/">Return to chat</a>
    </nav>
<?php else: ?>
    <h1>This link isn’t valid</h1>
    <p>Only valid HTTP and HTTPS links can be opened.</p>
    <nav><a href="/chat/">Return to chat</a></nav>
<?php endif; ?>
</main>
</body>
</html>
