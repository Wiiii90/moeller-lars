<?php

use App\Domain\Content\HomeHeroConfigurationService;
use App\Domain\Content\HomeHeroResolver;
use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomePresentationResolver;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\HomePresentationSetting;
use App\Models\SiteSection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;


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
