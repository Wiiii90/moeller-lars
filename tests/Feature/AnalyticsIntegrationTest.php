<?php

use App\Domain\Analytics\MatomoReportingClient;
use App\Domain\Analytics\OperationalMetricRecorder;
use App\Models\DailyMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

function configureAnalyticsReporting(): void
{
    Config::set('analytics.matomo.reporting_enabled', true);
    Config::set('analytics.matomo.tracking_enabled', false);
    Config::set('analytics.matomo.base_url', 'https://analytics.example.test');
    Config::set('analytics.matomo.site_id', 7);
    Config::set('analytics.matomo.api_token', 'secret-reporting-token');
    Config::set('analytics.matomo.report_timeout_seconds', 5);
    Config::set('analytics.matomo.report_cache_seconds', 1);
    Config::set('analytics.matomo.report_stale_seconds', 3600);
}

function bulkAnalyticsPayload(): array
{
    return [
        [
            'nb_visits' => 10,
            'nb_uniq_visitors' => 8,
            'nb_actions' => 24,
            'nb_actions_per_visit' => 2.4,
            'avg_time_on_site' => 91,
            'bounce_rate' => '35%',
            'ignored_raw_field' => 'not exposed',
        ],
        [
            'nb_visits' => 8,
            'nb_uniq_visitors' => 7,
            'nb_actions' => 18,
            'nb_actions_per_visit' => 2.25,
            'avg_time_on_site' => 80,
            'bounce_rate' => '40%',
        ],
        [
            '2026-08-17' => ['nb_visits' => 4, 'nb_actions' => 9],
            '2026-08-18' => ['nb_visits' => 6, 'nb_actions' => 15],
        ],
        [
            ['label' => 'https://artist.example/artworks/blue?private-query=1', 'nb_visits' => 5, 'nb_hits' => 12],
        ],
        [
            ['label' => 'https://artist.example/', 'nb_visits' => 4, 'nb_entrances' => 4, 'bounce_rate' => '25%'],
        ],
        [
            ['label' => 'https://artist.example/cv', 'nb_visits' => 3, 'nb_exits' => 3, 'exit_rate' => '40%'],
        ],
        [
            ['label' => 'https://artist.example/files/catalogue.pdf?download=1', 'nb_visits' => 2, 'nb_hits' => 3],
        ],
        [
            ['label' => 'https://instagram.com/larsmoeller_art?source=site', 'nb_visits' => 3, 'nb_hits' => 4],
        ],
        [
            ['label' => 'reflexion', 'nb_visits' => 2],
        ],
        [],
        [
            ['label' => 'artwork_open', 'nb_events' => 7, 'nb_visits' => 4, 'nb_uniq_visitors' => 4],
            ['label' => 'contact_submit_success', 'nb_events' => 2, 'nb_visits' => 2, 'nb_uniq_visitors' => 2],
        ],
        [
            ['label' => 'Artwork', 'nb_events' => 9, 'nb_visits' => 5, 'nb_uniq_visitors' => 4],
        ],
        [
            ['label' => 'Reflexion (2)', 'nb_events' => 5, 'nb_visits' => 4, 'nb_uniq_visitors' => 4],
        ],
        [
            ['label' => 'Direct Entry', 'nb_visits' => 6, 'nb_uniq_visitors' => 5],
            ['label' => 'Websites', 'nb_visits' => 4, 'nb_uniq_visitors' => 3],
        ],
        [
            ['label' => 'example.org', 'nb_visits' => 3, 'nb_uniq_visitors' => 3],
        ],
        [
            ['label' => 'Instagram', 'nb_visits' => 2, 'nb_uniq_visitors' => 2],
        ],
        [
            ['label' => 'Google', 'nb_visits' => 2, 'nb_uniq_visitors' => 2],
        ],
        [
            ['label' => 'summer-show', 'nb_visits' => 1, 'nb_uniq_visitors' => 1],
        ],
        [
            ['label' => 'ChatGPT', 'nb_visits' => 1, 'nb_uniq_visitors' => 1],
        ],
        [
            ['label' => 'Europe', 'nb_visits' => 9, 'nb_uniq_visitors' => 7],
        ],
        [
            ['label' => 'Germany', 'nb_visits' => 7, 'nb_uniq_visitors' => 6],
        ],
        [
            ['label' => 'Desktop', 'nb_visits' => 8, 'nb_uniq_visitors' => 7],
        ],
        [
            ['label' => 'Firefox', 'nb_visits' => 5, 'nb_uniq_visitors' => 4],
        ],
        [
            ['label' => 'Windows', 'nb_visits' => 6, 'nb_uniq_visitors' => 5],
        ],
        [
            ['label' => '1-3 min', 'nb_visits' => 4],
        ],
        [
            ['label' => '3-5 pages', 'nb_visits' => 5],
        ],
        [
            ['label' => '20', 'nb_visits' => 3],
        ],
        [
            ['label' => 'Tuesday', 'nb_visits' => 4],
        ],
        [
            'nb_visits_returning' => 4,
            'nb_actions_returning' => 11,
            'avg_time_on_site_returning' => 120,
        ],
    ];
}

it('keeps Matomo browser tracking absent when disabled', function () {
    Config::set('analytics.matomo.tracking_enabled', false);

    $this->get('/')->assertSuccessful()->assertDontSee('data-matomo-tracking', false);
});

