<?php

namespace App\Filament\Support;

final class ActivityClockProjection
{
    /**
     * @param  array<int, int|numeric-string>  $hourly
     * @return array{activity:list<array{hour:int,count:int}>,peak_hour:?int,peak_count:int}
     */
    public function fromHourly(array $hourly): array
    {
        $normalized = array_fill(0, 24, 0);

        foreach ($hourly as $hour => $count) {
            $hour = (int) $hour;
            if ($hour < 0 || $hour > 23) {
                continue;
            }

            $normalized[$hour] = max(0, (int) $count);
        }

        $peakCount = max($normalized);
        $peakHour = $peakCount > 0
            ? (int) array_search($peakCount, $normalized, true)
            : null;

        return [
            'activity' => array_map(
                static fn (int $hour, int $count): array => [
                    'hour' => $hour,
                    'count' => $count,
                ],
                array_keys($normalized),
                array_values($normalized),
            ),
            'peak_hour' => $peakHour,
            'peak_count' => $peakCount,
        ];
    }
}
