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
                $table->string('add_placement', 32)->default('follow');
            });

            return;
        }

        DB::transaction(static function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);

            foreach (['public', 'committed'] as $schema) {
                DB::statement("ALTER TABLE {$schema}.artwork_categories
                    ADD COLUMN add_placement varchar(32) NOT NULL DEFAULT 'follow'");
            }
        });
    }

    public function down(): void
    {
        // Forward-only: publication checkpoints retain their recorded schema.
    }
};
