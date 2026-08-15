<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('artwork_categories')->insertOrIgnore([
            ['slug' => 'paintings', 'name' => 'Paintings', 'state' => 'published', 'position' => 0, 'description' => null, 'legacy_id' => null, 'legacy_source' => null, 'migration_batch_id' => null, 'migrated_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'prints', 'name' => 'Prints', 'state' => 'published', 'position' => 1, 'description' => null, 'legacy_id' => null, 'legacy_source' => null, 'migration_batch_id' => null, 'migrated_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'drawings', 'name' => 'Drawings', 'state' => 'published', 'position' => 2, 'description' => null, 'legacy_id' => null, 'legacy_source' => null, 'migration_batch_id' => null, 'migrated_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'cyanotype', 'name' => 'Cyanotype', 'state' => 'published', 'position' => 3, 'description' => null, 'legacy_id' => null, 'legacy_source' => null, 'migration_batch_id' => null, 'migrated_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'bichromate', 'name' => 'Salt Print & Gum Bichromate', 'state' => 'published', 'position' => 4, 'description' => null, 'legacy_id' => null, 'legacy_source' => null, 'migration_batch_id' => null, 'migrated_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'litho', 'name' => 'Etching & Lithography', 'state' => 'published', 'position' => 5, 'description' => null, 'legacy_id' => null, 'legacy_source' => null, 'migration_batch_id' => null, 'migrated_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'photo', 'name' => 'Photography', 'state' => 'published', 'position' => 6, 'description' => null, 'legacy_id' => null, 'legacy_source' => null, 'migration_batch_id' => null, 'migrated_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'ignis', 'name' => 'Ignis-Serial', 'state' => 'published', 'position' => 7, 'description' => null, 'legacy_id' => null, 'legacy_source' => null, 'migration_batch_id' => null, 'migrated_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'other', 'name' => 'Other Photography', 'state' => 'published', 'position' => 8, 'description' => null, 'legacy_id' => null, 'legacy_source' => null, 'migration_batch_id' => null, 'migrated_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $categories = [
            ['slug' => 'paintings', 'name' => 'Paintings', 'position' => 0],
            ['slug' => 'prints', 'name' => 'Prints', 'position' => 1],
            ['slug' => 'drawings', 'name' => 'Drawings', 'position' => 2],
            ['slug' => 'cyanotype', 'name' => 'Cyanotype', 'position' => 3],
            ['slug' => 'bichromate', 'name' => 'Salt Print & Gum Bichromate', 'position' => 4],
            ['slug' => 'litho', 'name' => 'Etching & Lithography', 'position' => 5],
            ['slug' => 'photo', 'name' => 'Photography', 'position' => 6],
            ['slug' => 'ignis', 'name' => 'Ignis-Serial', 'position' => 7],
            ['slug' => 'other', 'name' => 'Other Photography', 'position' => 8],
        ];

        foreach ($categories as $category) {
            DB::table('artwork_categories')
                ->where('slug', $category['slug'])
                ->where('name', $category['name'])
                ->where('state', 'published')
                ->where('position', $category['position'])
                ->whereNull('description')
                ->whereNull('legacy_id')
                ->whereNull('legacy_source')
                ->whereNull('migration_batch_id')
                ->whereNull('migrated_at')
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('artworks')->whereColumn('artworks.artwork_category_id', 'artwork_categories.id'))
                ->delete();
        }
    }
};
