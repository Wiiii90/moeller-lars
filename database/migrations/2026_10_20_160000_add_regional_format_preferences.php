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
                $table->string('regional_format', 5)->default('de-DE');
            });
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->string('admin_region', 5)->default('de-DE');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('admin_region'));
        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.public_content_settings', fn (Blueprint $table) => $table->dropColumn('regional_format'));
        }
    }
};
