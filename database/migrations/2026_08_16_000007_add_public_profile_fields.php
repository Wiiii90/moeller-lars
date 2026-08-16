<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->string('public_email', 254)->nullable();
            $table->string('instagram_handle', 30)->nullable();
            $table->text('legal_disclaimer')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->dropColumn(['public_email', 'instagram_handle', 'legal_disclaimer']);
        });
    }
};
