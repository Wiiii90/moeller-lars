<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('pages_delete_without_confirmation')->default(false);
        });

        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                $table->dropColumn('navigation_hover_preview');
            });
        }
    }

    public function down(): void
    {
        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                $table->boolean('navigation_hover_preview')->default(false);
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('pages_delete_without_confirmation');
        });
    }
};
