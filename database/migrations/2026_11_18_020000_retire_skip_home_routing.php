<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['public', 'committed'] as $schema) {
            DB::statement("UPDATE {$schema}.home_presentation_settings SET template = 'artwork' WHERE template = 'skip_home'");
            DB::statement("ALTER TABLE {$schema}.home_presentation_settings DROP CONSTRAINT IF EXISTS home_presentation_settings_skip_target_section_id_foreign");
            DB::statement("ALTER TABLE {$schema}.home_presentation_settings DROP COLUMN IF EXISTS skip_target_section_id");
            DB::statement("ALTER TABLE {$schema}.home_presentation_settings DROP COLUMN IF EXISTS skip_home");
        }
    }

    public function down(): void
    {
        foreach (['public', 'committed'] as $schema) {
            DB::statement("ALTER TABLE {$schema}.home_presentation_settings ADD COLUMN skip_home boolean NOT NULL DEFAULT false");
            DB::statement("ALTER TABLE {$schema}.home_presentation_settings ADD COLUMN skip_target_section_id bigint NULL");
        }
        DB::statement('ALTER TABLE public.home_presentation_settings ADD CONSTRAINT home_presentation_settings_skip_target_section_id_foreign FOREIGN KEY (skip_target_section_id) REFERENCES public.site_sections (id) ON DELETE SET NULL');
    }
};
