<?php

declare(strict_types=1);

/**
 * Source-only check of Markdown links to local repository paths and headings.
 * Does not bootstrap Laravel, change files, access a network, or run CI.
 * Usage: php scripts/check-documentation-links.php [--check|--summary|--self-test]
 * Scope: Markdown inline destinations and reference definitions, excluding
 * fenced and inline code. Does not parse HTML links or dynamic MDX.
 */
$mode = $argv[1] ?? '--check';
if (count($argv) > 2 || ! in_array($mode, ['--check', '--summary', '--self-test'], true)) {
    fwrite(STDERR, "Usage: php scripts/check-documentation-links.php [--check|--summary|--self-test]\n");
    exit(2);
}

/** @return list<string> */
function documentationFiles(string $root): array
{
    $files = [];
    $exclude = ['.git', 'vendor', 'node_modules', 'storage', 'bootstrap', '.venv', '.idea'];
    $filter = new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $item) use ($exclude): bool {
            return ! $item->isLink() && (! $item->isDir() || ! in_array($item->getFilename(), $exclude, true));
        },
    );
    foreach (new RecursiveIteratorIterator($filter) as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
            $files[] = $file->getPathname();
        }
    }
    sort($files, SORT_STRING);
    return $files;
}

/** @return list<array{destination:string,line:int}> */
function markdownDestinations(string $source): array
{
    $links = [];
    $fence = null;
    foreach (preg_split('/\r\n|\r|\n/', $source) ?: [] as $i => $line) {
        if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $match)) {
            $token = $match[1];
            if ($fence === null) $fence = [$token[0], strlen($token)];
            elseif ($fence[0] === $token[0] && strlen($token) >= $fence[1]) $fence = null;
            continue;
        }
        if ($fence !== null) continue;
        // Inline code may contain literal Markdown syntax used as an example.
        $line = preg_replace('/(`+)(.*?)\1/u', '', $line) ?? $line;
        preg_match_all('/!?\[[^\]]*\]\(\s*(?:<([^>]+)>|([^\s)]+))/', $line, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $links[] = ['destination' => $match[1] !== '' ? $match[1] : $match[2], 'line' => $i + 1];
        }
        if (preg_match('/^\s{0,3}\[[^\]]+\]:\s*(?:<([^>]+)>|(\S+))/', $line, $match)) {
            $links[] = ['destination' => ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? ''), 'line' => $i + 1];
        }
    }
    return $links;
}

function githubHeadingSlug(string $heading): string
{
    $heading = html_entity_decode($heading, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $heading = preg_replace('/<[^>]*>/', '', $heading) ?? $heading;
    $heading = str_replace(['`', '*', '[', ']'], '', $heading);
    $heading = function_exists('mb_strtolower')
        ? mb_strtolower($heading, 'UTF-8')
        : strtolower(strtr($heading, ['Ä' => 'ä', 'Ö' => 'ö', 'Ü' => 'ü', 'ẞ' => 'ß']));
    $heading = preg_replace('/[^\p{L}\p{N}_\- ]/u', '', $heading) ?? $heading;
    return str_replace(' ', '-', $heading);
}

/** @return array<string,bool> */
function markdownAnchors(string $source): array
{
    $anchors = [];
    $duplicates = [];
    $fence = null;
    foreach (preg_split('/\r\n|\r|\n/', $source) ?: [] as $line) {
        if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $match)) {
            $token = $match[1];
            if ($fence === null) $fence = [$token[0], strlen($token)];
            elseif ($fence[0] === $token[0] && strlen($token) >= $fence[1]) $fence = null;
            continue;
        }
        if ($fence !== null || ! preg_match('/^#{1,6}\s+(.+?)\s*#*\s*$/u', $line, $match)) continue;
        $slug = githubHeadingSlug($match[1]);
        $suffix = $duplicates[$slug] ?? 0;
        $anchors[$suffix === 0 ? $slug : $slug.'-'.$suffix] = true;
        $duplicates[$slug] = $suffix + 1;
    }
    return $anchors;
}

/** @return array{file:?string,anchor:?string}|null */
function localDestination(string $root, string $from, string $destination): ?array
{
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $destination) || str_starts_with($destination, '//')) return null;
    [$path, $fragment] = array_pad(explode('#', $destination, 2), 2, null);
    $path = rawurldecode(explode('?', $path, 2)[0]);
    if ($path === '') $target = $from;
    else $target = str_starts_with($path, '/') ? $root.'/'.ltrim($path, '/') : dirname($from).'/'.$path;
    $resolved = realpath($target);
    if ($resolved !== false && ($resolved === $root || str_starts_with($resolved, $root.DIRECTORY_SEPARATOR))) {
        return ['file' => $resolved, 'anchor' => $fragment === null ? null : rawurldecode($fragment)];
    }
    return ['file' => null, 'anchor' => $fragment];
}

function checkDocumentation(string $root): array
{
    $files = documentationFiles($root);
    $checked = 0;
    $anchorsChecked = 0;
    $errors = [];
    $anchorCache = [];
    foreach ($files as $file) {
        $source = file_get_contents($file);
        if ($source === false) throw new RuntimeException("Cannot read {$file}");
        foreach (markdownDestinations($source) as $link) {
            $dest = localDestination($root, $file, $link['destination']);
            if ($dest === null) continue;
            $checked++;
            $display = substr($file, strlen($root) + 1).':'.$link['line'];
            if ($dest['file'] === null) {
                $errors[] = "{$display}: missing target {$link['destination']}";
            } elseif ($dest['anchor'] !== null && $dest['anchor'] !== '' && str_ends_with(strtolower($dest['file']), '.md')) {
                $anchorsChecked++;
                $anchorCache[$dest['file']] ??= markdownAnchors((string) file_get_contents($dest['file']));
                if (! isset($anchorCache[$dest['file']][$dest['anchor']])) {
                    $errors[] = "{$display}: missing heading #{$dest['anchor']} in ".substr($dest['file'], strlen($root) + 1);
                }
            }
        }
    }
    return ['files' => count($files), 'links' => $checked, 'anchors' => $anchorsChecked, 'errors' => $errors];
}

if ($mode === '--self-test') {
    $source = "# Eine Überschrift (14.10.2026)\n# Eine Überschrift (14.10.2026)\n[x](target.md#ein-anker)\n\n```md\n[bad](not-real.md)\n```\n[ref]: <target.md>\n`[also bad](not-real.md)`\n";
    $links = markdownDestinations($source);
    $anchors = markdownAnchors($source);
    if (count($links) !== 2 || $links[0]['destination'] !== 'target.md#ein-anker'
        || $links[1]['destination'] !== 'target.md'
        || ! isset($anchors['eine-überschrift-14102026'])
        || ! isset($anchors['eine-überschrift-14102026-1'])
        || localDestination('/tmp', '/tmp/example.md', 'media:123') !== null) {
        throw new RuntimeException('Markdown link fixture failed');
    }
    echo "PASS inline/reference links, code exclusion, Unicode and duplicate headings, URI schemes\n";
    exit(0);
}

$root = realpath(dirname(__DIR__));
if ($root === false) throw new RuntimeException('Cannot resolve repository root');
$result = checkDocumentation($root);
printf("Markdown files: %d; checked local links: %d; checked headings: %d; errors: %d\n",
    $result['files'], $result['links'], $result['anchors'], count($result['errors']));
foreach ($result['errors'] as $error) fwrite(STDERR, $error."\n");
if ($mode === '--check' && $result['errors'] !== []) exit(1);
