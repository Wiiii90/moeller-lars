<?php

use App\Domain\Analytics\ArtistReportingService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Config::set('analytics.matomo.reporting_enabled', true);
    Config::set('analytics.matomo.base_url', 'https://analytics.example.test');
    Config::set('analytics.matomo.site_id', 7);
    Config::set('analytics.matomo.api_token', 'secret-reporting-token');
    Config::set('analytics.matomo.report_timeout_seconds', 5);
    Config::set('analytics.matomo.report_cache_seconds', 600);
    Config::set('analytics.matomo.report_stale_seconds', 3600);
});

/** @return array<int, array<mixed>> */
function artistReportingPayload(?string $artworkKey = null): array
{
    $payload = array_fill(0, 31, []);
    $payload[0] = [
        'nb_visits' => 12,
        'nb_uniq_visitors' => 9,
        'nb_actions' => 30,
        'nb_actions_per_visit' => 2.5,
        'avg_time_on_site' => 94,
        'bounce_rate' => '25%',
        'visitor_ip' => '203.0.113.10',
        'user_agent' => 'must-never-leak',
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
        ['label' => 'https://artist.example/paintings?private=1', 'nb_visits' => 4, 'nb_hits' => 9],
        ['label' => 'https://artist.example/blog/studio-note?draft-token=secret', 'nb_visits' => 3, 'nb_hits' => 7],
        ['label' => 'https://artist.example/exhibitions', 'nb_visits' => 2, 'nb_hits' => 5],
    ];
    $payload[10] = [
        ['label' => 'blog_view', 'nb_events' => 7, 'nb_visits' => 4],
        ['label' => 'exhibition_view', 'nb_events' => 3, 'nb_visits' => 2],
        ['label' => 'exhibition_external_click', 'nb_events' => 2, 'nb_visits' => 2],
        ['label' => 'exhibition_directions_click', 'nb_events' => 1, 'nb_visits' => 1],
        ['label' => 'contact_submit_success', 'nb_events' => 2, 'nb_visits' => 2],
        ['label' => 'email_click', 'nb_events' => null, 'nb_visits' => 1],
    ];

    if ($artworkKey !== null) {
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
    }

    return $payload;
}

it('projects canonical dashboard blog exhibition and contact snippets from one cached Matomo report', function (): void {
    Http::fake([
        'https://analytics.example.test/index.php' => Http::response(artistReportingPayload()),
    ]);

    $reporting = app(ArtistReportingService::class);
    $dashboard = $reporting->dashboard('7d');
    $blog = $reporting->blog('/blog/studio-note?preview=1', '7d');
    $exhibitions = $reporting->exhibitions('7d');
    $contact = $reporting->contact('7d');

    expect($dashboard['status'])->toBe('available')
        ->and($dashboard['metrics']['visits'])->toBe(['state' => 'available', 'value' => 12.0])
        ->and($dashboard['metrics']['visitors'])->toBe(['state' => 'available', 'value' => 9.0])
        ->and($dashboard['trend']['state'])->toBe('available')
        ->and($blog['post']['views'])->toBe(['state' => 'available', 'value' => 7.0])
        ->and($blog['reads'])->toBe(['state' => 'available', 'value' => 7.0])
        ->and($blog['top_posts']['rows'][0]['label'])->toBe('/blog/studio-note')
        ->and($exhibitions['external_clicks'])->toBe(['state' => 'available', 'value' => 2.0])
        ->and($exhibitions['directions_clicks'])->toBe(['state' => 'available', 'value' => 1.0])
        ->and($contact['messages'])->toBe(['state' => 'available', 'value' => 2.0])
        ->and($contact['instagram_clicks'])->toBe(['state' => 'available', 'value' => 0.0])
        ->and($contact['email_clicks'])->toBe(['state' => 'unavailable', 'value' => null]);

    Http::assertSentCount(1);
    expect(json_encode([$dashboard, $blog, $exhibitions, $contact]))
        ->not->toContain('secret-reporting-token')
        ->not->toContain('203.0.113.10')
        ->not->toContain('must-never-leak')
        ->not->toContain('draft-token');
});

