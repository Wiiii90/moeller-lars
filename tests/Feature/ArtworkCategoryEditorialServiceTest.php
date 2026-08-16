<?php

use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\AuditEvent;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function categoryServiceAdmin(): User
{
    return User::factory()->admin()->create();
}

function customCategoryService(string $slug = 'custom-one', string $state = 'hidden'): ArtworkCategory
{
    return ArtworkCategory::create(['name' => 'Custom category', 'slug' => $slug, 'state' => $state, 'position' => 0]);
}

it('denies unauthenticated and non-admin category creation without mutation or audit', function () {
    $initialCount = ArtworkCategory::query()->count();
    expect(fn () => app(ArtworkCategoryEditorialService::class)->create(['name' => 'No', 'slug' => 'no', 'position' => 0]))
        ->toThrow(AuthorizationException::class);

    $this->actingAs(User::factory()->create(), 'web');
    expect(fn () => app(ArtworkCategoryEditorialService::class)->create(['name' => 'No', 'slug' => 'no', 'position' => 0]))
        ->toThrow(AuthorizationException::class);
    expect(ArtworkCategory::query()->count())->toBe($initialCount)->and(AuditEvent::query()->count())->toBe(0);
});

it('creates hidden categories and audits the admin actor', function () {
    $admin = categoryServiceAdmin();
    $this->actingAs($admin, 'web');
    $category = app(ArtworkCategoryEditorialService::class)->create([
        'name' => 'New category', 'slug' => 'new-category', 'position' => 4, 'description' => 'Description', 'state' => 'published',
    ]);

    expect($category->state)->toBe('hidden')
        ->and($category->legacy_id)->toBeNull()
        ->and(AuditEvent::query()->where('action', 'artwork_category.created')->where('entity_id', $category->id)->where('admin_user_id', $admin->id)->count())->toBe(1);
});

it('rejects reserved, invalid, and duplicate category slugs', function (string $slug) {
    $this->actingAs(categoryServiceAdmin(), 'web');
    expect(fn () => app(ArtworkCategoryEditorialService::class)->create(['name' => 'Bad', 'slug' => $slug, 'position' => 0]))
        ->toThrow(ValidationException::class);
})->with(['admin', 'artworks', 'media', 'cv', 'contact', 'blog', 'api', 'up', 'storage', 'sitemap', 'robots', 'Bad Slug']);

it('updates, publishes, and hides categories with the required audits and guards', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $category = customCategoryService();
    app(ArtworkCategoryEditorialService::class)->update($category, ['name' => 'Changed', 'position' => 2, 'description' => 'Changed']);
    app(ArtworkCategoryEditorialService::class)->publish($category);
    app(ArtworkCategoryEditorialService::class)->publish($category);
    app(ArtworkCategoryEditorialService::class)->hide($category);

    expect($category->fresh()->state)->toBe('hidden')
        ->and(AuditEvent::query()->where('entity_id', $category->id)->pluck('action')->all())->toBe([
            'artwork_category.updated', 'artwork_category.published', 'artwork_category.hidden',
        ]);
});

it('blocks hiding a category with published artwork', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $category = customCategoryService('with-work', 'published');
    Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'published-category-work', 'title' => 'Work', 'state' => 'published', 'position' => 0, 'date_precision' => 'unknown']);

    expect(fn () => app(ArtworkCategoryEditorialService::class)->hide($category))->toThrow(ValidationException::class);
    expect($category->fresh()->state)->toBe('published')
        ->and(AuditEvent::query()->where('action', 'artwork_category.hidden')->count())->toBe(0);
});

