<?php

it('keeps the mobile sidebar above header notifications with an explicit drawer close control', function (): void {
    $root = dirname(__DIR__, 3);
    $provider = file_get_contents($root.'/app/Providers/Filament/AdminPanelProvider.php');
    $admin = file_get_contents($root.'/resources/css/admin.css');
    $layouts = file_get_contents($root.'/resources/css/admin/layouts.css');
    $theme = file_get_contents($root.'/resources/css/admin/theme-effects.css');
    $notifications = file_get_contents($root.'/resources/css/admin/notifications.css');
    $close = file_get_contents($root.'/resources/views/filament/partials/admin-sidebar-close.blade.php');

    expect($provider)
        ->toContain('PanelsRenderHook::SIDEBAR_START')
        ->toContain("view('filament.partials.admin-sidebar-close')");

    expect($close)
        ->toContain('class="admin-sidebar-close-shell"')
        ->toContain('class="admin-sidebar-close-button"')
        ->toContain('x-on:click="$store.sidebar.close()"')
        ->toContain('title="Close navigation"');

    expect($admin)
        ->toContain('--admin-header-control-rail: calc(')
        ->toContain('--admin-workspace-content-inline-end: max(')
        ->toContain('var(--admin-header-control-rail)')
        ->toContain('.admin-sidebar-close-button {')
        ->toContain('width: var(--admin-header-control-size) !important;')
        ->toContain('margin: 0 !important;')
        ->toContain('border-radius: 9999px !important;');

    expect($notifications)
        ->toContain('inset-inline-start: var(--admin-header-control-rail);')
        ->toContain('inset-inline-end: var(--admin-header-control-rail);')
        ->toContain('width: var(--admin-workspace-content-width);')
        ->toContain('border: 1px solid var(--admin-line-strong);')
        ->toContain('box-shadow: 0 1px 2px rgba(0, 0, 0, .06);')
        ->toContain(".dark .admin-header-notification {")
        ->toContain('0 0 0 2px var(--admin-user-badge-aura)');

    expect($theme)
        ->toContain('html.fi .fi-topbar .fi-topbar-open-sidebar-btn,')
        ->toContain('html.fi .admin-sidebar-close-button {')
        ->toContain('html.fi:not(.dark) .fi-topbar .fi-topbar-open-sidebar-btn,')
        ->toContain('background: #080808;')
        ->toContain('color: #fff !important;')
        ->toContain('html.fi.dark .fi-topbar .fi-topbar-open-sidebar-btn,')
        ->toContain('0 0 0 1px var(--admin-user-badge-edge)')
        ->toContain('0 0 0 2px var(--admin-user-badge-aura)');

    expect($layouts)
        ->toContain('html.fi .fi-topbar .fi-topbar-close-sidebar-btn')
        ->toContain('display: none !important;')
        ->toContain('html.fi .fi-sidebar-close-overlay')
        ->toContain('z-index: 45;')
        ->toContain('html.fi .fi-sidebar')
        ->toContain('z-index: 50;')
        ->not->toContain('html.fi .fi-topbar .fi-topbar-open-sidebar-btn {')
        ->not->toContain('html.fi .admin-sidebar-close-button {');
});
