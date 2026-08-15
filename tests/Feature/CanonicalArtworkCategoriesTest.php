<?php

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function canonicalArtworkCategories(): array
{
    return [
        'paintings' => ['name' => 'Paintings', 'position' => 0],
        'prints' => ['name' => 'Prints', 'position' => 1],
        'drawings' => ['name' => 'Drawings', 'position' => 2],
        'cyanotype' => ['name' => 'Cyanotype', 'position' => 3],
        'bichromate' => ['name' => 'Salt Print & Gum Bichromate', 'position' => 4],
        'litho' => ['name' => 'Etching & Lithography', 'position' => 5],
        'photo' => ['name' => 'Photography', 'position' => 6],
        'ignis' => ['name' => 'Ignis-Serial', 'position' => 7],
        'other' => ['name' => 'Other Photography', 'position' => 8],
    ];
}

function runCanonicalProvisioningMigration(): object
{
    $migration = require base_path('database/migrations/2026_08_16_000001_provision_canonical_artwork_categories.php');
    $migration->up();

    return $migration;
}

it('contains exactly the nine canonical category slugs on a fresh database', function () {
    expect(ArtworkCategory::query()->pluck('slug')->sort()->values()->all())
        ->toBe(collect(canonicalArtworkCategories())->keys()->sort()->values()->all());
});

it('provisions the exact canonical names, states, positions, and no provenance', function () {
    foreach (canonicalArtworkCategories() as $slug => $expected) {
        $category = ArtworkCategory::query()->where('slug', $slug)->firstOrFail();

        expect($category->name)->toBe($expected['name'])
            ->and($category->state)->toBe('published')
            ->and($category->position)->toBe($expected['position'])
            ->and($category->legacy_id)->toBeNull()
            ->and($category->legacy_source)->toBeNull()
            ->and($category->migration_batch_id)->toBeNull()
            ->and($category->migrated_at)->toBeNull();
    }
});

it('serves every canonical category route on a fresh empty database', function () {
    foreach (array_keys(canonicalArtworkCategories()) as $slug) {
        $this->get('/'.$slug)->assertSuccessful();
    }
});

it('is idempotent and preserves an edited canonical category', function () {
    $paintings = ArtworkCategory::query()->where('slug', 'paintings')->firstOrFail();
    $paintings->update(['name' => 'Custom Paintings', 'state' => 'hidden', 'position' => 77]);

    runCanonicalProvisioningMigration();

    expect(ArtworkCategory::query()->count())->toBe(9)
        ->and($paintings->fresh()->name)->toBe('Custom Paintings')
        ->and($paintings->fresh()->state)->toBe('hidden')
        ->and($paintings->fresh()->position)->toBe(77);
});

it('down removes only unchanged unreferenced canonical categories', function () {
    $prints = ArtworkCategory::query()->where('slug', 'prints')->firstOrFail();
    $prints->update(['name' => 'Custom Prints']);
    $referenced = ArtworkCategory::query()->where('slug', 'drawings')->firstOrFail();
    Artwork::create([
        'artwork_category_id' => $referenced->getKey(),
        'slug' => 'referenced-bootstrap-category',
        'title' => 'Referenced category',
        'state' => 'draft',
        'position' => 0,
        'date_precision' => 'unknown',
    ]);

    $migration = runCanonicalProvisioningMigration();
    $migration->down();

    expect(ArtworkCategory::query()->where('slug', 'paintings')->exists())->toBeFalse()
        ->and(ArtworkCategory::query()->where('slug', 'prints')->exists())->toBeTrue()
        ->and(ArtworkCategory::query()->where('slug', 'drawings')->exists())->toBeTrue()
        ->and(ArtworkCategory::query()->where('slug', 'photo')->exists())->toBeFalse();
});
