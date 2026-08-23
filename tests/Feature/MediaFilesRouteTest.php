<?php

use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\MediaAsset;
use App\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

it('uses the artist-facing media-files resource route', function (): void {
    expect(parse_url(MediaAssetResource::getUrl('index'), PHP_URL_PATH))->toBe('/admin/media-files');

    $this->get('/admin/media-files')
        ->assertOk()
        ->assertSee('Media Files');
});

it('redirects legacy media-assets index and record URLs to media-files', function (): void {
    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/route-test.jpg',
        'original_filename' => 'route-test.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'route-test.jpg'),
        'state' => 'available',
        'alt_text' => 'Route test',
    ]);

    $this->get('/admin/media-assets')
        ->assertRedirect(MediaAssetResource::getUrl('index'));
    $this->get('/admin/media-assets/'.$asset->getKey())
        ->assertRedirect(MediaAssetResource::getUrl('view', ['record' => $asset]));
    $this->get('/admin/media-assets/'.$asset->getKey().'/edit')
        ->assertRedirect(MediaAssetResource::getUrl('edit', ['record' => $asset]));
});
