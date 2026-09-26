<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::connection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'settings';

if ($action === 'settings' && $method === 'GET') {
    Auth::requireAuth();
    $rows = $db->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll();
    Response::success(['settings' => array_column($rows, 'setting_value', 'setting_key')]);
}
if ($action === 'settings' && $method === 'PUT') {
    Auth::requireRole('admin');
    $allowed = ['accent_color', 'accent_secondary'];
    $stmt = $db->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach (requestBody() as $key => $value) if (in_array($key, $allowed, true) && preg_match('/^#[0-9a-f]{6}$/i', (string)$value)) $stmt->execute([$key, $value]);
    Response::success([], 'Appearance updated.');
}
if ($action === 'blocked' && $method === 'GET') {
    Auth::requireRole('moderator');
    Response::success(['blocked' => $db->query('SELECT * FROM blocked_videos ORDER BY created_at DESC')->fetchAll()]);
}
if ($action === 'blocked' && $method === 'POST') {
    $user = Auth::requireRole('moderator');
    $db->prepare('INSERT INTO blocked_videos (youtube_video_id, reason, blocked_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason), blocked_by = VALUES(blocked_by)')
       ->execute([(string)requireField('youtube_video_id'), trim((string)(requestBody()['reason'] ?? '')) ?: 'This track is unavailable on iXMusic.', $user['id']]);
    Response::success([], 'Track hidden from discovery.', 201);
}
if ($action === 'blocked' && $method === 'DELETE') {
    Auth::requireRole('moderator');
    $db->prepare('DELETE FROM blocked_videos WHERE youtube_video_id = ?')->execute([(string)($_GET['video_id'] ?? '')]);
    Response::success([], 'Track restored.');
}
Response::error('Unknown request.', 404);
