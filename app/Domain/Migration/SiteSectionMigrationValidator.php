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
        $categories = ArtworkCategory::query()->orderBy('id')->get();
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
            $categoryId = $section->artwork_category_id;
            if ($categoryId === null || $categories->firstWhere('id', $categoryId) === null) {
                $errors[] = "Gallery SiteSection {$section->id} references a missing artwork category.";
            }
        }

        foreach ($categories as $category) {
            $matches = $gallerySections->where('artwork_category_id', $category->id)->values();
            if ($matches->count() !== 1) {
                $errors[] = "Artwork category {$category->id} must map to exactly one Gallery SiteSection; found {$matches->count()}.";
                continue;
            }

            /** @var SiteSection $section */
            $section = $matches->first();
            $expectedParentSectionId = null;

            if ($category->parent_id !== null) {
                $parentMatches = $gallerySections->where('artwork_category_id', $category->parent_id)->values();
                if ($parentMatches->count() !== 1) {
                    $errors[] = "Artwork category {$category->id} has parent {$category->parent_id}, but that parent does not map to exactly one Gallery SiteSection.";
                    continue;
                }

                $expectedParentSectionId = $parentMatches->first()->id;
            }

            if ($section->parent_id !== $expectedParentSectionId) {
                $actual = $section->parent_id === null ? 'null' : (string) $section->parent_id;
                $expected = $expectedParentSectionId === null ? 'null' : (string) $expectedParentSectionId;
                $errors[] = "Gallery SiteSection {$section->id} has parent_id {$actual}; expected {$expected} from artwork-category hierarchy.";
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
}
