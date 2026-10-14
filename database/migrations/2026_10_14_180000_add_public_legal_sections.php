<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->text('legal_imprint')->nullable();
            $table->text('legal_privacy')->nullable();
            $table->text('legal_usage_rights')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->dropColumn(['legal_imprint', 'legal_privacy', 'legal_usage_rights']);
        });
    }
};
