<?php

use App\Domain\Content\RegionalFormats;
use Carbon\CarbonImmutable;

it('formats dates, intervals and numbers by regional convention', function (): void {
    $date = CarbonImmutable::parse('2026-10-20 16:12:00');
    $start = CarbonImmutable::parse('2026-10-01');

    expect(RegionalFormats::date($date, 'de-DE'))->toBe('20.10.2026')
        ->and(RegionalFormats::date($date, 'en-GB'))->toBe('20/10/2026')
        ->and(RegionalFormats::date($date, 'en-US'))->toBe('10/20/2026')
        ->and(RegionalFormats::dateTime($date, 'de-DE'))->toBe('20.10.2026, 16:12')
        ->and(RegionalFormats::dateRange($start, $date, 'de-DE'))->toBe('01.10.2026 – 20.10.2026')
        ->and(RegionalFormats::number(1234.56, 2, 'de-DE'))->toBe('1.234,56')
        ->and(RegionalFormats::number(1234.56, 2, 'en-GB'))->toBe('1,234.56');
});
