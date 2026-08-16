<?php

use App\Models\ArtworkCategory;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\ExhibitionMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\PublicContentSetting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function publicContentSettings(): PublicContentSetting
{
    return PublicContentSetting::query()->findOrFail(1);
}

function publicContentAsset(string $name = 'content'): MediaAsset
{
    return MediaAsset::create([
        'storage_key' => 'originals/'.$name.'.jpg',
        'original_filename' => $name.'.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 100,
        'sha256' => hash('sha256', $name),
        'state' => 'available',
        'alt_text' => ucfirst($name).' image',
    ]);
}

function publicContentThumbnail(MediaAsset $asset, string $name = 'content'): MediaVariant
{
    return MediaVariant::create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/'.$name.'.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 50,
        'sha256' => hash('sha256', $name.'-thumb'),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);
}

it('keeps the combined CV surface unavailable until CV or exhibitions is enabled', function () {
    $this->get('/cv')->assertNotFound();
});

it('renders published CV entries and the configured navigation item', function () {
    publicContentSettings()->update([
        'cv_enabled' => true,
        'cv_navigation_label' => 'CV & Exhibitions',
        'cv_navigation_position' => 20,
    ]);

    CvEntry::create([
        'section' => 'Biography',
        'title' => 'Artist in Hamburg',
        'state' => 'published',
        'position' => 0,
        'date_precision' => 'year',
        'year_text' => '2026',
        'body' => '**Selected** work',
    ]);

    $this->get('/cv')
        ->assertSuccessful()
        ->assertSee('CV &amp; Exhibitions', false)
        ->assertSee('Biography')
        ->assertSee('Artist in Hamburg')
        ->assertSee('<strong>Selected</strong> work', false);
});

it('rejects a navigation position collision instead of inventing a tie breaker', function () {
    ArtworkCategory::create([
        'slug' => 'works',
        'name' => 'Works',
        'state' => 'published',
        'position' => 7,
        'show_in_navigation' => true,
    ]);

    expect(fn () => publicContentSettings()->update([
        'cv_enabled' => true,
        'cv_navigation_position' => 7,
    ]))->toThrow(ValidationException::class);
});

it('enforces a total published CV order', function () {
    CvEntry::create([
        'section' => 'Biography',
        'title' => 'First',
        'state' => 'published',
        'position' => 4,
        'date_precision' => 'year',
        'year_text' => '2025',
    ]);

    expect(fn () => CvEntry::create([
        'section' => 'Biography',
        'title' => 'Second',
        'state' => 'published',
        'position' => 4,
        'date_precision' => 'year',
        'year_text' => '2024',
    ]))->toThrow(QueryException::class);
});

it('renders exhibition media through the existing controlled media routes', function () {
    publicContentSettings()->update([
        'exhibitions_enabled' => true,
        'cv_navigation_position' => 20,
    ]);

    $asset = publicContentAsset('exhibition');
    $variant = publicContentThumbnail($asset, 'exhibition');
    $exhibition = Exhibition::create([
        'slug' => 'current-show',
        'title' => 'Current Show',
        'state' => 'published',
        'position' => 0,
        'date_text' => '2026',
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
        'description' => 'Exhibition **description**.',
        'directions_url' => 'https://maps.example.test/show',
    ]);
    ExhibitionMedia::create([
        'exhibition_id' => $exhibition->getKey(),
        'media_asset_id' => $asset->getKey(),
        'role' => 'hero',
        'position' => 0,
    ]);

    $this->get('/cv')
        ->assertSuccessful()
        ->assertSee('Current Show')
        ->assertSee(route('media.variant', $variant), false)
        ->assertSee('Directions');
});

it('rejects exhibition media that cannot satisfy the public media contract', function () {
    $asset = publicContentAsset('missing-thumbnail');
    $exhibition = Exhibition::create([
        'slug' => 'draft-show',
        'title' => 'Draft Show',
        'state' => 'draft',
        'position' => 0,
        'date_text' => '2026',
    ]);

    expect(fn () => ExhibitionMedia::create([
        'exhibition_id' => $exhibition->getKey(),
        'media_asset_id' => $asset->getKey(),
        'role' => 'hero',
        'position' => 0,
    ]))->toThrow(ValidationException::class);
});
