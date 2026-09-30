<?php

declare(strict_types=1);

function ensureDataDirectories(): void
{
    foreach ([IXPANEL_DATA_DIR, IXPANEL_PENS_DIR] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the iXPanel data directory.');
        }
    }
}

function atomicJsonWrite(string $path, array $data): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        throw new RuntimeException('The target data directory does not exist.');
    }

    $temporary = tempnam($directory, '.ixpanel-');
    if ($temporary === false) {
        throw new RuntimeException('Could not create a temporary data file.');
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json) || file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
        @unlink($temporary);
        throw new RuntimeException('Could not write the JSON data.');
    }

    @chmod($temporary, 0640);
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Could not replace the JSON data file.');
    }
}

function readJsonFile(string $path, array $fallback = []): array
{
    if (!is_file($path) || !is_readable($path)) {
        return $fallback;
    }

    $raw = (string) file_get_contents($path);
    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3);
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : $fallback;
}

function listReviews(): array
{
    $reviews = readJsonFile(IXPANEL_REVIEWS_FILE, []);

    usort($reviews, static fn(array $a, array $b): int =>
        strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? ''))
    );

    return $reviews;
}

function withReviewsLock(callable $callback): void
{
    ensureDataDirectories();
    $handle = fopen(IXPANEL_REVIEWS_FILE . '.lock', 'c+');

    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        throw new RuntimeException('Could not lock the reviews file.');
    }

    try {
        $callback();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function updateReviewStatus(string $id, string $status): void
{
    if (!in_array($status, ['approved', 'pending'], true)) {
        throw new RuntimeException('Invalid review status.');
    }

    withReviewsLock(static function () use ($id, $status): void {
        $reviews = listReviews();
        $found = false;

        foreach ($reviews as &$review) {
            if (hash_equals((string) ($review['id'] ?? ''), $id)) {
                $review['status'] = $status;
                $review['approvedAt'] = $status === 'approved' ? gmdate('c') : null;
                $found = true;
                break;
            }
        }
        unset($review);

        if (!$found) {
            throw new RuntimeException('The selected review does not exist.');
        }

        atomicJsonWrite(IXPANEL_REVIEWS_FILE, $reviews);
    });
}

function deleteReview(string $id): void
{
    withReviewsLock(static function () use ($id): void {
        $reviews = listReviews();
        $filtered = array_values(array_filter(
            $reviews,
            static fn(array $review): bool =>
                !hash_equals((string) ($review['id'] ?? ''), $id)
        ));

        if (count($filtered) === count($reviews)) {
            throw new RuntimeException('The selected review does not exist.');
        }

        atomicJsonWrite(IXPANEL_REVIEWS_FILE, $filtered);
    });
}

function githubCachePath(): ?string
{
    foreach (['githubCache.json', 'github-cache.json', 'github.json'] as $filename) {
        $candidate = IXPANEL_DATA_DIR . DIRECTORY_SEPARATOR . $filename;
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function listPanelPens(): array
{
    ensureDataDirectories();
    $pens = [];

    foreach (glob(IXPANEL_PENS_DIR . '/*.json') ?: [] as $file) {
        $pen = readJsonFile($file);
        if (!isset($pen['id'])) {
            continue;
        }

        $content = (string) ($pen['content'] ?? '');
        $pens[] = [
            'id' => (string) $pen['id'],
            'content' => $content,
            'language' => (string) ($pen['language'] ?? 'plaintext'),
            'created' => $pen['created'] ?? null,
            'updated' => $pen['updated'] ?? null,
            'views' => (int) ($pen['views'] ?? 0),
            'bytes' => strlen($content),
        ];
    }

    usort($pens, fn(array $a, array $b): int => strcmp((string) $b['updated'], (string) $a['updated']));
    return $pens;
}

function validPenId(string $id): bool
{
    return preg_match('/^[A-Za-z0-9]{1,64}$/', $id) === 1;
}

function penPath(string $id): string
{
    return IXPANEL_PENS_DIR . DIRECTORY_SEPARATOR . $id . '.json';
}
