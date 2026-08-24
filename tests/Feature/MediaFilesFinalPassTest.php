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

function filesFinalPassAsset(string $filename): MediaAsset
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

it('keeps simple selection visible while switching Files views', function (): void {
    $asset = filesFinalPassAsset('selected.jpg');

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

it('deletes an unreferenced file through the Files delete action', function (): void {
    $asset = filesFinalPassAsset('delete-me.jpg');

    Livewire::test(ListMediaAssets::class)
        ->mountAction('delete', ['asset' => $asset->id])
        ->callMountedAction();

    expect($asset->fresh()->state)->toBe('deleted');
});

it('batch delete removes unreferenced files and leaves referenced files selected', function (): void {
    $deletable = filesFinalPassAsset('batch-delete.jpg');
    $referenced = filesFinalPassAsset('batch-referenced.jpg');

    $category = ArtworkCategory::query()->create([
        'slug' => 'files-final-pass',
        'name' => 'Files final pass',
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
        ->assertSet('selectedAssets', [$referenced->id]);

    expect($deletable->fresh()->state)->toBe('deleted')
        ->and($referenced->fresh()->state)->toBe('available');
});
