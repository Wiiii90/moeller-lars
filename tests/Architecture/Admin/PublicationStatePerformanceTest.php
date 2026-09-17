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

it('reads current publication state from uncheckpointed event state instead of snapshot diffs', function (): void {
    $root = dirname(__DIR__, 3);

    $publicationService = file_get_contents($root.'/app/Domain/Publication/PublicationService.php');
    preg_match(
        '/public function hasPendingChanges\(\): bool\s*\{(?<body>.*?)\n    \}/s',
        $publicationService,
        $matches,
    );

    expect($matches['body'] ?? '')
        ->toContain('hasUncheckpointedPendingEvents()')
        ->not->toContain('FULL OUTER JOIN');

    $eventStates = file_get_contents($root.'/app/Domain/Publication/PublicationEventStateService.php');
    expect($eventStates)
        ->toContain('function hasUncheckpointedPendingEvents(): bool')
        ->toContain("where('publication_event_states.status', PublicationEventState::STATUS_PENDING)")
        ->toContain("->from('publication_checkpoint_events')")
        ->toContain("->whereColumn(");
});

