<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE public_content_settings ALTER COLUMN contact_state SET DEFAULT 'enabled'");

        DB::table('public_content_settings')
            ->where('id', 1)
            ->where('contact_state', 'hidden')
            ->update([
                'contact_state' => 'enabled',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE public_content_settings ALTER COLUMN contact_state SET DEFAULT 'hidden'");
    }
};
