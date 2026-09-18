<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_presentation_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_section_id')->unique()->constrained('site_sections')->cascadeOnDelete();
            $table->string('template', 32)->default('artwork');
            $table->jsonb('configuration');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE home_presentation_settings ADD CONSTRAINT home_presentation_settings_template_check CHECK (template IN ('artwork', 'under_construction', 'skip_home', 'custom'))");

        $configuration = json_encode([
            'artwork' => [
                'show_details' => true,
                'show_gallery_link' => true,
            ],
            'under_construction' => [
                'public_site_gate' => false,
                'components' => [
                    [
                        'type' => 'image',
                        'media_asset_id' => null,
                        'image_decorative' => true,
                    ],
                    [
                        'type' => 'text',
                        'title' => 'Under construction',
                        'body' => 'The website is currently being updated.',
                    ],
                ],
            ],
            'custom' => [
                'components' => [],
            ],
        ], JSON_THROW_ON_ERROR);

        $now = now();
        foreach (DB::table('site_sections')->where('type', 'home')->pluck('id') as $siteSectionId) {
            DB::table('home_presentation_settings')->insert([
                'site_section_id' => $siteSectionId,
                'template' => 'artwork',
                'configuration' => $configuration,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('home_presentation_settings');
    }
};
