<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('public_page_width')->default(800);
            $table->unsignedSmallInteger('public_content_padding')->default(75);
        });
    }

    public function down(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->dropColumn(['public_page_width', 'public_content_padding']);
        });
    }
};
