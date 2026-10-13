<?php

declare(strict_types=1);

/**
 * Regenerate curated state diagrams from reviewed source-anchored transitions.
 * php scripts/generate-architecture-lifecycles.php --write
 * php scripts/generate-architecture-lifecycles.php --check
 * No Laravel bootstrap, DB, network or CI involved.
 */
$root = dirname(__DIR__);
$mode = $argv[1] ?? '--summary';
if (count($argv) > 2 || ! in_array($mode, ['--summary', '--write', '--check'], true)) {
    fwrite(STDERR, "Usage: php scripts/generate-architecture-lifecycles.php [--write|--check]\n");
    exit(2);
}
$manifest = json_decode((string) file_get_contents($root.'/docs/architecture/lifecycles.json'), true, 512, JSON_THROW_ON_ERROR);
if (($manifest['format'] ?? '') !== 'curated-state-machines-v1') throw new RuntimeException('Unsupported lifecycle manifest');
$out = $root.'/docs/architecture/generated/lifecycles';
$files = [];
$sourceCache = [];
$count = 0;
foreach ($manifest['models'] as $model) {
    $id = $model['id'];
    if (! is_string($id) || preg_match('/^[a-z][a-z-]*$/D', $id) !== 1) throw new RuntimeException('Invalid id');
    $states = array_fill_keys($model['states'], true);
    $rows = ['%% Curated; source anchors checked; semantic correctness requires review', 'stateDiagram-v2'];
    foreach ($model['transitions'] as $t) {
        if (! isset($states[$t['to']]) || ($t['from'] !== '[*]' && ! isset($states[$t['from']]))) {
            throw new RuntimeException("Unknown state: {$id}");
        }
        $path = $t['source'];
        if (! is_string($path) || ! str_starts_with($path, 'app/') || ! str_ends_with($path, '.php') || str_contains($path, '..')) {
            throw new RuntimeException('Unsafe source path');
        }
        if (! isset($sourceCache[$path])) {
            $sourceCache[$path] = file_get_contents($root.'/'.$path);
            if (! is_string($sourceCache[$path])) throw new RuntimeException("Missing source: {$path}");
        }
        if (! is_string($t['anchor']) || $t['anchor'] === '' || ! str_contains($sourceCache[$path], $t['anchor'])) {
            throw new RuntimeException("Source evidence missing: {$id} in {$path}");
        }
        if (! is_string($t['action']) || str_contains($t['action'], "\n")) throw new RuntimeException('Invalid edge');
        $rows[] = '    '.$t['from'].' --> '.$t['to'].': '.$t['action'];
        $count++;
    }
    $files[$id.'.mmd'] = implode("\n", $rows)."\n";
}
foreach ($files as $name => $content) {
    $path = $out.'/'.$name;
    if ($mode === '--write') {
        if (! is_dir($out) && ! mkdir($out, 0775, true) && ! is_dir($out)) throw new RuntimeException('Cannot create dir');
        if (file_put_contents($path, $content) === false) throw new RuntimeException("Cannot write {$name}");
    } elseif ($mode === '--check' && (! is_file($path) || file_get_contents($path) !== $content)) {
        fwrite(STDERR, "Missing/stale lifecycle diagram: {$name}\n");
        exit(1);
    }
}
echo json_encode(['models' => count($files), 'transitions' => $count, 'sources' => count($sourceCache)], JSON_THROW_ON_ERROR)."\n";
