<?php

use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\MediaAsset;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('returns real resource records through the global admin search provider', function (): void {
    $category = ArtworkCategory::query()->create([
        'slug' => 'global-search',
        'name' => 'Global search',
    ]);
    $artwork = Artwork::query()->create([
        'artwork_category_id' => $category->id,
        'slug' => 'topbar-needle-artwork',
        'title' => 'Topbar Needle Artwork',
        'state' => 'draft',
        'position' => 0,
    ]);
    $media = MediaAsset::query()->create([
        'storage_key' => 'originals/topbar-needle-media.jpg',
        'original_filename' => 'topbar-needle-media.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'topbar-needle-media.jpg'),
        'state' => 'available',
        'alt_text' => 'Topbar needle media',
    ]);

    expect(ArtworkResource::getGlobalSearchResults('Needle')->count())->toBeGreaterThan(0)
        ->and(MediaAssetResource::getGlobalSearchResults('Needle')->count())->toBeGreaterThan(0)
        ->and(MediaAssetResource::getGlobalSearchResultUrl($media))->toContain('/admin/media-files/'.$media->id)
        ->and(ArtworkResource::getGlobalSearchResultUrl($artwork))->not->toBeNull();
});