it('keeps unsupported range visitors distinct from measured zero', function (): void {
    $payload = artistReportingPayload();
    unset($payload[0]['nb_uniq_visitors']);

    Http::fake([
        'https://analytics.example.test/index.php' => Http::response($payload),
    ]);

    $dashboard = app(ArtistReportingService::class)->dashboard('30d');

    expect($dashboard['metrics']['visitors'])->toBe(['state' => 'unsupported', 'value' => null])
        ->and($dashboard['metrics']['visits'])->toBe(['state' => 'available', 'value' => 12.0]);
});

it('marks all embedded human metrics unavailable when Matomo fails without a cache', function (): void {
    Http::fake([
        'https://analytics.example.test/index.php' => Http::response([], 503),
    ]);

    $dashboard = app(ArtistReportingService::class)->dashboard('today');
    $contact = app(ArtistReportingService::class)->contact('today');

    expect($dashboard['status'])->toBe('unavailable')
        ->and($dashboard['metrics']['visits'])->toBe(['state' => 'unavailable', 'value' => null])
        ->and($dashboard['trend'])->toBe(['state' => 'unavailable', 'rows' => []])
        ->and($contact['messages'])->toBe(['state' => 'unavailable', 'value' => null]);
});

it('preserves stale aggregate reporting for embedded snippets after a live failure', function (): void {
    Http::fake([
        'https://analytics.example.test/index.php' => Http::sequence()
            ->push(artistReportingPayload(), 200)
            ->push([], 503),
    ]);

    $reporting = app(ArtistReportingService::class);
    $live = $reporting->dashboard('today');
    expect($live['status'])->toBe('available');

    Cache::forget('analytics:matomo:v5:site:7:today:fresh');
    $stale = $reporting->dashboard('today');

    expect($stale['status'])->toBe('stale')
        ->and($stale['metrics']['visits'])->toBe(['state' => 'available', 'value' => 12.0]);
});

it('projects Gallery page attention and trend without querying unrelated artwork keys', function (): void {
    $category = ArtworkCategory::create([
        'name' => 'Paintings',
        'slug' => 'paintings',
        'state' => 'published',
        'position' => 0,
        'show_in_navigation' => true,
        'show_on_home' => false,
    ]);
    $artwork = Artwork::create([
        'artwork_category_id' => $category->getKey(),
        'slug' => 'reporting-work',
        'title' => 'Reporting Work',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);
    $key = (string) $artwork->getAttribute('analytics_key');

    Http::fake([
        'https://analytics.example.test/index.php' => Http::response(artistReportingPayload($key)),
    ]);

    $gallery = app(ArtistReportingService::class)->gallery('/paintings?admin-preview=1', [$key], '30d');

    expect($gallery['page']['visits'])->toBe(['state' => 'available', 'value' => 4.0])
        ->and($gallery['page']['views'])->toBe(['state' => 'available', 'value' => 9.0])
        ->and($gallery['artworks']['state'])->toBe('available')
        ->and($gallery['artworks']['rows'])->toHaveCount(1)
        ->and($gallery['artworks']['rows'][0]['analytics_key'])->toBe($key)
        ->and($gallery['artworks']['rows'][0]['viewer_opens'])->toBe(3)
        ->and($gallery['artwork_interactions'])->toBe(['state' => 'available', 'value' => 5.0])
        ->and($gallery['trend']['state'])->toBe('available')
        ->and($gallery['trend']['rows'][0]['viewer_opens'])->toBe(3)
        ->and($gallery['trend']['rows'][0]['attention_seconds'])->toBe(18.0);
});

it('accepts every canonical range and rejects unknown ranges before making a request', function (): void {
    Http::fake([
        'https://analytics.example.test/index.php' => Http::response(artistReportingPayload()),
    ]);

    $reporting = app(ArtistReportingService::class);
    foreach (['today', '7d', '30d', '12m'] as $range) {
        expect($reporting->dashboard($range)['status'])->toBe('available');
    }
    Http::assertSentCount(4);

    expect(fn () => $reporting->dashboard('90d'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported analytics range.');
    Http::assertSentCount(4);
});
