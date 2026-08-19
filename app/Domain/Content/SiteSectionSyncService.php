<?php

namespace App\Domain\Content;

use App\Models\ArtworkCategory;
use App\Models\BlogSetting;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class SiteSectionSyncService
{
    public function syncGallery(ArtworkCategory $category): SiteSection
    {
        $parentSectionId = null;
        $parentCategoryId = $category->getAttribute('parent_id');

        if ($parentCategoryId !== null) {
            /** @var SiteSection|null $parentSection */
            $parentSection = SiteSection::query()
                ->where('type', SiteSection::TYPE_GALLERY)
                ->where('artwork_category_id', $parentCategoryId)
                ->first();

            if ($parentSection === null) {
                /** @var ArtworkCategory $parentCategory */
                $parentCategory = ArtworkCategory::query()->findOrFail($parentCategoryId);
                $parentSection = $this->syncGallery($parentCategory);
            }

            if ($parentSection->getAttribute('parent_id') !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Gallery sections support one submenu level only.',
                ]);
            }

            $parentSectionId = (int) $parentSection->getKey();
        }

        /** @var SiteSection $section */
        $section = SiteSection::query()->firstOrNew([
            'artwork_category_id' => $category->getKey(),
        ]);
        $section->fill([
            'type' => SiteSection::TYPE_GALLERY,
            'title' => (string) $category->getAttribute('name'),
            'navigation_label' => (string) $category->getAttribute('name'),
            'slug' => (string) $category->getAttribute('slug'),
            'state' => (string) $category->getAttribute('state'),
            'position' => (int) $category->getAttribute('position'),
            'show_in_navigation' => (bool) $category->getAttribute('show_in_navigation'),
            'parent_id' => $parentSectionId,
        ]);

        $this->assertParentVisibility($section);
        $this->assertNavigationPositionAvailable($section);
        $section->save();

        return $section;
    }

    public function deleteGallery(ArtworkCategory $category): void
    {
        SiteSection::query()
            ->where('type', SiteSection::TYPE_GALLERY)
            ->where('artwork_category_id', $category->getKey())
            ->delete();
    }

    public function syncPublicContent(PublicContentSetting $settings): void
    {
        $this->syncSingleton(
            SiteSection::TYPE_VITA,
            'Vita',
            'cv',
            (string) $settings->getAttribute('cv_navigation_label'),
            (int) $settings->getAttribute('cv_navigation_position'),
            (bool) $settings->getAttribute('cv_enabled'),
        );

        $this->syncSingleton(
            SiteSection::TYPE_EXHIBITIONS,
            'Exhibitions',
            'exhibitions',
            (string) $settings->getAttribute('exhibitions_navigation_label'),
            (int) $settings->getAttribute('exhibitions_navigation_position'),
            (bool) $settings->getAttribute('exhibitions_enabled'),
        );
    }

    public function syncBlog(BlogSetting $settings): SiteSection
    {
        return $this->syncSingleton(
            SiteSection::TYPE_BLOG,
            (string) $settings->getAttribute('listing_title'),
            'blog',
            (string) $settings->getAttribute('navigation_label'),
            (int) $settings->getAttribute('navigation_position'),
            (bool) $settings->getAttribute('public_enabled'),
        );
    }

    public function publicType(string $type): ?SiteSection
    {
        /** @var SiteSection|null $section */
        $section = SiteSection::query()
            ->where('type', $type)
            ->where('state', 'published')
            ->first();

        return $section;
    }

    private function syncSingleton(
        string $type,
        string $title,
        string $slug,
        string $navigationLabel,
        int $position,
        bool $enabled,
    ): SiteSection {
        /** @var SiteSection $section */
        $section = SiteSection::query()->firstOrNew(['type' => $type]);
        $section->fill([
            'title' => $title,
            'navigation_label' => $navigationLabel,
            'slug' => $slug,
            'state' => $enabled ? 'published' : 'hidden',
            'position' => $position,
            'show_in_navigation' => $enabled,
            'parent_id' => null,
            'artwork_category_id' => null,
        ]);

        $this->assertNavigationPositionAvailable($section);
        $section->save();

        return $section;
    }

    private function assertParentVisibility(SiteSection $section): void
    {
        if (
            $section->getAttribute('parent_id') === null
            || (string) $section->getAttribute('state') !== 'published'
            || ! (bool) $section->getAttribute('show_in_navigation')
        ) {
            return;
        }

        /** @var SiteSection|null $parent */
        $parent = SiteSection::query()->find($section->getAttribute('parent_id'));
        if (
            $parent === null
            || (string) $parent->getAttribute('type') !== SiteSection::TYPE_GALLERY
            || (string) $parent->getAttribute('state') !== 'published'
            || ! (bool) $parent->getAttribute('show_in_navigation')
        ) {
            throw ValidationException::withMessages([
                'parent_id' => 'A visible Gallery submenu requires a visible published Gallery parent.',
            ]);
        }
    }

    private function assertNavigationPositionAvailable(SiteSection $section): void
    {
        if (
            (string) $section->getAttribute('state') !== 'published'
            || ! (bool) $section->getAttribute('show_in_navigation')
        ) {
            return;
        }

        $parentId = $section->getAttribute('parent_id');
        /** @var Builder<SiteSection> $query */
        $query = SiteSection::query();
        $query->where('state', 'published');
        $query->where('show_in_navigation', true);
        $query->where('position', $section->getAttribute('position'));

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        if ($section->exists) {
            $query->whereKeyNot($section->getKey());
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'position' => $parentId === null
                    ? 'Another visible top-level site section already uses this navigation position.'
                    : 'Another visible Gallery in this submenu already uses this navigation position.',
            ]);
        }
    }
}
