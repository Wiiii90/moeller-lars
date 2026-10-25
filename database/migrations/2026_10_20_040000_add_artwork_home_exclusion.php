<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artworks', function (Blueprint $table): void {
            $table->boolean('exclude_from_home')->default(false);
        });

        // Keep the existing Working/Committed Publication parity contract.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.artworks ADD COLUMN exclude_from_home boolean NOT NULL DEFAULT false');
        }
    }

    public function down(): void
    {
        // Forward-only: dropping the column would discard editorial choices.
    }
};
