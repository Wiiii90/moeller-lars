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
            DB::table('public_content_settings')->whereIn('frame_style', ['layered', 'continuous'])
                ->update(['frame_style' => 'backing']);
            DB::table('public_content_settings')->where('frame_style', 'contours')
                ->update(['frame_style' => 'plain']);
            Schema::table('public_content_settings', static function (Blueprint $table): void {
                $table->string('frame_style', 12)->default('plain')->change();
            });

            return;
        }

        DB::transaction(static function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);

            foreach (['public', 'committed'] as $schema) {
                DB::table($schema.'.public_content_settings')
                    ->whereIn('frame_style', ['layered', 'continuous'])
                    ->update(['frame_style' => 'backing']);
                DB::table($schema.'.public_content_settings')
                    ->where('frame_style', 'contours')
                    ->update(['frame_style' => 'plain']);
                DB::statement("ALTER TABLE {$schema}.public_content_settings
                    ALTER COLUMN frame_style SET DEFAULT 'plain'");
            }
        });
    }

    public function down(): void
    {
        // The retired presentation styles cannot be reconstructed.
        // Historical publication checkpoints remain immutable.
    }
};
