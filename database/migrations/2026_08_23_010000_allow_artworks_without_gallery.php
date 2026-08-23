<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artworks', function (Blueprint $table): void {
            $table->foreignId('artwork_category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('artworks', function (Blueprint $table): void {
            $table->foreignId('artwork_category_id')->nullable(false)->change();
        });
    }
};
