<?php

it('keeps Journal tables on the shared metric alignment grid with labeled canonical actions', function (): void {
    $root = dirname(__DIR__, 3);
    $view = file_get_contents($root.'/resources/views/filament/pages/journal-workspace.blade.php');
    $css = file_get_contents($root.'/resources/css/admin/journal.css');

    expect($view)
        ->toContain('admin-table--six-grid journal-table')
        ->toContain('admin-table__col-two-units-minus-selection')
        ->toContain('journal-table__actions--blog')
        ->toContain('AdminRowAction::MoveUp')
        ->toContain('AdminRowAction::MoveDown')
        ->toContain('AdminRowAction::Edit')
        ->toContain('AdminRowAction::Publish')
        ->toContain('AdminRowAction::Unpublish')
        ->toContain('AdminRowAction::Delete')
        ->toContain('admin-table__selection admin-table__selection--trailing')
        ->toContain('wire:click.prevent="toggleVisibleSelection"')
        ->toContain('wire:click="togglePostSelection')
        ->toContain('wire:click="toggleExhibitionSelection')
        ->not->toContain('<x-filament::icon')
        ->not->toContain('>↑</button>')
        ->not->toContain('>↓</button>');

    expect($css)
        ->toContain('min-width: 80rem !important;')
        ->not->toContain('min-width: 88rem !important;')
        ->toContain('41.666667% - var(--admin-table-selection-width)')
        ->toContain('.journal-row-actions--blog')
        ->toContain('.journal-row-actions--exhibitions');
});
