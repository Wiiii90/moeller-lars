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

it('renders analytics and dashboard markers through one shared svg projection', function (): void {
    $component = file_get_contents(resource_path('views/components/admin/analytics-world-map.blade.php'));
    $analytics = file_get_contents(resource_path('views/filament/pages/analytics.blade.php'));
    $dashboard = file_get_contents(resource_path('views/filament/pages/dashboard.blade.php'));
    $overview = file_get_contents(app_path('Filament/Support/DashboardOverview.php'));
    $generator = file_get_contents(resource_path('scripts/generate-analytics-map.mjs'));

    expect($component)
        ->toContain('viewBox="0 0 1200 600"')
        ->toContain('preserveAspectRatio="xMidYMid meet"')
        ->toContain('class="analytics-world__marker-layer"')
        ->toContain('cx="{{ number_format($point[\'x\']')
        ->toContain('cy="{{ number_format($point[\'y\']')
        ->not->toContain('style="left:')
        ->and($analytics)
        ->toContain('app(\\App\\Domain\\Analytics\\AnalyticsWorldMap::class)->points($countryRows)')
        ->toContain('<x-admin.analytics-world-map')
        ->not->toContain('(($lon + 180.0) / 360.0)')
        ->and($dashboard)
        ->toContain('<x-admin.analytics-world-map :points="$analytics[\'map_points\']" />')
        ->not->toContain('style="left: {{ number_format($point[\'x\']')
        ->and($overview)
        ->toContain('app(AnalyticsWorldMap::class)->points($countryRows)')
        ->not->toContain('(($longitude + 180.0) / 360.0)')
        ->and($generator)
        ->toContain('const width = 1200;')
        ->toContain('const height = 600;')
        ->toContain('preserveAspectRatio="xMidYMid meet"');
});
