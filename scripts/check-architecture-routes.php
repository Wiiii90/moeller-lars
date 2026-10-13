<?php

declare(strict_types=1);

/**
 * Validate explicit routes/web.php declarations against the curated source
 * manifest. Source-only: do not bootstrap Laravel, contact CI or query a DB.
 *
 * Usage: php scripts/check-architecture-routes.php [--check|--summary]
 */
$root = dirname(__DIR__);
$mode = $argv[1] ?? '--check';
if (count($argv) > 2 || ! in_array($mode, ['--check', '--summary'], true)) {
    fwrite(STDERR, "Usage: php scripts/check-architecture-routes.php [--check|--summary]\n");
    exit(2);
}

$source = file_get_contents($root.'/routes/web.php');
$raw = file_get_contents($root.'/docs/architecture/runtime-entrypoints.json');
if (! is_string($source) || ! is_string($raw)) {
    throw new RuntimeException('Missing source route file or manifest');
}
$catalog = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
if (($catalog['format'] ?? null) !== 'http-entrypoints-v1'
    || ! is_array($catalog['routes'] ?? null)) {
    throw new RuntimeException('Unexpected route manifest format');
}

preg_match_all('/\bRoute::(get|post)\(\s*\x27([^\x27]+)\x27\s*,/m', $source, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
if (count($found) !== count($catalog['routes'])) {
    throw new RuntimeException('Source/manifest route count differs: '.count($found).' versus '.count($catalog['routes']));
}
$seen = [];
$groupCounts = [];
foreach ($catalog['routes'] as $index => $route) {
    $match = $found[$index] ?? null;
    if (! is_array($route) || $match === null) {
        throw new RuntimeException("Malformed route record at index {$index}");
    }
    $method = strtoupper($match[1][0]);
    $declared = $match[2][0];
    $line = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
    $path = ($route['group'] ?? null) === 'artist-preview' ? '/preview'.($declared === '/' ? '/' : $declared) : $declared;
    if (($route['method'] ?? null) !== $method || ($route['path'] ?? null) !== $path
        || ($route['line'] ?? null) !== $line || ($route['source'] ?? null) !== 'routes/web.php') {
        throw new RuntimeException("Stale declaration at route {$index}: {$method} {$path}:{$line}");
    }
    $key = $method.' '.$path;
    if (isset($seen[$key])) {
        throw new RuntimeException("Duplicate route record: {$key}");
    }
    $seen[$key] = true;

    $after = substr($source, $match[0][1] + strlen($match[0][0]), 180);
    if (($route['handler_kind'] ?? null) === 'closure') {
        if (($route['controller'] ?? null) !== null || ($route['action'] ?? null) !== null
            || ! preg_match('/^\s*static\s+function\s*\(/', $after)) {
            throw new RuntimeException("Stale closure handler for {$key}");
        }
    } elseif (! preg_match('/^\s*\[\s*([A-Za-z_]\w*)::class\s*,\s*\x27([A-Za-z_]\w*)\x27\s*\]/', $after, $handler)
        || ($route['controller'] ?? null) !== $handler[1]
        || ($route['action'] ?? null) !== $handler[2]) {
        throw new RuntimeException("Stale controller/action for {$key}");
    }
    $group = $route['group'] ?? '(unset)';
    $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
}
ksort($groupCounts, SORT_STRING);
echo json_encode(['routes' => count($found), 'groups' => $groupCounts, 'source' => 'routes/web.php'], JSON_THROW_ON_ERROR)."\n";
