<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artworks', function (Blueprint $table): void {
            $table->unsignedSmallInteger('work_year')->nullable()->after('work_date');
            $table->index(['state', 'work_year']);
        });
    }

    public function down(): void
    {
        Schema::table('artworks', function (Blueprint $table): void {
            $table->dropIndex(['state', 'work_year']);
            $table->dropColumn('work_year');
        });
    }
};
