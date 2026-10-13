<?php

use App\Domain\Content\ExhibitionAddressNormalizer;

it('splits recognizable historical German addresses without inventing the country', function (): void {
    $parser = new ExhibitionAddressNormalizer;

    expect($parser->parse('Wolfhager Str. 182, 34127 Kassel'))->toBe([
        'location_text' => 'Wolfhager Str. 182',
        'postal_code' => '34127',
        'city' => 'Kassel',
    ])->and($parser->parse('Hansa-Theater, Steindamm 17, 20099 Hamburg'))->toBe([
        'location_text' => 'Hansa-Theater, Steindamm 17',
        'postal_code' => '20099',
        'city' => 'Hamburg',
    ])->and($parser->parse('Hamburger Straße 1-15, Mundsburg Center, Hamburg'))->toBe([
        'location_text' => 'Hamburger Straße 1-15, Mundsburg Center',
        'postal_code' => null,
        'city' => 'Hamburg',
    ]);
});

it('classifies a city-only record without claiming it has a street address', function (): void {
    expect((new ExhibitionAddressNormalizer)->parse('Bad Saarow'))->toBe([
        'location_text' => null,
        'postal_code' => null,
        'city' => 'Bad Saarow',
    ]);
});

it('leaves ambiguous and compound legacy locations unmodified', function (): void {
    $parser = new ExhibitionAddressNormalizer;
    expect($parser->parse('Hans Albers Eck, Hans-Albers-Platz 20 & Hans-Albers-Klause, Friedrichstraße 19.'))->toBeNull()
        ->and($parser->parse(''))->toBeNull();
});
