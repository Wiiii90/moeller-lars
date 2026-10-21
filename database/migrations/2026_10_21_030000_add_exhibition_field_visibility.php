<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FIELDS = ['organizer', 'venue', 'external_url', 'location_text', 'postal_code', 'city', 'country'];

    public function up(): void
    {
        $tables = ['exhibitions'];
        if (DB::connection()->getDriverName() === 'pgsql') {
            $tables[] = 'committed.exhibitions';
        }

        foreach ($tables as $table) {
            Schema::table($table, static function (Blueprint $blueprint): void {
                foreach (self::FIELDS as $field) {
                    $blueprint->boolean('show_'.$field)->default(true);
                }
            });
        }
    }

    public function down(): void
    {
    }
};
