<?php

use App\Domain\Analytics\AnalyticsWorldMap;

it('projects country centroids into the generated map coordinate system', function (): void {
    $points = collect(app(AnalyticsWorldMap::class)->points([
        ['label' => 'Japan', 'nb_visits' => 10],
        ['label' => 'United States', 'nb_visits' => 8],
        ['label' => 'Germany', 'nb_visits' => 6],
    ]))->keyBy('label');

    expect($points['Japan'])
        ->toMatchArray(['x' => 1060.0, 'y' => 180.0])
        ->and($points['United States'])
        ->toMatchArray(['x' => 276.67, 'y' => 173.33])
        ->and($points['Germany'])
        ->toMatchArray(['x' => 630.0, 'y' => 130.0]);
});
