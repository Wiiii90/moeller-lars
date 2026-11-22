<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These are editor-managed directories, never publication records.
        Schema::table('exhibition_organizer_presets', static function (Blueprint $table): void {
            $table->string('street', 500)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('city', 160)->nullable();
            $table->string('country', 160)->nullable();
            $table->string('website', 2048)->nullable();
        });

        Schema::create('exhibition_venue_presets', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 240)->unique();
            $table->string('street', 500)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('city', 160)->nullable();
            $table->string('country', 160)->nullable();
            $table->string('website', 2048)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestampsTz();
        });

        // Import only explicitly entered Venue names. No organizer/venue
        // inference or change to an existing Exhibition is permitted.
        $groups = [];
        foreach (DB::table('exhibitions')
            ->select('venue', 'location_text', 'postal_code', 'city', 'country')
            ->whereNotNull('venue')
            ->orderBy('id')->cursor() as $entry) {
            $name = trim((string) $entry->venue);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (! isset($groups[$key])) {
                $groups[$key] = ['name' => $name, 'rows' => []];
            }
            $groups[$key]['rows'][] = $entry;
        }

        foreach (array_values($groups) as $position => $group) {
            $row = ['name' => $group['name'], 'position' => $position];
            foreach (['location_text' => 'street', 'postal_code' => 'postal_code', 'city' => 'city', 'country' => 'country'] as $from => $to) {
                $distinct = [];
                foreach ($group['rows'] as $entry) {
                    $value = trim((string) ($entry->{$from} ?? ''));
                    if ($value !== '') {
                        $distinct[$value] = $value;
                    }
                }
                // Conflicting historical addresses require artist review;
                // never silently pick an arbitrary location.
                $row[$to] = count($distinct) === 1 ? reset($distinct) : null;
            }
            DB::table('exhibition_venue_presets')->insert([
                ...$row,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Forward only: directory edits and imported names are artist data.
    }
};
