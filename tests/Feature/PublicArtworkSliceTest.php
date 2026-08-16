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

function sliceCategory(string $slug, string $state = 'published'): ArtworkCategory
{
    $category = ArtworkCategory::query()->firstOrNew(['slug' => $slug]);
    $category->fill(['name' => ucfirst($slug), 'state' => $state, 'position' => 0]);
    $category->save();

    return $category;
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
    $published = sliceCategory('paintings');
    $hidden = sliceCategory('prints', 'hidden');
    sliceArtwork($published, ['slug' => 'published-work']);
    sliceArtwork($published, ['slug' => 'draft-work', 'state' => 'draft']);
    sliceArtwork($published, ['slug' => 'hidden-work', 'state' => 'hidden']);
    sliceArtwork($published, ['slug' => 'archived-work', 'state' => 'archived']);
    sliceArtwork($hidden, ['slug' => 'hidden-category-work']);

    expect(app(PublicArtworkQuery::class)->category('paintings')->pluck('slug')->all())->toBe(['published-work']);
    $this->get('/artworks/hidden-category-work')->assertNotFound();
    $this->get('/artworks/draft-work')->assertNotFound();
});

it('orders category artwork by work date descending and position ascending', function () {
    $category = sliceCategory('paintings');
    sliceArtwork($category, ['slug' => 'old', 'work_date' => '2020-01-01', 'position' => 99]);
    sliceArtwork($category, ['slug' => 'same-date-first', 'work_date' => '2025-01-01', 'position' => 1]);
    sliceArtwork($category, ['slug' => 'same-date-second', 'work_date' => '2025-01-01', 'position' => 2]);
    sliceArtwork($category, ['slug' => 'no-date', 'work_date' => null, 'position' => 0]);

    expect(app(PublicArtworkQuery::class)->category('paintings')->pluck('slug')->all())
        ->toBe(['same-date-first', 'same-date-second', 'old', 'no-date']);
});

it('selects the newest eligible home artwork and has a usable empty state', function () {
    $paintings = sliceCategory('paintings');
    $drawings = sliceCategory('drawings');
    $prints = sliceCategory('prints');
    $photo = sliceCategory('photo');
    sliceArtwork($paintings, ['slug' => 'painting-home', 'work_date' => '2026-01-01', 'position' => 9]);
    sliceArtwork($drawings, ['slug' => 'drawing-home', 'work_date' => '2026-01-01', 'position' => 1]);
    sliceArtwork($prints, ['slug' => 'print-home', 'work_date' => '2025-01-01']);
    sliceArtwork($photo, ['slug' => 'photo-not-home', 'work_date' => '2030-01-01']);

    expect(app(PublicArtworkQuery::class)->latestForHome()?->slug)->toBe('drawing-home');
    $this->get('/')->assertSuccessful()->assertSee('drawing-home')->assertDontSee('photo-not-home');

    Artwork::query()->delete();
    ArtworkCategory::query()->delete();
    $this->get('/')->assertSuccessful()->assertSee('No artwork is currently available.');
});

it('serves all canonical category routes and the direct published route', function () {
    $paintings = sliceCategory('paintings');
    sliceArtwork($paintings, ['slug' => 'direct-work']);
    foreach ([
        'paintings',
        'prints',
        'drawings',
        'cyanotype',
        'bichromate',
        'litho',
        'photo',
        'ignis',
        'other',
    ] as $slug) {
        if ($slug !== 'paintings') {
            sliceCategory($slug);
        }
        $this->get('/'.$slug)->assertSuccessful();
    }

    $this->get('/artworks/direct-work')->assertSuccessful();
    $this->get('/artworks/missing-work')->assertNotFound();
});

it('resolves thumbnail, original fallback, missing media, and alt precedence', function () {
    Storage::fake(config('media.disk'));
    $category = sliceCategory('drawings');
    $artwork = sliceArtwork($category, ['slug' => 'media-work', 'title' => 'Title alt']);
    $asset = sliceAsset(['storage_key' => 'originals/media.jpg']);
    attachMedia($artwork, $asset, 'primary', 'Override alt');
    $variant = sliceVariant($asset, ['storage_key' => 'variants/media.jpg']);
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'orig');
    Storage::disk(config('media.disk'))->put($variant->storage_key, 'thumb');
    $media = app(PublicMedia::class);

    expect($media->thumbnailUrl($artwork->fresh()))->toBe(route('media.variant', $variant))
        ->and($media->altText($artwork->fresh()))->toBe('Override alt');
    $variant->update(['transform_profile' => 'other-profile']);
    expect($media->thumbnailUrl($artwork->fresh()))->toBe(route('media.original', $asset));
    $asset->update(['state' => 'quarantined']);
    expect($media->thumbnailUrl($artwork->fresh()))->toBeNull()->and($media->originalUrl($artwork->fresh()))->toBeNull();
    $asset->update(['state' => 'available', 'alt_text' => 'Asset alt']);
    $artwork->artworkMedia()->firstOrFail()->update(['alt_text_override' => null]);
    expect($media->altText($artwork->fresh()))->toBe('Asset alt');
    $asset->update(['alt_text' => null]);
    expect($media->altText($artwork->fresh()))->toBe('Title alt');
});

