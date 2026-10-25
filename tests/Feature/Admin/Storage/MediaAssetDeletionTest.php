<?php

use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Domain\Media\MediaAssetEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\AdminNotification;
use App\Models\AdminActionReceipt;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake(config('media.disk'));
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function filesDeletionAsset(string $filename): MediaAsset
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

it('deletes an unreferenced file through the Files delete action', function (): void {
    $asset = filesDeletionAsset('delete-me.jpg');
    $variant = MediaVariant::query()->create([
        'media_asset_id' => $asset->id, 'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/delete-me.webp', 'mime_type' => 'image/webp',
        'byte_size' => 4, 'sha256' => hash('sha256', 'delete-me-variant'),
        'transform_profile' => 'public-v1', 'state' => 'available',
        'width' => 10, 'height' => 5,
    ]);
    $disk = Storage::disk(config('media.disk'));
    $disk->put($asset->storage_key, 'Original fixture');
    $disk->put($variant->storage_key, 'Variant fixture');
    $receiptsBefore = AdminActionReceipt::query()->count();
    expect(\Illuminate\Support\Facades\DB::table('committed.media_assets')->where('id', $asset->id)->exists())->toBeFalse();
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    $component = Livewire::test(ListMediaAssets::class)
        ->mountAction('delete', ['asset' => $asset->id])
        ->callMountedAction()
        ->assertDispatched('admin-header-notification');

    expect($asset->fresh()->state)->toBe('deleted')
        ->and($variant->fresh()->state)->toBe('deleted')
        ->and($disk->exists($asset->storage_key))->toBeFalse()
        ->and($disk->exists($variant->storage_key))->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1)
        ->and(collect($component->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);

    Livewire::test(ListMediaAssets::class)
        ->mountAction('delete', ['asset' => $asset->id])
        ->callMountedAction()
        ->assertNotDispatched('admin-header-notification');

    expect(AuditEvent::query()->count())->toBe($auditsBefore + 1)
        ->and(AdminActionReceipt::query()->count())->toBe($receiptsBefore)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1)
        ->and(app(MediaAssetEditorialService::class)->delete($asset))->toBeFalse();
});

it('batch deletion removes selected file usages before deleting the assets', function (): void {
    $deletable = filesDeletionAsset('batch-delete.jpg');
    $referenced = filesDeletionAsset('batch-referenced.jpg');

    $category = ArtworkCategory::query()->create([
        'slug' => 'files-batch-delete',
        'name' => 'Files batch delete',
    ]);
    $artwork = Artwork::query()->create([
        'artwork_category_id' => $category->id,
        'slug' => 'referenced-work',
        'title' => 'Referenced work',
        'state' => 'draft',
        'position' => 0,
    ]);
    ArtworkMedia::query()->create([
        'artwork_id' => $artwork->id,
        'media_asset_id' => $referenced->id,
        'role' => 'primary',
        'position' => 0,
    ]);
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    $component = Livewire::test(ListMediaAssets::class)
        ->call('toggleAssetSelection', $deletable->id)
        ->call('toggleAssetSelection', $referenced->id)
        ->mountAction('deleteSelected')
        ->callMountedAction()
        ->assertSet('selectedAssets', [])
        ->assertDispatched('admin-header-notification');

    expect($deletable->fresh()->state)->toBe('deleted')
        ->and($referenced->fresh()->state)->toBe('deleted')
        ->and(ArtworkMedia::query()->where('media_asset_id', $referenced->id)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'media.deleted')->whereIn('entity_id', [$deletable->id, $referenced->id])->count())->toBe(2)
        ->and(AuditEvent::query()->where('action', 'artwork.media_reference_removed')->where('entity_id', $artwork->id)->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'artwork.updated')->where('entity_id', $artwork->id)->count())->toBe(1)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 4)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1)
        ->and(collect($component->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);

    $component->set('selectedAssets', [$deletable->id, $referenced->id])
        ->mountAction('deleteSelected')
        ->callMountedAction()
        ->assertSet('selectedAssets', [])
        ->assertNotDispatched('admin-header-notification');

    expect(AuditEvent::query()->count())->toBe($auditsBefore + 4)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);
});
