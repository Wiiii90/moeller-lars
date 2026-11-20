<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.public_content_settings', static function (Blueprint $table): void {
                $table->string('time_format', 2)->default('24');
                $table->string('time_zone', 64)->default('UTC');
            });
        }

        Schema::table('users', static function (Blueprint $table): void {
            $table->string('admin_number_format', 5)->default('de-DE');
            $table->string('admin_time_format', 2)->default('24');
            $table->string('admin_time_zone', 64)->default('UTC');
        });

        // The previous single admin region controlled both display formats.
        // Preserve each existing user's number separators when separating them.
        DB::table('users')->update([
            'admin_number_format' => DB::raw("CASE WHEN admin_region = 'de-DE' THEN 'de-DE' ELSE 'en-US' END"),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn(['admin_number_format', 'admin_time_format', 'admin_time_zone']);
        });

        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.public_content_settings', static function (Blueprint $table): void {
                $table->dropColumn(['time_format', 'time_zone']);
            });
        }
    }
};
