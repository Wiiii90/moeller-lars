<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE public_content_settings DROP CONSTRAINT IF EXISTS public_content_settings_singleton_check');

        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->string('scope', 32)->nullable();
        });

        DB::table('public_content_settings')->where('id', 1)->update(['scope' => 'general']);

        DB::table('public_content_settings')->insert([
            'id' => 2,
            'scope' => 'contact',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::statement(<<<'SQL'
            UPDATE public_content_settings AS target
            SET contact_state = source.contact_state,
                contact_status_text = source.contact_status_text,
                contact_icon = source.contact_icon
            FROM public_content_settings AS source
            WHERE target.scope = 'contact' AND source.scope = 'general'
        SQL);

        DB::table('public_content_settings')->insert([
            'id' => 3,
            'scope' => 'vita',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::statement(<<<'SQL'
            UPDATE public_content_settings AS target
            SET profile_text_blocks = source.profile_text_blocks
            FROM public_content_settings AS source
            WHERE target.scope = 'vita' AND source.scope = 'general'
        SQL);

        DB::statement('ALTER TABLE public_content_settings ALTER COLUMN scope SET NOT NULL');
        DB::statement('ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_scope_unique UNIQUE (scope)');
        DB::statement("ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_scope_check CHECK (scope IN ('general', 'contact', 'vita'))");

        Schema::table('blog_settings', function (Blueprint $table): void {
            $table->foreignId('site_section_id')
                ->nullable()
                ->constrained('site_sections')
                ->restrictOnDelete();
        });

        $blogSectionId = DB::table('site_sections')->where('type', 'blog')->value('id');
        if (! is_numeric($blogSectionId)) {
            throw new RuntimeException('The canonical Blog SiteSection must exist before normalizing Blog settings.');
        }

        DB::table('blog_settings')->update(['site_section_id' => (int) $blogSectionId]);
        DB::statement('ALTER TABLE blog_settings ALTER COLUMN site_section_id SET NOT NULL');
        DB::statement('ALTER TABLE blog_settings ADD CONSTRAINT blog_settings_site_section_unique UNIQUE (site_section_id)');
        DB::statement('ALTER TABLE blog_settings DROP CONSTRAINT IF EXISTS blog_settings_singleton_check');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE blog_settings DROP CONSTRAINT IF EXISTS blog_settings_site_section_unique');
        DB::statement('ALTER TABLE blog_settings ADD CONSTRAINT blog_settings_singleton_check CHECK (id = 1)');

        Schema::table('blog_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('site_section_id');
        });

        DB::statement('ALTER TABLE public_content_settings DROP CONSTRAINT IF EXISTS public_content_settings_scope_check');
        DB::statement('ALTER TABLE public_content_settings DROP CONSTRAINT IF EXISTS public_content_settings_scope_unique');
        DB::statement(<<<'SQL'
            UPDATE public_content_settings AS target
            SET contact_state = source.contact_state,
                contact_status_text = source.contact_status_text,
                contact_icon = source.contact_icon
            FROM public_content_settings AS source
            WHERE target.scope = 'general' AND source.scope = 'contact'
        SQL);
        DB::statement(<<<'SQL'
            UPDATE public_content_settings AS target
            SET profile_text_blocks = source.profile_text_blocks
            FROM public_content_settings AS source
            WHERE target.scope = 'general' AND source.scope = 'vita'
        SQL);
        DB::table('public_content_settings')->whereIn('scope', ['contact', 'vita'])->delete();

        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->dropColumn('scope');
        });

        DB::statement('ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_singleton_check CHECK (id = 1)');
    }
};
