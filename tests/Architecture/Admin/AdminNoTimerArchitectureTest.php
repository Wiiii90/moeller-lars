<?php

it('keeps authored application runtime free of timer and debounce scheduling', function (): void {
    $root = dirname(__DIR__, 3);
    $scanRoots = [
        $root.'/app',
        $root.'/resources/views',
        $root.'/resources/js',
        $root.'/resources/scripts',
    ];
    $forbidden = [
        'wire:model.live.debounce.',
        '.debounce.',
        '->debounce(',
        'searchDebounce(',
        'setTimeout(',
        'setInterval(',
        'requestAnimationFrame(',
        'cancelAnimationFrame(',
        'AbortSignal.timeout(',
        'requestIdleCallback(',
        'queueMicrotask(',
    ];
    $extensions = ['php', 'js', 'mjs'];
    $violations = [];

    foreach ($scanRoots as $scanRoot) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $isBlade = str_ends_with($path, '.blade.php');
            $extension = $file->getExtension();
            if (! $isBlade && ! in_array($extension, $extensions, true)) {
                continue;
            }

            $source = file_get_contents($path);
            if ($source === false) {
                continue;
            }

            foreach ($forbidden as $needle) {
                if (str_contains($source, $needle)) {
                    $violations[] = str_replace($root.DIRECTORY_SEPARATOR, '', $path).' -> '.$needle;
                }
            }
        }
    }

    sort($violations);

    expect($violations)->toBe([]);
});
