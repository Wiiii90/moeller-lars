<?php

use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

function adminViewerAsset(string $filename, string $suffix): MediaAsset
{
    return MediaAsset::create([
        'storage_key' => 'originals/'.$filename,
        'original_filename' => $filename,
        'mime_type' => 'image/jpeg',
        'byte_size' => 1200,
        'sha256' => hash('sha256', $suffix),
        'state' => 'available',
        'alt_text' => 'ALT '.$suffix,
        'width' => 1600,
        'height' => 1200,
    ]);
}

it('renders media metadata and typed usage from the library', function (): void {
    $asset = adminViewerAsset('library-viewer.jpg', 'library-viewer');
    $category = ArtworkCategory::create([
        'name' => 'Viewer Gallery',
        'slug' => 'viewer-gallery',
        'state' => 'hidden',
        'position' => 0,
        'show_in_navigation' => false,
        'show_on_home' => false,
    ]);
    $artwork = Artwork::create([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'viewer-artwork',
        'title' => 'Viewer Artwork',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);
    ArtworkMedia::create([
        'artwork_id' => $artwork->getKey(),
        'media_asset_id' => $asset->getKey(),
        'role' => 'primary',
        'position' => 0,
    ]);

    $this->get(MediaAssetResource::getUrl('view', ['record' => $asset]))
        ->assertSuccessful()
        ->assertSee('Media inspection')
        ->assertSee('library-viewer.jpg')
        ->assertSee('1600×1200')
        ->assertSee('ALT library-viewer')
        ->assertSee('Viewer Artwork');
});

it('navigates primary and additional media as one artwork inspection sequence', function (): void {
    $primary = adminViewerAsset('primary-viewer.jpg', 'primary');
    $additional = adminViewerAsset('additional-viewer.jpg', 'additional');
    $category = ArtworkCategory::create([
        'name' => 'Sequence Gallery',
        'slug' => 'sequence-gallery',
        'state' => 'hidden',
        'position' => 0,
        'show_in_navigation' => false,
        'show_on_home' => false,
    ]);
    $artwork = Artwork::create([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'sequence-artwork',
        'title' => 'Sequence Artwork',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);
    ArtworkMedia::create([
        'artwork_id' => $artwork->getKey(),
        'media_asset_id' => $primary->getKey(),
        'role' => 'primary',
        'position' => 0,
    ]);
    ArtworkMedia::create([
        'artwork_id' => $artwork->getKey(),
        'media_asset_id' => $additional->getKey(),
        'role' => 'additional',
        'position' => 0,
    ]);

    $primaryPage = $this->get(MediaAssetResource::getUrl('view', ['record' => $primary, 'artwork' => $artwork->getKey()]));
    $primaryPage
        ->assertSuccessful()
        ->assertSee('1 / 2')
        ->assertSee('Primary image')
        ->assertSee('Next →');

    $additionalPage = $this->get(MediaAssetResource::getUrl('view', ['record' => $additional, 'artwork' => $artwork->getKey()]));
    $additionalPage
        ->assertSuccessful()
        ->assertSee('2 / 2')
        ->assertSee('Gallery image')
        ->assertSee('← Previous')
        ->assertSee(ArtworkResource::getUrl('edit', ['record' => $artwork]), escape: false);
});
