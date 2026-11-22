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
            Schema::table('exhibition_organizer_presets', static function (Blueprint $table): void {
                $table->dropColumn('position');
            });
            Schema::table('exhibition_venue_presets', static function (Blueprint $table): void {
                $table->dropColumn('position');
            });
            Schema::table('exhibitions', static function (Blueprint $table): void {
                $table->boolean('description_enabled')->default(false);
            });
            DB::table('exhibitions')->whereNotNull('description')
                ->whereRaw("TRIM(description) <> ''")->update(['description_enabled' => true]);

            return;
        }

        DB::transaction(static function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);

            // The catalogs have no editorial ranking. Their order is defined
            // solely by case-insensitive names; the original migration remains
            // immutable and these obsolete columns are retired forward.
            Schema::table('exhibition_organizer_presets', static function (Blueprint $table): void {
                $table->dropColumn('position');
            });
            Schema::table('exhibition_venue_presets', static function (Blueprint $table): void {
                $table->dropColumn('position');
            });

            // This flag must travel with Exhibition Working and Committed data.
            // Existing authored descriptions retain their public visibility.
            foreach (['public', 'committed'] as $schema) {
                DB::statement("ALTER TABLE {$schema}.exhibitions
                    ADD COLUMN description_enabled boolean NOT NULL DEFAULT false");
                DB::statement("UPDATE {$schema}.exhibitions
                    SET description_enabled = true
                    WHERE description IS NOT NULL AND BTRIM(description) <> ''");
            }
        });
    }

    public function down(): void
    {
        // Forward only: do not silently discard the artist's visibility choices.
    }
};
