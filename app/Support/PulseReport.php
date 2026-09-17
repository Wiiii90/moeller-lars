<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use JsonException;
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

final class PulseReport
{
    private const QUEUE_TYPES = ['queued', 'processing', 'processed', 'released', 'failed'];

    /**
     * Build a read-only snapshot from the same Pulse aggregates used by the dashboard cards.
     *
     * @return array<string, mixed>
     */
    public function build(int $hours = 24, int $limit = 50): array
    {
        $interval = CarbonInterval::hours($hours);

        return [
            'schema_version' => 1,
            'generated_at' => now()->utc()->toIso8601String(),
            'application_environment' => app()->environment(),
            'pulse_enabled' => (bool) config('pulse.enabled', false),
            'server_name' => (string) config('pulse.recorders.'.Servers::class.'.server_name', gethostname()),
            'window_hours' => $hours,
            'row_limit' => $limit,
            'retention' => [
                'storage' => (string) config('pulse.storage.trim.keep', '3 days'),
                'ingest' => (string) config('pulse.ingest.trim.keep', '3 days'),
            ],
            'recorders' => $this->recorderConfiguration(),
            'servers' => $this->servers(),
            'queues' => $this->queues($interval, $limit),
            'slow_requests' => $this->slowRequests($interval, $limit),
            'slow_queries' => $this->slowQueries($interval, $limit),
            'exceptions' => $this->exceptions($interval, $limit),
            'slow_jobs' => $this->slowJobs($interval, $limit),
            'slow_outgoing_requests' => $this->slowOutgoingRequests($interval, $limit),
        ];
    }

