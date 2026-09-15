<?php

namespace App\Http\Middleware;

use App\Domain\Analytics\MatomoConfiguration;
use App\Jobs\RefreshMatomoReporting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class DeferMatomoReporting
{
    /**
     * These values intentionally mirror MatomoReportingClient's cache contract.
     * DeferMatomoReporting only stages an existing stale report or a short-lived
     * loading sentinel into the canonical fresh slot; the reporting client
     * remains the sole owner of live fetching and durable cache writes.
     */
    private const CACHE_NAMESPACE = 'analytics:matomo:v5';

    private const CACHE_SCHEMA = 6;

    private const PRESETS = ['today', '7d', '30d', '12m'];

    private const DEFAULT_PRESET = '30d';

    private const REFRESH_LOCK_SECONDS = 30;

    public function __construct(private readonly MatomoConfiguration $configuration) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('analytics.matomo.reporting_enabled')) {
            return $next($request);
        }

        $preset = $this->requestedPreset($request);

        try {
            $siteId = $this->configuration->siteId();
            $freshKey = $this->cacheKey($siteId, $preset, 'fresh');
            $staleKey = $this->cacheKey($siteId, $preset, 'stale');
            $fresh = Cache::get($freshKey);

            if ($this->isCurrentReport($fresh)) {
                return $next($request);
            }

            $stale = Cache::get($staleKey);
            Cache::put(
                $freshKey,
                is_array($stale) && $this->isCurrentReport($stale)
                    ? $this->staleForImmediateDisplay($stale)
                    : $this->loadingReport(),
                60,
            );
        } catch (Throwable) {
            // Reporting/cache configuration must never make the artist admin fatal.
            return $next($request);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            Cache::forget($freshKey);

            throw $exception;
        }

        $refreshKey = $this->cacheKey($siteId, $preset, 'refreshing');
        if (Cache::add($refreshKey, true, self::REFRESH_LOCK_SECONDS)) {
            try {
                RefreshMatomoReporting::dispatch($siteId, $preset)
                    ->onConnection('background');
            } catch (Throwable) {
                Cache::forget($refreshKey);
            }
        }

        return $response;
    }

    private function requestedPreset(Request $request): string
    {
        $components = $request->input('components');
        if (! is_array($components)) {
            return self::DEFAULT_PRESET;
        }

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $calls = $component['calls'] ?? null;
            if (! is_array($calls)) {
                continue;
            }

            foreach ($calls as $call) {
                if (! is_array($call) || ($call['method'] ?? null) !== 'setRange') {
                    continue;
                }

                $params = $call['params'] ?? null;
                $preset = is_array($params) ? ($params[0] ?? null) : null;
                if (is_string($preset) && in_array($preset, self::PRESETS, true)) {
                    return $preset;
                }
            }
        }

        return self::DEFAULT_PRESET;
    }

    private function cacheKey(int $siteId, string $preset, string $freshness): string
    {
        return self::CACHE_NAMESPACE.":site:{$siteId}:{$preset}:{$freshness}";
    }

    private function isCurrentReport(mixed $report): bool
    {
        return is_array($report) && ($report['schema'] ?? null) === self::CACHE_SCHEMA;
    }

    /** @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function staleForImmediateDisplay(array $report): array
    {
        $report['status'] = 'stale';
        $report['cache'] = 'stale';
        $report['message'] = 'Refreshing Matomo reporting in the background. Showing cached aggregate data.';

        return $report;
    }

    /** @return array<string, mixed> */
    private function loadingReport(): array
    {
        return [
            'schema' => self::CACHE_SCHEMA,
            'status' => 'loading',
            'cache' => 'deferred',
            'message' => 'Analytics data is loading in the background.',
        ];
    }
}
