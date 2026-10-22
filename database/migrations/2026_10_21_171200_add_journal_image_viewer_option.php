<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['blog_posts', 'exhibitions'] as $table) {
            Schema::table($table, static function (Blueprint $schema): void {
                $schema->boolean('image_viewer_enabled')->default(false);
            });
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            foreach (['blog_posts', 'exhibitions'] as $table) {
                DB::statement("ALTER TABLE committed.{$table} ADD COLUMN image_viewer_enabled boolean NOT NULL DEFAULT false");
            }
        }
    }

    public function down(): void
    {
    }
};
