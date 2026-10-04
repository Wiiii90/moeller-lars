<?php

it('keeps workspace notification and user control on one shell frame', function (): void {
    $root = dirname(__DIR__, 3);
    $theme = file_get_contents($root.'/resources/css/admin.css');
    $layouts = file_get_contents($root.'/resources/css/admin/layouts.css');
    $notifications = file_get_contents($root.'/resources/css/admin/notifications.css');
    $dialogs = file_get_contents($root.'/resources/css/admin/dialog-contract.css');

    expect($theme)
        ->toContain('--admin-workspace-max-width: 80rem;')
        ->toContain('--admin-workspace-gutter: 2rem;')
        ->toContain('--admin-header-control-rail:')
        ->not->toContain('--admin-workspace-main-extra-space')
        ->not->toContain('--admin-workspace-visual-shift')
        ->not->toContain('--admin-workspace-content-inline-start')
        ->not->toContain('--admin-workspace-content-inline-end');

    expect($layouts)
        ->toContain(".fi-layout {\n        width: 100%;")
        ->not->toContain('width: 100vw;')
        ->toContain('html.fi .fi-main:has(.admin-workspace) .admin-workspace')
        ->toContain('calc(100% - var(--admin-workspace-gutter) - var(--admin-header-control-rail))')
        ->toContain('margin-inline-end: var(--admin-header-control-rail);')
        ->toContain('position: fixed;')
        ->toContain('width: var(--admin-header-control-rail);')
        ->toContain('height: var(--admin-topbar-height);')
        ->toContain('justify-content: center;')
        ->toContain('width: var(--admin-header-control-size);')
        ->toContain('@media (max-width: 63.99rem)')
        ->toContain('padding-inline: var(--admin-header-control-edge-gap) !important;')
        ->toContain("html.fi .fi-main:has(.admin-workspace) .admin-workspace {\n        width: 100%;");

    expect($notifications)
        ->toContain('var(--admin-workspace-max-width)')
        ->toContain('100%')
        ->toContain('var(--sidebar-width)')
        ->toContain('var(--admin-workspace-gutter)')
        ->toContain('var(--admin-header-control-rail)')
        ->toContain('inset-inline-start: auto;')
        ->toContain('inset-inline-end: var(--admin-header-control-rail);')
        ->not->toContain('admin-workspace-content-inline-start')
        ->not->toContain('admin-workspace-content-inline-end');

    expect($dialogs)
        ->not->toContain('admin-workspace-visual-shift')
        ->not->toContain('inset-inline-start: calc(0rem -');
});
