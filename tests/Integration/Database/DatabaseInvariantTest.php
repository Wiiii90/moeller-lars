<?php

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\Exhibition;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\SiteSection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function invariantAsset(): MediaAsset
{
    static $sequence = 0;
    $sequence++;

    $asset = MediaAsset::create([
        'storage_key' => 'originals/invariant-asset-'.$sequence.'.jpg',
        'original_filename' => 'work.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'invariant-asset-'.$sequence),
        'state' => 'available',
        'alt_text' => 'Artwork image',
    ]);

    MediaVariant::create([
        'media_asset_id' => $asset->id,
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/invariant-thumbnail-'.$sequence.'.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'invariant-thumbnail-'.$sequence),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);

    return $asset;
}

it('allocates a unique published position through canonical placement without invalidating old snapshots', function (): void {
    $this->actingAs(\App\Models\User::factory()->admin()->create(), 'web');
    $first = ArtworkCategory::create(['slug' => 'first', 'name' => 'First', 'show_on_home' => false]);
    $firstSection = testGallerySection($first, ['state' => 'published', 'position' => 200]);
    $second = ArtworkCategory::create(['slug' => 'second', 'name' => 'Second', 'show_on_home' => false]);
    $secondSection = testGallerySection($second, ['state' => 'hidden', 'position' => 200]);

    $updated = app(\App\Domain\Content\SiteSectionEditorialService::class)
        ->updatePlacement($secondSection, 'published', null);

    expect($updated->state)->toBe('published')
        ->and((int) $updated->position)->not->toBe((int) $firstSection->position)
        ->and((int) $firstSection->fresh()->position)->toBe(200);
});

it('enforces one primary media usage per artwork', function (): void {
    $category = ArtworkCategory::create(['slug' => 'works', 'name' => 'Works', 'state' => 'published', 'position' => 0]);
    $artwork = Artwork::create([
        'artwork_category_id' => $category->id, 'slug' => 'one', 'title' => 'One', 'state' => 'draft', 'position' => 0,
    ]);
    ArtworkMedia::create(['artwork_id' => $artwork->id, 'media_asset_id' => invariantAsset()->id, 'role' => 'primary', 'position' => 0]);

    expect(fn () => ArtworkMedia::create([
        'artwork_id' => $artwork->id, 'media_asset_id' => invariantAsset()->id, 'role' => 'primary', 'position' => 1,
    ]))->toThrow(QueryException::class);
});

it('enforces at most one exhibition hero inside a Journal', function (): void {
    $journal = SiteSection::query()
        ->where('type', SiteSection::TYPE_JOURNAL)
        ->where('template', SiteSection::JOURNAL_TEMPLATE_EXHIBITIONS)
        ->firstOrFail();
    $exhibition = Exhibition::create([
        'site_section_id' => $journal->id,
        'slug' => 'show',
        'title' => 'Show',
        'state' => 'draft',
        'position' => 0,
    ]);
    JournalEntryMedia::create([
        'exhibition_id' => $exhibition->id,
        'media_asset_id' => invariantAsset()->id,
        'role' => JournalEntryMedia::ROLE_COVER,
        'position' => 0,
    ]);

    expect(fn () => JournalEntryMedia::create([
        'exhibition_id' => $exhibition->id,
        'media_asset_id' => invariantAsset()->id,
        'role' => JournalEntryMedia::ROLE_COVER,
        'position' => 1,
    ]))->toThrow(QueryException::class);
});

it('enforces Home as the only singleton SiteSection type', function (): void {
    expect(SiteSection::query()->where('type', SiteSection::TYPE_HOME)->count())->toBe(1);

    expect(fn () => DB::table('site_sections')->insert([
        'type' => SiteSection::TYPE_HOME,
        'template' => null,
        'title' => 'Duplicate Home',
        'navigation_label' => 'Duplicate Home',
        'slug' => 'duplicate-home',
        'state' => 'hidden',
        'position' => 999,
        'parent_id' => null,
        'artwork_category_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('keeps Home published with the title as its public menu label fallback', function (): void {
    /** @var SiteSection $home */
    $home = SiteSection::query()->where('type', SiteSection::TYPE_HOME)->firstOrFail();
    $home->fill([
        'navigation_label' => null,
    ]);
    $home->save();

    expect($home->fresh())
        ->state->toBe('published')
        ->navigation_label->toBeNull();

    $home->setAttribute('state', 'hidden');
    expect(fn () => $home->save())->toThrow(ValidationException::class);
    expect(fn () => $home->delete())->toThrow(ValidationException::class);
});
