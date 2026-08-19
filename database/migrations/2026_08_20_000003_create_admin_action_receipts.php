<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_action_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_event_id')->unique()->constrained('audit_events')->restrictOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action_key', 80);
            $table->string('inverse_action_key', 80);
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id');
            $table->string('before_state', 40);
            $table->string('after_state', 40);
            $table->unsignedSmallInteger('receipt_version')->default(1);
            $table->timestampTz('expires_at');
            $table->timestampTz('undone_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['admin_user_id', 'expires_at', 'undone_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_action_receipts');
    }
};
