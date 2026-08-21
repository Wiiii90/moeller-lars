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
            $table->jsonb('social_links')->nullable();
        });

        DB::table('public_content_settings')
            ->whereNotNull('instagram_handle')
            ->orderBy('id')
            ->get(['id', 'instagram_handle', 'show_instagram'])
            ->each(function (object $setting): void {
                $handle = is_string($setting->instagram_handle ?? null) ? trim($setting->instagram_handle) : '';
                if ($handle === '') {
                    return;
                }

                DB::table('public_content_settings')
                    ->where('id', $setting->id)
                    ->update([
                        'social_links' => json_encode([[
                            'platform' => 'instagram',
                            'url' => 'https://www.instagram.com/'.$handle.'/',
                            'visible' => (bool) ($setting->show_instagram ?? true),
                        ]], JSON_THROW_ON_ERROR),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->dropColumn('social_links');
        });
    }
};