it('renders the artist analytics workspace without requiring live Matomo', function () {
    Config::set('analytics.matomo.reporting_enabled', false);
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $this->get('/admin/analytics')
        ->assertSuccessful()
        ->assertSee('Human analytics')
        ->assertSee('Operational health')
        ->assertSee('Matomo reporting is disabled.');
});

it('builds the aggregate dashboard from one POST-authenticated Matomo bulk request', function () {
    configureAnalyticsReporting();

    Http::fake([
        'https://analytics.example.test/index.php' => Http::response(bulkAnalyticsPayload()),
    ]);

    $result = app(MatomoReportingClient::class)->report('7d');

    expect($result['status'])->toBe('available')
        ->and(array_keys($result['metrics']))->toBe([
            'nb_visits', 'nb_uniq_visitors', 'nb_actions', 'nb_actions_per_visit', 'avg_time_on_site', 'bounce_rate',
        ])
        ->and($result['metrics']['bounce_rate'])->toBe(35.0)
        ->and($result['comparison']['nb_visits'])->toBe(25.0)
        ->and($result['content'][0]['label'])->toBe('/artworks/blue')
        ->and($result['entry_pages'][0]['label'])->toBe('/')
        ->and($result['downloads'][0]['label'])->toBe('artist.example/files/catalogue.pdf')
        ->and($result['outlinks'][0]['label'])->toBe('instagram.com/larsmoeller_art')
        ->and($result['events'][0]['label'])->toBe('artwork_open')
        ->and($result['event_names'][0]['label'])->toBe('Reflexion (2)')
        ->and($result['ai_assistants'][0]['label'])->toBe('ChatGPT')
        ->and($result['countries'][0]['label'])->toBe('Germany')
        ->and($result['returning']['nb_visits_returning'])->toBe(4.0)
        ->and($result['series'])->toHaveCount(2);

    Http::assertSent(function (Request $request): bool {
        $urls = $request['urls'];

        return $request->method() === 'POST'
            && $request->url() === 'https://analytics.example.test/index.php'
            && $request['method'] === 'API.getBulkRequest'
            && $request['token_auth'] === 'secret-reporting-token'
            && is_array($urls)
            && count($urls) === 29
            && str_contains($urls[0], 'method=VisitsSummary.get')
            && str_contains($urls[3], 'method=Actions.getPageUrls')
            && str_contains($urls[10], 'method=Events.getAction')
            && str_contains($urls[14], 'method=Referrers.getWebsites')
            && str_contains($urls[18], 'method=Referrers.getAIAssistants')
            && collect($urls)->every(fn (string $url): bool => ! str_contains($url, 'token_auth'));
    });
});

it('keeps range analytics available when Matomo omits range unique visitors', function () {
    configureAnalyticsReporting();

    $payload = bulkAnalyticsPayload();
    unset($payload[0]['nb_uniq_visitors'], $payload[1]['nb_uniq_visitors']);

    Http::fake([
        'https://analytics.example.test/index.php' => Http::response($payload),
    ]);

    $result = app(MatomoReportingClient::class)->report('30d');

    expect($result['status'])->toBe('available')
        ->and($result['metrics']['nb_uniq_visitors'])->toBeNull()
        ->and($result['warnings'])->toContain('Range-level unique visitors are not enabled in Matomo; the remaining aggregate reports are still available.')
        ->and($result['countries'][0]['label'])->toBe('Germany')
        ->and($result['series'])->toHaveCount(2);
});

it('falls back to stale aggregate reporting when Matomo becomes unavailable', function () {
    configureAnalyticsReporting();

    Http::fake([
        'https://analytics.example.test/index.php' => Http::sequence()
            ->push(bulkAnalyticsPayload(), 200)
            ->push([], 503),
    ]);

    $live = app(MatomoReportingClient::class)->report('today');
    expect($live['status'])->toBe('available');

    Cache::forget('analytics:matomo:v4:site:7:today:fresh');
    $stale = app(MatomoReportingClient::class)->report('today');

    expect($stale['status'])->toBe('stale')
        ->and($stale['metrics']['nb_visits'])->toBe(10.0)
        ->and($stale['message'])->toContain('cached aggregate data');
});

it('stores only cumulative operational aggregates locally', function () {
    $recorder = app(OperationalMetricRecorder::class);
    $recorder->add('performance:request_duration_ms', 10.5, 'ms');
    $recorder->add('performance:request_duration_ms', 4.5, 'ms');
    $recorder->add('bot:request', 1, 'count');

    $performance = DailyMetric::query()->where('metric_name', 'performance:request_duration_ms')->sole();
    expect((float) $performance->value)->toBe(15.0)
        ->and($performance->sample_count)->toBe(2)
        ->and(DailyMetric::query()->where('metric_name', 'bot:request')->count())->toBe(1)
        ->and(DailyMetric::query()->where('source', 'application')->whereNotIn('metric_name', ['performance:request_duration_ms', 'bot:request'])->count())->toBe(0);
});

it('aggregates 404 and admin request health without storing visitor identifiers', function () {
    $this->get('/definitely-missing')->assertNotFound();
    $this->get('/admin/login')->assertSuccessful();

    expect((float) DailyMetric::query()->where('metric_name', 'error:http_404')->value('value'))->toBeGreaterThanOrEqual(1.0)
        ->and((float) DailyMetric::query()->where('operation:admin_request')->value('value'))->toBeGreaterThanOrEqual(1.0)
        ->and(DailyMetric::query()->whereNotNull('dimension_key')->count())->toBe(0);
});
