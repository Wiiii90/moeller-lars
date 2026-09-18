<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->string('background_mode', 16)->nullable();
            $table->string('background_color', 7)->nullable();
            $table->string('background_gradient_start', 7)->nullable();
            $table->string('background_gradient_end', 7)->nullable();
            $table->smallInteger('background_gradient_angle')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'background_mode',
                'background_color',
                'background_gradient_start',
                'background_gradient_end',
                'background_gradient_angle',
            ]);
        });
    }
};
