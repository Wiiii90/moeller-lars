<?php

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\BlogSetting;
use App\Models\Exhibition;
use App\Models\ExhibitionMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function invariantAsset(): MediaAsset
{
    $asset = MediaAsset::create([
        'storage_key' => 'originals/'.uniqid().'.jpg',
        'original_filename' => 'work.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', uniqid('', true)),
        'state' => 'available',
        'alt_text' => 'Artwork image',
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

it('enforces unique visible top-level SiteSection navigation positions', function () {
    $first = ArtworkCategory::create(['slug' => 'first', 'name' => 'First', 'show_on_home' => false]);
    testGallerySection($first, ['state' => 'published', 'show_in_navigation' => true, 'position' => 200]);
    $second = ArtworkCategory::create(['slug' => 'second', 'name' => 'Second', 'show_on_home' => false]);

    expect(fn () => testGallerySection($second, [
        'state' => 'published',
        'show_in_navigation' => true,
        'position' => 200,
    ]))->toThrow(QueryException::class);
});

it('enforces one primary media usage per artwork', function () {
    $category = ArtworkCategory::create(['slug' => 'works', 'name' => 'Works', 'state' => 'published', 'position' => 0]);
    $artwork = Artwork::create([
        'artwork_category_id' => $category->id, 'slug' => 'one', 'title' => 'One', 'state' => 'draft', 'position' => 0,
    ]);
    ArtworkMedia::create(['artwork_id' => $artwork->id, 'media_asset_id' => invariantAsset()->id, 'role' => 'primary', 'position' => 0]);

    expect(fn () => ArtworkMedia::create([
        'artwork_id' => $artwork->id, 'media_asset_id' => invariantAsset()->id, 'role' => 'primary', 'position' => 1,
    ]))->toThrow(QueryException::class);
});

it('enforces at most one exhibition hero', function () {
    $exhibition = Exhibition::create(['slug' => 'show', 'title' => 'Show', 'state' => 'draft', 'position' => 0]);
    ExhibitionMedia::create(['exhibition_id' => $exhibition->id, 'media_asset_id' => invariantAsset()->id, 'role' => 'hero', 'position' => 0]);

    expect(fn () => ExhibitionMedia::create([
        'exhibition_id' => $exhibition->id, 'media_asset_id' => invariantAsset()->id, 'role' => 'hero', 'position' => 1,
    ]))->toThrow(QueryException::class);
});

it('keeps public settings singletons singular', function () {
    expect(BlogSetting::query()->count())->toBe(1);
    expect(fn () => DB::table('blog_settings')->insert([
        'id' => 2,
        'public_enabled' => false,
        'navigation_label' => 'Blog',
        'navigation_position' => 110,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
