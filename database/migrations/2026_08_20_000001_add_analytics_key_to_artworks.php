<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artworks', function (Blueprint $table): void {
            $table->uuid('analytics_key')->nullable()->unique()->after('slug');
        });

        DB::table('artworks')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($artworks): void {
                foreach ($artworks as $artwork) {
                    DB::table('artworks')
                        ->where('id', $artwork->id)
                        ->update(['analytics_key' => (string) Str::uuid()]);
                }
            });

        Schema::table('artworks', function (Blueprint $table): void {
            $table->uuid('analytics_key')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('artworks', function (Blueprint $table): void {
            $table->dropUnique(['analytics_key']);
            $table->dropColumn('analytics_key');
        });
    }
};
