<?php

use App\Domain\Artwork\ArtworkEditorialService;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Media\PublicMedia;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function sliceCategory(string $slug, string $state, int $position): ArtworkCategory
{
    return ArtworkCategory::create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'state' => $state,
        'position' => $position,
        'show_in_navigation' => true,
        'show_on_home' => true,
    ]);
}

function sliceArtwork(ArtworkCategory $category, array $attributes = []): Artwork
{
    return Artwork::create(array_merge([
        'artwork_category_id' => $category->id, 'slug' => 'slice-'.uniqid(), 'title' => 'Slice artwork',
        'state' => 'published', 'position' => 0, 'date_precision' => 'unknown',
    ], $attributes));
}

function sliceAsset(array $attributes = []): MediaAsset
{
    return MediaAsset::create(array_merge([
        'storage_key' => 'originals/'.uniqid().'.jpg', 'original_filename' => 'slice.jpg',
        'mime_type' => 'image/jpeg', 'byte_size' => 4, 'sha256' => str_repeat('a', 64),
        'state' => 'available', 'alt_text' => 'Asset alt',
    ], $attributes));
}

function attachMedia(Artwork $artwork, MediaAsset $asset, string $role = 'primary', ?string $alt = null): ArtworkMedia
{
    return ArtworkMedia::create([
        'artwork_id' => $artwork->id, 'media_asset_id' => $asset->id, 'role' => $role,
        'position' => $role === 'primary' ? 0 : 1, 'alt_text_override' => $alt,
    ]);
}

function sliceVariant(MediaAsset $asset, array $attributes = []): MediaVariant
{
    return MediaVariant::create(array_merge([
        'media_asset_id' => $asset->id, 'variant_kind' => 'thumbnail', 'storage_key' => 'variants/'.uniqid().'.jpg',
        'mime_type' => 'image/jpeg', 'byte_size' => 4, 'sha256' => str_repeat('b', 64),
        'transform_profile' => PublicMedia::PUBLIC_TRANSFORM_PROFILE, 'state' => 'available',
    ], $attributes));
}

function publicReplacementJpeg(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'public-replacement-');
    $image = imagecreatetruecolor(8, 6);
    imagejpeg($image, $path, 90);
    imagedestroy($image);

    return UploadedFile::fake()->createWithContent('replacement.jpg', file_get_contents($path));
}

it('includes only published artwork in published categories', function () {
    $published = sliceCategory('sculptures', 'published', 0);
    $hidden = sliceCategory('works-a', 'hidden', 1);
    sliceArtwork($published, ['slug' => 'published-work']);
    sliceArtwork($published, ['slug' => 'draft-work', 'state' => 'draft']);
    sliceArtwork($published, ['slug' => 'hidden-work', 'state' => 'hidden']);
    sliceArtwork($published, ['slug' => 'archived-work', 'state' => 'archived']);
    sliceArtwork($hidden, ['slug' => 'hidden-category-work']);

    expect(app(PublicArtworkQuery::class)->category('sculptures')->pluck('slug')->all())->toBe(['published-work']);
    $this->get('/artworks/hidden-category-work')->assertNotFound();
    $this->get('/artworks/draft-work')->assertNotFound();
});

it('orders category artwork solely by curated position', function () {
    $category = sliceCategory('sculptures', 'published', 0);
    sliceArtwork($category, ['slug' => 'older-curated-first', 'work_date' => '2010-01-01', 'position' => 0]);
    sliceArtwork($category, ['slug' => 'newest-curated-last', 'work_date' => '2026-01-01', 'position' => 2]);
    sliceArtwork($category, ['slug' => 'middle-curated', 'work_date' => '2020-01-01', 'position' => 1]);

    expect(app(PublicArtworkQuery::class)->category('sculptures')->pluck('slug')->all())
        ->toBe(['older-curated-first', 'middle-curated', 'newest-curated-last']);
});

it('rejects duplicate curated positions instead of applying tie breakers', function () {
    $category = sliceCategory('works-a', 'published', 0);
    sliceArtwork($category, ['slug' => 'same-position-old', 'work_date' => '2020-01-01', 'position' => 4]);
    sliceArtwork($category, ['slug' => 'same-position-new', 'work_date' => '2025-01-01', 'position' => 4]);

    expect(fn () => app(PublicArtworkQuery::class)->category('works-a'))
        ->toThrow(LogicException::class);
});

