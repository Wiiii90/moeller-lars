<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // The committed schema was cloned on 2026-08-29. Any later change to a
        // tracked working table must be mirrored so publication comparisons remain
        // structurally meaningful instead of reporting synthetic staged changes.
        DB::statement('ALTER TABLE committed.public_content_settings ADD COLUMN IF NOT EXISTS public_page_width smallint NOT NULL DEFAULT 800');
        DB::statement('ALTER TABLE committed.public_content_settings ADD COLUMN IF NOT EXISTS public_content_padding smallint NOT NULL DEFAULT 75');

        DB::statement('ALTER TABLE committed.home_presentation_settings ADD COLUMN IF NOT EXISTS skip_home boolean NOT NULL DEFAULT false');
        DB::statement('ALTER TABLE committed.home_presentation_settings ADD COLUMN IF NOT EXISTS skip_target_section_id bigint NULL');

        DB::table('committed.home_presentation_settings')
            ->where('template', 'skip_home')
            ->update([
                'template' => 'artwork',
                'skip_home' => true,
            ]);

        DB::statement('ALTER TABLE committed.home_presentation_settings DROP CONSTRAINT IF EXISTS home_presentation_settings_template_check');
        DB::statement("ALTER TABLE committed.home_presentation_settings ADD CONSTRAINT home_presentation_settings_template_check CHECK (template IN ('artwork', 'under_construction', 'custom'))");

        // blog_settings is a superseded application-owned table from the original
        // dedicated Blog settings model. Canonical state already lives in the
        // SiteSection / JournalSetting model, so this obsolete RESTRICT foreign key
        // must not couple it back to canonical publication-state replacement.
        DB::statement('ALTER TABLE public.blog_settings DROP CONSTRAINT IF EXISTS blog_settings_site_section_id_foreign');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE committed.home_presentation_settings DROP CONSTRAINT IF EXISTS home_presentation_settings_template_check');
        DB::statement("ALTER TABLE committed.home_presentation_settings ADD CONSTRAINT home_presentation_settings_template_check CHECK (template IN ('artwork', 'under_construction', 'skip_home', 'custom'))");

        DB::table('committed.home_presentation_settings')
            ->where('skip_home', true)
            ->update(['template' => 'skip_home']);

        DB::statement('ALTER TABLE committed.home_presentation_settings DROP COLUMN IF EXISTS skip_target_section_id');
        DB::statement('ALTER TABLE committed.home_presentation_settings DROP COLUMN IF EXISTS skip_home');
        DB::statement('ALTER TABLE committed.public_content_settings DROP COLUMN IF EXISTS public_content_padding');
        DB::statement('ALTER TABLE committed.public_content_settings DROP COLUMN IF EXISTS public_page_width');

        // Intentionally do not restore blog_settings_site_section_id_foreign.
        // The FK was an obsolete internal coupling, not part of the canonical
        // Publication contract. Restoring it would reintroduce the blocker this
        // repair removes; the next forward migration retires the table itself.
    }
};
