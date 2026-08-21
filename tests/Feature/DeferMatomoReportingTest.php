<?php

use App\Domain\Analytics\MatomoReportingClient;
use App\Http\Middleware\DeferMatomoReporting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

use function Illuminate\Support\defer;

beforeEach(function (): void {
    Cache::flush();
    Config::set('analytics.matomo.reporting_enabled', true);
    Config::set('analytics.matomo.base_url', 'https://analytics.example.test');
    Config::set('analytics.matomo.site_id', 7);
    Config::set('analytics.matomo.api_token', 'secret-reporting-token');
    Config::set('analytics.matomo.report_timeout_seconds', 5);
    Config::set('analytics.matomo.report_cache_seconds', 600);
    Config::set('analytics.matomo.report_stale_seconds', 3600);

    foreach (['today', '7d', '30d', '12m'] as $preset) {
        defer()->forget("matomo-report-refresh:7:{$preset}");
    }
});

function cachedMatomoReport(float $visits = 9): array
{
    return [
        'schema' => 6,
        'status' => 'available',
        'metrics' => ['nb_visits' => $visits],
        'comparison' => [],
        'series' => [],
        'warnings' => [],
    ];
}

it('serves an empty-cache loading state before any live Matomo request', function (): void {
    Http::fake([
        'https://analytics.example.test/index.php' => Http::response([], 503),
    ]);

    $duringRequest = null;
    $response = app(DeferMatomoReporting::class)->handle(
        Request::create('/admin/analytics', 'GET'),
        function () use (&$duringRequest) {
            $duringRequest = app(MatomoReportingClient::class)->report('30d');
            Http::assertNothingSent();

            return response('analytics shell');
        },
    );

    expect($response->getContent())->toBe('analytics shell')
        ->and($duringRequest['status'])->toBe('loading')
        ->and($duringRequest['message'])->toContain('loading in the background')
        ->and(defer()->count())->toBe(1);
    Http::assertNothingSent();

    defer()->invoke();
    Http::assertSentCount(1);
    defer()->forget('matomo-report-refresh:7:30d');
});

it('serves stale aggregate data immediately and refreshes only after the response', function (): void {
    Cache::put('analytics:matomo:v5:site:7:30d:stale', cachedMatomoReport(14), 3600);
    Http::fake([
        'https://analytics.example.test/index.php' => Http::response([], 503),
    ]);

    $duringRequest = null;
    app(DeferMatomoReporting::class)->handle(
        Request::create('/admin/analytics', 'GET'),
        function () use (&$duringRequest) {
            $duringRequest = app(MatomoReportingClient::class)->report('30d');
            Http::assertNothingSent();

            return response('ok');
        },
    );

    expect($duringRequest['status'])->toBe('stale')
        ->and($duringRequest['metrics']['nb_visits'])->toBe(14.0)
        ->and($duringRequest['message'])->toContain('cached aggregate data')
        ->and(defer()->count())->toBe(1);
    Http::assertNothingSent();

    defer()->invoke();
    Http::assertSentCount(1);
    defer()->forget('matomo-report-refresh:7:30d');
});

it('leaves a valid fresh report untouched and schedules no refresh', function (): void {
    Cache::put('analytics:matomo:v5:site:7:30d:fresh', cachedMatomoReport(21), 600);
    Http::fake();

    $duringRequest = null;
    app(DeferMatomoReporting::class)->handle(
        Request::create('/admin', 'GET'),
        function () use (&$duringRequest) {
            $duringRequest = app(MatomoReportingClient::class)->report('30d');

            return response('ok');
        },
    );

    expect($duringRequest['status'])->toBe('available')
        ->and($duringRequest['metrics']['nb_visits'])->toBe(21.0)
        ->and(defer()->count())->toBe(0);
    Http::assertNothingSent();
});

it('protects the range selected by a Livewire setRange request', function (): void {
    Http::fake([
        'https://analytics.example.test/index.php' => Http::response([], 503),
    ]);

    $request = Request::create('/livewire/update', 'POST', [
        'components' => [[
            'calls' => [[
                'method' => 'setRange',
                'params' => ['7d'],
            ]],
        ]],
    ]);

    $duringRequest = null;
    app(DeferMatomoReporting::class)->handle(
        $request,
        function () use (&$duringRequest) {
            $duringRequest = app(MatomoReportingClient::class)->report('7d');
            Http::assertNothingSent();

            return response('ok');
        },
    );

    expect($duringRequest['status'])->toBe('loading')
        ->and(Cache::has('analytics:matomo:v5:site:7:7d:fresh'))->toBeTrue()
        ->and(defer()->count())->toBe(1);
    Http::assertNothingSent();

    defer()->invoke();
    Http::assertSentCount(1);
    defer()->forget('matomo-report-refresh:7:7d');
});
