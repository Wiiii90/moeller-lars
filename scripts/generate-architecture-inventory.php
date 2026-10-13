<?php

declare(strict_types=1);

/**
 * Read-only PHP symbol inventory; no Laravel boot, database, Composer or CI.
 *
 * php scripts/generate-architecture-inventory.php --write
 * php scripts/generate-architecture-inventory.php --check
 * php scripts/generate-architecture-inventory.php --self-test (no repository required)
 *
 * Only --write modifies docs/architecture/generated/php-symbols.json.
 * Imports are syntactic use declarations, not proven runtime dependencies.
 */

$root = dirname(__DIR__);
$outputFile = $root.'/docs/architecture/generated/php-symbols.json';
$arguments = array_slice($argv, 1);
if (count($arguments) > 1 || (count($arguments) === 1 && ! in_array($arguments[0], ['--write', '--check', '--self-test'], true))) {
    fwrite(STDERR, "Usage: php scripts/generate-architecture-inventory.php [--write|--check|--self-test]\n");
    exit(2);
}
$mode = $arguments[0] ?? '--summary';

/** @return list<string> */
function phpPaths(string $root, string $directory): array
{
    $base = $root.'/'.$directory;
    if (! is_dir($base)) {
        throw new RuntimeException("Missing repository directory: {$directory}");
    }

    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $paths[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }
    sort($paths, SORT_STRING);

    return $paths;
}

/** @return list<array{id:int|string,text:string,line:int}> */
function lex(string $source): array
{
    $tokens = token_get_all($source);
    $result = [];
    $line = 1;
    foreach ($tokens as $token) {
        $text = is_array($token) ? $token[1] : $token;
        $result[] = [
            'id' => is_array($token) ? $token[0] : $token,
            'text' => $text,
            'line' => is_array($token) ? $token[2] : $line,
        ];
        $line += substr_count($text, "\n");
    }

    return $result;
}

