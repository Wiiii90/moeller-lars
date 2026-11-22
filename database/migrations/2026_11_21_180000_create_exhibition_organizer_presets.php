<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Editor-only presets are not Publication records. Existing public
        // Exhibition text and historic checkpoints remain unchanged.
        Schema::create('exhibition_organizer_presets', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 240)->unique();
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
        });

        // Only exact original organizer names are imported. Composite names
        // are deliberately left distinct; inconsistent venues/addresses are
        // not inferred or changed during migration.
        $seen = [];
        $position = 0;
        foreach (DB::table('exhibitions')->select('organizer')->whereNotNull('organizer')
            ->orderBy('organizer')->cursor() as $row) {
            $name = trim((string) $row->organizer);
            $key = mb_strtolower($name);
            if ($name === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            DB::table('exhibition_organizer_presets')->insert([
                'name' => $name,
                'position' => $position++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }


    }

    public function down(): void
    {
        // Forward only: never discard artist-maintained organizer presets.
    }
};