it('changes custom slugs and collapses category redirect chains', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $category = customCategoryService();
    $service = app(ArtworkCategoryEditorialService::class);
    $service->changeSlug($category, 'custom-two');
    $service->changeSlug($category, 'custom-three');

    expect($category->fresh()->slug)->toBe('custom-three')
        ->and(Redirect::query()->where('source_path', '/custom-one')->value('target_path'))->toBe('/custom-three')
        ->and(Redirect::query()->where('source_path', '/custom-two')->value('target_path'))->toBe('/custom-three')
        ->and(AuditEvent::query()->where('action', 'artwork_category.slug_changed')->count())->toBe(2);
});

it('renames and safely deletes any hidden application category', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $category = customCategoryService('deletable');
    app(ArtworkCategoryEditorialService::class)->changeSlug($category, 'deletable-new');
    app(ArtworkCategoryEditorialService::class)->delete($category);
    expect(ArtworkCategory::query()->whereKey($category->id)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'artwork_category.deleted')->where('entity_id', $category->id)->exists())->toBeTrue()
        ->and(Redirect::query()->where('reason', 'artwork_category_slug_change')->where(fn ($q) => $q->where('source_path', '/deletable')->orWhere('target_path', '/deletable-new'))->exists())->toBeFalse();
});

it('persists generic presentation settings for created and updated categories', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $service = app(ArtworkCategoryEditorialService::class);
    $category = $service->create(['name' => 'Sculptures', 'slug' => 'sculptures', 'position' => 0, 'show_in_navigation' => true, 'show_on_home' => true]);

    expect($category->show_in_navigation)->toBeTrue()->and($category->show_on_home)->toBeTrue();
    $service->update($category, ['name' => 'Works A', 'position' => 1, 'show_in_navigation' => false, 'show_on_home' => false]);
    expect($category->fresh()->show_in_navigation)->toBeFalse()->and($category->fresh()->show_on_home)->toBeFalse();
});

it('rejects publishing a navigation category with a duplicate explicit position', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    ArtworkCategory::create([
        'name' => 'Sculptures', 'slug' => 'sculptures', 'state' => 'published', 'position' => 7,
        'show_in_navigation' => true,
    ]);
    $pending = ArtworkCategory::create([
        'name' => 'Works A', 'slug' => 'works-a', 'state' => 'hidden', 'position' => 7,
        'show_in_navigation' => true,
    ]);

    expect(fn () => app(ArtworkCategoryEditorialService::class)->publish($pending))
        ->toThrow(ValidationException::class);
    expect($pending->fresh()->state)->toBe('hidden');
});

it('reorders every artwork in a category and audits once', function () {
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

it('rejects an artwork from another category during reorder', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $category = customCategoryService('reorder-owned', 'published');
    $other = customCategoryService('reorder-other', 'published');
    $owned = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'owned-reorder', 'title' => 'Owned', 'state' => 'draft', 'position' => 4, 'date_precision' => 'unknown']);
    $foreign = Artwork::create(['artwork_category_id' => $other->id, 'slug' => 'foreign-reorder', 'title' => 'Foreign', 'state' => 'draft', 'position' => 9, 'date_precision' => 'unknown']);

    expect(fn () => app(ArtworkCategoryEditorialService::class)->reorderArtworks($category, [$foreign->id]))
        ->toThrow(ValidationException::class);
    expect($owned->fresh()->position)->toBe(4)->and($foreign->fresh()->position)->toBe(9);
});

it('does not write or audit an already normalized reorder', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $category = customCategoryService('reorder-noop', 'published');
    $first = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'noop-first', 'title' => 'First', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    $second = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'noop-second', 'title' => 'Second', 'state' => 'draft', 'position' => 1, 'date_precision' => 'unknown']);
    $updatedAt = $first->fresh()->updated_at;

    app(ArtworkCategoryEditorialService::class)->reorderArtworks($category, [$first->id, $second->id]);

    expect($first->fresh()->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'artwork_category.gallery_reordered')->count())->toBe(0);
});

it('rolls back all positions when reorder auditing fails', function () {
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
    expect($artwork->fresh()->position)->toBe(5)->and(AuditEvent::query()->count())->toBe(0);
});
