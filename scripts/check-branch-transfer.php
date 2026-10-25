<?php

declare(strict_types=1);

$arguments = getopt('', ['base:', 'candidate:', 'target:']);
$base = $arguments['base'] ?? '';
$candidate = $arguments['candidate'] ?? '';
$target = $arguments['target'] ?? '';
$allowed = ['dev', 'docs', 'testing', 'refactor', 'main'];

function failure(string $message): never
{
    fwrite(STDERR, "Transfer rejected: {$message}\n");
    exit(1);
}

function git(array $args): string
{
    $process = proc_open(
        array_merge(['git'], $args),
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    if (!is_resource($process)) {
        failure('Git could not be started.');
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        failure(trim($error) !== '' ? trim($error) : 'Git command failed.');
    }

    return trim((string) $output);
}

foreach (['base' => $base, 'candidate' => $candidate] as $label => $value) {
    if (!is_string($value) || preg_match('/\A[0-9a-f]{40}\z/i', $value) !== 1) {
        failure("{$label} must be a full 40-character commit SHA.");
    }

    git(['cat-file', '-e', "{$value}^{commit}"]);
}

if (!is_string($target) || !in_array($target, $allowed, true)) {
    failure('target must be dev, docs, testing, refactor or main.');
}

git(['merge-base', '--is-ancestor', $base, $candidate]);
$history = git(['rev-list', '--reverse', "{$base}..{$candidate}"]);

foreach (array_filter(explode("\n", $history)) as $sha) {
    $parents = preg_split('/\s+/', git(['rev-list', '--parents', '-n', '1', $sha]));
    if (count($parents) !== 2) {
        failure("{$sha} is a merge or root commit. Use isolated integration commits.");
    }

    $changed = git(['diff-tree', '--no-commit-id', '--name-only', '-r', $sha]);
    foreach (array_filter(explode("\n", $changed)) as $path) {
        if ($path === 'AGENTS.md'
            || $path === '.agents/roster.md'
            || str_starts_with($path, '.agents/areas/')
            || str_starts_with($path, '.agents/workers/')
            || str_starts_with($path, '.agents/topics/')) {
            failure("{$sha} changes branch-local path {$path}.");
        }
    }
}

$files = git(['ls-tree', '-r', '--name-only', $candidate]);

foreach (array_filter(explode("\n", $files)) as $path) {
    if ($target === 'main' && $path === '.agents/roster.md') {
        failure('Branch-local worker roster must not reach main.');
    }

    if (str_starts_with($path, '.agents/areas/')) {
        if ($target === 'main' || $path !== ".agents/areas/{$target}.md") {
            failure("{$path} does not belong on {$target}.");
        }
    }

    if (str_starts_with($path, '.agents/workers/')) {
        if ($target === 'main'
            || preg_match('~\A\.agents/workers/'.preg_quote($target, '~').'-[1-9][0-9]*\.md\z~', $path) !== 1) {
            failure("{$path} does not belong on {$target}.");
        }
    }

    if ($target === 'main' && str_starts_with($path, '.agents/topics/')) {
        failure("Temporary topic {$path} must not reach main.");
    }
}

fwrite(STDOUT, "Transfer boundary verified for {$target}: {$base} -> {$candidate}.\n");
