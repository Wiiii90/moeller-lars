<?php

use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Models\MediaAsset;
use App\Models\AuditEvent;
use App\Models\AdminNotification;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function filesSelectionAsset(string $filename): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/'.$filename,
        'original_filename' => $filename,
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', $filename),
        'state' => 'available',
        'alt_text' => 'Test image',
    ]);
}

it('keeps file selection while switching view modes', function (): void {
    $asset = filesSelectionAsset('selected.jpg');

    Livewire::test(ListMediaAssets::class)
        ->call('toggleAssetSelection', $asset->id)
        ->assertSet('selectedAssets', [$asset->id])
        ->call('setViewMode', 'grid')
        ->assertSet('viewMode', 'grid')
        ->assertSet('selectedAssets', [$asset->id])
        ->call('setViewMode', 'dense')
        ->assertSet('viewMode', 'dense')
        ->assertSet('selectedAssets', [$asset->id])
        ->call('setViewMode', 'list')
        ->assertSet('viewMode', 'list')
        ->assertSet('selectedAssets', [$asset->id]);
});

it('does not delete selected assets after the actor loses admin authorization', function (bool $guest): void {
    $asset = filesSelectionAsset('protected-selection.jpg');
    $component = Livewire::test(ListMediaAssets::class)
        ->call('toggleAssetSelection', $asset->id);
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    if ($guest) {
        auth('web')->logout();
    } else {
        $this->actingAs(User::factory()->create(['is_admin' => false]), 'web');
    }

    $component->mountAction('deleteSelected')->callMountedAction()
        ->assertNotDispatched('admin-header-notification', function (string $event, array $params): bool {
            return ($params['notification']['status'] ?? null) === 'success';
        });

    expect($asset->fresh()->state)->toBe('available')
        ->and(AuditEvent::query()->count())->toBe($auditsBefore)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore);
})->with(['guest' => true, 'non-admin' => false]);
