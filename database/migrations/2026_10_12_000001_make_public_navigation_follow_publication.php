<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Publication history stores schema hashes and historical site-section
     * records. Keep the retired column for snapshot compatibility, but remove
     * its index/validation authority. Only "published" controls menu inclusion.
     */
    public function up(): void
    {
        foreach ($this->schemas() as $schema) {
            $this->assertPublishedPositionsAreUnique($schema);

            DB::statement("DROP INDEX IF EXISTS {$schema}.site_sections_top_level_navigation_position_unique");
            DB::statement("DROP INDEX IF EXISTS {$schema}.site_sections_child_navigation_position_unique");
            DB::statement("ALTER TABLE {$schema}.site_sections DROP CONSTRAINT IF EXISTS site_sections_navigation_label_check");

            DB::statement("CREATE UNIQUE INDEX site_sections_published_root_position_unique ON {$schema}.site_sections (position) WHERE parent_id IS NULL AND state = 'published'");
            DB::statement("CREATE UNIQUE INDEX site_sections_published_child_position_unique ON {$schema}.site_sections (parent_id, position) WHERE parent_id IS NOT NULL AND state = 'published'");
        }
    }

    public function down(): void
    {
        foreach ($this->schemas() as $schema) {
            DB::statement("DROP INDEX IF EXISTS {$schema}.site_sections_published_root_position_unique");
            DB::statement("DROP INDEX IF EXISTS {$schema}.site_sections_published_child_position_unique");

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

    private function assertPublishedPositionsAreUnique(string $schema): void
    {
        $collision = DB::selectOne("
            SELECT parent_id, position
            FROM {$schema}.site_sections
            WHERE state = 'published'
            GROUP BY parent_id, position
            HAVING COUNT(*) > 1
            LIMIT 1
        ");

        if ($collision !== null) {
            throw new RuntimeException(
                "Published SiteSection positions conflict in {$schema}. Run the canonical ordering repair before changing navigation indexes."
            );
        }
    }
};
