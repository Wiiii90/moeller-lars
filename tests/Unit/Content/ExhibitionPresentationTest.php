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

it('keeps organizer and venue visibility independent without duplicating the venue byline', function (): void {
    $exhibition = new Exhibition;
    $exhibition->setRawAttributes([
        'organizer' => 'Galerie Muster',
        'venue' => 'Kunsthalle Musterstadt',
        'show_organizer' => true,
        'show_venue' => true,
    ]);

    expect($exhibition->summaryAttribution())->toBe('Galerie Muster')
        ->and($exhibition->publicVenue())->toBe('Kunsthalle Musterstadt');

    $exhibition->show_organizer = false;
    expect($exhibition->summaryAttribution())->toBeNull()
        ->and($exhibition->publicVenue())->toBe('Kunsthalle Musterstadt');

    $exhibition->show_venue = false;
    expect($exhibition->summaryAttribution())->toBeNull()
        ->and($exhibition->publicVenue())->toBeNull();

    $exhibition->show_organizer = true;
    expect($exhibition->summaryAttribution())->toBe('Galerie Muster')
        ->and($exhibition->publicVenue())->toBeNull();
});

it('preserves historical venue-only bylines without repeating them as details', function (): void {
    $exhibition = new Exhibition;
    $exhibition->setRawAttributes([
        'organizer' => null,
        'venue' => 'Der blonde Hans',
        'show_organizer' => false,
        'show_venue' => true,
    ]);

    expect($exhibition->summaryAttribution())->toBe('Der blonde Hans')
        ->and($exhibition->publicVenue())->toBeNull();

    $exhibition->organizer = 'Der blonde Hans';
    expect($exhibition->summaryAttribution())->toBeNull()
        ->and($exhibition->publicVenue())->toBe('Der blonde Hans');

    $exhibition->show_organizer = true;
    expect($exhibition->summaryAttribution())->toBe('Der blonde Hans')
        ->and($exhibition->publicVenue())->toBeNull();
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
