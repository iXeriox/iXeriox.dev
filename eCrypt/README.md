# eCrypt — private read-once chat

A standalone PHP/SQLite encrypted inbox at `/eCrypt/`, visually matched to the iXeriox.dev dark terminal theme.

## Server setup (required)

1. Ensure PHP 8.1+, PDO SQLite, HTTPS and a writable directory **outside** your public document root.
2. Generate an access-password hash on your server (do **not** commit the password or hash):
   ```bash
   php -r 'echo password_hash(readline("Access password: "), PASSWORD_DEFAULT), PHP_EOL;'
   ```
3. Generate a random stable encryption salt (keep this **unchanged** after users begin sending messages):
   ```bash
   php -r 'echo base64_encode(random_bytes(16)), PHP_EOL;'
   ```
4. Configure these server-side environment variables in Apache/PHP-FPM or a protected server configuration:
   ```
   ECRYPT_PASSWORD_HASH=<hash from step 2>
   ECRYPT_KDF_SALT=<base64 from step 3>
   ECRYPT_DB_PATH=/var/lib/ecrypt/messages.sqlite
   ```
   Ensure `/var/lib/ecrypt` exists and the web-server user can write it. Never place the DB under the served website, and never put credentials into Git.
5. Deploy `eCrypt/` under your existing site and visit `https://ixeriox.dev/eCrypt/`. Confirm HTTPS is correctly detected by PHP. For proxy deployments, only trust Cloudflare headers if your origin is reachable *exclusively* through Cloudflare.
6. Share the access password and a **different** strong encryption phrase privately with the intended participants. The encryption phrase is used only in the browser. Anyone who has both secrets can participate in the room. Use separate browsers/devices or separate browser sessions for sender and recipient.

## Behaviour

- AES-256-GCM encryption in the browser, with a key derived using PBKDF2-SHA256 (310k iterations).
- Text, audio, images, videos and voice notes. Mobile-friendly touch controls, 16px inputs to avoid iOS zoom, and separate photo/video/audio pickers. Attachments up to 6 MiB (limited for encrypted JSON/base64 overhead and mobile reliability).
- Messages are only visible to other browser sessions. Refresh the recipient inbox to see messages (auto refresh every 3.5 seconds).
- One-time retrieval is enforced with a SQLite write transaction: the first successful open request deletes the ciphertext before responding.
- Undelivered messages expire after 24 hours **when the API is next used**. They are not immediately removed at the exact expiration time.
- No user accounts: this is a **shared inbox** with names entered by users, not user-to-user private messaging.
- A second participant must know the encryption phrase. An incorrect phrase causes an opened message to be irretrievably consumed.
- A message is removed server-side on opening, not after confirmed viewing or full media playback.

## Security notes

This is an initial implementation, **not a security-audited messenger**. Do not depend on it for high-risk secrets until it has had proper testing and review. A compromised server can alter the JavaScript served to browsers. In particular, it does not provide cryptographic participant identity verification or forward secrecy. Shared access and encryption secrets must be securely distributed and rotated if compromised.

The app cannot prevent screenshots, screen recordings, browser caches, copies made by recipients, logs held by infrastructure, or database/backups created before deletion. Configure your infrastructure to avoid caching responses under `/eCrypt/`, and prevent database backups from retaining messages if strict ephemeral retention is required. The server gets the room access password at login but **does not** receive the separate encryption phrase. The access password is verified with a server-side password hash.

Authentication brute-force controls are session-local. For internet-facing deployments add Cloudflare/WAF or reverse-proxy IP-based rate limiting at `/eCrypt/index.php?action=login`, ensure TLS termination is secure, and test the access-control and message-delete paths before production use.
