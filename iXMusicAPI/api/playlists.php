<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::connection();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$action = $_GET['action'] ?? null;

function playlistOwnerOrElevated(PDO $db, int $playlistId, array $user): array
{
    $stmt = $db->prepare('SELECT * FROM playlists WHERE id = ? LIMIT 1');
    $stmt->execute([$playlistId]);
    $playlist = $stmt->fetch();
    if (!$playlist) {
        Response::error('Playlist not found.', 404);
    }
    $isOwner = (int) $playlist['user_id'] === (int) $user['id'];
    $isElevated = in_array($user['role'], ['moderator', 'admin'], true);
    if (!$isOwner && !$isElevated) {
        Response::error('You do not have permission to modify this playlist.', 403);
    }
    return $playlist;
}

function tracksForPlaylist(PDO $db, int $playlistId): array
{
    $stmt = $db->prepare(
        'SELECT t.id, t.youtube_video_id, t.title, t.artist, t.thumbnail_url, t.duration_seconds,
                pt.position, pt.added_at
         FROM playlist_tracks pt
         JOIN tracks t ON t.id = pt.track_id
         WHERE pt.playlist_id = ?
         ORDER BY pt.position ASC, pt.added_at ASC'
    );
    $stmt->execute([$playlistId]);
    return $stmt->fetchAll();
}

