<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('sender_name', 160);
            $table->string('sender_email', 320);
            $table->text('message');
            $table->timestampTz('read_at')->nullable();
            $table->string('mail_delivery_status', 24)->default('pending');
            $table->timestampTz('mail_delivered_at')->nullable();
            $table->timestampsTz();

            $table->index(['read_at', 'created_at']);
            $table->index(['mail_delivery_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
