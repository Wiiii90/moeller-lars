<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->boolean('navigation_nesting_enabled')->default(true);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.public_content_settings ADD COLUMN IF NOT EXISTS navigation_nesting_enabled boolean NOT NULL DEFAULT true');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.public_content_settings DROP COLUMN IF EXISTS navigation_nesting_enabled');
        }

        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->dropColumn('navigation_nesting_enabled');
        });
    }
};
