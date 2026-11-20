<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['public', 'committed'] as $schema) {
            // Preserve the existing eligible sources on each Hero page before
            // retiring the global category-level flag.
            DB::statement("UPDATE {$schema}.gallery_hero_settings AS setting
                SET configuration = jsonb_set(
                    COALESCE(setting.configuration, '{}'::jsonb),
                    '{enabled_gallery_ids}',
                    COALESCE((
                        SELECT jsonb_agg(category.id ORDER BY category.id)
                        FROM {$schema}.artwork_categories AS category
                        WHERE category.show_on_home = true
                    ), '[]'::jsonb),
                    true
                )
                WHERE NOT (COALESCE(setting.configuration, '{}'::jsonb) ? 'enabled_gallery_ids')");

            DB::statement("ALTER TABLE {$schema}.artwork_categories DROP COLUMN show_on_home");
        }
    }

    public function down(): void
    {
        // A page-specific selection cannot be losslessly collapsed back into
        // one shared Gallery flag. Keep the artist's distinct source choices.
        throw new \RuntimeException('Per-page Hero Gallery sources cannot be rolled back without losing independent selections.');
    }
};
