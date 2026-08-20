<?php

use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\AuditEvent;
use App\Models\Redirect;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function categoryServiceAdmin(): User
{
    return User::factory()->admin()->create();
}

function customCategoryService(string $slug = 'custom-one', string $sectionState = 'hidden'): ArtworkCategory
{
    $category = ArtworkCategory::create([
        'name' => 'Custom category',
        'slug' => $slug,
        'show_on_home' => false,
    ]);
    testGallerySection($category, [
        'state' => $sectionState,
        'show_in_navigation' => false,
    ]);

    return $category;
}

it('denies unauthenticated and non-admin Gallery creation without mutation or audit', function () {
    $initialCount = ArtworkCategory::query()->count();
    expect(fn () => app(ArtworkCategoryEditorialService::class)->create(['name' => 'No', 'slug' => 'no']))
        ->toThrow(AuthorizationException::class);

    $this->actingAs(User::factory()->create(), 'web');
    expect(fn () => app(ArtworkCategoryEditorialService::class)->create(['name' => 'No', 'slug' => 'no']))
        ->toThrow(AuthorizationException::class);
    expect(ArtworkCategory::query()->count())->toBe($initialCount)
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('creates Gallery content with one hidden canonical SiteSection and audits the admin actor', function () {
    $admin = categoryServiceAdmin();
    $this->actingAs($admin, 'web');

    $category = app(ArtworkCategoryEditorialService::class)->create([
        'name' => 'New category',
        'slug' => 'new-category',
        'description' => 'Description',
        'show_on_home' => true,
    ]);
    $section = $category->siteSection()->firstOrFail();

    expect($category->name)->toBe('New category')
        ->and($category->description)->toBe('Description')
        ->and($category->show_on_home)->toBeTrue()
        ->and($category->legacy_id)->toBeNull()
        ->and($section->type)->toBe(SiteSection::TYPE_GALLERY)
        ->and($section->state)->toBe('hidden')
        ->and($section->show_in_navigation)->toBeFalse()
        ->and($section->slug)->toBe('new-category')
        ->and(AuditEvent::query()->where('action', 'artwork_category.created')->where('entity_id', $category->id)->where('admin_user_id', $admin->id)->count())->toBe(1);
});

it('rejects reserved, invalid, and duplicate Gallery slugs', function (string $slug) {
    $this->actingAs(categoryServiceAdmin(), 'web');
    expect(fn () => app(ArtworkCategoryEditorialService::class)->create(['name' => 'Bad', 'slug' => $slug]))
        ->toThrow(ValidationException::class);
})->with(['admin', 'artworks', 'media', 'cv', 'contact', 'blog', 'api', 'up', 'storage', 'sitemap', 'robots', 'Bad Slug']);

it('updates Gallery content without mutating canonical placement', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $category = customCategoryService('content-update', 'published');
    $section = $category->siteSection()->firstOrFail();
    $section->update(['show_in_navigation' => true, 'navigation_label' => 'Custom navigation']);
    $position = (int) $section->position;

    app(ArtworkCategoryEditorialService::class)->update($category, [
        'name' => 'Changed',
        'description' => 'Changed description',
        'show_on_home' => true,
    ]);

    expect($category->fresh()->name)->toBe('Changed')
        ->and($category->fresh()->description)->toBe('Changed description')
        ->and($category->fresh()->show_on_home)->toBeTrue()
        ->and($section->fresh()->title)->toBe('Changed')
        ->and($section->fresh()->navigation_label)->toBe('Custom navigation')
        ->and($section->fresh()->state)->toBe('published')
        ->and((int) $section->fresh()->position)->toBe($position)
        ->and(AuditEvent::query()->where('action', 'artwork_category.updated')->where('entity_id', $category->id)->count())->toBe(1);
});

it('changes custom slugs, updates the canonical SiteSection and collapses redirect chains', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $category = customCategoryService();
    $section = $category->siteSection()->firstOrFail();
    $service = app(ArtworkCategoryEditorialService::class);

    $service->changeSlug($category, 'custom-two');
    $service->changeSlug($category, 'custom-three');

    expect($category->fresh()->slug)->toBe('custom-three')
        ->and($section->fresh()->slug)->toBe('custom-three')
        ->and(Redirect::query()->where('source_path', '/custom-one')->value('target_path'))->toBe('/custom-three')
        ->and(Redirect::query()->where('source_path', '/custom-two')->value('target_path'))->toBe('/custom-three')
        ->and(AuditEvent::query()->where('action', 'artwork_category.slug_changed')->count())->toBe(2);
});