    /**
     * Render the snapshot as copy/paste-friendly Markdown without adding data that Pulse did not store.
     *
     * @param  array<string, mixed>  $report
     */
    public function toMarkdown(array $report): string
    {
        $lines = [
            '# Laravel Pulse report',
            '',
            '- generated_at: '.($report['generated_at'] ?? 'unknown'),
            '- application_environment: '.($report['application_environment'] ?? 'unknown'),
            '- pulse_enabled: '.(($report['pulse_enabled'] ?? false) ? 'true' : 'false'),
            '- server_name: '.($report['server_name'] ?? 'unknown'),
            '- window_hours: '.($report['window_hours'] ?? 'unknown'),
            '- storage_retention: '.data_get($report, 'retention.storage', 'unknown'),
            '- ingest_retention: '.data_get($report, 'retention.ingest', 'unknown'),
            '',
            '## Recorder configuration',
            '',
        ];

        foreach ((array) ($report['recorders'] ?? []) as $name => $config) {
            $enabled = (bool) data_get($config, 'enabled', false) ? 'enabled' : 'disabled';
            $details = [];

            if (data_get($config, 'threshold_ms') !== null) {
                $details[] = 'threshold_ms='.data_get($config, 'threshold_ms');
            }
            if (data_get($config, 'sample_rate') !== null) {
                $details[] = 'sample_rate='.data_get($config, 'sample_rate');
            }

            $suffix = $details === [] ? '' : ' ('.implode(', ', $details).')';
            $lines[] = "- {$name}: {$enabled}{$suffix}";
        }

        $lines[] = '';
        $lines[] = '## Servers';
        $lines[] = '';
        $this->appendServerMarkdown($lines, (array) ($report['servers'] ?? []));

        $lines[] = '';
        $lines[] = '## Queues';
        $lines[] = '';
        $this->appendQueueMarkdown($lines, (array) ($report['queues'] ?? []));

        $lines[] = '';
        $lines[] = '## Slow requests';
        $lines[] = '';
        $this->appendSlowRequestMarkdown($lines, (array) ($report['slow_requests'] ?? []));

        $lines[] = '';
        $lines[] = '## Slow queries';
        $lines[] = '';
        $this->appendSlowQueryMarkdown($lines, (array) ($report['slow_queries'] ?? []));

        $lines[] = '';
        $lines[] = '## Exceptions';
        $lines[] = '';
        $this->appendExceptionMarkdown($lines, (array) ($report['exceptions'] ?? []));

        $lines[] = '';
        $lines[] = '## Slow jobs';
        $lines[] = '';
        $this->appendSlowJobMarkdown($lines, (array) ($report['slow_jobs'] ?? []));

        $lines[] = '';
        $lines[] = '## Slow outgoing requests';
        $lines[] = '';
        $this->appendSlowOutgoingMarkdown($lines, (array) ($report['slow_outgoing_requests'] ?? []));

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<string, array<string, bool|float|int|null>>
     */
    private function recorderConfiguration(): array
    {
        return [
            'cache_interactions' => $this->recorderConfig(CacheInteractions::class),
            'exceptions' => $this->recorderConfig(Exceptions::class),
            'queues' => $this->recorderConfig(Queues::class),
            'slow_jobs' => $this->recorderConfig(SlowJobs::class, true),
            'slow_outgoing_requests' => $this->recorderConfig(SlowOutgoingRequests::class, true),
            'slow_queries' => $this->recorderConfig(SlowQueries::class, true),
            'slow_requests' => $this->recorderConfig(SlowRequests::class, true),
            'user_jobs' => $this->recorderConfig(UserJobs::class),
            'user_requests' => $this->recorderConfig(UserRequests::class),
        ];
    }

    /**
     * @return array<string, bool|float|int|null>
     */
    private function recorderConfig(string $class, bool $includeThreshold = false): array
    {
        $config = (array) config('pulse.recorders.'.$class, []);

        return [
            'enabled' => (bool) ($config['enabled'] ?? true),
            'sample_rate' => array_key_exists('sample_rate', $config) ? (float) $config['sample_rate'] : null,
            'threshold_ms' => $includeThreshold && isset($config['threshold']) && is_numeric($config['threshold'])
                ? (int) $config['threshold']
                : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function servers(): array
    {
        return Pulse::values('system')
            ->map(function ($system, $slug): ?array {
                $values = $this->decodeObject((string) data_get($system, 'value', ''));
                if ($values === null) {
                    return null;
                }

                $timestamp = (int) data_get($system, 'timestamp', 0);
                $storage = collect($values['storage'] ?? [])
                    ->filter(fn ($disk): bool => is_array($disk))
                    ->map(fn (array $disk): array => [
                        'directory' => (string) ($disk['directory'] ?? ''),
                        'used_mb' => (int) ($disk['used'] ?? 0),
                        'total_mb' => (int) ($disk['total'] ?? 0),
                    ])
                    ->values()
                    ->all();

                return [
                    'key' => (string) $slug,
                    'name' => (string) ($values['name'] ?? $slug),
                    'cpu_percent' => (int) ($values['cpu'] ?? 0),
                    'memory_used_mb' => (int) ($values['memory_used'] ?? 0),
                    'memory_total_mb' => (int) ($values['memory_total'] ?? 0),
                    'storage' => $storage,
                    'updated_at' => $timestamp > 0
                        ? CarbonImmutable::createFromTimestamp($timestamp, 'UTC')->toIso8601String()
                        : null,
                    'recently_reported' => $timestamp > 0 && $timestamp >= now()->subSeconds(30)->getTimestamp(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function queues(CarbonInterval $interval, int $limit): array
    {
        return Pulse::aggregateTypes(self::QUEUE_TYPES, 'count', $interval, null, 'desc', $limit)
            ->map(function ($row): array {
                $result = ['queue' => (string) data_get($row, 'key', '')];

                foreach (self::QUEUE_TYPES as $type) {
                    $result[$type] = (int) data_get($row, $type, 0);
                }

                return $result;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function slowRequests(CarbonInterval $interval, int $limit): array
    {
        return Pulse::aggregate('slow_request', ['max', 'count'], $interval, 'max', 'desc', $limit)
            ->map(function ($row): array {
                [$method, $uri, $action] = $this->decodeTuple((string) data_get($row, 'key', ''), 3);

                return [
                    'method' => $method,
                    'uri' => $uri,
                    'action' => $action,
                    'count' => (int) data_get($row, 'count', 0),
                    'slowest_ms' => (int) data_get($row, 'max', 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function slowQueries(CarbonInterval $interval, int $limit): array
    {
        return Pulse::aggregate('slow_query', ['max', 'count'], $interval, 'max', 'desc', $limit)
            ->map(function ($row): array {
                [$sql, $location] = $this->decodeTuple((string) data_get($row, 'key', ''), 2);

                return [
                    'sql' => $sql,
                    'location' => $location,
                    'count' => (int) data_get($row, 'count', 0),
                    'slowest_ms' => (int) data_get($row, 'max', 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function exceptions(CarbonInterval $interval, int $limit): array
    {
        return Pulse::aggregate('exception', ['max', 'count'], $interval, 'count', 'desc', $limit)
            ->map(function ($row): array {
                [$class, $location] = $this->decodeTuple((string) data_get($row, 'key', ''), 2);
                $timestamp = (int) data_get($row, 'max', 0);

                return [
                    'class' => $class,
                    'location' => $location,
                    'count' => (int) data_get($row, 'count', 0),
                    'latest' => $timestamp > 0
                        ? CarbonImmutable::createFromTimestamp($timestamp, 'UTC')->toIso8601String()
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function slowJobs(CarbonInterval $interval, int $limit): array
    {
        return Pulse::aggregate('slow_job', ['max', 'count'], $interval, 'max', 'desc', $limit)
            ->map(fn ($row): array => [
                'job' => (string) data_get($row, 'key', ''),
                'count' => (int) data_get($row, 'count', 0),
                'slowest_ms' => (int) data_get($row, 'max', 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function slowOutgoingRequests(CarbonInterval $interval, int $limit): array
    {
        return Pulse::aggregate('slow_outgoing_request', ['max', 'count'], $interval, 'max', 'desc', $limit)
            ->map(function ($row): array {
                [$method, $uri] = $this->decodeTuple((string) data_get($row, 'key', ''), 2);

                return [
                    'method' => $method,
                    'uri' => $uri,
                    'count' => (int) data_get($row, 'count', 0),
                    'slowest_ms' => (int) data_get($row, 'max', 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string|null>
     */
    private function decodeTuple(string $value, int $size): array
    {
        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return array_fill(0, $size, null);
        }

        if (! is_array($decoded)) {
            return array_fill(0, $size, null);
        }

        $tuple = array_map(
            static fn ($item): ?string => is_scalar($item) || $item === null ? ($item === null ? null : (string) $item) : null,
            array_values($decoded),
        );

        return array_pad(array_slice($tuple, 0, $size), $size, null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeObject(string $value): ?array
    {
        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  list<string>  $lines
     * @param  array<int, mixed>  $servers
     */
    private function appendServerMarkdown(array &$lines, array $servers): void
    {
        if ($servers === []) {
            $lines[] = 'No results.';

            return;
        }

        foreach ($servers as $server) {
            $lines[] = '- '.data_get($server, 'name', 'unknown')
                .' | cpu='.data_get($server, 'cpu_percent', 0).'%'
                .' | memory='.data_get($server, 'memory_used_mb', 0).'/'.data_get($server, 'memory_total_mb', 0).' MB'
                .' | updated_at='.data_get($server, 'updated_at', 'unknown')
                .' | recently_reported='.(data_get($server, 'recently_reported', false) ? 'true' : 'false');

            foreach ((array) data_get($server, 'storage', []) as $storage) {
                $lines[] = '  - storage '.data_get($storage, 'directory', '')
                    .': '.data_get($storage, 'used_mb', 0).'/'.data_get($storage, 'total_mb', 0).' MB';
            }
        }
    }

    /**
     * @param  list<string>  $lines
     * @param  array<int, mixed>  $queues
     */
    private function appendQueueMarkdown(array &$lines, array $queues): void
    {
        if ($queues === []) {
            $lines[] = 'No results.';

            return;
        }

        foreach ($queues as $queue) {
            $lines[] = '- '.data_get($queue, 'queue', 'unknown')
                .' | queued='.data_get($queue, 'queued', 0)
                .' | processing='.data_get($queue, 'processing', 0)
                .' | processed='.data_get($queue, 'processed', 0)
                .' | released='.data_get($queue, 'released', 0)
                .' | failed='.data_get($queue, 'failed', 0);
        }
    }

    /**
     * @param  list<string>  $lines
     * @param  array<int, mixed>  $rows
     */
    private function appendSlowRequestMarkdown(array &$lines, array $rows): void
    {
        if ($rows === []) {
            $lines[] = 'No results.';

            return;
        }

        foreach ($rows as $row) {
            $lines[] = '- '.data_get($row, 'method', '?').' '.data_get($row, 'uri', '?')
                .' | count='.data_get($row, 'count', 0)
                .' | slowest_ms='.data_get($row, 'slowest_ms', 0)
                .' | action='.data_get($row, 'action', 'unknown');
        }
    }

    /**
     * @param  list<string>  $lines
     * @param  array<int, mixed>  $rows
     */
    private function appendSlowQueryMarkdown(array &$lines, array $rows): void
    {
        if ($rows === []) {
            $lines[] = 'No results.';

            return;
        }

        foreach ($rows as $row) {
            $lines[] = '- count='.data_get($row, 'count', 0)
                .' | slowest_ms='.data_get($row, 'slowest_ms', 0)
                .' | location='.data_get($row, 'location', 'unknown');
            $lines[] = '  - sql: '.str_replace("\n", ' ', (string) data_get($row, 'sql', 'unknown'));
        }
    }

    /**
     * @param  list<string>  $lines
     * @param  array<int, mixed>  $rows
     */
    private function appendExceptionMarkdown(array &$lines, array $rows): void
    {
        if ($rows === []) {
            $lines[] = 'No results.';

            return;
        }

        foreach ($rows as $row) {
            $lines[] = '- '.data_get($row, 'class', 'unknown')
                .' | count='.data_get($row, 'count', 0)
                .' | latest='.data_get($row, 'latest', 'unknown')
                .' | location='.data_get($row, 'location', 'unknown');
        }
    }

    /**
     * @param  list<string>  $lines
     * @param  array<int, mixed>  $rows
     */
    private function appendSlowJobMarkdown(array &$lines, array $rows): void
    {
        if ($rows === []) {
            $lines[] = 'No results.';

            return;
        }

        foreach ($rows as $row) {
            $lines[] = '- '.data_get($row, 'job', 'unknown')
                .' | count='.data_get($row, 'count', 0)
                .' | slowest_ms='.data_get($row, 'slowest_ms', 0);
        }
    }

    /**
     * @param  list<string>  $lines
     * @param  array<int, mixed>  $rows
     */
    private function appendSlowOutgoingMarkdown(array &$lines, array $rows): void
    {
        if ($rows === []) {
            $lines[] = 'No results.';

            return;
        }

        foreach ($rows as $row) {
            $lines[] = '- '.data_get($row, 'method', '?').' '.data_get($row, 'uri', '?')
                .' | count='.data_get($row, 'count', 0)
                .' | slowest_ms='.data_get($row, 'slowest_ms', 0);
        }
    }
}
