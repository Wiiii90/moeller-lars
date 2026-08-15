<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artwork_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 160);
            $table->string('state', 32)->default('hidden');
            $table->integer('position')->default(0);
            $table->text('description')->nullable();
            $table->bigInteger('legacy_id')->nullable();
            $table->string('legacy_source', 160)->nullable();
            $table->string('migration_batch_id', 120)->nullable();
            $table->timestampTz('migrated_at')->nullable();
            $table->timestampsTz();

            $table->index(['state', 'position']);
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('storage_key', 500)->unique();
            $table->string('original_filename', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->string('state', 32)->default('quarantined');
            $table->string('alt_text', 500)->nullable();
            $table->string('copyright_notice', 500)->nullable();
            $table->string('credit', 240)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->decimal('focal_point_x', 5, 4)->nullable();
            $table->decimal('focal_point_y', 5, 4)->nullable();
            $table->bigInteger('legacy_id')->nullable();
            $table->string('legacy_source', 160)->nullable();
            $table->string('legacy_path', 500)->nullable();
            $table->string('legacy_filename', 255)->nullable();
            $table->unsignedBigInteger('legacy_byte_size')->nullable();
            $table->string('migration_batch_id', 120)->nullable();
            $table->timestampTz('migrated_at')->nullable();
            $table->timestampsTz();

            $table->index(['state', 'mime_type']);
            $table->index('sha256');
        });

        Schema::create('artworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artwork_category_id')
                ->constrained('artwork_categories')
                ->restrictOnDelete();
            $table->string('slug', 180)->unique();
            $table->string('title', 240);
            $table->string('medium', 240)->nullable();
            $table->string('dimensions', 240)->nullable();
            $table->text('description')->nullable();
            $table->string('state', 32)->default('draft');
            $table->integer('position');
            $table->string('legacy_date_raw', 32)->nullable();
            $table->date('work_date')->nullable();
            $table->string('date_precision', 16)->default('unknown');
            $table->bigInteger('legacy_id')->nullable();
            $table->string('legacy_source', 160)->nullable();
            $table->string('migration_batch_id', 120)->nullable();
            $table->timestampTz('migrated_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->index(['artwork_category_id', 'state', 'work_date', 'position']);
            $table->index(['state', 'work_date', 'position']);
        });

        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_asset_id')
                ->constrained('media_assets')
                ->restrictOnDelete();
            $table->string('variant_kind', 32);
            $table->string('storage_key', 500)->unique();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->string('transform_profile', 120);
            $table->string('state', 32)->default('available');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestampsTz();

            $table->unique(['media_asset_id', 'variant_kind', 'transform_profile']);
            $table->index(['media_asset_id', 'state']);
        });

        Schema::create('artwork_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artwork_id')
                ->constrained('artworks')
                ->restrictOnDelete();
            $table->foreignId('media_asset_id')
                ->constrained('media_assets')
                ->restrictOnDelete();
            $table->string('role', 32)->default('additional');
            $table->integer('position');
            $table->string('alt_text_override', 500)->nullable();
            $table->timestampsTz();

            $table->unique(['artwork_id', 'position']);
            $table->unique(['artwork_id', 'media_asset_id']);
        });

        Schema::create('exhibitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 180)->unique();
            $table->string('title', 240);
            $table->string('state', 32)->default('draft');
            $table->integer('position');
            $table->string('venue', 240)->nullable();
            $table->string('city', 160)->nullable();
            $table->string('country', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('external_url', 2048)->nullable();
            $table->foreignId('hero_media_asset_id')
                ->nullable()
                ->constrained('media_assets')
                ->restrictOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('date_text', 160)->nullable();
            $table->bigInteger('legacy_id')->nullable();
            $table->string('legacy_source', 160)->nullable();
            $table->string('migration_batch_id', 120)->nullable();
            $table->timestampTz('migrated_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->index(['state', 'starts_on', 'position']);
        });

        Schema::create('cv_entries', function (Blueprint $table) {
            $table->id();
            $table->string('section', 120);
            $table->string('title', 240);
            $table->string('state', 32)->default('draft');
            $table->integer('position');
            $table->string('date_precision', 16)->default('unknown');
            $table->string('organisation', 240)->nullable();
            $table->string('location', 240)->nullable();
            $table->text('body')->nullable();
            $table->string('year_text', 80)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->bigInteger('legacy_id')->nullable();
            $table->string('legacy_source', 160)->nullable();
            $table->string('migration_batch_id', 120)->nullable();
            $table->timestampTz('migrated_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->index(['state', 'section', 'position']);
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 220)->unique();
            $table->string('title', 240);
            $table->text('body')->nullable();
            $table->string('state', 32)->default('draft');
            $table->integer('position');
            $table->text('excerpt')->nullable();
            $table->foreignId('cover_media_asset_id')
                ->nullable()
                ->constrained('media_assets')
                ->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('scheduled_at')->nullable();
            $table->bigInteger('legacy_id')->nullable();
            $table->string('legacy_source', 160)->nullable();
            $table->string('migration_batch_id', 120)->nullable();
            $table->timestampTz('migrated_at')->nullable();
            $table->timestampsTz();

            $table->index(['state', 'published_at', 'position']);
        });

        Schema::create('blog_settings', function (Blueprint $table) {
            $table->smallInteger('id')->primary();
            $table->boolean('public_enabled')->default(false);
            $table->string('listing_title', 240)->nullable();
            $table->text('listing_intro')->nullable();
            $table->timestampsTz();
        });

        DB::table('blog_settings')->insert([
            'id' => 1,
            'public_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source_path', 512)->unique();
            $table->string('target_path', 2048);
            $table->smallInteger('status_code')->default(301);
            $table->boolean('enabled')->default(true);
            $table->string('reason', 240)->nullable();
            $table->bigInteger('legacy_id')->nullable();
            $table->string('legacy_source', 160)->nullable();
            $table->string('migration_batch_id', 120)->nullable();
            $table->timestampTz('migrated_at')->nullable();
            $table->timestampsTz();

            $table->index(['enabled', 'source_path']);
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('action', 80);
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestampTz('occurred_at');
            $table->string('request_id', 120)->nullable();
            $table->jsonb('metadata')->nullable();

            $table->index(['entity_type', 'entity_id', 'occurred_at']);
            $table->index(['admin_user_id', 'occurred_at']);
            $table->index('occurred_at');
        });

        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->string('metric_name', 80);
            $table->string('source', 40);
            $table->decimal('value', 20, 4);
            $table->string('unit', 24);
            $table->dateTimeTz('calculated_at');
            $table->string('dimension_key', 160)->nullable();
            $table->unsignedBigInteger('sample_count')->nullable();

            $table->index(['metric_date', 'metric_name']);
        });

        DB::statement("ALTER TABLE artwork_categories ADD CONSTRAINT artwork_categories_state_check CHECK (state IN ('published', 'hidden'))");
        DB::statement('ALTER TABLE artwork_categories ADD CONSTRAINT artwork_categories_position_check CHECK (position >= 0)');
        DB::statement("ALTER TABLE artworks ADD CONSTRAINT artworks_state_check CHECK (state IN ('draft', 'published', 'hidden', 'archived'))");
        DB::statement('ALTER TABLE artworks ADD CONSTRAINT artworks_position_check CHECK (position >= 0)');
        DB::statement("ALTER TABLE artworks ADD CONSTRAINT artworks_date_precision_check CHECK (date_precision IN ('unknown', 'year', 'month', 'day'))");
        DB::statement('ALTER TABLE media_assets ADD CONSTRAINT media_assets_byte_size_check CHECK (byte_size > 0)');
        DB::statement('ALTER TABLE media_assets ADD CONSTRAINT media_assets_dimensions_check CHECK ((width IS NULL OR width > 0) AND (height IS NULL OR height > 0))');
        DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_sha256_check CHECK (sha256 ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_state_check CHECK (state IN ('available', 'quarantined', 'deleted'))");
        DB::statement('ALTER TABLE media_assets ADD CONSTRAINT media_assets_focal_x_check CHECK (focal_point_x IS NULL OR focal_point_x BETWEEN 0 AND 1)');
        DB::statement('ALTER TABLE media_assets ADD CONSTRAINT media_assets_focal_y_check CHECK (focal_point_y IS NULL OR focal_point_y BETWEEN 0 AND 1)');
        DB::statement('ALTER TABLE media_variants ADD CONSTRAINT media_variants_byte_size_check CHECK (byte_size > 0)');
        DB::statement('ALTER TABLE media_variants ADD CONSTRAINT media_variants_dimensions_check CHECK ((width IS NULL OR width > 0) AND (height IS NULL OR height > 0))');
        DB::statement("ALTER TABLE media_variants ADD CONSTRAINT media_variants_sha256_check CHECK (sha256 ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE media_variants ADD CONSTRAINT media_variants_state_check CHECK (state IN ('available', 'stale', 'deleted'))");
        DB::statement("ALTER TABLE artwork_media ADD CONSTRAINT artwork_media_role_check CHECK (role IN ('primary', 'additional'))");
        DB::statement('ALTER TABLE artwork_media ADD CONSTRAINT artwork_media_position_check CHECK (position >= 0)');
        DB::statement("ALTER TABLE exhibitions ADD CONSTRAINT exhibitions_state_check CHECK (state IN ('draft', 'published', 'hidden', 'archived'))");
        DB::statement('ALTER TABLE exhibitions ADD CONSTRAINT exhibitions_position_check CHECK (position >= 0)');
        DB::statement('ALTER TABLE exhibitions ADD CONSTRAINT exhibitions_dates_check CHECK (ends_on IS NULL OR starts_on IS NULL OR ends_on >= starts_on)');
        DB::statement("ALTER TABLE cv_entries ADD CONSTRAINT cv_entries_state_check CHECK (state IN ('draft', 'published', 'hidden', 'archived'))");
        DB::statement('ALTER TABLE cv_entries ADD CONSTRAINT cv_entries_position_check CHECK (position >= 0)');
        DB::statement("ALTER TABLE cv_entries ADD CONSTRAINT cv_entries_date_precision_check CHECK (date_precision IN ('unknown', 'year', 'month', 'day'))");
        DB::statement("ALTER TABLE blog_posts ADD CONSTRAINT blog_posts_state_check CHECK (state IN ('draft', 'scheduled', 'published', 'unpublished', 'archived'))");
        DB::statement('ALTER TABLE blog_posts ADD CONSTRAINT blog_posts_position_check CHECK (position >= 0)');
        DB::statement("ALTER TABLE blog_posts ADD CONSTRAINT blog_posts_published_check CHECK (state <> 'published' OR (body IS NOT NULL AND btrim(body) <> '' AND published_at IS NOT NULL))");
        DB::statement("ALTER TABLE blog_posts ADD CONSTRAINT blog_posts_scheduled_check CHECK (state <> 'scheduled' OR scheduled_at IS NOT NULL)");
        DB::statement('ALTER TABLE blog_settings ADD CONSTRAINT blog_settings_singleton_check CHECK (id = 1)');
        DB::statement('ALTER TABLE redirects ADD CONSTRAINT redirects_status_code_check CHECK (status_code IN (301, 302, 308))');
        DB::statement("ALTER TABLE redirects ADD CONSTRAINT redirects_paths_check CHECK (source_path LIKE '/%' AND source_path NOT LIKE '//%' AND source_path NOT LIKE '%#%' AND source_path NOT LIKE '%?%' AND source_path <> target_path AND ((target_path LIKE '/%' AND target_path NOT LIKE '//%' AND target_path NOT LIKE '%?%' AND target_path NOT LIKE '%#%') OR (target_path LIKE 'https://%' AND target_path NOT LIKE '%#%')))");
        DB::statement('ALTER TABLE daily_metrics ADD CONSTRAINT daily_metrics_value_check CHECK (value >= 0)');
        DB::statement("ALTER TABLE daily_metrics ADD CONSTRAINT daily_metrics_source_check CHECK (source IN ('local_log', 'application', 'matomo_cache'))");
        DB::statement("ALTER TABLE daily_metrics ADD CONSTRAINT daily_metrics_name_check CHECK (metric_name ~ '^(bot|error|performance|security|operation|storage|deployment|matomo_cache)([:._-].*)?$')");
        DB::statement('ALTER TABLE daily_metrics ADD CONSTRAINT daily_metrics_sample_count_check CHECK (sample_count IS NULL OR sample_count >= 0)');

        DB::statement('CREATE UNIQUE INDEX artwork_categories_legacy_unique ON artwork_categories (legacy_source, legacy_id) WHERE legacy_source IS NOT NULL AND legacy_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX artworks_legacy_unique ON artworks (legacy_source, legacy_id) WHERE legacy_source IS NOT NULL AND legacy_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX media_assets_legacy_unique ON media_assets (legacy_source, legacy_id) WHERE legacy_source IS NOT NULL AND legacy_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX exhibitions_legacy_unique ON exhibitions (legacy_source, legacy_id) WHERE legacy_source IS NOT NULL AND legacy_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX cv_entries_legacy_unique ON cv_entries (legacy_source, legacy_id) WHERE legacy_source IS NOT NULL AND legacy_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX blog_posts_legacy_unique ON blog_posts (legacy_source, legacy_id) WHERE legacy_source IS NOT NULL AND legacy_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX redirects_legacy_unique ON redirects (legacy_source, legacy_id) WHERE legacy_source IS NOT NULL AND legacy_id IS NOT NULL');
        DB::statement("CREATE UNIQUE INDEX artwork_media_primary_unique ON artwork_media (artwork_id) WHERE role = 'primary'");
        DB::statement('CREATE UNIQUE INDEX daily_metrics_unique ON daily_metrics (metric_date, metric_name, source, dimension_key) NULLS NOT DISTINCT');
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_metrics');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('blog_settings');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('cv_entries');
        Schema::dropIfExists('exhibitions');
        Schema::dropIfExists('artwork_media');
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('artworks');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('artwork_categories');
    }
};
