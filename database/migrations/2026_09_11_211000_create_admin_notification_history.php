<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_id', 96);
            $table->string('status', 24)->default('info');
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'source_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('dashboard_notification_filter', 24)->default('all');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('dashboard_notification_filter');
        });

        Schema::dropIfExists('admin_notifications');
    }
};
