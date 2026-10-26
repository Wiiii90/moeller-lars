<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_media', function (Blueprint $table): void {
            $table->boolean('video_controls_enabled')->default(true);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE committed.journal_entry_media
                ADD COLUMN video_controls_enabled boolean NOT NULL DEFAULT true");
        }
    }

    public function down(): void
    {
    }
};
