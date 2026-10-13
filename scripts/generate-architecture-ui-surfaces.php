<?php

declare(strict_types=1);

/**
 * Static, read-only UI inventory: Filament Pages/Concerns, Admin Livewire,
 * literal Action::make names, declared methods and Page/Concern -> Concern trait use.
 * Never boots Laravel, accesses a database, contacts CI or infers routes.
 */
$root = dirname(__DIR__);
$mode = $argv[1] ?? '--summary';
if (count($argv) > 2 || ! in_array($mode, ['--summary', '--write', '--check'], true)) {
    fwrite(STDERR, "Usage: php scripts/generate-architecture-ui-surfaces.php [--write|--check]\n");
    exit(2);
}
$patterns = [
    'page' => 'app/Filament/Pages/*.php',
    'concern' => 'app/Filament/Pages/Concerns/*.php',
    'livewire' => 'app/Livewire/Admin/*.php',
];

/** @return list<array{name:string,line:int}> */
function uiMatches(string $source, string $pattern, int $capture = 1): array
{
    $matches = [];
    preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $found = [];
    foreach ($matches as $match) {
        $found[] = [
            'name' => $match[$capture][0],
            'line' => substr_count(substr($source, 0, $match[0][1]), "\n") + 1,
        ];
    }
    return $found;
}
$records = [];
foreach ($patterns as $kind => $pattern) {
    $paths = glob($root.'/'.$pattern);
    if (! is_array($paths)) throw new RuntimeException("Cannot list {$pattern}");
    sort($paths, SORT_STRING);
    foreach ($paths as $path) {
        $source = file_get_contents($path);
        if (! is_string($source)) throw new RuntimeException("Cannot read {$path}");
        $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
        $types = uiMatches($source, '/^[ \t]*(?:(?:final|abstract)\s+)?(?:class|trait)\s+([A-Za-z_]\w*)\b/m');
        if (count($types) !== 1) throw new RuntimeException("Ambiguous declaration in {$relative}");
        $name = $types[0]['name'];
        $slugMatches = uiMatches($source, '/^[ \t]*protected\s+static\s+\?string\s+\$slug\s*=\s*\'([^\']*)\'/m');
        $viewMatches = uiMatches($source, '/^[ \t]*protected\s+string\s+\$view\s*=\s*\'([^\']*)\'/m');
        if (! preg_match('/^[ \t]*(?:(?:final|abstract)\s+)?(?:class|trait)\s+'.preg_quote($name, '/').'\b[^\{]*\{/m', $source, $declaration, PREG_OFFSET_CAPTURE)) {
            throw new RuntimeException("Cannot locate type body in {$relative}");
        }
        $bodyStart = $declaration[0][1] + strlen($declaration[0][0]) - 1;
        if ($bodyStart === false) throw new RuntimeException("Missing type body in {$relative}");
        $body = substr($source, $bodyStart + 1);
        $bodyTraits = uiMatches($body, '/^[ \t]*use\s+([A-Za-z_]\w*)\s*;/m');
        foreach ($bodyTraits as &$trait) {
            $trait['line'] += substr_count(substr($source, 0, $bodyStart + 1), "\n");
        }
        unset($trait);
        $records[] = [
            'name' => $name,
            'kind' => $kind,
            'path' => $relative,
            'slug' => $slugMatches[0]['name'] ?? null,
            'view' => $viewMatches[0]['name'] ?? null,
            'traits' => $bodyTraits,
            'public_methods' => uiMatches($source, '/^[ \t]*public\s+(?:static\s+)?function\s+([A-Za-z_]\w*)\s*\(/m'),
            'literal_actions' => uiMatches($source, '/\bAction::make\(\s*\'([^\']+)\'/'),
        ];
    }
}
$knownTraits = [];
foreach ($records as $record) if ($record['kind'] === 'concern') $knownTraits[$record['name']] = $record;
$edges = [];
foreach ($records as $record) {
    if (! in_array($record['kind'], ['page', 'concern'], true)) continue;
    foreach ($record['traits'] as $use) {
        if (isset($knownTraits[$use['name']])) {
            $edges[] = ['from' => $record['name'], 'to' => $use['name'],
                'path' => $record['path'], 'line' => $use['line']];
        }
    }
}
usort($edges, static fn (array $a, array $b): int => [$a['from'],$a['to']] <=> [$b['from'],$b['to']]);
$counts = ['pages' => 0, 'concerns' => 0, 'livewire' => 0, 'trait_uses' => count($edges), 'literal_actions' => 0];
foreach ($records as $record) {
    $counts[$record['kind'] === 'page' ? 'pages' : ($record['kind'] === 'concern' ? 'concerns' : 'livewire')]++;
    $counts['literal_actions'] += count($record['literal_actions']);
}
$data = [
    'format' => 'filament-livewire-source-inventory-v1',
    'limitations' => 'Source-only declarations; no dynamically registered routes or actions, inherited methods, runtime listeners, view composition, JavaScript events or executing code.',
    'counts' => $counts, 'units' => $records, 'trait_composition_edges' => $edges,
];
$nodes = [];
foreach ($edges as $edge) { $nodes[$edge['from']] = true; $nodes[$edge['to']] = true; }
ksort($nodes, SORT_STRING);
$ids = [];
$diagram = ['%% Direct Page/Concern -> Concern trait use; not HTTP or runtime call paths.', 'flowchart LR'];
foreach (array_keys($nodes) as $name) {
    $ids[$name] = 'U'.(count($ids) + 1);
    $diagram[] = '    '.$ids[$name].'["'.$name.'"]';
}
foreach ($edges as $edge) $diagram[] = '    '.$ids[$edge['from']].' -->|trait| '.$ids[$edge['to']];
$generated = [
    'ui-surfaces.json' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n",
    'ui-composition.mmd' => implode("\n", $diagram)."\n",
];
$out = $root.'/docs/architecture/generated';
foreach ($generated as $name => $content) {
    $path = $out.'/'.$name;
    if ($mode === '--write') {
        if (! is_dir($out) && ! mkdir($out, 0775, true) && ! is_dir($out)) throw new RuntimeException('Cannot create output directory');
        if (file_put_contents($path, $content) === false) throw new RuntimeException("Cannot write {$name}");
    } elseif ($mode === '--check' && (! is_file($path) || file_get_contents($path) !== $content)) {
        fwrite(STDERR, "Stale output: {$name}\n");
        exit(1);
    }
}
echo json_encode($counts, JSON_THROW_ON_ERROR)."\n";
