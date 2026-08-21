<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS site_sections_singleton_type_unique');
        DB::statement('ALTER TABLE site_sections DROP CONSTRAINT IF EXISTS site_sections_type_check');
        DB::statement('ALTER TABLE site_sections DROP CONSTRAINT IF EXISTS site_sections_slug_check');

        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_type_check CHECK (type IN ('home', 'gallery', 'navigation_group', 'vita', 'blog', 'exhibitions', 'contact'))");
        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_slug_check CHECK ((type IN ('home', 'navigation_group') AND slug IS NULL) OR (type NOT IN ('home', 'navigation_group') AND slug IS NOT NULL AND slug ~ '^[a-z0-9]+(?:-[a-z0-9]+)*$'))");
        DB::statement("CREATE UNIQUE INDEX site_sections_singleton_type_unique ON site_sections (type) WHERE type IN ('home', 'vita', 'blog', 'exhibitions', 'contact')");

        if (! DB::table('site_sections')->where('type', 'contact')->exists()) {
            $position = ((int) (DB::table('site_sections')->whereNull('parent_id')->max('position') ?? 0)) + 10;
            DB::table('site_sections')->insert([
                'type' => 'contact',
                'title' => 'Contact',
                'navigation_label' => 'Contact',
                'slug' => 'contact',
                // Contact content visibility remains owned by the existing typed Contact settings until #121 cuts over its editor.
                'state' => 'published',
                'position' => $position,
                'show_in_navigation' => false,
                'parent_id' => null,
                'artwork_category_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $groupIds = DB::table('site_sections')->where('type', 'navigation_group')->pluck('id');
        if ($groupIds->isNotEmpty()) {
            $position = (int) (DB::table('site_sections')->whereNull('parent_id')->max('position') ?? 0);
            $children = DB::table('site_sections')
                ->whereIn('parent_id', $groupIds)
                ->orderBy('parent_id')
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id']);
            foreach ($children as $child) {
                $position += 10;
                DB::table('site_sections')->where('id', $child->id)->update([
                    'parent_id' => null,
                    'position' => $position,
                    'updated_at' => now(),
                ]);
            }
            DB::table('site_sections')->whereIn('id', $groupIds)->delete();
        }
        DB::table('site_sections')->where('type', 'contact')->delete();

        DB::statement('DROP INDEX IF EXISTS site_sections_singleton_type_unique');
        DB::statement('ALTER TABLE site_sections DROP CONSTRAINT IF EXISTS site_sections_type_check');
        DB::statement('ALTER TABLE site_sections DROP CONSTRAINT IF EXISTS site_sections_slug_check');

        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_type_check CHECK (type IN ('home', 'gallery', 'vita', 'blog', 'exhibitions'))");
        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_slug_check CHECK ((type = 'home' AND slug IS NULL) OR (type <> 'home' AND slug IS NOT NULL AND slug ~ '^[a-z0-9]+(?:-[a-z0-9]+)*$'))");
        DB::statement("CREATE UNIQUE INDEX site_sections_singleton_type_unique ON site_sections (type) WHERE type IN ('home', 'vita', 'blog', 'exhibitions')");
    }
};
