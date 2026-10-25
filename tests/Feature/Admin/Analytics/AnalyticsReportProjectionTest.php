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

it('projects behavior from reports already present in the Matomo bulk payload', function (): void {
    $analytics = new Analytics;
    $analytics->detailReport = 'behavior';
    $analytics->matomo = [
        'status' => 'available',
        'metrics' => ['nb_visits' => 10],
        'returning' => ['nb_visits_returning' => 4],
        'visit_duration' => [[
            'label' => '0-10s',
            'nb_visits' => 6,
            'nb_uniq_visitors' => 5,
        ]],
        'pages_per_visit' => [[
            'label' => '2',
            'nb_visits' => 3,
            'nb_uniq_visitors' => 3,
        ]],
        'local_time' => [[
            'label' => '14',
            'nb_visits' => 2,
            'nb_uniq_visitors' => 2,
        ]],
        'day_of_week' => [[
            'label' => '1',
            'nb_visits' => 5,
            'nb_uniq_visitors' => 4,
        ]],
        'warnings' => [],
    ];

    $table = $analytics->detailTable();

    expect($analytics->detailReportOptions())
        ->toHaveKey('behavior', 'Behavior')
        ->and($table['rows'])
        ->toContain(['Visit type', 'New visits', '6', '—', '60.0%'])
        ->toContain(['Visit type', 'Returning visits', '4', '—', '40.0%'])
        ->toContain(['Visit duration', '0-10s', '6', '5', '60.0%'])
        ->toContain(['Pages per visit', '2 pages', '3', '3', '30.0%'])
        ->toContain(['Local time', '14:00–14:59', '2', '2', '20.0%'])
        ->toContain(['Day of week', 'Monday', '5', '4', '50.0%']);
});

it('rejects mounted analytics requests after logout or loss of administrative access', function (bool $guest): void {
    $actor = \App\Models\User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
    config()->set('analytics.matomo.reporting_enabled', false);
    \Illuminate\Support\Facades\Http::preventStrayRequests();
    $component = \Livewire\Livewire::test(Analytics::class);

    if ($guest) {
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
    } else {
        $actor->forceFill(['is_admin' => false])->save();
    }

    $component->set('search', 'Private report')->assertForbidden();
})->with(['guest' => true, 'revoked admin' => false]);
