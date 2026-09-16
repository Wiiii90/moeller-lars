<?php

it('keeps admin table scrollbars out of vertical table flow at every breakpoint', function (): void {
    $root = dirname(__DIR__, 3);
    $css = file_get_contents($root.'/resources/css/admin/table-flow.css');
    $theme = file_get_contents($root.'/resources/views/filament/partials/admin-theme.blade.php');

    expect($theme)->toContain('resources/css/admin/table-flow.css');

    expect($css)
        ->toContain('.admin-table {')
        ->toContain('scrollbar-width: none !important;')
        ->toContain('.admin-table::-webkit-scrollbar')
        ->toContain('height: 0 !important;')
        ->not->toContain('scrollbar-width: thin')
        ->not->toContain('@media (max-width: 760px)');
});
