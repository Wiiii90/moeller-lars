<?php

namespace App\Domain\Analytics;

final class AnalyticsWorldMap
{
    public const WIDTH = 1200.0;

    public const HEIGHT = 600.0;

    /**
     * Project country centroids into the exact equirectangular coordinate
     * system used by resources/scripts/generate-analytics-map.mjs.
     *
     * @param  list<array<string, mixed>>  $countryRows
     * @return list<array{label:string,visits:int,x:float,y:float,radius:float}>
     */
    public function points(array $countryRows): array
    {
        $centroids = config('analytics-country-centroids', []);
        if (! is_array($centroids)) {
            return [];
        }

        $positiveVisits = array_values(array_filter(
            array_map(
                static fn (array $row): ?float => is_numeric($row['nb_visits'] ?? null) ? (float) $row['nb_visits'] : null,
                $countryRows,
            ),
            static fn (?float $value): bool => $value !== null && $value > 0,
        ));
        $countryMax = $positiveVisits === [] ? 1.0 : max($positiveVisits);
        $points = [];

        foreach ($countryRows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $visits = is_numeric($row['nb_visits'] ?? null) ? (float) $row['nb_visits'] : null;
            $coords = $centroids[$label] ?? null;

            if ($label === '' || $visits === null || $visits <= 0 || ! is_array($coords) || count($coords) < 2) {
                continue;
            }

            $latitude = min(90.0, max(-90.0, (float) $coords[0]));
            $longitude = min(180.0, max(-180.0, (float) $coords[1]));

            $points[] = [
                'label' => $label,
                'visits' => (int) round($visits),
                'x' => round((($longitude + 180.0) / 360.0) * self::WIDTH, 2),
                'y' => round(((90.0 - $latitude) / 180.0) * self::HEIGHT, 2),
                'radius' => round(14.0 + (10.0 * sqrt($visits / $countryMax)), 2),
            ];
        }

        return $points;
    }
}
