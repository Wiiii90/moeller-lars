<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->string('postal_code', 16)->nullable()->after('location_text');
        });

        // Both tracked Publication schemas must change together. Historical
        // snapshots keep their original schema fingerprints; do not silently
        // rewrite immutable Publication checkpoint payloads.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.exhibitions ADD COLUMN postal_code varchar(16) NULL');
        }
    }

    public function down(): void
    {
        // Forward-only for stored editorial address data and Publication
        // history. Do not delete a now-canonical address field on rollback.
    }
};
