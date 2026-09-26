<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed.', 405);
}

$user = Auth::requireAuth();

$query = trim((string) ($_GET['q'] ?? ''));
if ($query === '') {
    Response::error('A search query "q" is required.', 422);
}

if (empty($config['youtube_api_key'])) {
    Response::error(
        'The server is not configured with a YouTube Data API key. Set YOUTUBE_API_KEY in your environment.',
        500
    );
}

$popular = isset($_GET['popular']);
$params = http_build_query([
    'part'       => 'snippet',
    'type'       => 'video',
    'videoCategoryId' => 10, // Music
    'maxResults' => 15,
    'q'          => $popular ? "$query official music" : $query,
    'key'        => $config['youtube_api_key'],
]);

$ch = curl_init("https://www.googleapis.com/youtube/v3/search?$params");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8,
]);
$raw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($raw === false || $httpCode >= 400) {
    Response::error('YouTube search failed. Please try again.', 502);
}

$json = json_decode($raw, true);
$results = [];
$blocked = Database::connection()->query('SELECT youtube_video_id, reason FROM blocked_videos')->fetchAll();
$blockedById = array_column($blocked, 'reason', 'youtube_video_id');

foreach (($json['items'] ?? []) as $item) {
    if (empty($item['id']['videoId'])) {
        continue;
    }
    $videoId = $item['id']['videoId'];
    $results[] = [
        'youtube_video_id' => $item['id']['videoId'],
        'title'            => html_entity_decode($item['snippet']['title'] ?? 'Untitled', ENT_QUOTES | ENT_HTML5),
        'artist'           => html_entity_decode($item['snippet']['channelTitle'] ?? '', ENT_QUOTES | ENT_HTML5),
        'thumbnail_url'    => $item['snippet']['thumbnails']['high']['url']
            ?? $item['snippet']['thumbnails']['default']['url']
            ?? '',
        'published_at'     => $item['snippet']['publishedAt'] ?? null,
        'blocked'          => isset($blockedById[$videoId]),
        'blocked_message'  => $blockedById[$videoId] ?? null,
    ];
}

Response::success(['results' => $results]);
