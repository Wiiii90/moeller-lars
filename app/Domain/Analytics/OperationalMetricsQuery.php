<?php

namespace App\Domain\Analytics;

use App\Models\DailyMetric;
use Illuminate\Database\Eloquent\Collection;

final class OperationalMetricsQuery
{
    /** @return Collection<int, DailyMetric> */
    public function recent(int $days = 30): Collection
    {
        if ($days < 1 || $days > 365) {
            throw new \InvalidArgumentException('Operational metric range must be between 1 and 365 days.');
        }

        /** @var Collection<int, DailyMetric> $metrics */
        $metrics = DailyMetric::query()
            ->where('metric_date', '>=', now()->subDays($days - 1)->toDateString())
            ->where('source', 'application')
            ->where(function ($query): void {
                $query->where('metric_name', 'like', 'bot:%')
                    ->orWhere('metric_name', 'like', 'error:%')
                    ->orWhere('metric_name', 'like', 'performance:%')
                    ->orWhere('metric_name', 'like', 'admin:%')
                    ->orWhere('metric_name', 'like', 'upload:%')
                    ->orWhere('metric_name', 'like', 'storage:%')
                    ->orWhere('metric_name', 'like', 'deployment:%')
                    ->orWhere('metric_name', 'like', 'security:%');
            })
            ->orderBy('metric_date')
            ->orderBy('metric_name')
            ->get();

        return $metrics;
    }
}
