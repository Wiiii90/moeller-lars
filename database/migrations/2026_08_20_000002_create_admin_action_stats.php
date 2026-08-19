<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_action_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action_key', 80);
            $table->unsignedBigInteger('use_count')->default(0);
            $table->timestampTz('last_used_at');

            $table->unique(['admin_user_id', 'action_key']);
            $table->index(['admin_user_id', 'last_used_at']);
        });

        DB::statement(<<<'SQL'
            INSERT INTO admin_action_stats (admin_user_id, action_key, use_count, last_used_at)
            SELECT admin_user_id, action, COUNT(*)::bigint, MAX(occurred_at)
            FROM audit_events
            WHERE admin_user_id IS NOT NULL
            GROUP BY admin_user_id, action
            ON CONFLICT (admin_user_id, action_key) DO NOTHING
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_action_stats');
    }
};
