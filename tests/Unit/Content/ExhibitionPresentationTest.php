<?php

use App\Models\Exhibition;
use Carbon\CarbonImmutable;

it('derives exhibition attribution and location from editorial fields', function (): void {
    foreach ([
        [
            ['organizer' => 'Barfuss Galerie & Freundeskreis', 'venue' => 'Simon-Petrus-Kirche',
                'city' => 'Norderstedt', 'country' => 'Deutschland'],
            'Barfuss Galerie & Freundeskreis', 'Simon-Petrus-Kirche · Norderstedt · Deutschland',
        ],
        [
            ['organizer' => null, 'venue' => 'Der blonde Hans', 'city' => 'Hamburg', 'country' => null],
            'Der blonde Hans', 'Der blonde Hans · Hamburg',
        ],
        [
            ['organizer' => null, 'venue' => null, 'city' => 'Hamburg'],
            null, 'Hamburg',
        ],
    ] as [$attributes, $attribution, $location]) {
        $exhibition = new Exhibition;
        $exhibition->setRawAttributes($attributes);
        expect($exhibition->summaryAttribution())->toBe($attribution);
        if ($attribution !== null) {
            expect($exhibition->mapLocationLabel())->toBe($location);
        }
    }
});

it('keeps organizer and venue visibility independent while retaining legacy attribution', function (): void {
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

    $legacy = new Exhibition;
    $legacy->setRawAttributes([
        'organizer' => null,
        'venue' => 'Der blonde Hans',
        'show_organizer' => false,
        'show_venue' => true,
    ]);
    expect($legacy->summaryAttribution())->toBe('Der blonde Hans')
        ->and($legacy->publicVenue())->toBeNull();

    $legacy->organizer = 'Der blonde Hans';
    expect($legacy->summaryAttribution())->toBeNull()
        ->and($legacy->publicVenue())->toBe('Der blonde Hans');

    $legacy->show_organizer = true;
    expect($legacy->summaryAttribution())->toBe('Der blonde Hans')
        ->and($legacy->publicVenue())->toBeNull();
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
