<?php

use App\Models\ArtworkCategory;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->group('unit')->in('Unit');

// These cases use Laravel services and model casts without persisting records.
pest()->extend(Illuminate\Foundation\Testing\TestCase::class)->in(
    'Unit/Artwork/ArtworkDateTest.php',
    'Unit/Artwork/GalleryDividerPresentationTest.php',
    'Unit/Artwork/GalleryPresentationTest.php',
    'Unit/Content/RegionalFormatsTest.php',
    'Unit/Content/SafeRichTextRendererTest.php',
);

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->group('integration')
    ->in('Integration');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->group('feature')
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->group('migration')
    ->in('Migration');

/** @param array<string, mixed> $overrides */
function testGallerySection(ArtworkCategory $category, array $overrides = []): SiteSection
{
    $parentId = $overrides['parent_id'] ?? null;
    if (! array_key_exists('position', $overrides)) {
        /** @var Builder<SiteSection> $siblings */
        $siblings = SiteSection::query();
        $parentId === null ? $siblings->whereNull('parent_id') : $siblings->where('parent_id', $parentId);
        if ($parentId === null) {
            $siblings->where('type', '<>', SiteSection::TYPE_HOME);
        }
        $overrides['position'] = ((int) ($siblings->max('position') ?? 0)) + 10;
    }

    return SiteSection::query()->create(array_merge([
        'type' => SiteSection::TYPE_GALLERY,
        'title' => (string) $category->getAttribute('name'),
        'navigation_label' => (string) $category->getAttribute('name'),
        'slug' => (string) $category->getAttribute('slug'),
        'state' => 'published',
        'parent_id' => $parentId,
        'artwork_category_id' => (int) $category->getKey(),
    ], $overrides));
}

function testStoredImageThumbnail(MediaAsset $asset): MediaVariant
{
    $key = 'variants/test-'.fake()->uuid().'.webp';
    $image = imagecreatetruecolor(2, 2);
    if ($image === false) {
        throw new RuntimeException('Unable to create the test thumbnail.');
    }
    ob_start();
    try {
        if (! imagewebp($image)) {
            throw new RuntimeException('Unable to encode the test thumbnail.');
        }
        $bytes = ob_get_contents();
    } finally {
        ob_end_clean();
        imagedestroy($image);
    }
    if (! is_string($bytes) || $bytes === '' || ! Storage::disk(config('media.disk'))->put($key, $bytes)) {
        throw new RuntimeException('Unable to store the test thumbnail.');
    }

    return MediaVariant::query()->create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => $key,
        'mime_type' => 'image/webp',
        'byte_size' => strlen($bytes),
        'sha256' => hash('sha256', $bytes),
        'state' => 'available',
        'width' => 2,
        'height' => 2,
    ]);
}
