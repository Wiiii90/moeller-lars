<?php

it('keeps the mobile sidebar above header notifications with an explicit drawer close control', function (): void {
    $root = dirname(__DIR__, 3);
    $provider = file_get_contents($root.'/app/Providers/Filament/AdminPanelProvider.php');
    $admin = file_get_contents($root.'/resources/css/admin.css');
    $layouts = file_get_contents($root.'/resources/css/admin/layouts.css');
    $theme = file_get_contents($root.'/resources/css/admin/theme-effects.css');
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
        ->toContain('.admin-sidebar-close-button {')
        ->toContain('width: var(--admin-header-control-size) !important;')
        ->toContain('margin: 0 !important;')
        ->toContain('border-radius: 9999px !important;');

    expect($theme)
        ->toContain('html.fi .fi-topbar .fi-topbar-open-sidebar-btn,')
        ->toContain('html.fi .admin-sidebar-close-button {')
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
