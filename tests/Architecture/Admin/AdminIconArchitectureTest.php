<?php

it('keeps shared admin action icons on the semantic icon catalog', function (): void {
    $root = dirname(__DIR__, 3);
    $catalog = file_get_contents($root.'/app/Filament/Support/AdminIcon.php');
    $rowActions = file_get_contents($root.'/app/Filament/Support/AdminRowAction.php');
    $dialogs = file_get_contents($root.'/app/Filament/Support/Dialogs/AdminDialog.php');

    expect($catalog)
        ->toContain("case DialogSubmit = 'heroicon-o-check';")
        ->toContain("case Commit = 'heroicon-o-check-circle';")
        ->toContain("case Edit = 'heroicon-o-pencil-square';")
        ->toContain("case Delete = 'heroicon-o-trash';")
        ->toContain("case Remove = 'heroicon-o-x-mark';")
        ->toContain("case Detach = 'heroicon-o-link-slash';")
        ->toContain("case OpenPublic = 'heroicon-o-arrow-top-right-on-square';")
        ->toContain("case MarkRead = 'heroicon-o-envelope-open';")
        ->toContain("case MarkUnread = 'heroicon-o-envelope';")
        ->not->toContain("case DialogSubmit = 'heroicon-o-check-circle';");

    expect($rowActions)
        ->toContain('AdminIcon::MoveUp')
        ->toContain('AdminIcon::MoveDown')
        ->toContain('AdminIcon::Edit')
        ->toContain('AdminIcon::Publish')
        ->toContain('AdminIcon::Unpublish')
        ->toContain('AdminIcon::Delete')
        ->toContain('AdminIcon::MarkRead')
        ->toContain('AdminIcon::MarkUnread');

    expect($dialogs)
        ->toContain('AdminIcon::DialogSubmit')
        ->toContain('AdminIcon::Delete');
});

it('does not hard code heroicons outside the shared admin icon catalog', function (): void {
    $root = dirname(__DIR__, 3);
    $catalogPath = realpath($root.'/app/Filament/Support/AdminIcon.php');
    $scanRoots = [
        $root.'/app/Filament',
        $root.'/app/Providers/Filament',
        $root.'/resources/views/filament',
        $root.'/resources/views/components/admin',
    ];
    $violations = [];

    foreach ($scanRoots as $scanRoot) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $path = $file->getPathname();
            if (realpath($path) === $catalogPath) {
                continue;
            }

            $source = file_get_contents($path);
            if ($source !== false && preg_match('/heroicon-[oms]-[a-z0-9-]+/', $source) === 1) {
                $violations[] = str_replace($root.DIRECTORY_SEPARATOR, '', $path);
            }
        }
    }

    sort($violations);

    expect($violations)->toBe([]);
});

it('keeps Gallery icon action geometry in the shared admin action contract', function (): void {
    $root = dirname(__DIR__, 3);
    $shared = file_get_contents($root.'/resources/css/admin.css');
    $gallery = file_get_contents($root.'/resources/css/admin/gallery.css');

    expect($shared)
        ->toContain('Canonical compact icon-action grammar shared by cards and data rows')
        ->toContain('.gallery-workspace__icon-action,')
        ->toContain('.gallery-workspace__order-action')
        ->toContain('.gallery-workspace__icon-action svg');

    expect($gallery)
        ->not->toContain(".gallery-workspace__icon-action {\n")
        ->not->toContain('.gallery-workspace__icon-action svg')
        ->not->toContain('.gallery-workspace__icon-action:hover')
        ->not->toContain('.gallery-workspace__icon-action:focus-visible')
        ->not->toContain('.gallery-workspace__icon-action:disabled');
});
