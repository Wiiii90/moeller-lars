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
                $table->string('header_divider_color', 7)->nullable();
                $table->boolean('header_name_auto_scale')->default(true);
                $table->boolean('page_surface_full_width')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                $table->dropColumn(['header_divider_color', 'header_name_auto_scale', 'page_surface_full_width']);
            });
        }
    }
};
