<?php

use App\Domain\Artwork\ArtworkDimensions;

it('formats height width and optional depth with centimetres by default', function (): void {
    expect(ArtworkDimensions::compose('120', '80', null, 'cm', null))->toBe('120 × 80 cm')
        ->and(ArtworkDimensions::compose('12.5', '8', '2', 'cm', null))->toBe('12.5 × 8 × 2 cm');
});

it('parses structured dimensions and preserves legacy free-form dimensions as custom', function (): void {
    expect(ArtworkDimensions::split('120 × 80 × 2 cm'))->toBe([
        'height' => '120',
        'width' => '80',
        'depth' => '2',
        'unit' => 'cm',
        'custom' => null,
    ])->and(ArtworkDimensions::split('Ø 44 cm, variable'))->toBe([
        'height' => null,
        'width' => null,
        'depth' => null,
        'unit' => 'cm',
        'custom' => 'Ø 44 cm, variable',
    ]);
});

it('round-trips structured non-default units and a custom legacy override without data loss', function (): void {
    $structured = ArtworkDimensions::split('48 × 32 × 1.5 in');
    expect(ArtworkDimensions::compose(
        $structured['height'],
        $structured['width'],
        $structured['depth'],
        $structured['unit'],
        $structured['custom'],
    ))->toBe('48 × 32 × 1.5 in');

    $legacy = 'Ø 44 cm, variable';
    $custom = ArtworkDimensions::split($legacy);
    expect(ArtworkDimensions::compose(
        $custom['height'],
        $custom['width'],
        $custom['depth'],
        $custom['unit'],
        $custom['custom'],
    ))->toBe($legacy);
});
