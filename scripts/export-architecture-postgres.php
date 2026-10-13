<?php

declare(strict_types=1);

/**
 * Read-only PostgreSQL catalog exporter. Does NOT run migrations or modify DB.
 * Use ONLY on an isolated, fully migrated disposable database.
 *
 * ARCH_DOC_DB_DSN='pgsql:host=127.0.0.1;port=5432;dbname=moeller_docs'
 * ARCH_DOC_DB_USER='...' ARCH_DOC_DB_PASSWORD='...' ARCH_DOC_DB_DISPOSABLE=1
 * php scripts/export-architecture-postgres.php --write
 * php scripts/export-architecture-postgres.php --check
 */

$root = dirname(__DIR__);
$mode = $argv[1] ?? '--summary';
if (count($argv) > 2 || ! in_array($mode, ['--summary', '--write', '--check'], true)) {
    fwrite(STDERR, "Usage: php scripts/export-architecture-postgres.php [--write|--check]\n");
    exit(2);
}
$dsn = getenv('ARCH_DOC_DB_DSN');
if (getenv('ARCH_DOC_DB_DISPOSABLE') !== '1' || ! is_string($dsn) || ! str_starts_with($dsn, 'pgsql:')) {
    fwrite(STDERR, "Refusing catalog access: set ARCH_DOC_DB_DISPOSABLE=1 and ARCH_DOC_DB_DSN=pgsql:... for an isolated migrated database.\n");
    exit(2);
}
$pdo = new PDO($dsn, getenv('ARCH_DOC_DB_USER') ?: '', getenv('ARCH_DOC_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT => 5,
]);
$pdo->beginTransaction();
$pdo->exec('SET TRANSACTION READ ONLY');
$queries = [
    'relations' => <<<'SQL'
        SELECT n.nspname AS schema_name, c.relname AS relation_name, c.relkind AS kind
        FROM pg_catalog.pg_class c
        JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname IN ('public', 'committed')
          AND c.relkind IN ('r', 'p', 'v', 'm')
          AND c.relname NOT LIKE 'pg_%'
        ORDER BY n.nspname, c.relname
        SQL,
    'columns' => <<<'SQL'
        SELECT table_schema AS schema_name, table_name AS relation_name,
               ordinal_position::int AS ordinal, column_name,
               data_type, udt_name, is_nullable, column_default,
               is_identity, is_generated
        FROM information_schema.columns
        WHERE table_schema IN ('public', 'committed')
        ORDER BY table_schema, table_name, ordinal_position
        SQL,
    'constraints' => <<<'SQL'
        SELECT n.nspname AS schema_name, r.relname AS relation_name,
               c.conname AS name, c.contype AS kind,
               pg_catalog.pg_get_constraintdef(c.oid, true) AS definition,
               rn.nspname AS target_schema, rr.relname AS target_relation
        FROM pg_catalog.pg_constraint c
        JOIN pg_catalog.pg_class r ON r.oid = c.conrelid
        JOIN pg_catalog.pg_namespace n ON n.oid = r.relnamespace
        LEFT JOIN pg_catalog.pg_class rr ON rr.oid = c.confrelid AND c.contype = 'f'
        LEFT JOIN pg_catalog.pg_namespace rn ON rn.oid = rr.relnamespace
        WHERE n.nspname IN ('public', 'committed')
        ORDER BY n.nspname, r.relname, c.contype, c.conname
        SQL,
    'indexes' => <<<'SQL'
        SELECT schemaname AS schema_name, tablename AS relation_name,
               indexname AS name, indexdef AS definition
        FROM pg_catalog.pg_indexes
        WHERE schemaname IN ('public', 'committed')
        ORDER BY schemaname, tablename, indexname
        SQL,
    'views' => <<<'SQL'
        SELECT schemaname AS schema_name, viewname AS name, definition
        FROM pg_catalog.pg_views
        WHERE schemaname IN ('public', 'committed')
        ORDER BY schemaname, viewname
        SQL,
    'triggers' => <<<'SQL'
        SELECT n.nspname AS schema_name, r.relname AS relation_name, t.tgname AS name,
               pg_catalog.pg_get_triggerdef(t.oid, true) AS definition
        FROM pg_catalog.pg_trigger t
        JOIN pg_catalog.pg_class r ON r.oid = t.tgrelid
        JOIN pg_catalog.pg_namespace n ON n.oid = r.relnamespace
        WHERE n.nspname IN ('public', 'committed') AND NOT t.tgisinternal
        ORDER BY n.nspname, r.relname, t.tgname
        SQL,
];
$result = ['format' => 'postgresql-schema-catalog-v1',
    'source' => 'PostgreSQL pg_catalog / information_schema (read-only; fully migrated isolated database)',
    'warnings' => ['No runtime data, application configuration, privileges, policy state, or secrets exported.'],
];
foreach ($queries as $name => $sql) {
    $result[$name] = $pdo->query($sql)->fetchAll();
}
$pdo->rollBack();
$byName = [];
foreach ($result['relations'] as $row) {
    $byName[$row['schema_name'].'.'.$row['relation_name']] = true;
}
$mermaid = ['%% Generated from catalog FK constraints; arrows do not claim cardinalities.', 'flowchart LR'];
$ids = [];
foreach (array_keys($byName) as $fullName) {
    $id = 'T'.(count($ids) + 1);
    $ids[$fullName] = $id;
    $mermaid[] = '    '.$id.'["'.str_replace('"', '', $fullName).'"]';
}
foreach ($result['constraints'] as $item) {
    if ($item['kind'] !== 'f') continue;
    $from = $item['schema_name'].'.'.$item['relation_name'];
    $to = $item['target_schema'].'.'.$item['target_relation'];
    if (isset($ids[$from], $ids[$to])) {
        $label = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $item['name']);
        $mermaid[] = '    '.$ids[$from].' -->|'.$label.'| '.$ids[$to];
    }
}
$generated = [
    'postgresql-catalog.json' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
    'postgresql-fk-graph.mmd' => implode("\n", $mermaid)."\n",
];
$directory = $root.'/docs/architecture/generated';
foreach ($generated as $name => $content) {
    $path = $directory.'/'.$name;
    if ($mode === '--write') {
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create generated directory");
        }
        if (file_put_contents($path, $content) === false) throw new RuntimeException("Cannot write {$name}");
    } elseif ($mode === '--check' && (! is_file($path) || file_get_contents($path) !== $content)) {
        fwrite(STDERR, "Stale or missing {$name}; run --write on the isolated database.\n");
        exit(1);
    }
}
$counts = [];
foreach (['relations','columns','constraints','indexes','views','triggers'] as $section) {
    $counts[$section] = count($result[$section]);
}
echo json_encode($counts, JSON_THROW_ON_ERROR)."\n";
