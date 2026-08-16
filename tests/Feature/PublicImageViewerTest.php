<?php

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\ViewException;

function viewerCategory(string $slug, int $position): ArtworkCategory
{
    return ArtworkCategory::create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'state' => 'published',
        'position' => $position,
        'show_in_navigation' => true,
        'show_on_home' => true,
    ]);
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

function viewerThumbnail(MediaAsset $asset): MediaVariant
{
    return MediaVariant::create([
        'media_asset_id' => $asset->id,
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/'.$asset->id.'.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 4,
        'sha256' => str_repeat('d', 64),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);
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
    $category = viewerCategory('sculptures', 0);
    $first = viewerArtwork($category, 'viewer-first', ['work_date' => '2026-01-01', 'position' => 1]);
    $second = viewerArtwork($category, 'viewer-second', ['work_date' => '2025-01-01', 'position' => 2]);
    foreach ([$first, $second] as $artwork) {
        $asset = viewerAsset('originals/'.$artwork->slug.'.jpg');
        viewerPrimary($artwork, $asset);
        $thumbnail = viewerThumbnail($asset);
        Storage::disk(config('media.disk'))->put($asset->storage_key, 'image');
        Storage::disk(config('media.disk'))->put($thumbnail->storage_key, 'thumb');
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
    $category = viewerCategory('sculptures', 0);
    $current = viewerArtwork($category, 'viewer-current', ['work_date' => '2026-01-01', 'position' => 2]);
    $other = viewerArtwork($category, 'viewer-other', ['work_date' => '2025-01-01', 'position' => 1]);
    $third = viewerArtwork($category, 'viewer-third', ['work_date' => '2024-01-01', 'position' => 0]);
    $outside = viewerArtwork(viewerCategory('works-a', 1), 'viewer-outside');
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

it('fails explicitly when a gallery card has no canonical primary media', function () {
    viewerArtwork(viewerCategory('sculptures', 0), 'viewer-missing');
    $this->withoutExceptionHandling();

    expect(fn () => $this->get('/sculptures'))->toThrow(ViewException::class);
});

it('escapes viewer data and keeps the viewer source free of unsafe DOM APIs', function () {
    $category = viewerCategory('sculptures', 0);
    $artwork = viewerArtwork($category, 'viewer-escaping', ['title' => '<script>" &']);
    $asset = viewerAsset('originals/escaping.jpg');
    $asset->update(['alt_text' => '<script>" &']);
    viewerPrimary($artwork, $asset);
    viewerThumbnail($asset);
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
