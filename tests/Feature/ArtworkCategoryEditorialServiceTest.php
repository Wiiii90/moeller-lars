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
})->with(['admin', 'artworks', 'media', 'cv', 'contact', 'blog', 'api', 'up', 'storage', 'sitemap', 'robots', 'index', 'Bad Slug']);

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

it('does not change legacy stable slugs and safely deletes custom categories', function () {
    $this->actingAs(categoryServiceAdmin(), 'web');
    $legacy = ArtworkCategory::query()->where('slug', 'paintings')->firstOrFail();
    expect(fn () => app(ArtworkCategoryEditorialService::class)->changeSlug($legacy, 'paintings-new'))->toThrow(ValidationException::class);

    $category = customCategoryService('deletable');
    app(ArtworkCategoryEditorialService::class)->changeSlug($category, 'deletable-new');
    app(ArtworkCategoryEditorialService::class)->delete($category);
    expect(ArtworkCategory::query()->whereKey($category->id)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'artwork_category.deleted')->where('entity_id', $category->id)->exists())->toBeTrue()
        ->and(Redirect::query()->where('reason', 'artwork_category_slug_change')->where(fn ($q) => $q->where('source_path', '/deletable')->orWhere('target_path', '/deletable-new'))->exists())->toBeFalse();
});
