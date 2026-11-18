<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['public', 'committed'] as $schema) {
            DB::statement("ALTER TABLE {$schema}.site_sections ADD COLUMN is_home boolean NOT NULL DEFAULT false");
            DB::statement("UPDATE {$schema}.site_sections SET is_home = true WHERE type = 'home'");
            DB::statement("CREATE UNIQUE INDEX site_sections_one_home_identity_unique ON {$schema}.site_sections (is_home) WHERE is_home = true");
        }
    }

    public function down(): void
    {
        foreach (['public', 'committed'] as $schema) {
            DB::statement("DROP INDEX IF EXISTS {$schema}.site_sections_one_home_identity_unique");
            DB::statement("ALTER TABLE {$schema}.site_sections DROP COLUMN IF EXISTS is_home");
        }
    }
};
