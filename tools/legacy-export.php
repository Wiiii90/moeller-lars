<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This exporter is CLI-only.\n");
    exit(64);
}

[$script, $configPath, $outputPath] = array_pad($argv, 3, null);
if (is_string($configPath) === false || is_string($outputPath) === false) {
    fwrite(STDERR, "Usage: php tools/legacy-export.php <mapping.json> <output.json>\n");
    exit(64);
}

$configJson = file_get_contents($configPath);
if (is_string($configJson) === false) {
    throw new RuntimeException('Could not read mapping file.');
}

$config = json_decode($configJson, true, 512, JSON_THROW_ON_ERROR);
if (is_array($config) === false) {
    throw new RuntimeException('Mapping root must be an object.');
}

$dsn = getenv('LEGACY_DB_DSN');
$user = getenv('LEGACY_DB_USER');
$password = getenv('LEGACY_DB_PASSWORD');
if ($dsn === false || trim($dsn) === '' || $user === false || $password === false) {
    throw new RuntimeException('LEGACY_DB_DSN, LEGACY_DB_USER and LEGACY_DB_PASSWORD are required.');
}

$mediaRoot = realpath((string) ($config['media_root'] ?? ''));
if ($mediaRoot === false || is_dir($mediaRoot) === false) {
    throw new RuntimeException('Configured media_root does not exist.');
}

$profileMediaConfig = $config['profile_media'] ?? null;
if (is_array($profileMediaConfig) === false) {
    throw new RuntimeException('Mapping must define profile_media.');
}

$profileMediaPath = str_replace('\\', '/', trim((string) ($profileMediaConfig['path'] ?? '')));
if ($profileMediaPath === ''
    || substr($profileMediaPath, 0, 1) === '/'
    || strpos($profileMediaPath, '../') !== false
    || preg_match('/^[A-Za-z]:[\\\\\/]/', $profileMediaPath) === 1) {
    throw new RuntimeException('profile_media.path must be a safe relative path.');
}

$profileLegacySource = trim((string) ($profileMediaConfig['legacy_source'] ?? ''));
$profileAltText = trim((string) ($profileMediaConfig['alt_text'] ?? ''));
if ($profileLegacySource === '' || $profileAltText === '') {
    throw new RuntimeException('profile_media requires explicit legacy_source and alt_text.');
}

$profileSourcePath = realpath($mediaRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $profileMediaPath));
if ($profileSourcePath === false || is_file($profileSourcePath) === false) {
    throw new RuntimeException("Missing canonical profile media: {$profileMediaPath}");
}
$rootPrefix = rtrim($mediaRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
if (strncmp($profileSourcePath, $rootPrefix, strlen($rootPrefix)) !== 0) {
    throw new RuntimeException('Profile media path escapes configured media_root.');
}

$profileByteSize = filesize($profileSourcePath);
$profileSha256 = hash_file('sha256', $profileSourcePath);
if (is_int($profileByteSize) === false || is_string($profileSha256) === false) {
    throw new RuntimeException('Could not fingerprint profile media.');
}

$manifestProfileMedia = [
    'legacy_source' => $profileLegacySource,
    'media_path' => $profileMediaPath,
    'media_byte_size' => $profileByteSize,
    'media_sha256' => $profileSha256,
    'alt_text' => $profileAltText,
];

$categories = $config['categories'] ?? null;
if (is_array($categories) === false || $categories === []) {
    throw new RuntimeException('Mapping must define at least one category.');
}

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');

$slugify = static function (string $value): string {
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) === false) {
        throw new RuntimeException('Could not transliterate artwork title.');
    }

    $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $ascii), '-'));
    if ($slug === '') {
        throw new RuntimeException('Artwork title cannot produce a slug.');
    }

    return $slug;
};

$nullable = static function ($value): ?string {
    if ($value === null) {
        return null;
    }
    $text = trim((string) $value);

    return $text === '' ? null : $text;
};

$manifestCategories = [];
$homeCandidates = [];

