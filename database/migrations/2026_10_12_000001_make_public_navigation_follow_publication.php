<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Publication history stores schema hashes and historical site-section
     * records. Keep the retired column for snapshot compatibility, but remove
     * its index/validation authority. Only "published" controls menu inclusion.
     * Position uniqueness stays with the canonical ordering services: older
     * restorable commits may contain duplicate positions that were formerly
     * excluded from their public navigation. A new unique partial index would
     * invalidate those snapshots even though their column schema still matches.
     */
    public function up(): void
    {
        foreach ($this->schemas() as $schema) {
            DB::statement("DROP INDEX IF EXISTS {$schema}.site_sections_top_level_navigation_position_unique");
            DB::statement("DROP INDEX IF EXISTS {$schema}.site_sections_child_navigation_position_unique");
            DB::statement("ALTER TABLE {$schema}.site_sections DROP CONSTRAINT IF EXISTS site_sections_navigation_label_check");

            DB::statement("CREATE INDEX site_sections_published_root_position_index ON {$schema}.site_sections (position) WHERE parent_id IS NULL AND state = 'published'");
            DB::statement("CREATE INDEX site_sections_published_child_position_index ON {$schema}.site_sections (parent_id, position) WHERE parent_id IS NOT NULL AND state = 'published'");
        }
    }

    public function down(): void
    {
        foreach ($this->schemas() as $schema) {
            DB::statement("DROP INDEX IF EXISTS {$schema}.site_sections_published_root_position_index");
            DB::statement("DROP INDEX IF EXISTS {$schema}.site_sections_published_child_position_index");

            DB::statement("ALTER TABLE {$schema}.site_sections ADD CONSTRAINT site_sections_navigation_label_check CHECK (NOT show_in_navigation OR (navigation_label IS NOT NULL AND btrim(navigation_label) <> ''))");
            DB::statement("CREATE UNIQUE INDEX site_sections_top_level_navigation_position_unique ON {$schema}.site_sections (position) WHERE parent_id IS NULL AND state = 'published' AND show_in_navigation = true");
            DB::statement("CREATE UNIQUE INDEX site_sections_child_navigation_position_unique ON {$schema}.site_sections (parent_id, position) WHERE parent_id IS NOT NULL AND state = 'published' AND show_in_navigation = true");
        }
    }

    /** @return list<string> */
    private function schemas(): array
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? ['public', 'committed']
            : [];
    }

};
