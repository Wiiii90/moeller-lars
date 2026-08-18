<?php

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function adminPreviewAsset(): array
{
    $asset = MediaAsset::create([
        'storage_key' => 'originals/admin-preview.jpg',
        'original_filename' => 'admin-preview.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 8,
        'sha256' => hash('sha256', 'original'),
        'state' => 'available',
        'alt_text' => null,
    ]);

    $variant = MediaVariant::create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/admin-preview.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 9,
        'sha256' => hash('sha256', 'thumbnail'),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);

    Storage::disk(config('media.disk'))->put($asset->getAttribute('storage_key'), 'original');
    Storage::disk(config('media.disk'))->put($variant->getAttribute('storage_key'), 'thumbnail');

    return [$asset, $variant];
}

beforeEach(function () {
    Storage::fake(config('media.disk'));
});

it('does not expose admin media previews to guests or non-admin users', function () {
    [$asset, $variant] = adminPreviewAsset();

    $this->get(route('admin.media.original', $asset))->assertForbidden();
    $this->get(route('admin.media.variant', $variant))->assertForbidden();

    $this->actingAs(User::factory()->create(), 'web');
    $this->get(route('admin.media.original', $asset))->assertForbidden();
    $this->get(route('admin.media.variant', $variant))->assertForbidden();
});

it('lets admins preview available unpublished media without making it public', function () {
    [$asset, $variant] = adminPreviewAsset();
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $original = $this->get(route('admin.media.original', $asset));
    $original
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/jpeg');
    expect((string) $original->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('max-age=3600');

    $variantResponse = $this->get(route('admin.media.variant', $variant));
    $variantResponse
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/webp');
    expect((string) $variantResponse->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('max-age=3600');
});

it('refuses quarantined media even for admins', function () {
    [$asset, $variant] = adminPreviewAsset();
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $asset->update(['state' => 'quarantined']);

    $this->get(route('admin.media.original', $asset))->assertNotFound();
    $this->get(route('admin.media.variant', $variant))->assertNotFound();
});
