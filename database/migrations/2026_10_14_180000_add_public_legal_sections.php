<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->text('legal_imprint')->nullable();
            $table->text('legal_privacy')->nullable();
            $table->text('legal_usage_rights')->nullable();
        });

        // Publication snapshots compare and copy the full row between public
        // and committed. Both schemas need the exact same column order/types.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.public_content_settings ADD COLUMN IF NOT EXISTS legal_imprint text NULL');
            DB::statement('ALTER TABLE committed.public_content_settings ADD COLUMN IF NOT EXISTS legal_privacy text NULL');
            DB::statement('ALTER TABLE committed.public_content_settings ADD COLUMN IF NOT EXISTS legal_usage_rights text NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.public_content_settings DROP COLUMN IF EXISTS legal_usage_rights');
            DB::statement('ALTER TABLE committed.public_content_settings DROP COLUMN IF EXISTS legal_privacy');
            DB::statement('ALTER TABLE committed.public_content_settings DROP COLUMN IF EXISTS legal_imprint');
        }

        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->dropColumn(['legal_imprint', 'legal_privacy', 'legal_usage_rights']);
        });
    }
};
