<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::connection();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

switch ($method) {

    // ------------------------------------------------------------
    // GET /api/users.php            -> list all members (admin/moderator)
    // GET /api/users.php?id=5       -> single member (admin/moderator)
    // ------------------------------------------------------------
    case 'GET':
        Auth::requireRole('moderator');

        if ($id) {
            $stmt = $db->prepare(
                'SELECT id, username, email, display_name, avatar_url, role, status, created_at, last_login_at
                 FROM users WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) {
                Response::error('User not found.', 404);
            }
            Response::success(['user' => $user]);
        }

        $search = trim((string) ($_GET['q'] ?? ''));
        $params = [];
        $sql = 'SELECT id, username, email, display_name, avatar_url, role, status, created_at, last_login_at
                FROM users';
        if ($search !== '') {
            $sql .= ' WHERE username LIKE ? OR email LIKE ? OR display_name LIKE ?';
            $like = "%$search%";
            $params = [$like, $like, $like];
        }
        $sql .= ' ORDER BY created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        Response::success(['users' => $stmt->fetchAll()]);
        break;

    // ------------------------------------------------------------
    // PUT /api/users.php?id=5   (admin only)
    // Body may include: role, status, display_name
    // ------------------------------------------------------------
    case 'PUT':
        $currentUser = Auth::requireRole('moderator');

        if (!$id) {
            Response::error('A user id is required.', 422);
        }

        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            Response::error('User not found.', 404);
        }

        $body = requestBody();
        $updates = [];
        $params = [];

        if (isset($body['role'])) {
            if ($currentUser['role'] !== 'admin') Response::error('Only administrators can change roles.', 403);
            if (!in_array($body['role'], ['user', 'moderator', 'admin'], true)) {
                Response::error('Invalid role.', 422);
            }
            if ((int) $target['id'] === (int) $currentUser['id'] && $body['role'] !== 'admin') {
                Response::error('You cannot demote your own account.', 400);
            }
            $updates[] = 'role = ?';
            $params[] = $body['role'];
        }

        if (isset($body['status'])) {
            if (!in_array($body['status'], ['active', 'suspended'], true)) {
                Response::error('Invalid status.', 422);
            }
            if ((int) $target['id'] === (int) $currentUser['id'] && $body['status'] === 'suspended') {
                Response::error('You cannot suspend your own account.', 400);
            }
            $updates[] = 'status = ?';
            $params[] = $body['status'];
        }

        if (isset($body['display_name']) && trim((string) $body['display_name']) !== '') {
            if ($currentUser['role'] !== 'admin') Response::error('Only administrators can edit profiles.', 403);
            $updates[] = 'display_name = ?';
            $params[] = trim((string) $body['display_name']);
        }

        if (empty($updates)) {
            Response::error('Nothing to update.', 422);
        }

        $params[] = $id;
        $db->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);

        $stmt = $db->prepare(
            'SELECT id, username, email, display_name, avatar_url, role, status, created_at, last_login_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        Response::success(['user' => $stmt->fetch()], 'Member updated.');
        break;

    // ------------------------------------------------------------
    // DELETE /api/users.php?id=5   (admin only)
    // ------------------------------------------------------------
    case 'DELETE':
        $currentUser = Auth::requireRole('admin');

        if (!$id) {
            Response::error('A user id is required.', 422);
        }
        if ((int) $id === (int) $currentUser['id']) {
            Response::error('You cannot delete your own account.', 400);
        }

        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            Response::error('User not found.', 404);
        }
        Response::success([], 'Member removed.');
        break;

    default:
        Response::error('Method not allowed.', 405);
}
