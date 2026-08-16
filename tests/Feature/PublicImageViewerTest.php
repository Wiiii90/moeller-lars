<?php

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

function viewerCategory(string $slug = 'sculptures'): ArtworkCategory
{
    $category = ArtworkCategory::query()->firstOrNew(['slug' => $slug]);
    $category->fill(['name' => ucfirst($slug), 'state' => 'published', 'position' => 0, 'show_in_navigation' => true, 'show_on_home' => true]);
    $category->save();

    return $category;
}

function viewerArtwork(ArtworkCategory $category, string $slug, array $attributes = []): Artwork
{
    return Artwork::create(array_merge([
        'artwork_category_id' => $category->id, 'slug' => $slug, 'title' => $slug,
        'state' => 'published', 'position' => 0, 'date_precision' => 'unknown',
    ], $attributes));
}

function viewerAsset(string $key): MediaAsset
{
    return MediaAsset::create([
        'storage_key' => $key, 'original_filename' => basename($key), 'mime_type' => 'image/jpeg',
        'byte_size' => 4, 'sha256' => str_repeat('c', 64), 'state' => 'available', 'alt_text' => 'Asset ALT',
    ]);
}

function viewerPrimary(Artwork $artwork, MediaAsset $asset): void
{
    ArtworkMedia::create(['artwork_id' => $artwork->id, 'media_asset_id' => $asset->id, 'role' => 'primary', 'position' => 0]);
}

it('renders one accessible global viewer modal with all controls', function () {
    $content = $this->get('/')->assertSuccessful()->getContent();

    expect(substr_count($content, 'data-artwork-viewer'))
        ->toBe(1)
        ->and($content)->toContain('data-viewer-close', 'Close artwork viewer', 'data-viewer-previous', 'Previous artwork')
        ->toContain('data-viewer-next', 'Next artwork', 'data-viewer-zoom-out', 'data-viewer-reset', 'data-viewer-zoom-in')
        ->toContain('data-viewer-loading', 'Loading image', 'data-viewer-missing', 'data-viewer-title', 'data-viewer-page-link');
});

it('renders ordered category and home viewer sequences with controlled URLs', function () {
    Storage::fake(config('media.disk'));
    $category = viewerCategory();
    $first = viewerArtwork($category, 'viewer-first', ['work_date' => '2025-01-01', 'position' => 1]);
    $second = viewerArtwork($category, 'viewer-second', ['work_date' => '2025-01-01', 'position' => 2]);
    foreach ([$first, $second] as $artwork) {
        $asset = viewerAsset('originals/'.$artwork->slug.'.jpg');
        viewerPrimary($artwork, $asset);
        Storage::disk(config('media.disk'))->put($asset->storage_key, 'image');
    }
    $categoryContent = $this->get('/sculptures')->assertSuccessful()->getContent();
    $firstPosition = strpos($categoryContent, 'data-viewer-key="viewer-first"');
    $secondPosition = strpos($categoryContent, 'data-viewer-key="viewer-second"');

    expect($categoryContent)->toContain('class="artwork-list" data-artwork-viewer-sequence')
        ->and($categoryContent)->toContain('data-artwork-viewer-item')
        ->and($firstPosition)->toBeLessThan($secondPosition)
        ->and($categoryContent)->toContain(route('media.original', ['mediaAsset' => $first->artworkMedia()->first()->mediaAsset]))
        ->and($categoryContent)->not->toContain('storage_key');

    $homeContent = $this->get('/')->assertSuccessful()->getContent();
    expect($homeContent)->toContain('class="home-artwork" data-artwork-viewer-sequence')
        ->and(substr_count($homeContent, 'class="artwork-card__link"'))->toBe(1);
});

it('renders direct-view sequence data and preserves the no-JS original link', function () {
    Storage::fake(config('media.disk'));
    $category = viewerCategory();
    $current = viewerArtwork($category, 'viewer-current', ['work_date' => '2026-01-01', 'position' => 2]);
    $other = viewerArtwork($category, 'viewer-other', ['work_date' => '2025-01-01', 'position' => 1]);
    $third = viewerArtwork($category, 'viewer-third', ['work_date' => '2024-01-01', 'position' => 0]);
    $outside = viewerArtwork(viewerCategory('works-a'), 'viewer-outside');
    foreach ([$current, $other, $third, $outside] as $artwork) {
        $asset = viewerAsset('originals/'.$artwork->slug.'.jpg');
        viewerPrimary($artwork, $asset);
        Storage::disk(config('media.disk'))->put($asset->storage_key, 'image');
    }

    $content = $this->get('/artworks/viewer-current')->assertSuccessful()->getContent();
    $url = route('media.original', ['mediaAsset' => $current->artworkMedia()->first()->mediaAsset]);
    expect($content)->toContain('class="artwork-detail" data-artwork-viewer-sequence')
        ->and($content)->toContain('class="artwork-detail__viewer-trigger" href="'.$url.'"')
        ->and($content)->toContain('data-viewer-key="viewer-current"', 'data-viewer-key="viewer-other"', 'data-viewer-key="viewer-third"')
        ->and($content)->not->toContain('data-viewer-key="viewer-outside"')
        ->and($content)->toContain('data-viewer-page="'.route('artworks.show', 'viewer-current').'"');
    $sequence = substr($content, (int) strpos($content, 'artwork-viewer-sequence-data'));
    expect(strpos($sequence, 'data-viewer-key="viewer-third"'))->toBeLessThan(strpos($sequence, 'data-viewer-key="viewer-other"'))
        ->and(strpos($sequence, 'data-viewer-key="viewer-other"'))->toBeLessThan(strpos($sequence, 'data-viewer-key="viewer-current"'));
});

it('keeps missing primary media as a normal card link with empty viewer source', function () {
    $artwork = viewerArtwork(viewerCategory(), 'viewer-missing');
    $content = $this->get('/sculptures')->assertSuccessful()->getContent();

    expect($content)->toContain('href="'.route('artworks.show', $artwork->slug).'"')
        ->and($content)->toContain('data-viewer-src=""')
        ->and($content)->toContain('data-artwork-viewer');
});

it('escapes viewer data and keeps the viewer source free of unsafe DOM APIs', function () {
    $category = viewerCategory();
    $artwork = viewerArtwork($category, 'viewer-escaping', ['title' => '<script>" &']);
    $asset = viewerAsset('originals/escaping.jpg');
    $asset->update(['alt_text' => '<script>" &']);
    viewerPrimary($artwork, $asset);
    $content = $this->get('/sculptures')->assertSuccessful()->getContent();
    $source = file_get_contents(resource_path('js/artwork-viewer.js'));

    expect($content)->toContain('&lt;script&gt;')
        ->and($content)->not->toContain('<script>" &')
        ->and($source)->not->toContain('eval(')->not->toContain('innerHTML =')->not->toContain('insertAdjacentHTML')
        ->not->toContain('document.write')->not->toContain('localStorage')->not->toContain('sessionStorage')
        ->not->toContain('fetch(')->not->toContain('window.location');
});

it('contains source contracts for wheel, pointer, pinch, keyboard, focus restore, and resize', function () {
    $source = file_get_contents(resource_path('js/artwork-viewer.js'));
    $css = file_get_contents(resource_path('css/app.css'));

    expect($source)->toContain('showModal', 'setPointerCapture', 'pointerdown', 'pointermove', 'wheel')
        ->toContain('orientationchange', 'requestAnimationFrame', 'ArrowLeft', 'ArrowRight', 'trigger.focus')
        ->and($css)->toContain('touch-action: none', 'object-fit: contain', '100dvh', '::backdrop', 'focus-visible');
});
