<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->string('title', 160);
            $table->string('navigation_label', 120)->nullable();
            $table->string('slug', 80)->nullable();
            $table->string('state', 32)->default('hidden');
            $table->integer('position');
            $table->boolean('show_in_navigation')->default(false);
            $table->foreignId('parent_id')->nullable()->constrained('site_sections')->restrictOnDelete();
            $table->foreignId('artwork_category_id')->nullable()->unique()->constrained('artwork_categories')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique('slug');
        });

        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_type_check CHECK (type IN ('home', 'gallery', 'vita', 'blog', 'exhibitions'))");
        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_state_check CHECK (state IN ('published', 'hidden'))");
        DB::statement('ALTER TABLE site_sections ADD CONSTRAINT site_sections_position_check CHECK (position >= 0)');
        DB::statement('ALTER TABLE site_sections ADD CONSTRAINT site_sections_parent_not_self_check CHECK (parent_id IS NULL OR parent_id <> id)');
        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_gallery_category_check CHECK ((type = 'gallery' AND artwork_category_id IS NOT NULL) OR (type <> 'gallery' AND artwork_category_id IS NULL))");
        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_slug_check CHECK ((type = 'home' AND slug IS NULL) OR (type <> 'home' AND slug IS NOT NULL AND slug ~ '^[a-z0-9]+(?:-[a-z0-9]+)*$'))");
        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_navigation_label_check CHECK (NOT show_in_navigation OR (navigation_label IS NOT NULL AND btrim(navigation_label) <> ''))");
        DB::statement("CREATE UNIQUE INDEX site_sections_singleton_type_unique ON site_sections (type) WHERE type IN ('home', 'vita', 'blog', 'exhibitions')");
        DB::statement("CREATE UNIQUE INDEX site_sections_top_level_navigation_position_unique ON site_sections (position) WHERE parent_id IS NULL AND state = 'published' AND show_in_navigation = true");
        DB::statement("CREATE UNIQUE INDEX site_sections_child_navigation_position_unique ON site_sections (parent_id, position) WHERE parent_id IS NOT NULL AND state = 'published' AND show_in_navigation = true");

        $now = now();
        $public = DB::table('public_content_settings')->where('id', 1)->first();
        $blog = DB::table('blog_settings')->where('id', 1)->first();

        $topLevelCandidates = collect();
        $categories = DB::table('artwork_categories')->orderBy('position')->orderBy('id')->get();

        foreach ($categories->whereNull('parent_id') as $category) {
            $topLevelCandidates->push([
                'key' => 'gallery:'.$category->id,
                'position' => (int) $category->position,
                'rank' => 10,
            ]);
        }

        $topLevelCandidates->push(['key' => 'home', 'position' => 0, 'rank' => 0]);
        $topLevelCandidates->push(['key' => 'vita', 'position' => (int) ($public->cv_navigation_position ?? 100), 'rank' => 20]);
        $topLevelCandidates->push(['key' => 'exhibitions', 'position' => (int) ($public->exhibitions_navigation_position ?? 110), 'rank' => 30]);
        $topLevelCandidates->push(['key' => 'blog', 'position' => (int) ($blog->navigation_position ?? 120), 'rank' => 40]);

        $normalizedTopLevelPositions = [];
        $sortedTopLevel = $topLevelCandidates
            ->sortBy(static fn (array $item): string => sprintf('%010d:%03d:%s', $item['position'], $item['rank'], $item['key']))
            ->values();
        foreach ($sortedTopLevel as $index => $item) {
            $normalizedTopLevelPositions[$item['key']] = $index * 10;
        }

        DB::table('site_sections')->insert([
            [
                'type' => 'home',
                'title' => 'Home',
                'navigation_label' => null,
                'slug' => null,
                'state' => 'published',
                'position' => $normalizedTopLevelPositions['home'],
                'show_in_navigation' => false,
                'parent_id' => null,
                'artwork_category_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'vita',
                'title' => 'Vita',
                'navigation_label' => (string) ($public->cv_navigation_label ?? 'Vita'),
                'slug' => 'cv',
                'state' => (bool) ($public->cv_enabled ?? false) ? 'published' : 'hidden',
                'position' => $normalizedTopLevelPositions['vita'],
                'show_in_navigation' => (bool) ($public->cv_enabled ?? false),
                'parent_id' => null,
                'artwork_category_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'exhibitions',
                'title' => 'Exhibitions',
                'navigation_label' => (string) ($public->exhibitions_navigation_label ?? 'Exhibitions'),
                'slug' => 'exhibitions',
                'state' => (bool) ($public->exhibitions_enabled ?? false) ? 'published' : 'hidden',
                'position' => $normalizedTopLevelPositions['exhibitions'],
                'show_in_navigation' => (bool) ($public->exhibitions_enabled ?? false),
                'parent_id' => null,
                'artwork_category_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'blog',
                'title' => (string) ($blog->listing_title ?? 'Blog'),
                'navigation_label' => (string) ($blog->navigation_label ?? 'Blog'),
                'slug' => 'blog',
                'state' => (bool) ($blog->public_enabled ?? false) ? 'published' : 'hidden',
                'position' => $normalizedTopLevelPositions['blog'],
                'show_in_navigation' => (bool) ($blog->public_enabled ?? false),
                'parent_id' => null,
                'artwork_category_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $sectionIdsByCategoryId = [];
        foreach ($categories->whereNull('parent_id')->values() as $category) {
            $position = $normalizedTopLevelPositions['gallery:'.$category->id];
            $sectionId = DB::table('site_sections')->insertGetId([
                'type' => 'gallery',
                'title' => $category->name,
                'navigation_label' => $category->name,
                'slug' => $category->slug,
                'state' => $category->state,
                'position' => $position,
                'show_in_navigation' => (bool) $category->show_in_navigation,
                'parent_id' => null,
                'artwork_category_id' => $category->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sectionIdsByCategoryId[(int) $category->id] = $sectionId;
            DB::table('artwork_categories')->where('id', $category->id)->update(['position' => $position]);
        }

        $childrenByParent = $categories->whereNotNull('parent_id')->groupBy('parent_id');
        foreach ($childrenByParent as $parentCategoryId => $children) {
            $parentSectionId = $sectionIdsByCategoryId[(int) $parentCategoryId] ?? null;
            if ($parentSectionId === null) {
                throw new RuntimeException('Cannot migrate gallery child without its parent site section.');
            }

            foreach ($children->sortBy([['position', 'asc'], ['id', 'asc']])->values() as $index => $category) {
                $position = $index * 10;
                $sectionId = DB::table('site_sections')->insertGetId([
                    'type' => 'gallery',
                    'title' => $category->name,
                    'navigation_label' => $category->name,
                    'slug' => $category->slug,
                    'state' => $category->state,
                    'position' => $position,
                    'show_in_navigation' => (bool) $category->show_in_navigation,
                    'parent_id' => $parentSectionId,
                    'artwork_category_id' => $category->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $sectionIdsByCategoryId[(int) $category->id] = $sectionId;
                DB::table('artwork_categories')->where('id', $category->id)->update(['position' => $position]);
            }
        }

        if ($public !== null) {
            DB::table('public_content_settings')->where('id', 1)->update([
                'cv_navigation_position' => $normalizedTopLevelPositions['vita'],
                'exhibitions_navigation_position' => $normalizedTopLevelPositions['exhibitions'],
                'updated_at' => $now,
            ]);
        }
        if ($blog !== null) {
            DB::table('blog_settings')->where('id', 1)->update([
                'navigation_position' => $normalizedTopLevelPositions['blog'],
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_sections');
    }
};
