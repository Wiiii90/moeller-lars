<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Editor-only presets are not Publication records. Existing public
        // Exhibition text and historic checkpoints remain unchanged.
        Schema::create('exhibition_organizer_presets', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 240)->unique();
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
        });

        Schema::create('exhibition_venue_presets', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_preset_id')->constrained('exhibition_organizer_presets')->cascadeOnDelete();
            $table->string('name', 240);
            $table->string('street', 500)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('city', 160)->nullable();
            $table->string('country', 160)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
        });

        // Only exact original organizer names are imported. Composite names
        // are deliberately left distinct; inconsistent venues/addresses are
        // not inferred or changed during migration.
        $seen = [];
        $position = 0;
        foreach (DB::table('exhibitions')->select('organizer')->whereNotNull('organizer')
            ->orderBy('organizer')->cursor() as $row) {
            $name = trim((string) $row->organizer);
            $key = mb_strtolower($name);
            if ($name === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            DB::table('exhibition_organizer_presets')->insert([
                'name' => $name,
                'position' => $position++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Import only explicitly authored venue names. Exact address variants
        // stay separate: no inferred venue labels, forced country or geocodes.
        $venueSeen = [];
        $positions = [];
        $organizerIds = DB::table('exhibition_organizer_presets')->pluck('id', 'name')->all();
        foreach (DB::table('exhibitions')
            ->select('organizer', 'venue', 'location_text', 'postal_code', 'city', 'country')
            ->whereNotNull('organizer')->whereNotNull('venue')
            ->orderBy('id')->cursor() as $row) {
            $organizer = trim((string) $row->organizer);
            $venue = trim((string) $row->venue);
            $organizerId = $organizerIds[$organizer] ?? null;
            if ($venue === '' || ! is_numeric($organizerId)) {
                continue;
            }
            $values = [
                'name' => $venue,
                'street' => is_string($row->location_text) && trim($row->location_text) !== '' ? trim($row->location_text) : null,
                'postal_code' => is_string($row->postal_code) && trim($row->postal_code) !== '' ? trim($row->postal_code) : null,
                'city' => is_string($row->city) && trim($row->city) !== '' ? trim($row->city) : null,
                'country' => is_string($row->country) && trim($row->country) !== '' ? trim($row->country) : null,
            ];
            $signature = json_encode([(int) $organizerId, ...array_values($values)], JSON_UNESCAPED_UNICODE);
            if (isset($venueSeen[$signature])) {
                continue;
            }
            $venueSeen[$signature] = true;
            $position = $positions[(int) $organizerId] ?? 0;
            DB::table('exhibition_venue_presets')->insert([
                'organizer_preset_id' => (int) $organizerId,
                ...$values,
                'position' => $position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $positions[(int) $organizerId] = $position + 1;
        }
    }

    public function down(): void
    {
        // Forward only: never discard artist-maintained organizer presets.
    }
};
