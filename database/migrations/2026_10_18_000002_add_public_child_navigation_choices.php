<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.site_sections', function (Blueprint $table): void {
                $table->string('navigation_child_target', 16)->default('gallery');
            });
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                $table->boolean('navigation_hover_preview')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.site_sections', function (Blueprint $table): void {
                $table->dropColumn('navigation_child_target');
            });
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                $table->dropColumn('navigation_hover_preview');
            });
        }
    }
};
