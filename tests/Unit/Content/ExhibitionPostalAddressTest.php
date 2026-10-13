<?php

use App\Models\Exhibition;

it('assembles a structured postal address without duplicating its city', function (): void {
    $exhibition = new Exhibition;
    $exhibition->setRawAttributes([
        'location_text' => 'Wolfhager Str. 182',
        'postal_code' => '34127',
        'city' => 'Kassel',
        'country' => null,
    ]);

    expect($exhibition->streetAddress())->toBe('Wolfhager Str. 182')
        ->and($exhibition->address())->toBe('Wolfhager Str. 182, 34127 Kassel');
});

it('does not invent a street address when only the city is known', function (): void {
    $exhibition = new Exhibition;
    $exhibition->setRawAttributes([
        'location_text' => null,
        'postal_code' => null,
        'city' => 'Berlin',
        'country' => null,
    ]);

    expect($exhibition->streetAddress())->toBeNull()
        ->and($exhibition->address())->toBe('Berlin');
});
