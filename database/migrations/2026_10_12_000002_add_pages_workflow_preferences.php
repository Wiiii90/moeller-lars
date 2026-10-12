<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->boolean('pages_auto_publish')->default(false);
            $table->boolean('pages_delete_without_confirmation')->default(false);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.public_content_settings ADD COLUMN IF NOT EXISTS pages_auto_publish boolean NOT NULL DEFAULT false');
            DB::statement('ALTER TABLE committed.public_content_settings ADD COLUMN IF NOT EXISTS pages_delete_without_confirmation boolean NOT NULL DEFAULT false');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.public_content_settings DROP COLUMN IF EXISTS pages_delete_without_confirmation');
            DB::statement('ALTER TABLE committed.public_content_settings DROP COLUMN IF EXISTS pages_auto_publish');
        }

        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->dropColumn(['pages_auto_publish', 'pages_delete_without_confirmation']);
        });
    }
};
