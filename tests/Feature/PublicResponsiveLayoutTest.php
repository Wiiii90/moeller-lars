<?php

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    responsiveArtwork('sculptures', 'navigation-sculpture', 0, 0);
    responsiveArtwork('works-a', 'navigation-work-a', 1, 0);
    responsiveArtwork('works-b', 'navigation-work-b', 2, 0);
});

function responsiveArtwork(
    string $categorySlug,
    string $slug,
    ?int $categoryPosition,
    int $artworkPosition,
): Artwork {
    $category = ArtworkCategory::query()->where('slug', $categorySlug)->first();
    if (! $category instanceof ArtworkCategory) {
        if ($categoryPosition === null) {
            throw new LogicException('A new responsive test category requires an explicit position.');
        }
        $category = ArtworkCategory::create([
            'name' => ucfirst($categorySlug),
            'slug' => $categorySlug,
            'state' => 'published',
            'position' => $categoryPosition,
            'show_in_navigation' => true,
            'show_on_home' => true,
        ]);
    } elseif ($categoryPosition !== null && (int) $category->getAttribute('position') !== $categoryPosition) {
        throw new LogicException('Responsive test category position does not match the existing fixture.');
    }

    $artwork = Artwork::create([
        'artwork_category_id' => $category->id,
        'slug' => $slug,
        'title' => 'Responsive work',
        'state' => 'published',
        'position' => $artworkPosition,
        'date_precision' => 'unknown',
    ]);
    $asset = MediaAsset::create([
        'storage_key' => 'originals/'.$slug.'.jpg',
        'original_filename' => $slug.'.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', $slug),
        'state' => 'available',
        'alt_text' => 'Responsive artwork ALT',
    ]);
    ArtworkMedia::create([
        'artwork_id' => $artwork->id,
        'media_asset_id' => $asset->id,
        'role' => 'primary',
        'position' => 0,
    ]);
    MediaVariant::create([
        'media_asset_id' => $asset->id,
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/'.$slug.'.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'thumb-'.$slug),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);

    return $artwork;
}

it('renders the responsive shell and ordered main navigation', function () {
    $content = $this->get('/')->assertSuccessful()->getContent();

    expect($content)->toContain('name="viewport" content="width=device-width, initial-scale=1"')
        ->and($content)->toContain('aria-label="Main navigation"')
        ->and(strpos($content, 'Sculptures'))->toBeLessThan(strpos($content, 'Works-a'))
        ->and(strpos($content, 'Works-a'))->toBeLessThan(strpos($content, 'Works-b'))
        ->and(strpos($content, 'Works-b'))->toBeLessThan(strpos($content, 'CV &amp; Exhibitions'))
        ->and($content)->not->toContain('Blog')->not->toContain('Admin')->not->toContain('Contact');
});

it('marks only the active artwork navigation link', function (string $path, string $active): void {
    $content = $this->get($path)->assertSuccessful()->getContent();

    expect(preg_match('/href="[^\"]*\/'.$active.'"[^>]*aria-current="page"/', $content))->toBe(1);
    foreach (['sculptures', 'works-a', 'works-b'] as $slug) {
        if ($slug !== $active) {
            expect($content)->not->toContain('href="'.url('/'.$slug).'" aria-current="page"');
        }
    }
})->with([
    'sculptures' => ['/sculptures', 'sculptures'],
    'works-a' => ['/works-a', 'works-a'],
    'works-b' => ['/works-b', 'works-b'],
]);

it('does not mark artwork navigation active on a custom published category', function () {
    responsiveArtwork('etchings', 'custom-responsive-work', 3, 0);
    ArtworkCategory::query()->where('slug', 'etchings')->update(['show_in_navigation' => false]);
    $content = $this->get('/etchings')->assertSuccessful()->getContent();

    expect($content)->not->toContain('aria-current="page"');
});

it('renders responsive artwork card and direct artwork markup', function () {
    $artwork = responsiveArtwork('sculptures', 'card-responsive-work', null, 1);
    $categoryContent = $this->get('/sculptures')->assertSuccessful()->getContent();
    $detailContent = $this->get('/artworks/'.$artwork->slug)->assertSuccessful()->getContent();

    expect($categoryContent)->toContain('class="artwork-card"', 'class="artwork-card__link"')
        ->and($categoryContent)->toContain('class="artwork-image artwork-card__image"')
        ->and($categoryContent)->toContain('class="artwork-card__metadata"')
        ->and($categoryContent)->toContain('href="'.route('artworks.show', $artwork->slug).'"')
        ->and($detailContent)->toContain('class="artwork-detail"', 'class="artwork-image artwork-detail__image"')
        ->and($detailContent)->toContain('class="artwork-detail__metadata"');
});

it('fails explicitly when required public media is missing', function () {
    $category = ArtworkCategory::query()->where('slug', 'sculptures')->firstOrFail();
    $artwork = Artwork::create([
        'artwork_category_id' => $category->id,
        'slug' => 'missing-responsive-work',
        'title' => 'Missing media',
        'state' => 'published',
        'position' => 1,
        'date_precision' => 'unknown',
    ]);
    $this->withoutExceptionHandling();

    expect(fn () => $this->get('/sculptures'))->toThrow(LogicException::class);
    expect(fn () => $this->get('/artworks/'.$artwork->slug))->toThrow(LogicException::class);
});

it('renders the legitimate empty home state when no eligible dated artwork exists', function () {
    Artwork::query()->delete();
    ArtworkCategory::query()->delete();

    expect($this->get('/')->assertSuccessful()->getContent())
        ->toContain('class="missing-media public-empty-state"');
});

it('keeps the responsive CSS contract without image cropping', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('overflow-x: hidden', 'max-width: 920px', 'max-width: 650px')
        ->and($css)->toContain('@media (max-width: 600px)', 'grid-template-columns: repeat(2, minmax(0, 1fr))')
        ->and($css)->toContain('@media (max-width: 380px)', '.site-header nav a:focus-visible')
        ->and($css)->toContain('[aria-current="page"]')
        ->and($css)->not->toContain('min-width: 320px')
        ->and($css)->not->toContain('object-fit: cover');
});
