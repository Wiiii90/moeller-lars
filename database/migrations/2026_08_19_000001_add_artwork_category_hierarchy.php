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
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('artwork_categories')
                ->restrictOnDelete();
        });

        DB::statement('DROP INDEX IF EXISTS artwork_categories_navigation_position_unique');
        DB::statement('ALTER TABLE artwork_categories ADD CONSTRAINT artwork_categories_parent_not_self_check CHECK (parent_id IS NULL OR parent_id <> id)');
        DB::statement("CREATE UNIQUE INDEX artwork_categories_top_level_navigation_position_unique ON artwork_categories (position) WHERE parent_id IS NULL AND state = 'published' AND show_in_navigation = true");
        DB::statement("CREATE UNIQUE INDEX artwork_categories_child_navigation_position_unique ON artwork_categories (parent_id, position) WHERE parent_id IS NOT NULL AND state = 'published' AND show_in_navigation = true");
        DB::statement('CREATE INDEX artwork_categories_parent_position_index ON artwork_categories (parent_id, position, id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS artwork_categories_parent_position_index');
        DB::statement('DROP INDEX IF EXISTS artwork_categories_child_navigation_position_unique');
        DB::statement('DROP INDEX IF EXISTS artwork_categories_top_level_navigation_position_unique');
        DB::statement('ALTER TABLE artwork_categories DROP CONSTRAINT IF EXISTS artwork_categories_parent_not_self_check');

        Schema::table('artwork_categories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_id');
        });

        DB::statement("CREATE UNIQUE INDEX artwork_categories_navigation_position_unique ON artwork_categories (position) WHERE state = 'published' AND show_in_navigation = true");
    }
};
