<?php

it('keeps header controls and burger workspace on one shell geometry', function (): void {
    $root = dirname(__DIR__, 3);
    $theme = file_get_contents($root.'/resources/css/admin.css');
    $layouts = file_get_contents($root.'/resources/css/admin/layouts.css');
    $notifications = file_get_contents($root.'/resources/css/admin/notifications.css');

    expect($theme)
        ->toContain('--admin-topbar-height: 4rem;')
        ->toContain('--admin-header-control-size: 2rem;')
        ->toContain('--admin-header-control-edge-gap: 1rem;')
        ->toContain('var(--admin-header-control-edge-gap)\n        + var(--admin-header-control-size)\n        + var(--admin-header-control-edge-gap)');

    expect($layouts)
        ->toContain('html.fi .fi-topbar .fi-topbar-end')
        ->toContain('inset-block: 0;')
        ->toContain('inset-inline-end: var(--admin-header-control-edge-gap);')
        ->toContain('padding-right: var(--admin-header-control-rail) !important;')
        ->toContain('@media (max-width: 63.99rem)')
        ->toContain('padding-inline: var(--admin-header-control-edge-gap) !important;')
        ->toContain('max-width: none !important;')
        ->not->toContain('padding-left: var(--admin-header-control-rail) !important;');

    expect($notifications)
        ->toContain('inset-inline-start: var(--admin-header-control-rail);')
        ->toContain('inset-inline-end: var(--admin-header-control-rail);')
        ->toContain('inset-inline-start: var(--admin-workspace-content-inline-start);')
        ->not->toContain('inset-inline-end: var(--admin-workspace-content-inline-end);')
        ->not->toContain(".fi-topbar-end {\n        position: relative;");
});
