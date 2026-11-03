<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The original page-metadata migration changed only Working. Keep both
     * publication schemas identical without promoting unpublished metadata.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [\App\Domain\Publication\PublicationSnapshot::LOCK_KEY]);

            $workingHasColumn = DB::table('information_schema.columns')
                ->where('table_schema', 'public')
                ->where('table_name', 'site_sections')
                ->where('column_name', 'meta_description')
                ->exists();

            if (! $workingHasColumn) {
                throw new \RuntimeException('Working site_sections.meta_description is missing; restore the owning migration before aligning Committed.');
            }

            DB::statement('ALTER TABLE committed.site_sections ADD COLUMN IF NOT EXISTS meta_description TEXT NULL');

            // Backfill only the published Gallery metadata from Committed.
            // Never promote unpublished Working values during a schema repair.
            DB::statement(<<<'SQL'
UPDATE committed.site_sections AS section
SET meta_description = gallery.description
FROM committed.artwork_categories AS gallery
WHERE section.artwork_category_id = gallery.id
  AND section.type = 'gallery'
  AND section.meta_description IS NULL
  AND gallery.description IS NOT NULL
  AND btrim(gallery.description) <> ''
SQL);
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [\App\Domain\Publication\PublicationSnapshot::LOCK_KEY]);
            DB::statement('ALTER TABLE committed.site_sections DROP COLUMN IF EXISTS meta_description');
        });
    }
};
