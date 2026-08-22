<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->string('default_media_copyright_notice', 500)->nullable();
        });

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('copyright_notice_mode', 16)->default('inherit');
        });

        DB::table('media_assets')
            ->whereNotNull('copyright_notice')
            ->whereRaw("trim(copyright_notice) <> ''")
            ->update(['copyright_notice_mode' => 'override']);

        DB::table('media_assets')
            ->whereNotNull('copyright_notice')
            ->whereRaw("trim(copyright_notice) = ''")
            ->update([
                'copyright_notice' => null,
                'copyright_notice_mode' => 'inherit',
            ]);

        DB::table('public_content_settings')
            ->where('scope', 'general')
            ->whereNull('default_media_copyright_notice')
            ->update(['default_media_copyright_notice' => '© Lars Möller']);
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropColumn('copyright_notice_mode');
        });

        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->dropColumn('default_media_copyright_notice');
        });
    }
};
