<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = [];
        foreach (DB::table('artwork_material_presets')->orderBy('id')->pluck('name') as $name) {
            if (! is_string($name)) {
                continue;
            }

            $value = trim($name);
            if ($value !== '') {
                $existing[mb_strtolower($value)] = true;
            }
        }

        $historical = [];
        foreach (DB::table('artworks')->whereNotNull('medium')->orderBy('id')->pluck('medium') as $name) {
            if (! is_string($name)) {
                continue;
            }

            $value = trim($name);
            if ($value === '' || mb_strlen($value) > 240) {
                continue;
            }

            $key = mb_strtolower($value);
            $historical[$key] ??= $value;
        }

        $now = now();
        $rows = [];
        foreach ($historical as $key => $name) {
            if (isset($existing[$key])) {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $existing[$key] = true;
        }

        if ($rows !== []) {
            DB::table('artwork_material_presets')->insert($rows);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: after this forward backfill, presets may be
        // artist-managed. Rolling back must not delete potentially intentional data.
    }
};
