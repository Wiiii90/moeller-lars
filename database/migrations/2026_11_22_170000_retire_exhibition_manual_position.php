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
        // Keep historical migrations, artist date values and version payloads.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            Schema::table('exhibitions', static function (Blueprint $table): void {
                $table->dropColumn('position');
            });

            return;
        }

        DB::transaction(static function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            foreach (['public', 'committed'] as $schema) {
                DB::statement("ALTER TABLE {$schema}.exhibitions
                    DROP CONSTRAINT IF EXISTS exhibitions_position_check,
                    DROP COLUMN position");
            }
        });
    }

    public function down(): void
    {
        // Forward-only: retired manual ranks cannot be recovered faithfully.
    }
};
