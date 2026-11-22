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
        // The actual date and the historical opening text remain untouched.
        // The former visibility cutoff is retired, not migrated into a new flag.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            Schema::table('exhibitions', static function (Blueprint $table): void {
                $table->dropColumn('show_vernissage');
            });

            return;
        }

        DB::transaction(static function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            foreach (['public', 'committed'] as $schema) {
                DB::statement("ALTER TABLE {$schema}.exhibitions DROP COLUMN show_vernissage");
            }
        });
    }

    public function down(): void
    {
        // Forward-only: restoring the cutoff would change the public contract.
    }
};
