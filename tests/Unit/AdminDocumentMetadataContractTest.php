<?php

use Filament\Pages\BasePage;

/** @return list<class-string<BasePage>> */
function adminPageClasses(): array
{
    $root = dirname(__DIR__, 2).'/app/Filament';
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

it('never lets an application admin page fall back to its PHP class name for the document title', function (): void {
    $pages = adminPageClasses();

    expect($pages)->not->toBeEmpty();

    foreach ($pages as $page) {
        $reflection = new ReflectionClass($page);
        $getTitle = $reflection->getMethod('getTitle');

        // Filament's specialized record pages own semantic title resolvers such as
        // "View {record}" and "Edit {record}". Only BasePage's generic resolver
        // humanizes a PHP class name when an application page forgot its metadata.
        if ($getTitle->getDeclaringClass()->getName() !== BasePage::class) {
            continue;
        }

        $title = $reflection->getProperty('title')->getValue();

        expect($title, $page.' must declare a canonical document title instead of using Filament\'s class-name fallback.')
            ->toBeString()
            ->not->toBe('');
    }
});

it('keeps admin and public favicon identity separate', function (): void {
    $root = dirname(__DIR__, 2);
    $provider = file_get_contents($root.'/app/Providers/Filament/AdminPanelProvider.php');
    $publicLayout = file_get_contents($root.'/resources/views/layouts/app.blade.php');
    $adminFavicon = file_get_contents($root.'/public/admin-favicon.svg');

    expect($provider)
        ->toContain("->favicon(asset('admin-favicon.svg'))");

    expect($adminFavicon)
        ->toBeString()
        ->not->toBe('');

    expect($publicLayout)
        ->toContain('$faviconVariant')
        ->not->toContain('admin-favicon.svg');
});
