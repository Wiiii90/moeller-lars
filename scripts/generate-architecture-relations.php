<?php

declare(strict_types=1);

/**
 * Source-only relationship graph. No application bootstrap or database.
 *
 * First run: php scripts/generate-architecture-inventory.php --write
 * Then:      php scripts/generate-architecture-relations.php --write
 * Verify:    php scripts/generate-architecture-relations.php --check
 *
 * Edges are syntactic evidence, not a fully inferred runtime call graph.
 */

$root = dirname(__DIR__);
$source = $root.'/docs/architecture/generated/php-symbols.json';
$out = $root.'/docs/architecture/generated';
$mode = $argv[1] ?? '--summary';
if (count($argv) > 2 || ! in_array($mode, ['--summary', '--write', '--check'], true)) {
    fwrite(STDERR, "Usage: php scripts/generate-architecture-relations.php [--write|--check]\n");
    exit(2);
}
if (! is_file($source)) {
    fwrite(STDERR, "Missing PHP inventory. Run generate-architecture-inventory.php --write first.\n");
    exit(1);
}
$inventory = json_decode((string) file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);
$types = [];
foreach ($inventory['types'] as $type) {
    $types[$type['fqn']] = $type;
}

/** @return list<array{id:int|string,text:string,line:int}> */
function graphTokens(string $source): array
{
    $result = [];
    $line = 1;
    foreach (token_get_all($source) as $item) {
        $text = is_array($item) ? $item[1] : $item;
        $result[] = ['id' => is_array($item) ? $item[0] : $item,
            'text' => $text, 'line' => is_array($item) ? $item[2] : $line];
        $line += substr_count($text, "\n");
    }
    return $result;
}

