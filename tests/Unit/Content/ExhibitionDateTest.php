<?php

use App\Domain\Content\ExhibitionDate;
use Carbon\CarbonImmutable;

it('retains authored year month and day precision without inventing calendar components', function (mixed $source, ?string $expected): void {
    expect(ExhibitionDate::normalize($source))->toBe($expected);
})->with([
    'unset' => [null, null],
    'year' => ['2027', '2027'],
    'month' => ['2027-02', '2027-02'],
    'leap day' => ['2028-02-29', '2028-02-29'],
    'exact date object' => [CarbonImmutable::parse('2027-03-05'), '2027-03-05'],
]);

it('rejects invalid Exhibition date precision and impossible calendar dates', function (mixed $invalid): void {
    expect(fn () => ExhibitionDate::normalize($invalid))->toThrow(\InvalidArgumentException::class);
})->with([
    'zero year' => '0000',
    'out of range month' => '2027-13',
    'zero month' => '2027-00',
    'non leap day' => '2027-02-29',
    'zero day' => '2027-03-00',
    'non canonical separators' => '03.05.2027',
    'untrimmed whitespace' => ' 2027',
    'timestamp' => '2027-03-05T00:00:00',
    'non string' => 2027,
]);

it('projects only unambiguous historic Exhibition expressions into canonical dates', function (
    mixed $structuredStart,
    mixed $structuredEnd,
    mixed $authored,
    ?string $expectedStart,
    ?string $expectedEnd,
): void {
    expect(ExhibitionDate::fromLegacy($structuredStart, $structuredEnd, $authored))
        ->toBe(['starts_on' => $expectedStart, 'ends_on' => $expectedEnd]);
})->with([
    'already structured' => ['2027-05-04', '2027-05-06', null, '2027-05-04', '2027-05-06'],
    'authored year' => [null, null, '2012', '2012', null],
    'authored year span' => [null, null, '2012 – 2014', '2012', '2014'],
    'english month' => [null, null, 'June 2014', '2014-06', null],
    'german month' => [null, null, 'März 2014', '2014-03', null],
    'single German date' => [null, null, '30.04.2015', '2015-04-30', null],
    'German date span' => [null, null, '30.04.2015 – 27.06.2015', '2015-04-30', '2015-06-27'],
    'consecutive opening' => [null, null, '30.04.2015 & 01.05.2015', '2015-04-30', '2015-05-01'],
    'matching structured and authored' => ['2014-06', null, 'June 2014', '2014-06', null],
]);

it('stops rather than guessing on contradictory or unconvertible legacy dates', function (
    mixed $start,
    mixed $end,
    mixed $authored,
): void {
    expect(fn () => ExhibitionDate::fromLegacy($start, $end, $authored))
        ->toThrow(\InvalidArgumentException::class);
})->with([
    'authored conflict' => ['2014-07-01', null, 'June 2014'],
    'ambiguous festival' => [null, null, 'Summer 2027'],
    'nonconsecutive joined days' => [null, null, '30.04.2015 & 03.05.2015'],
    'backwards year range' => [null, null, '2028–2027'],
    'backwards existing range' => ['2027-08', '2027-07', null],
    'invalid authored leap date' => [null, null, '29.02.2027'],
]);

it('formats the authored precision and protects unknown temporal certainty', function (): void {
    expect(ExhibitionDate::display('2027', '2028', 'de-DE'))->toBe('2027 – 2028')
        ->and(ExhibitionDate::display('2027-06', null, 'de-DE'))->toBe('06.2027')
        ->and(ExhibitionDate::display('2027-06', null, 'en-US'))->toBe('06/2027')
        ->and(ExhibitionDate::display('2027-06-12', '2027-06-14', 'en-GB'))->toBe('12/06/2027 – 14/06/2027')
        ->and(ExhibitionDate::display(null, null, 'de-DE'))->toBeNull()
        ->and(ExhibitionDate::validRange('2027-06', '2027'))->toBeTrue()
        ->and(ExhibitionDate::validRange('2027-06', '2027-05'))->toBeFalse()
        ->and(ExhibitionDate::validRange(null, '2027'))->toBeFalse();
    $now = CarbonImmutable::parse('2027-06-12');
    expect(ExhibitionDate::temporalState('2027', null, $now))->toBe('unknown')
        ->and(ExhibitionDate::temporalState('2027-06', null, $now))->toBe('unknown')
        ->and(ExhibitionDate::temporalState('2027-06-12', null, $now))->toBe('current')
        ->and(ExhibitionDate::temporalState('2027-07', null, $now))->toBe('upcoming')
        ->and(ExhibitionDate::temporalState('2027-05', null, $now))->toBe('past')
        ->and(ExhibitionDate::temporalState(null, null, $now))->toBe('unknown');
});

it('can losslessly map every factual Exhibition row in the legacy public Vita inventory', function (): void {
    $source = new \App\Domain\Migration\LegacyPublicCvImporter;
    $rows = array_values(array_filter(
        $source->expectedRows(),
        static fn (array $row): bool => $row['section'] === 'Exhibitions',
    ));
    expect($rows)->not->toBeEmpty();
    foreach ($rows as $row) {
        $mapped = ExhibitionDate::fromLegacy(
            $row['starts_on'],
            $row['ends_on'],
            $row['year_text'],
        );
        expect($mapped['starts_on'])->not->toBeNull();
        expect(ExhibitionDate::validRange($mapped['starts_on'], $mapped['ends_on']))->toBeTrue();
        expect(ExhibitionDate::display($mapped['starts_on'], $mapped['ends_on'], 'de-DE'))->not->toBeNull();
    }
});
