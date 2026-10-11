<?php

it('keeps Filters popovers single-column and exclusive to the compact container state', function (): void {
    $controls = file_get_contents(resource_path('views/components/admin/controls.blade.php'));
    $css = file_get_contents(resource_path('css/admin/six-cell-contract.css'));

    expect($controls)
        ->toContain('syncPanelToLayout()')
        ->toContain('getComputedStyle(this.')
        ->toContain("this.open = false")
        ->toContain("this.panelStyle = ''")
        ->toContain('x-on:resize.window="syncPanelToLayout()"')
        ->toContain('x-bind:aria-expanded="open.toString()"')
        ->toContain('const contentHeight = panel.scrollHeight')
        ->toContain('const fitsBelow = contentHeight <= below')
        ->toContain('const fitsAbove = contentHeight <= above')
        ->toContain('const availableHeight = openBelow ? below : above')
        ->not->toContain('below >= 180')
        ->not->toContain('Math.max(120, below)')
        ->not->toContain('Math.max(120, above)');

    expect(substr_count($controls, '{{ $filters }}'))->toBe(1);

    expect($css)
        ->toContain('--admin-filter-popover-display: grid;')
        ->toContain('--admin-filter-popover-position: fixed;')
        ->toContain('--admin-filter-popover-field-column: 1 / -1;')
        ->toContain('--admin-filter-popover-field-row: auto;')
        ->toContain('grid-column: var(--admin-filter-popover-field-column) !important;')
        ->toContain('grid-row: var(--admin-filter-popover-field-row) !important;')
        ->toContain('display: var(--admin-filter-popover-reset-display, none) !important;')
        ->toContain('grid-template-columns: minmax(0, 1fr) !important;')
        ->toContain('@container admin-workspace (max-width: 76rem)')
        ->toContain('@container admin-workspace (max-width: 54rem)')
        ->toContain('@container admin-workspace (max-width: 38rem)');

    expect($controls)
        ->not->toContain('setInterval(')
        ->not->toContain('ResizeObserver')
        ->not->toContain('window.innerWidth <');
});
