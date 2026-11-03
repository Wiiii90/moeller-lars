<?php

use App\Domain\Publication\PublicationSchemaGuard;
use App\Domain\Publication\PublicationSnapshot;
use App\Domain\Publication\PublicationVersionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            app(PublicationSchemaGuard::class)->assertParity(['media_variants']);

            foreach (['public', 'committed'] as $schema) {
                $duplicate = DB::selectOne("
                    SELECT media_asset_id, variant_kind
                    FROM {$schema}.media_variants
                    WHERE state = 'available'
                    GROUP BY media_asset_id, variant_kind
                    HAVING count(*) > 1
                    LIMIT 1
                ");
                if ($duplicate !== null) {
                    throw new RuntimeException("Conflicting available previews in {$schema} for media {$duplicate->media_asset_id}. No data changed.");
                }
            }

            $versions = app(PublicationVersionService::class);
            $oldHash = $versions->schemaHash();

            foreach (['public', 'committed'] as $schema) {
                DB::statement("ALTER TABLE {$schema}.media_variants DROP COLUMN transform_profile");
                DB::statement("CREATE UNIQUE INDEX media_variants_one_available_kind_unique ON {$schema}.media_variants (media_asset_id, variant_kind) WHERE state = 'available'");
            }

            app(PublicationSchemaGuard::class)->assertParity(['media_variants']);
            DB::table('publication_checkpoints')
                ->where('snapshot_available', true)
                ->where('schema_hash', $oldHash)
                ->update(['schema_hash' => $versions->schemaHash()]);
        }, attempts: 1);
    }

    public function down(): void
    {
        // The retired version field cannot be reconstructed accurately.
    }
};
