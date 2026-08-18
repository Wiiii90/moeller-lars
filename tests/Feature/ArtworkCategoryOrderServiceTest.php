<?php

use App\Domain\Artwork\ArtworkCategoryOrderService;
use App\Models\ArtworkCategory;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function orderedCategory(string $name, int $position): ArtworkCategory
{
    return ArtworkCategory::create([
        'name' => $name,
        'slug' => strtolower(str_replace(' ', '-', $name)),
        'state' => 'hidden',
        'position' => $position,
    ]);
}

it('moves categories directly while preserving the existing public navigation slots', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');

    $first = orderedCategory('First', 10);
    $second = orderedCategory('Second', 20);
    $third = orderedCategory('Third', 40);

    $service = app(ArtworkCategoryOrderService::class);
    expect($service->move($third, 'up'))->toBeTrue();

    expect(ArtworkCategory::query()->orderBy('position')->pluck('id')->all())
        ->toBe([$first->id, $third->id, $second->id])
        ->and(ArtworkCategory::query()->orderBy('position')->pluck('position')->all())
        ->toBe([10, 20, 40])
        ->and(AuditEvent::query()->where('action', 'artwork_category.updated')->where('admin_user_id', $admin->id)->count())
        ->toBe(2);
});

it('does not mutate or audit when moving beyond a list boundary', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $first = orderedCategory('Boundary first', 0);
    orderedCategory('Boundary second', 1);

    expect(app(ArtworkCategoryOrderService::class)->move($first, 'up'))->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe(0)
        ->and($first->fresh()->position)->toBe(0);
});

it('requires an admin actor for category order mutation', function () {
    $first = orderedCategory('Auth first', 0);
    orderedCategory('Auth second', 1);

    expect(fn () => app(ArtworkCategoryOrderService::class)->move($first, 'down'))
        ->toThrow(AuthorizationException::class);

    $this->actingAs(User::factory()->create(), 'web');
    expect(fn () => app(ArtworkCategoryOrderService::class)->move($first, 'down'))
        ->toThrow(AuthorizationException::class);

    expect($first->fresh()->position)->toBe(0)
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('rejects invalid category order directions', function () {
    $category = orderedCategory('Invalid direction', 0);

    expect(fn () => app(ArtworkCategoryOrderService::class)->canMove($category, 'sideways'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ArtworkCategoryOrderService::class)->move($category, 'sideways'))
        ->toThrow(InvalidArgumentException::class);
});
