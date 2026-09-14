<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table): void {
            $table->string('type', 64)->default('system')->after('source_id');
            $table->text('action_url')->nullable()->after('body');
            $table->string('action_label', 120)->nullable()->after('action_url');
            $table->string('entity_type', 64)->nullable()->after('action_label');
            $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
            $table->foreignId('audit_event_id')->nullable()->after('entity_id')->constrained('audit_events')->nullOnDelete();
            $table->foreignId('publication_checkpoint_id')->nullable()->after('audit_event_id')->constrained('publication_checkpoints')->nullOnDelete();
            $table->jsonb('metadata')->nullable()->after('publication_checkpoint_id');

            $table->index(['user_id', 'type', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'type', 'created_at']);
            $table->dropIndex(['entity_type', 'entity_id']);
            $table->dropConstrainedForeignId('publication_checkpoint_id');
            $table->dropConstrainedForeignId('audit_event_id');
            $table->dropColumn([
                'type',
                'action_url',
                'action_label',
                'entity_type',
                'entity_id',
                'metadata',
            ]);
        });
    }
};
