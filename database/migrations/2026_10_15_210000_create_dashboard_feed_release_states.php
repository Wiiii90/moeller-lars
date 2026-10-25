<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_feed_release_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entry_key', 160);
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('auto_pin_initialized_at')->nullable();
            $table->boolean('auto_pinned')->default(false);
            $table->timestampsTz();

            $table->unique(['user_id', 'entry_key']);
            $table->index(['user_id', 'auto_pinned']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_feed_release_states');
    }
};
