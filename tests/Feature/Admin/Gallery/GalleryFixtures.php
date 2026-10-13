<?php

use App\Domain\Analytics\ArtistReportingService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;


function galleryFixtureCategory(string $state = 'published'): ArtworkCategory
{
    static $sequence = 0;
    $sequence++;

    $gallery = ArtworkCategory::query()->create([
        'slug' => 'gallery-fixture-'.$sequence,
        'name' => 'Gallery fixture '.$sequence,
        'show_on_home' => false,
    ]);
    testGallerySection($gallery, ['state' => $state]);

    return $gallery;
}

function galleryFixtureArtwork(?ArtworkCategory $gallery, array $overrides = []): Artwork
{
    static $sequence = 0;
    $sequence++;

    return Artwork::query()->create(array_merge([
        'artwork_category_id' => $gallery?->getKey(),
        'slug' => 'gallery-artwork-fixture-'.$sequence,
        'title' => 'Artwork fixture '.$sequence,
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ], $overrides));
}

function galleryFixtureAsset(string $mime = 'image/jpeg', string $alt = 'Artwork media'): MediaAsset
{
    static $sequence = 0;
    $sequence++;

    return MediaAsset::query()->create([
        'storage_key' => 'originals/gallery-media-fixture-'.$sequence.($mime === 'video/mp4' ? '.mp4' : '.jpg'),
        'original_filename' => $mime === 'video/mp4' ? 'artwork.mp4' : 'artwork.jpg',
        'mime_type' => $mime,
        'byte_size' => 16,
        'sha256' => hash('sha256', 'gallery-media-fixture-'.$sequence),
        'state' => 'available',
        'alt_text' => $alt,
        'width' => $mime === 'video/mp4' ? null : 20,
        'height' => $mime === 'video/mp4' ? null : 10,
    ]);
}

function galleryFixturePrimary(Artwork $artwork, MediaAsset $asset): ArtworkMedia
{
    return ArtworkMedia::query()->create([
        'artwork_id' => $artwork->getKey(),
        'media_asset_id' => $asset->getKey(),
        'role' => 'primary',
        'position' => 0,
    ]);
}

function galleryFixtureThumbnail(MediaAsset $asset): MediaVariant
{
    static $sequence = 0;
    $sequence++;

    return MediaVariant::query()->create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/gallery-thumbnail-fixture-'.$sequence.'.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 8,
        'sha256' => hash('sha256', 'gallery-thumbnail-fixture-'.$sequence),
        'transform_profile' => 'public-v1',
        'state' => 'available',
        'width' => 10,
        'height' => 5,
    ]);
}

function galleryFixtureUnavailableAnalytics(): void
{
    app()->instance(ArtistReportingService::class, new class
    {
        /** @param list<string> $artworkAnalyticsKeys */
        public function gallery(string $publicPath, array $artworkAnalyticsKeys, string $range = '30d'): array
        {
            return [
                'status' => 'unavailable',
                'page' => [],
                'artworks' => [
                    'state' => 'unavailable',
                    'rows' => [],
                ],
            ];
        }
    });
}
