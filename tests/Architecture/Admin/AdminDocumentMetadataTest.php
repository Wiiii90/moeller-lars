<?php

use Filament\Pages\BasePage;

/** @return list<class-string<BasePage>> */
function adminDocumentMetadataPageClasses(): array
{
    $root = dirname(__DIR__, 3).'/app/Filament';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $classes = [];

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());
        if (! str_contains($path, '/Pages/')) {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        if (! is_string($source)) {
            continue;
        }

        if (preg_match('/namespace\s+([^;]+);/', $source, $namespace) !== 1) {
            continue;
        }
        if (preg_match('/(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/', $source, $class) !== 1) {
            continue;
        }

        $fqcn = $namespace[1].'\\'.$class[1];
        if (! class_exists($fqcn) || ! is_subclass_of($fqcn, BasePage::class)) {
            continue;
        }

        /** @var class-string<BasePage> $fqcn */
        $classes[] = $fqcn;
    }

    sort($classes);

    return $classes;
}

it('requires application admin pages to provide semantic document titles', function (): void {
    $pages = adminDocumentMetadataPageClasses();

    expect($pages)->not->toBeEmpty();

    foreach ($pages as $page) {
        $reflection = new ReflectionClass($page);
        $getTitle = $reflection->getMethod('getTitle');

        if ($getTitle->getDeclaringClass()->getName() !== BasePage::class) {
            continue;
        }

        $title = $reflection->getProperty('title')->getValue();

        expect($title)->toBeString()->not->toBe('');
    }
});
