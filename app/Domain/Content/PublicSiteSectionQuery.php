<?php

namespace App\Domain\Content;

use App\Models\Redirect;
use App\Models\SiteSection;

final class PublicSiteSectionQuery
{
    public function __construct(private readonly SitePreviewContext $preview) {}

    public function bySlug(string $slug): ?SiteSection
    {
        $query = SiteSection::query()->where('slug', $slug);
        $this->preview->constrainSectionQuery($query);

        return $query->first();
    }

    public function galleryBySlug(string $slug): ?SiteSection
    {
        $query = SiteSection::query()
            ->where('type', SiteSectionType::Gallery->value)
            ->where('slug', $slug)
            ->with('artworkCategory');
        $this->preview->constrainSectionQuery($query);

        return $query->first();
    }

    public function galleryExistsForCategory(int $categoryId): bool
    {
        $query = SiteSection::query()
            ->where('type', SiteSectionType::Gallery->value)
            ->where('artwork_category_id', $categoryId);
        $this->preview->constrainSectionQuery($query);

        return $query->exists();
    }

    public function redirect(string $sourcePath, string $reason): ?Redirect
    {
        if ($this->preview->active()) {
            return null;
        }

        return Redirect::query()
            ->where('source_path', $sourcePath)
            ->where('enabled', true)
            ->where('reason', $reason)
            ->first();
    }
}
