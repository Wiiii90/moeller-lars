<?php

use App\Domain\Content\PublicNavigationService;
use App\Models\ArtworkCategory;
use App\Models\BlogSetting;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('backfills the typed singleton site sections from existing settings', function (): void {
    $public = PublicContentSetting::query()->findOrFail(1);
    $blog = BlogSetting::query()->findOrFail(1);

    expect(SiteSection::query()->where('type', SiteSection::TYPE_HOME)->count())->toBe(1)
        ->and(SiteSection::query()->where('type', SiteSection::TYPE_VITA)->count())->toBe(1)
        ->and(SiteSection::query()->where('type', SiteSection::TYPE_BLOG)->count())->toBe(1)
        ->and(SiteSection::query()->where('type', SiteSection::TYPE_EXHIBITIONS)->count())->toBe(1);

    $vita = SiteSection::query()->where('type', SiteSection::TYPE_VITA)->firstOrFail();
    $blogSection = SiteSection::query()->where('type', SiteSection::TYPE_BLOG)->firstOrFail();

    expect($vita->navigation_label)->toBe($public->cv_navigation_label)
        ->and($vita->position)->toBe($public->cv_navigation_position)
        ->and($vita->state)->toBe($public->cv_enabled ? 'published' : 'hidden')
        ->and($blogSection->navigation_label)->toBe($blog->navigation_label)
        ->and($blogSection->position)->toBe($blog->navigation_position)
        ->and($blogSection->state)->toBe($blog->public_enabled ? 'published' : 'hidden');
});

it('mirrors gallery category lifecycle and hierarchy into site sections', function (): void {
    $parent = ArtworkCategory::create([
        'name' => 'Paintings',
        'slug' => 'paintings',
        'state' => 'published',
        'position' => 10,
        'show_in_navigation' => true,
        'show_on_home' => false,
    ]);
    $child = ArtworkCategory::create([
        'name' => 'Large works',
        'slug' => 'large-works',
        'state' => 'published',
        'position' => 10,
        'parent_id' => $parent->id,
        'show_in_navigation' => true,
        'show_on_home' => false,
    ]);

    $parentSection = SiteSection::query()->where('artwork_category_id', $parent->id)->firstOrFail();
    $childSection = SiteSection::query()->where('artwork_category_id', $child->id)->firstOrFail();

    expect($parentSection->type)->toBe(SiteSection::TYPE_GALLERY)
        ->and($parentSection->slug)->toBe('paintings')
        ->and($childSection->parent_id)->toBe($parentSection->id)
        ->and($childSection->slug)->toBe('large-works');

    $child->update([
        'name' => 'Works on paper',
        'slug' => 'works-on-paper',
        'position' => 20,
    ]);

    expect($childSection->fresh()->title)->toBe('Works on paper')
        ->and($childSection->fresh()->slug)->toBe('works-on-paper')
        ->and($childSection->fresh()->position)->toBe(20);
});

it('synchronizes legacy singleton editors during the cutover', function (): void {
    $public = PublicContentSetting::query()->findOrFail(1);
    $public->update([
        'cv_enabled' => true,
        'cv_navigation_label' => 'VITA',
        'cv_navigation_position' => 40,
    ]);

    $blog = BlogSetting::query()->findOrFail(1);
    $blog->update([
        'public_enabled' => true,
        'navigation_label' => 'JOURNAL',
        'navigation_position' => 50,
    ]);

    $vita = SiteSection::query()->where('type', SiteSection::TYPE_VITA)->firstOrFail();
    $blogSection = SiteSection::query()->where('type', SiteSection::TYPE_BLOG)->firstOrFail();

    expect($vita->state)->toBe('published')
        ->and($vita->show_in_navigation)->toBeTrue()
        ->and($vita->navigation_label)->toBe('VITA')
        ->and($vita->position)->toBe(40)
        ->and($blogSection->state)->toBe('published')
        ->and($blogSection->show_in_navigation)->toBeTrue()
        ->and($blogSection->navigation_label)->toBe('JOURNAL')
        ->and($blogSection->position)->toBe(50);
});

it('builds public navigation only from visible canonical sections', function (): void {
    $gallery = ArtworkCategory::create([
        'name' => 'Painting',
        'slug' => 'painting',
        'state' => 'published',
        'position' => 10,
        'show_in_navigation' => true,
        'show_on_home' => false,
    ]);

    PublicContentSetting::query()->findOrFail(1)->update([
        'cv_enabled' => true,
        'cv_navigation_label' => 'Vita',
        'cv_navigation_position' => 20,
    ]);

    $items = app(PublicNavigationService::class)->items();

    expect($items->pluck('label')->all())->toBe(['Painting', 'Vita']);

    SiteSection::query()->where('artwork_category_id', $gallery->id)->firstOrFail()->update([
        'state' => 'hidden',
        'show_in_navigation' => false,
    ]);

    expect(app(PublicNavigationService::class)->items()->pluck('label')->all())->toBe(['Vita']);
});

it('uses the site section as the public availability gate', function (): void {
    $settings = PublicContentSetting::query()->findOrFail(1);
    $settings->update([
        'cv_enabled' => true,
        'cv_navigation_label' => 'Vita',
        'cv_navigation_position' => 20,
    ]);

    $this->get('/cv')->assertSuccessful();

    SiteSection::query()->where('type', SiteSection::TYPE_VITA)->firstOrFail()->update([
        'state' => 'hidden',
        'show_in_navigation' => false,
    ]);

    expect($settings->fresh()->cv_enabled)->toBeTrue();
    $this->get('/cv')->assertNotFound();
});

it('rejects hierarchy on non-gallery section types', function (): void {
    $parent = SiteSection::query()->where('type', SiteSection::TYPE_VITA)->firstOrFail();
    $blog = SiteSection::query()->where('type', SiteSection::TYPE_BLOG)->firstOrFail();

    expect(fn () => $blog->update(['parent_id' => $parent->id]))
        ->toThrow(ValidationException::class, 'Only Gallery sections may have a parent section.');
});
