<?php

namespace App\Domain\Content;

use App\Models\Redirect;
use App\Models\SiteSection;

final class SiteSectionPathPolicy
{
    /**
     * Persisted reason value for Gallery slug redirects. The value is retained so
     * existing redirect records remain authoritative after the policy consolidation.
     */
    public const GALLERY_SLUG_REDIRECT_REASON = 'artwork_category_slug_change';

    /** Persisted reason for configurable page slug redirects. */
    public const CUSTOM_PAGE_SLUG_REDIRECT_REASON = 'site_section_slug_change';

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

    public function isReserved(string $slug): bool
    {
        return in_array($slug, self::RESERVED_SLUGS, true);
    }

    public function available(string $slug, ?int $ignoreSiteSectionId = null): bool
    {
        if ($this->isReserved($slug)) {
            return false;
        }

        $sections = SiteSection::query()->where('slug', $slug);
        if ($ignoreSiteSectionId !== null) {
            $sections->whereKeyNot($ignoreSiteSectionId);
        }
        if ($sections->exists()) {
            return false;
        }

        return ! Redirect::query()->where('source_path', '/'.$slug)->where('enabled', true)->exists();
    }
}
