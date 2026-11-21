<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['public', 'committed'] as $schema) {
            // The two-level hierarchy is permanently available. Retire the
            // old toggle without changing any existing parent assignments.
            DB::statement("ALTER TABLE {$schema}.public_content_settings DROP COLUMN IF EXISTS navigation_nesting_enabled");
        }
    }

    public function down(): void
    {
        foreach (['public', 'committed'] as $schema) {
            DB::statement("ALTER TABLE {$schema}.public_content_settings ADD COLUMN IF NOT EXISTS navigation_nesting_enabled boolean NOT NULL DEFAULT true");
        }
    }
};
