<?php

namespace App\Domain\Content;

use App\Models\PublicContentSetting;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;

final class RegionalFormats
{
    public const OPTIONS = [
        'de-DE' => 'Deutschland (20.10.2026 · 1.234,56)',
        'en-GB' => 'United Kingdom (20/10/2026 · 1,234.56)',
        'en-US' => 'United States (10/20/2026 · 1,234.56)',
    ];

    public const DEFAULT = 'de-DE';

    public static function publicRegion(): string
    {
        return self::region(PublicContentSetting::general()->getAttribute('regional_format'));
    }

    public static function adminRegion(): string
    {
        return self::region(Auth::user()?->getAttribute('admin_region'));
    }

    public static function publicDate(DateTimeInterface $date): string
    {
        return self::date($date, self::publicRegion());
    }

    public static function publicDateTime(DateTimeInterface $date): string
    {
        return self::dateTime($date, self::publicRegion());
    }

    public static function publicDateRange(DateTimeInterface $start, DateTimeInterface $end): string
    {
        return self::dateRange($start, $end, self::publicRegion());
    }

    public static function adminDate(DateTimeInterface $date): string
    {
        return self::date($date, self::adminRegion());
    }

    public static function adminDateTime(DateTimeInterface $date): string
    {
        return self::dateTime($date, self::adminRegion());
    }

    public static function date(DateTimeInterface $date, string $region): string
    {
        return $date->format(match (self::region($region)) {
            'de-DE' => 'd.m.Y',
            'en-GB' => 'd/m/Y',
            'en-US' => 'm/d/Y',
        });
    }

    public static function dateTime(DateTimeInterface $date, string $region): string
    {
        return self::date($date, $region).', '.$date->format('H:i');
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

    public static function region(mixed $value): string
    {
        return is_string($value) && array_key_exists($value, self::OPTIONS)
            ? $value
            : self::DEFAULT;
    }
}