it('safely deletes Gallery content only while its canonical SiteSection is hidden and empty', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $category = customCategoryService('deletable');
    app(ArtworkCategoryEditorialService::class)->changeSlug($category, 'deletable-new');
    app(ArtworkCategoryEditorialService::class)->delete($category);

    expect(ArtworkCategory::query()->whereKey($category->id)->exists())->toBeFalse()
        ->and(SiteSection::query()->where('artwork_category_id', $category->id)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'artwork_category.deleted')->where('entity_id', $category->id)->exists())->toBeTrue()
        ->and(Redirect::query()->where('reason', 'artwork_category_slug_change')->where(fn ($q) => $q->where('source_path', '/deletable')->orWhere('target_path', '/deletable-new'))->exists())->toBeFalse();
});

it('rejects deleting a public, populated, or parent Gallery', function (string $case) {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $category = customCategoryService('delete-'.$case, $case === 'public' ? 'published' : 'hidden');
    $section = $category->siteSection()->firstOrFail();

    if ($case === 'populated') {
        Artwork::create([
            'artwork_category_id' => $category->id,
            'slug' => 'delete-blocking-work',
            'title' => 'Work',
            'state' => 'draft',
            'position' => 0,
            'date_precision' => 'unknown',
        ]);
    }
    if ($case === 'parent') {
        $child = ArtworkCategory::create(['name' => 'Child', 'slug' => 'delete-child', 'show_on_home' => false]);
        testGallerySection($child, ['state' => 'hidden', 'parent_id' => $section->id]);
    }

    expect(fn () => app(ArtworkCategoryEditorialService::class)->delete($category))
        ->toThrow(ValidationException::class);
    expect(ArtworkCategory::query()->whereKey($category->id)->exists())->toBeTrue();
})->with(['public', 'populated', 'parent']);

it('persists homepage eligibility independently from SiteSection placement', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $service = app(ArtworkCategoryEditorialService::class);
    $category = $service->create([
        'name' => 'Sculptures',
        'slug' => 'sculptures',
        'show_on_home' => true,
    ]);
    $section = $category->siteSection()->firstOrFail();

    expect($category->show_on_home)->toBeTrue()
        ->and($section->state)->toBe('hidden')
        ->and($section->show_in_navigation)->toBeFalse();

    $service->update($category, [
        'name' => 'Works A',
        'description' => null,
        'show_on_home' => false,
    ]);

    expect($category->fresh()->show_on_home)->toBeFalse()
        ->and($section->fresh()->state)->toBe('hidden')
        ->and($section->fresh()->show_in_navigation)->toBeFalse();
});

it('reorders every artwork in a Gallery and audits once', function () {
    $admin = categoryServiceAdmin();
    $this->actingAs($admin, 'web');
    $category = customCategoryService('reorderable', 'published');
    $first = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'reorder-first', 'title' => 'First', 'state' => 'published', 'position' => 10, 'date_precision' => 'unknown']);
    $second = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'reorder-second', 'title' => 'Second', 'state' => 'draft', 'position' => 20, 'date_precision' => 'unknown']);
    $third = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'reorder-third', 'title' => 'Third', 'state' => 'archived', 'position' => 30, 'date_precision' => 'unknown']);

    app(ArtworkCategoryEditorialService::class)->reorderArtworks($category, [$third->id, $first->id, $second->id]);

    expect($third->fresh()->position)->toBe(0)
        ->and($first->fresh()->position)->toBe(1)
        ->and($second->fresh()->position)->toBe(2)
        ->and(AuditEvent::query()->where('action', 'artwork_category.gallery_reordered')->where('entity_id', $category->id)->where('admin_user_id', $admin->id)->count())->toBe(1);
});

