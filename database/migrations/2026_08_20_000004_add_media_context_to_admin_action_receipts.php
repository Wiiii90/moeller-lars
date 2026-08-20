<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_action_receipts', function (Blueprint $table): void {
            $table->unsignedBigInteger('media_asset_id')->nullable();
            $table->unsignedBigInteger('artwork_media_id')->nullable();
            $table->unsignedBigInteger('neighbor_artwork_media_id')->nullable();
            $table->unsignedBigInteger('previous_artwork_media_id')->nullable();
            $table->unsignedBigInteger('next_artwork_media_id')->nullable();
            $table->unsignedInteger('before_position')->nullable();
            $table->unsignedInteger('after_position')->nullable();
            $table->string('inverse_direction', 8)->nullable();

            $table->index(['entity_type', 'entity_id', 'media_asset_id']);
            $table->index('artwork_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('admin_action_receipts', function (Blueprint $table): void {
            $table->dropIndex(['entity_type', 'entity_id', 'media_asset_id']);
            $table->dropIndex(['artwork_media_id']);
            $table->dropColumn([
                'media_asset_id',
                'artwork_media_id',
                'neighbor_artwork_media_id',
                'previous_artwork_media_id',
                'next_artwork_media_id',
                'before_position',
                'after_position',
                'inverse_direction',
            ]);
        });
    }
};
