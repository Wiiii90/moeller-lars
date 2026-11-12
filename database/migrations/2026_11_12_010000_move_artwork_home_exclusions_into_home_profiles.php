<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Retire the Gallery Artwork Home flag as a live source of truth.
        // Preserve existing exclusions in Home's two automatic source profiles
        // before the public resolver stops consulting the old Artwork column.
        $schemas = DB::connection()->getDriverName() === 'pgsql'
            ? ['public', 'committed'] : [null];
        foreach ($schemas as $schema) {
            $artworksTable = $schema === null ? 'artworks' : $schema.'.artworks';
            $settingsTable = $schema === null ? 'home_presentation_settings' : $schema.'.home_presentation_settings';
            $excluded = DB::table($artworksTable)
                ->where('exclude_from_home', true)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            if ($excluded === []) {
                continue;
            }

            foreach (DB::table($settingsTable)->get(['id', 'configuration']) as $row) {
                $root = json_decode((string) $row->configuration, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($root)) {
                    continue;
                }

                $home = is_array($root['artwork'] ?? null) ? $root['artwork'] : [];
                $profiles = is_array($home['source_profiles'] ?? null) ? $home['source_profiles'] : [];
                foreach (['automatic', 'semi_automatic'] as $mode) {
                    $profile = is_array($profiles[$mode] ?? null) ? $profiles[$mode] : [];
                    $saved = is_array($profile['disabled_auto_artwork_ids'] ?? null)
                        ? $profile['disabled_auto_artwork_ids']
                        : (is_array($home['disabled_auto_artwork_ids'] ?? null) ? $home['disabled_auto_artwork_ids'] : []);
                    $profile['disabled_auto_artwork_ids'] = array_values(array_unique(array_merge(
                        array_map('intval', $saved),
                        $excluded,
                    )));
                    sort($profile['disabled_auto_artwork_ids']);
                    $profiles[$mode] = $profile;
                }

                $home['source_profiles'] = $profiles;
                $root['artwork'] = $home;
                DB::table($settingsTable)->where('id', $row->id)->update([
                    'configuration' => json_encode($root, JSON_THROW_ON_ERROR),
                ]);
            }
        }

        // Keep the historical column in Working/Committed for publication
        // snapshots. It is no longer consulted or exposed by the current
        // Home or Gallery editors. Do not discard snapshot metadata.
    }

    public function down(): void
    {
        // One-way data ownership transfer: removing Home-managed exclusions
        // would lose legitimate editorial decisions.
    }
};
