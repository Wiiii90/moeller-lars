<?php

use App\Domain\Admin\AdminSettingsService;
use App\Livewire\Admin\PublicationStateBridge;
use App\Models\PublicContentSetting;
use App\Models\User;
use Livewire\Livewire;

it('dispatches publication state changes only when the pending flag changes', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    $control = Livewire::test(PublicationStateBridge::class)
        ->assertSet('hasPendingChanges', false)
        ->call('refreshState')
        ->assertNotDispatched('publication-state-changed');

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'legal_disclaimer' => 'Publication bridge '.fake()->uuid(),
    ]);

    $control
        ->call('refreshState')
        ->assertSet('hasPendingChanges', true)
        ->assertDispatched('publication-state-changed');

    Livewire::test(PublicationStateBridge::class)
        ->assertSet('hasPendingChanges', true)
        ->call('refreshState')
        ->assertNotDispatched('publication-state-changed');
});
