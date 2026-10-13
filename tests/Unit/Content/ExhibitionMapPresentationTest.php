<?php

use App\Domain\Content\ExhibitionMapPresentation;
use App\Models\Exhibition;

it('presents the same exhibition location regardless of the retired map shape', function (): void {
    $maps = new ExhibitionMapPresentation;
    $wide = new Exhibition;
    $wide->setRawAttributes([
        'latitude' => '52.5200000',
        'longitude' => '13.4050000',
        'map_shape' => 'wide',
    ]);
    $square = new Exhibition;
    $square->setRawAttributes([
        'latitude' => '52.5200000',
        'longitude' => '13.4050000',
        'map_shape' => 'square',
    ]);

    $presentation = $maps->for($wide);
    expect($presentation)->not->toBeNull()
        ->and($maps->for($square))->toEqual($presentation)
        ->and($presentation)->toHaveKeys(['latitude', 'longitude', 'embed_url', 'public_url'])
        ->not->toHaveKey('shape');
});

it('omits a map for an exhibition without coordinates', function (): void {
    expect((new ExhibitionMapPresentation)->for(new Exhibition))->toBeNull();
});
