<?php

use Illuminate\Support\Facades\File;

it('keeps admin header feedback centralized ephemeral and presentation only', function (): void {
    $view = file_get_contents(resource_path('views/filament/partials/admin-header-feedback.blade.php'));
    $styles = file_get_contents(resource_path('css/admin/notifications.css'));
    $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($provider)
        ->toContain('PanelsRenderHook::TOPBAR_START')
        ->toContain("view('filament.partials.admin-header-feedback')")
        ->and($view)
        ->toContain('window.setTimeout')
        ->toContain('window.clearTimeout')
        ->toContain('runtime.expiresAt')
        ->toContain('runtime.current = null')
        ->toContain('x-show="current !== null"')
        ->not->toContain('setInterval')
        ->not->toContain('requestAnimationFrame')
        ->not->toContain('MutationObserver')
        ->not->toContain('ResizeObserver')
        ->not->toContain('wire:poll')
        ->not->toContain('fetch(')
        ->not->toContain('Livewire.dispatch')
        ->not->toContain('$wire')
        ->not->toContain('admin-header-feedback__counter')
        ->and($styles)
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->not->toContain('.admin-header-feedback__counter');

    $renderOccurrences = 0;

    foreach ([...File::allFiles(app_path()), ...File::allFiles(resource_path('views'))] as $file) {
        $renderOccurrences += substr_count(
            file_get_contents($file->getRealPath()),
            'filament.partials.admin-header-feedback',
        );
    }

    expect($renderOccurrences)->toBe(1);
});
