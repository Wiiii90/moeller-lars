<?php

namespace App\Domain\Artwork;

class ArtworkCategoryPathPolicy
{
    public const RESERVED_APPLICATION_SLUGS = [
        'admin',
        'artworks',
        'media',
        'cv',
        'exhibitions',
        'contact',
        'blog',
        'api',
        'up',
        'storage',
        'sitemap',
        'robots',
    ];

    public const CATEGORY_SLUG_REDIRECT_REASON = 'artwork_category_slug_change';

    public function isReserved(string $slug): bool
    {
        return in_array($slug, self::RESERVED_APPLICATION_SLUGS, true);
    }
}
