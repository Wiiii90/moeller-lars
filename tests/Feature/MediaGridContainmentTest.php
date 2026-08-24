<?php

it('keeps Media Files views, selection treatment, and actions on the accepted contract', function (): void {
    $css = file_get_contents(resource_path('css/admin/media.css'));
    $view = file_get_contents(resource_path('views/filament/resources/media-assets/pages/list-media-assets.blade.php'));

    expect($css)->toBeString()
        ->and($css)->toContain('grid-template-columns: repeat(5, minmax(0, 1fr));')
        ->and($css)->toContain('grid-template-rows: auto minmax(0, 1fr) auto;')
        ->and($css)->toContain('contain: layout paint;')
        ->and($css)->toContain('isolation: isolate;')
        ->and($css)->toContain(".media-workspace__visual img,\n.media-workspace__visual video")
        ->and($css)->toContain('object-fit: contain;')
        ->and($css)->toContain('object-position: center;')
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
        ->and($css)->toContain('.media-workspace__grid-item.is-selected::after')
        ->and($css)->toContain('inset: 0;')
        ->and($css)->toContain('z-index: 10;')
        ->and($css)->toContain('border: 2px solid var(--admin-accent);')
        ->and($css)->toContain('pointer-events: none;')
        ->and($css)->toContain('.media-workspace__table tbody tr.is-selected > td:first-child')
        ->and($css)->toContain('box-shadow: inset 2px 0 0 var(--admin-accent);')
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
        ->and($view)->toContain("route('admin.media.download-selected'")
        ->and($view)->toContain('>Download selected</a>')
        ->and($view)->toContain('>Delete selected</button>');

    expect(strpos($view, '>Download selected</a>'))
        ->toBeLessThan(strpos($view, '>Delete selected</button>'));

    $gridStart = strpos($view, "@if (\$viewMode === 'grid')");
    $tableStart = strpos($view, '<x-admin.table', $gridStart);
    expect($gridStart)->not->toBeFalse()
        ->and($tableStart)->not->toBeFalse();

    $gridBlock = substr($view, $gridStart, $tableStart - $gridStart);
    expect($gridBlock)
        ->toContain('class="media-workspace__visual"')
        ->toContain("wire:click=\"mountAction('preview', { asset:")
        ->not->toContain('>Preview</button>')
        ->toContain("route('admin.media.download'")
        ->toContain('>Download</a>')
        ->toContain('>Edit</button>')
        ->toContain('>Delete</button>');

    $tableBlock = substr($view, $tableStart);
    expect($tableBlock)
        ->toContain("@if (\$viewMode === 'list')\n                                        <th scope=\"col\" class=\"media-workspace__thumb-head\">Preview</th>")
        ->toContain("@if (\$viewMode === 'list')\n                                            <td class=\"media-workspace__thumb\">")
        ->toContain("wire:click=\"mountAction('preview', { asset:")
        ->toContain("@if (\$viewMode === 'dense')\n                                                    <button class=\"admin-action\" type=\"button\" wire:click=\"mountAction('preview', { asset:")
        ->and(substr_count($tableBlock, '>Preview</button>'))->toBe(1)
        ->and($tableBlock)->toContain("route('admin.media.download'")
        ->and($tableBlock)->toContain('>Download</a>')
        ->and($tableBlock)->toContain('>Edit</button>')
        ->and($tableBlock)->toContain('>Delete</button>');

    expect(preg_match(
        '/media-workspace__selection-head.*@if \(\$viewMode === \'list\'\).*media-workspace__thumb-head">Preview<\/th>.*@endif.*<th scope="col">Media<\/th>.*<th scope="col">Type<\/th>.*<th scope="col">Used in<\/th>.*<th scope="col">Status<\/th>.*<th scope="col">Size<\/th>.*<th scope="col">Actions<\/th>/s',
        $tableBlock,
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
