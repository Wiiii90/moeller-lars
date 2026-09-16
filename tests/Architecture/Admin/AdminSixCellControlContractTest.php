<?php

it('aligns complete data toolbars to the six metric cells', function (): void {
    $root = dirname(__DIR__, 3);
    $component = file_get_contents($root.'/resources/views/components/admin/controls.blade.php');
    $css = file_get_contents($root.'/resources/css/admin/six-cell-contract.css');
    $theme = file_get_contents($root.'/resources/views/filament/partials/admin-theme.blade.php');
    $vite = file_get_contents($root.'/vite.config.js');

    expect($component)
        ->toContain('$hasCompleteDataToolbar')
        ->toContain('substr_count($filters->toHtml(), \'<select\')')
        ->toContain("'admin-data-controls--six-cell' => \$usesMetricGrid")
        ->toContain("'admin-data-controls--six-cell-filters-'.\$normalizedFilterCount => \$usesMetricGrid")
        ->toContain('class="admin-data-controls__utility"');

    expect($css)
        ->toContain('grid-template-columns: repeat(6, minmax(0, 1fr));')
        ->toContain('.admin-data-controls--six-cell-filters-0 > :first-child { grid-column: span 4; }')
        ->toContain('.admin-data-controls--six-cell-filters-1 > :first-child { grid-column: span 3; }')
        ->toContain('.admin-data-controls--six-cell-filters-2 > :first-child { grid-column: span 2; }')
        ->toContain(".admin-data-controls__utility {\n    display: grid;")
        ->toContain('grid-column: span 2;')
        ->toContain('var(--admin-table-selection-width)')
        ->toContain('justify-self: center;');

    foreach ([$theme, $vite] as $loader) {
        expect($loader)->toContain('resources/css/admin/six-cell-contract.css');
    }
});
