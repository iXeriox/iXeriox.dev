# eCrypt — private read-once chat

Standalone PHP/SQLite encrypted inbox at `/eCrypt/` matching the iXeriox.dev theme.

## Quick start

Requirements: PHP 8.1+, `pdo_sqlite`, HTTPS, and a directory writable by the PHP web-server account.

From the deployed website repository, run:

```bash
php eCrypt/setup.php
```

The setup script generates a random room access password (printed **once**), a stable encryption salt, and SQLite storage. It stores configuration in `../ecrypt-private/config.json` relative to the **website root** (specifically, two directories above `eCrypt/setup.php`). The folder is outside the public website tree. Do not commit or publish it.

**Permissions:** Run setup as the account that executes PHP for your website, or set secure ownership/permissions so that account can read `config.json` and write `messages.sqlite` and its parent directory. SQLite needs directory write access for its transaction journal. If you deploy with a repository nested in another folder, check that the derived directory really is outside your configured document root.

Visit `https://ixeriox.dev/eCrypt/`. Enter the generated access password, plus a **different**, strong encryption phrase shared privately with all participants. The encryption phrase is never sent to the server. Use two distinct browsers/devices to test sending and opening.

Running setup again does not overwrite existing credentials or messages. To use centrally managed credentials instead, you can set `ECRYPT_PASSWORD_HASH`, `ECRYPT_KDF_SALT` and `ECRYPT_DB_PATH` in your PHP server environment; those take priority over the generated config. Never change the salt after messages have been sent.

There is intentionally **no public first-visitor installer**: an internet-facing installation must not let a stranger claim ownership first.

## Behaviour and limitations

- Browser-side AES-256-GCM with PBKDF2-SHA256 (310,000 iterations).
- Text, photos, video, audio and voice messages, with a 6 MiB attachment upload limit.
- Messages are visible in other browser sessions; the inbox refreshes every 3.5 seconds.
- The first successful open request deletes the encrypted message from SQLite before delivering it. Decryption errors or interrupted playback cannot be undone.
- Undelivered messages expire after 24 hours on the next API request.
- This is a shared inbox, **not** user-to-user messaging or independently verified participant identity.

## Security

This is not a security-audited messenger. Use HTTPS, restrict direct origin access behind Cloudflare if relying on its HTTPS headers, and configure proxy/WAF rate-limiting for login. Avoid caching or backing up encrypted messages if strict ephemeral retention is needed. Anyone with both secrets can enter the room; distribute them securely. The app cannot prevent screenshots, recordings, recipient copies, or compromise of browser-served JavaScript.
