<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the original 2026-11-21 migration. It may already have
        // created organizer-owned Venues in a persistent local database.
        Schema::table('exhibition_organizer_presets', static function (Blueprint $table): void {
            $table->string('street', 500)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('city', 160)->nullable();
            $table->string('country', 160)->nullable();
            $table->string('website', 2048)->nullable();
        });

        if (! Schema::hasTable('exhibition_venue_presets')) {
            // Databases whose original migration ran after the earlier
            // source-only edit have no Venue table yet.
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
        } elseif (Schema::hasColumn('exhibition_venue_presets', 'organizer_preset_id')) {
            // The original table allowed one Venue per Organizer. Check
            // whether its data can be mapped to a globally named Venue
            // without guessing which of two identically named rows to keep.
            $seen = [];
            foreach (DB::table('exhibition_venue_presets')->orderBy('id')->get(['id', 'name']) as $venue) {
                $name = trim((string) $venue->name);
                if ($name === '') {
                    throw new RuntimeException(
                        'Legacy exhibition_venue_presets contains an unnamed Venue (ID '.$venue->id.'). Review it before migrating.',
                    );
                }
                $key = mb_strtolower($name);
                if (isset($seen[$key])) {
                    throw new RuntimeException(
                        'Legacy Venue name "'.$name.'" is used by IDs '.$seen[$key].' and '.$venue->id
                        .'. Reconcile these rows explicitly before converting to an independent Venue directory.',
                    );
                }
                $seen[$key] = (int) $venue->id;
            }

            // Keep IDs, names, addresses, order and timestamps intact.
            // Only the rejected Organizer ownership is retired.
            Schema::table('exhibition_venue_presets', static function (Blueprint $table): void {
                $table->dropConstrainedForeignId('organizer_preset_id');
                $table->string('website', 2048)->nullable();
                $table->unique('name');
            });
        } else {
            // A pre-existing independent directory must already have the
            // canonical shape. Do not silently accept an unrelated table.
            foreach (['name', 'street', 'postal_code', 'city', 'country', 'website', 'position'] as $column) {
                if (! Schema::hasColumn('exhibition_venue_presets', $column)) {
                    throw new RuntimeException(
                        'Existing exhibition_venue_presets is missing "'.$column.'". Review the schema before migrating.',
                    );
                }
            }
        }

        // Reuse existing artist-maintained Venues. Import only the distinct
        // explicitly recorded exhibition Venue names not present already.
        // No Exhibition row, organizer pairing or snapshot is rewritten.
        $known = [];
        foreach (DB::table('exhibition_venue_presets')->select('name')->cursor() as $venue) {
            $key = mb_strtolower(trim((string) $venue->name));
            if ($key !== '') {
                $known[$key] = true;
            }
        }

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
            if (isset($known[$key])) {
                continue;
            }
            if (! isset($groups[$key])) {
                $groups[$key] = ['name' => $name, 'rows' => []];
            }
            $groups[$key]['rows'][] = $entry;
        }

        $lastPosition = DB::table('exhibition_venue_presets')->max('position');
        $position = $lastPosition === null ? 0 : ((int) $lastPosition + 1);
        foreach ($groups as $group) {
            $row = ['name' => $group['name'], 'position' => $position++];
            foreach ([
                'location_text' => 'street',
                'postal_code' => 'postal_code',
                'city' => 'city',
                'country' => 'country',
            ] as $from => $to) {
                $distinct = [];
                foreach ($group['rows'] as $entry) {
                    $value = trim((string) ($entry->{$from} ?? ''));
                    if ($value !== '') {
                        $distinct[$value] = $value;
                    }
                }
                // Conflicting historical values require artist review.
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
        // Forward only: preserve artist-maintained directory data and IDs.
    }
};
