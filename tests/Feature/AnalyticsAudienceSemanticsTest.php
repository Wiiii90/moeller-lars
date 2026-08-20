<?php

use App\Filament\Pages\Analytics;

/** @return array<int, array{label:string,value:string,detail:string}> */
function audienceHighlights(array $report): array
{
    $method = new ReflectionMethod(Analytics::class, 'buildAudienceHighlights');
    $result = $method->invoke(new Analytics, $report);

    expect($result)->toBeArray();

    return $result;
}

it('does not invent a returning visitor split or ranking leaders from unavailable metrics', function () {
    $highlights = audienceHighlights([
        'status' => 'available',
        'metrics' => ['nb_visits' => 10.0],
        'returning' => [],
        'referrers' => [
            ['label' => 'Unavailable source', 'nb_visits' => null],
            ['label' => 'Measured source', 'nb_visits' => 3.0],
        ],
        'countries' => [
            ['label' => 'Unavailable country', 'nb_visits' => null],
        ],
        'content' => [
            ['label' => '/unavailable', 'nb_hits' => null],
            ['label' => '/measured', 'nb_hits' => 7.0],
        ],
        'ai_assistants' => [
            ['label' => 'Unavailable AI source', 'nb_visits' => null],
        ],
    ]);

    expect($highlights[0])
        ->toMatchArray([
            'label' => 'New / returning',
            'value' => '—',
            'detail' => 'Returning-visitor split unavailable',
        ])
        ->and($highlights[1])
        ->toMatchArray([
            'label' => 'Leading source',
            'value' => 'Measured source',
            'detail' => '3 visits',
        ])
        ->and($highlights[2])
        ->toMatchArray([
            'label' => 'Leading country',
            'value' => 'No data',
            'detail' => 'No geography data',
        ])
        ->and($highlights[3])
        ->toMatchArray([
            'label' => 'Most viewed content',
            'value' => '/measured',
            'detail' => '7 views/actions',
        ])
        ->and($highlights[4])
        ->toMatchArray([
            'label' => 'AI referrals',
            'value' => 'None detected',
            'detail' => 'No AI-assistant referrals in range',
        ]);
});

it('marks whole unavailable audience reports separately from successful empty reports', function () {
    $highlights = audienceHighlights([
        'status' => 'available',
        'metrics' => ['nb_visits' => 10.0],
        'returning' => ['nb_visits_returning' => 2.0],
        'referrers' => [],
        'countries' => [],
        'content' => [],
        'ai_assistants' => [],
        'warnings' => [
            'Referrers report is unavailable.',
            'Countries report is unavailable.',
            'Content report is unavailable.',
            'Ai assistants report is unavailable.',
        ],
    ]);

    expect($highlights[1])
        ->toMatchArray([
            'label' => 'Leading source',
            'value' => '—',
            'detail' => 'Referrer report unavailable',
        ])
        ->and($highlights[2])
        ->toMatchArray([
            'label' => 'Leading country',
            'value' => '—',
            'detail' => 'Country report unavailable',
        ])
        ->and($highlights[3])
        ->toMatchArray([
            'label' => 'Most viewed content',
            'value' => '—',
            'detail' => 'Content report unavailable',
        ])
        ->and($highlights[4])
        ->toMatchArray([
            'label' => 'AI referrals',
            'value' => '—',
            'detail' => 'AI-assistant report unavailable',
        ]);

    $empty = audienceHighlights([
        'status' => 'available',
        'metrics' => ['nb_visits' => 10.0],
        'returning' => ['nb_visits_returning' => 2.0],
        'referrers' => [],
        'countries' => [],
        'content' => [],
        'ai_assistants' => [],
        'warnings' => [],
    ]);

    expect($empty[1]['value'])->toBe('No data')
        ->and($empty[2]['value'])->toBe('No data')
        ->and($empty[3]['value'])->toBe('No data')
        ->and($empty[4]['value'])->toBe('None detected');
});

it('keeps a genuine zero returning visitor metric distinct from unavailable', function () {
    $highlights = audienceHighlights([
        'status' => 'available',
        'metrics' => ['nb_visits' => 10.0],
        'returning' => ['nb_visits_returning' => 0.0],
    ]);

    expect($highlights[0])
        ->toMatchArray([
            'value' => '10 / 0',
            'detail' => 'visits in selected period',
        ]);
});

it('can infer a zero new returning split when the authoritative total is zero', function () {
    $highlights = audienceHighlights([
        'status' => 'available',
        'metrics' => ['nb_visits' => 0.0],
        'returning' => [],
    ]);

    expect($highlights[0])
        ->toMatchArray([
            'value' => '0 / 0',
            'detail' => 'visits in selected period',
        ]);
});
