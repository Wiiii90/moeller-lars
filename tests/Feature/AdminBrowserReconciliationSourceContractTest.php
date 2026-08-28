<?php

it('keeps reconciled admin primitives on one canonical authority and one shared load path', function (): void {
    $adminCss = (string) file_get_contents(resource_path('css/admin.css'));
    $dataCss = (string) file_get_contents(resource_path('css/admin/data-workspace.css'));
    $taskCss = (string) file_get_contents(resource_path('css/admin/task-surfaces.css'));
    $customCss = (string) file_get_contents(resource_path('css/admin/custom-page.css'));
    $dashboardCss = (string) file_get_contents(resource_path('css/admin/dashboard.css'));
    $mediaCss = (string) file_get_contents(resource_path('css/admin/media.css'));
    $theme = (string) file_get_contents(resource_path('views/filament/partials/admin-theme.blade.php'));
    $vite = (string) file_get_contents(base_path('vite.config.js'));
    $pagesRow = (string) file_get_contents(resource_path('views/filament/pages/partials/site-section-row.blade.php'));
    $customSequence = (string) file_get_contents(resource_path('views/filament/pages/partials/custom-page-workspace-sequence.blade.php'));
    $journal = (string) file_get_contents(resource_path('views/filament/pages/journal-workspace.blade.php'));
    $consumers = implode("\n", [$pagesRow, $customSequence, $journal]);

    expect(file_exists(resource_path('css/admin/editorial-primitives.css')))->toBeFalse()
        ->and(substr_count($adminCss, "@import './admin/data-workspace.css';"))->toBe(1)
        ->and(substr_count($adminCss, "@import './admin/media.css';"))->toBe(1)
        ->and(substr_count($dataCss, "@import './task-surfaces.css';"))->toBe(1)
        ->and($theme)->not->toContain('data-workspace.css', 'task-surfaces.css', 'editorial-primitives.css', 'admin/media.css')
        ->and($vite)->not->toContain('data-workspace.css', 'task-surfaces.css', 'editorial-primitives.css', 'admin/media.css')
        ->and($adminCss)->not->toContain('.media-workspace__', '.media-file-dialog__', '.media-inspector__')
        ->and($mediaCss)->toContain(
            '.media-workspace__thumb button',
            '.media-workspace__visual',
            '.media-workspace__filename-button',
            '.media-workspace__state.is-available::before',
            '.media-file-dialog__content {',
            '.media-file-dialog__preview {',
            '.media-file-dialog__details {',
            '.media-file-dialog__references {',
        )
        ->and($mediaCss)->not->toContain('.admin-pager', '.admin-position {', '.admin-action--state')
        ->and($taskCss)->toContain('--admin-control-text-inset:')
        ->and($taskCss)->toContain('.admin-position {')
        ->and($taskCss)->toContain('.admin-action--state {')
        ->and($taskCss)->toContain('.admin-action.is-danger {')
        ->and($taskCss)->toContain('.admin-bottom-add {')
        ->and($taskCss)->not->toContain('.admin-pager {')
        ->and($dataCss)->toContain('.admin-pager,')
        ->and($dataCss)->not->toContain('.admin-action.is-danger', '.admin-action--state', '.admin-position {', '.admin-selection')
        ->and($customCss)->not->toContain('.admin-action.is-danger', '.admin-action--state', '.admin-pager')
        ->and($dataCss)->not->toContain('.admin-dashboard', 'dashboard-feed')
        ->and($dashboardCss)->toContain('.admin-dashboard')
        ->and($consumers)->not->toContain('admin-position-badge', 'is-state-toggle', 'admin-action-slot')
        ->and($pagesRow)->toContain('admin-position', 'admin-action--state', 'admin-action is-danger')
        ->and($customSequence)->toContain('admin-position', 'admin-action--state', 'admin-action is-danger')
        ->and($journal)->toContain('admin-position', 'admin-action--state', 'admin-action is-danger');
});

it('keeps strict public CSP sources free of ordinary inline style attributes', function (): void {
    $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
    $appCss = (string) file_get_contents(resource_path('css/app.css'));
    $securityHeaders = (string) file_get_contents(app_path('Http/Middleware/SecurityHeaders.php'));

    expect($layout)->toContain('class="public-preview-badge"')
        ->and($layout)->not->toContain('style="')
        ->and($appCss)->toContain('.public-preview-badge {')
        ->and($appCss)->toContain('.exhibition-entry__map--wide .exhibition-entry__map-frame')
        ->and($appCss)->toContain('.exhibition-entry__map--square .exhibition-entry__map-frame')
        ->and($securityHeaders)->not->toContain("'unsafe-inline'");

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views/pages'), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        expect((string) file_get_contents($file->getPathname()))
            ->not->toContain('style="');
    }
});

it('keeps the central AdminRichText repair as the single shared editor source', function (): void {
    $richText = (string) file_get_contents(app_path('Filament/Support/AdminRichText.php'));
    $journalSchema = (string) file_get_contents(app_path('Filament/Support/JournalEntryEditorSchema.php'));
    $customForms = (string) file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspaceForms.php'));

    expect($richText)->toContain("'x-on:admin-rich-text-image-insert'")
        ->and($richText)->not->toContain("\\$el.addEventListener('admin-rich-text-image-insert'")
        ->and($journalSchema)->toContain('AdminRichText::schema')
        ->and($customForms)->toContain('AdminRichText::schema')
        ->and($customForms)->not->toContain('MarkdownEditor::make');
});
