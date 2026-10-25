<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                $table->string('header_divider_paint_mode', 16)->nullable();
                $table->string('header_divider_paint_gradient_start', 7)->nullable();
                $table->string('header_divider_paint_gradient_end', 7)->nullable();
                $table->smallInteger('header_divider_paint_gradient_angle')->nullable();
                $table->jsonb('header_divider_paint_gradient_stops')->nullable();
                $table->boolean('header_divider_paint_gradient_repeating')->default(false);
                $table->unsignedSmallInteger('header_divider_paint_gradient_repeat_span')->default(120);
                $table->unsignedSmallInteger('header_divider_paint_gradient_center_x')->default(50);
                $table->unsignedSmallInteger('header_divider_paint_gradient_center_y')->default(50);
                $table->string('header_divider_paint_gradient_shape', 12)->default('circle');
            });
        }
    }

    public function down(): void
    {
        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                $table->dropColumn([
                    'header_divider_paint_mode',
                    'header_divider_paint_gradient_start',
                    'header_divider_paint_gradient_end',
                    'header_divider_paint_gradient_angle',
                    'header_divider_paint_gradient_stops',
                    'header_divider_paint_gradient_repeating',
                    'header_divider_paint_gradient_repeat_span',
                    'header_divider_paint_gradient_center_x',
                    'header_divider_paint_gradient_center_y',
                    'header_divider_paint_gradient_shape',
                ]);
            });
        }
    }
};