/** @param list<array{id:int|string,text:string,line:int}> $tokens */
function nextSignificant(array $tokens, int $from): ?int
{
    for ($i = $from; $i < count($tokens); $i++) {
        if (! in_array($tokens[$i]['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $i;
        }
    }

    return null;
}

/** @param list<array{id:int|string,text:string,line:int}> $tokens */
function previousSignificant(array $tokens, int $from): ?int
{
    for ($i = $from; $i >= 0; $i--) {
        if (! in_array($tokens[$i]['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $i;
        }
    }

    return null;
}

/**
 * @return array{types:list<array{fqn:string,kind:string,line:int}>,imports:list<string>}
 */
function symbolsIn(string $source): array
{
    $tokens = lex($source);
    $namespace = '';
    $types = [];
    $imports = [];
    $depth = 0;
    $namespaceDepth = 0;
    $namespaceOpening = null;
    $kinds = [T_CLASS => 'class', T_INTERFACE => 'interface', T_TRAIT => 'trait', T_ENUM => 'enum'];

    for ($i = 0; $i < count($tokens); $i++) {
        $id = $tokens[$i]['id'];
        if ($id === T_NAMESPACE) {
            $name = '';
            for ($j = $i + 1; $j < count($tokens) && ! in_array($tokens[$j]['text'], [';', '{'], true); $j++) {
                if (in_array($tokens[$j]['id'], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                    $name .= $tokens[$j]['text'];
                }
            }
            $namespace = $name;
            $namespaceOpening = isset($tokens[$j]) && $tokens[$j]['text'] === '{' ? $j : null;
            if ($namespaceOpening === null) {
                $namespaceDepth = 0; // Unbracketed namespace declaration.
            }
        }

        // File imports are legal at namespace scope. Closure captures, trait uses
        // and anonymous class traits must never become file-level imports.
        if ($id === T_USE && $depth === $namespaceDepth) {
            $next = nextSignificant($tokens, $i + 1);
            if ($next !== null && $tokens[$next]['text'] !== '(') {
                $expression = '';
                for ($j = $i + 1; $j < count($tokens) && $tokens[$j]['text'] !== ';'; $j++) {
                    $expression .= $tokens[$j]['text'];
                }
                if ($j < count($tokens)) {
                    $imports[] = trim(preg_replace('/\s+/', ' ', $expression) ?? $expression);
                }
            }
        }

        if (is_int($id) && isset($kinds[$id])) {
            $previous = previousSignificant($tokens, $i - 1);
            if (! ($id === T_CLASS && $previous !== null && in_array($tokens[$previous]['id'], [T_NEW, T_DOUBLE_COLON], true))) {
                $next = nextSignificant($tokens, $i + 1);
                if ($next !== null && $tokens[$next]['id'] === T_STRING) {
                    $types[] = [
                        'fqn' => ($namespace !== '' ? $namespace.'\\' : '').$tokens[$next]['text'],
                        'kind' => $kinds[$id],
                        'line' => $tokens[$i]['line'],
                    ];
                }
            }
        }

        // Interpolated strings use T_CURLY_OPEN / T_DOLLAR_OPEN_CURLY_BRACES
        // and a closing '}'. Count them to preserve the namespace depth.
        if ($tokens[$i]['text'] === '{' || in_array($id, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
            $depth++;
            if ($i === $namespaceOpening) {
                $namespaceDepth = $depth;
                $namespaceOpening = null;
            }
        } elseif ($tokens[$i]['text'] === '}') {
            if ($depth === $namespaceDepth && $namespaceDepth > 0) {
                $namespace = '';
                $namespaceDepth = 0;
            }
            $depth--;
        }
    }
    sort($imports, SORT_STRING);

    return ['types' => $types, 'imports' => array_values(array_unique($imports))];
}

// Runs without app/, Composer, Laravel or any database. No generated output.
if ($mode === '--self-test') {
    $fixtures = [
        [
            'name' => 'closure-use-before-type',
            'source' => <<<'PHP'
<?php
namespace App\Example;
use App\Models\User;
$fn = static function () use ($value) { return $value; };
class Example {}
PHP,
            'types' => ['App\Example\Example'],
            'imports' => ['App\Models\User'],
        ],
        [
            'name' => 'bracketed-namespaces-and-traits',
            'source' => <<<'PHP'
<?php
namespace App\First {
    use App\Models\User as Person;
    class Alpha { use SomeTrait; }
}
namespace App\Second {
    use App\Models\Post;
    class Beta { public function run(): void { $fn = static function () use ($x) {}; } }
}
PHP,
            'types' => ['App\First\Alpha', 'App\Second\Beta'],
            'imports' => ['App\Models\Post', 'App\Models\User as Person'],
        ],
        [
            'name' => 'interpolated-string',
            'source' => <<<'PHP'
<?php
namespace App\Example;
use App\Models\Person;
$name = 'World';
$text = "Hello {$name}";
class Example {}
PHP,
            'types' => ['App\Example\Example'],
            'imports' => ['App\Models\Person'],
        ],
        [
            'name' => 'anonymous-class-and-group-import',
            'source' => <<<'PHP'
<?php
namespace App\Example;
use App\Domain\Media\{Asset as A, Variant};
$x = new class { use OtherTrait; };
class MainClass { use LocalTrait; }
PHP,
            'types' => ['App\Example\MainClass'],
            'imports' => ['App\Domain\Media\{Asset as A, Variant}'],
        ],
    ];
    foreach ($fixtures as $fixture) {
        $result = symbolsIn($fixture['source']);
        if (array_column($result['types'], 'fqn') !== $fixture['types']
            || $result['imports'] !== $fixture['imports']) {
            throw new RuntimeException('Tokenizer fixture failed: '.$fixture['name'].' '
                .json_encode($result, JSON_THROW_ON_ERROR));
        }
        echo 'PASS '.$fixture['name']."\n";
    }
    exit(0);
}

$allAppFiles = phpPaths($root, 'app');
$migrations = phpPaths($root, 'database/migrations');
$symbols = [];
$types = [];
$domainModules = [];
foreach ($allAppFiles as $path) {
    $source = file_get_contents($root.'/'.$path);
    if ($source === false) {
        throw new RuntimeException("Cannot read {$path}");
    }
    $found = symbolsIn($source);
    $symbols[] = ['path' => $path, 'types' => $found['types'], 'imports' => $found['imports']];
    foreach ($found['types'] as $type) {
        $types[] = ['path' => $path, ...$type];
    }
    if (preg_match('~^app/Domain/([^/]+)/~', $path, $match) === 1) {
        $domainModules[$match[1]] = ($domainModules[$match[1]] ?? 0) + 1;
    }
}
ksort($domainModules, SORT_STRING);
usort($types, static fn (array $a, array $b): int => [$a['fqn'], $a['path']] <=> [$b['fqn'], $b['path']]);

$data = [
    'format' => 'php-symbol-inventory-v1',
    'limitations' => 'PHP tokenizer: declarations and file-level imports, not resolved runtime calls, container bindings, complete DB schema or framework routes.',
    'counts' => [
        'application_php_files' => count($allAppFiles),
        'declared_php_types' => count($types),
        'eloquent_model_files' => count(array_filter($allAppFiles, static fn (string $p): bool => str_starts_with($p, 'app/Models/'))),
        'migration_php_files' => count($migrations),
    ],
    'domain_module_file_counts' => $domainModules,
    'types' => $types,
    'php_files' => $symbols,
    'migration_files' => $migrations,
];
$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
if ($mode === '--write') {
    $directory = dirname($outputFile);
    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        throw new RuntimeException("Cannot create {$directory}");
    }
    if (file_put_contents($outputFile, $json) === false) {
        throw new RuntimeException("Cannot write {$outputFile}");
    }
    echo "Updated docs/architecture/generated/php-symbols.json\n";
} elseif ($mode === '--check') {
    if (! is_file($outputFile) || file_get_contents($outputFile) !== $json) {
        fwrite(STDERR, "Source inventory missing or stale. Run with --write.\n");
        exit(1);
    }
    echo "PHP source inventory matches checked-in source.\n";
}
echo json_encode($data['counts'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
