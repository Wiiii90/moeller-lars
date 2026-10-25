<?php

use App\Domain\Content\ExhibitionMapPresentation;
use App\Models\Exhibition;

it('provides a secure OpenStreetMap marker and destination for a geocoded exhibition', function (): void {
    $exhibition = new Exhibition;
    $exhibition->setRawAttributes([
        'latitude' => '52.5200000',
        'longitude' => '13.4050000',
    ]);

    $presentation = (new ExhibitionMapPresentation)->for($exhibition);
    expect($presentation)->not->toBeNull()
        ->and($presentation['latitude'])->toBe(52.52)
        ->and($presentation['longitude'])->toBe(13.405);

    $embed = parse_url($presentation['embed_url']);
    $public = parse_url($presentation['public_url']);
    parse_str($embed['query'], $embedQuery);
    parse_str($public['query'], $publicQuery);

    expect($embed['scheme'])->toBe('https')
        ->and($embed['host'])->toBe('www.openstreetmap.org')
        ->and($embedQuery['marker'])->toBe('52.52,13.405')
        ->and($public['scheme'])->toBe('https')
        ->and($public['host'])->toBe('www.openstreetmap.org')
        ->and((float) $publicQuery['mlat'])->toBe(52.52)
        ->and((float) $publicQuery['mlon'])->toBe(13.405);
});

it('omits a map for an exhibition without coordinates', function (): void {
    expect((new ExhibitionMapPresentation)->for(new Exhibition))->toBeNull();
});
