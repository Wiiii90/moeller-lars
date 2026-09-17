<?php

it('keeps legacy cv persistence outside the active runtime and admin surface', function (): void {
    $root = dirname(__DIR__, 2);

    expect(is_dir($root.'/app/Filament/Resources/CvEntries'))->toBeFalse()
        ->and(is_dir($root.'/resources/views/filament/resources/cv-entries'))->toBeFalse()
        ->and(is_file($root.'/app/Filament/Widgets/ContactHealth.php'))->toBeFalse()
        ->and(is_file($root.'/resources/views/filament/widgets/contact-health.blade.php'))->toBeFalse();

    $model = file_get_contents($root.'/app/Models/CvEntry.php');
    expect($model)
        ->toContain('Historical migration compatibility model')
        ->not->toContain('SafeRichTextRenderer')
        ->not->toContain('SafeLinkPolicy')
        ->not->toContain('ValidationException')
        ->not->toContain('function booted');

    $allowed = [
        'app/Domain/Migration/',
        'app/Models/CvEntry.php',
        'app/Models/MediaAsset.php',
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (collect($allowed)->contains(fn (string $path): bool => str_ends_with($path, '/')
            ? str_starts_with($relative, $path)
            : $relative === $path)) {
            continue;
        }

        expect(file_get_contents($file->getPathname()), $relative)->not->toContain('CvEntry');
    }

    $provider = file_get_contents($root.'/app/Providers/Filament/AdminPanelProvider.php');
    expect($provider)->not->toContain('ContactHealth');
});
