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
        // Preserve published migration history and default to the prior
        // active behavior for every existing Exhibition Journal.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            Schema::table('journal_settings', static function (Blueprint $table): void {
                $table->boolean('default_settings_enabled')->default(true);
                $table->dropColumn(['default_gallery_enabled', 'default_show_vernissage']);
            });

            return;
        }

        DB::transaction(static function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            foreach (['public', 'committed'] as $schema) {
                DB::statement("ALTER TABLE {$schema}.journal_settings
                    ADD COLUMN default_settings_enabled boolean NOT NULL DEFAULT true,
                    DROP COLUMN default_gallery_enabled,
                    DROP COLUMN default_show_vernissage");
            }
        });
    }

    public function down(): void
    {
        // Forward only: do not restore retired global settings.
    }
};
