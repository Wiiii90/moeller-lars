<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_sections')
            ->where('type', 'home')
            ->update([
                'navigation_label' => 'Home',
                'slug' => null,
                'state' => 'published',
                'show_in_navigation' => true,
                'updated_at' => now(),
            ]);

        DB::statement('DROP INDEX IF EXISTS exhibitions_published_position_unique');
    }

    public function down(): void
    {
        DB::statement("CREATE UNIQUE INDEX exhibitions_published_position_unique ON exhibitions (position) WHERE state = 'published'");

        DB::table('site_sections')
            ->where('type', 'home')
            ->update([
                'navigation_label' => null,
                'show_in_navigation' => false,
                'updated_at' => now(),
            ]);
    }
};
