<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::connection();
$action = $_GET['action'] ?? 'liked';

switch (true) {

    // ------------------------------------------------------------
    // GET /api/tracks.php?action=liked        -> current user's liked tracks
    // GET /api/tracks.php?action=history       -> recently played
    // ------------------------------------------------------------
    case $method === 'GET' && $action === 'liked':
        $user = Auth::requireAuth();
        $stmt = $db->prepare(
            'SELECT t.id, t.youtube_video_id, t.title, t.artist, t.thumbnail_url, t.duration_seconds, lt.liked_at
             FROM liked_tracks lt JOIN tracks t ON t.id = lt.track_id
             WHERE lt.user_id = ? ORDER BY lt.liked_at DESC'
        );
        $stmt->execute([$user['id']]);
        Response::success(['tracks' => $stmt->fetchAll()]);
        break;

    case $method === 'GET' && $action === 'history':
        $user = Auth::requireAuth();
        $stmt = $db->prepare(
            'SELECT t.id, t.youtube_video_id, t.title, t.artist, t.thumbnail_url, t.duration_seconds,
                    MAX(ph.played_at) AS played_at
             FROM play_history ph JOIN tracks t ON t.id = ph.track_id
             WHERE ph.user_id = ? GROUP BY t.id ORDER BY played_at DESC LIMIT 50'
        );
        $stmt->execute([$user['id']]);
        Response::success(['tracks' => $stmt->fetchAll()]);
        break;

    // ------------------------------------------------------------
    // POST /api/tracks.php?action=like     body: { track } (track object from search or a track_id)
    // POST /api/tracks.php?action=play     body: { track_id }  -> logs a play_history row
    // ------------------------------------------------------------
    case $method === 'POST' && $action === 'like':
        $user = Auth::requireAuth();
        $trackId = resolveOrCreateTrack($db, requestBody(), $user['id']);

        $stmt = $db->prepare('INSERT IGNORE INTO liked_tracks (user_id, track_id) VALUES (?, ?)');
        $stmt->execute([$user['id'], $trackId]);

        Response::success(['track_id' => $trackId], 'Added to Liked Songs.', 201);
        break;

    case $method === 'POST' && $action === 'play':
        $user = Auth::requireAuth();
        $trackId = resolveOrCreateTrack($db, requestBody(), $user['id']);

        $db->prepare('INSERT INTO play_history (user_id, track_id) VALUES (?, ?)')->execute([$user['id'], $trackId]);
        Response::success([], 'Play recorded.', 201);
        break;

    // ------------------------------------------------------------
    // DELETE /api/tracks.php?action=unlike&track_id=9
    // ------------------------------------------------------------
    case $method === 'DELETE' && $action === 'unlike':
        $user = Auth::requireAuth();
        $trackId = isset($_GET['track_id']) ? (int) $_GET['track_id'] : 0;
        if (!$trackId) {
            Response::error('A track_id is required.', 422);
        }
        $db->prepare('DELETE FROM liked_tracks WHERE user_id = ? AND track_id = ?')->execute([$user['id'], $trackId]);
        Response::success([], 'Removed from Liked Songs.');
        break;

    default:
        Response::error('Unknown request.', 404);
}

/**
 * Accepts either { track_id } for an already-cached track, or a full
 * { youtube_video_id, title, artist, thumbnail_url, duration_seconds }
 * payload, and returns the internal tracks.id, inserting if necessary.
 */
function resolveOrCreateTrack(PDO $db, array $body, int $userId): int
{
    if (!empty($body['track_id'])) {
        return (int) $body['track_id'];
    }

    $videoId = trim((string) ($body['youtube_video_id'] ?? ''));
    if ($videoId === '') {
        Response::error('A track_id or youtube_video_id is required.', 422);
    }

    $stmt = $db->prepare('SELECT id FROM tracks WHERE youtube_video_id = ? LIMIT 1');
    $stmt->execute([$videoId]);
    $existing = $stmt->fetch();
    if ($existing) {
        return (int) $existing['id'];
    }

    $stmt = $db->prepare(
        'INSERT INTO tracks (youtube_video_id, title, artist, thumbnail_url, duration_seconds, added_by)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $videoId,
        trim((string) ($body['title'] ?? 'Untitled')),
        trim((string) ($body['artist'] ?? '')),
        trim((string) ($body['thumbnail_url'] ?? '')),
        $body['duration_seconds'] ?? null,
        $userId,
    ]);

    return (int) $db->lastInsertId();
}
