<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artwork_categories', static function (Blueprint $table): void {
            $table->boolean('artwork_menu_surface_wide')->default(false);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.artwork_categories
                ADD COLUMN artwork_menu_surface_wide boolean NOT NULL DEFAULT false');
        }
    }

    public function down(): void
    {
    }
};
