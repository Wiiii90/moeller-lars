<?php

namespace App\Domain\Media;

final class MediaStorageUnits
{
    public const DECIMAL_GIGABYTE_BYTES = 1_000_000_000;

    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        if ($bytes < 1_000) {
            return $bytes.' B';
        }

        if ($bytes < 1_000_000) {
            return self::formatDecimal($bytes / 1_000, 1).' kB';
        }

        if ($bytes < self::DECIMAL_GIGABYTE_BYTES) {
            return self::formatDecimal($bytes / 1_000_000, 1).' MB';
        }

        return self::formatDecimal($bytes / self::DECIMAL_GIGABYTE_BYTES, 2).' GB';
    }

    private static function formatDecimal(float $value, int $precision): string
    {
        return rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.');
    }
}
