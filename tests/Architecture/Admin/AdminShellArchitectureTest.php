<?php

it('keeps the mobile sidebar above header notifications with an explicit drawer close control', function (): void {
    $root = dirname(__DIR__, 3);
    $provider = file_get_contents($root.'/app/Providers/Filament/AdminPanelProvider.php');
    $layouts = file_get_contents($root.'/resources/css/admin/layouts.css');
    $close = file_get_contents($root.'/resources/views/filament/partials/admin-sidebar-close.blade.php');

    expect($provider)
        ->toContain('PanelsRenderHook::SIDEBAR_START')
        ->toContain("view('filament.partials.admin-sidebar-close')");

    expect($close)
        ->toContain('class="admin-sidebar-close-shell"')
        ->toContain('class="admin-sidebar-close-button"')
        ->toContain('x-on:click="$store.sidebar.close()"')
        ->toContain('title="Close navigation"');

    expect($layouts)
        ->toContain('width: var(--admin-header-control-size) !important;')
        ->toContain('html.fi .fi-topbar .fi-topbar-close-sidebar-btn')
        ->toContain('display: none !important;')
        ->toContain('html.fi .fi-sidebar-close-overlay')
        ->toContain('z-index: 45;')
        ->toContain('html.fi .fi-sidebar')
        ->toContain('z-index: 50;');
});
