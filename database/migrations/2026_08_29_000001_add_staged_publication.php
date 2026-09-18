<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historical publication contract as introduced by this migration.
     *
     * Keep these lists frozen here. A historical migration must not change its
     * migrate:fresh behavior when the runtime PublicationSnapshot contract evolves.
     * Later publication-schema changes belong in later forward migrations.
     *
     * @var list<string>
     */
    private const TRACKED_TABLES = [
        'artwork_categories',
        'artworks',
        'artwork_media',
        'media_assets',
        'media_variants',
        'site_sections',
        'custom_page_settings',
        'journal_settings',
        'journal_entry_media',
        'home_presentation_settings',
        'cv_entries',
        'exhibitions',
        'exhibition_media',
        'blog_posts',
        'public_content_settings',
        'redirects',
    ];

    /** @var list<string> */
    private const TRACKED_AUDIT_ENTITY_TYPES = [
        'artwork_category',
        'artwork',
        'artwork_media',
        'media_asset',
        'media_variant',
        'site_section',
        'custom_page_setting',
        'journal_setting',
        'journal_entry_media',
        'home_presentation_setting',
        'cv_entry',
        'exhibition',
        'exhibition_media',
        'blog_post',
        'public_content_setting',
        'redirect',
    ];

    public function up(): void
    {
        Schema::create('publication_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message', 240)->nullable();
            $table->unsignedInteger('change_count')->default(0);
            $table->timestampTz('published_at');
        });

        Schema::create('publication_checkpoint_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('publication_checkpoint_id')->constrained('publication_checkpoints')->cascadeOnDelete();
            $table->foreignId('audit_event_id')->unique()->constrained('audit_events')->restrictOnDelete();
            $table->timestampTz('created_at');
        });

        Schema::create('publication_media_cleanups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('media_asset_id')->index();
            $table->text('storage_key')->unique();
            $table->timestampTz('created_at');
        });

        DB::statement('DROP SCHEMA IF EXISTS committed CASCADE');
        DB::statement('CREATE SCHEMA committed');

        foreach (self::TRACKED_TABLES as $table) {
            DB::statement("CREATE TABLE committed.{$table} (LIKE public.{$table} INCLUDING ALL)");
            DB::statement("INSERT INTO committed.{$table} SELECT * FROM public.{$table}");
        }

        $checkpointId = DB::table('publication_checkpoints')->insertGetId([
            'admin_user_id' => null,
            'message' => 'Initial public state',
            'change_count' => 0,
            'published_at' => now(),
        ]);

        $createdAt = now();
        DB::table('audit_events')
            ->whereIn('entity_type', self::TRACKED_AUDIT_ENTITY_TYPES)
            ->orderBy('id')
            ->pluck('id')
            ->chunk(500)
            ->each(function ($eventIds) use ($checkpointId, $createdAt): void {
                $rows = collect($eventIds)
                    ->map(static fn (mixed $id): array => [
                        'publication_checkpoint_id' => $checkpointId,
                        'audit_event_id' => (int) $id,
                        'created_at' => $createdAt,
                    ])
                    ->all();

                if ($rows !== []) {
                    DB::table('publication_checkpoint_events')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS committed CASCADE');
        Schema::dropIfExists('publication_media_cleanups');
        Schema::dropIfExists('publication_checkpoint_events');
        Schema::dropIfExists('publication_checkpoints');
    }
};
