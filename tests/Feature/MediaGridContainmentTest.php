<?php

it('keeps mixed-aspect media previews contained and alternative views on one task baseline', function (): void {
    $css = file_get_contents(resource_path('css/admin/media.css'));
    $view = file_get_contents(resource_path('views/filament/resources/media-assets/pages/list-media-assets.blade.php'));

    expect($css)->toBeString()
        ->and($css)->toContain('grid-template-columns: repeat(5, minmax(0, 1fr));')
        ->and($css)->toContain('grid-template-rows: auto minmax(0, 1fr) auto;')
        ->and($css)->toContain('contain: layout paint;')
        ->and($css)->toContain('isolation: isolate;')
        ->and($css)->toContain(".media-workspace__visual img,\n.media-workspace__visual video")
        ->and($css)->toContain('max-width: 100%;')
        ->and($css)->toContain('max-height: 100%;')
        ->and($css)->toContain('object-fit: contain;')
        ->and($css)->toContain('object-position: center;')
        ->and($css)->toContain('align-self: end;')
        ->and($css)->toContain('--media-task-surface-header-height: 2.25rem;')
        ->and($css)->toContain('margin-top: var(--media-task-surface-header-height);')
        ->and($view)->toBeString()
        ->and($view)->not->toContain('placeholder=')
        ->and($view)->not->toContain('Filter · Media type')
        ->and($view)->toContain('<span>Type</span>')
        ->and($view)->toContain('media-workspace__control-label">Filter</span>')
        ->and($view)->toContain('>Reset</button>')
        ->and($view)->toContain('>Multi-action')
        ->and($view)->toContain('media-workspace__control-label">View</span>')
        ->and($view)->toContain("setViewMode('list')")
        ->and($view)->toContain("setViewMode('grid')")
        ->and($view)->toContain("setViewMode('dense')")
        ->and($view)->toContain("'Selected' : 'Select'")
        ->and($view)->toContain('>Delete</button>');
});
