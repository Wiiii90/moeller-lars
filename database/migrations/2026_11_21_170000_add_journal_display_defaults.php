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
            Schema::table('journal_settings', static function (Blueprint $table): void {
                $table->string('default_gallery_presentation', 24)->nullable();
                $table->boolean('default_image_viewer_enabled')->default(false);
                $table->boolean('default_gallery_enabled')->default(false);
                $table->boolean('default_show_vernissage')->default(false);
            });

            return;
        }

        DB::transaction(static function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            foreach (['public', 'committed'] as $schema) {
                DB::statement("ALTER TABLE {$schema}.journal_settings
                    ADD COLUMN default_gallery_presentation varchar(24) NULL,
                    ADD COLUMN default_image_viewer_enabled boolean NOT NULL DEFAULT false,
                    ADD COLUMN default_gallery_enabled boolean NOT NULL DEFAULT false,
                    ADD COLUMN default_show_vernissage boolean NOT NULL DEFAULT false");
            }
        });
    }

    public function down(): void
    {
        // Forward only: keep publication and retained history compatible.
    }
};
