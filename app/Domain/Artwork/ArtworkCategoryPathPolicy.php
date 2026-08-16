<?php

namespace App\Domain\Artwork;

class ArtworkCategoryPathPolicy
{
    public const RESERVED_APPLICATION_SLUGS = [
        'admin',
        'artworks',
        'media',
        'cv',
        'contact',
        'blog',
        'api',
        'up',
        'storage',
        'sitemap',
        'robots',
        'index',
    ];

    public const LEGACY_STABLE_SLUGS = [
        'paintings',
        'prints',
        'drawings',
        'cyanotype',
        'bichromate',
        'litho',
        'photo',
        'ignis',
        'other',
    ];

    public const CATEGORY_SLUG_REDIRECT_REASON = 'artwork_category_slug_change';

    public function isReserved(string $slug): bool
    {
        return in_array($slug, self::RESERVED_APPLICATION_SLUGS, true);
    }

    public function isLegacyStable(string $slug): bool
    {
        return in_array($slug, self::LEGACY_STABLE_SLUGS, true);
    }
}