foreach ($categories as $category) {
    if (is_array($category) === false) {
        throw new RuntimeException('Every category mapping must be an object.');
    }

    $table = (string) ($category['table'] ?? '');
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
        throw new RuntimeException("Unsafe legacy table name: {$table}");
    }

    $mediaDirectory = trim((string) ($category['media_directory'] ?? ''), '/\\');
    if ($mediaDirectory === '') {
        throw new RuntimeException("Category {$table} has no media_directory.");
    }

    $statement = $pdo->query("SELECT id, filename, title, date, material, dimension, comment FROM `{$table}`");
    $rows = $statement->fetchAll();
    if ($rows === []) {
        throw new RuntimeException("Legacy table {$table} contains no artwork rows.");
    }

    $byDate = [];
    foreach ($rows as $row) {
        $date = trim((string) ($row['date'] ?? ''));
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new RuntimeException("Legacy {$table} row {$row['id']} has invalid date {$date}.");
        }
        $byDate[$date][] = $row;
    }
    krsort($byDate, SORT_STRING);

    $tieOrder = $category['tie_order'] ?? [];
    if (is_array($tieOrder) === false) {
        throw new RuntimeException("Category {$table} tie_order must be an object.");
    }

    $orderedRows = [];
    foreach ($byDate as $date => $sameDateRows) {
        if (count($sameDateRows) === 1) {
            $orderedRows[] = $sameDateRows[0];

            continue;
        }

        $reviewedIds = $tieOrder[$date] ?? null;
        if (is_array($reviewedIds) === false) {
            $ids = implode(', ', array_map(static fn (array $row): string => (string) $row['id'], $sameDateRows));
            throw new RuntimeException("Ambiguous {$table} date {$date}; reviewed tie_order required for legacy IDs {$ids}.");
        }

        $rowsById = [];
        foreach ($sameDateRows as $row) {
            $rowsById[(string) $row['id']] = $row;
        }
        $expectedIds = array_map('strval', array_keys($rowsById));
        $actualIds = array_map('strval', $reviewedIds);
        sort($expectedIds, SORT_STRING);
        $sortedActualIds = $actualIds;
        sort($sortedActualIds, SORT_STRING);
        if ($expectedIds !== $sortedActualIds || count($actualIds) !== count(array_unique($actualIds))) {
            throw new RuntimeException("Reviewed tie_order for {$table} {$date} does not exactly match the ambiguous legacy rows.");
        }

        foreach ($actualIds as $legacyId) {
            $orderedRows[] = $rowsById[$legacyId];
        }
    }

    $artworks = [];
    foreach ($orderedRows as $position => $row) {
        $legacyId = (int) $row['id'];
        $filename = trim((string) $row['filename']);
        $title = trim((string) $row['title']);
        if ($filename === '' || $title === '') {
            throw new RuntimeException("Legacy {$table} row {$legacyId} is missing filename or title.");
        }
        if (basename(str_replace('\\', '/', $filename)) !== $filename) {
            throw new RuntimeException("Legacy {$table} row {$legacyId} contains a non-basename filename.");
        }

        $relativeMediaPath = str_replace('\\', '/', $mediaDirectory.'/'.$filename);
        $sourcePath = realpath($mediaRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeMediaPath));
        if ($sourcePath === false || is_file($sourcePath) === false) {
            throw new RuntimeException("Missing canonical original for {$table} legacy ID {$legacyId}: {$relativeMediaPath}");
        }
        $rootPrefix = rtrim($mediaRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (strncmp($sourcePath, $rootPrefix, strlen($rootPrefix)) !== 0) {
            throw new RuntimeException("Media path escapes configured media_root for {$table} legacy ID {$legacyId}.");
        }

        $byteSize = filesize($sourcePath);
        $sha256 = hash_file('sha256', $sourcePath);
        if (is_int($byteSize) === false || is_string($sha256) === false) {
            throw new RuntimeException("Could not fingerprint media for {$table} legacy ID {$legacyId}.");
        }

        $date = (string) $row['date'];
        $categorySlug = $slugify((string) ($category['slug'] ?? $table));
        $artwork = [
            'legacy_id' => $legacyId,
            'slug' => $categorySlug.'-'.$slugify($title).'-'.$legacyId,
            'title' => $title,
            'position' => $position,
            'legacy_date_raw' => $date,
            'work_year' => (int) substr($date, 0, 4),
            'work_date' => $date,
            'date_precision' => 'day',
            'medium' => $nullable($row['material']),
            'dimensions' => $nullable($row['dimension']),
            'description' => $nullable($row['comment']),
            'media_path' => $relativeMediaPath,
            'media_byte_size' => $byteSize,
            'media_sha256' => $sha256,
            'alt_text' => $title,
            'featured_on_home' => false,
        ];
        $artworks[] = $artwork;

        if (($category['show_on_home'] ?? false) === true) {
            $homeCandidates[] = [
                'date' => $date,
                'category_index' => count($manifestCategories),
                'artwork_index' => count($artworks) - 1,
                'table' => $table,
                'legacy_id' => $legacyId,
            ];
        }
    }

    $manifestCategories[] = [
        'legacy_source' => $table,
        'slug' => (string) ($category['slug'] ?? ''),
        'name' => (string) ($category['name'] ?? ''),
        'position' => (int) ($category['position'] ?? -1),
        'show_in_navigation' => ($category['show_in_navigation'] ?? false) === true,
        'show_on_home' => ($category['show_on_home'] ?? false) === true,
        'description' => $nullable($category['description'] ?? null),
        'artworks' => $artworks,
    ];
}

if ($homeCandidates !== []) {
    usort($homeCandidates, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));
    $latestDate = $homeCandidates[0]['date'];
    $latest = array_values(array_filter($homeCandidates, static fn (array $candidate): bool => $candidate['date'] === $latestDate));

    if (count($latest) !== 1) {
        throw new RuntimeException("Global legacy home winner is ambiguous at {$latestDate}; resolve the source before export.");
    }

    $winner = $latest[0];
    $manifestCategories[$winner['category_index']]['artworks'][$winner['artwork_index']]['featured_on_home'] = true;
}

$pdo->rollBack();

$manifest = [
    'batch' => 'legacy-'.gmdate('Ymd-His'),
    'profile_media' => $manifestProfileMedia,
    'categories' => $manifestCategories,
];

$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
if (file_put_contents($outputPath, $json."\n", LOCK_EX) === false) {
    throw new RuntimeException('Could not write snapshot manifest.');
}

fwrite(STDOUT, sprintf(
    "Exported %d categories / %d artworks + profile media to %s\n",
    count($manifestCategories),
    array_sum(array_map(static fn (array $category): int => count($category['artworks']), $manifestCategories)),
    $outputPath,
));
