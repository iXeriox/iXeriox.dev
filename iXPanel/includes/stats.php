<?php

declare(strict_types=1);

function bytesToArray(int|float $bytes): array
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = max(0, (float) $bytes);
    $index = 0;

    while ($value >= 1024 && $index < count($units) - 1) {
        $value /= 1024;
        $index++;
    }

    return ['value' => round($value, $index > 1 ? 1 : 0), 'unit' => $units[$index]];
}

function linuxMemory(): ?array
{
    if (!is_readable('/proc/meminfo')) {
        return null;
    }

    $values = [];
    foreach (file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (preg_match('/^([A-Za-z_()]+):\s+(\d+)\s+kB$/', $line, $match)) {
            $values[$match[1]] = (int) $match[2] * 1024;
        }
    }

    $total = $values['MemTotal'] ?? 0;
    $available = $values['MemAvailable'] ?? ($values['MemFree'] ?? 0);
    return $total > 0 ? ['total' => $total, 'used' => max(0, $total - $available)] : null;
}

function serverUptime(): ?int
{
    if (!is_readable('/proc/uptime')) {
        return null;
    }

    return (int) floor((float) explode(' ', trim((string) file_get_contents('/proc/uptime')))[0]);
}

function cpuModel(): string
{
    if (is_readable('/proc/cpuinfo')) {
        $contents = (string) file_get_contents('/proc/cpuinfo');
        if (preg_match('/model name\s*:\s*(.+)/i', $contents, $match)) {
            return trim($match[1]);
        }
    }

    return php_uname('m');
}

function coreCount(): int
{
    if (is_readable('/proc/cpuinfo')) {
        $count = preg_match_all('/^processor\s*:/m', (string) file_get_contents('/proc/cpuinfo'));
        if ($count > 0) {
            return $count;
        }
    }

    return 1;
}

function statPayload(): array
{
    $memory = linuxMemory();
    $diskTotal = (float) (@disk_total_space(DIRECTORY_SEPARATOR) ?: 0);
    $diskFree = (float) (@disk_free_space(DIRECTORY_SEPARATOR) ?: 0);
    $loads = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0, 0, 0]) : [0, 0, 0];
    $cores = coreCount();
    $cpuPercent = min(100, max(0, ((float) ($loads[0] ?? 0) / max(1, $cores)) * 100));
    $memoryPercent = $memory ? ($memory['used'] / max(1, $memory['total'])) * 100 : 0;
    $diskUsed = max(0, $diskTotal - $diskFree);
    $diskPercent = $diskTotal > 0 ? ($diskUsed / $diskTotal) * 100 : 0;

    $_SESSION['ixpanel_views'] = (int) ($_SESSION['ixpanel_views'] ?? 0) + 1;

    return [
        'generatedAt' => gmdate('c'),
        'health' => [
            'status' => ($cpuPercent < 90 && $memoryPercent < 90 && $diskPercent < 92) ? 'Operational' : 'Attention',
            'score' => (int) round(max(0, 100 - max($cpuPercent, $memoryPercent, $diskPercent) * .45)),
        ],
        'cpu' => [
            'percent' => round($cpuPercent, 1),
            'load' => array_map(fn($value): float => round((float) $value, 2), array_slice($loads, 0, 3)),
            'cores' => $cores,
            'model' => cpuModel(),
        ],
        'memory' => [
            'percent' => round($memoryPercent, 1),
            'used' => bytesToArray($memory['used'] ?? memory_get_usage(true)),
            'total' => bytesToArray($memory['total'] ?? memory_get_usage(true)),
            'process' => bytesToArray(memory_get_usage(true)),
            'peak' => bytesToArray(memory_get_peak_usage(true)),
        ],
        'disk' => [
            'percent' => round($diskPercent, 1),
            'used' => bytesToArray($diskUsed),
            'free' => bytesToArray($diskFree),
            'total' => bytesToArray($diskTotal),
        ],
        'system' => [
            'hostname' => gethostname() ?: 'Unknown host',
            'os' => PHP_OS_FAMILY,
            'kernel' => php_uname('r'),
            'architecture' => php_uname('m'),
            'uptime' => serverUptime(),
            'timezone' => date_default_timezone_get(),
            'serverSoftware' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'PHP server'),
        ],
        'php' => [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memoryLimit' => ini_get('memory_limit') ?: 'Unknown',
            'maxExecutionTime' => (int) ini_get('max_execution_time'),
            'uploadLimit' => ini_get('upload_max_filesize') ?: 'Unknown',
            'extensions' => count(get_loaded_extensions()),
            'opcache' => function_exists('opcache_get_status') && (bool) @opcache_get_status(false),
        ],
        'request' => [
            'https' => isHttpsRequest(),
            'protocol' => (string) ($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP'),
            'sessionViews' => $_SESSION['ixpanel_views'],
        ],
    ];
}
