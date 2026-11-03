<?php

use App\Domain\Publication\PublicationSchemaGuard;
use App\Domain\Publication\PublicationVersionService;
use App\Models\MediaAsset;
use App\Models\PublicationCheckpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restore only the immediately preceding media_variants schema inside the
 * already-disposable PostgreSQL migration test transaction. Production must
 * never be used for these reversible schema-contract fixtures.
 */
function preparePreviousMediaVariantSchema(): void
{
    foreach (['public', 'committed'] as $schema) {
        DB::statement("ALTER TABLE {$schema}.media_variants ADD COLUMN transform_profile VARCHAR(120) NOT NULL DEFAULT 'legacy'");
        DB::statement("DROP INDEX IF EXISTS {$schema}.media_variants_one_available_kind_unique");
    }
}

function legacyVariantMigration(): \Illuminate\Database\Migrations\Migration
{
    return require database_path('migrations/2026_11_03_120000_retire_media_variant_transform_profile.php');
}

function previousMediaVariantRow(int $assetId, string $kind, string $profile, string $storageKey): array
{
    return [
        'media_asset_id' => $assetId,
        'variant_kind' => $kind,
        'transform_profile' => $profile,
        'storage_key' => $storageKey,
        'mime_type' => 'image/webp',
        'byte_size' => 8,
        'sha256' => hash('sha256', $storageKey),
        'state' => 'available',
        'width' => 120,
        'height' => 80,
        'created_at' => now(),
        'updated_at' => now(),
    ];
}

it('removes the legacy preview profile from populated Working and Committed without losing history', function (): void {
    preparePreviousMediaVariantSchema();

    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/legacy-profile.jpg',
        'original_filename' => 'legacy-profile.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 8,
        'sha256' => hash('sha256', 'legacy-file'),
        'state' => 'available',
    ]);
    DB::table('committed.media_assets')->insert($asset->getAttributes());

    foreach (['public', 'committed'] as $schema) {
        DB::table("{$schema}.media_variants")->insert(
            previousMediaVariantRow($asset->id, 'thumbnail', 'public-v2', "variants/{$schema}-legacy.webp"),
        );
    }

    $versions = app(PublicationVersionService::class);
    $checkpoint = PublicationCheckpoint::query()->where('snapshot_available', true)->firstOrFail();
    $checkpoint->forceFill(['schema_hash' => $versions->schemaHash()])->save();
    $oldHash = $checkpoint->schema_hash;

    legacyVariantMigration()->up();

    expect(Schema::hasColumn('media_variants', 'transform_profile'))->toBeFalse()
        ->and(DB::table('information_schema.columns')->where('table_schema', 'committed')
            ->where('table_name', 'media_variants')->where('column_name', 'transform_profile')->exists())->toBeFalse()
        ->and(DB::table('media_variants')->where('media_asset_id', $asset->id)->count())->toBe(1)
        ->and(DB::table('committed.media_variants')->where('media_asset_id', $asset->id)->count())->toBe(1)
        ->and($checkpoint->fresh()->schema_hash)->toBe($versions->schemaHash())
        ->and($checkpoint->fresh()->schema_hash)->not->toBe($oldHash);
    app(PublicationSchemaGuard::class)->assertParity();
});

it('aborts without schema changes when old Working previews have two active entries of the same kind', function (): void {
    preparePreviousMediaVariantSchema();

    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/legacy-conflict.jpg',
        'original_filename' => 'legacy-conflict.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 8,
        'sha256' => hash('sha256', 'conflict-file'),
        'state' => 'available',
    ]);

    DB::table('media_variants')->insert([
        previousMediaVariantRow($asset->id, 'thumbnail', 'public-v1', 'variants/conflict-first.webp'),
        previousMediaVariantRow($asset->id, 'thumbnail', 'public-v2', 'variants/conflict-second.webp'),
    ]);

    expect(fn () => legacyVariantMigration()->up())
        ->toThrow(RuntimeException::class, 'Conflicting available previews in public');

    expect(Schema::hasColumn('media_variants', 'transform_profile'))->toBeTrue()
        ->and(DB::table('information_schema.columns')->where('table_schema', 'committed')
            ->where('table_name', 'media_variants')->where('column_name', 'transform_profile')->exists())->toBeTrue()
        ->and(DB::table('media_variants')->where('media_asset_id', $asset->id)->count())->toBe(2);
});

it('migrates media independently of unrelated site_sections drift while the full guard still rejects it', function (): void {
    preparePreviousMediaVariantSchema();
    DB::statement('ALTER TABLE public.site_sections ADD COLUMN test_only_public_drift VARCHAR(20) NULL');

    legacyVariantMigration()->up();

    expect(Schema::hasColumn('media_variants', 'transform_profile'))->toBeFalse()
        ->and(Schema::hasColumn('site_sections', 'test_only_public_drift'))->toBeTrue();
    app(PublicationSchemaGuard::class)->assertParity(['media_variants']);
    expect(fn () => app(PublicationSchemaGuard::class)->assertParity())
        ->toThrow(RuntimeException::class, 'Publication snapshot schema drift detected for site_sections.');
});
