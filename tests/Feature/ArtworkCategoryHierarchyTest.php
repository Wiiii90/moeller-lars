<?php

use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Domain\Content\PublicNavigationService;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

it('creates one-level Gallery hierarchy while keeping content and placement ownership separate', function (): void {
    $service = app(ArtworkCategoryEditorialService::class);
    $parent = $service->create(['name' => 'Paintings', 'slug' => 'paintings']);
    $parentSection = $parent->siteSection()->firstOrFail();
    $child = $service->create([
        'name' => 'Works on paper',
        'slug' => 'works-on-paper',
        'parent_section_id' => $parentSection->id,
    ]);
    $childSection = $child->siteSection()->firstOrFail();

    expect($parent->getAttribute('parent_id'))->toBeNull()
        ->and($child->getAttribute('parent_id'))->toBeNull()
        ->and($parentSection->parent_id)->toBeNull()
        ->and((int) $childSection->parent_id)->toBe($parentSection->id)
        ->and($childSection->slug)->toBe('works-on-paper');
});

it('publishes and hides Gallery placement through SiteSectionEditorialService only', function (): void {
    $category = app(ArtworkCategoryEditorialService::class)->create(['name' => 'Sculptures', 'slug' => 'sculptures']);
    $section = $category->siteSection()->firstOrFail();
    $legacyState = $category->fresh()->getRawOriginal('state');

    $published = app(SiteSectionEditorialService::class)->updateGallery($section, 'published', true, null);
    expect($published->state)->toBe('published')
        ->and($published->show_in_navigation)->toBeTrue()
        ->and($category->fresh()->getRawOriginal('state'))->toBe($legacyState);

    $hidden = app(SiteSectionEditorialService::class)->updateGallery($published, 'hidden', false, null);
    expect($hidden->state)->toBe('hidden')
        ->and($hidden->show_in_navigation)->toBeFalse()
        ->and($category->fresh()->getRawOriginal('state'))->toBe($legacyState);
});

it('rejects hiding a visible parent while a visible submenu Gallery still depends on it', function (): void {
    $parent = ArtworkCategory::create(['name' => 'Parent', 'slug' => 'parent-gallery', 'show_on_home' => false]);
    $parentSection = testGallerySection($parent, ['state' => 'published', 'show_in_navigation' => true]);
    $child = ArtworkCategory::create(['name' => 'Child', 'slug' => 'child-gallery', 'show_on_home' => false]);
    testGallerySection($child, [
        'state' => 'published',
        'show_in_navigation' => true,
        'parent_id' => $parentSection->id,
        'position' => 10,
    ]);

    expect(fn () => app(SiteSectionEditorialService::class)->updateGallery($parentSection, 'hidden', false, null))
        ->toThrow(ValidationException::class);
    expect($parentSection->fresh()->state)->toBe('published');
});

it('reorders top-level and child Galleries through SiteSectionOrderService without mutating category placement columns', function (): void {
    $first = ArtworkCategory::create(['name' => 'First', 'slug' => 'first-gallery', 'show_on_home' => false]);
    $second = ArtworkCategory::create(['name' => 'Second', 'slug' => 'second-gallery', 'show_on_home' => false]);
    $firstSection = testGallerySection($first, ['state' => 'hidden', 'position' => 200]);
    $secondSection = testGallerySection($second, ['state' => 'hidden', 'position' => 210]);

    expect(app(SiteSectionOrderService::class)->move($secondSection, 'up'))->toBeTrue()
        ->and((int) $secondSection->fresh()->position)->toBe(200)
        ->and((int) $firstSection->fresh()->position)->toBe(210)
        ->and((int) $second->fresh()->getAttribute('position'))->toBe(0)
        ->and((int) $first->fresh()->getAttribute('position'))->toBe(0);

    $childA = ArtworkCategory::create(['name' => 'A', 'slug' => 'child-a', 'show_on_home' => false]);
    $childB = ArtworkCategory::create(['name' => 'B', 'slug' => 'child-b', 'show_on_home' => false]);
    $childASection = testGallerySection($childA, ['state' => 'hidden', 'parent_id' => $secondSection->id, 'position' => 10]);
    $childBSection = testGallerySection($childB, ['state' => 'hidden', 'parent_id' => $secondSection->id, 'position' => 20]);

    expect(app(SiteSectionOrderService::class)->move($childBSection, 'up'))->toBeTrue()
        ->and((int) $childBSection->fresh()->position)->toBe(10)
        ->and((int) $childASection->fresh()->position)->toBe(20);
});

it('builds public navigation hierarchy exclusively from canonical SiteSections', function (): void {
    $parent = ArtworkCategory::create(['name' => 'Paintings', 'slug' => 'nav-paintings', 'show_on_home' => false]);
    $parentSection = testGallerySection($parent, [
        'navigation_label' => 'PAINTINGS',
        'state' => 'published',
        'show_in_navigation' => true,
        'position' => 200,
    ]);
    $child = ArtworkCategory::create(['name' => 'Paper', 'slug' => 'nav-paper', 'show_on_home' => false]);
    testGallerySection($child, [
        'navigation_label' => 'PAPER',
        'state' => 'published',
        'show_in_navigation' => true,
        'parent_id' => $parentSection->id,
        'position' => 10,
    ]);

    $items = app(PublicNavigationService::class)->items();
    $gallery = $items->firstWhere('label', 'PAINTINGS');

    expect($gallery)->not->toBeNull()
        ->and($gallery['children'])->toHaveCount(1)
        ->and($gallery['children'][0]['label'])->toBe('PAPER');
});

it('rejects a second hierarchy level', function (): void {
    $parent = ArtworkCategory::create(['name' => 'Parent', 'slug' => 'level-parent', 'show_on_home' => false]);
    $parentSection = testGallerySection($parent, ['state' => 'hidden']);
    $child = ArtworkCategory::create(['name' => 'Child', 'slug' => 'level-child', 'show_on_home' => false]);
    $childSection = testGallerySection($child, ['state' => 'hidden', 'parent_id' => $parentSection->id, 'position' => 10]);
    $grandchild = ArtworkCategory::create(['name' => 'Grandchild', 'slug' => 'level-grandchild', 'show_on_home' => false]);
    $grandchildSection = testGallerySection($grandchild, ['state' => 'hidden']);

    expect(fn () => app(SiteSectionEditorialService::class)->updateGallery($grandchildSection, 'hidden', false, $childSection->id))
        ->toThrow(ValidationException::class);
});
