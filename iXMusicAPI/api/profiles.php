<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::connection();
Auth::requireAuth();
$id = (int)($_GET['id'] ?? 0);
if (!$id) Response::error('A profile id is required.', 422);
$stmt = $db->prepare('SELECT id, username, display_name, avatar_url, created_at FROM users WHERE id = ? AND status = "active"');
$stmt->execute([$id]); $profile = $stmt->fetch();
if (!$profile) Response::error('Profile not found.', 404);
$stmt = $db->prepare('SELECT p.*, (SELECT COUNT(*) FROM playlist_tracks WHERE playlist_id=p.id) track_count FROM playlists p WHERE p.user_id=? AND p.is_public=1 ORDER BY p.updated_at DESC');
$stmt->execute([$id]);
Response::success(['profile' => $profile, 'playlists' => $stmt->fetchAll()]);
