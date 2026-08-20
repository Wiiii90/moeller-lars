<?php

namespace App\Domain\Migration;

use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use Illuminate\Support\Collection;

final class SiteSectionMigrationValidator
{
    /**
     * Validate the canonical SiteSection ownership boundary without mutating data.
     *
     * @return array{
     *     ok: bool,
     *     source: array{artwork_categories: int},
     *     target: array{site_sections: int, singleton_sections: int, gallery_sections: int},
     *     errors: list<string>
     * }
     */
    public function validate(): array
    {
        $errors = [];
        /** @var Collection<int, ArtworkCategory> $categories */
        $categories = ArtworkCategory::query()->orderBy('id')->get();

        /** @var Collection<int, SiteSection> $sections */
        $sections = SiteSection::query()->orderBy('id')->get();

        foreach (SiteSection::SINGLETON_TYPES as $type) {
            $count = $sections->where('type', $type)->count();
            if ($count !== 1) {
                $errors[] = "Expected exactly one {$type} SiteSection; found {$count}.";
            }
        }

        /** @var Collection<int, SiteSection> $gallerySections */
        $gallerySections = $sections->where('type', SiteSection::TYPE_GALLERY)->values();

        foreach ($gallerySections as $section) {
            $categoryId = $this->nullableIntAttribute($section, 'artwork_category_id');
            if ($categoryId === null || $categories->firstWhere('id', $categoryId) === null) {
                $errors[] = 'Gallery SiteSection '.(int) $section->getKey().' references a missing artwork category.';
            }

            $parentId = $this->nullableIntAttribute($section, 'parent_id');
            if ($parentId === null) {
                continue;
            }

            /** @var SiteSection|null $parent */
            $parent = $gallerySections->firstWhere('id', $parentId);
            if ($parent === null) {
                $errors[] = 'Gallery SiteSection '.(int) $section->getKey()." references missing parent SiteSection {$parentId}.";

                continue;
            }

            if ($parent->getAttribute('parent_id') !== null) {
                $errors[] = 'Gallery SiteSection '.(int) $section->getKey().' exceeds the supported one-level Gallery hierarchy.';
            }
        }

        foreach ($categories as $category) {
            $categoryId = (int) $category->getKey();
            $matches = $gallerySections->where('artwork_category_id', $categoryId)->values();
            if ($matches->count() !== 1) {
                $errors[] = "Artwork category {$categoryId} must map to exactly one Gallery SiteSection; found {$matches->count()}.";
            }
        }

        return [
            'ok' => $errors === [],
            'source' => [
                'artwork_categories' => $categories->count(),
            ],
            'target' => [
                'site_sections' => $sections->count(),
                'singleton_sections' => $sections->whereIn('type', SiteSection::SINGLETON_TYPES)->count(),
                'gallery_sections' => $gallerySections->count(),
            ],
            'errors' => $errors,
        ];
    }

    private function nullableIntAttribute(SiteSection $model, string $attribute): ?int
    {
        $value = $model->getAttribute($attribute);

        return $value === null ? null : (int) $value;
    }
}
