<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // blog_settings belonged to the application's original dedicated Blog
        // settings model. Its canonical values were migrated into SiteSection /
        // JournalSetting before this retirement and it is no longer runtime or
        // Publication authority.
        Schema::dropIfExists('blog_settings');
    }

    public function down(): void
    {
        // Recreate the schema that existed immediately before retirement. The
        // obsolete duplicate values are intentionally not re-materialized from
        // canonical Journal state, and the removed site_sections FK stays removed.
        Schema::create('blog_settings', function (Blueprint $table): void {
            $table->smallInteger('id')->primary();
            $table->boolean('public_enabled')->default(false);
            $table->string('listing_title', 240)->nullable();
            $table->text('listing_intro')->nullable();
            $table->timestampsTz();
            $table->string('navigation_label', 120)->default('Blog');
            $table->integer('navigation_position')->default(110);
            $table->unsignedBigInteger('site_section_id')->unique();
        });

        DB::statement('ALTER TABLE blog_settings ADD CONSTRAINT blog_settings_navigation_position_check CHECK (navigation_position >= 0)');
        DB::statement("ALTER TABLE blog_settings ADD CONSTRAINT blog_settings_navigation_label_check CHECK (NOT public_enabled OR btrim(navigation_label) <> '')");
    }
};
