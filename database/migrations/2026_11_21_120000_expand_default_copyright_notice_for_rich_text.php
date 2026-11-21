<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Retain existing text while allowing Rich Text markup.
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->text('default_media_copyright_notice')->nullable()->change();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.public_content_settings ALTER COLUMN default_media_copyright_notice TYPE text');
        }
    }

    public function down(): void
    {
        if (DB::table('public_content_settings')
            ->whereRaw('char_length(default_media_copyright_notice) > 500')
            ->exists()) {
            throw new RuntimeException('Cannot shrink the copyright notice without discarding editorial data.');
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            if (DB::table('committed.public_content_settings')
                ->whereRaw('char_length(default_media_copyright_notice) > 500')
                ->exists()) {
                throw new RuntimeException('Cannot shrink the committed copyright notice without discarding published data.');
            }
            DB::statement('ALTER TABLE committed.public_content_settings ALTER COLUMN default_media_copyright_notice TYPE varchar(500)');
        }

        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->string('default_media_copyright_notice', 500)->nullable()->change();
        });
    }
};
