<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_sections', function (Blueprint $table): void {
            $table->string('template', 32)->nullable()->after('type');
        });

        Schema::create('custom_page_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_section_id')->unique()->constrained('site_sections')->cascadeOnDelete();
            $table->jsonb('blocks')->default('[]');
            $table->timestampsTz();
        });

        Schema::create('journal_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_section_id')->unique()->constrained('site_sections')->cascadeOnDelete();
            $table->string('listing_title', 240)->nullable();
            $table->text('listing_intro')->nullable();
            $table->timestampsTz();
        });

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->foreignId('site_section_id')->nullable()->constrained('site_sections')->restrictOnDelete();
            $table->index(['site_section_id', 'position']);
        });

        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->foreignId('site_section_id')->nullable()->constrained('site_sections')->restrictOnDelete();
            $table->index(['site_section_id', 'position']);
        });

        DB::statement('DROP INDEX IF EXISTS site_sections_singleton_type_unique');
        DB::statement('DROP INDEX IF EXISTS blog_posts_public_position_unique');
        DB::statement('ALTER TABLE site_sections DROP CONSTRAINT IF EXISTS site_sections_type_check');

        $vita = DB::table('site_sections')->where('type', 'vita')->first();
        $contact = DB::table('site_sections')->where('type', 'contact')->first();
        $blog = DB::table('site_sections')->where('type', 'blog')->first();
        $exhibitions = DB::table('site_sections')->where('type', 'exhibitions')->first();
        $legacyBlogSettings = DB::table('blog_settings')->orderBy('id')->first();
        $now = now();

        if ($vita !== null) {
            DB::table('site_sections')->where('id', $vita->id)->update([
                'type' => 'custom',
                'template' => null,
                'title' => $this->displayTitle($vita, 'CV'),
                'updated_at' => $now,
            ]);
            DB::table('custom_page_settings')->insert([
                'site_section_id' => $vita->id,
                'blocks' => json_encode($this->vitaBlocks(), JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($contact !== null) {
            DB::table('site_sections')->where('id', $contact->id)->update([
                'type' => 'custom',
                'template' => null,
                'title' => $this->displayTitle($contact, 'Contact'),
                'updated_at' => $now,
            ]);
            DB::table('custom_page_settings')->insert([
                'site_section_id' => $contact->id,
                'blocks' => json_encode([$this->contactBlock(false)], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($blog !== null) {
            $title = $this->displayTitle($blog, 'Blog');
            DB::table('site_sections')->where('id', $blog->id)->update([
                'type' => 'journal',
                'template' => 'blog',
                'title' => $title,
                'updated_at' => $now,
            ]);
            DB::table('blog_posts')->whereNull('site_section_id')->update(['site_section_id' => $blog->id]);
            DB::table('journal_settings')->insert([
                'site_section_id' => $blog->id,
                'listing_title' => is_string($legacyBlogSettings?->listing_title ?? null)
                    ? $legacyBlogSettings->listing_title
                    : $title,
                'listing_intro' => $legacyBlogSettings?->listing_intro ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($exhibitions !== null) {
            $title = $this->displayTitle($exhibitions, 'Exhibitions');
            DB::table('site_sections')->where('id', $exhibitions->id)->update([
                'type' => 'journal',
                'template' => 'exhibitions',
                'title' => $title,
                'updated_at' => $now,
            ]);
            DB::table('exhibitions')->whereNull('site_section_id')->update(['site_section_id' => $exhibitions->id]);
            DB::table('journal_settings')->insert([
                'site_section_id' => $exhibitions->id,
                'listing_title' => $title,
                'listing_intro' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (DB::table('blog_posts')->whereNull('site_section_id')->exists()) {
            throw new RuntimeException('Every Blog post must belong to a Journal page.');
        }
        if (DB::table('exhibitions')->whereNull('site_section_id')->exists()) {
            throw new RuntimeException('Every Exhibition must belong to a Journal page.');
        }

        DB::statement('ALTER TABLE blog_posts ALTER COLUMN site_section_id SET NOT NULL');
        DB::statement('ALTER TABLE exhibitions ALTER COLUMN site_section_id SET NOT NULL');
        DB::statement('CREATE UNIQUE INDEX blog_posts_section_position_unique ON blog_posts (site_section_id, position)');
        DB::statement('CREATE UNIQUE INDEX exhibitions_section_position_unique ON exhibitions (site_section_id, position)');

        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_type_check CHECK (type IN ('home', 'gallery', 'navigation_group', 'custom', 'journal'))");
        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_template_check CHECK ((type = 'journal' AND template IN ('blog', 'exhibitions')) OR (type <> 'journal' AND template IS NULL))");
        DB::statement("CREATE UNIQUE INDEX site_sections_singleton_type_unique ON site_sections (type) WHERE type = 'home'");
    }

    public function down(): void
    {
        $unexpected = DB::table('site_sections')
            ->whereIn('type', ['custom', 'journal'])
            ->whereNotIn('slug', ['cv', 'contact', 'blog', 'exhibitions'])
            ->exists();
        if ($unexpected) {
            throw new RuntimeException('User-created configurable pages must be removed before rolling back their schema.');
        }

        DB::statement('DROP INDEX IF EXISTS blog_posts_section_position_unique');
        DB::statement('DROP INDEX IF EXISTS exhibitions_section_position_unique');
        DB::statement('ALTER TABLE blog_posts ALTER COLUMN site_section_id DROP NOT NULL');
        DB::statement('ALTER TABLE exhibitions ALTER COLUMN site_section_id DROP NOT NULL');

        DB::statement('DROP INDEX IF EXISTS site_sections_singleton_type_unique');
        DB::statement('ALTER TABLE site_sections DROP CONSTRAINT IF EXISTS site_sections_template_check');
        DB::statement('ALTER TABLE site_sections DROP CONSTRAINT IF EXISTS site_sections_type_check');

        $blogSection = DB::table('site_sections')->where('slug', 'blog')->where('type', 'journal')->first();
        if ($blogSection !== null) {
            $journalSettings = DB::table('journal_settings')->where('site_section_id', $blogSection->id)->first();
            if ($journalSettings !== null) {
                DB::table('blog_settings')->where('id', 1)->update([
                    'listing_title' => $journalSettings->listing_title,
                    'listing_intro' => $journalSettings->listing_intro,
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('site_sections')->where('slug', 'cv')->where('type', 'custom')->update(['type' => 'vita', 'template' => null]);
        DB::table('site_sections')->where('slug', 'contact')->where('type', 'custom')->update(['type' => 'contact', 'template' => null]);
        DB::table('site_sections')->where('slug', 'blog')->where('type', 'journal')->update(['type' => 'blog', 'template' => null]);
        DB::table('site_sections')->where('slug', 'exhibitions')->where('type', 'journal')->update(['type' => 'exhibitions', 'template' => null]);

        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_type_check CHECK (type IN ('home', 'gallery', 'navigation_group', 'vita', 'blog', 'exhibitions', 'contact'))");
        DB::statement("CREATE UNIQUE INDEX site_sections_singleton_type_unique ON site_sections (type) WHERE type IN ('home', 'vita', 'blog', 'exhibitions', 'contact')");
        DB::statement("CREATE UNIQUE INDEX blog_posts_public_position_unique ON blog_posts (position) WHERE state IN ('published', 'scheduled')");

        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->dropIndex(['site_section_id', 'position']);
            $table->dropConstrainedForeignId('site_section_id');
        });

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropIndex(['site_section_id', 'position']);
            $table->dropConstrainedForeignId('site_section_id');
        });

        Schema::dropIfExists('journal_settings');
        Schema::dropIfExists('custom_page_settings');

        Schema::table('site_sections', function (Blueprint $table): void {
            $table->dropColumn('template');
        });
    }

    private function displayTitle(object $section, string $fallback): string
    {
        $navigationLabel = $section->navigation_label ?? null;
        if (is_string($navigationLabel) && trim($navigationLabel) !== '') {
            return trim($navigationLabel);
        }

        $title = $section->title ?? null;

        return is_string($title) && trim($title) !== '' ? trim($title) : $fallback;
    }

    /** @return list<array<string, mixed>> */
    private function vitaBlocks(): array
    {
        $entries = DB::table('cv_entries')->orderBy('position')->orderBy('id')->get();
        $portraitId = $entries->first(fn (object $entry): bool => is_numeric($entry->image_media_asset_id ?? null))?->image_media_asset_id;

        $items = $entries->map(static fn (object $entry): array => [
            'visible' => ($entry->state ?? null) === 'published',
            'date' => $entry->year_text ?? null,
            'title' => (string) ($entry->title ?? ''),
            'meta' => $entry->organisation ?? null,
            'location' => $entry->location ?? null,
            'body' => $entry->body ?? null,
            'url' => $entry->external_url ?? null,
        ])->values()->all();

        $blocks = [];
        if ($items !== [] || is_numeric($portraitId)) {
            $blocks[] = [
                'type' => 'list',
                'title' => null,
                'divider' => true,
                'media_asset_id' => is_numeric($portraitId) ? (int) $portraitId : null,
                'items' => $items,
            ];
        }

        $profileBlocks = $this->jsonArray(DB::table('public_content_settings')->where('scope', 'vita')->value('profile_text_blocks'));
        $blocks[] = $this->contactBlock($profileBlocks !== []);

        foreach ($profileBlocks as $index => $profileBlock) {
            if (! is_array($profileBlock)) {
                continue;
            }
            $title = $profileBlock['title'] ?? null;
            $body = $profileBlock['body'] ?? null;
            if (! is_string($title) || trim($title) === '' || ! is_string($body) || trim($body) === '') {
                continue;
            }
            $blocks[] = [
                'type' => 'text',
                'title' => trim($title),
                'body' => $body,
                'divider' => $index < count($profileBlocks) - 1,
                'media_asset_id' => null,
            ];
        }

        return $blocks;
    }

    /** @return array<string, mixed> */
    private function contactBlock(bool $divider): array
    {
        return [
            'type' => 'contact',
            'divider' => $divider,
            'show_email' => true,
            'show_form' => true,
            'social_platforms' => $this->configuredSocialPlatforms(),
        ];
    }

    /** @return list<string> */
    private function configuredSocialPlatforms(): array
    {
        $links = $this->jsonArray(DB::table('public_content_settings')->where('scope', 'general')->value('social_links'));

        return collect($links)
            ->filter(static fn (mixed $link): bool => is_array($link) && ($link['visible'] ?? true) === true && is_string($link['platform'] ?? null))
            ->pluck('platform')
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<mixed> */
    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_is_list($value) ? $value : [];
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) && array_is_list($decoded) ? $decoded : [];
    }
};