it('delivers only media attached to a public primary artwork', function () {
    Storage::fake(config('media.disk'));
    $publicCategory = sliceCategory('paintings');
    $hiddenCategory = sliceCategory('prints', 'hidden');
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

it('handles every legacy redirect and rejects malformed values', function () {
    $redirects = [
        'paintings' => '/paintings', 'prints' => '/prints', 'drawings' => '/drawings',
        'cyanotype' => '/cyanotype', 'bichromate' => '/bichromate', 'litho' => '/litho',
        'photo' => '/photo', 'ignis' => '/ignis', 'other' => '/other', 'vita' => '/cv', 'contact' => '/contact',
    ];
    foreach ($redirects as $site => $target) {
        $this->get('/index.php?site='.$site)->assertMovedPermanently()->assertRedirect($target);
    }
    $this->get('/index.php')->assertMovedPermanently()->assertRedirect('/');
    $this->get('/index.php?site=links')->assertNotFound();
    $this->get('/index.php?site=unknown')->assertNotFound();
    $this->get('/index.php?site[]=paintings')->assertNotFound()->assertDontSee('Exception');
    $this->get('/index.php?site[bad]=value')->assertNotFound()->assertDontSee('Exception');
});

it('keeps the required shell navigation and excludes Blog, Admin, and Contact', function () {
    $html = $this->get('/')->assertSuccessful()->getContent();
    expect($html)->toContain('Paintings', 'Prints', 'Drawings', 'CV &amp; Exhibitions')
        ->and(strpos($html, 'Paintings'))->toBeLessThan(strpos($html, 'Prints'))
        ->and(strpos($html, 'Prints'))->toBeLessThan(strpos($html, 'Drawings'))
        ->and(strpos($html, 'Drawings'))->toBeLessThan(strpos($html, 'CV &amp; Exhibitions'))
        ->and($html)->not->toContain('Blog')->and($html)->not->toContain('Admin')->and($html)->not->toContain('Contact');
});

it('renders public media metadata with escaped output and preserves ALT precedence', function () {
    Storage::fake(config('media.disk'));
    $category = sliceCategory('paintings');
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

it('omits empty public media metadata and keeps deleted media unavailable', function () {
    Storage::fake(config('media.disk'));
    $category = sliceCategory('prints');
    $artwork = sliceArtwork($category, ['slug' => 'no-metadata-public']);
    $asset = sliceAsset(['storage_key' => 'originals/no-metadata.jpg', 'alt_text' => null, 'credit' => null, 'copyright_notice' => null]);
    attachMedia($artwork, $asset);
    Storage::disk(config('media.disk'))->put($asset->storage_key, 'orig');
    $content = $this->get('/artworks/no-metadata-public')->assertSuccessful()->getContent();
    expect($content)->not->toContain('artwork-credit')->and($content)->not->toContain('artwork-copyright');
    $asset->update(['state' => 'deleted']);
    $this->get(route('media.original', $asset))->assertNotFound();
});

it('switches public delivery to the replacement primary media atomically', function () {
    Storage::fake(config('media.disk'));
    $category = sliceCategory('paintings');
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
    $newVariant = $newAsset->variants()->where('transform_profile', 'public-v1')->firstOrFail();

    $this->get('/artworks/replacement-public-artwork')->assertSuccessful();
    $this->get(route('media.original', $oldAsset))->assertNotFound();
    $this->get(route('media.variant', $oldVariant))->assertNotFound();
    $this->get(route('media.original', $newAsset))->assertSuccessful();
    $this->get(route('media.variant', $newVariant))->assertSuccessful();
    expect(app(PublicMedia::class)->originalUrl($artwork))->toBe(route('media.original', $newAsset))
        ->and(app(PublicMedia::class)->thumbnailUrl($artwork))->toBe(route('media.variant', $newVariant));
});
