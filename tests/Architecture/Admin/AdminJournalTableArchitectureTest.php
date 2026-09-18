<?php

it('keeps Journal tables on the shared metric alignment grid with the complete canonical action set', function (): void {
    $root = dirname(__DIR__, 3);
    $view = file_get_contents($root.'/resources/views/filament/pages/journal-workspace.blade.php');
    $css = file_get_contents($root.'/resources/css/admin/journal.css');

    expect($view)
        ->toContain('admin-table--six-grid journal-table')
        ->toContain('admin-table__col-two-units-minus-selection')
        ->toContain('admin-table__col-one-half-units')
        ->toContain('journal-table__actions--blog')
        ->toContain('AdminRowAction::MoveUp')
        ->toContain('AdminRowAction::MoveDown')
        ->toContain('AdminRowAction::Edit')
        ->toContain('AdminRowAction::Publish')
        ->toContain('AdminRowAction::Unpublish')
        ->toContain('AdminRowAction::Schedule')
        ->toContain('AdminRowAction::Archive')
        ->toContain('AdminRowAction::Delete')
        ->toContain('admin-table__selection admin-table__selection--trailing')
        ->toContain('wire:click.prevent="toggleVisibleSelection"')
        ->toContain('wire:click="togglePostSelection')
        ->toContain('wire:click="toggleExhibitionSelection')
        ->not->toContain('<x-filament::icon')
        ->not->toContain('>↑</button>')
        ->not->toContain('>↓</button>');

    expect($css)
        ->toContain(".journal-table--blog,\n.journal-table--exhibitions {\n    min-width: 76rem !important;")
        ->toContain('.journal-table--blog col:nth-child(4)')
        ->toContain('width: var(--admin-table-one-unit);')
        ->toContain('.journal-table--blog col:nth-child(6)')
        ->toContain('width: var(--admin-table-half-unit);')
        ->toContain('width: calc(50% - var(--admin-table-selection-width));')
        ->toContain('Schedule ends exactly on the 4/6 metric line')
        ->not->toContain('.journal-table--exhibitions col:nth-child(5)')
        ->not->toContain('width: calc(41.666667% - var(--admin-table-selection-width));')
        ->toContain(".journal-row-actions--blog {\n    grid-template-columns: repeat(7, minmax(max-content, 1fr));")
        ->toContain('justify-content: stretch;')
        ->toContain('.journal-row-actions--exhibitions')
        ->toContain(".journal-row-actions .admin-action__label {\n    min-width: 0;\n    white-space: nowrap;")
        ->toContain('overflow: visible;')
        ->not->toContain(".journal-row-actions .admin-action__label {\n    min-width: 0;\n    overflow: hidden;")
        ->not->toContain(".journal-row-actions .admin-action__label {\n    min-width: 0;\n    text-overflow: ellipsis;");
});
