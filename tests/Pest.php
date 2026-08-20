<?php

use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/** @param array<string, mixed> $overrides */
function testGallerySection(ArtworkCategory $category, array $overrides = []): SiteSection
{
    $parentId = $overrides['parent_id'] ?? null;
    if (! array_key_exists('position', $overrides)) {
        /** @var Builder<SiteSection> $siblings */
        $siblings = SiteSection::query();
        $parentId === null ? $siblings->whereNull('parent_id') : $siblings->where('parent_id', $parentId);
        if ($parentId === null) {
            $siblings->where('type', '<>', SiteSection::TYPE_HOME);
        }
        $overrides['position'] = ((int) ($siblings->max('position') ?? 0)) + 10;
    }

    return SiteSection::query()->create(array_merge([
        'type' => SiteSection::TYPE_GALLERY,
        'title' => (string) $category->getAttribute('name'),
        'navigation_label' => (string) $category->getAttribute('name'),
        'slug' => (string) $category->getAttribute('slug'),
        'state' => 'published',
        'show_in_navigation' => false,
        'parent_id' => $parentId,
        'artwork_category_id' => (int) $category->getKey(),
    ], $overrides));
}

/** @param array<string, mixed> $attributes */
function testUniqueSection(string $type, array $attributes): SiteSection
{
    /** @var SiteSection $section */
    $section = SiteSection::query()->where('type', $type)->sole();
    $section->fill($attributes);
    $section->save();

    return $section->fresh();
}
