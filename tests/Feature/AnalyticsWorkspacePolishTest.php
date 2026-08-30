<?php

use App\Filament\Pages\Analytics;

it('keeps the shared Visual Stage structurally present across reporting states', function (): void {
    $view = (string) file_get_contents(resource_path('views/filament/pages/analytics.blade.php'));
    $analyticsCss = (string) file_get_contents(resource_path('css/admin/analytics.css'));
    $dataCss = (string) file_get_contents(resource_path('css/admin/data-workspace.css'));

    expect($view)
        ->toContain('analytics-visual-stage admin-visual-stage admin-visual-stage--stackable')
        ->toContain('analytics-world admin-visual-stage__pane')
        ->toContain('analytics-stage-rail admin-visual-stage__pane')
        ->toContain('aria-label="Analytics Visual Stage"')
        ->toContain("\$status === 'disabled' => 'No reporting data for this environment.'")
        ->toContain("\$status === 'unavailable' => 'Reporting data is currently unavailable.'")
        ->toContain("\$countryRows === [] => 'No country-level visits in this period.'")
        ->toContain("view()->exists('filament.generated.analytics-world-map')")
        ->toContain("@include('filament.generated.analytics-world-map')")
        ->not->toContain('@if ($available)')
        ->not->toContain('Matomo reporting is disabled.')
        ->and($analyticsCss)
        ->not->toContain('height: var(--admin-visual-stage-height);', '--analytics-visual-stage-height', '--analytics-stage-height')
        ->and($dataCss)
        ->toMatch('/\.admin-visual-stage\s*\{[^}]*height:\s*var\(--admin-visual-stage-height\);/s');
});

it('uses loaded country data for client-side map and ranking selection without Matomo fanout', function (): void {
    $view = (string) file_get_contents(resource_path('views/filament/pages/analytics.blade.php'));
    $client = (string) file_get_contents(app_path('Domain/Analytics/MatomoReportingClient.php'));

    expect($view)
        ->toContain('selectCountry(country) { this.selectedCountry = country; this.activeCountry = country }')
        ->toContain('x-on:mouseenter="previewCountry(')
        ->toContain('x-on:focus="previewCountry(')
        ->toContain('x-on:click="selectCountry(')
        ->toContain('x-bind:aria-pressed=')
        ->not->toContain('wire:click="selectCountry')
        ->and(substr_count($client, 'API.getBulkRequest'))->toBe(1)
        ->and($client)->not->toContain('getCountryDetail', 'countryRequest');
});

it('keeps one central detail table and its controls together after the Visual Stage', function (): void {
    $view = (string) file_get_contents(resource_path('views/filament/pages/analytics.blade.php'));
    $css = (string) file_get_contents(resource_path('css/admin/analytics.css'));

    $stage = strpos($view, 'analytics-visual-stage admin-visual-stage admin-visual-stage--stackable');
    $detail = strpos($view, 'analytics-detail-surface admin-visual-stage-followup');
    $controls = strpos($view, '<x-admin.controls class="analytics-controls"');
    $table = strpos($view, '<x-admin.table class="admin-table--data analytics-detail-table"');

    expect($stage)->not->toBeFalse()
        ->and($detail)->not->toBeFalse()
        ->and($controls)->not->toBeFalse()
        ->and($table)->not->toBeFalse()
        ->and($stage)->toBeLessThan($detail)
        ->and($detail)->toBeLessThan($controls)
        ->and($controls)->toBeLessThan($table)
        ->and(substr_count($view, '<x-admin.table'))->toBe(1)
        ->and($view)->toContain('class="admin-pager"')
        ->not->toContain('analytics-pager-boundary')
        ->not->toContain('->links()', 'links()')
        ->and($css)->not->toContain('.analytics-pager-boundary');
});

it('removes the large operational presentation and public payload while keeping bot telemetry separate', function (): void {
    $view = (string) file_get_contents(resource_path('views/filament/pages/analytics.blade.php'));
    $page = (string) file_get_contents(app_path('Filament/Pages/Analytics.php'));
    $query = (string) file_get_contents(app_path('Domain/Analytics/OperationalMetricsQuery.php'));
    $recorder = (string) file_get_contents(app_path('Domain/Analytics/OperationalMetricRecorder.php'));

    expect($view)
        ->not->toContain('Application operational health', 'Average admin response', 'analytics-operations')
        ->toContain('Application signals')
        ->toContain('Human analytics · Matomo')
        ->and($page)
        ->toContain('buildApplicationSignals')
        ->toContain("->filter(static fn (DailyMetric \$metric): bool => \$metric->getAttribute('metric_name') === 'bot:request')")
        ->toContain("(\$row['name'] ?? null) !== 'bot:request'")
        ->toContain("'detail' => 'Application telemetry'")
        ->not->toContain('public array $operational = []', 'public array $operationalSummary = []', 'buildOperationalSummary')
        ->and($query)
        ->toContain("'bot:%'", "'error:%'", "'performance:%'", "'operation:%'", "'storage:%'", "'deployment:%'", "'security:%'")
        ->and($recorder)
        ->toContain('INSERT INTO daily_metrics', 'DB::statement');
});

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

it('only surfaces measured bot telemetry as an application signal', function (): void {
    $method = new ReflectionMethod(Analytics::class, 'buildApplicationSignals');
    $analytics = new Analytics;

    expect($method->invoke($analytics, []))->toBe([])
        ->and($method->invoke($analytics, [
            ['name' => 'error:http_5xx', 'value' => 4.0],
        ]))->toBe([])
        ->and($method->invoke($analytics, [
            ['name' => 'bot:request', 'value' => 0.0],
        ]))->toBe([[
            'label' => 'Bot requests',
            'value' => '0',
            'detail' => 'Application telemetry',
        ]]);
});

it('keeps map geometry in the normal build artifact and exposes a distinct offline-build state', function (): void {
    $package = (string) file_get_contents(base_path('package.json'));
    $docker = (string) file_get_contents(base_path('Dockerfile'));
    $generator = (string) file_get_contents(resource_path('scripts/generate-analytics-map.mjs'));

    expect($package)
        ->toContain('"generate:analytics-map"')
        ->toContain('"build": "npm run generate:analytics-map && vite build"')
        ->toContain('"dev": "npm run generate:analytics-map && vite"')
        ->and($docker)
        ->toContain('COPY --from=frontend /build/resources/views/filament/generated/analytics-world-map.blade.php')
        ->and($generator)
        ->toContain('data-map-source-unavailable="true"')
        ->toContain('Map geometry unavailable in this build.')
        ->not->toContain('window.fetch', 'leaflet', 'mapbox');
});
