<?php

namespace App\Domain\Content;

use App\Models\PublicContentSetting;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Auth;

final class RegionalFormats
{
    public const OPTIONS = [
        'de-DE' => 'Deutschland (20.10.2026)',
        'en-GB' => 'United Kingdom (20/10/2026)',
        'en-US' => 'United States (10/20/2026)',
    ];

    public const NUMBER_OPTIONS = [
        'de-DE' => '1.234,56',
        'en-US' => '1,234.56',
    ];

    public const TIME_OPTIONS = [
        '24' => '24-hour (23:45)',
        '12' => '12-hour (11:45 PM)',
    ];

    public const DEFAULT = 'de-DE';

    public const DEFAULT_TIME_FORMAT = '24';

    public const DEFAULT_TIME_ZONE = 'UTC';

    /** @return key-of<self::OPTIONS> */
    public static function publicRegion(): string
    {
        return self::region(PublicContentSetting::general()->getAttribute('regional_format'));
    }

    public static function publicNumberRegion(): string
    {
        return self::numberRegion(PublicContentSetting::general()->getAttribute('number_format'));
    }

    public static function publicTimeFormat(): string
    {
        return self::timeFormat(PublicContentSetting::general()->getAttribute('time_format'));
    }

    public static function publicTimeZone(): string
    {
        return self::timeZone(PublicContentSetting::general()->getAttribute('time_zone'));
    }

    /** @return key-of<self::OPTIONS> */
    public static function adminRegion(): string
    {
        return self::region(Auth::user()?->getAttribute('admin_region'));
    }

    public static function adminNumberRegion(): string
    {
        return self::numberRegion(Auth::user()?->getAttribute('admin_number_format'));
    }

    public static function adminTimeFormat(): string
    {
        return self::timeFormat(Auth::user()?->getAttribute('admin_time_format'));
    }

    public static function adminTimeZone(): string
    {
        return self::timeZone(Auth::user()?->getAttribute('admin_time_zone'));
    }

    public static function publicDate(DateTimeInterface $date): string
    {
        return self::date($date, self::publicRegion());
    }

    public static function publicDateTime(DateTimeInterface $date): string
    {
        return self::dateTime($date, self::publicRegion(), self::publicTimeFormat(), self::publicTimeZone());
    }

    public static function publicDateRange(DateTimeInterface $start, DateTimeInterface $end): string
    {
        return self::dateRange($start, $end, self::publicRegion());
    }

    public static function publicNumber(float|int $value, int $decimals): string
    {
        return self::number($value, $decimals, self::publicNumberRegion());
    }

    public static function adminDate(DateTimeInterface $date): string
    {
        return self::date($date, self::adminRegion());
    }

    public static function adminDateTime(DateTimeInterface $date): string
    {
        return self::dateTime($date, self::adminRegion(), self::adminTimeFormat(), self::adminTimeZone());
    }

    public static function adminNumber(float|int $value, int $decimals = 0): string
    {
        return self::number($value, $decimals, self::adminNumberRegion());
    }

    public static function pickerDateFormat(): string
    {
        return match (self::adminRegion()) {
            'de-DE' => 'd.m.Y',
            'en-GB' => 'd/m/Y',
            'en-US' => 'm/d/Y',
        };
    }

    public static function pickerDateTimeFormat(): string
    {
        return self::pickerDateFormat().' '.self::timePattern(self::adminTimeFormat());
    }

    public static function date(DateTimeInterface $date, string $region): string
    {
        return $date->format(match (self::region($region)) {
            'de-DE' => 'd.m.Y',
            'en-GB' => 'd/m/Y',
            'en-US' => 'm/d/Y',
        });
    }

    public static function dateTime(DateTimeInterface $date, string $region, string $timeFormat = self::DEFAULT_TIME_FORMAT, ?string $timeZone = null): string
    {
        $instant = DateTimeImmutable::createFromInterface($date);
        if ($timeZone !== null) {
            $instant = $instant->setTimezone(new DateTimeZone(self::timeZone($timeZone)));
        }

        return self::date($instant, $region).', '.$instant->format(self::timePattern($timeFormat));
    }

    public static function dateRange(DateTimeInterface $start, DateTimeInterface $end, string $region): string
    {
        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            return self::date($start, $region);
        }

        return self::date($start, $region).' – '.self::date($end, $region);
    }

    public static function number(float|int $value, int $decimals, string $region): string
    {
        return match (self::region($region)) {
            'de-DE' => number_format($value, $decimals, ',', '.'),
            'en-GB', 'en-US' => number_format($value, $decimals, '.', ','),
        };
    }

    /** @return key-of<self::OPTIONS> */
    public static function region(mixed $value): string
    {
        return is_string($value) && array_key_exists($value, self::OPTIONS)
            ? $value
            : self::DEFAULT;
    }

    public static function numberRegion(mixed $value): string
    {
        return is_string($value) && array_key_exists($value, self::NUMBER_OPTIONS)
            ? $value
            : self::DEFAULT;
    }

    public static function timeFormat(mixed $value): string
    {
        return is_string($value) && array_key_exists($value, self::TIME_OPTIONS)
            ? $value
            : self::DEFAULT_TIME_FORMAT;
    }

    public static function timeZone(mixed $value): string
    {
        return is_string($value) && in_array($value, DateTimeZone::listIdentifiers(DateTimeZone::ALL), true)
            ? $value
            : self::DEFAULT_TIME_ZONE;
    }

    /** @return array<string,string> */
    public static function timeZoneOptions(): array
    {
        $options = [self::DEFAULT_TIME_ZONE => 'UTC'];
        $winter = new DateTimeImmutable('first day of January 12:00 UTC');
        $summer = new DateTimeImmutable('first day of July 12:00 UTC');
        foreach (DateTimeZone::listIdentifiers(DateTimeZone::ALL) as $zone) {
            if ($zone === self::DEFAULT_TIME_ZONE) {
                continue;
            }

            $zoneInfo = new DateTimeZone($zone);
            $abbreviations = array_unique([
                $winter->setTimezone($zoneInfo)->format('T'),
                $summer->setTimezone($zoneInfo)->format('T'),
            ]);
            $options[$zone] = str_replace('_', ' ', $zone).' ('.implode(' / ', $abbreviations).')';
        }

        return $options;
    }

    private static function timePattern(string $value): string
    {
        return self::timeFormat($value) === '12' ? 'g:i A' : 'H:i';
    }
}
