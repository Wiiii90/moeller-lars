<?php

declare(strict_types=1);

/**
 * Eloquent relation diagram from actual Model source, not presumed SQL foreign keys.
 * php scripts/generate-architecture-eloquent.php --write
 * php scripts/generate-architecture-eloquent.php --check
 * php scripts/generate-architecture-eloquent.php --self-test (without repository or DB)
 * No framework bootstrap, network, database, migrations or CI.
 */
$root = dirname(__DIR__);
$mode = $argv[1] ?? '--summary';
if (count($argv) > 2 || ! in_array($mode, ['--summary','--write','--check','--self-test'], true)) {
    fwrite(STDERR, "Usage: php scripts/generate-architecture-eloquent.php [--write|--check|--self-test]\n");
    exit(2);
}
/** @return list<array{id:int|string,text:string,line:int}> */
function eloquentTokens(string $source): array
{
    $tokens = [];
    $line = 1;
    foreach (token_get_all($source, TOKEN_PARSE) as $token) {
        $value = is_array($token) ? $token[1] : $token;
        $tokens[] = [
            'id' => is_array($token) ? $token[0] : $token,
            'text' => $value,
            'line' => is_array($token) ? $token[2] : $line,
        ];
        $line += substr_count($value, "\n");
    }
    return $tokens;
}

