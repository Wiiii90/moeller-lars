<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artwork_categories', function (Blueprint $table): void {
            $table->string('artwork_divider', 16)->default('none');
        });

        // Gallery settings participate in the committed Publication snapshot.
        // Schema parity is required before publishing the new column.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE committed.artwork_categories
                ADD COLUMN artwork_divider varchar(16) NOT NULL DEFAULT 'none'");
        }
    }

    public function down(): void
    {
        // Forward-only: published editorial versions must remain restorable.
    }
};
