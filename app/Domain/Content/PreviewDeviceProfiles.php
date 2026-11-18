<?php

namespace App\Domain\Content;

final class PreviewDeviceProfiles
{
    /**
     * A single catalogue for the admin's live viewport and browser renderer.
     * Dimensions are CSS layout pixels, not physical panel pixels.
     *
     * @return array<string, array{label: string, category: string, width: int, height: int, scale: float, touch: bool, platform: string}>
     */
    public static function all(): array
    {
        return [
            'desktop-compact' => ['label' => 'Desktop · 1024 × 768', 'category' => 'desktop', 'width' => 1024, 'height' => 768, 'scale' => 1, 'touch' => false, 'platform' => 'desktop'],
            'desktop-laptop' => ['label' => 'Laptop · 1280 × 800', 'category' => 'desktop', 'width' => 1280, 'height' => 800, 'scale' => 1, 'touch' => false, 'platform' => 'desktop'],
            'desktop-wide' => ['label' => 'Desktop · 1440 × 900', 'category' => 'desktop', 'width' => 1440, 'height' => 900, 'scale' => 1, 'touch' => false, 'platform' => 'desktop'],
            'desktop-large' => ['label' => 'Desktop · 1920 × 1080', 'category' => 'desktop', 'width' => 1920, 'height' => 1080, 'scale' => 1, 'touch' => false, 'platform' => 'desktop'],
            'tablet-mini' => ['label' => 'Small tablet · 768 × 1024', 'category' => 'tablet', 'width' => 768, 'height' => 1024, 'scale' => 2, 'touch' => true, 'platform' => 'tablet'],
            'tablet-large' => ['label' => 'Large tablet · 820 × 1180', 'category' => 'tablet', 'width' => 820, 'height' => 1180, 'scale' => 2, 'touch' => true, 'platform' => 'tablet'],
            'iphone-small' => ['label' => 'Small iPhone · 375 × 667', 'category' => 'mobile', 'width' => 375, 'height' => 667, 'scale' => 2, 'touch' => true, 'platform' => 'ios'],
            'iphone-13' => ['label' => 'iPhone · 390 × 844', 'category' => 'mobile', 'width' => 390, 'height' => 844, 'scale' => 3, 'touch' => true, 'platform' => 'ios'],
            'iphone-large' => ['label' => 'Large iPhone · 430 × 932', 'category' => 'mobile', 'width' => 430, 'height' => 932, 'scale' => 3, 'touch' => true, 'platform' => 'ios'],
            'android-small' => ['label' => 'Small Android · 360 × 800', 'category' => 'mobile', 'width' => 360, 'height' => 800, 'scale' => 3, 'touch' => true, 'platform' => 'android'],
            'pixel-7' => ['label' => 'Pixel · 412 × 915', 'category' => 'mobile', 'width' => 412, 'height' => 915, 'scale' => 2.625, 'touch' => true, 'platform' => 'android'],
        ];
    }

    /** @return array{label: string, category: string, width: int, height: int, scale: float, touch: bool, platform: string}|null */
    public static function find(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }
}
