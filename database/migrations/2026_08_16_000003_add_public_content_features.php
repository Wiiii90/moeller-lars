<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_content_settings', function (Blueprint $table): void {
            $table->smallInteger('id')->primary();
            $table->boolean('cv_enabled')->default(false);
            $table->boolean('exhibitions_enabled')->default(false);
            $table->string('cv_navigation_label', 120)->default('CV & Exhibitions');
            $table->integer('cv_navigation_position')->default(100);
            $table->string('contact_state', 32)->default('hidden');
            $table->string('contact_status_text', 500)->nullable();
            $table->string('contact_icon', 32)->default('construction');
            $table->timestampsTz();
        });

        DB::table('public_content_settings')->insert([
            'id' => 1,
            'cv_enabled' => false,
            'exhibitions_enabled' => false,
            'cv_navigation_label' => 'CV & Exhibitions',
            'cv_navigation_position' => 100,
            'contact_state' => 'hidden',
            'contact_status_text' => null,
            'contact_icon' => 'construction',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->string('directions_url', 2048)->nullable();
            $table->dropConstrainedForeignId('hero_media_asset_id');
        });

        Schema::create('exhibition_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exhibition_id')->constrained('exhibitions')->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->restrictOnDelete();
            $table->string('role', 32)->default('additional');
            $table->integer('position');
            $table->string('alt_text_override', 500)->nullable();
            $table->timestampsTz();

            $table->unique(['exhibition_id', 'position']);
            $table->unique(['exhibition_id', 'media_asset_id']);
        });

        DB::statement('ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_singleton_check CHECK (id = 1)');
        DB::statement('ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_navigation_position_check CHECK (cv_navigation_position >= 0)');
        DB::statement("ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_navigation_label_check CHECK ((NOT cv_enabled AND NOT exhibitions_enabled) OR btrim(cv_navigation_label) <> '')");
        DB::statement("ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_contact_state_check CHECK (contact_state IN ('enabled', 'under_construction', 'hidden'))");
        DB::statement("ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_contact_icon_check CHECK (contact_icon IN ('construction', 'mail', 'info'))");
        DB::statement("ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_contact_status_check CHECK (contact_state <> 'under_construction' OR (contact_status_text IS NOT NULL AND btrim(contact_status_text) <> ''))");

        DB::statement("ALTER TABLE exhibitions ADD CONSTRAINT exhibitions_external_url_check CHECK (external_url IS NULL OR external_url ~ '^https?://')");
        DB::statement("ALTER TABLE exhibitions ADD CONSTRAINT exhibitions_directions_url_check CHECK (directions_url IS NULL OR directions_url ~ '^https?://')");
        DB::statement("ALTER TABLE exhibitions ADD CONSTRAINT exhibitions_published_content_check CHECK (state <> 'published' OR (btrim(title) <> '' AND date_text IS NOT NULL AND btrim(date_text) <> ''))");
        DB::statement("ALTER TABLE cv_entries ADD CONSTRAINT cv_entries_published_content_check CHECK (state <> 'published' OR (btrim(section) <> '' AND btrim(title) <> '' AND year_text IS NOT NULL AND btrim(year_text) <> ''))");
        DB::statement("ALTER TABLE exhibition_media ADD CONSTRAINT exhibition_media_role_check CHECK (role IN ('hero', 'additional'))");
        DB::statement('ALTER TABLE exhibition_media ADD CONSTRAINT exhibition_media_position_check CHECK (position >= 0)');

        DB::statement("CREATE UNIQUE INDEX cv_entries_published_position_unique ON cv_entries (position) WHERE state = 'published'");
        DB::statement("CREATE UNIQUE INDEX exhibitions_published_position_unique ON exhibitions (position) WHERE state = 'published'");
        DB::statement("CREATE UNIQUE INDEX exhibition_media_hero_unique ON exhibition_media (exhibition_id) WHERE role = 'hero'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS exhibition_media_hero_unique');
        DB::statement('DROP INDEX IF EXISTS exhibitions_published_position_unique');
        DB::statement('DROP INDEX IF EXISTS cv_entries_published_position_unique');

        Schema::dropIfExists('exhibition_media');

        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->dropColumn('directions_url');
            $table->foreignId('hero_media_asset_id')
                ->nullable()
                ->constrained('media_assets')
                ->restrictOnDelete();
        });

        Schema::dropIfExists('public_content_settings');
    }
};
