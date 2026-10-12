<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_sections', function (Blueprint $table): void {
            $table->boolean('content_heading_enabled')->default(false);
            $table->string('content_heading_override', 160)->nullable();
            $table->string('content_heading_alignment', 8)->default('left');
            $table->string('content_heading_divider', 8)->default('none');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.site_sections ADD COLUMN IF NOT EXISTS content_heading_enabled boolean NOT NULL DEFAULT false');
            DB::statement('ALTER TABLE committed.site_sections ADD COLUMN IF NOT EXISTS content_heading_override varchar(160) NULL');
            DB::statement("ALTER TABLE committed.site_sections ADD COLUMN IF NOT EXISTS content_heading_alignment varchar(8) NOT NULL DEFAULT 'left'");
            DB::statement("ALTER TABLE committed.site_sections ADD COLUMN IF NOT EXISTS content_heading_divider varchar(8) NOT NULL DEFAULT 'none'");

            foreach (['public', 'committed'] as $schema) {
                DB::statement("ALTER TABLE {$schema}.site_sections ADD CONSTRAINT site_sections_heading_alignment_check CHECK (content_heading_alignment IN ('left', 'center', 'right'))");
                DB::statement("ALTER TABLE {$schema}.site_sections ADD CONSTRAINT site_sections_heading_divider_check CHECK (content_heading_divider IN ('none', 'thin', 'subtle', 'strong', 'dotted'))");
            }
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            foreach (['public', 'committed'] as $schema) {
                DB::statement("ALTER TABLE {$schema}.site_sections DROP CONSTRAINT IF EXISTS site_sections_heading_divider_check");
                DB::statement("ALTER TABLE {$schema}.site_sections DROP CONSTRAINT IF EXISTS site_sections_heading_alignment_check");
            }
            foreach (['content_heading_enabled', 'content_heading_override', 'content_heading_alignment', 'content_heading_divider'] as $column) {
                DB::statement("ALTER TABLE committed.site_sections DROP COLUMN IF EXISTS {$column}");
            }
        }

        Schema::table('site_sections', function (Blueprint $table): void {
            $table->dropColumn([
                'content_heading_enabled', 'content_heading_override',
                'content_heading_alignment', 'content_heading_divider',
            ]);
        });
    }
};
