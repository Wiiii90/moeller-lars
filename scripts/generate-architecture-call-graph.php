<?php

declare(strict_types=1);

/** Source-anchored, curated method-call graph; no Laravel boot or database. */
$root = dirname(__DIR__);
$mode = $argv[1] ?? '--summary';
if (count($argv) > 2 || ! in_array($mode, ['--summary', '--write', '--check'], true)) {
    fwrite(STDERR, "Usage: php scripts/generate-architecture-call-graph.php [--write|--check]\n");
    exit(2);
}
$manifest = $root.'/docs/architecture/admin-publication-calls.json';
$input = file_get_contents($manifest);
if ($input === false) throw new RuntimeException('Missing call manifest');
$spec = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
if (($spec['format'] ?? '') !== 'curated-method-calls-v1') throw new RuntimeException('Unsupported manifest format');
$nodes = [];
$edges = [];
$sourceCache = [];
foreach ($spec['edges'] as $edge) {
    foreach (['from', 'to', 'method', 'path', 'line', 'anchor'] as $field) {
        if (! isset($edge[$field])) throw new RuntimeException("Missing edge field: {$field}");
    }
    $path = $edge['path'];
    if (! is_string($path) || ! str_starts_with($path, 'app/') || ! str_ends_with($path, '.php')
        || str_contains($path, '..') || str_contains($path, '\\')) {
        throw new RuntimeException('Unsafe source path');
    }
    foreach (['from', 'to', 'method'] as $field) {
        if (! is_string($edge[$field]) || preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $edge[$field]) !== 1) {
            throw new RuntimeException("Invalid call field: {$field}");
        }
    }
    if (! is_int($edge['line']) || $edge['line'] < 1 || ! is_string($edge['anchor']) || $edge['anchor'] === '') {
        throw new RuntimeException('Invalid source evidence');
    }
    if (! isset($sourceCache[$path])) {
        $contents = file_get_contents($root.'/'.$path);
        if (! is_string($contents)) throw new RuntimeException("Missing source: {$path}");
        $sourceCache[$path] = $contents;
    }
    $lines = explode("\n", $sourceCache[$path]);
    if (! str_contains($lines[$edge['line'] - 1] ?? '', $edge['anchor'])) {
        throw new RuntimeException("Stale source anchor: {$path}:{$edge['line']} ({$edge['from']}->{$edge['to']})");
    }
    $nodes[$edge['from']] = true;
    $nodes[$edge['to']] = true;
    $edges[] = $edge;
}
ksort($nodes, SORT_STRING);
usort($edges, static fn (array $a, array $b): int => [$a['from'], $a['to'], $a['method'], $a['line']]
    <=> [$b['from'], $b['to'], $b['method'], $b['line']]);
$ids = [];
$diagram = ['%% Curated, checked PHP call sites; not an exhaustive runtime call graph', 'flowchart LR'];
foreach (array_keys($nodes) as $name) {
    $ids[$name] = 'N'.(count($ids) + 1);
    $diagram[] = '    '.$ids[$name].'["'.$name.'"]';
}
foreach ($edges as $edge) {
    $diagram[] = '    '.$ids[$edge['from']].' -->|'.$edge['method'].'| '.$ids[$edge['to']];
}
$output = implode("\n", $diagram)."\n";
$target = $root.'/docs/architecture/generated/admin-publication-calls.mmd';
if ($mode === '--write') {
    if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
        throw new RuntimeException('Cannot create output directory');
    }
    if (file_put_contents($target, $output) === false) throw new RuntimeException('Cannot write output');
} elseif ($mode === '--check' && (! is_file($target) || file_get_contents($target) !== $output)) {
    fwrite(STDERR, "Source call-graph output is missing or stale; run --write.\n");
    exit(1);
}
echo json_encode(['nodes' => count($nodes), 'call_sites' => count($edges), 'source_files' => count($sourceCache)], JSON_THROW_ON_ERROR)."\n";
