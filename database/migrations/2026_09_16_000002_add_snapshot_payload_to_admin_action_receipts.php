<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_action_receipts', function (Blueprint $table): void {
            $table->jsonb('snapshot_payload')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('admin_action_receipts', function (Blueprint $table): void {
            $table->dropColumn('snapshot_payload');
        });
    }
};
