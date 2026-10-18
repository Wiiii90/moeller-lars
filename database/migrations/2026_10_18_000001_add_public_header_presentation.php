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
                $table->string('header_name_position', 12)->default('inside');
                $table->string('header_name_caps', 12)->default('small-caps');
                $table->boolean('header_content_joined')->default(true);
            });
        }
    }

    public function down(): void
    {
        foreach (['public', 'committed'] as $schema) {
            Schema::table($schema.'.public_content_settings', function (Blueprint $table): void {
                $table->dropColumn(['header_name_position', 'header_name_caps', 'header_content_joined']);
            });
        }
    }
};
