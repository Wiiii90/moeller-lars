<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_event_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_event_id')->unique()->constrained('audit_events')->restrictOnDelete();
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');
            $table->string('status', 24);
            $table->timestampTz('updated_at');
            $table->index(['entity_type', 'entity_id', 'status'], 'publication_event_entity_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_event_states');
    }
};