it('rejects invalid artwork reorder sets without mutation', function (array $ids) {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $category = customCategoryService('invalid-reorder', 'published');
    $first = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'invalid-first', 'title' => 'First', 'state' => 'draft', 'position' => 4, 'date_precision' => 'unknown']);
    $second = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'invalid-second', 'title' => 'Second', 'state' => 'draft', 'position' => 8, 'date_precision' => 'unknown']);

    expect(fn () => app(ArtworkCategoryEditorialService::class)->reorderArtworks($category, $ids))
        ->toThrow(ValidationException::class);
    expect($first->fresh()->position)->toBe(4)
        ->and($second->fresh()->position)->toBe(8)
        ->and(AuditEvent::query()->where('action', 'artwork_category.gallery_reordered')->count())->toBe(0);
})->with([
    'missing' => [[1]],
    'duplicate' => [[1, 1]],
    'non-positive' => [[0, 2]],
    'non-int' => [['1', 2]],
]);

it('rejects an artwork from another Gallery during reorder', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $category = customCategoryService('reorder-owned', 'published');
    $other = customCategoryService('reorder-other', 'published');
    $owned = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'owned-reorder', 'title' => 'Owned', 'state' => 'draft', 'position' => 4, 'date_precision' => 'unknown']);
    $foreign = Artwork::create(['artwork_category_id' => $other->id, 'slug' => 'foreign-reorder', 'title' => 'Foreign', 'state' => 'draft', 'position' => 9, 'date_precision' => 'unknown']);

    expect(fn () => app(ArtworkCategoryEditorialService::class)->reorderArtworks($category, [$foreign->id]))
        ->toThrow(ValidationException::class);
    expect($owned->fresh()->position)->toBe(4)
        ->and($foreign->fresh()->position)->toBe(9);
});

it('does not write or audit an already normalized artwork reorder', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $category = customCategoryService('reorder-noop', 'published');
    $first = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'noop-first', 'title' => 'First', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    $second = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'noop-second', 'title' => 'Second', 'state' => 'draft', 'position' => 1, 'date_precision' => 'unknown']);
    $updatedAt = $first->fresh()->updated_at;

    app(ArtworkCategoryEditorialService::class)->reorderArtworks($category, [$first->id, $second->id]);

    expect($first->fresh()->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'artwork_category.gallery_reordered')->count())->toBe(0);
});

it('rolls back all artwork positions when reorder auditing fails', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $category = customCategoryService('reorder-rollback', 'published');
    $first = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'rollback-first', 'title' => 'First', 'state' => 'draft', 'position' => 10, 'date_precision' => 'unknown']);
    $second = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'rollback-second', 'title' => 'Second', 'state' => 'draft', 'position' => 20, 'date_precision' => 'unknown']);
    AuditEvent::creating(function (AuditEvent $event): void {
        if ($event->getAttribute('action') === 'artwork_category.gallery_reordered') {
            throw new RuntimeException('reorder audit failed');
        }
    });

    try {
        expect(fn () => app(ArtworkCategoryEditorialService::class)->reorderArtworks($category, [$second->id, $first->id]))
            ->toThrow(RuntimeException::class, 'reorder audit failed');
    } finally {
        AuditEvent::flushEventListeners();
    }

    expect($first->fresh()->position)->toBe(10)
        ->and($second->fresh()->position)->toBe(20)
        ->and(AuditEvent::query()->where('action', 'artwork_category.gallery_reordered')->count())->toBe(0);
});

it('requires an admin for artwork reorder', function () {
    $category = customCategoryService('reorder-auth', 'published');
    $artwork = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'auth-reorder', 'title' => 'Auth', 'state' => 'draft', 'position' => 5, 'date_precision' => 'unknown']);

    expect(fn () => app(ArtworkCategoryEditorialService::class)->reorderArtworks($category, [$artwork->id]))
        ->toThrow(AuthorizationException::class);
    $this->actingAs(User::factory()->create(), 'web');
    expect(fn () => app(ArtworkCategoryEditorialService::class)->reorderArtworks($category, [$artwork->id]))
        ->toThrow(AuthorizationException::class);
    expect($artwork->fresh()->position)->toBe(5)
        ->and(AuditEvent::query()->count())->toBe(0);
});
