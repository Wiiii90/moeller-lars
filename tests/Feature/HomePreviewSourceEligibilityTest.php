<?php

use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\SitePreviewContext;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\HomePresentationSetting;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\SiteSection;
use App\Models\User;

function homePreviewSetting(string $template, array $components): HomePresentationSetting
{
    $home = SiteSection::query()->create([
        'type' => SiteSection::TYPE_HOME,
        'title' => 'Home',
        'navigation_label' => 'Home',
        'slug' => null,
        'state' => 'published',
        'position' => 0,
        'show_in_navigation' => true,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);

    $configuration = HomePresentationEditorialService::defaults();
    $configuration[$template]['components'] = $components;

    $settings = new HomePresentationSetting;
    $settings->setAttribute('site_section_id', $home->getKey());
    $settings->setAttribute('template', $template);
    $settings->setAttribute('configuration', $configuration);
    $settings->save();

    return $settings;
}

function homePreviewImage(): MediaAsset
{
    $asset = new MediaAsset;
    $asset->fill([
        'storage_key' => 'originals/home-preview.jpg',
        'original_filename' => 'home-preview.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 3,
        'sha256' => str_repeat('b', 64),
        'state' => 'available',
        'alt_text' => 'Home preview ALT',
        'width' => 2,
        'height' => 2,
    ]);
    $asset->save();

    MediaVariant::query()->create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/home-preview.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'home-preview'),
        'transform_profile' => 'public-v1',
        'state' => 'available',
        'width' => 2,
        'height' => 2,
    ]);

    return $asset;
}

function homePreviewComponents(MediaAsset $asset): array
{
    return [
        [
            'type' => 'image',
            'media_asset_id' => (int) $asset->getKey(),
            'image_decorative' => false,
        ],
        [
            'type' => 'text',
            'title' => 'Preview heading',
            'body' => null,
        ],
        [
            'type' => 'text',
            'title' => null,
            'body' => 'Preview **rich text**',
        ],
        [
            'type' => 'divider',
        ],
    ];
}

function homeSourceCategory(bool $showOnHome, string $sectionState = 'published'): ArtworkCategory
{
    $category = new ArtworkCategory;
    $category->fill([
        'slug' => 'home-source-'.fake()->unique()->uuid(),
        'name' => 'Home source',
        'show_on_home' => $showOnHome,
    ]);
    $category->save();
    testGallerySection($category, ['state' => $sectionState]);

    return $category;
}

function homeSourceArtwork(ArtworkCategory $category, string $state = 'published'): Artwork
{
    $artwork = new Artwork;
    $artwork->fill([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'home-artwork-'.fake()->unique()->uuid(),
        'title' => 'Home artwork',
        'state' => $state,
        'position' => 0,
        'work_year' => 2026,
        'date_precision' => 'year',
    ]);
    $artwork->save();

    return $artwork;
}

it('renders the Under Construction preview with every shared Home component kind', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $asset = homePreviewImage();
    homePreviewSetting('under_construction', homePreviewComponents($asset));

    $this->get('/preview')
        ->assertOk()
        ->assertSee('Home preview ALT')
        ->assertSee('Preview heading')
        ->assertSee('Preview <strong>rich text</strong>', false)
        ->assertSee('custom-page__divider', false);
});

it('renders the Custom preview through the same shared component renderer', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $asset = homePreviewImage();
    homePreviewSetting('custom', homePreviewComponents($asset));

    $this->get('/preview')
        ->assertOk()
        ->assertSee('Home preview ALT')
        ->assertSee('Preview heading')
        ->assertSee('Preview <strong>rich text</strong>', false)
        ->assertSee('custom-page__divider', false);
});

it('keeps Home source preference while effective eligibility follows Gallery publication', function (): void {
    $category = homeSourceCategory(true);
    homeSourceArtwork($category);
    $query = app(PublicArtworkQuery::class);

    expect($query->homeCandidateCount())->toBe(1);

    $section = SiteSection::query()->where('artwork_category_id', $category->getKey())->firstOrFail();
    $section->update(['state' => 'hidden']);

    expect($category->fresh()->show_on_home)->toBeTrue()
        ->and(app(PublicArtworkQuery::class)->homeCandidateCount())->toBe(0);

    $section->update(['state' => 'published']);

    expect($category->fresh()->show_on_home)->toBeTrue()
        ->and(app(PublicArtworkQuery::class)->homeCandidateCount())->toBe(1);

    $category->update(['show_on_home' => false]);
    $section->update(['state' => 'hidden']);
    $section->update(['state' => 'published']);

    expect($category->fresh()->show_on_home)->toBeFalse()
        ->and(app(PublicArtworkQuery::class)->homeCandidateCount())->toBe(0);
});

it('does not reactivate an unpublished Home source in Preview while Preview still expands Artwork visibility', function (): void {
    $category = homeSourceCategory(true);
    homeSourceArtwork($category, 'draft');
    app(SitePreviewContext::class)->activate(request());

    expect(app(PublicArtworkQuery::class)->homeCandidateCount())->toBe(1);

    SiteSection::query()
        ->where('artwork_category_id', $category->getKey())
        ->update(['state' => 'hidden']);

    expect($category->fresh()->show_on_home)->toBeTrue()
        ->and(app(PublicArtworkQuery::class)->homeCandidateCount())->toBe(0);
});
