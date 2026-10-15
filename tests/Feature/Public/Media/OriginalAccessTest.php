<?php

use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Publication\PublicationService;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

it('denies public delivery of an unreferenced original even after publication, while the admin can access it', function (): void {
    Storage::fake('public-media-access-test');
    config(['media.disk' => 'public-media-access-test']);

    $contents = 'synthetic-media-content';
    $storageKey = 'originals/not-public.jpg';
    Storage::disk(config('media.disk'))->put($storageKey, $contents);

    $asset = MediaAsset::query()->create([
        'storage_key' => $storageKey,
        'original_filename' => 'not-public.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => strlen($contents),
        'sha256' => hash('sha256', $contents),
        'state' => 'available',
        'alt_text' => 'Private media fixture',
    ]);

    $adminUrl = route('admin.media.original', ['mediaAsset' => $asset->getKey()]);
    $this->get($adminUrl)->assertForbidden();
    $this->actingAs(User::factory()->create(), 'web')
        ->get($adminUrl)
        ->assertForbidden();

    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    expect(app(PublicationService::class)->commit($actor))->not->toBeNull();

    $this->get(route('admin.media.original', ['mediaAsset' => $asset->getKey()]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Sendfile', Storage::disk(config('media.disk'))->path($storageKey));

    Auth::guard('web')->logout();
    $this->get(route('media.original', ['mediaAsset' => $asset->getKey()]))
        ->assertNotFound();
});

it('serves a page image only while its reference is part of the committed public website', function (): void {
    Storage::fake('public-media-publication-test');
    config(['media.disk' => 'public-media-publication-test']);

    $originalKey = 'originals/page-image.jpg';
    $variantKey = 'variants/page-image.webp';
    Storage::disk(config('media.disk'))->put($originalKey, 'original-image');
    Storage::disk(config('media.disk'))->put($variantKey, 'thumbnail-image');

    $asset = MediaAsset::query()->create([
        'storage_key' => $originalKey,
        'original_filename' => 'page-image.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => strlen('original-image'),
        'sha256' => hash('sha256', 'original-image'),
        'state' => 'available',
        'alt_text' => 'Published page image',
        'width' => 100,
        'height' => 100,
    ]);
    $variant = MediaVariant::query()->create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => $variantKey,
        'mime_type' => 'image/webp',
        'byte_size' => strlen('thumbnail-image'),
        'sha256' => hash('sha256', 'thumbnail-image'),
        'transform_profile' => 'public-v1',
        'state' => 'available',
        'width' => 20,
        'height' => 20,
    ]);

    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    $editor = app(SiteSectionEditorialService::class);
    $page = $editor->createCustomPage('Image publication test', 'image-publication-test');
    $settings = $page->customPageSetting()->firstOrFail();
    $publishedImage = [
        'type' => 'image',
        'published' => true,
        'media_asset_id' => (int) $asset->getKey(),
        'image_decorative' => false,
    ];
    $settings->update(['blocks' => [$publishedImage]]);
    $editor->updatePlacement($page, 'published', null);

    $url = route('media.original', ['mediaAsset' => $asset->getKey()]);
    $variantUrl = route('media.variant', ['mediaVariant' => $variant->getKey()]);

    $this->get($url)->assertNotFound();
    $this->get($variantUrl)->assertNotFound();

    $previewOriginal = route('preview.media.original', ['mediaAsset' => $asset->getKey()]);
    $previewVariant = route('preview.media.variant', ['mediaVariant' => $variant->getKey()]);

    // Working media remains private even when the actual original and variant
    // files exist. Signing in as an ordinary user is not sufficient.
    Auth::guard('web')->logout();
    $this->get($previewOriginal)->assertNotFound();
    $this->get($previewVariant)->assertNotFound();

    $this->actingAs(User::factory()->create(), 'web');
    $this->get($previewOriginal)->assertNotFound();
    $this->get($previewVariant)->assertNotFound();

    // Only an administrator may inspect the Working media before Commit.
    $this->actingAs($actor, 'web');
    $this->get($previewOriginal)
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-store, private');
    $this->get($previewVariant)->assertOk();

    $publication = app(PublicationService::class);
    expect($publication->commit($actor))->not->toBeNull();

    // Committed content is public to guests, not merely visible to the
    // administrator who authored the draft.
    Auth::guard('web')->logout();
    $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    $this->get($variantUrl)->assertOk()->assertHeader('Content-Type', 'image/webp');

    $this->actingAs($actor, 'web');

    // An editorial change must not leak before the next explicit Commit.
    $settings->update(['blocks' => [[...$publishedImage, 'published' => false]]]);
    $this->get($url)->assertOk();
    $this->get($variantUrl)->assertOk();

    expect($publication->commit($actor))->not->toBeNull();
    Auth::guard('web')->logout();
    $this->get($url)->assertNotFound();
    $this->get($variantUrl)->assertNotFound();

    // Removing the public reference does not delete the underlying stored file.
    Storage::disk(config('media.disk'))->assertExists($originalKey);
});
