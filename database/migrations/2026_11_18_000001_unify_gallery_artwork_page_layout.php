<?php

use App\Domain\Publication\PublicationSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            Schema::table('artwork_categories', static function (Blueprint $table): void {
                $table->string('artwork_page_layout', 16)->default('standard');
            });
            DB::table('artwork_categories')->where(function ($query): void {
                $query->where('landscape_detail_enabled', true)
                    ->orWhere('portrait_detail_enabled', true);
            })->update(['artwork_page_layout' => 'expanded']);
            Schema::table('artwork_categories', static function (Blueprint $table): void {
                $table->dropColumn([
                    'landscape_detail_enabled', 'landscape_detail_scale',
                    'portrait_detail_enabled', 'artwork_page_surface_mode',
                    'artwork_menu_surface_wide',
                ]);
            });

            return;
        }

        DB::transaction(static function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);

            foreach (['public', 'committed'] as $schema) {
                DB::statement("ALTER TABLE {$schema}.artwork_categories
                    ADD COLUMN artwork_page_layout varchar(16) NOT NULL DEFAULT 'standard'");
                DB::statement("UPDATE {$schema}.artwork_categories
                    SET artwork_page_layout = CASE
                        WHEN COALESCE(landscape_detail_enabled, false)
                          OR COALESCE(portrait_detail_enabled, false)
                        THEN 'expanded' ELSE 'standard' END");
                DB::statement("ALTER TABLE {$schema}.artwork_categories
                    DROP COLUMN landscape_detail_enabled,
                    DROP COLUMN landscape_detail_scale,
                    DROP COLUMN portrait_detail_enabled,
                    DROP COLUMN artwork_page_surface_mode,
                    DROP COLUMN artwork_menu_surface_wide");
            }
        });
    }

    public function down(): void
    {
        // One-way editorial decision: old independent layout combinations
        // cannot be reconstructed from one intentional Standard/Expanded mode.
        // Historical checkpoints remain immutable and are projected on read.
    }
};
