<?php

use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Models\MediaAsset;
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
