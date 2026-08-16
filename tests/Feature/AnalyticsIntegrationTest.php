<?php

use App\Domain\Analytics\MatomoReportingClient;
use App\Domain\Analytics\OperationalMetricRecorder;
use App\Models\DailyMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('keeps Matomo browser tracking absent when disabled', function () {
    Config::set('analytics.matomo.enabled', false);

    $this->get('/')->assertSuccessful()->assertDontSee('data-matomo-tracking', false);
});

it('reads only allowlisted aggregate Matomo summary metrics using POST authentication', function () {
    Config::set('analytics.matomo.enabled', true);
    Config::set('analytics.matomo.base_url', 'https://analytics.example.test');
    Config::set('analytics.matomo.site_id', 7);
    Config::set('analytics.matomo.api_token', 'secret-reporting-token');
    Config::set('analytics.matomo.report_timeout_seconds', 5);

    Http::fake([
        'https://analytics.example.test/index.php' => Http::response([
            'nb_visits' => 10,
            'nb_uniq_visitors' => 8,
            'nb_actions' => 24,
            'nb_actions_per_visit' => 2.4,
            'avg_time_on_site' => 91,
            'bounce_rate' => '35%',
            'ignored_raw_field' => 'not exposed',
        ]),
    ]);

    $result = app(MatomoReportingClient::class)->summary();

    expect($result['status'])->toBe('available')
        ->and(array_keys($result['metrics']))->toBe([
            'nb_visits', 'nb_uniq_visitors', 'nb_actions', 'nb_actions_per_visit', 'avg_time_on_site', 'bounce_rate',
        ]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://analytics.example.test/index.php'
        && $request['method'] === 'VisitsSummary.get'
        && $request['token_auth'] === 'secret-reporting-token');
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
