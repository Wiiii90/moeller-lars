<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['public', 'committed'] as $schema) {
            // Hero Artworks is a Gallery presentation and need not own an
            // Artwork category. Existing Showcase categories and all Gallery
            // and Exhibition records remain untouched.
            // Both schemas use the same forward-only shape. Home identity was
            // backfilled by the preceding migration and must remain unique.
            $homeCount = DB::table("{$schema}.site_sections")->where('is_home', true)->count();
            if ($homeCount !== 1) {
                throw new RuntimeException("Expected exactly one protected Home page in {$schema}.");
            }

            DB::statement("ALTER TABLE {$schema}.site_sections ADD COLUMN public_site_gate boolean NOT NULL DEFAULT false");
            DB::statement("ALTER TABLE {$schema}.site_sections DROP CONSTRAINT IF EXISTS site_sections_type_check");
            DB::statement("ALTER TABLE {$schema}.site_sections DROP CONSTRAINT IF EXISTS site_sections_slug_check");
            DB::statement("ALTER TABLE {$schema}.site_sections DROP CONSTRAINT IF EXISTS site_sections_gallery_category_check");
            DB::statement("DROP INDEX IF EXISTS {$schema}.site_sections_singleton_type_unique");

            $home = DB::table("{$schema}.site_sections")->where('is_home', true)->first();
            if ($home === null || $home->type !== 'home') {
                throw new RuntimeException("The {$schema} Home section cannot be migrated safely.");
            }

            DB::table("{$schema}.site_sections")->where('id', $home->id)->update([
                'type' => 'gallery',
                'template' => 'hero_artworks',
                'slug' => null,
                'artwork_category_id' => null,
                'parent_id' => null,
                'state' => 'published',
                'navigation_label' => $home->show_in_navigation
                    ? (trim((string) $home->navigation_label) ?: (string) $home->title)
                    : $home->navigation_label,
            ]);

            DB::statement("ALTER TABLE {$schema}.site_sections ADD CONSTRAINT site_sections_type_check CHECK (type IN ('gallery', 'journal', 'custom', 'navigation_group'))");
            DB::statement("ALTER TABLE {$schema}.site_sections ADD CONSTRAINT site_sections_slug_check CHECK (
                (is_home AND slug IS NULL)
                OR (NOT is_home AND type = 'navigation_group' AND slug IS NULL)
                OR (NOT is_home AND type <> 'navigation_group' AND slug IS NOT NULL AND slug ~ '^[a-z0-9]+(?:-[a-z0-9]+)*$')
            )");
            DB::statement("ALTER TABLE {$schema}.site_sections ADD CONSTRAINT site_sections_gallery_category_check CHECK (
                (type = 'gallery' AND (template = 'hero_artworks' OR artwork_category_id IS NOT NULL))
                OR (type <> 'gallery' AND artwork_category_id IS NULL)
            )");

            // The old Home configuration can be reset. Transfer only its
            // Artwork presentation to the new canonical Gallery owner, then
            // retire the obsolete template column and table name.
            DB::statement("UPDATE {$schema}.home_presentation_settings
                SET configuration = COALESCE(configuration->'artwork', '{}'::jsonb)");
            DB::statement("ALTER TABLE {$schema}.home_presentation_settings DROP CONSTRAINT IF EXISTS home_presentation_settings_template_check");
            DB::statement("ALTER TABLE {$schema}.home_presentation_settings DROP COLUMN template");
            DB::statement("ALTER TABLE {$schema}.home_presentation_settings RENAME TO gallery_hero_settings");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('The Home Page Type cutover is irreversible without a deliberate data migration.');
    }
};
