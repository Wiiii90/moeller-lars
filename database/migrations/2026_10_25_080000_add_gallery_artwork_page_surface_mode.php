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
            $table->string('artwork_page_surface_mode', 16)->default('inherit');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE committed.artwork_categories
                ADD COLUMN artwork_page_surface_mode varchar(16) NOT NULL DEFAULT 'inherit'");
        }
    }

    public function down(): void
    {
    }
};
