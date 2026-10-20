<?php

use App\Models\Exhibition;
use Carbon\CarbonImmutable;

it('shows organizer separately from the physical map location', function (): void {
    $exhibition = new Exhibition;
    $exhibition->setRawAttributes([
        'organizer' => 'Barfuss Galerie & Freundeskreis',
        'venue' => 'Simon-Petrus-Kirche',
        'city' => 'Norderstedt',
        'country' => 'Deutschland',
    ]);

    expect($exhibition->summaryAttribution())->toBe('Barfuss Galerie & Freundeskreis')
        ->and($exhibition->mapLocationLabel())->toBe('Simon-Petrus-Kirche · Norderstedt · Deutschland');
});

it('keeps ambiguous pre-migration venue display without appending a ghost city', function (): void {
    $exhibition = new Exhibition;
    $exhibition->setRawAttributes([
        'organizer' => null,
        'venue' => 'Der blonde Hans',
        'city' => 'Hamburg',
        'country' => null,
    ]);

    expect($exhibition->summaryAttribution())->toBe('Der blonde Hans')
        ->and($exhibition->mapLocationLabel())->toBe('Der blonde Hans · Hamburg');
});

it('does not invent a public city byline when no organizer or venue exists', function (): void {
    $exhibition = new Exhibition;
    $exhibition->setRawAttributes([
        'organizer' => null, 'venue' => null, 'city' => 'Hamburg',
    ]);
    expect($exhibition->summaryAttribution())->toBeNull();
});

it('only exposes opted-in vernissages before their actual opening time', function (): void {
    $exhibition = new Exhibition;
    $exhibition->setRawAttributes([
        'starts_on' => '2027-03-03',
        'ends_on' => '2027-04-03',
        'vernissage_at' => '2027-03-03 18:00:00',
        'show_vernissage' => false,
    ]);

    expect($exhibition->shouldShowPublicVernissage(CarbonImmutable::parse('2027-03-02 12:00:00')))->toBeFalse();
    $exhibition->setAttribute('show_vernissage', true);
    expect($exhibition->shouldShowPublicVernissage(CarbonImmutable::parse('2027-03-02 12:00:00')))->toBeTrue()
        ->and($exhibition->shouldShowPublicVernissage(CarbonImmutable::parse('2027-03-03 17:00:00')))->toBeTrue()
        ->and($exhibition->shouldShowPublicVernissage(CarbonImmutable::parse('2027-03-03 19:00:00')))->toBeFalse();
});
