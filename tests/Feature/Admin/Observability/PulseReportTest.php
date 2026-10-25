<?php

use App\Support\PulseReport;
use Illuminate\Support\Facades\Artisan;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\CacheInteractions;
use Laravel\Pulse\Recorders\Exceptions;
use Laravel\Pulse\Recorders\Queues;
use Laravel\Pulse\Recorders\Servers;
use Laravel\Pulse\Recorders\SlowJobs;
use Laravel\Pulse\Recorders\SlowOutgoingRequests;
use Laravel\Pulse\Recorders\SlowQueries;
use Laravel\Pulse\Recorders\SlowRequests;
use Laravel\Pulse\Recorders\UserJobs;
use Laravel\Pulse\Recorders\UserRequests;

function mockPulseReportData(): void
{
    $timestamp = now()->getTimestamp();

    Pulse::shouldReceive('values')
        ->with('system')
        ->andReturn(collect([
            'moeller-lars-validation' => (object) [
                'timestamp' => $timestamp,
                'key' => 'moeller-lars-validation',
                'value' => json_encode([
                    'name' => 'moeller-lars-validation',
                    'cpu' => 3,
                    'memory_used' => 1126,
                    'memory_total' => 1946,
                    'storage' => [[
                        'directory' => '/var/www/html/storage/app/private',
                        'used' => 14336,
                        'total' => 46080,
                    ]],
                ], JSON_THROW_ON_ERROR),
            ],
        ]));

    Pulse::shouldReceive('aggregateTypes')
        ->andReturn(collect([
            (object) [
                'key' => 'database:default',
                'queued' => 3,
                'processing' => 3,
                'processed' => 3,
                'released' => 0,
                'failed' => 0,
            ],
        ]));

    Pulse::shouldReceive('aggregate')
        ->andReturnUsing(function (string $type) use ($timestamp) {
            return match ($type) {
                'slow_request' => collect([
                    (object) [
                        'key' => json_encode(['GET', '/admin/dashboard', 'App\\Filament\\Pages\\Dashboard'], JSON_THROW_ON_ERROR),
                        'count' => 4,
                        'max' => 2107,
                    ],
                ]),
                'slow_query' => collect([
                    (object) [
                        'key' => json_encode(['select * from "sessions" where "id" = ? limit 1', 'vendor/livewire/livewire/src/Middleware.php:19'], JSON_THROW_ON_ERROR),
                        'count' => 8,
                        'max' => 777,
                    ],
                ]),
                'exception' => collect([
                    (object) [
                        'key' => json_encode(['Illuminate\\Database\\QueryException', 'app/Http/Middleware/RecordOperationalMetrics.php:20'], JSON_THROW_ON_ERROR),
                        'count' => 87,
                        'max' => $timestamp - 60,
                    ],
                ]),
                'slow_job', 'slow_outgoing_request' => collect(),
                default => throw new RuntimeException("Unexpected Pulse aggregate type: {$type}"),
            };
        });
}

function configurePulseReportRecorders(): void
{
    config()->set('pulse.enabled', true);
    config()->set('pulse.storage.trim.keep', '3 days');
    config()->set('pulse.ingest.trim.keep', '3 days');
    config()->set('pulse.recorders.'.Servers::class.'.server_name', 'moeller-lars-validation');

    foreach ([
        CacheInteractions::class => false,
        Exceptions::class => true,
        Queues::class => true,
        SlowJobs::class => true,
        SlowOutgoingRequests::class => true,
        SlowQueries::class => true,
        SlowRequests::class => true,
        UserJobs::class => false,
        UserRequests::class => false,
    ] as $recorder => $enabled) {
        config()->set('pulse.recorders.'.$recorder.'.enabled', $enabled);
        config()->set('pulse.recorders.'.$recorder.'.sample_rate', 1);
    }

    config()->set('pulse.recorders.'.SlowJobs::class.'.threshold', 1000);
    config()->set('pulse.recorders.'.SlowOutgoingRequests::class.'.threshold', 750);
    config()->set('pulse.recorders.'.SlowQueries::class.'.threshold', 250);
    config()->set('pulse.recorders.'.SlowRequests::class.'.threshold', 1000);
}

it('builds a stable read-only Pulse snapshot and copyable Markdown', function (): void {
    mockPulseReportData();
    configurePulseReportRecorders();

    $reporter = app(PulseReport::class);
    $report = $reporter->build(24, 50);

    expect($report['schema_version'])->toBe(1)
        ->and($report['pulse_enabled'])->toBeTrue()
        ->and($report['window_hours'])->toBe(24)
        ->and($report['server_name'])->toBe('moeller-lars-validation')
        ->and(data_get($report, 'recorders.cache_interactions.enabled'))->toBeFalse()
        ->and(data_get($report, 'recorders.user_requests.enabled'))->toBeFalse()
        ->and(data_get($report, 'recorders.slow_queries.threshold_ms'))->toBe(250)
        ->and(data_get($report, 'servers.0.memory_used_mb'))->toBe(1126)
        ->and(data_get($report, 'servers.0.storage.0.used_mb'))->toBe(14336)
        ->and(data_get($report, 'queues.0.processed'))->toBe(3)
        ->and(data_get($report, 'slow_requests.0.uri'))->toBe('/admin/dashboard')
        ->and(data_get($report, 'slow_requests.0.slowest_ms'))->toBe(2107)
        ->and(data_get($report, 'slow_queries.0.sql'))->toBe('select * from "sessions" where "id" = ? limit 1')
        ->and(data_get($report, 'slow_queries.0.count'))->toBe(8)
        ->and(data_get($report, 'exceptions.0.class'))->toBe('Illuminate\\Database\\QueryException')
        ->and(data_get($report, 'exceptions.0.count'))->toBe(87)
        ->and($report['slow_jobs'])->toBe([])
        ->and($report['slow_outgoing_requests'])->toBe([]);

    $markdown = $reporter->toMarkdown($report);

    expect($markdown)
        ->toContain('# Laravel Pulse report')
        ->toContain('## Slow requests')
        ->toContain('GET /admin/dashboard | count=4 | slowest_ms=2107')
        ->toContain('## Slow queries')
        ->toContain('select * from "sessions" where "id" = ? limit 1')
        ->toContain('Illuminate\\Database\\QueryException | count=87')
        ->toContain('## Slow outgoing requests')
        ->toContain('No results.');
});

it('exports JSON from the console and rejects unsafe report bounds', function (): void {
    mockPulseReportData();
    configurePulseReportRecorders();

    expect(Artisan::call('pulse:report', [
        '--hours' => 24,
        '--format' => 'json',
        '--limit' => 50,
    ]))->toBe(0);

    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['schema_version'])->toBe(1)
        ->and($payload['window_hours'])->toBe(24)
        ->and(data_get($payload, 'slow_requests.0.uri'))->toBe('/admin/dashboard');

    expect(Artisan::call('pulse:report', ['--hours' => 0]))->toBe(64)
        ->and(Artisan::call('pulse:report', ['--format' => 'xml']))->toBe(64)
        ->and(Artisan::call('pulse:report', ['--limit' => 101]))->toBe(64);
});