/** @param list<array{id:int|string,text:string,line:int}> $tokens */
function graphNext(array $tokens, int $from): ?int
{
    for ($i = $from; $i < count($tokens); $i++) {
        if (! in_array($tokens[$i]['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $i;
        }
    }
    return null;
}

/** @param list<array{id:int|string,text:string,line:int}> $tokens */
function graphClose(array $tokens, int $at): ?int
{
    $depth = 0;
    for ($i = $at; $i < count($tokens); $i++) {
        if ($tokens[$i]['text'] === '(') $depth++;
        if ($tokens[$i]['text'] === ')' && --$depth === 0) return $i;
    }
    return null;
}

/** @return array<string,string> */
function graphAliases(array $imports): array
{
    $aliases = [];
    foreach ($imports as $import) {
        if (preg_match('/^(function|const)\s/i', $import)) continue;
        if (preg_match('/^([^{}]+)\{([^{}]+)\}$/', $import, $m)) {
            $parts = array_map(fn ($p) => rtrim(trim($m[1]), '\\'). '\\'.trim($p), explode(',', $m[2]));
        } else {
            $parts = explode(',', $import);
        }
        foreach ($parts as $part) {
            if (! preg_match('/^([\w\\\\]+)(?:\s+as\s+(\w+))?$/i', trim($part), $m)) continue;
            $fqn = ltrim($m[1], '\\');
            $alias = $m[2] ?? substr(strrchr('\\'.$fqn, '\\'), 1);
            $aliases[strtolower($alias)] = $fqn;
        }
    }
    return $aliases;
}

/** @param array<string,string> $aliases */
function graphResolve(string $symbol, string $ns, array $aliases): ?string
{
    if (in_array(strtolower($symbol), ['self', 'static', 'parent', 'string', 'int', 'float', 'bool',
        'array', 'object', 'iterable', 'mixed', 'callable', 'void', 'null', 'false', 'true', 'never'], true)) return null;
    if (str_starts_with($symbol, '\\')) return ltrim($symbol, '\\');
    $parts = explode('\\', $symbol);
    $alias = $aliases[strtolower($parts[0])] ?? null;
    if ($alias !== null) {
        array_shift($parts);
        return $alias.($parts ? '\\'.implode('\\', $parts) : '');
    }
    return $ns === '' ? $symbol : $ns.'\\'.$symbol;
}

/** @param list<array{id:int|string,text:string,line:int}> $tokens */
function graphLiteral(array $tokens, int $start, string $ns, array $aliases): ?string
{
    $s = graphNext($tokens, $start);
    if ($s === null || ! in_array($tokens[$s]['id'], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) return null;
    $colon = graphNext($tokens, $s + 1);
    $class = $colon === null ? null : graphNext($tokens, $colon + 1);
    if ($class === null || $tokens[$colon]['id'] !== T_DOUBLE_COLON || strtolower($tokens[$class]['text']) !== 'class') return null;
    return graphResolve($tokens[$s]['text'], $ns, $aliases);
}

/** @return string */
function graphModule(string $type): string
{
    $parts = explode('\\', $type);
    return ($parts[1] ?? 'unknown') === 'Domain' ? 'Domain/'.($parts[2] ?? 'unknown') : ($parts[1] ?? 'unknown');
}

$edges = [];
$skipped = [];
foreach ($inventory['php_files'] as $record) {
    $path = $record['path'];
    $code = file_get_contents($root.'/'.$path);
    if ($code === false) throw new RuntimeException("Missing source: {$path}");
    $tokens = graphTokens($code);
    $aliases = graphAliases($record['imports']);
    $declared = $record['types'];
    if (count($declared) !== 1) {
        $skipped[] = ['path' => $path, 'reason' => 'No single unambiguous named type'];
        continue;
    }
    $type = $declared[0]['fqn'];
    $ns = str_contains($type, '\\') ? substr($type, 0, strrpos($type, '\\')) : '';
    $emit = static function (?string $target, string $kind, int $line, string $detail = '') use (&$edges, $types, $type, $path): void {
        if ($target !== null && isset($types[$target]) && $target !== $type) {
            $edges[] = ['from' => $type, 'to' => $target, 'kind' => $kind,
                'source' => $path, 'line' => $line, 'detail' => $detail];
        }
    };

    $depth = 0;
    $classDepth = null;
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (in_array($t['id'], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            $name = graphNext($tokens, $i + 1);
            if ($name !== null && $tokens[$name]['id'] === T_STRING) {
                $relation = '';
                for ($j = $name + 1; $j < $n && ! in_array($tokens[$j]['text'], ['{', ';'], true); $j++) {
                    if ($tokens[$j]['id'] === T_EXTENDS) $relation = 'extends';
                    elseif ($tokens[$j]['id'] === T_IMPLEMENTS) $relation = 'implements';
                    elseif ($relation !== '' && in_array($tokens[$j]['id'], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                        $emit(graphResolve($tokens[$j]['text'], $ns, $aliases), $relation, $tokens[$j]['line']);
                    }
                }
                if ($j < $n && $tokens[$j]['text'] === '{') $classDepth = $depth + 1;
            }
        }

        // Trait declarations at class depth, excluding closure use(...).
        if ($classDepth !== null && $depth === $classDepth && $t['id'] === T_USE) {
            for ($j = $i + 1; $j < $n && ! in_array($tokens[$j]['text'], [';', '{'], true); $j++) {
                if (in_array($tokens[$j]['id'], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    $emit(graphResolve($tokens[$j]['text'], $ns, $aliases), 'trait_use', $tokens[$j]['line']);
                }
            }
        }

        // Constructor type-hints: strong structural dependency, even if unused at runtime.
        if ($classDepth !== null && $depth === $classDepth && $t['id'] === T_FUNCTION) {
            $name = graphNext($tokens, $i + 1);
            if ($name !== null && $tokens[$name]['text'] === '&') $name = graphNext($tokens, $name + 1);
            if ($name !== null && strtolower($tokens[$name]['text']) === '__construct') {
                $open = graphNext($tokens, $name + 1);
                $close = $open !== null && $tokens[$open]['text'] === '(' ? graphClose($tokens, $open) : null;
                if ($close !== null) {
                    $start = $open + 1;
                    for ($j = $start; $j <= $close; $j++) {
                        if ($j === $close || $tokens[$j]['text'] === ',') {
                            $variable = null;
                            for ($k = $start; $k < $j; $k++) if ($tokens[$k]['id'] === T_VARIABLE) { $variable = $k; break; }
                            if ($variable !== null) {
                                for ($k = $start; $k < $variable; $k++) {
                                    if (in_array($tokens[$k]['id'], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)
                                        && ! in_array(strtolower($tokens[$k]['text']), ['public','private','protected','readonly'], true)) {
                                        $emit(graphResolve($tokens[$k]['text'], $ns, $aliases), 'constructor_type', $tokens[$k]['line']);
                                    }
                                }
                            }
                            $start = $j + 1;
                        }
                    }
                }
            }
        }

        // Eloquent relation calls with literal targets; dynamic relations remain unmodeled.
        if ($t['id'] === T_VARIABLE && $t['text'] === '$this') {
            $arrow = graphNext($tokens, $i + 1);
            $method = $arrow !== null ? graphNext($tokens, $arrow + 1) : null;
            $open = $method !== null ? graphNext($tokens, $method + 1) : null;
            if ($arrow !== null && $tokens[$arrow]['id'] === T_OBJECT_OPERATOR && $method !== null && $open !== null
                && $tokens[$open]['text'] === '('
                && in_array($tokens[$method]['text'], ['belongsTo','belongsToMany','hasOne','hasMany','hasOneThrough','hasManyThrough','morphOne','morphMany','morphToMany','morphedByMany'], true)) {
                $emit(graphLiteral($tokens, $open + 1, $ns, $aliases), 'eloquent_relation', $t['line'], $tokens[$method]['text']);
            }
        }

        // Explicit service resolution only; unrelated function calls are not inferred.
        if ($t['id'] === T_STRING && in_array($t['text'], ['app', 'resolve'], true)) {
            $open = graphNext($tokens, $i + 1);
            $prev = $i ? $tokens[$i - 1]['id'] : null;
            if ($open !== null && $tokens[$open]['text'] === '(' && $prev !== T_FUNCTION) {
                $emit(graphLiteral($tokens, $open + 1, $ns, $aliases), 'service_locator', $t['line'], $t['text']);
            }
        }

        if ($t['text'] === '{') $depth++;
        elseif ($t['text'] === '}') {
            if ($classDepth !== null && $depth === $classDepth) $classDepth = null;
            $depth--;
        }
    }
}
usort($edges, static fn (array $a, array $b): int => [$a['from'],$a['kind'],$a['to'],$a['source'],$a['line']]
    <=> [$b['from'],$b['kind'],$b['to'],$b['source'],$b['line']]);
$unique = [];
foreach ($edges as $edge) $unique[implode('|', [$edge['from'],$edge['to'],$edge['kind'],$edge['source'],$edge['line'],$edge['detail']])] = $edge;
$edges = array_values($unique);
$modules = [];
foreach ($edges as $edge) {
    $a = graphModule($edge['from']);
    $b = graphModule($edge['to']);
    if ($a !== $b) $modules[$a][$b] = ($modules[$a][$b] ?? 0) + 1;
}
ksort($modules);
foreach ($modules as &$targets) ksort($targets);
unset($targets);
$payload = ['format' => 'php-relationships-v1',
    'limitations' => 'Static named-type evidence only. No dynamic calls, Laravel container bindings or runtime verification. Files with zero/multiple declarations are skipped.',
    'source_inventory_format' => $inventory['format'],
    'counts' => ['relations' => count($edges), 'skipped_files' => count($skipped)],
    'relationships' => $edges, 'skipped_files' => $skipped, 'module_references' => $modules];
$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
$nodes = [];
foreach ($modules as $a => $targets) { $nodes[$a] = true; foreach ($targets as $b => $_) $nodes[$b] = true; }
ksort($nodes);
$identities = [];
$diagram = ['%% Generated from explicit PHP syntax; counts are references, not runtime calls.', 'flowchart LR'];
foreach (array_keys($nodes) as $name) {
    $id = 'M'.(count($identities) + 1);
    $identities[$name] = $id;
    $diagram[] = '    '.$id.'["'.$name.'"]';
}
foreach ($modules as $a => $targets) foreach ($targets as $b => $count) {
    $diagram[] = '    '.$identities[$a].' -->|'.$count.'| '.$identities[$b];
}
$generated = ['php-relationships.json' => $json, 'module-relationships.mmd' => implode("\n", $diagram)."\n"];
foreach ($generated as $name => $content) {
    $path = $out.'/'.$name;
    if ($mode === '--write') {
        if (! is_dir($out) && ! mkdir($out, 0775, true) && ! is_dir($out)) throw new RuntimeException("Could not create {$out}");
        if (file_put_contents($path, $content) === false) throw new RuntimeException("Could not write {$path}");
    } elseif ($mode === '--check' && (! is_file($path) || file_get_contents($path) !== $content)) {
        fwrite(STDERR, "Missing/stale {$name}; run --write.\n");
        exit(1);
    }
}
echo json_encode($payload['counts'], JSON_THROW_ON_ERROR)."\n";
