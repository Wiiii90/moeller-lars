<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('file_creator', 240)->nullable();
            $table->string('file_rights_holder', 240)->nullable();
            $table->text('rights_usage_terms')->nullable();
            $table->string('license_inquiry_url', 2048)->nullable();
        });

        // Both schemas participate in the publication parity/snapshot contract.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.media_assets ADD COLUMN file_creator varchar(240) NULL');
            DB::statement('ALTER TABLE committed.media_assets ADD COLUMN file_rights_holder varchar(240) NULL');
            DB::statement('ALTER TABLE committed.media_assets ADD COLUMN rights_usage_terms text NULL');
            DB::statement('ALTER TABLE committed.media_assets ADD COLUMN license_inquiry_url varchar(2048) NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.media_assets DROP COLUMN license_inquiry_url');
            DB::statement('ALTER TABLE committed.media_assets DROP COLUMN rights_usage_terms');
            DB::statement('ALTER TABLE committed.media_assets DROP COLUMN file_rights_holder');
            DB::statement('ALTER TABLE committed.media_assets DROP COLUMN file_creator');
        }

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropColumn(['file_creator', 'file_rights_holder', 'rights_usage_terms', 'license_inquiry_url']);
        });
    }
};
