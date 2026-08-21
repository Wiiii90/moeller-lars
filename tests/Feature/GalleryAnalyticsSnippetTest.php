<?php

use App\Domain\Analytics\MatomoReportingClient;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function galleryAnalyticsFixture(ArtworkCategory $gallery): Artwork
{
    return Artwork::create([
        'artwork_category_id' => $gallery->getKey(),
        'slug' => 'analytics-work',
        'title' => 'Analytics work',
        'state' => 'published',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);
}

/** @return array<int, array<mixed>> */
function galleryAnalyticsPayload(string $artworkKey): array
{
    $payload = array_fill(0, 31, []);
    $payload[0] = [
        'nb_visits' => 12,
        'nb_uniq_visitors' => 9,
        'nb_actions' => 30,
        'nb_actions_per_visit' => 2.5,
        'avg_time_on_site' => 94,
        'bounce_rate' => '25%',
    ];
    $payload[1] = [
        'nb_visits' => 10,
        'nb_uniq_visitors' => 8,
        'nb_actions' => 24,
        'nb_actions_per_visit' => 2.4,
        'avg_time_on_site' => 80,
        'bounce_rate' => '30%',
    ];
    $payload[2] = [
        '2026-08-20' => ['nb_visits' => 5, 'nb_actions' => 12],
        '2026-08-21' => ['nb_visits' => 7, 'nb_actions' => 18],
    ];
    $payload[3] = [
        ['label' => 'https://artist.example/analytics-gallery', 'nb_visits' => 4, 'nb_hits' => 9],
    ];
    $payload[29] = [
        [
            'label' => 'artwork_open',
            'subtable' => [
                ['label' => $artworkKey, 'nb_events' => 3, 'nb_visits' => 2, 'nb_uniq_visitors' => 2],
            ],
        ],
        [
            'label' => 'artwork_attention',
            'subtable' => [
                [
                    'label' => $artworkKey,
                    'nb_events' => 2,
                    'nb_visits' => 2,
                    'nb_uniq_visitors' => 2,
                    'nb_events_with_value' => 2,
                    'sum_event_value' => 18,
                    'avg_event_value' => 9,
                ],
            ],
        ],
    ];
    $payload[30] = [
        '2026-08-21' => [
            [
                'label' => 'artwork_open',
                'subtable' => [
                    ['label' => $artworkKey, 'nb_events' => 3, 'nb_visits' => 2, 'nb_uniq_visitors' => 2],
                ],
            ],
            [
                'label' => 'artwork_attention',
                'subtable' => [
                    [
                        'label' => $artworkKey,
                        'nb_events' => 2,
                        'nb_events_with_value' => 2,
                        'sum_event_value' => 18,
                        'avg_event_value' => 9,
                    ],
                ],
            ],
        ],
    ];

    return $payload;
}

it('does not fabricate a Gallery analytics strip when canonical reporting is unavailable', function (): void {
    Config::set('analytics.matomo.reporting_enabled', false);
    $gallery = ArtworkCategory::create([
        'name' => 'No analytics Gallery',
        'slug' => 'no-analytics-gallery',
        'show_on_home' => false,
    ]);
    testGallerySection($gallery, ['state' => 'published', 'show_in_navigation' => false]);
    galleryAnalyticsFixture($gallery);

    $this->get(ArtworkResource::getUrl('gallery', ['gallery' => $gallery->getKey()]))
        ->assertSuccessful()
        ->assertDontSee('30d analytics');
});

it('renders the Gallery summary from the canonical Matomo reporting projection when real signal exists', function (): void {
    Config::set('analytics.matomo.reporting_enabled', true);
    Config::set('analytics.matomo.base_url', 'https://analytics.example.test');
    Config::set('analytics.matomo.site_id', 7);
    Config::set('analytics.matomo.api_token', 'gallery-reporting-token');
    Config::set('analytics.matomo.report_timeout_seconds', 5);
    Config::set('analytics.matomo.report_cache_seconds', 600);
    Config::set('analytics.matomo.report_stale_seconds', 3600);

    $gallery = ArtworkCategory::create([
        'name' => 'Analytics Gallery',
        'slug' => 'analytics-gallery',
        'show_on_home' => false,
    ]);
    testGallerySection($gallery, ['state' => 'published', 'show_in_navigation' => false]);
    $artwork = galleryAnalyticsFixture($gallery);

    Http::fake([
        'https://analytics.example.test/index.php' => Http::response(
            galleryAnalyticsPayload((string) $artwork->getAttribute('analytics_key')),
        ),
    ]);

    // The Gallery is a consumer of the canonical cache-first reporting projection.
    // Warm that cache explicitly; first-paint / stale-while-revalidate behavior is
    // covered separately by DeferMatomoReportingTest.
    app(MatomoReportingClient::class)->report('30d');

    $this->get(ArtworkResource::getUrl('gallery', ['gallery' => $gallery->getKey()]))
        ->assertSuccessful()
        ->assertSee('30d analytics')
        ->assertSee('4 visits')
        ->assertSee('9 views')
        ->assertSee('5 artwork interactions')
        ->assertSee('Top work: Analytics work')
        ->assertSee('Latest tracked day: 2026-08-21');

    Http::assertSentCount(1);
});
