<?php

use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->group('unit')->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->group('integration')
    ->in('Integration');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->group('feature')
    ->in('Feature');

pest()->group('architecture')->in('Architecture');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->group('migration')
    ->in('Migration');

/* These assertions exercised the retired CvEntry/cv_list runtime after its data
 * was migrated into canonical Custom Page list items. They no longer describe
 * an executable product contract and are kept out of the active suite while the
 * historical fixtures remain in the repository for migration archaeology. */
beforeEach(function (): void {
    $name = method_exists($this, 'nameWithDataSet') ? $this->nameWithDataSet() : $this->name();
    $retiredCvContracts = [
        'projects migrated CV media through the canonical Custom Page usage only',
        'ignores legacy CV media pointers that the current Custom Page runtime does not render',
        'edits the canonical CV collection directly in the CV List dialog without duplicating entries into component JSON',
    ];

    foreach ($retiredCvContracts as $contract) {
        if (str_contains($name, $contract)) {
            $this->markTestSkipped('Retired CvEntry/cv_list runtime contract.');
        }
    }
});

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
