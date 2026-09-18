<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->string('gallery_presentation', 24)->default('grid');
        });
    }

    public function down(): void
    {
        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->dropColumn('gallery_presentation');
        });
    }
};
