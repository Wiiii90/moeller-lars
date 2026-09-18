<?php

namespace App\Jobs;

use App\Domain\Analytics\MatomoReportingClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class RefreshMatomoReporting implements ShouldQueue
{
    use Queueable;

    private const CACHE_NAMESPACE = 'analytics:matomo:v5';

    private const CACHE_SCHEMA = 6;

    private const PRESETS = ['today', '7d', '30d', '12m'];

    public int $tries = 1;

    public function __construct(
        public readonly int $siteId,
        public readonly string $preset,
    ) {}

    public function handle(MatomoReportingClient $client): void
    {
        $refreshKey = $this->cacheKey('refreshing');

        try {
            if ($this->siteId <= 0 || ! in_array($this->preset, self::PRESETS, true)) {
                return;
            }

            $freshKey = $this->cacheKey('fresh');
            $stagedReport = Cache::get($freshKey);

            Cache::forget($freshKey);
            $client->report($this->preset);

            if (! $this->isCurrentReport(Cache::get($freshKey)) && $this->isCurrentReport($stagedReport)) {
                Cache::put($freshKey, $stagedReport, 60);
            }
        } finally {
            Cache::forget($refreshKey);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::forget($this->cacheKey('refreshing'));
    }

    private function cacheKey(string $freshness): string
    {
        return self::CACHE_NAMESPACE.":site:{$this->siteId}:{$this->preset}:{$freshness}";
    }

    private function isCurrentReport(mixed $report): bool
    {
        return is_array($report) && ($report['schema'] ?? null) === self::CACHE_SCHEMA;
    }
}
