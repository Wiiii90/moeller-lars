<?php

use App\Domain\Analytics\MatomoReportingClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('preserves unavailable row metrics instead of coercing them to zero', function () {
    Cache::flush();

    Config::set('analytics.matomo.reporting_enabled', true);
    Config::set('analytics.matomo.tracking_enabled', false);
    Config::set('analytics.matomo.base_url', 'https://analytics.example.test');
    Config::set('analytics.matomo.site_id', 7);
    Config::set('analytics.matomo.api_token', 'secret-reporting-token');
    Config::set('analytics.matomo.report_timeout_seconds', 5);
    Config::set('analytics.matomo.report_cache_seconds', 600);
    Config::set('analytics.matomo.report_stale_seconds', 3600);

    $summary = [
        'nb_visits' => 10,
        'nb_uniq_visitors' => 8,
        'nb_actions' => 24,
        'nb_actions_per_visit' => 2.4,
        'avg_time_on_site' => 91,
        'bounce_rate' => '35%',
    ];

    $payload = array_fill(0, 31, []);
    $payload[0] = $summary;
    $payload[1] = $summary;
    $payload[2] = [
        '2026-08-20' => ['nb_visits' => 0, 'nb_actions' => 0],
    ];
    $payload[3] = [
        [
            'label' => 'https://artist.example/artworks/blue?private-query=1',
            'nb_hits' => 12,
        ],
    ];
    $payload[20] = [
        [
            'label' => 'Germany',
            'nb_visits' => 0,
        ],
    ];

    Cache::put('analytics:matomo:v5:site:7:30d:fresh', [
        'status' => 'available',
        'content' => [[
            'label' => '/legacy-cache',
            'nb_visits' => 0.0,
        ]],
    ], 600);

    Http::fake([
        'https://analytics.example.test/index.php' => Http::response($payload),
    ]);

    $result = app(MatomoReportingClient::class)->report('30d');

    expect($result['status'])->toBe('available')
        ->and($result['cache'])->toBe('live')
        ->and($result['content'][0]['label'])->toBe('/artworks/blue')
        ->and($result['content'][0]['nb_hits'])->toBe(12.0)
        ->and($result['content'][0]['nb_visits'])->toBeNull()
        ->and($result['content'][0]['nb_uniq_visitors'])->toBeNull()
        ->and($result['countries'][0]['nb_visits'])->toBe(0.0)
        ->and($result['countries'][0]['nb_uniq_visitors'])->toBeNull();

    Http::assertSentCount(1);
});
