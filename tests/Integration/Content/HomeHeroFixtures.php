<?php

use App\Domain\Content\HomeHeroConfigurationService;
use App\Domain\Content\HomePresentationEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\HomePresentationSetting;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\SiteSection;


function heroDomainSettings(array $artworkConfiguration = []): HomePresentationSetting
{
    $home = SiteSection::query()
        ->where('type', SiteSection::TYPE_HOME)
        ->firstOrFail();

    $configuration = HomePresentationEditorialService::defaults();
    $configuration['artwork'] = array_replace($configuration['artwork'], $artworkConfiguration);

    $settings = HomePresentationSetting::query()
        ->where('site_section_id', $home->getKey())
        ->firstOrFail();
    $settings->setAttribute('template', 'artwork');
    $settings->setAttribute('configuration', $configuration);
    $settings->save();

    return $settings;
}

function heroDomainCategory(string $name = 'Hero source'): ArtworkCategory
{
    $category = new ArtworkCategory;
    $category->fill([
        'slug' => 'hero-source-'.fake()->unique()->uuid(),
        'name' => $name,
        'show_on_home' => true,
    ]);
    $category->save();
    testGallerySection($category, ['state' => 'published']);

    return $category;
}

function heroDomainArtwork(ArtworkCategory $category, string $title, int $position = 0): Artwork
{
    $artwork = new Artwork;
    $artwork->fill([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'hero-artwork-'.fake()->unique()->uuid(),
        'title' => $title,
        'state' => 'published',
        'position' => $position,
        'work_year' => 2026,
        'date_precision' => 'year',
    ]);
    $artwork->save();

    // Hero candidates need one renderable primary image, including ALT and
    // the canonical thumbnail. These domain tests only read media metadata.
    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/hero-domain-'.$artwork->getKey().'.jpg',
        'original_filename' => 'hero-domain.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'hero-original-'.$artwork->getKey()),
        'state' => 'available',
        'alt_text' => $title,
        'width' => 2,
        'height' => 2,
    ]);
    MediaVariant::query()->create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'transform_profile' => 'public-v1',
        'storage_key' => 'variants/hero-domain-'.$artwork->getKey().'.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'hero-thumbnail-'.$artwork->getKey()),
        'state' => 'available',
        'width' => 2,
        'height' => 2,
    ]);
    $artwork->artworkMedia()->create([
        'media_asset_id' => $asset->getKey(),
        'role' => 'primary',
        'position' => 0,
    ]);

    return $artwork;
}

function heroManualMembers(Artwork ...$artworks): array
{
    $count = count($artworks);
    $base = intdiv(HomeHeroConfigurationService::WEIGHT_TOTAL, $count);
    $remainder = HomeHeroConfigurationService::WEIGHT_TOTAL - ($base * $count);

    return array_map(
        static fn (Artwork $artwork, int $index): array => [
            'artwork_id' => (int) $artwork->getKey(),
            'weight' => $base + ($index < $remainder ? 1 : 0),
        ],
        $artworks,
        array_keys($artworks),
    );
}
