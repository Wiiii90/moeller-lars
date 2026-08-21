<?php

namespace App\Domain\Content;

use App\Models\Redirect;
use App\Models\SiteSection;

final class SiteSectionPathPolicy
{
    private const RESERVED_SLUGS = [
        'admin',
        'api',
        'artworks',
        'media',
        'preview',
        'robots',
        'sitemap',
        'storage',
        'up',
    ];

    public function available(string $slug): bool
    {
        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            return false;
        }

        if (SiteSection::query()->where('slug', $slug)->exists()) {
            return false;
        }

        return ! Redirect::query()->where('source_path', '/'.$slug)->where('enabled', true)->exists();
    }
}
