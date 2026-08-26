<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_post_id')->nullable()->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('exhibition_id')->nullable()->constrained('exhibitions')->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->restrictOnDelete();
            $table->string('role', 24);
            $table->integer('position')->default(0);
            $table->string('alt_text_override', 500)->nullable();
            $table->uuid('embed_key')->nullable();
            $table->timestampsTz();

            $table->index('media_asset_id');
            $table->unique('embed_key');
        });

        DB::statement("ALTER TABLE journal_entry_media ADD CONSTRAINT journal_entry_media_owner_check CHECK ((CASE WHEN blog_post_id IS NULL THEN 0 ELSE 1 END) + (CASE WHEN exhibition_id IS NULL THEN 0 ELSE 1 END) = 1)");
        DB::statement("ALTER TABLE journal_entry_media ADD CONSTRAINT journal_entry_media_role_check CHECK (role IN ('cover', 'inline', 'gallery'))");
        DB::statement('ALTER TABLE journal_entry_media ADD CONSTRAINT journal_entry_media_position_check CHECK (position >= 0)');
        DB::statement("ALTER TABLE journal_entry_media ADD CONSTRAINT journal_entry_media_embed_check CHECK ((role = 'inline' AND embed_key IS NOT NULL) OR (role <> 'inline' AND embed_key IS NULL))");
        DB::statement("CREATE UNIQUE INDEX journal_entry_media_blog_cover_unique ON journal_entry_media (blog_post_id) WHERE blog_post_id IS NOT NULL AND role = 'cover'");
        DB::statement("CREATE UNIQUE INDEX journal_entry_media_exhibition_cover_unique ON journal_entry_media (exhibition_id) WHERE exhibition_id IS NOT NULL AND role = 'cover'");
        DB::statement("CREATE UNIQUE INDEX journal_entry_media_blog_role_position_unique ON journal_entry_media (blog_post_id, role, position) WHERE blog_post_id IS NOT NULL");
        DB::statement("CREATE UNIQUE INDEX journal_entry_media_exhibition_role_position_unique ON journal_entry_media (exhibition_id, role, position) WHERE exhibition_id IS NOT NULL");

        DB::statement(<<<'SQL'
            INSERT INTO journal_entry_media (blog_post_id, media_asset_id, role, position, created_at, updated_at)
            SELECT id, cover_media_asset_id, 'cover', 0, NOW(), NOW()
            FROM blog_posts
            WHERE cover_media_asset_id IS NOT NULL
            ON CONFLICT DO NOTHING
        SQL);

        DB::statement(<<<'SQL'
            INSERT INTO journal_entry_media (exhibition_id, media_asset_id, role, position, alt_text_override, created_at, updated_at)
            SELECT exhibition_id,
                   media_asset_id,
                   CASE WHEN role = 'hero' THEN 'cover' ELSE 'gallery' END,
                   CASE WHEN role = 'hero' THEN 0 ELSE position END,
                   alt_text_override,
                   created_at,
                   updated_at
            FROM exhibition_media
            ON CONFLICT DO NOTHING
        SQL);
    }

    public function down(): void
    {
        // Forward-only data migration: legacy references remain intact for compatibility.
    }
};
