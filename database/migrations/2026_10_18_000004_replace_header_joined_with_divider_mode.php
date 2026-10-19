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
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                $table->string('header_divider_mode', 12)->default('fine');
            });
            DB::table($schema.'.public_content_settings')->update([
                'header_divider_mode' => DB::raw("CASE WHEN header_content_joined THEN 'fine' ELSE 'separate' END"),
            ]);
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                $table->dropColumn('header_content_joined');
            });
        }
    }

    public function down(): void
    {
        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                $table->boolean('header_content_joined')->default(true);
            });
            DB::table($schema.'.public_content_settings')->update([
                'header_content_joined' => DB::raw("header_divider_mode = 'fine'"),
            ]);
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                $table->dropColumn('header_divider_mode');
            });
        }
    }
};
