<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_presentation_settings', function (Blueprint $table): void {
            $table->boolean('skip_home')->default(false);
            $table->foreignId('skip_target_section_id')
                ->nullable()
                ->constrained('site_sections')
                ->nullOnDelete();
        });

        DB::table('home_presentation_settings')
            ->where('template', 'skip_home')
            ->update([
                'template' => 'artwork',
                'skip_home' => true,
            ]);

        DB::statement('ALTER TABLE home_presentation_settings DROP CONSTRAINT IF EXISTS home_presentation_settings_template_check');
        DB::statement("ALTER TABLE home_presentation_settings ADD CONSTRAINT home_presentation_settings_template_check CHECK (template IN ('artwork', 'under_construction', 'custom'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE home_presentation_settings DROP CONSTRAINT IF EXISTS home_presentation_settings_template_check');
        DB::statement("ALTER TABLE home_presentation_settings ADD CONSTRAINT home_presentation_settings_template_check CHECK (template IN ('artwork', 'under_construction', 'skip_home', 'custom'))");

        DB::table('home_presentation_settings')
            ->where('skip_home', true)
            ->update(['template' => 'skip_home']);

        Schema::table('home_presentation_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('skip_target_section_id');
            $table->dropColumn('skip_home');
        });
    }
};
