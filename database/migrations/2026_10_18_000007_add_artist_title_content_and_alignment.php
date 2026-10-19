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
                $table->boolean('header_name_visible')->default(true);
                $table->string('header_name_text', 100)->default('Lars Möller');
                $table->string('header_name_alignment', 12)->default('left');
                $table->string('header_name_mobile_alignment', 12)->default('center');
            });
        }
    }

    public function down(): void
    {
        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                $table->dropColumn([
                    'header_name_visible', 'header_name_text',
                    'header_name_alignment', 'header_name_mobile_alignment',
                ]);
            });
        }
    }
};
