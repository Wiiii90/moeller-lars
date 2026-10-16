<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The registry is global and immutable. References are captured in each
        // Working/Committed media row and therefore in publication history.
        Schema::create('media_rights_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('media_asset_id');
            $table->string('role', 16);
            $table->char('revision', 64)->unique();
            $table->string('storage_key', 255)->unique();
            $table->char('source_sha256', 64);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('byte_size');
            $table->string('mime_type', 64);
            $table->timestamp('created_at');
            $table->index(['media_asset_id', 'role']);
        });

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->foreignId('rights_artifact_id')->nullable();
        });
        Schema::table('media_variants', function (Blueprint $table): void {
            $table->foreignId('rights_artifact_id')->nullable();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.media_assets ADD COLUMN rights_artifact_id bigint NULL');
            DB::statement('ALTER TABLE committed.media_variants ADD COLUMN rights_artifact_id bigint NULL');

            foreach (['public', 'committed'] as $schema) {
                foreach (['media_assets', 'media_variants'] as $table) {
                    DB::statement("ALTER TABLE {$schema}.{$table} ADD CONSTRAINT {$table}_rights_artifact_fk FOREIGN KEY (rights_artifact_id) REFERENCES public.media_rights_artifacts(id) ON DELETE RESTRICT");
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            foreach (['public', 'committed'] as $schema) {
                foreach (['media_assets', 'media_variants'] as $table) {
                    DB::statement("ALTER TABLE {$schema}.{$table} DROP CONSTRAINT IF EXISTS {$table}_rights_artifact_fk");
                }
            }
            DB::statement('ALTER TABLE committed.media_variants DROP COLUMN rights_artifact_id');
            DB::statement('ALTER TABLE committed.media_assets DROP COLUMN rights_artifact_id');
        }

        Schema::table('media_variants', fn (Blueprint $table) => $table->dropColumn('rights_artifact_id'));
        Schema::table('media_assets', fn (Blueprint $table) => $table->dropColumn('rights_artifact_id'));
        Schema::dropIfExists('media_rights_artifacts');
    }
};
