<?php

use App\Domain\Migration\SiteSectionMigrationValidator;
use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('reconciles the invariants owned by the SiteSection migration validator', function (): void {
    $parent = ArtworkCategory::create(['name' => 'Paintings', 'slug' => 'paintings', 'show_on_home' => true]);
    $parentSection = testGallerySection($parent, ['state' => 'published', 'position' => 200]);
    $child = ArtworkCategory::create(['name' => 'Works on paper', 'slug' => 'works-on-paper', 'show_on_home' => false]);
    testGallerySection($child, ['state' => 'published', 'parent_id' => $parentSection->id, 'position' => 10]);

    $result = app(SiteSectionMigrationValidator::class)->validate();

    expect($result['ok'])->toBeTrue()
        ->and($result['errors'])->toBe([])
        ->and($result['source']['artwork_categories'])->toBe(2)
        ->and($result['target']['unique_sections'])->toBe(count(SiteSection::UNIQUE_TYPES))
        ->and($result['target']['gallery_sections'])->toBe(2);
});

it('fails when an artwork category has no Gallery SiteSection', function (): void {
    $category = ArtworkCategory::create(['name' => 'Unmapped', 'slug' => 'unmapped', 'show_on_home' => false]);
    $result = app(SiteSectionMigrationValidator::class)->validate();

    expect($result['ok'])->toBeFalse()
        ->and($result['errors'])->toContain("Artwork category {$category->id} must map to exactly one Gallery SiteSection; found 0.");
});

it('fails when the Gallery hierarchy exceeds one submenu level', function (): void {
    $parent = ArtworkCategory::create(['name' => 'Parent', 'slug' => 'validator-parent', 'show_on_home' => false]);
    $parentSection = testGallerySection($parent, ['state' => 'hidden', 'position' => 200]);
    $child = ArtworkCategory::create(['name' => 'Child', 'slug' => 'validator-child', 'show_on_home' => false]);
    $childSection = testGallerySection($child, ['state' => 'hidden', 'parent_id' => $parentSection->id, 'position' => 10]);
    $grandchild = ArtworkCategory::create(['name' => 'Grandchild', 'slug' => 'validator-grandchild', 'show_on_home' => false]);
    $grandchildSection = testGallerySection($grandchild, ['state' => 'hidden', 'position' => 210]);

    DB::table('site_sections')->where('id', $grandchildSection->id)->update(['parent_id' => $childSection->id]);
    $result = app(SiteSectionMigrationValidator::class)->validate();

    expect($result['ok'])->toBeFalse()
        ->and($result['errors'])->toContain('Gallery SiteSection '.(int) $grandchildSection->id.' exceeds the supported one-level Gallery hierarchy.');
});
