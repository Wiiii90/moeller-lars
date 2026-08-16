<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artworks', function (Blueprint $table): void {
            $table->boolean('featured_on_home')->default(false)->after('work_year');
        });
    }

    public function down(): void
    {
        Schema::table('artworks', function (Blueprint $table): void {
            $table->dropColumn('featured_on_home');
        });
    }
};
