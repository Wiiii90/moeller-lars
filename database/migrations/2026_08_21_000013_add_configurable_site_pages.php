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

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->foreignId('site_section_id')->nullable()->constrained('site_sections')->restrictOnDelete();
        });

        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->foreignId('site_section_id')->nullable()->constrained('site_sections')->restrictOnDelete();
        });

        $blogSectionId = DB::table('site_sections')->where('type', 'blog')->value('id');
        if (is_numeric($blogSectionId)) {
            DB::table('blog_posts')->whereNull('site_section_id')->update(['site_section_id' => (int) $blogSectionId]);
        }

        $exhibitionsSectionId = DB::table('site_sections')->where('type', 'exhibitions')->value('id');
        if (is_numeric($exhibitionsSectionId)) {
            DB::table('exhibitions')->whereNull('site_section_id')->update(['site_section_id' => (int) $exhibitionsSectionId]);
        }

        DB::statement('ALTER TABLE site_sections DROP CONSTRAINT IF EXISTS site_sections_type_check');
        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_type_check CHECK (type IN ('home', 'gallery', 'navigation_group', 'vita', 'blog', 'exhibitions', 'contact', 'custom', 'journal'))");
        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_template_check CHECK ((type = 'journal' AND template IN ('blog', 'exhibitions')) OR (type <> 'journal' AND template IS NULL))");
    }

    public function down(): void
    {
        if (DB::table('site_sections')->whereIn('type', ['custom', 'journal'])->exists()) {
            throw new RuntimeException('Configurable pages must be removed before rolling back their schema.');
        }

        DB::statement('ALTER TABLE site_sections DROP CONSTRAINT IF EXISTS site_sections_template_check');
        DB::statement('ALTER TABLE site_sections DROP CONSTRAINT IF EXISTS site_sections_type_check');
        DB::statement("ALTER TABLE site_sections ADD CONSTRAINT site_sections_type_check CHECK (type IN ('home', 'gallery', 'navigation_group', 'vita', 'blog', 'exhibitions', 'contact'))");

        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('site_section_id');
        });

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('site_section_id');
        });

        Schema::dropIfExists('custom_page_settings');

        Schema::table('site_sections', function (Blueprint $table): void {
            $table->dropColumn('template');
        });
    }
};
