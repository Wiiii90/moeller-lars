<?php

use App\Models\ArtworkCategory;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\ExhibitionMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function publicContentSettings(): PublicContentSetting
{
    return PublicContentSetting::query()->sole();
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
        'width' => 1600,
        'height' => 1200,
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
        'width' => 960,
        'height' => 720,
    ]);
}

it('keeps CV and exhibitions unavailable until their canonical SiteSections are published', function () {
    $this->get('/cv')->assertNotFound();
    $this->get('/exhibitions')->assertNotFound();
});

it('renders every published Vita entry regardless of its editorial section label', function () {
    testSingletonSection(SiteSection::TYPE_VITA, [
        'navigation_label' => 'CV',
        'state' => 'published',
        'show_in_navigation' => true,
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
    CvEntry::create([
        'section' => 'Awards',
        'title' => 'Independent award',
        'state' => 'published',
        'position' => 1,
        'date_precision' => 'year',
        'year_text' => '2025',
    ]);

    $this->get('/cv')
        ->assertSuccessful()
        ->assertSee('CV')
        ->assertSee('Artist in Hamburg')
        ->assertSee('Independent award')
        ->assertSee('<strong>Selected</strong> work', false);
});

it('renders the CV portrait from the canonical thumbnail instead of the original', function () {
    testSingletonSection(SiteSection::TYPE_VITA, [
        'navigation_label' => 'CV',
        'state' => 'published',
        'show_in_navigation' => true,
    ]);

    $asset = publicContentAsset('portrait');
    $variant = publicContentThumbnail($asset, 'portrait');
    CvEntry::create([
        'section' => 'Biography',
        'title' => 'Portrait entry',
        'state' => 'published',
        'position' => 0,
        'date_precision' => 'year',
        'year_text' => '2026',
        'image_media_asset_id' => $asset->getKey(),
    ]);

    $this->get('/cv')
        ->assertSuccessful()
        ->assertSee(route('media.variant', $variant), false)
        ->assertDontSee(route('media.original', $asset), false)
        ->assertSee('width="960"', false)
        ->assertSee('height="720"', false)
        ->assertSee('fetchpriority="high"', false);
});

it('rejects a navigation position collision instead of inventing a tie breaker', function () {
    $category = ArtworkCategory::create(['slug' => 'works', 'name' => 'Works', 'show_on_home' => false]);
    testGallerySection($category, ['state' => 'published', 'show_in_navigation' => true, 'position' => 200]);

    expect(fn () => testSingletonSection(SiteSection::TYPE_VITA, [
        'state' => 'published',
        'show_in_navigation' => true,
        'position' => 200,
    ]))->toThrow(QueryException::class);
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

it('renders structured exhibition schedule and media through controlled public routes', function () {
    testSingletonSection(SiteSection::TYPE_EXHIBITIONS, [
        'navigation_label' => 'EXHIBITIONS',
        'state' => 'published',
        'show_in_navigation' => true,
    ]);

    $asset = publicContentAsset('exhibition');
    $variant = publicContentThumbnail($asset, 'exhibition');
    $exhibition = Exhibition::create([
        'slug' => 'current-show',
        'title' => 'Current Show',
        'state' => 'published',
        'position' => 0,
        'date_text' => '2026',
        'opening_text' => '3 January, 7 pm',
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

    $this->get('/exhibitions')
        ->assertSuccessful()
        ->assertSee('Current Show')
        ->assertSee('Vernissage')
        ->assertSee('3 January, 7 pm')
        ->assertSee(route('media.variant', $variant), false)
        ->assertSee('Directions');

    $this->get('/cv')->assertNotFound();
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
