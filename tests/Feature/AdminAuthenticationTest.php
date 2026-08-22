<?php

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function authPreviewAsset(): array
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

it('keeps the admin surface private', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/register')->assertNotFound();
    $this->get('/admin/password-reset/request')->assertNotFound();
});

it('denies the admin surface to authenticated non-admin users', function (): void {
    $this->actingAs(User::factory()->create(), 'web')
        ->get('/admin')
        ->assertForbidden();
});

it('allows admin users into the admin surface', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web')
        ->get('/admin')
        ->assertSuccessful();
});

it('keeps unpublished media private while allowing admin preview', function (): void {
    Storage::fake(config('media.disk'));
    [$asset, $variant] = authPreviewAsset();

    $this->get(route('admin.media.original', $asset))->assertForbidden();
    $this->get(route('admin.media.variant', $variant))->assertForbidden();

    $this->actingAs(User::factory()->create(), 'web');
    $this->get(route('admin.media.original', $asset))->assertForbidden();
    $this->get(route('admin.media.variant', $variant))->assertForbidden();

    $this->actingAs(User::factory()->admin()->create(), 'web');
    $this->get(route('admin.media.original', $asset))->assertSuccessful();
    $this->get(route('admin.media.variant', $variant))->assertSuccessful();
});

it('never previews quarantined media', function (): void {
    Storage::fake(config('media.disk'));
    [$asset, $variant] = authPreviewAsset();
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $asset->update(['state' => 'quarantined']);

    $this->get(route('admin.media.original', $asset))->assertNotFound();
    $this->get(route('admin.media.variant', $variant))->assertNotFound();
});

it('keeps artist preview non-discoverable to guests and non-admin users', function (): void {
    $this->get('/preview')->assertNotFound();
    $this->actingAs(User::factory()->create(), 'web')->get('/preview')->assertNotFound();
});
