<?php

use App\Domain\Artwork\ArtworkDate;
use App\Models\Artwork;
use Illuminate\Validation\ValidationException;

it('keeps exactly the selected date precision without inventing a month or day', function (?string $source, array $parts): void {
    expect(ArtworkDate::normalize($source))->toBe($parts);
    $artwork = new Artwork;
    $artwork->setRawAttributes($parts);
    expect(ArtworkDate::fromArtwork($artwork))->toBe($source);
})->with([
    'undated' => [null, ['year' => null, 'month' => null, 'day' => null]],
    'year' => ['2027', ['year' => 2027, 'month' => null, 'day' => null]],
    'month' => ['2027-02', ['year' => 2027, 'month' => 2, 'day' => null]],
    'leap day' => ['2028-02-29', ['year' => 2028, 'month' => 2, 'day' => 29]],
]);

it('rejects impossible or noncanonical dates at the owning Artwork editor boundary', function (mixed $source): void {
    expect(fn () => ArtworkDate::normalize($source))->toThrow(ValidationException::class);
})->with([
    'three digit year' => '999',
    'invalid month' => '2027-13',
    'invalid leap day' => '2027-02-29',
    'invalid day' => '2027-04-31',
    'ambiguous human date' => 'April 2027',
    'timestamp rather than day' => '2027-02-01T00:00:00',
    'non-string' => 2027,
]);

it('rejects date parts with missing parents and accepts valid leap days', function (): void {
    ArtworkDate::validateParts(['year' => 2028, 'month' => 2, 'day' => 29]);
    foreach ([
        ['year' => null, 'month' => 2, 'day' => null],
        ['year' => 2027, 'month' => null, 'day' => 3],
        ['year' => 2027, 'month' => 2, 'day' => 29],
        ['year' => 2027, 'month' => 11, 'day' => 31],
    ] as $parts) {
        expect(fn () => ArtworkDate::validateParts($parts))->toThrow(ValidationException::class);
    }
});

it('displays partial dates in all configured public regions without losing their precision', function (): void {
    $artwork = new Artwork;
    $artwork->setRawAttributes(['year' => 2027, 'month' => null, 'day' => null]);
    expect(ArtworkDate::display($artwork, 'de-DE'))->toBe('2027');

    $artwork->setRawAttributes(['year' => 2027, 'month' => 6, 'day' => null]);
    expect(ArtworkDate::display($artwork, 'de-DE'))->toBe('06.2027')
        ->and(ArtworkDate::display($artwork, 'en-GB'))->toBe('06/2027')
        ->and(ArtworkDate::display($artwork, 'en-US'))->toBe('06/2027');

    $artwork->setRawAttributes(['year' => 2027, 'month' => 6, 'day' => 17]);
    expect(ArtworkDate::display($artwork, 'de-DE'))->toBe('17.06.2027')
        ->and(ArtworkDate::display($artwork, 'en-GB'))->toBe('17/06/2027')
        ->and(ArtworkDate::display($artwork, 'en-US'))->toBe('06/17/2027');
});
