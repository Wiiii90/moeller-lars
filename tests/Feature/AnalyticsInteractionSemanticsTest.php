<?php

use App\Filament\Pages\Analytics;

/** @return array<string, int|float|string> */
function interactionSignalsFor(
    array $events,
    array $artworkAttention,
    bool $eventsAvailable = true,
    bool $artworkEventsAvailable = true,
): array {
    $method = new ReflectionMethod(Analytics::class, 'buildInteractionSignals');
    $result = $method->invoke(new Analytics, $events, $artworkAttention, $eventsAvailable, $artworkEventsAvailable);

    expect($result)->toBeArray();

    return $result;
}

it('distinguishes an unavailable events report from a successfully empty report', function () {
    $unavailable = interactionSignalsFor([], [], false, true);
    $empty = interactionSignalsFor([], [], true, true);

    expect($unavailable)
        ->toMatchArray([
            'Artwork detail views' => 0,
            'Artwork opens' => '—',
            'Artwork zooms' => '—',
            'Active artwork views' => 0,
            'Next / previous' => '—',
            'Exhibition views' => '—',
            'Contact messages' => '—',
        ])
        ->and($empty)
        ->toMatchArray([
            'Artwork detail views' => 0,
            'Artwork opens' => 0,
            'Artwork zooms' => 0,
            'Active artwork views' => 0,
            'Next / previous' => 0,
            'Exhibition views' => 0,
            'Contact messages' => 0,
        ]);
});

it('keeps unavailable individual event metrics distinct from absent zero-count actions', function () {
    $signals = interactionSignalsFor([
        ['label' => 'artwork_open', 'nb_events' => null],
        ['label' => 'blog_view', 'nb_events' => 4.0],
    ], [], true, true);

    expect($signals)
        ->toMatchArray([
            'Artwork opens' => '—',
            'Artwork zooms' => 0,
            'Blog reads' => 4,
            'Email / Instagram clicks' => 0,
        ]);
});

it('marks artwork-derived signals unavailable independently from generic event signals', function () {
    $signals = interactionSignalsFor([
        ['label' => 'artwork_open', 'nb_events' => 3.0],
    ], [
        ['detail_views' => 8, 'attention_events' => 5],
    ], true, false);

    expect($signals)
        ->toMatchArray([
            'Artwork detail views' => '—',
            'Artwork opens' => 3,
            'Active artwork views' => '—',
        ]);
});
