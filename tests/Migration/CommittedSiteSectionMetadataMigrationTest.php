<?php

use App\Domain\Publication\PublicationSchemaGuard;
use App\Domain\Publication\PublicationService;
use App\Models\ArtworkCategory;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function committedGalleryMetadataMigration(): Migration
{
    return require database_path('migrations/2026_11_03_160000_align_committed_site_section_meta_description.php');
}

it('aligns the Committed page metadata schema using only the already committed Gallery source', function (): void {
    $category = ArtworkCategory::query()->create([
        'name' => 'Synthetic Gallery', 'slug' => 'synthetic-meta-gallery',
    ]);
    $section = testGallerySection($category, ['state' => 'hidden']);
    DB::table('artwork_categories')->where('id', $category->id)
        ->update(['description' => 'Published Gallery summary']);
    $actor = User::factory()->admin()->create();
    expect(app(PublicationService::class)->commit($actor, 'Original Gallery metadata'))->not->toBeNull();

    // The artist subsequently edits Working, but never commits the draft.
    DB::table('site_sections')->where('id', $section->id)
        ->update(['meta_description' => 'Unpublished page revision']);
    DB::table('artwork_categories')->where('id', $category->id)
        ->update(['description' => 'Unpublished Gallery revision']);

    DB::statement('ALTER TABLE committed.site_sections DROP COLUMN meta_description');
    expect(fn () => app(PublicationSchemaGuard::class)->assertParity())
        ->toThrow(RuntimeException::class, 'Publication snapshot schema drift detected for site_sections.');

    committedGalleryMetadataMigration()->up();

    expect(Schema::hasColumn('site_sections', 'meta_description'))->toBeTrue()
        ->and(DB::table('information_schema.columns')
            ->where('table_schema', 'committed')->where('table_name', 'site_sections')
            ->where('column_name', 'meta_description')->exists())->toBeTrue()
        ->and(DB::table('site_sections')->where('id', $section->id)->value('meta_description'))
        ->toBe('Unpublished page revision')
        ->and(DB::table('committed.site_sections')->where('id', $section->id)->value('meta_description'))
        ->toBe('Published Gallery summary');
    app(PublicationSchemaGuard::class)->assertParity();

    // Replaying a defensive schema repair must not overwrite authored values.
    committedGalleryMetadataMigration()->up();
    expect(DB::table('committed.site_sections')->where('id', $section->id)->value('meta_description'))
        ->toBe('Published Gallery summary');
});

it('fails closed instead of inventing Working metadata when the owning migration is missing', function (): void {
    DB::statement('ALTER TABLE public.site_sections DROP COLUMN meta_description');

    expect(fn () => committedGalleryMetadataMigration()->up())
        ->toThrow(RuntimeException::class, 'Working site_sections.meta_description is missing');

    expect(DB::table('information_schema.columns')
        ->where('table_schema', 'committed')->where('table_name', 'site_sections')
        ->where('column_name', 'meta_description')->exists())->toBeTrue()
        ->and(Schema::hasColumn('site_sections', 'meta_description'))->toBeFalse();
});
