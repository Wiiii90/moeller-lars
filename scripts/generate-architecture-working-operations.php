<?php

declare(strict_types=1);

/**
 * Rebuild curated Working operations diagrams, validating source anchors.
 * No Laravel bootstrap, database access, external network, or CI.
 * php scripts/generate-architecture-working-operations.php --write
 * php scripts/generate-architecture-working-operations.php --check
 */
$root = dirname(__DIR__);
$mode = $argv[1] ?? '--summary';
if (count($argv) > 2 || ! in_array($mode, ['--summary', '--write', '--check'], true)) {
    fwrite(STDERR, "Usage: php scripts/generate-architecture-working-operations.php [--write|--check]\n");
    exit(2);
}
$path = $root.'/docs/architecture/publication-working-operations.json';
$content = file_get_contents($path);
if (! is_string($content)) throw new RuntimeException('Manifest is missing');
$data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
if (($data['format'] ?? '') !== 'publication-working-operations-v1') throw new RuntimeException('Wrong format');
$sourceCache = [];
$count = 0;
$dest = $root.'/docs/architecture/generated/working-operations';
foreach ($data['flows'] as $flow) {
    $id = $flow['id'] ?? null;
    if (! is_string($id) || preg_match('/^[a-z][a-z0-9-]*$/D', $id) !== 1) throw new RuntimeException('Unsafe flow name');
    if (! is_array($flow['diagram'] ?? null) || ($flow['diagram'][0] ?? null) !== 'sequenceDiagram') {
        throw new RuntimeException("Invalid Mermaid source: {$id}");
    }
    foreach ($flow['evidence'] as $item) {
        $source = $item['path'];
        $line = $item['line'];
        $anchor = $item['anchor'];
        if (! is_string($source) || ! str_starts_with($source, 'app/') || ! str_ends_with($source, '.php')
            || str_contains($source, '..') || str_contains($source, '\\')
            || ! is_int($line) || $line < 1 || ! is_string($anchor) || $anchor === '') {
            throw new RuntimeException("Unsafe source anchor: {$id}");
        }
        if (! array_key_exists($source, $sourceCache)) {
            $raw = file_get_contents($root.'/'.$source);
            if (! is_string($raw)) throw new RuntimeException("Missing source: {$source}");
            $sourceCache[$source] = explode("\n", $raw);
        }
        if (! str_contains($sourceCache[$source][$line - 1] ?? '', $anchor)) {
            throw new RuntimeException("Stale evidence: {$id}:{$source}:{$line}");
        }
        $count++;
    }
    $generated = implode("\n", $flow['diagram'])."\n";
    $destination = $dest.'/'.$id.'.mmd';
    if ($mode === '--write') {
        if (! is_dir($dest) && ! mkdir($dest, 0775, true) && ! is_dir($dest)) {
            throw new RuntimeException('Cannot create output directory');
        }
        if (file_put_contents($destination, $generated) === false) throw new RuntimeException('Failed writing Mermaid');
    } elseif ($mode === '--check' && (! is_file($destination) || file_get_contents($destination) !== $generated)) {
        fwrite(STDERR, "Stale or missing Mermaid: {$id}\n");
        exit(1);
    }
}
echo json_encode(['flows' => count($data['flows']), 'anchors' => $count, 'source_files' => count($sourceCache)], JSON_THROW_ON_ERROR)."\n";
