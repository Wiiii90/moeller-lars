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
            $table->boolean('landscape_detail_enabled')->default(false);
            $table->unsignedSmallInteger('landscape_detail_scale')->default(150);
            $table->string('edge_navigation_mode', 16)->default('disabled');
        });

        // Gallery settings are part of the Committed snapshot: identical
        // PostgreSQL columns are required by PublicationSchemaGuard.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE committed.artwork_categories
                ADD COLUMN landscape_detail_enabled boolean NOT NULL DEFAULT false,
                ADD COLUMN landscape_detail_scale smallint NOT NULL DEFAULT 150,
                ADD COLUMN edge_navigation_mode varchar(16) NOT NULL DEFAULT 'disabled'");
        }
    }

    public function down(): void
    {
        // Forward-only: do not discard the published editorial settings.
    }
};
