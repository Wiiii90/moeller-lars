<?php

use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Publication\PublicationService;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
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

    $this->get(route('media.original', ['mediaAsset' => $asset->getKey()]))
        ->assertNotFound();

    $this->get(route('admin.media.original', ['mediaAsset' => $asset->getKey()]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Sendfile', Storage::disk(config('media.disk'))->path($storageKey));
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
    MediaVariant::query()->create([
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
    $this->get($url)->assertNotFound();

    $publication = app(PublicationService::class);
    expect($publication->commit($actor))->not->toBeNull();

    $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('Cache-Control', 'public, max-age=31536000, immutable');

    // An editorial change must not leak before the next explicit Commit.
    $settings->update(['blocks' => [[...$publishedImage, 'published' => false]]]);
    $this->get($url)->assertOk();

    expect($publication->commit($actor))->not->toBeNull();
    $this->get($url)->assertNotFound();

    // Removing the public reference does not delete the underlying stored file.
    Storage::disk(config('media.disk'))->assertExists($originalKey);
});
