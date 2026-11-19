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
        if (DB::connection()->getDriverName() !== 'pgsql') {
            Schema::table('artwork_categories', static function (Blueprint $table): void {
                $table->boolean('viewer_navigation_cyclic')->default(false);
                $table->dropColumn(['viewer_zoom_enabled', 'video_sound_enabled']);
            });

            return;
        }

        DB::transaction(static function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);

            foreach (['public', 'committed'] as $schema) {
                DB::statement("ALTER TABLE {$schema}.artwork_categories
                    ADD COLUMN viewer_navigation_cyclic boolean NOT NULL DEFAULT false,
                    DROP COLUMN viewer_zoom_enabled,
                    DROP COLUMN video_sound_enabled");
            }
        });
    }

    public function down(): void
    {
        // Forward-only: retired Gallery presentation switches are not restored.
        // Historical publication checkpoints remain immutable.
    }
};
