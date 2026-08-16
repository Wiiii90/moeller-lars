<?php

namespace App\Domain\Analytics;

use App\Models\DailyMetric;
use Illuminate\Support\Collection;

final class OperationalMetricsQuery
{
    /** @return Collection<int, DailyMetric> */
    public function recent(int $days = 30): Collection
    {
        if ($days < 1 || $days > 365) {
            throw new \InvalidArgumentException('Operational metric range must be between 1 and 365 days.');
        }

        return DailyMetric::query()
            ->where('metric_date', '>=', now()->subDays($days - 1)->toDateString())
            ->where('source', 'application')
            ->where(function ($query): void {
                $query->where('metric_name', 'like', 'bot:%')
                    ->orWhere('metric_name', 'like', 'error:%')
                    ->orWhere('metric_name', 'like', 'performance:%');
            })
            ->orderBy('metric_date')
            ->orderBy('metric_name')
            ->get();
    }
}
