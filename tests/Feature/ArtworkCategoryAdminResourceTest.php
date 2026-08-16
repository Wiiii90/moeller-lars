<?php

use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use App\Filament\Resources\ArtworkCategories\Pages\CreateArtworkCategory;
use App\Filament\Resources\ArtworkCategories\Pages\EditArtworkCategory;
use App\Filament\Resources\ArtworkCategories\Pages\ListArtworkCategories;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\AuditEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

it('shows category administration to admins and denies non-admins', function () {
    Livewire::test(ListArtworkCategories::class)->assertSuccessful();
    auth()->logout();
    $this->actingAs(User::factory()->create(), 'web');
    $this->get(ArtworkCategoryResource::getUrl('index'))->assertForbidden();
});

it('creates and edits a category through the real Filament pages', function () {
    Livewire::test(CreateArtworkCategory::class)
        ->fillForm(['name' => 'Admin custom', 'slug' => 'admin-custom', 'position' => 3, 'description' => 'Desc'])
        ->call('create')
        ->assertHasNoFormErrors();
    $category = ArtworkCategory::query()->where('slug', 'admin-custom')->firstOrFail();
    expect($category->state)->toBe('hidden')->and(AuditEvent::query()->where('action', 'artwork_category.created')->where('entity_id', $category->id)->count())->toBe(1);

    Livewire::test(EditArtworkCategory::class, ['record' => $category->id])
        ->fillForm(['name' => 'Admin changed', 'position' => 4, 'description' => 'Changed', 'slug' => 'other-slug'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($category->fresh()->name)->toBe('Admin changed')
        ->and($category->fresh()->slug)->toBe('admin-custom')
        ->and(AuditEvent::query()->where('action', 'artwork_category.updated')->where('entity_id', $category->id)->count())->toBe(1);
});

it('publishes, changes slug, and deletes a custom category through actions', function () {
    $category = ArtworkCategory::create(['name' => 'Action category', 'slug' => 'action-category', 'state' => 'hidden', 'position' => 0]);
    $component = Livewire::test(EditArtworkCategory::class, ['record' => $category->id]);
    $component->call('mountAction', 'publish')->call('callMountedAction');
    expect($category->fresh()->state)->toBe('published');

    $component = Livewire::test(EditArtworkCategory::class, ['record' => $category->id]);
    $component->call('mountAction', 'changeSlug')->set('mountedActions.0.data.slug', 'action-renamed')->call('callMountedAction');
    expect($category->fresh()->slug)->toBe('action-renamed');

    $component = Livewire::test(EditArtworkCategory::class, ['record' => $category->id]);
    $component->call('mountAction', 'hide')->call('callMountedAction');
    $component->call('mountAction', 'deleteCategory')->call('callMountedAction');
    expect(ArtworkCategory::query()->whereKey($category->id)->exists())->toBeFalse();
});

it('does not expose slug change or delete actions for legacy categories', function () {
    $category = ArtworkCategory::query()->where('slug', 'paintings')->firstOrFail();
    Livewire::test(EditArtworkCategory::class, ['record' => $category->id])
        ->assertActionHidden('changeSlug')
        ->assertActionHidden('deleteCategory');
});

it('blocks hiding a category with published artwork', function () {
    $category = ArtworkCategory::create(['name' => 'Referenced', 'slug' => 'referenced-category', 'state' => 'published', 'position' => 0]);
    Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'referenced-work', 'title' => 'Referenced work', 'state' => 'published', 'position' => 0, 'date_precision' => 'unknown']);
    Livewire::test(EditArtworkCategory::class, ['record' => $category->id])->call('mountAction', 'hide')->call('callMountedAction');
    expect($category->fresh()->state)->toBe('published');
});

it('shows gallery reorder only for categories with at least two artworks', function () {
    $category = ArtworkCategory::create(['name' => 'Reorder category', 'slug' => 'admin-reorder-category', 'state' => 'published', 'position' => 0]);
    Livewire::test(EditArtworkCategory::class, ['record' => $category->id])->assertActionHidden('reorderArtworks');

    Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'admin-reorder-one', 'title' => 'One', 'state' => 'draft', 'position' => 0, 'date_precision' => 'unknown']);
    Livewire::test(EditArtworkCategory::class, ['record' => $category->id])->assertActionHidden('reorderArtworks');
    Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'admin-reorder-two', 'title' => 'Two', 'state' => 'published', 'position' => 1, 'date_precision' => 'unknown']);
    Livewire::test(EditArtworkCategory::class, ['record' => $category->id])->assertActionVisible('reorderArtworks');
});

it('reorders artworks through the category drag-order action', function () {
    $category = ArtworkCategory::create(['name' => 'Drag category', 'slug' => 'drag-order-category', 'state' => 'published', 'position' => 0]);
    $first = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'drag-first', 'title' => 'First', 'state' => 'draft', 'position' => 10, 'date_precision' => 'unknown']);
    $second = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'drag-second', 'title' => 'Second', 'state' => 'published', 'position' => 20, 'date_precision' => 'unknown']);
    $third = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'drag-third', 'title' => 'Third', 'state' => 'archived', 'position' => 30, 'date_precision' => 'unknown']);

    Livewire::test(EditArtworkCategory::class, ['record' => $category->id])
        ->call('mountAction', 'reorderArtworks')
        ->set('mountedActions.0.data.artworks', [
            ['id' => $third->id],
            ['id' => $first->id],
            ['id' => $second->id],
        ])
        ->call('callMountedAction');

    expect($third->fresh()->position)->toBe(0)
        ->and($first->fresh()->position)->toBe(1)
        ->and($second->fresh()->position)->toBe(2)
        ->and(AuditEvent::query()->where('action', 'artwork_category.gallery_reordered')->where('entity_id', $category->id)->count())->toBe(1);
});

it('rejects a manipulated category reorder payload without partial mutation', function () {
    $category = ArtworkCategory::create(['name' => 'Safe drag category', 'slug' => 'safe-drag-category', 'state' => 'published', 'position' => 0]);
    $first = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'safe-drag-first', 'title' => 'First', 'state' => 'draft', 'position' => 4, 'date_precision' => 'unknown']);
    $second = Artwork::create(['artwork_category_id' => $category->id, 'slug' => 'safe-drag-second', 'title' => 'Second', 'state' => 'draft', 'position' => 8, 'date_precision' => 'unknown']);

    Livewire::test(EditArtworkCategory::class, ['record' => $category->id])
        ->call('mountAction', 'reorderArtworks')
        ->set('mountedActions.0.data.artworks', [['id' => $first->id], ['id' => 999999]])
        ->call('callMountedAction');

    expect($first->fresh()->position)->toBe(4)
        ->and($second->fresh()->position)->toBe(8)
        ->and(AuditEvent::query()->where('action', 'artwork_category.gallery_reordered')->count())->toBe(0);
});
