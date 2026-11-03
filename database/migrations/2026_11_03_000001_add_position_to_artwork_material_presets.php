<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artwork_material_presets', function (Blueprint $table): void {
            $table->unsignedInteger('position')->default(0);
        });

        // Preserve the currently displayed alphabetical order when introducing
        // explicitly persisted manual ordering.
        $ids = DB::table('artwork_material_presets')->orderBy('name')->orderBy('id')->pluck('id');
        foreach ($ids as $index => $id) {
            DB::table('artwork_material_presets')->where('id', $id)->update(['position' => $index]);
        }
    }

    public function down(): void
    {
        Schema::table('artwork_material_presets', function (Blueprint $table): void {
            $table->dropColumn('position');
        });
    }
};
