<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->string('contact_recipient_email', 254)->nullable();
        });

        DB::table('public_content_settings')
            ->where('id', 1)
            ->whereNull('contact_recipient_email')
            ->whereNotNull('public_email')
            ->update(['contact_recipient_email' => DB::raw('public_email')]);
    }

    public function down(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->dropColumn('contact_recipient_email');
        });
    }
};
