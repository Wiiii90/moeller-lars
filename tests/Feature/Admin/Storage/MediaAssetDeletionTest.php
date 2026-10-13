<?php

use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
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

    Livewire::test(ListMediaAssets::class)
        ->mountAction('delete', ['asset' => $asset->id])
        ->callMountedAction();

    expect($asset->fresh()->state)->toBe('deleted');
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

    Livewire::test(ListMediaAssets::class)
        ->call('toggleAssetSelection', $deletable->id)
        ->call('toggleAssetSelection', $referenced->id)
        ->mountAction('deleteSelected')
        ->callMountedAction()
        ->assertSet('selectedAssets', []);

    expect($deletable->fresh()->state)->toBe('deleted')
        ->and($referenced->fresh()->state)->toBe('deleted')
        ->and(ArtworkMedia::query()->where('media_asset_id', $referenced->id)->exists())->toBeFalse();
});
