<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No publication-snapshot column additions: existing checkpoint
        // schema hashes, payloads and historical Undo stay valid.
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

        // 'working' is mutable; 'checkpoint:<id>' is append-only.
        // The existing publication owner will select and retain these rows.
        Schema::create('media_rights_artifact_selections', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key', 64);
            $table->unsignedBigInteger('media_asset_id');
            $table->string('role', 16);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('media_rights_artifact_id')
                ->constrained('media_rights_artifacts')->restrictOnDelete();
            $table->unique(['scope_key', 'role', 'source_id'], 'media_rights_selection_unique');
            $table->index(['media_asset_id', 'scope_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_rights_artifact_selections');
        Schema::dropIfExists('media_rights_artifacts');
    }
};
