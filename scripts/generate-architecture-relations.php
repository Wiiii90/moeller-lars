<?php

declare(strict_types=1);

/**
 * Source-only relationship graph. No application bootstrap or database.
 *
 * First run: php scripts/generate-architecture-inventory.php --write
 * Then:      php scripts/generate-architecture-relations.php --write
 * Verify:    php scripts/generate-architecture-relations.php --check
 * Fixtures:  php scripts/generate-architecture-relations.php --self-test (no inventory required)
 *
 * Edges are syntactic evidence, not a fully inferred runtime call graph.
 */

$root = dirname(__DIR__);
$mode = $argv[1] ?? '--summary';
if (count($argv) > 2 || ! in_array($mode, ['--summary', '--write', '--check', '--self-test'], true)) {
    fwrite(STDERR, "Usage: php scripts/generate-architecture-relations.php [--write|--check|--self-test]\n");
    exit(2);
}
// Read-only isolated syntax probes; do not need the app inventory or Laravel.
if ($mode === '--self-test') {
    $cases = [
        ['global helpers', '<?php app(A::class); resolve(B::class); \app(C::class); \resolve(D::class);', 4],
        ['method and static calls', '<?php $x->app(A::class); $x?->resolve(B::class); C::resolve(D::class); C::app(E::class);', 0],
        ['declarations', '<?php function app($x){} function /* comment */ & resolve($y){}', 0],
        ['namespaced helpers', '<?php Foo\app(A::class); Other\resolve(B::class);', 0],
        ['mixed calls', '<?php app(A::class); $obj->resolve(B::class); resolve(C::class);', 2],
    ];
    foreach ($cases as [$name, $sourceCode, $expected]) {
        $tokens = graphTokens($sourceCode);
        $found = 0;
        foreach (array_keys($tokens) as $index) {
            if (graphIsServiceLocatorCall($tokens, $index)) {
                $found++;
            }
        }
        if ($found !== $expected) {
            throw new RuntimeException("Service locator fixture {$name}: expected {$expected}, found {$found}");
        }
        echo "PASS {$name} ({$found})\n";
    }
    $constructorFixtures = [
        ['promoted union and attributes', <<<'PHP'
<?php
class Example {
    public function __construct(
        #[Inject(Foo::class, options: [1,2])] private Foo|Bar $service,
        private array $items = [1,2],
        protected readonly ?Baz $target = null,
    ) {}
}
PHP, ['Foo','Bar','Baz']],
        ['intersection and nested defaults', <<<'PHP'
<?php
class Example {
    public function __construct(Foo&Bar $service, private array $values = ['a,b', [1,2]], private Qux $record) {}
}
PHP, ['Foo','Bar','Qux']],
        ['single typed argument', <<<'PHP'
<?php
class Example { public function __construct(private readonly Thing $thing) {} }
PHP, ['Thing']],
        ['attribute is not a type', <<<'PHP'
<?php
class Example { public function __construct(#[Custom] private object $thing) {} }
PHP, ['object']],
    ];
    foreach ($constructorFixtures as [$name, $sourceCode, $expected]) {
        $tokens = graphTokens($sourceCode);
        $open = null;
        $close = null;
        foreach ($tokens as $i => $token) {
            if ($token['text'] === '__construct') {
                $open = graphNext($tokens, $i + 1);
                $close = graphClose($tokens, $open);
                break;
            }
        }
        if ($open === null || $close === null) {
            throw new RuntimeException("Constructor fixture missing delimiters: {$name}");
        }
        $actual = array_column(graphConstructorParameterTypes($tokens, $open, $close), 'name');
        if ($actual !== $expected) {
            throw new RuntimeException("Constructor fixture {$name}: ".json_encode($actual));
        }
        echo "PASS constructor: {$name}\n";
    }
    exit(0);
}

$source = $root.'/docs/architecture/generated/php-symbols.json';
$out = $root.'/docs/architecture/generated';
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
function graphPrevious(array $tokens, int $from): ?int
{
    for ($i = $from; $i >= 0; $i--) {
        if (! in_array($tokens[$i]['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $i;
        }
    }
    return null;
}

/** @param list<array{id:int|string,text:string,line:int}> $tokens */
function graphIsServiceLocatorCall(array $tokens, int $index): bool
{
    $token = $tokens[$index];
    if (! in_array($token['id'], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
        return false;
    }
    $helper = ltrim($token['text'], '\\');
    if (! in_array($helper, ['app', 'resolve'], true)) {
        return false;
    }
    $open = graphNext($tokens, $index + 1);
    if ($open === null || $tokens[$open]['text'] !== '(') {
        return false;
    }
    $previous = graphPrevious($tokens, $index - 1);
    if ($previous !== null && $tokens[$previous]['text'] === '&') {
        $previous = graphPrevious($tokens, $previous - 1);
    }
    if ($previous !== null && in_array($tokens[$previous]['id'], [
        T_FUNCTION, T_FN, T_NEW, T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR,
    ], true)) {
        return false; // Declaration or method/static call, not a global helper.
    }
    return true;
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

/**
 * Extract only parameter type tokens before the first variable. Do not
 * misclassify constructor attributes or default-value expressions as types.
 * Commas inside nested arguments/arrays never start a new parameter.
 *
 * @param list<array{id:int|string,text:string,line:int}> $tokens
 * @return list<array{name:string,line:int}>
 */
function graphConstructorParameterTypes(array $tokens, int $open, int $close): array
{
    $found = [];
    $start = $open + 1;
    $parenthesis = 0;
    $square = 0;
    $braces = 0;
    for ($j = $open + 1; $j <= $close; $j++) {
        $t = $tokens[$j];
        $txt = $t['text'];
        if ($j === $close || ($txt === ',' && $parenthesis === 0 && $square === 0 && $braces === 0)) {
            $attributeDepth = 0;
            for ($k = $start; $k < $j; $k++) {
                $current = $tokens[$k];
                $value = $current['text'];
                if ($current['id'] === T_ATTRIBUTE || $value === '[') {
                    $attributeDepth++;
                    continue;
                }
                if ($value === ']' && $attributeDepth > 0) {
                    $attributeDepth--;
                    continue;
                }
                if ($attributeDepth > 0) continue;
                if ($current['id'] === T_VARIABLE) break;
                if (in_array($current['id'], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    $found[] = ['name' => $value, 'line' => $current['line']];
                }
            }
            $start = $j + 1;
            continue;
        }
        if ($txt === '(') $parenthesis++;
        elseif ($txt === ')') $parenthesis--;
        elseif ($t['id'] === T_ATTRIBUTE || $txt === '[') $square++;
        elseif ($txt === ']') $square--;
        elseif ($txt === '{') $braces++;
        elseif ($txt === '}') $braces--;
    }
    return $found;
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
function graphLiteral(array $tokens, int $start, string $ns, array $aliases, string $selfType): ?string
{
    $s = graphNext($tokens, $start);
    if ($s === null || ! in_array($tokens[$s]['id'], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) return null;
    $colon = graphNext($tokens, $s + 1);
    $class = $colon === null ? null : graphNext($tokens, $colon + 1);
    if ($class === null || $tokens[$colon]['id'] !== T_DOUBLE_COLON || strtolower($tokens[$class]['text']) !== 'class') return null;
    return strtolower($tokens[$s]['text']) === 'self' ? $selfType : graphResolve($tokens[$s]['text'], $ns, $aliases);
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
        if ($target !== null && isset($types[$target]) && ($target !== $type || $kind === 'eloquent_relation')) {
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
                    foreach (graphConstructorParameterTypes($tokens, $open, $close) as $hint) {
                        $emit(
                            graphResolve($hint['name'], $ns, $aliases),
                            'constructor_type',
                            $hint['line'],
                        );
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
                $emit(graphLiteral($tokens, $open + 1, $ns, $aliases, $type), 'eloquent_relation', $t['line'], $tokens[$method]['text']);
            }
        }

        // Explicit service resolution only; unrelated function calls are not inferred.
        if (graphIsServiceLocatorCall($tokens, $i)) {
            $open = graphNext($tokens, $i + 1);
            $emit(graphLiteral($tokens, $open + 1, $ns, $aliases, $type),
                'service_locator', $t['line'], $t['text']);
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
    'limitations' => 'Static named-type evidence only; only unqualified/global app()/resolve() helper calls count as service locators, not method/static calls. No dynamic calls, Laravel container bindings or runtime verification. Files with zero/multiple declarations are skipped.',
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
