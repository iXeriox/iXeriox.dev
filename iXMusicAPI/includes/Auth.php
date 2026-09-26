<?php

/**
 * Handles the current request's authentication state and permission checks.
 *
 * Permission levels (lowest -> highest): user < moderator < admin
 */
class Auth
{
    private static ?array $currentUser = null;
    private static bool $resolved = false;

    private const LEVELS = [
        'user'      => 1,
        'moderator' => 2,
        'admin'     => 3,
    ];

    private static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? apache_request_headers()['Authorization']
            ?? '';

        if (preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /** Resolves + caches the authenticated user for this request, or null. */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$currentUser;
        }
        self::$resolved = true;

        $token = self::bearerToken();
        if (!$token) {
            return null;
        }

        $config = require __DIR__ . '/../config.php';
        $payload = JWT::decode($token, $config['jwt_secret']);
        if (!$payload || empty($payload['sub'])) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, username, email, display_name, avatar_url, role, status, created_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$payload['sub']]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active') {
            return null;
        }

        self::$currentUser = $user;
        return $user;
    }

    /** Aborts the request with 401 unless a valid token was supplied. */
    public static function requireAuth(): array
    {
        $user = self::user();
        if (!$user) {
            Response::error('Authentication required.', 401);
        }
        return $user;
    }

    /**
     * Aborts with 403 unless the current user's role meets or exceeds
     * the given minimum role level (e.g. requireRole('moderator') also
     * allows admins through).
     */
    public static function requireRole(string $minimumRole): array
    {
        $user = self::requireAuth();

        $userLevel = self::LEVELS[$user['role']] ?? 0;
        $requiredLevel = self::LEVELS[$minimumRole] ?? 999;

        if ($userLevel < $requiredLevel) {
            Response::error('You do not have permission to perform this action.', 403);
        }

        return $user;
    }

    public static function issueToken(array $user): string
    {
        $config = require __DIR__ . '/../config.php';
        return JWT::encode(['sub' => $user['id']], $config['jwt_secret'], $config['jwt_ttl']);
    }
}
