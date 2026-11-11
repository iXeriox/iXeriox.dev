# eCrypt — individual encrypted one-time messages

eCrypt is a standalone PHP 8.1+/SQLite messaging application at `/eCrypt/`, with separate per-user passwords and browser-side encryption phrases.

## Initial deployment

Requires PHP 8.1+, PDO SQLite, HTTPS and writable private storage. From the deployed repository:

```bash
php eCrypt/setup.php
```

The script initializes `/iXeriox.dev/ecrypt-private/` for a deployment rooted at `/iXeriox.dev/www`. Ensure the PHP worker can read `config.json` and write `messages.sqlite` and its parent directory. Never publish the private folder.

**The password printed by setup is the room invitation password**, not any user's access password. Share the invitation password only with approved new users. If it has been disclosed, rotate the password hash in the private config before giving it to new users. Existing user access passwords are independent of this invite.

## Each user's registration and login

1. Select **Create account**. Enter a unique username, a personal access password (12+ characters), a different personal encryption phrase (12+ characters), and the room invitation password.
2. The browser generates a new RSA-OAEP keypair. The private key is encrypted locally using a key derived from that person's phrase (PBKDF2-SHA256 with 310,000 iterations and a per-user random salt). The server saves only the public key, encrypted private key, and password hash.
3. For later logins select **Log in**, enter the same username, their access password, and their own encryption phrase. The browser decrypts their key locally. Other users have different credentials and phrases.
4. Enter another registered username in **Send to**, and send text/media/voice. The sender generates a random AES-256-GCM message key and encrypts it to the recipient's RSA public key. The recipient decrypts with their own private key. Users never need one another's phrases.

If a user forgets their encryption phrase, messages encrypted to their existing private key cannot be recovered. **Do not change or delete their stored private key or encryption salt.**

The original shared-room inbox messages are not transferred to individual accounts; the legacy `messages` table is intentionally retained to prevent accidental destruction, but old messages are not displayed by the new per-user inbox.

## Notes

- Each message is delivered to one named recipient. The first successful open API call atomically removes ciphertext from SQLite **before decryption**.
- Undelivered messages expire after 24 hours on the next API use.
- Max file attachment: 6 MiB. Recipients must have an account first.
- For safety, the registration invitation is required: the first public visitor cannot take ownership of an uninitialised server.
- Keep Cloudflare/WAF IP-based login and registration rate limits; per-session throttling alone is insufficient. Use HTTPS and restrict origin reachability when trusting proxy-provided HTTPS headers.
- This is **not a security-audited system**. The server supplies public keys and browser JavaScript, so a malicious or compromised server can impersonate key owners or alter the client. There is no cryptographic identity verification, key transparency or forward secrecy. Avoid using it for high-risk secrets. Read-once does not prevent recipients from copying, recording or retaining plaintext.
