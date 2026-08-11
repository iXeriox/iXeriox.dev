<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>iXPanel Login</title>
    <link rel="stylesheet" href="assets/ixpanel.css">
</head>
<body class="auth-page">
    <form class="login" method="post">
        <a class="login-logo" href="/" aria-label="Return to iXeriox.dev">iXeriox<span>.dev/iXPanel</span></a>
        <h1>Administrator access</h1>
        <p>Authenticate to manage site intelligence and stored pens.</p>

        <?php if ($loginError !== ''): ?>
            <div class="login-error"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <label for="username">USERNAME</label>
        <input id="username" name="username" autocomplete="username" required autofocus>

        <label for="password">PASSWORD</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <button name="ixpanel_login" value="1">Enter iXPanel</button>
    </form>
</body>
</html>
