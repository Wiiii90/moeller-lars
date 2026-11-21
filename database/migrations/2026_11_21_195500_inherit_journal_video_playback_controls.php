<?php

use App\Domain\Publication\PublicationSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep all existing per-media values as explicit historical overrides.
        // New media inherit the Journal's default until an editor overrides it.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            Schema::table('journal_settings', static function (Blueprint $table): void {
                $table->boolean('default_video_controls_enabled')->default(true);
            });

            Schema::table('journal_entry_media', static function (Blueprint $table): void {
                $table->boolean('video_controls_enabled')->nullable()->default(null)->change();
            });

            return;
        }

        DB::transaction(static function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);

            foreach (['public', 'committed'] as $schema) {
                DB::statement("ALTER TABLE {$schema}.journal_settings
                    ADD COLUMN default_video_controls_enabled boolean NOT NULL DEFAULT true");
                DB::statement("ALTER TABLE {$schema}.journal_entry_media
                    ALTER COLUMN video_controls_enabled DROP NOT NULL,
                    ALTER COLUMN video_controls_enabled DROP DEFAULT");
            }
        });
    }

    public function down(): void
    {
        // Forward only: restoring NOT NULL would erase intentional inheritance.
    }
};
