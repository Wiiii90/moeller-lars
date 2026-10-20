<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const EXHIBITION_TITLE = 'Der blonde Hans';
    private const LEGACY_CITY = 'Hamburg';
    private const ORIGINAL_LOCATION_DESCRIPTION = 'Hans Albers Eck, Hans-Albers-Platz 20 & Hans-Albers-Klause, Friedrichstraße 19.';

    public function up(): void
    {
        // The 2026-08-18 Vita import preserved the original two-location
        // address in description while location_text contained only a city.
        // Reclassify only the verifiable, unedited original values. Preserve
        // description itself and never guess a single map pin for two venues.
        foreach (['public.exhibitions', 'committed.exhibitions'] as $table) {
            DB::table($table)
                ->where('legacy_source', 'legacy-public-vita')
                ->where('title', self::EXHIBITION_TITLE)
                ->where('description', self::ORIGINAL_LOCATION_DESCRIPTION)
                ->where(function ($query): void {
                    $query->whereNull('location_text')
                        ->orWhere('location_text', self::LEGACY_CITY);
                })
                ->where(function ($query): void {
                    $query->whereNull('city')
                        ->orWhere('city', self::LEGACY_CITY);
                })
                ->where('map_enabled', false)
                ->update([
                    'location_text' => self::ORIGINAL_LOCATION_DESCRIPTION,
                    'city' => self::LEGACY_CITY,
                ]);
        }
    }

    public function down(): void
    {
        // Non-destructive: never undo an artist's subsequent editorial edits.
    }
};
