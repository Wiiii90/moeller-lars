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
                $table->string('number_format', 5)->default('de-DE');
            });

            DB::table($schema.'.public_content_settings')->update([
                'number_format' => DB::raw("CASE WHEN regional_format = 'de-DE' THEN 'de-DE' ELSE 'en-US' END"),
            ]);
        }
    }

    public function down(): void
    {
        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                $table->dropColumn('number_format');
            });
        }
    }
};
