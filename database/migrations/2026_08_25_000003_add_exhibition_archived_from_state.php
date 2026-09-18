<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->string('archived_from_state', 32)->nullable()->after('state');
        });

        DB::statement("ALTER TABLE exhibitions ADD CONSTRAINT exhibitions_archived_from_state_check CHECK (archived_from_state IS NULL OR archived_from_state IN ('draft', 'published'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE exhibitions DROP CONSTRAINT IF EXISTS exhibitions_archived_from_state_check');

        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->dropColumn('archived_from_state');
        });
    }
};
