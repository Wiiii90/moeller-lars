<?php

it('updates publication state from the original Livewire response without a follow-up request', function (): void {
    $root = dirname(__DIR__, 3);

    $bridgeView = file_get_contents($root.'/resources/views/livewire/admin/publication-state-bridge.blade.php');
    expect($bridgeView)
        ->toContain('Livewire.interceptRequest')
        ->toContain("response.headers.get('X-Publication-Pending')")
        ->toContain("window.dispatchEvent(new CustomEvent('publication-state-changed'")
        ->not->toContain('Livewire.interceptMessage')
        ->not->toContain('Livewire.getByName')
        ->not->toContain('refreshState()');

    $bridge = file_get_contents($root.'/app/Livewire/Admin/PublicationStateBridge.php');
    expect($bridge)
        ->not->toContain('function mount(')
        ->not->toContain('function refreshState(');

    $bootstrap = file_get_contents($root.'/bootstrap/app.php');
    expect($bootstrap)->toContain('AttachPublicationState::class');
});
