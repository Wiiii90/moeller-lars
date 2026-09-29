<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('admin_activity_ordering_events');
        Schema::dropIfExists('admin_activity_ordering_groups');
        Schema::dropIfExists('admin_activity_ordering_projections');

        Schema::create('admin_activity_ordering_projections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 240);
            $table->string('action', 80);
            $table->string('target_label', 240)->nullable();
            $table->foreignId('first_audit_event_id')->constrained('audit_events')->restrictOnDelete();
            $table->foreignId('last_audit_event_id')->constrained('audit_events')->restrictOnDelete();
            $table->unsignedInteger('event_count')->default(1);
            $table->unsignedInteger('item_count')->default(0);
            $table->jsonb('before_state');
            $table->jsonb('after_state');
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at');

            $table->unique('last_audit_event_id');
            $table->index(['admin_user_id', 'scope', 'action', 'id'], 'activity_ordering_projection_lookup');
        });

        Schema::create('admin_activity_ordering_events', function (Blueprint $table): void {
            $table->foreignId('audit_event_id')->primary()->constrained('audit_events')->restrictOnDelete();
            $table->foreignId('projection_id')->constrained('admin_activity_ordering_projections')->cascadeOnDelete();
            $table->unsignedInteger('sequence');

            $table->unique(['projection_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_ordering_events');
        Schema::dropIfExists('admin_activity_ordering_projections');
    }
};
