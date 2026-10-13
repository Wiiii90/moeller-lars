<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->string('gallery_presentation', 24)->default('sequence');
        });

        // Publication schemas must keep the same tracked columns.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE committed.blog_posts ADD COLUMN gallery_presentation varchar(24) NOT NULL DEFAULT 'sequence'");
        }
    }

    public function down(): void
    {
        // Forward-only: published editorial presentation and checkpoint
        // schemas must not silently lose an authored gallery setting.
    }
};
