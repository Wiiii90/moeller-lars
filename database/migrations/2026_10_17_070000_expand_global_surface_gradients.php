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
                foreach (['background', 'menu_background', 'page_background'] as $surface) {
                    $table->jsonb($surface.'_gradient_stops')->nullable();
                    $table->boolean($surface.'_gradient_repeating')->default(false);
                    $table->unsignedSmallInteger($surface.'_gradient_repeat_span')->default(120);
                    $table->unsignedSmallInteger($surface.'_gradient_center_x')->default(50);
                    $table->unsignedSmallInteger($surface.'_gradient_center_y')->default(50);
                    $table->string($surface.'_gradient_shape', 12)->default('circle');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                foreach (['background', 'menu_background', 'page_background'] as $surface) {
                    $table->dropColumn([
                        $surface.'_gradient_stops',
                        $surface.'_gradient_repeating',
                        $surface.'_gradient_repeat_span',
                        $surface.'_gradient_center_x',
                        $surface.'_gradient_center_y',
                        $surface.'_gradient_shape',
                    ]);
                }
            });
        }
    }
};
