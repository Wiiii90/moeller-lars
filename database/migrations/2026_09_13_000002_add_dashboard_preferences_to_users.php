<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedSmallInteger('dashboard_notification_retention')->default(10);
            $table->boolean('dashboard_delete_without_confirmation')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'dashboard_notification_retention',
                'dashboard_delete_without_confirmation',
            ]);
        });
    }
};
