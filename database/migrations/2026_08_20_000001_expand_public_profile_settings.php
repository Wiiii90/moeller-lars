<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->boolean('show_public_email')->default(true);
            $table->boolean('show_instagram')->default(true);
            $table->jsonb('profile_text_blocks')->nullable();
            $table->foreignId('favicon_media_asset_id')
                ->nullable()
                ->constrained('media_assets')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('favicon_media_asset_id');
            $table->dropColumn(['show_public_email', 'show_instagram', 'profile_text_blocks']);
        });
    }
};
