<?php

use App\Domain\Content\PublicNavigationService;
use App\Models\ArtworkCategory;
use App\Models\BlogSetting;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('creates exactly one canonical SiteSection for every singleton public surface', function (): void {
    expect(SiteSection::query()->where('type', SiteSection::TYPE_HOME)->count())->toBe(1)
        ->and(SiteSection::query()->where('type', SiteSection::TYPE_VITA)->count())->toBe(1)
        ->and(SiteSection::query()->where('type', SiteSection::TYPE_BLOG)->count())->toBe(1)
        ->and(SiteSection::query()->where('type', SiteSection::TYPE_EXHIBITIONS)->count())->toBe(1);
});

it('does not mirror direct legacy Gallery placement fields into the canonical SiteSection', function (): void {
    $category = ArtworkCategory::create([
        'name' => 'Paintings',
        'slug' => 'paintings',
        'show_on_home' => false,
    ]);
    $section = testGallerySection($category, [
        'navigation_label' => 'PAINTINGS',
        'state' => 'published',
        'show_in_navigation' => true,
        'position' => 200,
    ]);

    $category->forceFill([
        'state' => 'hidden',
        'position' => 999,
        'show_in_navigation' => false,
        'parent_id' => null,
    ])->save();

    expect($section->fresh()->state)->toBe('published')
        ->and($section->fresh()->show_in_navigation)->toBeTrue()
        ->and((int) $section->fresh()->position)->toBe(200)
        ->and($section->fresh()->navigation_label)->toBe('PAINTINGS');
});

it('does not synchronize legacy singleton settings after the SiteSection cutover', function (): void {
    $vita = SiteSection::query()->where('type', SiteSection::TYPE_VITA)->firstOrFail();
    $blogSection = SiteSection::query()->where('type', SiteSection::TYPE_BLOG)->firstOrFail();
    $vitaState = $vita->state;
    $vitaPosition = (int) $vita->position;
    $blogState = $blogSection->state;
    $blogPosition = (int) $blogSection->position;

    PublicContentSetting::query()->findOrFail(1)->forceFill([
        'cv_enabled' => ! (bool) PublicContentSetting::query()->findOrFail(1)->getRawOriginal('cv_enabled'),
        'cv_navigation_label' => 'LEGACY VITA',
        'cv_navigation_position' => 777,
    ])->save();
    BlogSetting::query()->findOrFail(1)->forceFill([
        'public_enabled' => ! (bool) BlogSetting::query()->findOrFail(1)->getRawOriginal('public_enabled'),
        'navigation_label' => 'LEGACY BLOG',
        'navigation_position' => 888,
    ])->save();

    expect($vita->fresh()->state)->toBe($vitaState)
        ->and((int) $vita->fresh()->position)->toBe($vitaPosition)
        ->and($vita->fresh()->navigation_label)->not->toBe('LEGACY VITA')
        ->and($blogSection->fresh()->state)->toBe($blogState)
        ->and((int) $blogSection->fresh()->position)->toBe($blogPosition)
        ->and($blogSection->fresh()->navigation_label)->not->toBe('LEGACY BLOG');
});

it('builds public navigation only from visible canonical sections', function (): void {
    $gallery = ArtworkCategory::create([
        'name' => 'Painting',
        'slug' => 'painting',
        'show_on_home' => false,
    ]);
    $gallerySection = testGallerySection($gallery, [
        'navigation_label' => 'Painting',
        'state' => 'published',
        'show_in_navigation' => true,
        'position' => 200,
    ]);
    testSingletonSection(SiteSection::TYPE_VITA, [
        'navigation_label' => 'Vita',
        'state' => 'published',
        'show_in_navigation' => true,
        'position' => 210,
    ]);

    $items = app(PublicNavigationService::class)->items();
    expect($items->pluck('label')->all())->toContain('Painting', 'Vita');

    $gallerySection->update([
        'state' => 'hidden',
        'show_in_navigation' => false,
    ]);

    expect(app(PublicNavigationService::class)->items()->pluck('label')->all())->not->toContain('Painting');
});

it('uses the SiteSection as the public availability gate regardless of legacy settings', function (): void {
    PublicContentSetting::query()->findOrFail(1)->forceFill(['cv_enabled' => false])->save();
    $vita = testSingletonSection(SiteSection::TYPE_VITA, [
        'navigation_label' => 'Vita',
        'state' => 'published',
        'show_in_navigation' => true,
        'position' => 200,
    ]);

    $this->get('/cv')->assertSuccessful();

    $vita->update([
        'state' => 'hidden',
        'show_in_navigation' => false,
    ]);
    PublicContentSetting::query()->findOrFail(1)->forceFill(['cv_enabled' => true])->save();

    $this->get('/cv')->assertNotFound();
});

it('rejects hierarchy on non-Gallery section types', function (): void {
    $parent = SiteSection::query()->where('type', SiteSection::TYPE_VITA)->firstOrFail();
    $blog = SiteSection::query()->where('type', SiteSection::TYPE_BLOG)->firstOrFail();

    expect(fn () => $blog->update(['parent_id' => $parent->id]))
        ->toThrow(ValidationException::class, 'Only Gallery sections may have a parent section.');
});
