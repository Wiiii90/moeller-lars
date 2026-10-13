<?php

declare(strict_types=1);

/**
 * Validate explicit routes/web.php declarations against the curated source
 * manifest. Source-only: do not bootstrap Laravel, contact CI or query a DB.
 *
 * Usage: php scripts/check-architecture-routes.php [--check|--summary|--self-test]
 */
$root = dirname(__DIR__);
$mode = $argv[1] ?? '--check';
if (count($argv) > 2 || ! in_array($mode, ['--check', '--summary', '--self-test'], true)) {
    fwrite(STDERR, "Usage: php scripts/check-architecture-routes.php [--check|--summary|--self-test]\n");
    exit(2);
}

/** @return list<array{id:int|string,text:string,line:int,offset:int}> */
function routeTokens(string $source): array
{
    $result = [];
    $line = 1;
    $offset = 0;
    foreach (token_get_all($source) as $token) {
        $text = is_array($token) ? $token[1] : $token;
        $result[] = [
            'id' => is_array($token) ? $token[0] : $token,
            'text' => $text,
            'line' => is_array($token) ? $token[2] : $line,
            'offset' => $offset,
        ];
        $line += substr_count($text, "\n");
        $offset += strlen($text);
    }
    return $result;
}

/** @param list<array{id:int|string,text:string,line:int,offset:int}> $tokens */
function routeNext(array $tokens, int $at): ?int
{
    for ($i = $at; $i < count($tokens); $i++) {
        if (! in_array($tokens[$i]['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $i;
        }
    }
    return null;
}

/**
 * Extract only literal Route::get/post declarations from actual PHP tokens.
 * Dynamic router registrations remain outside this curated manifest.
 * @return list<array{method:string,declared:string,line:int,after_offset:int}>
 */
function explicitRoutesIn(string $source): array
{
    $tokens = routeTokens($source);
    $out = [];
    foreach ($tokens as $i => $token) {
        if ($token['id'] !== T_STRING || $token['text'] !== 'Route') continue;
        $scope = routeNext($tokens, $i + 1);
        $method = $scope === null ? null : routeNext($tokens, $scope + 1);
        $open = $method === null ? null : routeNext($tokens, $method + 1);
        $literal = $open === null ? null : routeNext($tokens, $open + 1);
        $comma = $literal === null ? null : routeNext($tokens, $literal + 1);
        if ($scope === null || $method === null || $open === null || $literal === null || $comma === null) continue;
        if ($tokens[$scope]['id'] !== T_DOUBLE_COLON
            || $tokens[$method]['id'] !== T_STRING
            || ! in_array($tokens[$method]['text'], ['get', 'post'], true)
            || $tokens[$open]['text'] !== '('
            || $tokens[$literal]['id'] !== T_CONSTANT_ENCAPSED_STRING
            || $tokens[$comma]['text'] !== ',') continue;
        $quoted = $tokens[$literal]['text'];
        if (! str_starts_with($quoted, "'") || ! str_ends_with($quoted, "'")) continue;
        $out[] = [
            'method' => strtoupper($tokens[$method]['text']),
            'declared' => substr($quoted, 1, -1),
            'line' => $token['line'],
            'after_offset' => $tokens[$comma]['offset'] + 1,
        ];
    }
    return $out;
}

if ($mode === '--self-test') {
    $sourceCode = <<<'PHP'
<?php
// Route::get('/ignored-comment', [Other::class, 'index']);
/* Route::post('/ignored-block', [Other::class, 'store']); */
$text = "Route::get('/ignored-string', [Other::class, 'index']);";
Route::get('/actual', [Things::class, 'index']);
Route::post('/submit', static function () {});
PHP;
    $found = explicitRoutesIn($sourceCode);
    if (count($found) !== 2
        || $found[0]['method'] !== 'GET'
        || $found[0]['declared'] !== '/actual'
        || $found[0]['line'] !== 5
        || $found[1]['method'] !== 'POST'
        || $found[1]['declared'] !== '/submit') {
        throw new RuntimeException('Explicit route tokenizer self-test failed: '.json_encode($found));
    }
    echo "PASS commented and string route exclusion\n";
    echo "PASS explicit route ordering and line numbers\n";
    exit(0);
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

$found = explicitRoutesIn($source);
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
    $method = $match['method'];
    $declared = $match['declared'];
    $line = $match['line'];
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

    $after = substr($source, $match['after_offset'], 180);
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
