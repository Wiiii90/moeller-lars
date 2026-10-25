<?php

use App\Domain\Analytics\AnalyticsWorldMap;

it('excludes unmappable and nonpositive countries without fabricating map markers', function (): void {
    $points = app(AnalyticsWorldMap::class)->points([
        ['label' => 'Japan', 'nb_visits' => 10],
        ['label' => 'Germany', 'nb_visits' => 5],
        ['label' => 'Unknownland', 'nb_visits' => 20],
        ['label' => 'United States', 'nb_visits' => 0],
        ['label' => 'Japan', 'nb_visits' => -1],
        ['label' => 'Germany', 'nb_visits' => 'not a number'],
        ['label' => '', 'nb_visits' => 99],
    ]);

    expect(array_column($points, 'label'))->toBe(['Japan', 'Germany'])
        ->and(array_column($points, 'visits'))->toBe([10, 5]);

    foreach ($points as $point) {
        expect($point['x'])->toBeGreaterThanOrEqual(0)
            ->toBeLessThanOrEqual(AnalyticsWorldMap::WIDTH)
            ->and($point['y'])->toBeGreaterThanOrEqual(0)
            ->toBeLessThanOrEqual(AnalyticsWorldMap::HEIGHT);
    }
});

it('scales marker prominence by visits while keeping geographical ordering', function (): void {
    $points = collect(app(AnalyticsWorldMap::class)->points([
        ['label' => 'Japan', 'nb_visits' => 20],
        ['label' => 'Germany', 'nb_visits' => 10],
        ['label' => 'United States', 'nb_visits' => 5],
    ]))->keyBy('label');

    expect($points['Japan']['radius'])->toBeGreaterThan($points['Germany']['radius'])
        ->and($points['Germany']['radius'])->toBeGreaterThan($points['United States']['radius'])
        ->and($points['Japan']['x'])->toBeGreaterThan($points['Germany']['x'])
        ->and($points['Germany']['x'])->toBeGreaterThan($points['United States']['x']);
});
