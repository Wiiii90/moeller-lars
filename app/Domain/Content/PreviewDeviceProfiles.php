<?php

namespace App\Domain\Content;

final class PreviewDeviceProfiles
{
    /**
     * A single catalogue for the admin's responsive live viewport.
     * Dimensions are CSS layout pixels, not physical panel pixels.
     * Null dimensions follow the artist's current browser viewport.
     *
     * @return array<string, array{label: string, category: string, width: int|null, height: int|null, touch: bool}>
     */
    public static function all(): array
    {
        return [
            'current-window' => ['label' => 'Current window', 'category' => 'desktop', 'width' => null, 'height' => null, 'touch' => false],
            'desktop-compact' => ['label' => 'Small desktop (1024 × 768)', 'category' => 'desktop', 'width' => 1024, 'height' => 768, 'touch' => false],
            'desktop-laptop' => ['label' => 'Desktop (1280 × 800)', 'category' => 'desktop', 'width' => 1280, 'height' => 800, 'touch' => false],
            'desktop-wide' => ['label' => 'Wide desktop (1440 × 900)', 'category' => 'desktop', 'width' => 1440, 'height' => 900, 'touch' => false],
            'desktop-large' => ['label' => 'Large desktop (1920 × 1080)', 'category' => 'desktop', 'width' => 1920, 'height' => 1080, 'touch' => false],
            'tablet-mini' => ['label' => 'iPad (768 × 1024)', 'category' => 'tablet', 'width' => 768, 'height' => 1024, 'touch' => true],
            'tablet-large' => ['label' => 'iPad Air (820 × 1180)', 'category' => 'tablet', 'width' => 820, 'height' => 1180, 'touch' => true],
            'tablet-android' => ['label' => 'Android tablet (800 × 1280)', 'category' => 'tablet', 'width' => 800, 'height' => 1280, 'touch' => true],
            'iphone-small' => ['label' => 'iPhone SE (375 × 667)', 'category' => 'mobile', 'width' => 375, 'height' => 667, 'touch' => true],
            'iphone-13' => ['label' => 'iPhone (390 × 844)', 'category' => 'mobile', 'width' => 390, 'height' => 844, 'touch' => true],
            'iphone-large' => ['label' => 'Large iPhone (430 × 932)', 'category' => 'mobile', 'width' => 430, 'height' => 932, 'touch' => true],
            'android-small' => ['label' => 'Android (360 × 800)', 'category' => 'mobile', 'width' => 360, 'height' => 800, 'touch' => true],
            'pixel-7' => ['label' => 'Pixel 7 (412 × 915)', 'category' => 'mobile', 'width' => 412, 'height' => 915, 'touch' => true],
        ];
    }

    /** @return array{label: string, category: string, width: int|null, height: int|null, touch: bool}|null */
    public static function find(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }
}