it('selects the uniquely newest eligible home artwork and has a usable empty state', function () {
    $sculptures = sliceCategory('sculptures', 'published', 0);
    $worksA = sliceCategory('works-a', 'published', 1);
    $worksB = sliceCategory('works-b', 'published', 2);
    $excluded = sliceCategory('works-c', 'published', 3);
    $excluded->update(['show_on_home' => false]);
    sliceArtwork($sculptures, ['slug' => 'sculpture-home', 'work_date' => '2025-01-01', 'position' => 9]);
    sliceArtwork($worksA, ['slug' => 'works-a-home', 'work_date' => '2026-01-01', 'position' => 1]);
    sliceArtwork($worksB, ['slug' => 'works-b-home', 'work_date' => '2024-01-01']);
    sliceArtwork($excluded, ['slug' => 'excluded-home', 'work_date' => '2030-01-01']);

    expect(app(PublicArtworkQuery::class)->latestForHome()?->slug)->toBe('works-a-home');
    $this->get('/')->assertSuccessful()->assertSee('works-a-home')->assertDontSee('excluded-home');

    Artwork::query()->delete();
    ArtworkCategory::query()->delete();
    $this->get('/')->assertSuccessful()->assertSee('No artwork is currently available.');
});

it('rejects an ambiguous newest home date instead of applying another ordering field', function () {
    $sculptures = sliceCategory('sculptures', 'published', 0);
    $worksA = sliceCategory('works-a', 'published', 1);
    sliceArtwork($sculptures, ['slug' => 'same-date-a', 'work_date' => '2026-01-01', 'position' => 9]);
    sliceArtwork($worksA, ['slug' => 'same-date-b', 'work_date' => '2026-01-01', 'position' => 1]);

    expect(fn () => app(PublicArtworkQuery::class)->latestForHome())
        ->toThrow(LogicException::class);
});

it('keeps homepage latest-work chronological despite curated positions', function () {
    $sculptures = sliceCategory('sculptures', 'published', 0);
    $worksA = sliceCategory('works-a', 'published', 1);
    $worksB = sliceCategory('works-b', 'published', 2);
    sliceArtwork($sculptures, ['slug' => 'home-curated-first-old', 'work_date' => '2010-01-01', 'position' => 0]);
    sliceArtwork($worksA, ['slug' => 'home-newest-position-last', 'work_date' => '2026-01-01', 'position' => 9]);
    sliceArtwork($worksB, ['slug' => 'home-middle', 'work_date' => '2020-01-01', 'position' => 1]);

    expect(app(PublicArtworkQuery::class)->latestForHome()?->slug)->toBe('home-newest-position-last');
});

it('serves arbitrary published category routes and the direct published route', function () {
    Storage::fake(config('media.disk'));
    $sculptures = sliceCategory('sculptures', 'published', 0);
    sliceCategory('works-a', 'published', 1);
    sliceCategory('works-b', 'published', 2);
    $artwork = sliceArtwork($sculptures, ['slug' => 'direct-work']);
    $asset = sliceAsset(['storage_key' => 'originals/direct-work.jpg']);
    attachMedia($artwork, $asset);
    sliceVariant($asset, ['storage_key' => 'variants/direct-work.webp', 'mime_type' => 'image/webp']);

    foreach (['sculptures', 'works-a', 'works-b'] as $slug) {
        $this->get('/'.$slug)->assertSuccessful();
    }

    $this->get('/artworks/direct-work')->assertSuccessful();
    $this->get('/artworks/missing-work')->assertNotFound();
});

it('requires canonical public media instead of substituting alternate sources', function () {
    Storage::fake(config('media.disk'));
    $category = sliceCategory('works-b', 'published', 0);
    $artwork = sliceArtwork($category, ['slug' => 'media-work', 'title' => 'Title is not ALT fallback']);
    $asset = sliceAsset(['storage_key' => 'originals/media.jpg']);
    attachMedia($artwork, $asset, 'primary', 'Override alt');
    $variant = sliceVariant($asset, ['storage_key' => 'variants/media.jpg']);
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'orig');
    Storage::disk(config('media.disk'))->put($variant->storage_key, 'thumb');
    $media = app(PublicMedia::class);

    expect($media->thumbnailUrl($artwork->fresh()))->toBe(route('media.variant', $variant))
        ->and($media->altText($artwork->fresh()))->toBe('Override alt');

    $variant->update(['transform_profile' => 'other-profile']);
    expect(fn () => $media->thumbnailUrl($artwork->fresh()))->toThrow(LogicException::class);

    $variant->update(['transform_profile' => PublicMedia::PUBLIC_TRANSFORM_PROFILE]);
    $asset->update(['state' => 'quarantined']);
    expect(fn () => $media->thumbnailUrl($artwork->fresh()))->toThrow(LogicException::class)
        ->and(fn () => $media->originalUrl($artwork->fresh()))->toThrow(LogicException::class);

    $asset->update(['state' => 'available', 'alt_text' => 'Asset alt']);
    $artwork->artworkMedia()->firstOrFail()->update(['alt_text_override' => null]);
    expect($media->altText($artwork->fresh()))->toBe('Asset alt');

    $asset->update(['alt_text' => null]);
    expect(fn () => $media->altText($artwork->fresh()))->toThrow(LogicException::class);
});

