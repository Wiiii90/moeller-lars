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

function galleryServiceAdmin(): User
{
    return User::factory()->admin()->create();
}

function galleryFixture(string $slug = 'fixture-gallery'): ArtworkCategory
{
    $category = ArtworkCategory::create([
        'name' => 'Fixture Gallery',
        'slug' => $slug,
        'show_on_home' => false,
    ]);
    testGallerySection($category, ['state' => 'hidden', 'show_in_navigation' => false]);

    return $category;
}

it('requires an admin for Gallery mutations', function (): void {
    $service = app(ArtworkCategoryEditorialService::class);

    expect(fn () => $service->create(['name' => 'Denied', 'slug' => 'denied']))
        ->toThrow(AuthorizationException::class);

    $this->actingAs(User::factory()->create(), 'web');

    expect(fn () => $service->create(['name' => 'Denied', 'slug' => 'denied']))
        ->toThrow(AuthorizationException::class)
        ->and(ArtworkCategory::query()->where('slug', 'denied')->exists())->toBeFalse();
});

it('creates one hidden canonical Gallery section and audits it', function (): void {
    $admin = galleryServiceAdmin();
    $this->actingAs($admin, 'web');

    $category = app(ArtworkCategoryEditorialService::class)->create([
        'name' => 'New Gallery',
        'slug' => 'new-gallery',
        'description' => 'Description',
        'show_on_home' => true,
    ]);
    $section = $category->siteSection()->firstOrFail();

    expect($section->type)->toBe(SiteSection::TYPE_GALLERY)
        ->and($section->slug)->toBe('new-gallery')
        ->and($section->state)->toBe('hidden')
        ->and($section->show_in_navigation)->toBeFalse()
        ->and($category->show_on_home)->toBeTrue()
        ->and(AuditEvent::query()
            ->where('action', 'artwork_category.created')
            ->where('entity_id', $category->id)
            ->where('admin_user_id', $admin->id)
            ->exists())->toBeTrue();
});

it('rejects only system-reserved or invalid Gallery slugs', function (string $slug): void {
    $this->actingAs(galleryServiceAdmin(), 'web');

    expect(fn () => app(ArtworkCategoryEditorialService::class)->create(['name' => 'Bad', 'slug' => $slug]))
        ->toThrow(ValidationException::class);
})->with(['admin', 'artworks', 'media', 'api', 'up', 'storage', 'sitemap', 'robots', 'Bad Slug']);

it('changes a Gallery slug together with its canonical section and redirect', function (): void {
    $this->actingAs(galleryServiceAdmin(), 'web');
    $category = galleryFixture('old-gallery');

    app(ArtworkCategoryEditorialService::class)->changeSlug($category, 'new-gallery-path');

    expect($category->fresh()->slug)->toBe('new-gallery-path')
        ->and($category->siteSection()->firstOrFail()->slug)->toBe('new-gallery-path')
        ->and(Redirect::query()->where('source_path', '/old-gallery')->value('target_path'))->toBe('/new-gallery-path')
        ->and(AuditEvent::query()->where('action', 'artwork_category.slug_changed')->count())->toBe(1);
});

it('deletes only hidden empty Galleries', function (): void {
    $this->actingAs(galleryServiceAdmin(), 'web');
    $service = app(ArtworkCategoryEditorialService::class);
    $deletable = galleryFixture('deletable-gallery');

    $service->delete($deletable);

    expect(ArtworkCategory::query()->whereKey($deletable->id)->exists())->toBeFalse()
        ->and(SiteSection::query()->where('artwork_category_id', $deletable->id)->exists())->toBeFalse();

    $blocked = galleryFixture('blocked-gallery');
    Artwork::create([
        'artwork_category_id' => $blocked->id,
        'slug' => 'blocking-artwork',
        'title' => 'Blocking artwork',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);

    expect(fn () => $service->delete($blocked))->toThrow(ValidationException::class);
});

it('reorders the complete Gallery artwork set atomically and audits once', function (): void {
    $this->actingAs(galleryServiceAdmin(), 'web');
    $category = galleryFixture('ordered-gallery');
    $first = Artwork::create([
        'artwork_category_id' => $category->id,
        'slug' => 'first-work',
        'title' => 'First',
        'state' => 'draft',
        'position' => 10,
        'date_precision' => 'unknown',
    ]);
    $second = Artwork::create([
        'artwork_category_id' => $category->id,
        'slug' => 'second-work',
        'title' => 'Second',
        'state' => 'draft',
        'position' => 20,
        'date_precision' => 'unknown',
    ]);

    app(ArtworkCategoryEditorialService::class)->reorderArtworks($category, [$second->id, $first->id]);

    expect($second->fresh()->position)->toBe(0)
        ->and($first->fresh()->position)->toBe(1)
        ->and(AuditEvent::query()->where('action', 'artwork_category.gallery_reordered')->count())->toBe(1);
});
