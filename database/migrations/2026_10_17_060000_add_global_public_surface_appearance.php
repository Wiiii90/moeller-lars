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
                foreach (['menu_background', 'page_background'] as $surface) {
                    $table->string($surface.'_mode', 16)->nullable();
                    $table->string($surface.'_color', 7)->nullable();
                    $table->string($surface.'_gradient_start', 7)->nullable();
                    $table->string($surface.'_gradient_end', 7)->nullable();
                    $table->smallInteger($surface.'_gradient_angle')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                foreach (['menu_background', 'page_background'] as $surface) {
                    $table->dropColumn([
                        $surface.'_mode',
                        $surface.'_color',
                        $surface.'_gradient_start',
                        $surface.'_gradient_end',
                        $surface.'_gradient_angle',
                    ]);
                }
            });
        }
    }
};
