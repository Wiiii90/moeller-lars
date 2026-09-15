<?php

use App\Filament\Pages\Analytics;

it('projects disabled empty and missing analytics values without inventing zeroes', function (): void {
    $analytics = new Analytics;
    $analytics->matomo = [
        'status' => 'disabled',
        'metrics' => [],
    ];

    expect($analytics->detailTable())
        ->toMatchArray([
            'state' => 'unavailable',
            'rows' => [],
            'message' => 'No reporting data for this environment.',
        ]);

    $analytics->detailReport = 'geography';
    $analytics->matomo = [
        'status' => 'available',
        'metrics' => ['nb_visits' => 0],
        'countries' => [],
        'warnings' => [],
    ];

    expect($analytics->detailTable())
        ->toMatchArray([
            'state' => 'empty',
            'rows' => [],
            'message' => 'No country-level visits in this period.',
        ]);

    $analytics->detailReport = 'content';
    $analytics->matomo = [
        'status' => 'available',
        'metrics' => ['nb_visits' => 1],
        'content' => [[
            'label' => '/gallery',
            'nb_hits' => null,
            'nb_visits' => 1,
            'bounce_rate' => null,
            'avg_time_on_page' => null,
        ]],
        'warnings' => [],
    ];

    expect($analytics->detailTable()['rows'][0])
        ->toBe(['/gallery', '—', '1', '—', '—']);
});

it('searches only the active report and resets search when the report view changes', function (): void {
    $analytics = new Analytics;
    $analytics->matomo = [
        'status' => 'available',
        'metrics' => ['nb_visits' => 3],
        'content' => [
            ['label' => '/gallery/blue', 'nb_hits' => 2, 'nb_visits' => 2],
            ['label' => '/journal/red', 'nb_hits' => 1, 'nb_visits' => 1],
        ],
        'warnings' => [],
    ];
    $analytics->search = 'blue';

    expect($analytics->detailTable()['total'])->toBe(1);

    $analytics->setDetailReport('geography');

    expect($analytics->search)->toBe('')
        ->and($analytics->detailPage)->toBe(1)
        ->and($analytics->detailReport)->toBe('geography');
});

it('keeps geography context stable around the scrollable ranking without duplicate stage metrics', function (): void {
    $view = file_get_contents(resource_path('views/filament/pages/analytics.blade.php'));
    $css = file_get_contents(resource_path('css/admin/analytics.css'));
    $page = file_get_contents(app_path('Filament/Pages/Analytics.php'));

    expect($view)
        ->toContain('analytics-stage-ranking__scroll')
        ->toContain('analytics-stage-rail__context')
        ->toContain('analytics-country-context__primary')
        ->toContain('Selected country')
        ->toContain('Actions')
        ->not->toContain('analytics-stage-signals')
        ->not->toContain('$stageHighlights')
        ->and($css)
        ->toContain('.analytics-stage-ranking__scroll')
        ->toContain('overflow-y: auto;')
        ->toContain('@keyframes analytics-marker-arrival')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->and($page)
        ->not->toContain('OperationalMetricsQuery')
        ->not->toContain('buildApplicationSignals');
});