switch (true) {

    // ------------------------------------------------------------
    // GET /api/playlists.php               -> your playlists + public playlists
    // GET /api/playlists.php?id=5           -> single playlist with tracks
    // ------------------------------------------------------------
    case $method === 'GET' && !$id:
        $user = Auth::requireAuth();
        $stmt = $db->prepare(
            'SELECT p.*, u.username AS owner_username,
                    (SELECT COUNT(*) FROM playlist_tracks WHERE playlist_id = p.id) AS track_count
             FROM playlists p
             JOIN users u ON u.id = p.user_id
             WHERE p.user_id = ? OR p.is_public = 1
             ORDER BY p.updated_at DESC'
        );
        $stmt->execute([$user['id']]);
        Response::success(['playlists' => $stmt->fetchAll()]);
        break;

    case $method === 'GET' && $id !== null:
        $user = Auth::requireAuth();
        $stmt = $db->prepare(
            'SELECT p.*, u.username AS owner_username FROM playlists p
             JOIN users u ON u.id = p.user_id WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $playlist = $stmt->fetch();
        if (!$playlist) {
            Response::error('Playlist not found.', 404);
        }
        $isOwner = (int) $playlist['user_id'] === (int) $user['id'];
        if (!$playlist['is_public'] && !$isOwner && !in_array($user['role'], ['moderator', 'admin'], true)) {
            Response::error('This playlist is private.', 403);
        }
        $playlist['tracks'] = tracksForPlaylist($db, $id);
        Response::success(['playlist' => $playlist]);
        break;

    // ------------------------------------------------------------
    // POST /api/playlists.php                         -> create playlist
    // POST /api/playlists.php?id=5&action=add_track    -> add a track
    // ------------------------------------------------------------
    case $method === 'POST' && !$id:
        $user = Auth::requireAuth();
        $name = trim((string) requireField('name'));
        $description = trim((string) (requestBody()['description'] ?? ''));
        $isPublic = !empty(requestBody()['is_public']) ? 1 : 0;

        $stmt = $db->prepare(
            'INSERT INTO playlists (user_id, name, description, is_public) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$user['id'], $name, $description, $isPublic]);
        $newId = (int) $db->lastInsertId();

        $stmt = $db->prepare('SELECT * FROM playlists WHERE id = ?');
        $stmt->execute([$newId]);
        $playlist = $stmt->fetch();
        $playlist['tracks'] = [];

        Response::success(['playlist' => $playlist], 'Playlist created.', 201);
        break;

    case $method === 'POST' && $id !== null && $action === 'add_track':
        $user = Auth::requireAuth();
        $playlist = playlistOwnerOrElevated($db, $id, $user);

        $videoId = trim((string) requireField('youtube_video_id'));
        $title = trim((string) requireField('title'));
        $artist = trim((string) (requestBody()['artist'] ?? ''));
        $thumbnail = trim((string) (requestBody()['thumbnail_url'] ?? ''));
        $duration = requestBody()['duration_seconds'] ?? null;

        // Upsert into the global track cache
        $stmt = $db->prepare('SELECT id FROM tracks WHERE youtube_video_id = ? LIMIT 1');
        $stmt->execute([$videoId]);
        $track = $stmt->fetch();

        if ($track) {
            $trackId = (int) $track['id'];
        } else {
            $stmt = $db->prepare(
                'INSERT INTO tracks (youtube_video_id, title, artist, thumbnail_url, duration_seconds, added_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$videoId, $title, $artist, $thumbnail, $duration, $user['id']]);
            $trackId = (int) $db->lastInsertId();
        }

        $posStmt = $db->prepare('SELECT COALESCE(MAX(position), -1) + 1 AS next_pos FROM playlist_tracks WHERE playlist_id = ?');
        $posStmt->execute([$id]);
        $nextPos = (int) $posStmt->fetch()['next_pos'];

        try {
            $stmt = $db->prepare(
                'INSERT INTO playlist_tracks (playlist_id, track_id, position) VALUES (?, ?, ?)'
            );
            $stmt->execute([$id, $trackId, $nextPos]);
        } catch (PDOException $e) {
            Response::error('That track is already in the playlist.', 409);
        }

        $db->prepare('UPDATE playlists SET updated_at = NOW() WHERE id = ?')->execute([$id]);

        Response::success(['tracks' => tracksForPlaylist($db, $id)], 'Track added.', 201);
        break;

    // ------------------------------------------------------------
    // PUT /api/playlists.php?id=5             -> update name/description/visibility
    // PUT /api/playlists.php?id=5&action=reorder -> reorder tracks
    // ------------------------------------------------------------
    case $method === 'PUT' && $id !== null && $action === 'reorder':
        $user = Auth::requireAuth();
        playlistOwnerOrElevated($db, $id, $user);

        $order = requireField('track_ids'); // array of track ids, in new order
        if (!is_array($order)) {
            Response::error('track_ids must be an array.', 422);
        }

        $stmt = $db->prepare('UPDATE playlist_tracks SET position = ? WHERE playlist_id = ? AND track_id = ?');
        foreach ($order as $position => $trackId) {
            $stmt->execute([$position, $id, (int) $trackId]);
        }

        Response::success(['tracks' => tracksForPlaylist($db, $id)], 'Playlist reordered.');
        break;

    case $method === 'PUT' && $id !== null:
        $user = Auth::requireAuth();
        playlistOwnerOrElevated($db, $id, $user);

        $body = requestBody();
        $updates = [];
        $params = [];

        if (isset($body['name']) && trim((string) $body['name']) !== '') {
            $updates[] = 'name = ?';
            $params[] = trim((string) $body['name']);
        }
        if (isset($body['description'])) {
            $updates[] = 'description = ?';
            $params[] = trim((string) $body['description']);
        }
        if (isset($body['is_public'])) {
            $updates[] = 'is_public = ?';
            $params[] = !empty($body['is_public']) ? 1 : 0;
        }
        if (isset($body['cover_image'])) {
            $updates[] = 'cover_image = ?';
            $params[] = trim((string) $body['cover_image']);
        }

        if (empty($updates)) {
            Response::error('Nothing to update.', 422);
        }

        $params[] = $id;
        $db->prepare('UPDATE playlists SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);

        $stmt = $db->prepare('SELECT * FROM playlists WHERE id = ?');
        $stmt->execute([$id]);
        $playlist = $stmt->fetch();
        $playlist['tracks'] = tracksForPlaylist($db, $id);

        Response::success(['playlist' => $playlist], 'Playlist updated.');
        break;

    // ------------------------------------------------------------
    // DELETE /api/playlists.php?id=5                          -> delete playlist
    // DELETE /api/playlists.php?id=5&action=remove_track&track_id=9
    // ------------------------------------------------------------
    case $method === 'DELETE' && $id !== null && $action === 'remove_track':
        $user = Auth::requireAuth();
        playlistOwnerOrElevated($db, $id, $user);

        $trackId = isset($_GET['track_id']) ? (int) $_GET['track_id'] : 0;
        if (!$trackId) {
            Response::error('A track_id is required.', 422);
        }

        $db->prepare('DELETE FROM playlist_tracks WHERE playlist_id = ? AND track_id = ?')
           ->execute([$id, $trackId]);

        Response::success(['tracks' => tracksForPlaylist($db, $id)], 'Track removed.');
        break;

    case $method === 'DELETE' && $id !== null:
        $user = Auth::requireAuth();
        playlistOwnerOrElevated($db, $id, $user);

        $db->prepare('DELETE FROM playlists WHERE id = ?')->execute([$id]);
        Response::success([], 'Playlist deleted.');
        break;

    default:
        Response::error('Unknown request.', 404);
}
