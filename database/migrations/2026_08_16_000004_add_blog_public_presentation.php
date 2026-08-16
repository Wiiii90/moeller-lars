<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_settings', function (Blueprint $table): void {
            $table->string('navigation_label', 120)->default('Blog');
            $table->integer('navigation_position')->default(110);
        });

        DB::statement('ALTER TABLE blog_settings ADD CONSTRAINT blog_settings_navigation_position_check CHECK (navigation_position >= 0)');
        DB::statement("ALTER TABLE blog_settings ADD CONSTRAINT blog_settings_navigation_label_check CHECK (NOT public_enabled OR btrim(navigation_label) <> '')");
        DB::statement("ALTER TABLE blog_posts DROP CONSTRAINT IF EXISTS blog_posts_scheduled_check");
        DB::statement("ALTER TABLE blog_posts ADD CONSTRAINT blog_posts_scheduled_check CHECK (state <> 'scheduled' OR (scheduled_at IS NOT NULL AND btrim(title) <> '' AND body IS NOT NULL AND btrim(body) <> ''))");
        DB::statement("CREATE UNIQUE INDEX blog_posts_public_position_unique ON blog_posts (position) WHERE state IN ('published', 'scheduled')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS blog_posts_public_position_unique');
        DB::statement('ALTER TABLE blog_posts DROP CONSTRAINT IF EXISTS blog_posts_scheduled_check');
        DB::statement("ALTER TABLE blog_posts ADD CONSTRAINT blog_posts_scheduled_check CHECK (state <> 'scheduled' OR scheduled_at IS NOT NULL)");

        Schema::table('blog_settings', function (Blueprint $table): void {
            $table->dropColumn(['navigation_label', 'navigation_position']);
        });
    }
};
