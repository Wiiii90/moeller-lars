<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publication_event_states', function (Blueprint $table): void {
            $table->index(
                ['status', 'audit_event_id'],
                'publication_event_states_status_audit_event_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('publication_event_states', function (Blueprint $table): void {
            $table->dropIndex('publication_event_states_status_audit_event_idx');
        });
    }
};
