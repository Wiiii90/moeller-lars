<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->string('organizer', 240)->nullable()->after('venue');
            $table->boolean('show_vernissage')->default(false)->after('vernissage_at');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.exhibitions ADD COLUMN organizer varchar(240) NULL');
            DB::statement('ALTER TABLE committed.exhibitions ADD COLUMN show_vernissage boolean NOT NULL DEFAULT false');
        }

        // Only legacy Vita rows have a provable source: organisation -> venue.
        // Newer editorial venue values may be organizers OR places; never guess.
        foreach (['exhibitions', ...(DB::connection()->getDriverName() === 'pgsql' ? ['committed.exhibitions'] : [])] as $table) {
            DB::table($table)
                ->where('legacy_source', 'legacy-public-vita')
                ->whereNotNull('venue')
                ->whereRaw("btrim(venue) <> ''")
                ->update(['organizer' => DB::raw('venue'), 'venue' => null]);

            DB::table($table)
                ->where(function ($query): void {
                    $query->whereNotNull('vernissage_at')
                        ->orWhereRaw("NULLIF(btrim(opening_text), '') IS NOT NULL");
                })
                ->update(['show_vernissage' => true]);
        }
    }

    public function down(): void
    {
        // Forward only: do not discard migrated organizer text, published states,
        // or historic Publication checkpoint compatibility.
    }
};
