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
        ->and($css)->toContain('.media-workspace__view-trigger')
        ->and($css)->toContain('min-width: 4.75rem;')
        ->and($css)->toContain('.media-workspace__selection-head input[type="checkbox"]')
        ->and($css)->toContain('.media-workspace__selection-cell input[type="checkbox"]')
        ->and($css)->toContain('.media-workspace__selection-checkbox input[type="checkbox"]')
        ->and($css)->toContain('appearance: none;')
        ->and($css)->toContain('width: 1.125rem;')
        ->and($css)->toContain('height: 1.125rem;')
        ->and($css)->toContain('border: 1px solid var(--admin-line-strong);')
        ->and($css)->toContain('background: var(--admin-accent);')
        ->and($css)->toContain(':focus-visible')
        ->and($css)->toContain(':disabled')
        ->and($view)->toBeString()
        ->and($view)->not->toContain('placeholder=')
        ->and($view)->not->toContain('Filter · Media type')
        ->and($view)->toContain('<span>Type</span>')
        ->and($view)->toContain('media-workspace__control-label">Filter</span>')
        ->and($view)->toContain('>Reset</button>')
        ->and($view)->toContain('media-workspace__control-label">View</span>')
        ->and($view)->toContain('media-workspace__control-label">Selection</span>')
        ->and($view)->not->toContain('Multi-action')
        ->and(substr_count($view, 'media-workspace__view-trigger'))->toBe(1)
        ->and($view)->not->toContain('style="min-width: 4.75rem;"')
        ->and(substr_count($view, "setViewMode('list')"))->toBe(1)
        ->and(substr_count($view, "setViewMode('grid')"))->toBe(1)
        ->and(substr_count($view, "setViewMode('dense')"))->toBe(1)
        ->and($view)->toContain('>{{ $viewModeLabel }}</button>')
        ->and($view)->toContain('Selected files')
        ->and($view)->toContain('media-workspace__selection-count')
        ->and($view)->toContain('@disabled($selectedAssets === [])')
        ->and($view)->not->toContain("'Selected' : 'Select'")
        ->and($view)->not->toContain('>Select</button>')
        ->and($view)->not->toContain('>Selected</button>')
        ->and($view)->toContain('media-workspace__selection-checkbox')
        ->and($view)->toContain('media-workspace__selection-head')
        ->and($view)->toContain('media-workspace__selection-cell')
        ->and($view)->toContain('type="checkbox"')
        ->and($view)->toContain('<th scope="col">Actions</th>')
        ->and($view)->toContain('>Preview</button>')
        ->and($view)->toContain('>Edit</button>')
        ->and($view)->toContain('>Delete</button>');

    expect(preg_match(
        '/media-workspace__selection-head.*media-workspace__thumb-head">Preview<\/th>.*<th scope="col">Media<\/th>.*<th scope="col">Type<\/th>.*<th scope="col">Used in<\/th>.*<th scope="col">Status<\/th>.*<th scope="col">Size<\/th>.*<th scope="col">Actions<\/th>/s',
        $view,
    ))->toBe(1);
});

it('keeps Media Files preview metadata exact and places usage below it', function (): void {
    $css = file_get_contents(resource_path('css/admin/media.css'));
    $preview = file_get_contents(resource_path('views/filament/resources/media-assets/partials/preview-dialog.blade.php'));

    expect($preview)->toBeString()
        ->and($preview)->not->toContain('Checksum')
        ->and($preview)->not->toContain("asset['checksum']")
        ->and($preview)->not->toContain('style=')
        ->and($preview)->toContain('media-file-dialog__metadata-grid')
        ->and($preview)->toContain('media-file-dialog__usage')
        ->and($css)->toBeString()
        ->and($css)->toContain('.media-file-dialog .media-file-dialog__preview')
        ->and($css)->toContain('max-height: 52vh;')
        ->and($css)->toContain('max-height: 50vh;')
        ->and($css)->toContain('object-fit: contain;')
        ->and($css)->toContain('.media-file-dialog .media-file-dialog__metadata-grid')
        ->and($css)->toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        ->and($css)->toContain('.media-file-dialog .media-file-dialog__usage')
        ->and($css)->toContain('width: 100%;');

    preg_match_all('/<dt>([^<]+)<\/dt>/', $preview, $metadataLabels);

    expect($metadataLabels[1])->toBe([
        'File',
        'Type',
        'Dimensions',
        'Size',
        'Status',
        'Created',
        'ALT text',
        'Credit',
        'Copyright',
        'Copyright source',
    ]);

    expect(strpos($preview, '>Metadata</h3>'))
        ->toBeLessThan(strpos($preview, '>Used in</h3>'));
});
