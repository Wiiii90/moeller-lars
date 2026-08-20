<?php

namespace App\Domain\Migration;

use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use Illuminate\Support\Collection;

final class SiteSectionMigrationValidator
{
    /**
     * Validate the canonical SiteSection projection without mutating migrated data.
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
        }

        foreach ($categories as $category) {
            $categoryId = (int) $category->getKey();
            $matches = $gallerySections->where('artwork_category_id', $categoryId)->values();
            if ($matches->count() !== 1) {
                $errors[] = "Artwork category {$categoryId} must map to exactly one Gallery SiteSection; found {$matches->count()}.";
                continue;
            }

            $section = $matches->first();
            if (($section instanceof SiteSection) === false) {
                $errors[] = "Artwork category {$categoryId} does not have a readable Gallery SiteSection mapping.";
                continue;
            }

            $expectedParentSectionId = null;
            $categoryParentId = $this->nullableIntAttribute($category, 'parent_id');

            if ($categoryParentId !== null) {
                $parentMatches = $gallerySections->where('artwork_category_id', $categoryParentId)->values();
                if ($parentMatches->count() !== 1) {
                    $errors[] = "Artwork category {$categoryId} has parent {$categoryParentId}, but that parent does not map to exactly one Gallery SiteSection.";
                    continue;
                }

                $parentSection = $parentMatches->first();
                if (($parentSection instanceof SiteSection) === false) {
                    $errors[] = "Artwork category {$categoryId} has parent {$categoryParentId}, but that parent mapping is unreadable.";
                    continue;
                }

                $expectedParentSectionId = (int) $parentSection->getKey();
            }

            $actualParentSectionId = $this->nullableIntAttribute($section, 'parent_id');
            if ($actualParentSectionId !== $expectedParentSectionId) {
                $actual = $actualParentSectionId === null ? 'null' : (string) $actualParentSectionId;
                $expected = $expectedParentSectionId === null ? 'null' : (string) $expectedParentSectionId;
                $errors[] = 'Gallery SiteSection '.(int) $section->getKey()." has parent_id {$actual}; expected {$expected} from artwork-category hierarchy.";
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

    private function nullableIntAttribute(ArtworkCategory|SiteSection $model, string $attribute): ?int
    {
        $value = $model->getAttribute($attribute);

        return $value === null ? null : (int) $value;
    }
}
