<?php

it('uses one framework-native SPA navigation and runtime lifecycle', function (): void {
    $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    $viz = file_get_contents(resource_path('js/admin-viz.js'));
    $selects = file_get_contents(resource_path('js/admin-selects.js'));
    $storageViz = file_get_contents(resource_path('js/admin-storage-viz.js'));
    $vizHook = file_get_contents(resource_path('views/filament/partials/admin-viz.blade.php'));
    $publicationControl = file_get_contents(app_path('Livewire/Admin/PublicationStateControl.php'));

    expect($provider)
        ->toContain('->spa()')
        ->toContain('->spaUrlExceptions')
        ->toContain("config('pulse.path', 'pulse')")
        ->toContain("view('filament.partials.publication-state-control-hook')")
        ->not->toContain('PublicationStateBridge')
        ->and($vizHook)
        ->toContain("@persist('admin-global-runtime-assets')")
        ->toContain("@vite('resources/js/admin-viz.js')")
        ->toContain("@vite('resources/js/admin-password-tools.js')")
        ->not->toContain("admin-storage-viz.js")
        ->and($viz)
        ->toContain("import './admin-notifications.js';")
        ->not->toContain('admin-modal-scroll')
        ->toContain("document.addEventListener('livewire:navigate', prewarmStorageRuntime)")
        ->toContain("document.addEventListener('livewire:navigated', scheduleRefresh)")
        ->not->toContain('DOMContentLoaded')
        ->not->toContain("alpine:navigate")
        ->and($selects)
        ->toContain("document.addEventListener('livewire:navigating', teardownWorkspaceSelects)")
        ->toContain("document.addEventListener('livewire:navigated', scheduleEnhancement)")
        ->not->toContain('cleanupOrphanedGeneratedUi')
        ->not->toContain('DOMContentLoaded')
        ->and($storageViz)
        ->toContain("document.addEventListener('livewire:navigating', disposeStorageVisualizations)")
        ->toContain('export function disposeStorageVisualizations()')
        ->and($publicationControl)
        ->toContain('final class PublicationStateControl')
        ->toContain("view('livewire.admin.publication-state-control')")
        ->not->toContain('Bridge');
});
