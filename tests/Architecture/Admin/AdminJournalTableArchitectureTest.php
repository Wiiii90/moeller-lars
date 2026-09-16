<?php

it('keeps Journal tables on the shared metric alignment grid with labeled canonical actions', function (): void {
    $root = dirname(__DIR__, 3);
    $view = file_get_contents($root.'/resources/views/filament/pages/journal-workspace.blade.php');
    $css = file_get_contents($root.'/resources/css/admin/journal.css');

    expect($view)
        ->toContain('admin-table--six-grid journal-table')
        ->toContain('admin-table__col-two-units-minus-selection')
        ->toContain('journal-table__actions--blog')
        ->toContain('AdminIcon::MoveUp->mini()')
        ->toContain('AdminIcon::MoveDown->mini()')
        ->toContain('<span class="admin-action__label">Move up</span>')
        ->toContain('<span class="admin-action__label">Move down</span>')
        ->toContain('AdminIcon::Edit->mini()')
        ->toContain('AdminIcon::Publish->mini()')
        ->toContain('AdminIcon::Unpublish->mini()')
        ->toContain('AdminIcon::Delete->mini()')
        ->not->toContain('>↑</button>')
        ->not->toContain('>↓</button>');

    expect($css)
        ->toContain('41.666667% - var(--admin-table-selection-width)')
        ->toContain('.journal-row-actions--blog')
        ->toContain('.journal-row-actions--exhibitions');
});