/** @param list<array{id:int|string,text:string,line:int}> $tokens */
function eloquentNext(array $tokens, int $start): ?int
{
    for ($i = $start; $i < count($tokens); $i++) {
        if (! in_array($tokens[$i]['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $i;
        }
    }
    return null;
}

/**
 * Deliberately narrow: direct parameterless public methods, matching explicit
 * return type and method, with a literal first model::class argument.
 * Skipped forms are not inferred as SQL constraints or runtime relations.
 * @return list<array{method:string,kind:string,target:string,line:int}>
 */
function eloquentRelationsIn(string $source, string $model): array
{
    $tokens = eloquentTokens($source);
    $relationTypes = [
        'BelongsTo' => 'belongsTo', 'HasOne' => 'hasOne',
        'HasMany' => 'hasMany', 'BelongsToMany' => 'belongsToMany',
    ];
    $relations = [];
    foreach ($tokens as $i => $token) {
        if ($token['id'] !== T_PUBLIC) {
            continue;
        }
        $fn = eloquentNext($tokens, $i + 1);
        if ($fn === null || $tokens[$fn]['id'] !== T_FUNCTION) {
            continue;
        }
        $method = eloquentNext($tokens, $fn + 1);
        if ($method !== null && $tokens[$method]['text'] === '&') {
            $method = eloquentNext($tokens, $method + 1);
        }
        if ($method === null || $tokens[$method]['id'] !== T_STRING) {
            continue;
        }
        $open = eloquentNext($tokens, $method + 1);
        $close = $open === null ? null : eloquentNext($tokens, $open + 1);
        if ($close === null || $tokens[$open]['text'] !== '(' || $tokens[$close]['text'] !== ')') {
            continue;
        }
        $colon = eloquentNext($tokens, $close + 1);
        $type = $colon === null ? null : eloquentNext($tokens, $colon + 1);
        if ($type === null || $tokens[$colon]['text'] !== ':') {
            continue;
        }
        $return = $tokens[$type]['text'];
        if (! isset($relationTypes[$return])) {
            continue;
        }
        $body = eloquentNext($tokens, $type + 1);
        $ret = $body === null ? null : eloquentNext($tokens, $body + 1);
        $thisToken = $ret === null ? null : eloquentNext($tokens, $ret + 1);
        $arrow = $thisToken === null ? null : eloquentNext($tokens, $thisToken + 1);
        $call = $arrow === null ? null : eloquentNext($tokens, $arrow + 1);
        $paren = $call === null ? null : eloquentNext($tokens, $call + 1);
        $target = $paren === null ? null : eloquentNext($tokens, $paren + 1);
        $operator = $target === null ? null : eloquentNext($tokens, $target + 1);
        $class = $operator === null ? null : eloquentNext($tokens, $operator + 1);
        if ($body === null || $ret === null || $thisToken === null || $arrow === null
            || $call === null || $paren === null || $target === null || $operator === null || $class === null) {
            continue;
        }
        if ($tokens[$body]['text'] !== '{' || $tokens[$ret]['id'] !== T_RETURN
            || $tokens[$thisToken]['id'] !== T_VARIABLE || $tokens[$thisToken]['text'] !== '$this'
            || $tokens[$arrow]['id'] !== T_OBJECT_OPERATOR
            || $tokens[$call]['id'] !== T_STRING || $tokens[$call]['text'] !== $relationTypes[$return]
            || $tokens[$paren]['text'] !== '('
            || $tokens[$operator]['id'] !== T_DOUBLE_COLON
            || strtolower($tokens[$class]['text']) !== 'class') {
            continue;
        }
        $targetName = $tokens[$target]['text'];
        if ($targetName === 'self') {
            $targetName = $model;
        } elseif ($tokens[$target]['id'] !== T_STRING || ! preg_match('/^[A-Z][A-Za-z_0-9]*$/', $targetName)) {
            continue;
        }
        $relations[] = [
            'method' => $tokens[$method]['text'],
            'kind' => $tokens[$call]['text'],
            'target' => $targetName,
            'line' => $tokens[$thisToken]['line'],
        ];
    }
    return $relations;
}

if ($mode === '--self-test') {
    $fixtures = [
        ['ordinary and comments', <<<'CODE'
<?php
class Entry {
    /** return $this->belongsTo(Bogus::class); */
    public function owner(): BelongsTo {
        // Comments and whitespace do not affect the declaration.
        return $this
            ->belongsTo(Owner::class, 'owner_id');
    }
}
CODE, [['method'=>'owner','kind'=>'belongsTo','target'=>'Owner','line'=>6]]],
        ['self relation', <<<'CODE'
<?php
class Node {
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
}
CODE, [['method'=>'children','kind'=>'hasMany','target'=>'Node','line'=>3]]],
        ['chained direct relation', <<<'CODE'
<?php
class Media {
    public function entries(): BelongsToMany {
        return $this->belongsToMany(Entry::class)->withPivot('rank');
    }
}
CODE, [['method'=>'entries','kind'=>'belongsToMany','target'=>'Entry','line'=>4]]],
        ['mismatched return and dynamic target', <<<'CODE'
<?php
class Broken {
    public function wrong(): HasMany { return $this->belongsTo(Person::class); }
    public function dynamic(): HasMany { return $this->hasMany($target); }
}
CODE, []],
        ['comments, strings and protected functions', <<<'CODE'
<?php
class Example {
    protected function hidden(): HasOne { return $this->hasOne(Owner::class); }
    public function label(): string { return "return \$this->belongsTo(Fake::class)"; }
}
CODE, []],
    ];
    foreach ($fixtures as [$name, $source, $expected]) {
        $actual = eloquentRelationsIn($source, $name === 'self relation' ? 'Node' : 'Entry');
        if ($actual !== $expected) {
            throw new RuntimeException($name.' expected '.json_encode($expected).' got '.json_encode($actual));
        }
        echo "PASS {$name}\n";
    }
    exit(0);
}

$paths = glob($root.'/app/Models/*.php');
if (! is_array($paths) || $paths === []) throw new RuntimeException('No Eloquent model sources');
sort($paths, SORT_STRING);
$models = [];
$relations = [];
foreach ($paths as $path) {
    $name = pathinfo($path, PATHINFO_FILENAME);
    $relative = 'app/Models/'.$name.'.php';
    $source = file_get_contents($path);
    if (! is_string($source)) throw new RuntimeException("Cannot read {$relative}");
    $models[] = ['name' => $name, 'path' => $relative];
    foreach (eloquentRelationsIn($source, $name) as $rel) {
        $relations[] = [
            'model' => $name, 'method' => $rel['method'], 'kind' => $rel['kind'],
            'target' => $rel['target'], 'path' => $relative, 'line' => $rel['line'],
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
