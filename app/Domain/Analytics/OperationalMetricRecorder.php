<?php

namespace App\Domain\Analytics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OperationalMetricRecorder
{
    public function add(string $metricName, float $value, string $unit, int $sampleCount = 1): void
    {
        $this->addMany([[
            'name' => $metricName,
            'value' => $value,
            'unit' => $unit,
            'sample_count' => $sampleCount,
        ]]);
    }

    /**
     * @param  list<array{name:string,value:float,unit:string,sample_count?:int}>  $metrics
     */
    public function addMany(array $metrics): void
    {
        if ($metrics === []) {
            return;
        }

        $collapsed = [];
        foreach ($metrics as $metric) {
            $name = $metric['name'];
            $sampleCount = $metric['sample_count'] ?? 1;

            if (isset($collapsed[$name])) {
                if ($collapsed[$name]['unit'] !== $metric['unit']) {
                    Log::warning('Operational metric batch contains conflicting units.', [
                        'metric' => $name,
                    ]);

                    return;
                }

                $collapsed[$name]['value'] += $metric['value'];
                $collapsed[$name]['sample_count'] += $sampleCount;

                continue;
            }

            $collapsed[$name] = [
                'value' => $metric['value'],
                'unit' => $metric['unit'],
                'sample_count' => $sampleCount,
            ];
        }

        $metricDate = now()->toDateString();
        $calculatedAt = now();
        $rows = [];
        $bindings = [];

        foreach ($collapsed as $name => $metric) {
            $rows[] = "(?, ?, 'application', ?, ?, ?, NULL, ?)";
            $bindings[] = $metricDate;
            $bindings[] = $name;
            $bindings[] = $metric['value'];
            $bindings[] = $metric['unit'];
            $bindings[] = $calculatedAt;
            $bindings[] = $metric['sample_count'];
        }

        $sql = <<<'SQL'
            INSERT INTO daily_metrics
                (metric_date, metric_name, source, value, unit, calculated_at, dimension_key, sample_count)
            VALUES __ROWS__
            ON CONFLICT (metric_date, metric_name, source, dimension_key)
            DO UPDATE SET
                value = daily_metrics.value + EXCLUDED.value,
                calculated_at = EXCLUDED.calculated_at,
                sample_count = COALESCE(daily_metrics.sample_count, 0) + EXCLUDED.sample_count
            SQL;

        try {
            DB::statement(str_replace('__ROWS__', implode(', ', $rows), $sql), $bindings);
        } catch (Throwable $exception) {
            Log::warning('Operational metric aggregation failed.', [
                'metrics' => array_keys($collapsed),
                'exception' => $exception::class,
            ]);
        }
    }
}
