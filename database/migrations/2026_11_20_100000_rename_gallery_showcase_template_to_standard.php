<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['public', 'committed'] as $schema) {
            DB::statement("ALTER TABLE {$schema}.site_sections DROP CONSTRAINT IF EXISTS site_sections_template_check");
            DB::statement("UPDATE {$schema}.site_sections SET template = 'standard' WHERE type = 'gallery' AND template = 'showcase'");
            DB::statement("ALTER TABLE {$schema}.site_sections ADD CONSTRAINT site_sections_template_check CHECK (
                (type = 'journal' AND template IN ('blog', 'exhibitions'))
                OR (type = 'gallery' AND template IN ('standard', 'hero_artworks'))
                OR (type NOT IN ('journal', 'gallery') AND template IS NULL)
            )");
        }
    }

    public function down(): void
    {
        foreach (['public', 'committed'] as $schema) {
            DB::statement("ALTER TABLE {$schema}.site_sections DROP CONSTRAINT IF EXISTS site_sections_template_check");
            DB::statement("UPDATE {$schema}.site_sections SET template = 'showcase' WHERE type = 'gallery' AND template = 'standard'");
            DB::statement("ALTER TABLE {$schema}.site_sections ADD CONSTRAINT site_sections_template_check CHECK (
                (type = 'journal' AND template IN ('blog', 'exhibitions'))
                OR (type = 'gallery' AND template IN ('showcase', 'hero_artworks'))
                OR (type NOT IN ('journal', 'gallery') AND template IS NULL)
            )");
        }
    }
};
