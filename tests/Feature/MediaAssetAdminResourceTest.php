<?php

use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\MediaAssets\Pages\EditMediaAsset;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function mediaAdminAsset(string $state = 'available'): MediaAsset
{
    return MediaAsset::create(['storage_key' => 'originals/admin-'.uniqid().'.txt', 'original_filename' => 'admin.txt', 'mime_type' => 'text/plain', 'byte_size' => 4, 'sha256' => hash('sha256', 'orig'), 'state' => $state, 'width' => 2, 'height' => 2]);
}

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

it('shows media administration to admins and denies non-admins', function () {
    Livewire::test(ListMediaAssets::class)->assertSuccessful();
    auth()->logout();
    $this->actingAs(User::factory()->create(), 'web');
    $this->get(MediaAssetResource::getUrl('index'))->assertForbidden();
});

it('does not expose a media create page', function () {
    $this->get('/admin/media-assets/create')->assertNotFound();
});

it('edits only media metadata through the service and shows safe table fields', function () {
    $asset = mediaAdminAsset();
    Livewire::test(EditMediaAsset::class, ['record' => $asset->id])
        ->fillForm(['alt_text' => 'Admin ALT', 'credit' => 'Admin credit', 'copyright_notice' => 'Admin copyright', 'state' => 'deleted', 'sha256' => 'changed', 'storage_key' => 'changed'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($asset->fresh()->alt_text)->toBe('Admin ALT')
        ->and($asset->fresh()->state)->toBe('available')
        ->and(AuditEvent::query()->where('action', 'media.metadata_updated')->count())->toBe(1);
    Livewire::test(ListMediaAssets::class)->assertCanSeeTableRecords([$asset])->assertDontSee('storage_key');
});

it('verifies integrity and deletes unreferenced media without a hard delete', function () {
    Storage::fake(config('media.disk'));
    $asset = mediaAdminAsset();
    $variant = MediaVariant::create(['media_asset_id' => $asset->id, 'variant_kind' => 'other', 'storage_key' => 'variants/admin.txt', 'mime_type' => 'text/plain', 'byte_size' => 3, 'sha256' => hash('sha256', 'var'), 'transform_profile' => 'other', 'state' => 'available', 'width' => 2, 'height' => 2]);
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'orig');
    Storage::disk(config('media.disk'))->put($variant->storage_key, 'var');
    Livewire::test(EditMediaAsset::class, ['record' => $asset->id])->call('mountAction', 'verifyIntegrity')->call('callMountedAction');
    Livewire::test(EditMediaAsset::class, ['record' => $asset->id])->call('mountAction', 'deleteMedia')->call('callMountedAction');
    expect($asset->fresh()->state)->toBe('deleted')
        ->and($variant->fresh()->state)->toBe('deleted')
        ->and(MediaAsset::query()->whereKey($asset->id)->exists())->toBeTrue();
    Livewire::test(EditMediaAsset::class, ['record' => $asset->id])->assertActionHidden('deleteMedia');
});