it('delivers only media attached to a public primary artwork', function () {
    Storage::fake(config('media.disk'));
    $publicCategory = sliceCategory('sculptures', 'published', 0);
    $hiddenCategory = sliceCategory('works-a', 'hidden', 1);
    $publicArtwork = sliceArtwork($publicCategory, ['slug' => 'public-media-work']);
    $draftArtwork = sliceArtwork($publicCategory, ['slug' => 'draft-media-work', 'state' => 'draft']);
    $hiddenArtwork = sliceArtwork($hiddenCategory, ['slug' => 'hidden-media-work']);
    $publicAsset = sliceAsset(['storage_key' => 'originals/public.jpg']);
    $draftAsset = sliceAsset(['storage_key' => 'originals/draft.jpg']);
    $hiddenAsset = sliceAsset(['storage_key' => 'originals/hidden.jpg']);
    $unreferencedAsset = sliceAsset(['storage_key' => 'originals/unreferenced.jpg']);
    attachMedia($publicArtwork, $publicAsset);
    attachMedia($draftArtwork, $draftAsset);
    attachMedia($hiddenArtwork, $hiddenAsset);
    $publicVariant = sliceVariant($publicAsset, ['storage_key' => 'variants/public.jpg']);
    $draftVariant = sliceVariant($draftAsset, ['storage_key' => 'variants/draft.jpg']);
    $unreferencedVariant = sliceVariant($unreferencedAsset, ['storage_key' => 'variants/unreferenced.jpg']);
    foreach ([$publicAsset, $draftAsset, $hiddenAsset, $unreferencedAsset] as $asset) {
        Storage::disk(config('media.disk'))->put($asset->storage_key, 'orig');
    }
    foreach ([$publicVariant, $draftVariant, $unreferencedVariant] as $variant) {
        Storage::disk(config('media.disk'))->put($variant->storage_key, 'thumb');
    }

    $response = $this->get(route('media.original', $publicAsset));
    $response->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect(collect(['public', 'max-age=31536000', 'immutable'])
        ->every(fn (string $token) => str_contains((string) $response->headers->get('Cache-Control'), $token)))->toBeTrue();
    $this->get(route('media.variant', $publicVariant))->assertOk();
    foreach ([$draftAsset, $hiddenAsset, $unreferencedAsset] as $asset) {
        $this->get(route('media.original', $asset))->assertNotFound();
    }
    foreach ([$draftVariant, $unreferencedVariant] as $variant) {
        $this->get(route('media.variant', $variant))->assertNotFound();
    }

    attachMedia($publicArtwork, sliceAsset(['storage_key' => 'originals/additional.jpg']), 'additional');
    $additional = ArtworkMedia::query()->where('role', 'additional')->firstOrFail()->mediaAsset;
    $this->get(route('media.original', $additional))->assertNotFound();
    Storage::disk(config('media.disk'))->delete($publicAsset->storage_key);
    Storage::disk(config('media.disk'))->delete($publicVariant->storage_key);
    $this->get(route('media.original', $publicAsset))->assertNotFound();
    $this->get(route('media.variant', $publicVariant))->assertNotFound();
    $publicAsset->update(['state' => 'deleted']);
    $publicVariant->update(['state' => 'stale']);
    $this->get(route('media.original', $publicAsset))->assertNotFound();
    $this->get(route('media.variant', $publicVariant))->assertNotFound();
});

it('keeps the required shell navigation and excludes Blog, Admin, and Contact', function () {
    sliceCategory('sculptures', 'published', 0);
    sliceCategory('works-a', 'published', 1);
    sliceCategory('works-b', 'published', 2);
    $html = $this->get('/')->assertSuccessful()->getContent();
    expect($html)->toContain('Sculptures', 'Works-a', 'Works-b', 'CV &amp; Exhibitions')
        ->and(strpos($html, 'Sculptures'))->toBeLessThan(strpos($html, 'Works-a'))
        ->and(strpos($html, 'Works-a'))->toBeLessThan(strpos($html, 'Works-b'))
        ->and(strpos($html, 'Works-b'))->toBeLessThan(strpos($html, 'CV &amp; Exhibitions'))
        ->and($html)->not->toContain('Blog')->and($html)->not->toContain('Admin')->and($html)->not->toContain('Contact');
});

