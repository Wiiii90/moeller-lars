<?php

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function responsiveArtwork(string $categorySlug = 'paintings', string $slug = 'responsive-work'): Artwork
{
    $category = ArtworkCategory::query()->firstOrNew(['slug' => $categorySlug]);
    $category->fill(['name' => ucfirst($categorySlug), 'state' => 'published', 'position' => 0]);
    $category->save();

    return Artwork::create([
        'artwork_category_id' => $category->id,
        'slug' => $slug,
        'title' => 'Responsive work',
        'state' => 'published',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);
}

it('renders the responsive shell and ordered main navigation', function () {
    $content = $this->get('/')->assertSuccessful()->getContent();

    expect($content)->toContain('name="viewport" content="width=device-width, initial-scale=1"')
        ->and($content)->toContain('aria-label="Main navigation"')
        ->and(strpos($content, 'Paintings'))->toBeLessThan(strpos($content, 'Prints'))
        ->and(strpos($content, 'Prints'))->toBeLessThan(strpos($content, 'Drawings'))
        ->and(strpos($content, 'Drawings'))->toBeLessThan(strpos($content, 'CV &amp; Exhibitions'))
        ->and($content)->not->toContain('Blog')->not->toContain('Admin')->not->toContain('Contact');
});

it('marks only the active artwork navigation link', function (string $path, string $active): void {
    $content = $this->get($path)->assertSuccessful()->getContent();

    expect(preg_match('/href="[^\"]*\/'.$active.'"[^>]*aria-current="page"/', $content))->toBe(1);
    foreach (['paintings', 'prints', 'drawings'] as $slug) {
        if ($slug !== $active) {
            expect($content)->not->toContain('href="'.url('/'.$slug).'" aria-current="page"');
        }
    }
})->with([
    'paintings' => ['/paintings', 'paintings'],
    'prints' => ['/prints', 'prints'],
    'drawings' => ['/drawings', 'drawings'],
]);

it('does not mark artwork navigation active on a custom published category', function () {
    responsiveArtwork('etchings', 'custom-responsive-work');
    $content = $this->get('/etchings')->assertSuccessful()->getContent();

    expect($content)->not->toContain('aria-current="page"');
});

it('renders responsive artwork card and direct artwork markup', function () {
    $artwork = responsiveArtwork('paintings', 'card-responsive-work');
    $categoryContent = $this->get('/paintings')->assertSuccessful()->getContent();
    $detailContent = $this->get('/artworks/'.$artwork->slug)->assertSuccessful()->getContent();

    expect($categoryContent)->toContain('class="artwork-card"', 'class="artwork-card__link"')
        ->and($categoryContent)->toContain('class="missing-media artwork-card__image"')
        ->and($categoryContent)->toContain('class="artwork-card__metadata"')
        ->and($categoryContent)->toContain('href="'.route('artworks.show', $artwork->slug).'"')
        ->and($detailContent)->toContain('class="artwork-detail"', 'class="missing-media"')
        ->and($detailContent)->toContain('class="artwork-detail__metadata"')
        ->and($detailContent)->toContain('role="img" aria-label="Media unavailable"');
});

it('renders missing and empty public states accessibly', function () {
    $artwork = responsiveArtwork('paintings', 'missing-responsive-work');
    $categoryContent = $this->get('/paintings')->assertSuccessful()->getContent();
    $detailContent = $this->get('/artworks/'.$artwork->slug)->assertSuccessful()->getContent();

    expect($categoryContent)->toContain('role="img" aria-label="Media unavailable"');
    expect($detailContent)->toContain('role="img" aria-label="Media unavailable"');

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
