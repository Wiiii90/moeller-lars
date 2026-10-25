<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artwork_categories', function (Blueprint $table): void {
            $table->boolean('show_artwork_information')->default(true);
            $table->boolean('show_media_credits')->default(false);
            $table->boolean('show_media_copyright')->default(false);
            $table->string('caption_layout', 16)->default('rights_left');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE committed.artwork_categories
                ADD COLUMN show_artwork_information boolean NOT NULL DEFAULT true,
                ADD COLUMN show_media_credits boolean NOT NULL DEFAULT false,
                ADD COLUMN show_media_copyright boolean NOT NULL DEFAULT false,
                ADD COLUMN caption_layout varchar(16) NOT NULL DEFAULT 'rights_left'");
        }
    }

    public function down(): void
    {
        // Forward-only: previously committed caption settings remain restorable.
    }
};
