<?php

use App\Domain\Migration\SiteSectionMigrationValidator;
use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('reconciles canonical singleton and gallery site sections', function (): void {
    $parent = ArtworkCategory::create([
        'name' => 'Paintings',
        'slug' => 'paintings',
        'state' => 'published',
        'position' => 10,
        'show_in_navigation' => true,
        'show_on_home' => true,
    ]);
    ArtworkCategory::create([
        'name' => 'Works on paper',
        'slug' => 'works-on-paper',
        'state' => 'published',
        'position' => 20,
        'parent_id' => $parent->id,
        'show_in_navigation' => true,
        'show_on_home' => false,
    ]);

    $result = app(SiteSectionMigrationValidator::class)->validate();

    expect($result['ok'])->toBeTrue()
        ->and($result['errors'])->toBe([])
        ->and($result['source']['artwork_categories'])->toBe(2)
        ->and($result['target']['singleton_sections'])->toBe(count(SiteSection::SINGLETON_TYPES))
        ->and($result['target']['gallery_sections'])->toBe(2)
        ->and($result['target']['site_sections'])->toBe(count(SiteSection::SINGLETON_TYPES) + 2);
});

it('fails when the persisted gallery hierarchy no longer matches artwork categories', function (): void {
    $parent = ArtworkCategory::create([
        'name' => 'Paintings',
        'slug' => 'paintings',
        'state' => 'published',
        'position' => 10,
        'show_in_navigation' => true,
        'show_on_home' => true,
    ]);
    $child = ArtworkCategory::create([
        'name' => 'Works on paper',
        'slug' => 'works-on-paper',
        'state' => 'published',
        'position' => 20,
        'parent_id' => $parent->id,
        'show_in_navigation' => true,
        'show_on_home' => false,
    ]);

    DB::table('site_sections')
        ->where('artwork_category_id', $child->id)
        ->update(['parent_id' => null]);

    $result = app(SiteSectionMigrationValidator::class)->validate();

    expect($result['ok'])->toBeFalse()
        ->and($result['errors'])->toHaveCount(1)
        ->and($result['errors'][0])->toContain('artwork-category hierarchy');
});
