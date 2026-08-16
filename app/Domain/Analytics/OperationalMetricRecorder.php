<?php

namespace App\Domain\Analytics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OperationalMetricRecorder
{
    public function add(string $metricName, float $value, string $unit, int $sampleCount = 1): void
    {
        try {
            DB::statement(
                <<<'SQL'
                INSERT INTO daily_metrics
                    (metric_date, metric_name, source, value, unit, calculated_at, dimension_key, sample_count)
                VALUES
                    (?, ?, 'application', ?, ?, ?, NULL, ?)
                ON CONFLICT (metric_date, metric_name, source, dimension_key)
                DO UPDATE SET
                    value = daily_metrics.value + EXCLUDED.value,
                    calculated_at = EXCLUDED.calculated_at,
                    sample_count = COALESCE(daily_metrics.sample_count, 0) + EXCLUDED.sample_count
                SQL,
                [now()->toDateString(), $metricName, $value, $unit, now(), $sampleCount],
            );
        } catch (Throwable $exception) {
            Log::warning('Operational metric aggregation failed.', [
                'metric' => $metricName,
                'exception' => $exception::class,
            ]);
        }
    }
}
