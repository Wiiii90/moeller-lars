<?php

use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Domain\Artwork\ArtworkCategoryOrderService;
use App\Models\ArtworkCategory;
use App\Models\PublicContentSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function hierarchyCategory(string $name, int $position, ?int $parentId = null, string $state = 'hidden', bool $navigation = false): ArtworkCategory
{
    return ArtworkCategory::create([
        'name' => $name,
        'slug' => strtolower(str_replace(' ', '-', $name)),
        'state' => $state,
        'position' => $position,
        'parent_id' => $parentId,
        'show_in_navigation' => $navigation,
    ]);
}

it('assigns an optional top-level parent and rejects deeper nesting', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $parent = hierarchyCategory('Hierarchy parent', 10);
    $child = hierarchyCategory('Hierarchy child', 20);
    $service = app(ArtworkCategoryEditorialService::class);

    $service->update($child, [
        'name' => 'Hierarchy child',
        'position' => 20,
        'parent_id' => $parent->id,
        'description' => null,
        'show_in_navigation' => false,
        'show_on_home' => false,
    ]);

    expect($child->fresh()->parent_id)->toBe($parent->id);

    $grandchild = hierarchyCategory('Hierarchy grandchild', 30);
    expect(fn () => $service->update($grandchild, [
        'name' => 'Hierarchy grandchild',
        'position' => 30,
        'parent_id' => $child->id,
        'description' => null,
        'show_in_navigation' => false,
        'show_on_home' => false,
    ]))->toThrow(ValidationException::class, 'Only one level of child categories is supported.');

    expect(fn () => $service->update($parent, [
        'name' => 'Hierarchy parent',
        'position' => 10,
        'parent_id' => $parent->id,
        'description' => null,
        'show_in_navigation' => false,
        'show_on_home' => false,
    ]))->toThrow(ValidationException::class, 'A category cannot be its own parent.');
});

it('requires visible published children to have a visible published parent', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $parent = hierarchyCategory('Hidden nav parent', 10, null, 'hidden', false);
    $child = hierarchyCategory('Visible nav child', 10, $parent->id, 'hidden', true);
    $service = app(ArtworkCategoryEditorialService::class);

    expect(fn () => $service->publish($child))
        ->toThrow(ValidationException::class, 'A visible child category requires a published parent');

    $service->update($parent, [
        'name' => 'Hidden nav parent',
        'position' => 10,
        'description' => null,
        'show_in_navigation' => true,
        'show_on_home' => false,
    ]);
    $service->publish($parent);
    $service->publish($child);

    expect($child->fresh()->state)->toBe('published')
        ->and(fn () => $service->hide($parent))
        ->toThrow(ValidationException::class, 'published child categories cannot be hidden');
});

it('orders child categories independently from top-level categories', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $topFirst = hierarchyCategory('Top first', 10);
    $topSecond = hierarchyCategory('Top second', 20);
    $parent = hierarchyCategory('Child parent', 30);
    $childFirst = hierarchyCategory('Child first', 10, $parent->id);
    $childSecond = hierarchyCategory('Child second', 20, $parent->id);

    expect(app(ArtworkCategoryOrderService::class)->move($childSecond, 'up'))->toBeTrue()
        ->and($childSecond->fresh()->position)->toBe(10)
        ->and($childFirst->fresh()->position)->toBe(20)
        ->and($topFirst->fresh()->position)->toBe(10)
        ->and($topSecond->fresh()->position)->toBe(20)
        ->and($parent->fresh()->position)->toBe(30);
});

it('keeps child positions out of the global navigation collision space', function () {
    $parent = hierarchyCategory('Public parent', 5, null, 'published', true);
    hierarchyCategory('Public child', 7, $parent->id, 'published', true);

    $settings = PublicContentSetting::query()->findOrFail(1);
    $settings->update([
        'cv_enabled' => true,
        'cv_navigation_label' => 'CV',
        'cv_navigation_position' => 7,
    ]);

    expect($settings->fresh()->cv_navigation_position)->toBe(7);
});

it('renders child categories as an accessible dropdown without changing category URLs', function () {
    $parent = hierarchyCategory('Navigation parent', 5, null, 'published', true);
    $child = hierarchyCategory('Navigation child', 1, $parent->id, 'published', true);

    $this->get('/navigation-child')
        ->assertSuccessful()
        ->assertSee('Navigation parent')
        ->assertSee('Navigation child')
        ->assertSee('data-navigation-submenu-toggle', false)
        ->assertSee('aria-expanded="false"', false)
        ->assertSee('href="'.route('artworks.category', ['category' => $child->slug]).'" aria-current="page"', false);
});
