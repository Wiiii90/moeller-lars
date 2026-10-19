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
            $table->string('sort_mode', 16)->default('manual');
        });

        // PublicationSnapshot requires matching Working/Committed columns.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE committed.artwork_categories
                ADD COLUMN sort_mode varchar(16) NOT NULL DEFAULT 'manual'");
        }
    }

    public function down(): void
    {
        // Forward-only: preserve historical publication order settings.
    }
};
