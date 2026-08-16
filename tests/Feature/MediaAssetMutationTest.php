<?php

use App\Domain\Media\MediaAssetEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\ExhibitionMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function mutationAsset(): MediaAsset
{
    $asset = MediaAsset::create([
        'storage_key' => 'originals/'.uniqid().'.jpg',
        'original_filename' => 'asset.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', uniqid('', true)),
        'state' => 'available',
        'alt_text' => 'Asset ALT',
    ]);
    MediaVariant::create([
        'media_asset_id' => $asset->id,
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/'.uniqid().'.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 4,
        'sha256' => hash('sha256', uniqid('', true)),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);

    return $asset;
}

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

it('updates only approved media metadata', function () {
    $asset = mutationAsset();
    $updated = app(MediaAssetEditorialService::class)->updateMetadata($asset, ['alt_text' => 'Updated ALT', 'credit' => 'Credit']);

    expect($updated->alt_text)->toBe('Updated ALT')->and($updated->credit)->toBe('Credit');
    expect(fn () => app(MediaAssetEditorialService::class)->updateMetadata($asset, ['state' => 'deleted']))
        ->toThrow(ValidationException::class);
});

it('logically deletes unreferenced media and removes its files', function () {
    Storage::fake(config('media.disk'));
    $asset = mutationAsset();
    $variant = $asset->variants()->sole();
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'orig');
    Storage::disk(config('media.disk'))->put($variant->storage_key, 'variant');

    expect(app(MediaAssetEditorialService::class)->delete($asset))->toBeTrue();
    expect($asset->fresh()->state)->toBe('deleted')->and($variant->fresh()->state)->toBe('deleted');
    Storage::disk(config('media.disk'))->assertMissing($asset->storage_key);
    Storage::disk(config('media.disk'))->assertMissing($variant->storage_key);
});

it('refuses deletion while media is referenced by an approved content type', function (string $type) {
    $asset = mutationAsset();

    if ($type === 'artwork') {
        $category = ArtworkCategory::create(['slug' => 'works', 'name' => 'Works', 'state' => 'published', 'position' => 0]);
        $artwork = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'work', 'title' => 'Work', 'state' => 'draft', 'position' => 0]);
        ArtworkMedia::create(['artwork_id' => $artwork->id, 'media_asset_id' => $asset->id, 'role' => 'primary', 'position' => 0]);
    } elseif ($type === 'exhibition') {
        $exhibition = Exhibition::create(['slug' => 'show', 'title' => 'Show', 'state' => 'draft', 'position' => 0]);
        ExhibitionMedia::create(['exhibition_id' => $exhibition->id, 'media_asset_id' => $asset->id, 'role' => 'additional', 'position' => 0]);
    } elseif ($type === 'cv') {
        CvEntry::create(['section' => 'CV', 'title' => 'Entry', 'state' => 'draft', 'position' => 0, 'image_media_asset_id' => $asset->id]);
    } else {
        BlogPost::create(['slug' => 'post', 'title' => 'Post', 'state' => 'draft', 'position' => 0, 'cover_media_asset_id' => $asset->id]);
    }

    expect(fn () => app(MediaAssetEditorialService::class)->delete($asset))->toThrow(ValidationException::class);
    expect($asset->fresh()->state)->toBe('available');
})->with(['artwork', 'exhibition', 'cv', 'blog']);
