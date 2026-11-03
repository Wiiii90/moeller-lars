<?php

use App\Models\Exhibition;
use Carbon\CarbonImmutable;

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
