<?php

declare(strict_types=1);

/**
 * Deterministic class-import diagrams from a versioned PHP source audit.
 * No Laravel bootstrap, database, external network or CI.
 */
$root = dirname(__DIR__);
$mode = $argv[1] ?? '--summary';
if (count($argv) > 2 || ! in_array($mode, ['--summary','--write','--check'], true)) {
    fwrite(STDERR, "Usage: php scripts/generate-architecture-import-diagrams.php [--write|--check]\n");
    exit(2);
}
$input = file_get_contents($root.'/docs/architecture/generated/app-source-audit.json');
if (! is_string($input)) throw new RuntimeException('Missing full source audit');
$data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
if (($data['format'] ?? null) !== 'remote-php-source-audit-v1') throw new RuntimeException('Wrong source format');
$records = $data['records'] ?? [];
$imports = $data['imports'] ?? [];
if (count($records) !== ($data['counts']['files'] ?? -1)
    || count($imports) !== ($data['counts']['internal_import_declarations'] ?? -1)) {
    throw new RuntimeException('Source-audit count mismatch');
}
$owners = [];
$modules = [];
foreach ($records as $record) {
    $path = $record['path'];
    if (! is_string($path) || ! str_starts_with($path, 'app/') || ! str_ends_with($path, '.php') || isset($owners[$path])) {
        throw new RuntimeException('Invalid/duplicate source path');
    }
    if (count($record['definitions']) !== 1) throw new RuntimeException("Ambiguous source: {$path}");
    $parts = explode('/', $path);
    $module = $parts[1] === 'Domain' ? implode('/', array_slice($parts, 1, 2)) : $parts[1];
    $owners[$path] = $module;
    $modules[$module][] = $path;
}
ksort($modules, SORT_STRING);
$groupEdges = array_fill_keys(array_keys($modules), []);
foreach ($imports as $edge) {
    $from = $edge['from'] ?? null; $to = $edge['to'] ?? null;
    if (! is_string($from) || ! is_string($to) || ! isset($owners[$from], $owners[$to])
        || $edge['fromModule'] !== $owners[$from] || $edge['toModule'] !== $owners[$to]) {
        throw new RuntimeException('Broken import source or target');
    }
    $groupEdges[$owners[$from]][] = ['from' => $from, 'to' => $to];
}
$output = $root.'/docs/architecture/generated/class-imports';
$files = [];
foreach ($modules as $module => $locals) {
    $related = $locals;
    foreach ($groupEdges[$module] as $edge) $related[] = $edge['to'];
    $related = array_values(array_unique($related));
    sort($related, SORT_STRING);
    $ids = array_combine($related, array_map(static fn (int $n): string => 'T'.$n, range(1, count($related))));
    $lines = [
        '%% Import declarations only. No implied runtime calls, object instances or method invocations.',
        'flowchart LR',
    ];
    foreach ($related as $path) {
        $label = str_replace('"', "'", substr($path, 4, -4));
        $lines[] = '    '.$ids[$path].'["'.$label.'"]';
    }
    $edges = $groupEdges[$module];
    usort($edges, static fn (array $a, array $b): int => [$a['from'],$a['to']] <=> [$b['from'],$b['to']]);
    foreach ($edges as $edge) $lines[] = '    '.$ids[$edge['from']].' -->|import| '.$ids[$edge['to']];
    $filename = strtolower(str_replace('/', '-', $module)).'.mmd';
    $files[$filename] = implode("\n", $lines)."\n";
}
foreach ($files as $filename => $content) {
    $path = $output.'/'.$filename;
    if ($mode === '--write') {
        if (! is_dir($output) && ! mkdir($output, 0775, true) && ! is_dir($output)) throw new RuntimeException('Cannot create output');
        if (file_put_contents($path, $content) === false) throw new RuntimeException("Cannot write {$filename}");
    } elseif ($mode === '--check' && (! is_file($path) || file_get_contents($path) !== $content)) {
        fwrite(STDERR, "Missing/stale diagram: {$filename}\n");
        exit(1);
    }
}
if ($mode === '--check') {
    foreach (glob($output.'/*.mmd') ?: [] as $path) {
        if (! isset($files[basename($path)])) {
            fwrite(STDERR, 'Unexpected diagram: '.basename($path)."\n");
            exit(1);
        }
    }
}
echo json_encode(['source_files'=>count($owners), 'import_edges'=>count($imports),
    'module_diagrams'=>count($files)], JSON_THROW_ON_ERROR)."\n";