it('derives navigation from arbitrary category presentation data', function () {
    sliceCategory('sculptures', 'published', 0);
    $hiddenFromNavigation = sliceCategory('works-a', 'published', 1);
    $hiddenFromNavigation->update(['show_in_navigation' => false]);

    $html = $this->get('/')->assertSuccessful()->getContent();

    expect($html)->toContain('Sculptures')->not->toContain('Works-a');
});

it('renders public media metadata with escaped output and preserves explicit ALT precedence', function () {
    Storage::fake(config('media.disk'));
    $category = sliceCategory('sculptures', 'published', 0);
    $artwork = sliceArtwork($category, ['slug' => 'metadata-public-artwork', 'title' => 'Artwork title']);
    $asset = sliceAsset([
        'storage_key' => 'originals/metadata-public.jpg',
        'alt_text' => 'Asset ALT',
        'credit' => '<script>alert(1)</script>',
        'copyright_notice' => '<img src=x onerror=alert(1)>',
    ]);
    attachMedia($artwork, $asset, 'primary', 'Usage ALT');
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'orig');
    $content = $this->get('/artworks/metadata-public-artwork')->assertSuccessful()->getContent();

    expect($content)->toContain('Usage ALT')
        ->and($content)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($content)->toContain('&lt;img src=x onerror=alert(1)&gt;')
        ->and($content)->not->toContain('<script>alert(1)</script>')
        ->and($content)->not->toContain('<img src=x onerror=alert(1)>');
});

it('omits optional public media metadata without substituting required ALT data', function () {
    Storage::fake(config('media.disk'));
    $category = sliceCategory('works-a', 'published', 0);
    $artwork = sliceArtwork($category, ['slug' => 'no-metadata-public']);
    $asset = sliceAsset([
        'storage_key' => 'originals/no-metadata.jpg',
        'alt_text' => 'Explicit artwork ALT',
        'credit' => null,
        'copyright_notice' => null,
    ]);
    attachMedia($artwork, $asset);
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'orig');
    $content = $this->get('/artworks/no-metadata-public')->assertSuccessful()->getContent();
    expect($content)->not->toContain('artwork-credit')->and($content)->not->toContain('artwork-copyright');
    $asset->update(['state' => 'deleted']);
    $this->get(route('media.original', $asset))->assertNotFound();
});

it('switches public delivery to the replacement primary media atomically', function () {
    Storage::fake(config('media.disk'));
    $category = sliceCategory('sculptures', 'published', 0);
    $artwork = sliceArtwork($category, ['slug' => 'replacement-public-artwork']);
    $oldAsset = sliceAsset(['storage_key' => 'originals/replacement-old.jpg']);
    $oldVariant = sliceVariant($oldAsset, ['storage_key' => 'variants/replacement-old.webp', 'mime_type' => 'image/webp']);
    attachMedia($artwork, $oldAsset, 'primary', 'Old override');
    Storage::disk(config('media.disk'))->put($oldAsset->storage_key, 'old');
    Storage::disk(config('media.disk'))->put($oldVariant->storage_key, 'old-thumb');
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    app(ArtworkEditorialService::class)->replacePrimaryMedia($artwork, publicReplacementJpeg());
    $artwork->refresh();
    $newAsset = $artwork->artworkMedia()->where('role', 'primary')->firstOrFail()->mediaAsset;
    $newAsset->update(['alt_text' => 'Replacement ALT']);
    $newVariant = $newAsset->variants()->where('transform_profile', 'public-v1')->firstOrFail();

    $this->get('/artworks/replacement-public-artwork')->assertSuccessful();
    $this->get(route('media.original', $oldAsset))->assertNotFound();
    $this->get(route('media.variant', $oldVariant))->assertNotFound();
    $this->get(route('media.original', $newAsset))->assertSuccessful();
    $this->get(route('media.variant', $newVariant))->assertSuccessful();
    expect(app(PublicMedia::class)->originalUrl($artwork))->toBe(route('media.original', $newAsset))
        ->and(app(PublicMedia::class)->thumbnailUrl($artwork))->toBe(route('media.variant', $newVariant));
});
