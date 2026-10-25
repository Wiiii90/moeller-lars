<?php

use App\Domain\Admin\AdminActivityNotificationContext;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

require_once __DIR__.'/MediaReferenceFixtures.php';

it('opens Preview and Edit as workspace actions and saves canonical metadata in place', function (): void {
    $asset = workspaceReferenceAsset('dialog-image.jpg');

    Livewire::test(ListMediaAssets::class)
        ->mountAction('preview', ['asset' => $asset->id])
        ->assertMountedActionModalSee('dialog-image.jpg')
        ->assertMountedActionModalSee('Metadata')
        ->assertMountedActionModalSee('Used in');

    app(AdminActivityNotificationContext::class)->discard();
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();
    $metadata = [

            'alt_text' => 'Updated canonical ALT',
            'credit' => 'Studio credit',
            'copyright_notice_mode' => MediaAsset::COPYRIGHT_OVERRIDE,
            'copyright_notice' => 'All rights reserved',
    ];

    Livewire::test(ListMediaAssets::class)
        ->mountAction('edit', ['asset' => $asset->id])
        ->fillForm($metadata)
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['status'] ?? null) === 'success';
        });

    $asset->refresh();
    expect($asset->getAttribute('alt_text'))->toBe('Updated canonical ALT')
        ->and($asset->getAttribute('credit'))->toBe('Studio credit')
        ->and($asset->getAttribute('copyright_notice_mode'))->toBe(MediaAsset::COPYRIGHT_OVERRIDE)
        ->and($asset->getAttribute('copyright_notice'))->toBe('All rights reserved')
        ->and($asset->getAttribute('state'))->toBe('available')
        ->and(AuditEvent::query()->where('action', 'media.metadata_updated')
            ->where('entity_id', $asset->getKey())->count())->toBe(1)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    Livewire::test(ListMediaAssets::class)
        ->mountAction('edit', ['asset' => $asset->id])
        ->fillForm($metadata)
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotDispatched('admin-header-notification');

    expect(AuditEvent::query()->count())->toBe($auditsBefore + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    // A new editor instance must reopen the canonical stored fields.
    Livewire::test(ListMediaAssets::class)
        ->mountAction('edit', ['asset' => $asset->id])
        ->assertSet('mountedActions.0.data.alt_text', 'Updated canonical ALT')
        ->assertSet('mountedActions.0.data.credit', 'Studio credit')
        ->assertSet('mountedActions.0.data.copyright_notice_mode', MediaAsset::COPYRIGHT_OVERRIDE)
        ->assertSet('mountedActions.0.data.copyright_notice', 'All rights reserved');
});
