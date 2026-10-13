<?php

declare(strict_types=1);

/**
 * Eloquent relation diagram from actual Model source, not presumed SQL foreign keys.
 * php scripts/generate-architecture-eloquent.php --write
 * php scripts/generate-architecture-eloquent.php --check
 * No framework bootstrap, network, database, migrations or CI.
 */
$root = dirname(__DIR__);
$mode = $argv[1] ?? '--summary';
if (count($argv) > 2 || ! in_array($mode, ['--summary','--write','--check'], true)) {
    fwrite(STDERR, "Usage: php scripts/generate-architecture-eloquent.php [--write|--check]\n");
    exit(2);
}
$paths = glob($root.'/app/Models/*.php');
if (! is_array($paths) || $paths === []) throw new RuntimeException('No Eloquent model sources');
sort($paths, SORT_STRING);
$models = [];
$relations = [];
$pattern = '/public\s+function\s+([A-Za-z_]\w*)\s*\(\s*\)\s*:\s*(BelongsTo|HasOne|HasMany|BelongsToMany)\s*\{\s*return\s+\$this->(belongsTo|hasOne|hasMany|belongsToMany)\(\s*(self|[A-Z][A-Za-z_0-9]*)::class/s';
foreach ($paths as $path) {
    $name = pathinfo($path, PATHINFO_FILENAME);
    $relative = 'app/Models/'.$name.'.php';
    $source = file_get_contents($path);
    if (! is_string($source)) throw new RuntimeException("Cannot read {$relative}");
    $models[] = ['name' => $name, 'path' => $relative];
    if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
        throw new RuntimeException("Invalid relation expression in {$relative}");
    }
    foreach ($matches as $match) {
        $method = $match[1][0];
        $kind = $match[3][0];
        $target = $match[4][0] === 'self' ? $name : $match[4][0];
        $call = strpos($source, '$this->'.$kind, $match[0][1]);
        if ($call === false) throw new RuntimeException("Cannot locate {$name}::{$method}");
        $relations[] = [
            'model' => $name, 'method' => $method, 'kind' => $kind, 'target' => $target,
            'path' => $relative, 'line' => substr_count(substr($source, 0, $call), "\n") + 1,
        ];
    }
}
usort($relations, static fn (array $a, array $b): int => [$a['model'],$a['method'],$a['target']] <=> [$b['model'],$b['method'],$b['target']]);
$data = [
    'format' => 'eloquent-relationships-v1',
    'scope' => 'Exact Eloquent relations declared in app/Models/*.php; not database foreign-key evidence.',
    'counts' => ['models' => count($models), 'relations' => count($relations)],
    'models' => $models,
    'relations' => $relations,
];
$ids = [];
foreach ($models as $i => $model) $ids[$model['name']] = 'M'.($i + 1);
$graph = ['%% Generated from explicit Eloquent relation methods, NOT pg_catalog foreign keys', 'flowchart LR'];
foreach ($models as $model) $graph[] = '    '.$ids[$model['name']].'["'.$model['name'].'"]';
foreach ($relations as $rel) {
    if (! isset($ids[$rel['target']])) throw new RuntimeException("Unknown model target: {$rel['target']}");
    $graph[] = '    '.$ids[$rel['model']].' -->|'.$rel['method'].'| '.$ids[$rel['target']];
}
$outputs = [
    'eloquent-relationships.json' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n",
    'eloquent-relationships.mmd' => implode("\n", $graph)."\n",
];
$out = $root.'/docs/architecture/generated';
foreach ($outputs as $name => $content) {
    $destination = $out.'/'.$name;
    if ($mode === '--write') {
        if (! is_dir($out) && ! mkdir($out, 0775, true) && ! is_dir($out)) throw new RuntimeException('Cannot create output dir');
        if (file_put_contents($destination, $content) === false) throw new RuntimeException("Cannot write {$name}");
    } elseif ($mode === '--check' && (! is_file($destination) || file_get_contents($destination) !== $content)) {
        fwrite(STDERR, "Missing/stale Eloquent inventory: {$name}\n");
        exit(1);
    }
}
echo json_encode($data['counts'], JSON_THROW_ON_ERROR)."\n";
